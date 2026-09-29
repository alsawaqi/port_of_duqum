<?php

namespace App\Models;

class Verification_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "verification";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $builder = $this->db->table($this->db->prefixTable("verification"));
        $builder->where("deleted", 0);
        $code = $this->_get_clean_value($options, "code");
        if ($code) {
            $builder->where("code", $code);
        }

        $type = $this->_get_clean_value($options, "type");
        if ($type) {
            $builder->where("type", $type);
        }

        return $builder->get();
    }

    /**
     * Issue a selector/validator reset credential. Only the validator hash is
     * retained, and the record is bound to the immutable users.id value.
     */
    public function issue_password_reset_token(
        int $userId,
        string $requestIpHash,
        int $lifetimeSeconds
    ): ?string {
        if ($userId < 1 || $lifetimeSeconds < 1) {
            return null;
        }

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $now = gmdate("Y-m-d H:i:s");
        $table = $this->db->table($this->db->prefixTable("auth_password_reset_tokens"));

        $this->db->transBegin();
        try {
            // A new request supersedes every older unused link for this user.
            $table->where("user_id", $userId)
                ->where("used_at", null)
                ->update(["used_at" => $now]);

            $created = $table->insert([
                "user_id" => $userId,
                "selector" => $selector,
                "validator_hash" => hash("sha256", $validator),
                "expires_at" => gmdate("Y-m-d H:i:s", time() + $lifetimeSeconds),
                "used_at" => null,
                "request_ip_hash" => $requestIpHash,
                "created_at" => $now,
            ]);

            if (!$created || $this->db->transStatus() === false) {
                $this->db->transRollback();
                return null;
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            log_message("error", "Unable to issue password reset token: {message}", [
                "message" => $exception->getMessage(),
            ]);
            return null;
        }

        return $selector . "." . $validator;
    }

    /**
     * Return only non-secret reset metadata after constant-time validation.
     */
    public function get_valid_password_reset_token(string $token): ?object
    {
        $parts = $this->parse_reset_token($token);
        if (!$parts) {
            return null;
        }

        try {
            $tokens = $this->db->prefixTable("auth_password_reset_tokens");
            $users = $this->db->prefixTable("users");
            $row = $this->db->query(
                "SELECT tokens.id, tokens.user_id, tokens.validator_hash, tokens.expires_at
                 FROM {$tokens} tokens
                 INNER JOIN {$users} users ON users.id = tokens.user_id
                 WHERE tokens.selector = ?
                   AND tokens.used_at IS NULL
                   AND users.deleted = 0
                   AND users.status = 'active'
                   AND users.disable_login = 0
                 LIMIT 1",
                [$parts["selector"]]
            )->getRow();
        } catch (\Throwable $exception) {
            log_message("error", "Unable to validate password reset token: {message}", [
                "message" => $exception->getMessage(),
            ]);
            return null;
        }

        if (!$row
            || $this->utc_timestamp($row->expires_at ?? null) <= time()
            || !hash_equals(
                (string) $row->validator_hash,
                hash("sha256", $parts["validator"])
            )) {
            return null;
        }

        unset($row->validator_hash);
        return $row;
    }

    /**
     * Consume the token and change the exact user's password atomically.
     * The session version increment revokes all of that user's older sessions.
     */
    public function consume_password_reset_token(string $token, string $passwordHash): int
    {
        $parts = $this->parse_reset_token($token);
        if (!$parts || $passwordHash === "") {
            return 0;
        }

        $tokens = $this->db->prefixTable("auth_password_reset_tokens");
        $users = $this->db->prefixTable("users");
        $this->db->transBegin();

        try {
            $row = $this->db->query(
                "SELECT id, user_id, validator_hash, expires_at, used_at
                 FROM {$tokens}
                 WHERE selector = ?
                 LIMIT 1
                 FOR UPDATE",
                [$parts["selector"]]
            )->getRow();

            $isValid = $row
                && empty($row->used_at)
                && $this->utc_timestamp($row->expires_at ?? null) > time()
                && hash_equals(
                    (string) $row->validator_hash,
                    hash("sha256", $parts["validator"])
                );
            if (!$isValid) {
                $this->db->transRollback();
                return 0;
            }

            $now = gmdate("Y-m-d H:i:s");
            $consumed = $this->db->query(
                "UPDATE {$tokens}
                 SET used_at = ?
                 WHERE id = ? AND used_at IS NULL",
                [$now, (int) $row->id]
            );
            $passwordChanged = $this->db->query(
                "UPDATE {$users}
                 SET password = ?, auth_session_version = auth_session_version + 1
                 WHERE id = ? AND deleted = 0 AND status = 'active' AND disable_login = 0",
                [$passwordHash, (int) $row->user_id]
            );
            $changedRows = $this->db->affectedRows();

            if (!$consumed
                || !$passwordChanged
                || $changedRows !== 1
                || $this->db->transStatus() === false) {
                $this->db->transRollback();
                return 0;
            }

            $this->db->transCommit();
            return (int) $row->user_id;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            log_message("error", "Unable to consume password reset token: {message}", [
                "message" => $exception->getMessage(),
            ]);
            return 0;
        }
    }

    public function revoke_password_reset_token(string $token): void
    {
        $parts = $this->parse_reset_token($token);
        if (!$parts) {
            return;
        }

        try {
            $this->db->table($this->db->prefixTable("auth_password_reset_tokens"))
                ->where("selector", $parts["selector"])
                ->where("used_at", null)
                ->update(["used_at" => gmdate("Y-m-d H:i:s")]);
        } catch (\Throwable $exception) {
            log_message("error", "Unable to revoke password reset token: {message}", [
                "message" => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Store a keyed OTP digest and return the code only to the caller that will
     * immediately hand it to the configured delivery provider.
     *
     * @return array{challenge_id: string, code: string}|null
     */
    public function issue_mfa_challenge(
        int $userId,
        string $provider,
        string $destinationHint,
        string $ipHash,
        string $userAgentHash,
        string $hmacKey,
        int $codeLength,
        int $lifetimeSeconds,
        int $maxAttempts
    ): ?array {
        if ($userId < 1
            || strlen($hmacKey) < 32
            || $codeLength < 6
            || $codeLength > 8
            || $lifetimeSeconds < 1
            || $maxAttempts < 1) {
            return null;
        }

        $challengeId = bin2hex(random_bytes(16));
        $upperBound = (10 ** $codeLength) - 1;
        $code = str_pad((string) random_int(0, $upperBound), $codeLength, "0", STR_PAD_LEFT);
        $codeHash = hash_hmac("sha256", $challengeId . "|" . $code, $hmacKey);
        $now = gmdate("Y-m-d H:i:s");
        $table = $this->db->table($this->db->prefixTable("auth_mfa_challenges"));

        $this->db->transBegin();
        try {
            $table->where("user_id", $userId)
                ->where("purpose", "signin")
                ->where("consumed_at", null)
                ->update(["consumed_at" => $now]);

            $created = $table->insert([
                "challenge_id" => $challengeId,
                "user_id" => $userId,
                "purpose" => "signin",
                "provider" => substr($provider, 0, 32),
                "destination_hint" => substr($destinationHint, 0, 190),
                "code_hash" => $codeHash,
                "attempts" => 0,
                "max_attempts" => $maxAttempts,
                "expires_at" => gmdate("Y-m-d H:i:s", time() + $lifetimeSeconds),
                "consumed_at" => null,
                "request_ip_hash" => $ipHash,
                "user_agent_hash" => $userAgentHash,
                "created_at" => $now,
            ]);

            if (!$created || $this->db->transStatus() === false) {
                $this->db->transRollback();
                return null;
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            log_message("error", "Unable to issue MFA challenge: {message}", [
                "message" => $exception->getMessage(),
            ]);
            return null;
        }

        return ["challenge_id" => $challengeId, "code" => $code];
    }

    public function get_active_mfa_challenge(string $challengeId, int $userId): ?object
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId) || $userId < 1) {
            return null;
        }

        try {
            $row = $this->db->table($this->db->prefixTable("auth_mfa_challenges"))
                ->select("challenge_id, user_id, provider, destination_hint, expires_at, attempts, max_attempts")
                ->getWhere([
                    "challenge_id" => $challengeId,
                    "user_id" => $userId,
                    "purpose" => "signin",
                    "consumed_at" => null,
                ], 1)
                ->getRow();
        } catch (\Throwable $exception) {
            return null;
        }

        return $row
            && $this->utc_timestamp($row->expires_at ?? null) > time()
            && (int) $row->attempts < (int) $row->max_attempts
            ? $row
            : null;
    }

    public function revoke_mfa_challenge(string $challengeId, int $userId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId) || $userId < 1) {
            return;
        }

        try {
            $this->db->table($this->db->prefixTable("auth_mfa_challenges"))
                ->where("challenge_id", $challengeId)
                ->where("user_id", $userId)
                ->where("consumed_at", null)
                ->update(["consumed_at" => gmdate("Y-m-d H:i:s")]);
        } catch (\Throwable $exception) {
            log_message("error", "Unable to revoke MFA challenge: {message}", [
                "message" => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{status: string, remaining_attempts: int}
     */
    public function verify_and_consume_mfa_challenge(
        string $challengeId,
        int $userId,
        string $code,
        string $hmacKey
    ): array {
        $invalid = ["status" => "invalid", "remaining_attempts" => 0];
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId)
            || $userId < 1
            || !preg_match('/^[0-9]{6,8}$/D', $code)
            || strlen($hmacKey) < 32) {
            return $invalid;
        }

        $table = $this->db->prefixTable("auth_mfa_challenges");
        $this->db->transBegin();
        try {
            $row = $this->db->query(
                "SELECT * FROM {$table}
                 WHERE challenge_id = ? AND user_id = ? AND purpose = 'signin'
                 LIMIT 1
                 FOR UPDATE",
                [$challengeId, $userId]
            )->getRow();

            if (!$row || !empty($row->consumed_at)) {
                $this->db->transRollback();
                return $invalid;
            }
            if ($this->utc_timestamp($row->expires_at ?? null) <= time()) {
                $this->db->query(
                    "UPDATE {$table} SET consumed_at = ? WHERE id = ? AND consumed_at IS NULL",
                    [gmdate("Y-m-d H:i:s"), (int) $row->id]
                );
                $this->db->transCommit();
                return ["status" => "expired", "remaining_attempts" => 0];
            }

            $attempts = (int) $row->attempts + 1;
            $maximum = (int) $row->max_attempts;
            $valid = hash_equals(
                (string) $row->code_hash,
                hash_hmac("sha256", $challengeId . "|" . $code, $hmacKey)
            );
            $consumedAt = ($valid || $attempts >= $maximum)
                ? gmdate("Y-m-d H:i:s")
                : null;

            $this->db->query(
                "UPDATE {$table}
                 SET attempts = ?, consumed_at = ?
                 WHERE id = ? AND consumed_at IS NULL",
                [$attempts, $consumedAt, (int) $row->id]
            );
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return $invalid;
            }
            $this->db->transCommit();

            return [
                "status" => $valid ? "verified" : ($attempts >= $maximum ? "locked" : "invalid"),
                "remaining_attempts" => max(0, $maximum - $attempts),
            ];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            log_message("error", "Unable to verify MFA challenge: {message}", [
                "message" => $exception->getMessage(),
            ]);
            return $invalid;
        }
    }

    private function parse_reset_token(string $token): ?array
    {
        if (!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/D', $token, $matches)) {
            return null;
        }

        return ["selector" => $matches[1], "validator" => $matches[2]];
    }

    private function utc_timestamp($value): int
    {
        if (!$value) {
            return 0;
        }

        $date = \DateTimeImmutable::createFromFormat(
            "Y-m-d H:i:s",
            (string) $value,
            new \DateTimeZone("UTC")
        );

        return $date ? $date->getTimestamp() : 0;
    }

}
