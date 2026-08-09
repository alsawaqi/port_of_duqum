<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

/**
 * Atomically validates and records QR movements. The locked, indexed movement
 * query prevents simultaneous/replayed entry or exit requests from both being
 * accepted for the same pass holder.
 */
final class Gate_pass_scan_recorder
{
    private const MAX_NOTE_LENGTH = 1000;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: db_connect();
    }

    /**
     * @param list<int|null> $visitorIds
     * @return array{success:bool,status_code:int,message:string,saved_count:int}
     */
    public function record(
        int $gatePassId,
        int $expectedRequestId,
        array $visitorIds,
        string $action,
        ?int $securityUserId,
        int $performedBy,
        string $note,
        string $ipAddress,
        string $userAgent
    ): array {
        $action = strtolower(trim($action));
        if (!in_array($action, ['entry', 'exit', 'check'], true)) {
            return $this->failure(422, 'The requested gate movement is invalid.');
        }

        $note = mb_substr(trim($note), 0, self::MAX_NOTE_LENGTH);
        $passes = $this->db->prefixTable('gate_passes');
        $requests = $this->db->prefixTable('gate_pass_requests');
        $visitors = $this->db->prefixTable('gate_pass_request_visitors');
        $logs = $this->db->prefixTable('gate_pass_scan_log');
        $recordedAt = get_current_utc_time();

        $this->db->transBegin();
        try {
            $pass = $this->db->query(
                "SELECT gp.*, req.stage AS request_stage, req.deleted AS request_deleted,
                        req.visit_from AS request_valid_from, req.visit_to AS request_valid_to
                 FROM {$passes} gp
                 INNER JOIN {$requests} req ON req.id = gp.gate_pass_request_id
                 WHERE gp.id = ? AND gp.deleted = 0
                 LIMIT 1 FOR UPDATE",
                [$gatePassId]
            )->getRow();

            if (!$pass
                || (int)$pass->gate_pass_request_id !== $expectedRequestId
                || (int)$pass->request_deleted === 1
            ) {
                throw new DomainException('Gate pass not found.', 404);
            }
            if ((string)$pass->request_stage !== 'issued' || (string)$pass->status !== 'active') {
                throw new DomainException('Gate pass is not active and issued.', 409);
            }

            $validity = gate_pass_validity_status(
                ($pass->request_valid_from ?? null) ?: ($pass->valid_from ?? null),
                ($pass->request_valid_to ?? null) ?: ($pass->valid_to ?? null),
                $recordedAt
            );
            if (empty($validity['is_valid'])) {
                throw new DomainException((string)$validity['message'], 409);
            }

            $visitorRows = $this->resolveVisitors(
                $visitors,
                $expectedRequestId,
                (int)($pass->gate_pass_request_visitor_id ?? 0),
                $visitorIds
            );
            if ($action === 'entry') {
                foreach ($visitorRows as $visitor) {
                    if ($visitor !== null && (int)($visitor->is_blocked ?? 0) === 1) {
                        throw new DomainException('Entry is blocked for one or more selected visitors.', 409);
                    }
                }
            }

            foreach ($visitorRows as $visitor) {
                $visitorId = $visitor === null ? null : (int)$visitor->id;
                $latest = $this->db->query(
                    "SELECT action FROM {$logs}
                     WHERE gate_pass_id = ?
                       AND gate_pass_request_visitor_id <=> ?
                       AND action IN ('entry', 'exit')
                     ORDER BY recorded_at DESC, id DESC
                     LIMIT 1 FOR UPDATE",
                    [$gatePassId, $visitorId]
                )->getRow();
                $transition = gate_pass_scan_transition($latest->action ?? null, $action);
                if (empty($transition['allowed'])) {
                    $label = $visitor === null
                        ? 'this pass'
                        : (trim((string)($visitor->full_name ?? '')) ?: 'the selected visitor');
                    throw new DomainException($transition['message'] . ' Affected: ' . $label . '.', 409);
                }
            }

            $savedCount = 0;
            foreach ($visitorRows as $visitor) {
                $visitorId = $visitor === null ? null : (int)$visitor->id;
                $saved = $this->db->table($logs)->insert([
                    'gate_pass_request_id' => $expectedRequestId,
                    'gate_pass_id' => $gatePassId,
                    'gate_pass_request_visitor_id' => $visitorId,
                    'security_user_id' => $securityUserId ?: null,
                    'action' => $action,
                    'note' => $note !== '' ? $note : null,
                    'recorded_at' => $recordedAt,
                    'performed_by' => $performedBy,
                    'ip_address' => mb_substr($ipAddress, 0, 100),
                    'user_agent' => mb_substr($userAgent, 0, 500),
                    'created_at' => $recordedAt,
                ]);
                if (!$saved) {
                    throw new \RuntimeException('Unable to write the gate scan log.');
                }
                $savedCount++;
            }

            $meta = json_decode((string)($pass->meta ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $meta['security'] = is_array($meta['security'] ?? null) ? $meta['security'] : [];
            $meta['security']['scan_count'] = (int)($meta['security']['scan_count'] ?? 0) + 1;
            $meta['security']['last_scan_at'] = $recordedAt;
            $meta['security']['last_scan_by'] = $performedBy;
            $meta['security']['last_scan_action'] = $action;
            $meta['security']['last_scan_visitor_ids'] = array_values(array_map(
                static fn($row) => $row === null ? null : (int)$row->id,
                $visitorRows
            ));

            $updated = $this->db->table($passes)->where('id', $gatePassId)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => $recordedAt,
            ]);
            if (!$updated || $this->db->transStatus() === false) {
                throw new \RuntimeException('Unable to finalize the gate scan.');
            }

            $this->db->transCommit();
            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'Gate action recorded for ' . $savedCount . ' visitor' . ($savedCount === 1 ? '' : 's') . '.',
                'saved_count' => $savedCount,
            ];
        } catch (DomainException $exception) {
            $this->db->transRollback();
            return $this->failure($exception->getCode() ?: 409, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->db->transRollback();
            log_message('error', 'GATE PASS SCAN RECORDING FAILED: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return $this->failure(500, 'The gate action could not be recorded. Please try again.');
        }
    }

    /** @return list<object|null> */
    private function resolveVisitors(string $table, int $requestId, int $assignedVisitorId, array $visitorIds): array
    {
        if ($assignedVisitorId > 0) {
            $row = $this->db->query(
                "SELECT id, full_name, is_blocked FROM {$table}
                 WHERE id = ? AND gate_pass_request_id = ? AND deleted = 0
                 LIMIT 1 FOR UPDATE",
                [$assignedVisitorId, $requestId]
            )->getRow();
            if (!$row) {
                throw new DomainException('The assigned pass holder is no longer available.', 409);
            }
            return [$row];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $visitorIds))));
        if (!$ids) {
            $hasVisitors = $this->db->query(
                "SELECT id FROM {$table} WHERE gate_pass_request_id = ? AND deleted = 0 LIMIT 1 FOR UPDATE",
                [$requestId]
            )->getRow();
            if ($hasVisitors) {
                throw new DomainException('Select at least one visitor for this scan action.', 422);
            }
            return [null];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $params = array_merge([$requestId], $ids);
        $rows = $this->db->query(
            "SELECT id, full_name, is_blocked FROM {$table}
             WHERE gate_pass_request_id = ? AND deleted = 0 AND id IN ({$placeholders})
             ORDER BY id ASC FOR UPDATE",
            $params
        )->getResult();
        if (count($rows) !== count($ids)) {
            throw new DomainException('One or more selected visitors are invalid.', 422);
        }

        return $rows;
    }

    private function failure(int $statusCode, string $message): array
    {
        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
            'saved_count' => 0,
        ];
    }
}
