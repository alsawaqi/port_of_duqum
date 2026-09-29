<?php

namespace App\Libraries;

/** Only live records reserve business codes; SQL enforces the same rule. */
final class Unique_master_code
{
    public static function error($db, string $table, string $code, int $id = 0): ?string
    {
        $code = trim($code);
        if ($code === '') {
            return 'master_code_required';
        }
        $row = $db->table($table)->select('id,deleted')->where('code', $code)
            ->where('id !=', $id)->where('deleted', 0)->get()->getRow();
        if (!$row) {
            return null;
        }
        return 'master_code_duplicate';
    }
}
