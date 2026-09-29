<?php

namespace App\Libraries;

trait Saves_unique_master_code
{
    public ?string $save_error = null;

    public function ci_save($data = [], $id = 0)
    {
        $this->save_error = null;
        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper(trim((string) $data['code']));
            $this->save_error = Unique_master_code::error($this->db, $this->table_without_prefix, $data['code'], (int) $id);
            if ($this->save_error) { return false; }
        }
        // These parent IDs are foreign keys, so reject stale/tampered choices
        // before SQL rather than exposing a database exception to the user.
        $parent = match ($this->table_without_prefix) {
            'regions' => ['country_id', 'country'],
            'cities' => ['regions_id', 'regions'],
            default => null,
        };
        if ($parent && array_key_exists($parent[0], $data)) {
            $valid = $this->db->table($parent[1])->where('id', (int) $data[$parent[0]])->where('deleted', 0)->countAllResults();
            if (!$valid) { $this->save_error = 'master_parent_invalid'; return false; }
        }
        try {
            $saved = parent::ci_save($data, $id);
            if ($saved) { return $saved; }
        } catch (\Throwable $e) {
            // A concurrent save can still hit the unique index after validation.
            log_message('error', 'Master data save refused in ' . $this->table_without_prefix . ': ' . get_class($e));
        }
        $this->save_error = (int) ($this->db->error()['code'] ?? 0) === 1062 ? 'master_code_duplicate' : 'error_occurred';
        return false;
    }
}
