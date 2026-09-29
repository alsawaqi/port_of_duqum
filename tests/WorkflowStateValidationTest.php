<?php

namespace App\Controllers {
    class Security_Controller {
        public $login_user;
        public $request;
        public $response;
        public function validate_submitted_data($rules): void {}
    }
}

namespace {
    require __DIR__ . '/../app/Controllers/Gate_pass_security_inbox.php';
    require __DIR__ . '/../app/Controllers/Gate_pass_rop_inbox.php';
    require __DIR__ . '/../app/Controllers/Ptw_portal.php';
    function app_lang($key) { return $key; }
    // A refused review must not reach a transaction, payment, or pass issuance.
    function db_connect() { throw new RuntimeException('Unexpected database access on a refused review'); }
    $checks = 0;
    $check = static function ($ok, $message) use (&$checks) {
        $checks++;
        if (!$ok) { throw new RuntimeException($message); }
    };
    $request = new class {
        public array $post = [];
        public function getPost($key) { return $this->post[$key] ?? null; }
    };
    $securityClass = new ReflectionClass(App\Controllers\Gate_pass_security_inbox::class);
    $security = $securityClass->newInstanceWithoutConstructor();
    $security->login_user = (object)['id'=>1,'is_admin'=>1];
    $canReview = $securityClass->getMethod('_can_review_request');
    foreach (['commercial_approved'=>true,'rejected'=>false,'cancelled'=>false,'returned'=>false,'draft'=>false,'submitted'=>false,'security_approved'=>false] as $status=>$expected) {
        $row = (object)['id'=>1,'stage'=>'security','status'=>$status,'company_id'=>1];
        $check($canReview->invoke($security,$row)===$expected,"Security review guard: $status");
    }
    $ropClass = new ReflectionClass(App\Controllers\Gate_pass_rop_inbox::class);
    $rop = $ropClass->newInstanceWithoutConstructor();
    $rop->request = $request;
    $rop->login_user = (object)['id'=>1,'is_admin'=>1];
    $model = new class {
        public object $row;
        public function get_one($id) { return $this->row; }
    };
    $ropClass->getProperty('Gate_pass_requests_model')->setValue($rop,$model);
    foreach (['rejected','cancelled','returned','draft','commercial_approved','rop_approved'] as $status) {
        $model->row = (object)['id'=>1,'stage'=>'rop','status'=>$status,'deleted'=>0];
        $request->post = ['gate_pass_request_id'=>1,'decision'=>'approved'];
        ob_start();
        try { $rop->save_approval(); $result=json_decode(ob_get_contents(),true); }
        finally { ob_end_clean(); }
        $check(($result['success'] ?? null)===false,"ROP refuses $status before touching storage");
    }
    $ptwClass = new ReflectionClass(App\Controllers\Ptw_portal::class);
    $ptw = $ptwClass->newInstanceWithoutConstructor();
    $ptw->request = $request;
    $normalize = $ptwClass->getMethod('_normalize_datetime');
    foreach ([''=>'','2026-10-01T09:30'=>'2026-10-01 09:30:00','2028-02-29 23:59:59'=>'2028-02-29 23:59:59'] as $raw=>$expected) {
        $check($normalize->invoke($ptw,$raw)===($expected ?: null),'Valid PTW timestamp: '.$raw);
    }
    foreach (['not-a-date','2026-02-29T12:00','2026-04-31T09:00','0000-00-00 00:00:00','2026-10-01T24:00','2026-10-01T10:30:80','01/10/2026 09:00'] as $invalid) {
        $check($normalize->invoke($ptw,$invalid)===null,'Invalid PTW timestamp refused: '.$invalid);
        foreach (['work_from','work_to'] as $field) {
            $request->post = [$field=>$invalid];
            [$errors,$fieldErrors] = $ptwClass->getMethod('_validate_ptw_submission')->invoke($ptw,null,[],'draft',(object)['id'=>1]);
            $check(isset($fieldErrors[$field]) && count($errors)>0,'Invalid draft date gets field feedback: '.$field);
        }
    }
    echo "Workflow state/date validation: $checks checks passed.\n";
}
