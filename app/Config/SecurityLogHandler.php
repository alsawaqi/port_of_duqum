<?php

namespace Config;

use CodeIgniter\Log\Handlers\FileHandler;

/**
 * File logger that removes common credentials before anything reaches disk.
 */
final class SecurityLogHandler extends FileHandler
{
    public function handle($level, $message): bool
    {
        return parent::handle(
            $level,
            self::redactSensitiveData((string) $message)
        );
    }

    public static function redactSensitiveData(string $message): string
    {
        $message = (string) preg_replace(
            '/\b(Bearer)\s+[A-Za-z0-9._~+\/=:-]+/i',
            '$1 [REDACTED]',
            $message
        );
        $message = (string) preg_replace(
            '/\b((?:Authorization|Proxy-Authorization|Cookie|Set-Cookie)\s*:\s*)[^\r\n]+/i',
            '$1[REDACTED]',
            $message
        );
        $message = (string) preg_replace(
            '/(\b(?:password|passwd|pwd|secret|client_secret|token|access_token|refresh_token|id_token|api[_-]?key|csrf(?:_token)?|auth_token|authorization|proxy_authorization|cookie|set_cookie|encryption_key|private_key|db_password|smtp_pass|access_key)\b["\']?\s*(?::|=>|=)\s*)(?:"[^"]*"|\'[^\']*\'|[^&\s,;}]+)/i',
            '$1[REDACTED]',
            $message
        );
        $message = (string) preg_replace(
            '~(https?://)[^/\s:@]+:[^/@\s]+@~i',
            '$1[REDACTED]@',
            $message
        );

        return $message;
    }
}
