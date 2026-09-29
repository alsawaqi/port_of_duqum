<?php

namespace App\Libraries;

/** Lossless, opaque PNG output without requiring GD/Imagick on the new host. */
final class Gate_pass_qr
{
    public static function png(string $token, int $scale = 6): string
    {
        if ($token === '' || strlen($token) > 1024) {
            throw new \InvalidArgumentException('Invalid Gate Pass QR token.');
        }
        require_once APPPATH . 'ThirdParty/tcpdf/tcpdf_barcodes_2d.php';
        $matrix = (new \TCPDF2DBarcode($token, 'QRCODE,H'))->getBarcodeArray();
        $scale = max(1, min(12, $scale));
        // The four-module quiet zone is required for reliable scanning.
        $size = ((int) $matrix['num_cols'] + 8) * $scale;
        $raw = '';
        for ($y = -4; $y < (int) $matrix['num_rows'] + 4; $y++) {
            $row = '';
            for ($x = -4; $x < (int) $matrix['num_cols'] + 4; $x++) {
                $black = $y >= 0 && $x >= 0 && !empty($matrix['bcode'][$y][$x]);
                $row .= str_repeat($black ? "\x00" : "\xff", $scale);
            }
            $raw .= str_repeat("\x00" . $row, $scale);
        }
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0))
            . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
    }
}
