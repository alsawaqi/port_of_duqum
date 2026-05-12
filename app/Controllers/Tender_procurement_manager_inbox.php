<?php

namespace App\Controllers;

use App\Models\Tender_bid_requirements_model;
use App\Models\Tender_communications_model;
use App\Models\Tender_procurement_manager_users_model;
use App\Models\Tender_rfq_details_model;
use App\Models\Tender_rfq_items_model;
use App\Models\Tenders_model;
use App\Libraries\Tender_testing_stage;

class Tender_procurement_manager_inbox extends Security_Controller
{
    protected $db;
    protected $Tenders_model;
    protected $Tender_bid_requirements_model;
    protected $Tender_communications_model;
    protected $Tender_procurement_manager_users_model;
    protected $Tender_rfq_details_model;
    protected $Tender_rfq_items_model;
    protected $Tender_testing_stage;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->db = db_connect();
        $this->Tenders_model = new Tenders_model();
        $this->Tender_bid_requirements_model = new Tender_bid_requirements_model();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->Tender_procurement_manager_users_model = new Tender_procurement_manager_users_model();
        $this->Tender_rfq_details_model = new Tender_rfq_details_model();
        $this->Tender_rfq_items_model = new Tender_rfq_items_model();
        $this->Tender_testing_stage = new Tender_testing_stage();
    }

    public function index()
    {
        $this->access_only_tender("procurement_manager_inbox", "view");
        return $this->template->rander("tender_procurement_manager_inbox/index");
    }

    public function list_data()
    {
        $this->access_only_tender("procurement_manager_inbox", "view");

        $t = $this->db->prefixTable("tenders");
        $companies = $this->db->prefixTable("companies");
        $departments = $this->db->prefixTable("departments");
        $submitter = $this->db->prefixTable("users");
        $reviewer = $this->db->prefixTable("users");
        $manager = $this->db->prefixTable("tender_procurement_manager_users");

        $params = [];
        $scope = "";
        if (!$this->login_user->is_admin) {
            $scope = " AND EXISTS (
                SELECT 1 FROM $manager manager_scope
                WHERE manager_scope.deleted=0
                  AND manager_scope.status='active'
                  AND manager_scope.user_id=?
                  AND manager_scope.company_id=$t.company_id
            )";
            $params[] = (int)$this->login_user->id;
        }

        $sql = "SELECT $t.*,
                    company.name AS company_name,
                    department.name AS department_name,
                    CONCAT(submitter.first_name, ' ', submitter.last_name) AS submitted_by_name,
                    CONCAT(reviewer.first_name, ' ', reviewer.last_name) AS reviewed_by_name
                FROM $t
                LEFT JOIN $companies company ON company.id=$t.company_id AND company.deleted=0
                LEFT JOIN $departments department ON department.id=$t.department_id AND department.deleted=0
                LEFT JOIN $submitter submitter ON submitter.id=$t.procurement_manager_submitted_by AND submitter.deleted=0
                LEFT JOIN $reviewer reviewer ON reviewer.id=$t.procurement_manager_reviewed_by AND reviewer.deleted=0
                WHERE $t.deleted=0
                  AND (
                        $t.status='draft'
                        OR COALESCE($t.procurement_manager_action, 'initial') IN ('update', 'cancel', 'stage_override')
                  )
                  AND $t.procurement_manager_status IN ('pending', 'approved', 'rejected', 'revision_requested')
                  $scope
                ORDER BY
                  FIELD($t.procurement_manager_status, 'pending', 'revision_requested', 'rejected', 'approved'),
                  $t.procurement_manager_submitted_at DESC,
                  $t.id DESC";

        $rows = $this->db->query($sql, $params)->getResult();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function approve()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "comment" => "permit_empty",
        ]);
        $this->access_only_tender("procurement_manager_inbox", "update");

        $tender_id = (int)$this->request->getPost("tender_id");
        $tender = $this->_get_tender_for_manager($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        if ((string)($tender->procurement_manager_status ?? "") !== "pending") {
            return $this->response->setJSON(["success" => false, "message" => "Only pending tenders can be approved."]);
        }

        $action = (string) ($tender->procurement_manager_action ?? "initial");
        if ($action === "") {
            $action = "initial";
        }

        $now = date("Y-m-d H:i:s");
        $approval_payload = [
            "procurement_manager_status" => "approved",
            "procurement_manager_reviewed_by" => (int)$this->login_user->id,
            "procurement_manager_reviewed_at" => $now,
            "procurement_manager_comment" => trim((string)$this->request->getPost("comment")) ?: null,
            "updated_at" => $now,
        ];

        switch ($action) {
            case "cancel":
                $approval_payload["status"] = "cancelled";
                $this->Tenders_model->ci_save($approval_payload, $tender_id);

                return $this->response->setJSON([
                    "success" => true,
                    "message" => "Tender cancellation approved.",
                ]);

            case "update":
                $this->_apply_approved_manager_update($tender, $this->_decode_manager_payload($tender));
                $this->Tenders_model->ci_save($approval_payload, $tender_id);

                return $this->response->setJSON([
                    "success" => true,
                    "message" => "Tender update approved and applied.",
                ]);

            case "stage_override":
                $this->_apply_approved_stage_override($tender, $this->_decode_manager_payload($tender));
                $this->Tenders_model->ci_save($approval_payload, $tender_id);

                return $this->response->setJSON([
                    "success" => true,
                    "message" => "Workflow stage change approved and applied.",
                ]);

            default:
                $this->Tenders_model->ci_save($approval_payload, $tender_id);
                break;
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Tender approved. Procurement can publish it now.",
        ]);
    }

    private function _decode_manager_payload($tender): array
    {
        $payload = json_decode((string) ($tender->procurement_manager_payload ?? ""), true);
        return is_array($payload) ? $payload : [];
    }

    private function _apply_approved_manager_update($tender, array $payload): void
    {
        $tender_id = (int) ($tender->id ?? 0);
        if (!$tender_id || !$payload) {
            return;
        }

        $fields = $payload["tender_fields"] ?? [];
        if (is_array($fields) && $fields) {
            if (empty($fields["tender_request_id"]) && !empty($tender->tender_request_id)) {
                $fields["tender_request_id"] = (int) $tender->tender_request_id;
            }

            $fields["updated_at"] = date("Y-m-d H:i:s");
            $this->Tenders_model->ci_save(clean_data($fields), $tender_id);
            $this->_record_manager_update_history($tender, $fields, $payload, $fields["updated_at"]);
        }

        $target = $payload["target"] ?? [];
        if (is_array($target)) {
            $this->_sync_target_rule_from_payload($tender_id, $target);
        }

        $required_sections = $payload["required_sections"] ?? null;
        if (is_array($required_sections)) {
            $this->Tender_bid_requirements_model->sync_requirements($tender_id, $required_sections);
        }

        $rfq = $payload["rfq"] ?? [];
        if (is_array($rfq)) {
            $detail = $rfq["detail"] ?? [];
            $items = $rfq["items"] ?? [];

            if (is_array($detail)) {
                $this->Tender_rfq_details_model->sync_detail($tender_id, $detail);
            }

            if (is_array($items)) {
                $this->Tender_rfq_items_model->sync_items($tender_id, $items);
            }
        }

        $team_ids = $payload["team_ids"] ?? [];
        if (is_array($team_ids) && $this->_has_any_team_selection($team_ids)) {
            $this->_sync_tender_teams_from_payload($tender_id, $team_ids);
        }

        $fresh = $this->_get_tender_for_manager($tender_id);
        if ($fresh) {
            $this->_sync_invites_from_payload($fresh, $target);
        }

        if (!empty($payload["testing_workflow_stage"])) {
            $this->_apply_pending_testing_stage_override($fresh ?: $tender, (string) $payload["testing_workflow_stage"]);
        }
    }

    private function _apply_approved_stage_override($tender, array $payload): void
    {
        $tender_id = (int) ($tender->id ?? 0);
        $fields = $payload["tender_fields"] ?? [];
        if (!$tender_id || !is_array($fields) || !$fields) {
            return;
        }

        $target_stage = (string) ($payload["workflow_stage"] ?? ($fields["workflow_stage"] ?? ""));
        $now = date("Y-m-d H:i:s");

        switch ($target_stage) {
            case "bidding":
            case "technical_3key":
                $this->_expire_opening_sessions($tender_id, "technical", $now);
                $this->_expire_opening_sessions($tender_id, "commercial", $now);
                break;

            case "technical":
                $this->_ensure_override_opening($tender_id, "technical", $now);
                $this->_expire_opening_sessions($tender_id, "commercial", $now);
                break;

            case "commercial":
                $this->_ensure_override_opening($tender_id, "technical", $now);
                break;
        }

        $this->Tenders_model->ci_save(clean_data($fields), $tender_id);
        $this->_record_manager_stage_override($tender, $payload, $now);
    }

    private function _apply_pending_testing_stage_override($tender, string $testing_stage): void
    {
        $tender_id = (int) ($tender->id ?? 0);
        if (!$tender_id || in_array((string) ($tender->status ?? ""), ["awarded", "cancelled"], true)) {
            return;
        }

        $now = date("Y-m-d H:i:s");
        $payload = $this->Tender_testing_stage->build_payload($testing_stage, $tender, $now);
        if (!$payload) {
            return;
        }

        $actions = $this->Tender_testing_stage->opening_actions($testing_stage);
        foreach (($actions["expire"] ?? []) as $stage) {
            $this->_expire_opening_sessions($tender_id, (string) $stage, $now);
        }

        foreach (($actions["unlock"] ?? []) as $stage) {
            $this->_ensure_override_opening($tender_id, (string) $stage, $now, "TEST");
        }

        $this->Tenders_model->ci_save($payload, $tender_id);
        $this->_record_manager_testing_stage_override($tender, $testing_stage, $payload, $now);
    }

    private function _expire_opening_sessions(int $tender_id, string $stage, string $now): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $this->db->query(
            "UPDATE $tbo
             SET status='expired',
                 updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status IN ('codes_generated', 'unlocked')",
            [$now, $tender_id, $stage]
        );
    }

    private function _ensure_override_opening(int $tender_id, string $stage, string $now, string $code = "OVERRIDE"): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");

        $existing = $this->db->query(
            "SELECT id
             FROM $tbo
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status='unlocked'
             ORDER BY id DESC
             LIMIT 1",
            [$tender_id, $stage]
        )->getRow();

        if ($existing) {
            return;
        }

        $this->_expire_opening_sessions($tender_id, $stage, $now);
        $this->db->query(
            "INSERT INTO $tbo
                (tender_id, stage, status, chairman_code, secretary_code, member_code, generated_by, generated_at, expires_at, unlocked_at, created_at, updated_at, deleted)
             VALUES
                (?, ?, 'unlocked', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $tender_id,
                $stage,
                $code,
                $code,
                $code,
                $this->login_user->id,
                $now,
                $now,
                $now,
                $now,
                $now,
            ]
        );
    }

    private function _workflow_stage_options(): array
    {
        return [
            "bidding" => "Bid Submission / Bidding",
            "technical_3key" => "Bid Opening",
            "technical" => "Technical Evaluation",
            "commercial" => "Commercial Evaluation",
            "award_decision" => "Award Decision",
        ];
    }

    private function _workflow_stage_label(string $stage): string
    {
        $options = $this->_workflow_stage_options();
        return $options[$stage] ?? ucwords(str_replace("_", " ", $stage));
    }

    private function _stage_date_label(string $field): string
    {
        $labels = [
            "closing_at" => "Submission Deadline",
            "bid_opening_at" => "Bid Opening Deadline",
            "technical_start_at" => "Technical Evaluation Start",
            "technical_end_at" => "Technical Evaluation End",
            "technical_eval_deadline" => "Technical Evaluation Deadline",
            "technical_locked_at" => "Technical Lock",
            "commercial_unlocked_at" => "Commercial Unlock",
            "commercial_start_at" => "Commercial Evaluation Start",
            "commercial_end_at" => "Commercial Evaluation End",
            "commercial_eval_deadline" => "Commercial Evaluation Deadline",
            "award_ready_at" => "Award Decision Ready",
        ];

        return $labels[$field] ?? ucwords(str_replace("_", " ", $field));
    }

    private function _manager_update_field_labels(): array
    {
        return [
            "reference" => "Reference",
            "title" => "Title",
            "tender_type" => "Tender Type",
            "tender_fee" => "Tender Fees",
            "release_at" => "Tender Release",
            "document_purchase_deadline" => "Document Purchase Deadline",
            "site_visit_at" => "Site Visit",
            "site_visit_location" => "Site Visit Location",
            "clarification_deadline" => "Clarification Deadline",
            "closing_at" => "Submission Deadline",
            "bid_opening_at" => "Bid Opening",
            "technical_eval_deadline" => "Technical Evaluation Deadline",
            "commercial_eval_deadline" => "Commercial Evaluation Deadline",
            "brief_description" => "Brief Description",
        ];
    }

    private function _record_manager_update_history($tender, array $fields, array $payload, string $now): void
    {
        $changes = [];
        foreach ($this->_manager_update_field_labels() as $field => $label) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            $old = trim((string) ($tender->{$field} ?? ""));
            $new = trim((string) ($fields[$field] ?? ""));
            if ($old !== $new) {
                $changes[] = $label . ": " . ($old !== "" ? $old : "-") . " to " . ($new !== "" ? $new : "-");
            }
        }

        if (!empty($payload["testing_workflow_stage"])) {
            $changes[] = "Temporary Testing Stage: " . $this->Tender_testing_stage->label((string) $payload["testing_workflow_stage"]);
        }

        if (!$changes) {
            return;
        }

        $table = $this->db->prefixTable("tender_workflow_history");
        $this->db->table($table)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => "manager_update_approved",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($fields["status"] ?? ($tender->status ?? "")),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => (string) ($fields["workflow_stage"] ?? ($tender->workflow_stage ?? "")),
            "open_until" => $fields["commercial_eval_deadline"] ?? $fields["technical_eval_deadline"] ?? $fields["closing_at"] ?? null,
            "reason" => "Tender update approved by procurement manager",
            "details" => implode("\n", $changes),
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _workflow_history_details($tender, array $payload, array $adjustments): string
    {
        $fields = [
            "closing_at",
            "bid_opening_at",
            "technical_start_at",
            "technical_end_at",
            "technical_eval_deadline",
            "technical_locked_at",
            "commercial_unlocked_at",
            "commercial_start_at",
            "commercial_end_at",
            "commercial_eval_deadline",
            "award_ready_at",
        ];

        $lines = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $old = !empty($tender->{$field}) ? date("Y-m-d H:i", strtotime((string) $tender->{$field})) : "-";
            $new = !empty($payload[$field]) ? date("Y-m-d H:i", strtotime((string) $payload[$field])) : "-";
            if ($old !== $new) {
                $lines[] = $this->_stage_date_label($field) . ": " . $old . " to " . $new;
            }
        }

        if ($adjustments) {
            $lines[] = "Automatic downstream alignment:";
            foreach ($adjustments as $adjustment) {
                $lines[] = "- " . $adjustment;
            }
        }

        return implode("\n", $lines);
    }

    private function _record_manager_stage_override($tender, array $manager_payload, string $now): void
    {
        $fields = $manager_payload["tender_fields"] ?? [];
        if (!is_array($fields)) {
            $fields = [];
        }

        $target_stage = (string) ($manager_payload["workflow_stage"] ?? ($fields["workflow_stage"] ?? ""));
        $open_until = (string) ($manager_payload["open_until"] ?? "");
        $reason = trim((string) ($manager_payload["reason"] ?? ""));
        $adjustments = is_array($manager_payload["adjustments"] ?? null) ? $manager_payload["adjustments"] : [];
        $table = $this->db->prefixTable("tender_workflow_history");

        $this->db->table($table)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => "stage_override",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($fields["status"] ?? ($tender->status ?? "")),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => $target_stage,
            "open_until" => $open_until ?: null,
            "reason" => $reason ?: null,
            "details" => $this->_workflow_history_details($tender, $fields, $adjustments) ?: null,
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));

        $message = "Workflow stage approved by procurement manager.\n";
        $message .= "From: " . $this->_workflow_stage_label((string) ($tender->workflow_stage ?? "")) . "\n";
        $message .= "To: " . $this->_workflow_stage_label($target_stage) . "\n";
        if ($open_until !== "") {
            $message .= "Open until: " . date("Y-m-d H:i", strtotime($open_until)) . "\n";
        }
        if ($reason !== "") {
            $message .= "Reason: " . $reason . "\n";
        }
        if ($adjustments) {
            $message .= "Downstream schedule alignment:\n" . implode("\n", $adjustments);
        }

        $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "vendor_id" => null,
            "parent_id" => null,
            "type" => "circular",
            "subject" => "Internal Workflow Stage Approval - " . ($tender->reference ?? "Tender"),
            "message" => $message,
            "sent_to_all" => 0,
            "is_vendor_visible" => 0,
            "published_at" => $now,
            "created_by" => $this->login_user->id,
            "status" => "internal",
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _record_manager_testing_stage_override($tender, string $testing_stage, array $payload, string $now): void
    {
        $history = $this->db->prefixTable("tender_workflow_history");
        $label = $this->Tender_testing_stage->label($testing_stage);

        $this->db->table($history)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => "testing_stage_override",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($payload["status"] ?? ($tender->status ?? "")),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => (string) ($payload["workflow_stage"] ?? ($tender->workflow_stage ?? "")),
            "open_until" => $payload["commercial_end_at"] ?? $payload["technical_end_at"] ?? $payload["closing_at"] ?? null,
            "reason" => "Temporary procurement testing stage selector approved by procurement manager",
            "details" => "Temporary testing stage: " . $label,
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _sync_target_rule_from_payload(int $tender_id, array $target): void
    {
        $target_table = $this->db->prefixTable("tender_target_specialties");
        $target_vendors_table = $this->db->prefixTable("tender_target_vendors");
        $now = date("Y-m-d H:i:s");
        $mode = (string) ($target["target_mode"] ?? "specialty");
        $vendor_category_id = (int) ($target["vendor_category_id"] ?? 0);
        $vendor_sub_category_id = (int) ($target["vendor_sub_category_id"] ?? 0);
        $vendor_group_id = (int) ($target["vendor_group_id"] ?? 0);
        $vendor_grade_id = (int) ($target["vendor_grade_id"] ?? 0);
        $specific_vendor_ids = $this->_clean_vendor_ids((array) ($target["specific_vendor_ids"] ?? []));

        $this->db->query("UPDATE $target_table SET deleted=1 WHERE tender_id=?", [$tender_id]);
        $this->db->query("UPDATE $target_vendors_table SET deleted=1 WHERE tender_id=?", [$tender_id]);

        if ($mode === "specific_vendors") {
            foreach ($specific_vendor_ids as $vendor_id) {
                $this->db->query(
                    "INSERT INTO $target_vendors_table (tender_id, vendor_id, created_by, created_at, deleted)
                     VALUES (?, ?, ?, ?, 0)",
                    [$tender_id, $vendor_id, (int) $this->login_user->id, $now]
                );
            }

            return;
        }

        if ($mode === "group" && $vendor_group_id > 0) {
            $this->db->query(
                "INSERT INTO $target_table (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, vendor_grade_id, created_by, created_at, deleted)
                 VALUES (?, 0, NULL, ?, NULL, ?, ?, 0)",
                [$tender_id, $vendor_group_id, (int) $this->login_user->id, $now]
            );
        } elseif ($mode === "grade" && $vendor_grade_id > 0) {
            $this->db->query(
                "INSERT INTO $target_table (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, vendor_grade_id, created_by, created_at, deleted)
                 VALUES (?, 0, NULL, NULL, ?, ?, ?, 0)",
                [$tender_id, $vendor_grade_id, (int) $this->login_user->id, $now]
            );
        } elseif ($vendor_category_id > 0) {
            $this->db->query(
                "INSERT INTO $target_table (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, vendor_grade_id, created_by, created_at, deleted)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, 0)",
                [$tender_id, $vendor_category_id, $vendor_sub_category_id ?: null, (int) $this->login_user->id, $now]
            );
        }
    }

    private function _clean_vendor_ids(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    private function _has_any_team_selection(array $team_ids): bool
    {
        return !empty($team_ids["technical"])
            || !empty($team_ids["commercial"])
            || (int) ($team_ids["chairman"] ?? 0) > 0
            || (int) ($team_ids["secretary"] ?? 0) > 0
            || !empty($team_ids["itc_member"]);
    }

    private function _sync_tender_teams_from_payload(int $tender_id, array $team_ids): void
    {
        $team = $this->db->prefixTable("tender_team_members");
        $now = date("Y-m-d H:i:s");
        $this->db->query("UPDATE $team SET deleted=1, is_active=0, updated_at=? WHERE tender_id=?", [$now, $tender_id]);

        $insert = function (int $user_id, string $role) use ($team, $tender_id, $now): void {
            if (!$user_id) {
                return;
            }

            $this->db->query(
                "INSERT INTO $team (tender_id, user_id, team_role, is_active, created_at, updated_at, deleted)
                 VALUES (?, ?, ?, 1, ?, ?, 0)",
                [$tender_id, $user_id, $role, $now, $now]
            );
        };

        foreach ((array) ($team_ids["technical"] ?? []) as $user_id) {
            $insert((int) $user_id, "technical_evaluator");
        }

        foreach ((array) ($team_ids["commercial"] ?? []) as $user_id) {
            $insert((int) $user_id, "commercial_evaluator");
        }

        $insert((int) ($team_ids["chairman"] ?? 0), "chairman");
        $insert((int) ($team_ids["secretary"] ?? 0), "secretary");

        foreach ((array) ($team_ids["itc_member"] ?? []) as $user_id) {
            $insert((int) $user_id, "itc_member");
        }
    }

    private function _sync_invites_from_payload($tender, array $target): void
    {
        $tender_id = (int) ($tender->id ?? 0);
        $tender_type = (string) ($tender->tender_type ?? "open");
        $tender_request_id = (int) ($tender->tender_request_id ?? 0);
        $mode = (string) ($target["target_mode"] ?? "specialty");
        $vendor_category_id = (int) ($target["vendor_category_id"] ?? 0);
        $vendor_sub_category_id = (int) ($target["vendor_sub_category_id"] ?? 0);
        $vendor_group_id = (int) ($target["vendor_group_id"] ?? 0);
        $vendor_grade_id = (int) ($target["vendor_grade_id"] ?? 0);
        $specific_vendor_ids = $this->_clean_vendor_ids((array) ($target["specific_vendor_ids"] ?? []));
        $has_explicit_target = ($mode === "group" && $vendor_group_id > 0)
            || ($mode === "specific_vendors" && $specific_vendor_ids)
            || ($mode === "grade" && $vendor_grade_id > 0)
            || ($mode === "specialty" && $vendor_category_id > 0);

        if (!$has_explicit_target && $tender_type === "close" && $tender_request_id && $this->_count_request_selected_vendors($tender_request_id) > 0) {
            $this->_sync_invites_from_request($tender_id, $tender_request_id);
        } elseif ($mode === "group" && $vendor_group_id > 0) {
            $this->_sync_invites_by_vendor_group($tender_id, $vendor_group_id);
        } elseif ($mode === "specific_vendors" && $specific_vendor_ids) {
            $this->_sync_invites_from_specific_vendors($tender_id, $specific_vendor_ids);
        } elseif ($mode === "grade" && $vendor_grade_id > 0) {
            $this->_sync_invites_by_vendor_grade($tender_id, $vendor_grade_id);
        } elseif ($vendor_category_id > 0) {
            $this->_sync_invites_by_specialty($tender_id, $vendor_category_id, $vendor_sub_category_id);
        }
    }

    private function _clear_invites(int $tender_id): void
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=?", [$tender_id]);
    }

    private function _count_request_selected_vendors(int $tender_request_id): int
    {
        $trv = $this->db->prefixTable("tender_request_vendors");
        $row = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM $trv
             WHERE deleted=0
               AND tender_request_id=?",
            [$tender_request_id]
        )->getRow();

        return (int) ($row->total ?? 0);
    }

    private function _sync_invites_from_request(int $tender_id, int $tender_request_id): void
    {
        $trv = $this->db->prefixTable("tender_request_vendors");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $now = date("Y-m-d H:i:s");

        $this->_clear_invites($tender_id);

        $rows = $this->db->query(
            "SELECT DISTINCT $trv.vendor_id
             FROM $trv
             JOIN $vendors ON $vendors.id=$trv.vendor_id
             WHERE $trv.deleted=0
               AND $vendors.deleted=0
               AND $vendors.status='approved'
               AND $trv.tender_request_id=?",
            [$tender_request_id]
        )->getResult();

        foreach ($rows as $row) {
            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, (int) $row->vendor_id, (int) $this->login_user->id, $now]
            );
        }
    }

    private function _sync_invites_by_specialty(int $tender_id, int $vendor_category_id, int $vendor_sub_category_id): void
    {
        $this->_clear_invites($tender_id);
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $spec = $this->db->prefixTable("vendor_specialties");
        $now = date("Y-m-d H:i:s");

        $sql = "SELECT DISTINCT $vendors.id AS vendor_id
                FROM $spec
                JOIN $vendors ON $vendors.id=$spec.vendor_id
                WHERE $spec.deleted=0
                  AND $spec.status='approved'
                  AND $vendors.deleted=0
                  AND $vendors.status='approved'
                  AND $spec.vendor_category_id=?";
        $params = [$vendor_category_id];

        if ($vendor_sub_category_id > 0) {
            $sql .= " AND $spec.vendor_sub_category_id=?";
            $params[] = $vendor_sub_category_id;
        }

        foreach ($this->db->query($sql, $params)->getResult() as $row) {
            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, (int) $row->vendor_id, (int) $this->login_user->id, $now]
            );
        }
    }

    private function _sync_invites_by_vendor_group(int $tender_id, int $vendor_group_id): void
    {
        $this->_clear_invites($tender_id);
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $now = date("Y-m-d H:i:s");

        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND vendor_group_id=?",
            [$vendor_group_id]
        )->getResult();

        foreach ($rows as $row) {
            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, (int) $row->vendor_id, (int) $this->login_user->id, $now]
            );
        }
    }

    private function _sync_invites_from_specific_vendors(int $tender_id, array $vendor_ids): void
    {
        $this->_clear_invites($tender_id);
        $vendor_ids = $this->_clean_vendor_ids($vendor_ids);
        if (!$vendor_ids) {
            return;
        }

        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $now = date("Y-m-d H:i:s");
        $placeholders = implode(",", array_fill(0, count($vendor_ids), "?"));

        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND id IN ($placeholders)",
            $vendor_ids
        )->getResult();

        foreach ($rows as $row) {
            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, (int) $row->vendor_id, (int) $this->login_user->id, $now]
            );
        }
    }

    private function _sync_invites_by_vendor_grade(int $tender_id, int $vendor_grade_id): void
    {
        $this->_clear_invites($tender_id);
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $now = date("Y-m-d H:i:s");

        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND vendor_grade_id=?",
            [$vendor_grade_id]
        )->getResult();

        foreach ($rows as $row) {
            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, (int) $row->vendor_id, (int) $this->login_user->id, $now]
            );
        }
    }

    private function _get_tender_for_manager(int $tender_id)
    {
        if (!$tender_id) {
            return null;
        }

        $t = $this->db->prefixTable("tenders");
        $row = $this->db->query(
            "SELECT * FROM $t WHERE deleted=0 AND id=? LIMIT 1",
            [$tender_id]
        )->getRow();

        if (!$row || $this->login_user->is_admin) {
            return $row;
        }

        return $this->Tender_procurement_manager_users_model->is_manager_for_company((int)$this->login_user->id, (int)($row->company_id ?? 0))
            ? $row
            : null;
    }

    private function _status_badge(string $status): string
    {
        $class = match ($status) {
            "pending" => "bg-warning",
            "approved" => "bg-success",
            "rejected" => "bg-danger",
            "revision_requested" => "bg-info",
            default => "bg-secondary",
        };

        return "<span class='badge $class'>" . esc(ucwords(str_replace("_", " ", $status ?: "draft"))) . "</span>";
    }

    private function _pending_action_summary($row): string
    {
        $action = (string)($row->procurement_manager_action ?? "initial");
        if ($action === "") {
            $action = "initial";
        }

        if ($action === "initial") {
            return "<span class='text-off'>Initial tender approval</span>";
        }

        $payload = $this->_decode_manager_payload($row);
        if ($action === "cancel") {
            $from_status = $this->_summary_value($payload["from_status"] ?? ($row->status ?? ""));
            $from_stage = $this->_summary_value($payload["from_stage"] ?? ($row->workflow_stage ?? ""));
            $requested_at = $this->_summary_value($payload["requested_at"] ?? ($row->procurement_manager_submitted_at ?? ""));

            return "<div class='small'><strong>Cancel tender</strong><br>Status: $from_status<br>Stage: $from_stage<br>Requested: $requested_at</div>";
        }

        if ($action === "stage_override") {
            $from_stage = $this->_summary_value($payload["from_stage"] ?? ($row->workflow_stage ?? ""));
            $to_stage = $this->_summary_value($this->_workflow_stage_label((string) ($payload["workflow_stage"] ?? "")));
            $open_until = $this->_summary_value($payload["open_until"] ?? "");
            $reason = $this->_summary_value($payload["reason"] ?? "");
            $adjustments = is_array($payload["adjustments"] ?? null) ? count($payload["adjustments"]) : 0;

            return "<div class='small'><strong>Workflow stage change</strong><br>From: $from_stage<br>To: $to_stage<br>Open until: $open_until<br>Reason: $reason"
                . ($adjustments ? "<br>Automatic adjustments: " . (int) $adjustments : "")
                . "</div>";
        }

        if ($action !== "update") {
            return "<span class='text-off'>" . esc(ucwords(str_replace("_", " ", $action))) . "</span>";
        }

        $fields = $payload["tender_fields"] ?? [];
        $changes = [];
        $labels = [
            "reference" => "Reference",
            "title" => "Title",
            "tender_type" => "Tender Type",
            "tender_fee" => "Tender Fees",
            "release_at" => "Release",
            "document_purchase_deadline" => "Document Purchase Deadline",
            "site_visit_at" => "Site Visit",
            "site_visit_location" => "Site Visit Location",
            "clarification_deadline" => "Clarification Deadline",
            "closing_at" => "Closing",
            "bid_opening_at" => "Bid Opening",
            "technical_eval_deadline" => "Technical Evaluation",
            "commercial_eval_deadline" => "Commercial Evaluation",
        ];

        foreach ($labels as $key => $label) {
            if (!is_array($fields) || !array_key_exists($key, $fields)) {
                continue;
            }

            $old = $this->_summary_raw_value($row->{$key} ?? "");
            $new = $this->_summary_raw_value($fields[$key] ?? "");
            if ($old !== $new) {
                $changes[] = "<strong>" . esc($label) . ":</strong> " . $this->_summary_value($old) . " &rarr; " . $this->_summary_value($new);
            }
        }

        if (is_array($fields) && array_key_exists("brief_description", $fields) && $this->_summary_raw_value($row->brief_description ?? "") !== $this->_summary_raw_value($fields["brief_description"] ?? "")) {
            $changes[] = "<strong>Brief Description:</strong> updated";
        }

        $target = $payload["target"] ?? [];
        if (is_array($target) && $target) {
            $mode = (string)($target["target_mode"] ?? "specialty");
            $specific_count = count((array)($target["specific_vendor_ids"] ?? []));
            $changes[] = "<strong>Vendor Target:</strong> " . esc(ucwords(str_replace("_", " ", $mode)))
                . " category " . (int)($target["vendor_category_id"] ?? 0)
                . ", sub-category " . (int)($target["vendor_sub_category_id"] ?? 0)
                . ", group " . (int)($target["vendor_group_id"] ?? 0)
                . ", grade " . (int)($target["vendor_grade_id"] ?? 0)
                . ($specific_count ? ", selected vendors " . $specific_count : "");
        }

        $required_sections = $payload["required_sections"] ?? null;
        if (is_array($required_sections)) {
            $changes[] = "<strong>Bid Requirements:</strong> " . count($required_sections) . " selected";
        }

        $rfq_items = $payload["rfq"]["items"] ?? null;
        if (is_array($rfq_items)) {
            $changes[] = "<strong>RFQ Items:</strong> " . count($rfq_items) . " line item(s)";
        }

        if ($this->_has_any_team_selection((array)($payload["team_ids"] ?? []))) {
            $changes[] = "<strong>Tender Team:</strong> updated";
        }

        if (!empty($payload["testing_workflow_stage"])) {
            $changes[] = "<strong>Testing Stage:</strong> " . esc((string)$payload["testing_workflow_stage"]);
        }

        if (!$changes) {
            return "<span class='text-off'>Update request pending</span>";
        }

        $total = count($changes);
        $changes = array_slice($changes, 0, 5);
        if ($total > count($changes)) {
            $changes[] = "<span class='text-off'>+" . ($total - count($changes)) . " more change(s)</span>";
        }

        return "<div class='small'>" . implode("<br>", $changes) . "</div>";
    }

    private function _summary_raw_value($value): string
    {
        return trim((string)($value ?? ""));
    }

    private function _summary_value($value): string
    {
        $value = $this->_summary_raw_value($value);
        return esc($value !== "" ? $value : "-");
    }

    private function _make_row($row): array
    {
        $status = (string)($row->procurement_manager_status ?? "draft");
        $action = (string)($row->procurement_manager_action ?? "initial");
        if ($action === "") {
            $action = "initial";
        }
        $action_label = "<span class='badge bg-light text-dark'>" . esc(ucwords(str_replace("_", " ", $action))) . "</span>";
        $submitted_by = trim((string)($row->submitted_by_name ?? ""));
        $reviewed_by = trim((string)($row->reviewed_by_name ?? ""));

        $details = anchor(
            get_uri("tender_reports/details/" . (int)$row->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => "View tender"]
        );

        $approve = "";
        if ($status === "pending" && $this->can_tender("procurement_manager_inbox", "update")) {
            $approve = js_anchor(
                "<i data-feather='check-circle' class='icon-16'></i> Approve",
                [
                    "class" => "btn btn-success btn-sm approve-tender",
                    "title" => "Approve tender",
                    "data-tender-id" => (int)$row->id,
                    "data-action-url" => get_uri("tender_procurement_manager_inbox/approve"),
                ]
            );
        }

        return [
            esc($row->reference ?? "-"),
            esc($row->title ?? "-"),
            esc($row->company_name ?? "-"),
            esc($row->department_name ?? "-"),
            $this->_status_badge($status),
            $action_label,
            $this->_pending_action_summary($row),
            $row->procurement_manager_submitted_at ? format_to_datetime($row->procurement_manager_submitted_at) : "-",
            esc($submitted_by ?: "-"),
            $row->procurement_manager_reviewed_at ? format_to_datetime($row->procurement_manager_reviewed_at) : "-",
            esc($reviewed_by ?: "-"),
            trim($details . " " . $approve),
        ];
    }
}
