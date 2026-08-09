<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Verifies migration-owned database prerequisites without changing schema at request time.
 */
class Runtime_schema_guard
{
    private static array $verified = [];

    /**
     * @param array<string, list<string>> $requirements Logical table names mapped to required fields.
     */
    public static function requireTablesAndColumns(
        BaseConnection $db,
        array $requirements,
        string $feature
    ): void {
        $cache_key = spl_object_id($db) . ":structure:" . hash("sha256", serialize($requirements));
        if (isset(self::$verified[$cache_key])) {
            return;
        }

        $issues = [];

        try {
            foreach ($requirements as $table => $columns) {
                if (!$db->tableExists($table)) {
                    $issues[] = $db->prefixTable($table) . " (missing table)";
                    continue;
                }

                $available = $db->getFieldNames($table);
                foreach ($columns as $column) {
                    if (!in_array($column, $available, true)) {
                        $issues[] = $db->prefixTable($table) . "." . $column . " (missing field)";
                    }
                }
            }
        } catch (Throwable $e) {
            log_message("critical", "Database readiness inspection failed for {$feature}: " . $e->getMessage());
            throw new RuntimeException(
                "The database schema could not be verified for {$feature}. Apply pending migrations before serving requests.",
                0,
                $e
            );
        }

        if ($issues) {
            self::fail($feature, $issues);
        }

        self::$verified[$cache_key] = true;
    }

    /**
     * @param array{nullable?: bool, types?: list<string>} $expectations
     */
    public static function requireColumnProperties(
        BaseConnection $db,
        string $table,
        string $column,
        array $expectations,
        string $feature
    ): void {
        $cache_key = spl_object_id($db) . ":field:" . hash(
            "sha256",
            serialize([$table, $column, $expectations])
        );
        if (isset(self::$verified[$cache_key])) {
            return;
        }

        self::requireTablesAndColumns($db, [$table => [$column]], $feature);

        try {
            $field = null;
            foreach ($db->getFieldData($table) as $candidate) {
                if ((string) ($candidate->name ?? "") === $column) {
                    $field = $candidate;
                    break;
                }
            }

            $issues = [];
            if (!$field) {
                $issues[] = $db->prefixTable($table) . "." . $column . " (metadata unavailable)";
            } else {
                if (array_key_exists("nullable", $expectations)
                    && (bool) ($field->nullable ?? false) !== (bool) $expectations["nullable"]
                ) {
                    $issues[] = $db->prefixTable($table) . "." . $column . " (unexpected nullability)";
                }

                $types = array_map("strtolower", $expectations["types"] ?? []);
                if ($types && !in_array(strtolower((string) ($field->type ?? "")), $types, true)) {
                    $issues[] = $db->prefixTable($table) . "." . $column . " (unexpected type)";
                }
            }
        } catch (Throwable $e) {
            log_message("critical", "Database field readiness inspection failed for {$feature}: " . $e->getMessage());
            throw new RuntimeException(
                "The database schema could not be verified for {$feature}. Apply pending migrations before serving requests.",
                0,
                $e
            );
        }

        if ($issues) {
            self::fail($feature, $issues);
        }

        self::$verified[$cache_key] = true;
    }

    /**
     * @param list<string> $required_fragments
     */
    public static function requireColumnDefinitionContains(
        BaseConnection $db,
        string $table,
        string $column,
        array $required_fragments,
        string $feature
    ): void {
        $cache_key = spl_object_id($db) . ":definition:" . hash(
            "sha256",
            serialize([$table, $column, $required_fragments])
        );
        if (isset(self::$verified[$cache_key])) {
            return;
        }

        self::requireTablesAndColumns($db, [$table => [$column]], $feature);

        try {
            $row = $db->query(
                "SELECT COLUMN_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1",
                [$db->getDatabase(), $db->prefixTable($table), $column]
            )->getRow();
            $definition = strtolower((string) ($row->COLUMN_TYPE ?? ""));
            $issues = [];

            foreach ($required_fragments as $fragment) {
                if ($definition === "" || strpos($definition, strtolower($fragment)) === false) {
                    $issues[] = $db->prefixTable($table) . "." . $column . " (incompatible definition)";
                    break;
                }
            }
        } catch (Throwable $e) {
            log_message("critical", "Database field definition inspection failed for {$feature}: " . $e->getMessage());
            throw new RuntimeException(
                "The database schema could not be verified for {$feature}. Apply pending migrations before serving requests.",
                0,
                $e
            );
        }

        if ($issues) {
            self::fail($feature, $issues);
        }

        self::$verified[$cache_key] = true;
    }

    /**
     * @param list<string> $issues
     */
    private static function fail(string $feature, array $issues): void
    {
        log_message("critical", "Database readiness check failed for {$feature}: " . implode(", ", $issues));

        throw new RuntimeException(
            "The database schema is not ready for {$feature}. Apply pending migrations before serving requests."
        );
    }
}
