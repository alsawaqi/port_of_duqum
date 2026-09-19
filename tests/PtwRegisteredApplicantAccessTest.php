<?php

namespace App\Controllers {
    class Security_Controller {
        public $login_user;
    }
    function app_redirect($uri) { throw new \RuntimeException('redirect:' . $uri); }
    function db_connect() { return $GLOBALS['ptwTestDb']; }
}

namespace {
    require dirname(__DIR__) . '/app/Controllers/Ptw_portal.php';
    $checks = 0;
    $check = static function ($value, $message) use (&$checks) {
        $checks++;
        if (!$value) { throw new RuntimeException($message); }
    };
    $class = new ReflectionClass(App\Controllers\Ptw_portal::class);
    $controller = $class->newInstanceWithoutConstructor();
    $call = static fn($method, ...$args) => $class->getMethod($method)->invoke($controller, ...$args);
    $GLOBALS['ptwTestDb'] = new class {
        public $reviewerCompany = null;
        public function prefixTable($table) { return 'pod_' . $table; }
        public function query($sql, $params) {
            $allowed = $this->reviewerCompany === $params[1];
            return new class($allowed) {
                public function __construct(private $allowed) {}
                public function getRow() { return $this->allowed ? (object)['id' => 1] : null; }
            };
        }
    };
    foreach (['vendor', 'gate_pass', 'client', 'staff'] as $identity) {
        $controller->login_user = (object)['id' => 17, 'is_admin' => false, 'user_type' => $identity];
        $check($call('_require_ptw_access') === null, "$identity can enter without a PTW assignment");
        $own = (object)['applicant_user_id' => 17, 'company_id' => 4, 'status' => 'draft', 'stage' => 'draft'];
        $check($call('_can_access_application', $own), "$identity can read own application");
        $check($call('_can_edit_application', $own), "$identity can edit own draft");
        foreach (['hsse', 'hmo', 'terminal'] as $stage) {
            $own->status = 'revise'; $own->stage = $stage;
            $check($call('_can_edit_application', $own), "$identity can revise at $stage");
        }
        foreach ([['submitted', 'hsse'], ['approved', 'completed'], ['rejected', 'hsse']] as [$status, $stage]) {
            $own->status = $status; $own->stage = $stage;
            $check(!$call('_can_edit_application', $own), "$identity cannot edit $status application");
        }
        $other = (object)['applicant_user_id' => 18, 'company_id' => 4, 'status' => 'draft', 'stage' => 'draft'];
        $check(!$call('_can_access_application', $other), "$identity cannot read another applicant's record");
        $check(!$call('_can_edit_application', $other), "$identity cannot edit another applicant's record");
    }
    $controller->login_user = (object)['id' => 17, 'is_admin' => false];
    $GLOBALS['ptwTestDb']->reviewerCompany = 4;
    $check($call('_can_access_application', $other), 'Reviewer can read assigned-company record');
    $other->company_id = 5;
    $check(!$call('_can_access_application', $other), 'Reviewer cannot read another company');
    $check(!$call('_can_edit_application', $other), 'Reviewer does not gain applicant edit authority');
    $controller->login_user = (object)['id' => 0, 'is_admin' => false];
    try { $call('_require_ptw_access'); $check(false, 'Anonymous access must fail'); }
    catch (RuntimeException $e) { $check($e->getMessage() === 'redirect:forbidden', 'Anonymous access denied'); }

    $controller->login_user = (object)['id' => 17, 'is_admin' => false];
    $listModel = new class {
        public $options;
        public function get_details($options) { $this->options = $options; return $this; }
        public function getResult() { return []; }
    };
    $class->getProperty('Ptw_applications_model')->setValue($controller, $listModel);
    ob_start(); $controller->applications_list_data(); $output = ob_get_clean();
    $check($listModel->options === ['applicant_user_id' => 17], 'My Applications always filters by session owner');
    $check(json_decode($output, true) === ['data' => []], 'Empty personal application list is valid');
    echo "OK: $checks registered PTW applicant access checks." . PHP_EOL;
}
