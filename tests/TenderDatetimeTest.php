<?php
require dirname(__DIR__) . '/app/Libraries/Tender_datetime.php';
use App\Libraries\Tender_datetime;
$cases = [
    ['2028-02-29', 'end', '2028-02-29 23:59:59'],
    ['2026-10-04', 'start', '2026-10-04 00:00:00'],
    ['2026-10-04T09:05', 'start', '2026-10-04 09:05:00'],
    ['2026-10-04 09:05:06', 'end', '2026-10-04 09:05:06'],
    ['', 'end', null], [null, 'end', null], [[], 'end', null],
];
foreach (['2026-02-29', '2026-04-31', '2026-13-01', '0000-00-00', '2026-10-04T24:00', '2026-10-04T12:60', '2026-10-04T12:00junk', 'tomorrow', '+1 week', '2026-10-04 09:00:60', '2026-10-04T09:00Z'] as $invalid) {
    $cases[] = [$invalid, 'end', null];
}
foreach ($cases as [$value, $edge, $expected]) {
    if (Tender_datetime::normalize($value, $edge) !== $expected) {
        throw new RuntimeException('Unexpected tender date normalization: ' . json_encode($value));
    }
}
echo count($cases) . " tender date behavior checks passed.\n";
