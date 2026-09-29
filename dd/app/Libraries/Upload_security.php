<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

class UploadSecurityException extends \RuntimeException
{
}

/**
 * Central, fail-safe validation and storage for untrusted uploads.
 *
 * Malware scanners can register app_filter_secure_upload_malware_scan and
 * return ["configured" => true, "clean" => true|false]. The application keeps
 * the scanner as an integration hook by default, and can be configured to fail
 * closed by setting UPLOAD_MALWARE_SCAN_FAIL_CLOSED=true once a scanner exists.
 */
class Upload_security
{
    public const CONTEXT_GENERIC = "generic";
    public const CONTEXT_IMAGE = "image";
    public const CONTEXT_SPREADSHEET = "spreadsheet";
    public const CONTEXT_SECURITY_DOCUMENT = "security_document";
    public const CONTEXT_SIGNATURE_IMAGE = "signature_image";

    private const EXECUTABLE_EXTENSIONS = [
        "cgi", "dll", "exe", "htaccess", "inc", "js", "jsp", "mjs", "msi",
        "phar", "php", "php3", "php4", "php5", "php7", "php8", "phtml",
        "pl", "py", "shtml", "sh", "svg", "vbs",
    ];

    private const TYPE_RULES = [
        "jpg" => ["mimes" => ["image/jpeg", "image/pjpeg"], "magic" => "jpeg"],
        "jpeg" => ["mimes" => ["image/jpeg", "image/pjpeg"], "magic" => "jpeg"],
        "png" => ["mimes" => ["image/png"], "magic" => "png"],
        "gif" => ["mimes" => ["image/gif"], "magic" => "gif"],
        "webp" => ["mimes" => ["image/webp"], "magic" => "webp"],
        "pdf" => ["mimes" => ["application/pdf"], "magic" => "pdf"],
        "doc" => ["mimes" => ["application/msword", "application/x-ole-storage", "application/cdfv2"], "magic" => "ole"],
        "xls" => ["mimes" => ["application/vnd.ms-excel", "application/x-ole-storage", "application/cdfv2"], "magic" => "ole"],
        "ppt" => ["mimes" => ["application/vnd.ms-powerpoint", "application/x-ole-storage", "application/cdfv2"], "magic" => "ole"],
        "docx" => ["mimes" => ["application/vnd.openxmlformats-officedocument.wordprocessingml.document", "application/zip", "application/x-zip-compressed"], "magic" => "docx"],
        "xlsx" => ["mimes" => ["application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/zip", "application/x-zip-compressed"], "magic" => "xlsx"],
        "pptx" => ["mimes" => ["application/vnd.openxmlformats-officedocument.presentationml.presentation", "application/zip", "application/x-zip-compressed"], "magic" => "pptx"],
        "csv" => ["mimes" => ["text/plain", "text/csv", "application/csv", "application/vnd.ms-excel"], "magic" => "text"],
        "txt" => ["mimes" => ["text/plain"], "magic" => "text"],
        "rtf" => ["mimes" => ["application/rtf", "text/rtf", "text/plain"], "magic" => "rtf"],
        "zip" => ["mimes" => ["application/zip", "application/x-zip-compressed"], "magic" => "zip"],
        "mp3" => ["mimes" => ["audio/mpeg", "audio/mp3"], "magic" => "mp3"],
        "wav" => ["mimes" => ["audio/wav", "audio/x-wav", "audio/wave"], "magic" => "wav"],
        "ogg" => ["mimes" => ["audio/ogg", "video/ogg", "application/ogg"], "magic" => "ogg"],
        "webm" => ["mimes" => ["audio/webm", "video/webm"], "magic" => "webm"],
        "mp4" => ["mimes" => ["video/mp4", "application/mp4"], "magic" => "isobmff"],
        "m4a" => ["mimes" => ["audio/mp4", "audio/x-m4a", "video/mp4"], "magic" => "isobmff"],
        "mov" => ["mimes" => ["video/quicktime"], "magic" => "isobmff"],
    ];

    /** @var callable|null */
    private $scanner;
    private bool $failClosed;

    public function __construct(?callable $scanner = null, ?bool $failClosed = null)
    {
        $this->scanner = $scanner;
        $this->failClosed = $failClosed ?? $this->defaultFailClosed();
    }

    public function validateClientClaim(
        string $originalName,
        int $declaredSize,
        string $context,
        ?array $allowedExtensions = null
    ): array {
        $extension = $this->validatedExtension($originalName, $context, $allowedExtensions);
        $maximum = $this->maximumBytes($context);

        if ($declaredSize < 1 || $declaredSize > $maximum) {
            throw new UploadSecurityException("The file exceeds the allowed upload size.");
        }

        return [
            "original_name" => $this->safeOriginalName($originalName),
            "extension" => $extension,
            "size_bytes" => $declaredSize,
            "max_size_bytes" => $maximum,
        ];
    }

    public function validateUploadedFile(
        UploadedFile $file,
        string $context,
        ?array $allowedExtensions = null
    ): array {
        if (!$file->isValid() || $file->hasMoved()) {
            throw new UploadSecurityException("The uploaded file is invalid or incomplete.");
        }

        return $this->validatePath(
            $file->getTempName(),
            $file->getClientName(),
            (int)$file->getSize(),
            $context,
            $allowedExtensions
        );
    }

    public function validatePath(
        string $path,
        string $originalName,
        int $declaredSize,
        string $context,
        ?array $allowedExtensions = null
    ): array {
        $claim = $this->validateClientClaim($originalName, $declaredSize, $context, $allowedExtensions);
        if (!is_file($path) || !is_readable($path)) {
            throw new UploadSecurityException("The uploaded file cannot be read.");
        }

        $actualSize = filesize($path);
        if ($actualSize === false || $actualSize < 1 || $actualSize > $claim["max_size_bytes"]) {
            throw new UploadSecurityException("The file exceeds the allowed upload size.");
        }

        $extension = $claim["extension"];
        $rule = self::TYPE_RULES[$extension] ?? null;
        if (!$rule) {
            throw new UploadSecurityException("This file type is not supported.");
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower(trim((string)$finfo->file($path)));
        if (!in_array($detectedMime, $rule["mimes"], true)) {
            throw new UploadSecurityException("The file content does not match its extension.");
        }

        $this->assertMagic($path, $extension, $rule["magic"]);
        if ($context === self::CONTEXT_SIGNATURE_IMAGE) {
            $this->assertSignatureImageConstraints($path);
        }
        $this->assertMalwareSafe($path, $context, $detectedMime);

        return [
            "original_name" => $claim["original_name"],
            "extension" => $extension,
            "detected_mime" => $detectedMime,
            "size_bytes" => (int)$actualSize,
        ];
    }

    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        string $context,
        string $prefix = "upload_",
        ?array $allowedExtensions = null
    ): array {
        $metadata = $this->validateUploadedFile($file, $context, $allowedExtensions);
        $directory = $this->prepareDirectory($directory);
        $prefix = preg_replace('/[^a-z0-9_-]+/i', '', $prefix) ?: "upload_";
        $storedName = $prefix . bin2hex(random_bytes(24)) . "." . $metadata["extension"];

        if (!$file->move($directory, $storedName, false)) {
            throw new UploadSecurityException("The uploaded file could not be stored safely.");
        }

        $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
        @chmod($storedPath, 0640);

        return $metadata + [
            "stored_name" => $storedName,
            "path" => $storedPath,
        ];
    }

    /**
     * Safely persists untrusted bytes that did not arrive as an UploadedFile,
     * such as a browser signature-pad data URI.
     */
    public function storeUntrustedBytes(
        string $bytes,
        string $originalName,
        string $directory,
        string $context,
        string $prefix = "upload_",
        ?array $allowedExtensions = null
    ): array {
        $size = strlen($bytes);
        $this->validateClientClaim($originalName, $size, $context, $allowedExtensions);
        $directory = $this->prepareDirectory($directory);
        $prefix = preg_replace('/[^a-z0-9_-]+/i', '', $prefix) ?: "upload_";
        $temporaryPath = $directory . DIRECTORY_SEPARATOR . ".pending_" . bin2hex(random_bytes(24));
        $handle = @fopen($temporaryPath, "xb");
        if (!$handle) {
            throw new UploadSecurityException("The generated file could not be stored safely.");
        }

        $writeComplete = false;
        try {
            $written = 0;
            while ($written < $size) {
                $chunk = fwrite($handle, substr($bytes, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new UploadSecurityException("The generated file could not be stored safely.");
                }
                $written += $chunk;
            }
            if (!fflush($handle)) {
                throw new UploadSecurityException("The generated file could not be stored safely.");
            }
            $writeComplete = true;
        } finally {
            fclose($handle);
            if (!$writeComplete && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        @chmod($temporaryPath, 0600);
        try {
            $metadata = $this->validatePath(
                $temporaryPath,
                $originalName,
                $size,
                $context,
                $allowedExtensions
            );
            $storedName = $prefix . bin2hex(random_bytes(24)) . "." . $metadata["extension"];
            $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
            if (!@rename($temporaryPath, $storedPath)) {
                throw new UploadSecurityException("The generated file could not be stored safely.");
            }
            @chmod($storedPath, 0640);

            return $metadata + [
                "stored_name" => $storedName,
                "path" => $storedPath,
            ];
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * Fully inspects untrusted in-memory bytes without retaining them. This is
     * used for mail/API attachments before an existing storage adapter writes
     * or forwards the payload.
     */
    public function validateUntrustedBytes(
        string $bytes,
        string $originalName,
        string $context,
        ?array $allowedExtensions = null
    ): array {
        $size = strlen($bytes);
        $this->validateClientClaim($originalName, $size, $context, $allowedExtensions);
        $base = defined("WRITEPATH")
            ? WRITEPATH . "uploads" . DIRECTORY_SEPARATOR . ".inspection"
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . "pod_upload_inspection";
        $directory = $this->prepareDirectory($base);
        $temporaryPath = $directory . DIRECTORY_SEPARATOR . ".pending_" . bin2hex(random_bytes(24));
        $handle = @fopen($temporaryPath, "xb");
        if (!$handle) {
            throw new UploadSecurityException("The file could not be inspected safely.");
        }

        try {
            $written = 0;
            while ($written < $size) {
                $chunk = fwrite($handle, substr($bytes, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new UploadSecurityException("The file could not be inspected safely.");
                }
                $written += $chunk;
            }
            if (!fflush($handle)) {
                throw new UploadSecurityException("The file could not be inspected safely.");
            }
            fclose($handle);
            $handle = null;
            @chmod($temporaryPath, 0600);

            return $this->validatePath(
                $temporaryPath,
                $originalName,
                $size,
                $context,
                $allowedExtensions
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    public function maximumBytes(string $context): int
    {
        return match ($context) {
            self::CONTEXT_SIGNATURE_IMAGE => 1024 * 1024,
            self::CONTEXT_IMAGE => 5 * 1024 * 1024,
            self::CONTEXT_SPREADSHEET => 10 * 1024 * 1024,
            self::CONTEXT_SECURITY_DOCUMENT => 10 * 1024 * 1024,
            default => 20 * 1024 * 1024,
        };
    }

    public function prepareStorageDirectory(string $directory): string
    {
        return $this->prepareDirectory($directory);
    }

    public function allowedExtensions(string $context, ?array $requested = null): array
    {
        $policy = match ($context) {
            self::CONTEXT_SIGNATURE_IMAGE => ["png"],
            self::CONTEXT_IMAGE => ["jpg", "jpeg", "png"],
            self::CONTEXT_SPREADSHEET => ["csv", "xls", "xlsx"],
            self::CONTEXT_SECURITY_DOCUMENT => ["jpg", "jpeg", "png", "pdf"],
            default => array_keys(self::TYPE_RULES),
        };

        if ($requested === null) {
            return $policy;
        }

        $requested = array_values(array_unique(array_filter(array_map(
            static fn($extension) => strtolower(ltrim(trim((string)$extension), ".")),
            $requested
        ))));

        return array_values(array_intersect($policy, $requested));
    }

    private function validatedExtension(string $originalName, string $context, ?array $allowed): string
    {
        if ($originalName === "" || strlen($originalName) > 200 || str_contains($originalName, "\0")) {
            throw new UploadSecurityException("The file name is invalid.");
        }

        $unixName = str_replace("\\", "/", $originalName);
        if (basename($unixName) !== $unixName || str_contains($unixName, ":")) {
            throw new UploadSecurityException("Paths are not allowed in upload names.");
        }

        $parts = explode(".", strtolower($unixName));
        $extension = count($parts) > 1 ? (string)end($parts) : "";
        if ($extension === "" || array_intersect($parts, self::EXECUTABLE_EXTENSIONS)) {
            throw new UploadSecurityException("Executable upload types are prohibited.");
        }

        $allowlist = $this->allowedExtensions($context, $allowed);
        if (!in_array($extension, $allowlist, true)) {
            throw new UploadSecurityException("This file type is not allowed in this upload context.");
        }

        return $extension;
    }

    private function assertMagic(string $path, string $extension, string $magic): void
    {
        $handle = fopen($path, "rb");
        if (!$handle) {
            throw new UploadSecurityException("The uploaded file cannot be inspected.");
        }
        $header = (string)fread($handle, 8192);
        fclose($handle);

        $valid = match ($magic) {
            "jpeg" => str_starts_with($header, "\xFF\xD8\xFF") && $this->validImage($path, IMAGETYPE_JPEG),
            "png" => str_starts_with($header, "\x89PNG\x0D\x0A\x1A\x0A") && $this->validImage($path, IMAGETYPE_PNG),
            "gif" => (str_starts_with($header, "GIF87a") || str_starts_with($header, "GIF89a")) && $this->validImage($path, IMAGETYPE_GIF),
            "webp" => substr($header, 0, 4) === "RIFF" && substr($header, 8, 4) === "WEBP" && $this->validImage($path, defined("IMAGETYPE_WEBP") ? IMAGETYPE_WEBP : 18),
            "pdf" => str_starts_with($header, "%PDF-") && $this->pdfHasEof($path),
            "ole" => str_starts_with($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            "zip" => $this->hasZipMagic($header),
            "docx", "xlsx", "pptx" => $this->hasZipMagic($header) && $this->validOpenXml($path, $magic),
            "text" => !str_contains($header, "\0") && $this->looksLikeText($header),
            "rtf" => str_starts_with(ltrim($header), "{\\rtf"),
            "mp3" => str_starts_with($header, "ID3") || (isset($header[1]) && ord($header[0]) === 0xFF && (ord($header[1]) & 0xE0) === 0xE0),
            "wav" => substr($header, 0, 4) === "RIFF" && substr($header, 8, 4) === "WAVE",
            "ogg" => str_starts_with($header, "OggS"),
            "webm" => str_starts_with($header, "\x1A\x45\xDF\xA3"),
            "isobmff" => strlen($header) >= 12 && substr($header, 4, 4) === "ftyp",
            default => false,
        };

        if (!$valid) {
            throw new UploadSecurityException("The file signature is invalid for .{$extension}.");
        }
    }

    private function validImage(string $path, int $expectedType): bool
    {
        $info = @getimagesize($path);
        return is_array($info) && (int)($info[2] ?? 0) === $expectedType;
    }

    private function assertSignatureImageConstraints(string $path): void
    {
        $info = @getimagesize($path);
        if (
            !is_array($info)
            || (int)($info[2] ?? 0) !== IMAGETYPE_PNG
            || (int)($info[0] ?? 0) < 1
            || (int)($info[1] ?? 0) < 1
            || (int)$info[0] > 2048
            || (int)$info[1] > 2048
        ) {
            throw new UploadSecurityException("The signature image dimensions are invalid.");
        }

        $size = filesize($path);
        $handle = $size !== false ? @fopen($path, "rb") : false;
        if (!$handle || fseek($handle, max(0, (int)$size - 12)) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new UploadSecurityException("The signature image is incomplete.");
        }
        $tail = (string)fread($handle, 12);
        fclose($handle);
        if ($tail !== "\x00\x00\x00\x00IEND\xAE\x42\x60\x82") {
            throw new UploadSecurityException("The signature image contains trailing or invalid data.");
        }
    }

    /**
     * Resolves a database relative path only when it remains inside the
     * expected protected upload directory. Symlinks and traversal escape are
     * rejected after realpath resolution.
     */
    public function resolveStoredFile(string $relativePath, string $requiredPrefix): ?string
    {
        if (!defined("WRITEPATH")) {
            return null;
        }

        $relative = $this->normalizeStoredRelativePath($relativePath);
        $prefix = $this->normalizeStoredRelativePath($requiredPrefix);
        if ($relative === null || $prefix === null || $relative === $prefix || !str_starts_with($relative, $prefix . "/")) {
            return null;
        }

        $root = realpath(WRITEPATH . "uploads" . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $prefix));
        $candidate = realpath(WRITEPATH . "uploads" . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative));
        if ($root === false || $candidate === false || !is_file($candidate)) {
            return null;
        }

        $rootCheck = rtrim(str_replace(chr(92), "/", $root), "/") . "/";
        $candidateCheck = str_replace(chr(92), "/", $candidate);
        if (DIRECTORY_SEPARATOR === chr(92)) {
            $rootCheck = strtolower($rootCheck);
            $candidateCheck = strtolower($candidateCheck);
        }

        return str_starts_with($candidateCheck, $rootCheck) ? $candidate : null;
    }

    private function normalizeStoredRelativePath(string $path): ?string
    {
        if ($path === "" || str_contains($path, "\0") || str_contains($path, ":")) {
            return null;
        }
        $path = trim(str_replace(chr(92), "/", $path), "/");
        if ($path === "" || preg_match('#(^|/)\.{1,2}(/|$)#', $path)) {
            return null;
        }
        return $path;
    }

    private function pdfHasEof(string $path): bool
    {
        $size = filesize($path);
        if ($size === false) {
            return false;
        }
        $handle = fopen($path, "rb");
        if (!$handle) {
            return false;
        }
        fseek($handle, max(0, $size - 2048));
        $tail = (string)stream_get_contents($handle);
        fclose($handle);
        return str_contains($tail, "%%EOF");
    }

    private function hasZipMagic(string $header): bool
    {
        return str_starts_with($header, "PK\x03\x04")
            || str_starts_with($header, "PK\x05\x06")
            || str_starts_with($header, "PK\x07\x08");
    }

    private function validOpenXml(string $path, string $type): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $required = match ($type) {
            "docx" => "word/document.xml",
            "xlsx" => "xl/workbook.xml",
            "pptx" => "ppt/presentation.xml",
        };
        $valid = $zip->locateName("[Content_Types].xml") !== false
            && $zip->locateName($required) !== false;
        $zip->close();
        return $valid;
    }

    private function looksLikeText(string $bytes): bool
    {
        if ($bytes === "") {
            return false;
        }
        return preg_match('//u', $bytes) === 1
            || mb_check_encoding($bytes, "Windows-1252");
    }

    private function assertMalwareSafe(string $path, string $context, string $mime): void
    {
        $result = ["configured" => false, "clean" => null];
        if ($this->scanner) {
            $result = ["configured" => true, "clean" => (bool)call_user_func($this->scanner, $path, $context, $mime)];
        } elseif (function_exists("app_hooks")) {
            $filtered = app_hooks()->apply_filters("app_filter_secure_upload_malware_scan", [
                "configured" => false,
                "clean" => null,
                "path" => $path,
                "context" => $context,
                "detected_mime" => $mime,
            ]);
            if (is_array($filtered)) {
                $result = $filtered + $result;
            }
        }

        if (!empty($result["configured"]) && ($result["clean"] ?? null) !== true) {
            throw new UploadSecurityException("The file failed malware inspection.");
        }
        if (empty($result["configured"]) && $this->failClosed) {
            throw new UploadSecurityException("File scanning is unavailable; upload rejected.");
        }
    }

    private function prepareDirectory(string $directory): string
    {
        if ($directory === "" || str_contains($directory, "\0") || str_contains(str_replace("\\", "/", $directory), "/../")) {
            throw new UploadSecurityException("The upload destination is invalid.");
        }
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new UploadSecurityException("The secure upload directory could not be created.");
        }
        @chmod($directory, 0750);
        $resolved = realpath($directory);
        if ($resolved === false || !is_writable($resolved)) {
            throw new UploadSecurityException("The secure upload directory is unavailable.");
        }

        if (defined("WRITEPATH")) {
            $writeRoot = realpath(WRITEPATH);
            if ($writeRoot !== false) {
                $root = rtrim(str_replace("\\", "/", $writeRoot), "/") . "/";
                $candidate = rtrim(str_replace("\\", "/", $resolved), "/") . "/";
                if (!str_starts_with(strtolower($candidate), strtolower($root))) {
                    throw new UploadSecurityException("Uploads must be stored under the protected writable root.");
                }
            }
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    private function safeOriginalName(string $name): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace("\\", "/", $name))) ?: "upload";
    }

    private function defaultFailClosed(): bool
    {
        $configured = getenv("UPLOAD_MALWARE_SCAN_FAIL_CLOSED");
        if ($configured !== false && $configured !== "") {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }
        return false;
    }
}
