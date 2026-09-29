<?php
// Verify actual ROP dropdown coverage, form rendering and save/edit round trips.
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development'); define('CI_DEBUG', true);
require 'app/Config/Paths.php'; require 'system/Boot.php';
class OmanPlateCodesTestBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
OmanPlateCodesTestBoot::init();
helper(['general', 'date_time', 'url', 'language', 'form']);
config('Rise')->app_settings_array = ['language'=>'english', 'timezone'=>'Asia/Muscat', 'date_format'=>'Y-m-d'];
config('App')->baseURL = 'https://example.invalid/';
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) throw new ErrorException($message, 0, $level, $file, $line);
});
$checks = 0;
$check = static function ($ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
$snapshot = json_decode(file_get_contents(__DIR__.'/fixtures/oman_plate_codes_2026-09-27.json'), true, 512, JSON_THROW_ON_ERROR);
$pairs = gate_pass_oman_plate_code_pairs();
$check(count($pairs) === 97, 'All 97 ROP codes represented exactly once');
$expected = array_map(static fn($code) => str_replace(' ', '', $code), $snapshot['codes']['english']);
$actual = array_keys($pairs); sort($expected); sort($actual);
$check($expected === $actual, 'Latin catalog exactly matches the independent ROP snapshot');
$expected = array_map(static fn($code) => str_replace(' ', '', $code), $snapshot['codes']['arabic']);
$actual = array_values($pairs); sort($expected); sort($actual);
$check($expected === $actual, 'Arabic catalog exactly matches the independent ROP snapshot');

foreach ($snapshot['codes'] as $language=>$codes) {
    service('request')->setLocale($language);
    service('language')->setLocale($language);
    $options = gate_pass_oman_plate_prefix_options_for_ui();
    foreach ($codes as $printedCode) {
        $code = str_replace(' ', '', $printedCode);
        $check(($options[$code] ?? null) === $printedCode, "$language dropdown contains $printedCode");
        $payload = gate_pass_prepare_vehicle_plate_payload(false, $code, '00123', '', '');
        $check(($payload['ok'] ?? false) && $payload['data']['plate_no'] === "$code 00123", "Save $printedCode preserves digits");
        $check($payload['data']['is_international_plate'] === 0 && $payload['data']['plate_country'] === null, 'Omani plate stays domestic');
        $spaced = gate_pass_plate_merge_from_post_parts($printedCode, '00123');
        $check(($spaced['plate'] ?? null) === "$code 00123", "Printed code spacing is accepted: $printedCode");
        foreach (["$code 00123", "$printedCode 00123", $code.'00123'] as $storedPlate) {
            $check(gate_pass_plate_split_prefix_digits($storedPlate) === ['prefix'=>$code, 'digits'=>'00123'], "Reopen $storedPlate");
            $check(gate_pass_plate_no_is_valid($storedPlate), "Validate $storedPlate");
        }
    }
    foreach (['gate_pass_portal/requests/vehicle_modal_form', 'gate_pass_security_inbox/vehicle_modal_form'] as $viewName) {
        $html = view($viewName, ['model_info'=>null, 'gate_pass_request_id'=>1], ['saveData'=>false]);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $rendered = [];
        foreach ($xpath->query('//select[@name="plate_prefix"]/option') as $option) {
            $rendered[$option->getAttribute('value')] = trim($option->textContent);
        }
        foreach ($codes as $printedCode) {
            $check(($rendered[str_replace(' ', '', $printedCode)] ?? null) === $printedCode, "$viewName renders $language code $printedCode");
        }
        // A saved plate must remain selected even when the interface changes language.
        foreach (['SS 00123', 'س س 00123', 'MCT 123', 'ZZ 123'] as $storedPlate) {
            $row = (object)['id'=>1, 'plate_no'=>$storedPlate, 'mulkiyah_attachment_path'=>'', 'type'=>'private'];
            $edit = view($viewName, ['model_info'=>$row, 'gate_pass_request_id'=>1], ['saveData'=>false]);
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $edit);
            $xpath = new DOMXPath($dom);
            $selected = $xpath->query('//select[@name="plate_prefix"]/option[@selected]');
            $split = gate_pass_plate_split_prefix_digits($storedPlate);
            $check($selected->length === 1 && $selected->item(0)->getAttribute('value') === $split['prefix'], "$viewName keeps saved $storedPlate selected in $language");
        }
    }
}

foreach (array_merge(range('A', 'Z'), ['MCT','DH','SH','HD','LK','TB','TC','TD','TA','YB','EXP','OM','KA','KB','KC']) as $legacy) {
    $check(isset(gate_pass_oman_plate_latin_prefix_values()[$legacy]), "Preserve legacy Latin code $legacy");
}
foreach (['أ','ب','ت','ث','ج','ح','خ','د','ذ','ر','ز','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','ه','و','ي','ى','ة'] as $legacy) {
    $check(isset(gate_pass_oman_plate_arabic_prefix_values()[$legacy]), "Preserve legacy Arabic letter $legacy");
}
foreach (['<script>', 'A/B', 'A1', 'س<script>', 'سسس'] as $badCode) {
    $check(!gate_pass_plate_merge_from_post_parts($badCode, '123')['ok'], 'Reject invalid plate prefix');
}
$check(!gate_pass_plate_merge_from_post_parts('SS', '1234567')['ok'], 'Reject more than six digits');
$check(!gate_pass_plate_merge_from_post_parts('', '123')['ok'], 'Require a plate code');
$check(gate_pass_plate_merge_from_post_parts('s s', '00123')['plate'] === 'SS 00123', 'Accept lowercase printed Latin code');
$international = gate_pass_prepare_vehicle_plate_payload(true, '', '', 'United Arab Emirates', 'dubai / 12345');
$check($international['ok'] && $international['data']['international_plate_no'] === 'DUBAI / 12345', 'International plates retain their existing path');
echo "GatePassOmanPlateCodesTest: $checks checks passed; all 97 ROP codes in both languages, both forms, save/edit parsing and legacy plates.\n";
