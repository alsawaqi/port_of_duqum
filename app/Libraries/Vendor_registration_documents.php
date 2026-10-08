<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

/** The same current document requirements drive the public form and server validation. */
final class Vendor_registration_documents
{
    public function __construct(private BaseConnection $db) {}

    public function definitions(int $groupId, ?int $riyadaTypeId = null): array
    {
        $table = $this->db->prefixTable('vendor_document_types');
        $types = [];
        foreach ($this->db->query("SELECT id, name, code, is_required FROM {$table}
            WHERE deleted=0 AND is_active=1 AND (vendor_group_id IS NULL OR vendor_group_id=?)
            ORDER BY is_required DESC, name, id", [$groupId])->getResult() as $row) {
            $id = (int) $row->id;
            $types[$id] = ['id' => $id, 'name' => (string) $row->name, 'code' => (string) $row->code,
                'required' => (bool) $row->is_required || $id === $riyadaTypeId];
        }
        if ($riyadaTypeId && !isset($types[$riyadaTypeId])) {
            throw new DomainException('Riyadha is not configured for this vendor group. Please contact the administrator.');
        }
        return $types;
    }

    /** Returns complete, typed uploads only. Called before creating the vendor or its user. */
    public function submission(array $types, array $post, array $files, ?int $riyadaTypeId = null): array
    {
        $rows = [];
        $issued = $post['registration_issued_at'] ?? [];
        $expires = $post['registration_expires_at'] ?? [];
        if (!is_array($issued) || !is_array($expires)) {
            throw new DomainException(app_lang('vendor_registration_document_dates_invalid'));
        }
        foreach ($files as $name => $file) {
            if (!str_starts_with((string) $name, 'registration_file_')) { continue; }
            $id = substr($name, strlen('registration_file_'));
            if (!ctype_digit($id) || !isset($types[(int) $id])) {
                throw new DomainException(app_lang('vendor_registration_document_type_unavailable'));
            }
            $this->addRow($rows, $types, (int) $id, $file, $issued[$id] ?? null, $expires[$id] ?? null, true);
        }
        // Empty fixed rows have no upload, but dates alone must not be silently discarded.
        foreach ($types as $id => $type) {
            if (!array_key_exists('registration_file_' . $id, $files)) {
                $this->addRow($rows, $types, $id, null, $issued[$id] ?? null, $expires[$id] ?? null, true);
            }
        }
        // Additional documents retain the existing repeatable-field contract.
        $extra = [];
        foreach (['vendor_document_type_id', 'issued_at', 'expires_at'] as $field) {
            $value = $post[$field] ?? [];
            $extra[$field] = is_array($value) ? $value : [$value];
        }
        $uploads = $files['file'] ?? [];
        $uploads = is_array($uploads) ? $uploads : [$uploads];
        $keys = array_unique(array_merge(array_keys($uploads), array_keys($extra['vendor_document_type_id']),
            array_keys($extra['issued_at']), array_keys($extra['expires_at'])));
        foreach ($keys as $key) {
            $id = $extra['vendor_document_type_id'][$key] ?? '';
            if (!is_scalar($id) || ($id !== '' && !ctype_digit((string) $id))) {
                throw new DomainException(app_lang('vendor_registration_document_type_unavailable'));
            }
            $this->addRow($rows, $types, (int) $id, $uploads[$key] ?? null,
                $extra['issued_at'][$key] ?? null, $extra['expires_at'][$key] ?? null, false);
        }
        // Accept an open point-1 form without asking for Riyadha twice. All current requirements still apply.
        if (isset($files['riyada_file']) && $files['riyada_file'] instanceof UploadedFile
            && $files['riyada_file']->getError() !== UPLOAD_ERR_NO_FILE) {
            if (!$riyadaTypeId) { throw new DomainException(app_lang('vendor_registration_document_type_unavailable')); }
            $this->addRow($rows, $types, $riyadaTypeId, $files['riyada_file'], null, null, true);
        }
        $submitted = array_column($rows, 'vendor_document_type_id');
        $missing = [];
        foreach ($types as $id => $type) {
            if ($type['required'] && !in_array($id, $submitted, true)) { $missing[] = $type['name']; }
        }
        if ($missing) {
            throw new DomainException(app_lang('vendor_registration_required_documents_missing') . ' ' . implode(', ', $missing));
        }
        foreach ($rows as &$row) {
            $row['registration_riyada'] = $row['vendor_document_type_id'] === $riyadaTypeId;
            if ($row['registration_riyada']) { $riyadaTypeId = null; }
        }
        unset($row);
        return $rows;
    }

    private function addRow(array &$rows, array $types, int $id, mixed $file, mixed $issued, mixed $expires, bool $fixed): void
    {
        $hasFile = $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
        $hasDates = ($issued !== null && $issued !== '') || ($expires !== null && $expires !== '');
        if (!$hasFile && !$hasDates && ($fixed || !$id) && ($file === null || $file instanceof UploadedFile)) { return; }
        if (!isset($types[$id])) { throw new DomainException(app_lang('vendor_registration_document_type_unavailable')); }
        if (!$hasFile || !$file->isValid() || $file->hasMoved()) {
            throw new DomainException(app_lang('vendor_registration_document_upload_incomplete') . ' ' . $types[$id]['name']);
        }
        $issued = $this->date($issued);
        $expires = $this->date($expires);
        if ($issued && $expires && $expires < $issued) {
            throw new DomainException(app_lang('vendor_registration_document_dates_invalid') . ' ' . $types[$id]['name']);
        }
        $rows[] = ['vendor_document_type_id' => $id, 'file' => $file, 'issued_at' => $issued, 'expires_at' => $expires];
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new DomainException(app_lang('vendor_registration_document_dates_invalid'));
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
            throw new DomainException(app_lang('vendor_registration_document_dates_invalid'));
        }
        return $value;
    }
}
