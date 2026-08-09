<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Moves authorized tender-source documents out of the public web tree.
 * This migration deliberately fails closed on missing, malformed, unsupported,
 * or out-of-scope files so production cannot silently retain public paths.
 */
class Migrate_legacy_tender_documents extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists("tender_documents")) {
            return;
        }

        $table = $this->db->prefixTable("tender_documents");
        $rows = $this->db->query(
            "SELECT * FROM {$table}
             WHERE deleted=0 AND path LIKE 'files/tender_files/%'
             ORDER BY id ASC"
        )->getResult();
        if (!$rows) {
            return;
        }

        $legacyRoot = realpath(ROOTPATH . "files" . DIRECTORY_SEPARATOR . "tender_files");
        if (!$legacyRoot) {
            throw new \RuntimeException("Legacy tender root is unavailable.");
        }
        $legacyPrefix = $this->canonicalPrefix($legacyRoot);
        $allowedMimes = [
            "application/pdf" => "pdf",
            "image/jpeg" => "jpg",
            "image/png" => "png",
        ];
        $fields = array_flip($this->db->getFieldNames($table));
        $createdTargets = [];
        $sourcesToRemove = [];

        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                $tenderId = (int) ($row->tender_id ?? 0);
                $relative = str_replace("\\", "/", (string) ($row->path ?? ""));
                $expectedPrefix = "files/tender_files/{$tenderId}/";
                if ($tenderId < 1
                    || !str_starts_with($relative, $expectedPrefix)
                    || str_contains($relative, "\0")
                    || preg_match('#(^|/)\.{1,2}(/|$)#', $relative)) {
                    throw new \RuntimeException("Unsafe legacy tender path on document " . (int) $row->id);
                }

                $source = realpath(ROOTPATH . str_replace("/", DIRECTORY_SEPARATOR, $relative));
                if (!$source || !is_file($source)
                    || !str_starts_with($this->canonicalPath($source), $legacyPrefix)) {
                    throw new \RuntimeException("Legacy tender file is missing or outside its root.");
                }

                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source) ?: "";
                $extension = $allowedMimes[$mime] ?? null;
                if (!$extension || ($mime === "application/pdf" && !$this->pdfHasEof($source))) {
                    throw new \RuntimeException("Unsupported legacy tender document type.");
                }

                $targetDir = WRITEPATH . "uploads" . DIRECTORY_SEPARATOR
                    . "tender_documents" . DIRECTORY_SEPARATOR . "tender_{$tenderId}";
                if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
                    throw new \RuntimeException("Unable to create protected tender storage.");
                }

                $storedName = "td_" . bin2hex(random_bytes(24)) . "." . $extension;
                $target = $targetDir . DIRECTORY_SEPARATOR . $storedName;
                if (!copy($source, $target)) {
                    throw new \RuntimeException("Unable to copy a legacy tender document.");
                }
                $createdTargets[] = $target;
                @chmod($target, 0640);
                $sourceHash = hash_file("sha256", $source);
                $targetHash = hash_file("sha256", $target);
                if (filesize($source) !== filesize($target)
                    || $sourceHash === false
                    || $targetHash === false
                    || !hash_equals($sourceHash, $targetHash)) {
                    throw new \RuntimeException("Tender document copy verification failed.");
                }

                $data = [
                    "path" => "tender_documents/tender_{$tenderId}/{$storedName}",
                ];
                if (isset($fields["mime_type"])) {
                    $data["mime_type"] = $mime;
                }
                if (isset($fields["size_bytes"])) {
                    $data["size_bytes"] = filesize($target);
                }
                if (isset($fields["updated_at"])) {
                    $data["updated_at"] = gmdate("Y-m-d H:i:s");
                }

                $updated = $this->db->table($table)
                    ->where("id", (int) $row->id)
                    ->where("path", (string) $row->path)
                    ->update($data);
                if (!$updated || $this->db->affectedRows() !== 1) {
                    throw new \RuntimeException("Tender document row changed during migration.");
                }
                $sourcesToRemove[] = $source;
            }

            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new \RuntimeException("Tender storage migration transaction failed.");
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            foreach ($createdTargets as $target) {
                if (is_file($target)) {
                    @unlink($target);
                }
            }
            throw $exception;
        }

        foreach ($sourcesToRemove as $source) {
            if (!@unlink($source)) {
                log_message("critical", "Migrated tender source could not be removed: {path}", [
                    "path" => $source,
                ]);
            }
        }
    }

    public function down()
    {
        // Protected documents are not copied back into a public directory.
    }

    private function canonicalPrefix(string $path): string
    {
        return rtrim($this->canonicalPath($path), "/") . "/";
    }

    private function canonicalPath(string $path): string
    {
        $path = str_replace("\\", "/", $path);
        return DIRECTORY_SEPARATOR === "\\" ? strtolower($path) : $path;
    }

    private function pdfHasEof(string $path): bool
    {
        $size = filesize($path);
        $handle = fopen($path, "rb");
        if ($size === false || !$handle) {
            return false;
        }
        fseek($handle, max(0, $size - 2048));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);
        return str_contains($tail, "%%EOF");
    }
}
