<?php

namespace App\Libraries;

/** The database's global code uniqueness also includes archived master records. */
final class Unique_master_code
{
    public static function error($db, string $table, string $code, int $id = 0): ?string
    {
        $code = trim($code);
        if ($code === '') {
            return 'master_code_required';
        }
        $row = $db->table($table)->select('id,deleted')->where('code', $code)
            ->where('id !=', $id)->get()->getRow();
        if (!$row) {
            return null;
        }
        return !empty($row->deleted) ? 'master_code_archived' : 'master_code_duplicate';
    }
}
