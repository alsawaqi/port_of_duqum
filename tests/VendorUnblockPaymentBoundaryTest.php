<?php

namespace App\Controllers {
    class Security_Controller {
        public $request;
        public $login_user;
        public function access_only_vendors_update() {}
        public function validate_submitted_data($rules) {}
    }
}
namespace {
    require_once __DIR__ . '/../app/Controllers/Vendors.php';
    function vendor_blocked_status() { return 'suspended'; }
    function vendor_status_options() { return ['new','pending_payment','submitted','approved','rejected','revise','suspended','expired']; }
    function clean_data($data) { return $data; }
    function app_lang($key) { return $key; }
    $reflection = new ReflectionClass(\App\Controllers\Vendors::class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $db = new class {
        public ?string $previous = null;
        public function prefixTable($name) { return $name; }
        public function query($sql, $args) {
            return new class($this->previous) {
                public function __construct(private ?string $previous) {}
                public function getRow() { return $this->previous === null ? null : (object)['from_status' => $this->previous]; }
            };
        }
    };
    $reflection->getProperty('db')->setValue($controller, $db);
    $previousStatus = $reflection->getMethod('_last_status_before_block');
    $checks = 0;
    foreach ([null => 'new', 'approved' => 'approved', 'pending_payment' => 'pending_payment', 'suspended' => 'new', 'invalid' => 'new'] as $prior => $expected) {
        $db->previous = $prior === '' ? null : $prior;
        if ($previousStatus->invoke($controller, 1) !== $expected) { throw new RuntimeException('Unsafe restoration from ' . (string)$prior); }
        $checks++;
    }
    $db->previous = 'approved';
    $model = new class {
        public array $attemptedSave = [];
        public function get_one($id) { return (object)['id' => 1, 'deleted' => 0, 'status' => 'suspended', 'registration_valid_from' => null, 'registration_valid_to' => null]; }
        // Stop before rendering unrelated vendor table UI; inspect the actual data passed to persistence.
        public function ci_save(&$data, $id) { $this->attemptedSave = $data; return false; }
    };
    $reflection->getProperty('Vendors_model')->setValue($controller, $model);
    $controller->request = new class { public function getPost($key) { return '1'; } };
    $controller->login_user = (object)['id' => 2];
    ob_start();
    $controller->unblock();
    ob_end_clean();
    if (($model->attemptedSave['status'] ?? '') !== 'approved'
        || array_key_exists('registration_valid_from', $model->attemptedSave)
        || array_key_exists('registration_valid_to', $model->attemptedSave)) {
        throw new RuntimeException('Unblocking must restore authentic prior approval without creating registration validity.');
    }
    $checks++;
    echo 'Vendor unblock payment boundary: ' . $checks . " checks passed.\n";
}
