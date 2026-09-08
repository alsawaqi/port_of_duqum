<?php

namespace App\Controllers {
    class Security_Controller {
        public $login_user;
        public $response;
    }
}

namespace {
    require_once __DIR__ . '/../app/Controllers/Gate_pass_security_inbox.php';
    function app_lang($key) { return $key; }
    function get_uri($path) { return $path; }
    function esc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function modal_anchor($url, $text, $options = []) { return '<a href="' . $url . '">' . $text . '</a>'; }
    function js_anchor($text, $options = []) { return '<a href="' . ($options['data-action-url'] ?? '') . '">' . $text . '</a>'; }
    function gate_pass_vehicle_plate_display($row) { return 'TEST-PLATE'; }
    function gate_pass_vehicle_mulkiyah_path_value($row) { return ''; }

    $class = new ReflectionClass(\App\Controllers\Gate_pass_security_inbox::class);
    $controller = $class->newInstanceWithoutConstructor();
    $controller->login_user = (object)['id' => 101, 'is_admin' => 0];
    $controller->response = new class {
        public function setJSON($value) { return $value; }
    };
    $requestModel = new class {
        public object $request;
        public function get_details($options) {
            return new class($this->request) {
                public function __construct(private object $row) {}
                public function getRow() { return $this->row; }
            };
        }
    };
    $assignments = new class {
        public function get_user_assignments($id) {
            return new class {
                public function getResult() { return [(object)['company_id' => 11]]; }
            };
        }
    };
    $children = new class {
        public function get_details($options) {
            return new class {
                public function getResult() {
                    return [(object)['id' => 50, 'gate_pass_request_id' => 46, 'full_name' => 'TEST VISITOR',
                        'id_type' => 'TEST', 'id_number' => 'TEST-ID', 'nationality' => 'TEST', 'phone' => '',
                        'role' => 'visitor', 'type' => 'private', 'is_blocked' => 0, 'block_reason' => '']];
                }
            };
        }
    };
    foreach (['Gate_pass_requests_model' => $requestModel, 'Gate_pass_security_users_model' => $assignments,
        'Gate_pass_request_visitors_model' => $children, 'Gate_pass_request_vehicles_model' => $children] as $name => $model) {
        $class->getProperty($name)->setValue($controller, $model);
    }

    $checks = 0;
    foreach ([
        ['issued', 'rop_approved', 11, 0, true, false],
        ['issued', 'issued', 11, 0, true, false],
        ['security', 'commercial_approved', 11, 0, true, true],
        ['issued', 'rop_approved', 14, 0, false, false],
        ['security', 'commercial_approved', 14, 0, false, false],
        ['issued', 'cancelled', 11, 0, false, false],
        ['security', 'returned', 11, 0, false, false],
        ['commercial', 'department_approved', 11, 0, false, false],
        ['issued', 'rop_approved', 11, 1, false, false],
    ] as [$stage, $status, $company, $deleted, $canView, $canEdit]) {
        $requestModel->request = (object)['id' => 46, 'stage' => $stage, 'status' => $status,
            'company_id' => $company, 'deleted' => $deleted];
        foreach (['visitors_list_data', 'vehicles_list_data'] as $method) {
            $data = $controller->$method(46)['data'];
            if (count($data) !== ($canView ? 1 : 0)) {
                throw new RuntimeException("Incorrect $method visibility: $stage/$status/company $company/deleted $deleted");
            }
            $checks++;
            if ($canView) {
                $actions = end($data[0]);
                // Blocking remains available at the gate, independently of request editing.
                $hasEdit = str_contains($actions, '/visitor_modal_form') || str_contains($actions, '/vehicle_modal_form');
                $hasDelete = str_contains($actions, '/delete_');
                if ($hasEdit !== $canEdit || $hasDelete !== $canEdit) {
                    throw new RuntimeException("Incorrect $method editing controls: $stage/$status");
                }
                $checks++;
            }
        }
    }
    echo "Gate security scan display: $checks checks passed.\n";
}
