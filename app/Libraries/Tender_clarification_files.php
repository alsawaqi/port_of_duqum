<?php

namespace App\Libraries;

use App\Models\Tender_communications_model;

class Tender_clarification_files
{
    /** Caller owns the message transaction. Remove every staged file on storage failure. */
    public static function save($request, int $communicationId, int $tenderId, ?int $vendorId, int $userId): void
    {
        $files = $request->getFileMultiple('clarification_files') ?: [];
        $security = new Upload_security();
        $files = array_values(array_filter($files, static fn($file) => $file && $file->getError() !== UPLOAD_ERR_NO_FILE));
        foreach ($files as $file) {
            $security->validateUploadedFile($file, Upload_security::CONTEXT_SECURITY_DOCUMENT);
        }
        $relative = "tender_clarifications/tender_$tenderId/communication_$communicationId/";
        $directory = WRITEPATH . 'uploads/' . $relative;
        $paths = []; $rows = [];
        try {
            foreach ($files as $file) {
                $stored = $security->storeUploadedFile($file, $directory, Upload_security::CONTEXT_SECURITY_DOCUMENT, 'tc_');
                $paths[] = $directory . $stored['stored_name'];
                $rows[] = ['disk' => 'local', 'path' => $relative . $stored['stored_name'],
                    'original_name' => $stored['original_name'], 'mime_type' => $stored['detected_mime'], 'size_bytes' => $stored['size_bytes']];
            }
            (new Tender_communications_model())->save_attachments($communicationId, $tenderId, $vendorId, $rows, $userId);
            if (!db_connect()->transStatus()) { throw new \RuntimeException('Clarification storage failed.'); }
        } catch (\Throwable $e) {
            foreach ($paths as $path) { if (is_file($path)) { unlink($path); } }
            throw $e;
        }
    }
}
