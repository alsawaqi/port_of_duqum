<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$read = static function (string $path) use ($root, $fail): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        $fail('Unable to read ' . $path . '.');
    }
    return $source;
};
$contains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (!str_contains($source, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$method = static function (string $source, string $start, string $end) use ($fail): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from + strlen($start));
    if ($from === false || $to === false) {
        $fail('Unable to isolate ' . $start . '.');
    }
    return substr($source, $from, $to - $from);
};

$security = $read('app/Controllers/Security_Controller.php');
$constructor = $method(
    $security,
    'public function __construct',
    'private function _confine_vendor_only_identity'
);

$contains(
    '$enforceExternalControllerBoundary = (bool) $redirect',
    $constructor,
    'the normal protected-controller boundary remains enabled'
);
$contains(
    '((int) $login_user_id > 0 && get_class($this) !== self::class)',
    $constructor,
    'authenticated controller subclasses cannot bypass confinement with redirect=false'
);
$contains(
    '$this->_redirect_external_dashboard($enforceExternalControllerBoundary);',
    $constructor,
    'revoked external memberships are handled under the global boundary'
);
$contains(
    'if ($enforceExternalControllerBoundary)',
    $constructor,
    'every external identity classifier is enforced for authenticated subclasses'
);

$guard = $method(
    $security,
    'protected function access_only_non_external_portal_identity',
    'private function _redirect_external_dashboard'
);
$contains(
    '(int) ($this->login_user->id ?? 0) > 0',
    $guard,
    'the defense-in-depth guard preserves anonymous public access'
);
foreach ([
    'is_vendor_only_identity',
    'is_gate_pass_only_identity',
    'is_ptw_applicant_only_identity',
] as $identityFlag) {
    $contains(
        $identityFlag,
        $guard,
        'the defense-in-depth guard covers ' . $identityFlag
    );
}
$contains('app_redirect(', $guard, 'authenticated external identities redirect');
$contains('forbidden', $guard, 'authenticated external identities fail closed');

foreach (['Contract', 'Estimate', 'Offer', 'Store'] as $controller) {
    $contains(
        'parent::__construct(false);',
        $read('app/Controllers/' . $controller . '.php'),
        $controller . ' retains anonymous public-controller behavior'
    );
}

$contains(
    'new Security_Controller(false)',
    $read('app/Libraries/Template.php'),
    'exact base-controller utility construction remains supported'
);
$contains(
    'new Security_Controller(false)',
    $read('app/Libraries/Left_menu.php'),
    'left-menu utility construction remains supported'
);

$store = $read('app/Controllers/Store.php');
$itemView = $method(
    $store,
    'function item_view()',
    'protected function check_access_to_this_item'
);
$guardPosition = strpos(
    $itemView,
    '$this->access_only_non_external_portal_identity();'
);
$lookupPosition = strpos($itemView, '$this->Items_model->get_details(');
if ($guardPosition === false || $lookupPosition === false || $guardPosition > $lookupPosition) {
    $fail('Store::item_view must reject external identities before loading item data.');
}

echo 'External controller escape hardening contracts passed.' . PHP_EOL;
