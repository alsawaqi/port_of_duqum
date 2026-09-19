<?php

namespace App\Controllers {
    class Security_Controller {
        public $request;
        public $response;
        public $login_user;
        public $Users_model;
    }
    function app_lang($key) { return $key; }
    function get_language_list() { return ['english' => 'English', 'arabic' => 'Arabic']; }
}

namespace {
    require dirname(__DIR__) . '/app/Controllers/Portal_account.php';
    $checks = 0;
    $check = static function ($value, $message) use (&$checks) {
        $checks++;
        if (!$value) { throw new RuntimeException($message); }
    };
    $call = static function ($method, $post, $user) {
        $controller = (new ReflectionClass(App\Controllers\Portal_account::class))->newInstanceWithoutConstructor();
        $controller->login_user = (object) $user;
        $controller->request = new class($method, $post) {
            public function __construct(private $method, private $post) {}
            public function getMethod() { return $this->method; }
            public function getPost($key) { return $this->post[$key] ?? null; }
        };
        $controller->response = new class {
            public $code = 200;
            public $body;
            public function setStatusCode($code) { $this->code = $code; return $this; }
            public function setHeader($key, $value) { return $this; }
            public function setJSON($body) { $this->body = $body; return $this; }
        };
        $controller->Users_model = new class {
            public $writes = [];
            public function ci_save($data, $id) { $this->writes[] = [$id, $data]; return $id; }
        };
        $response = $controller->save_language();
        return [$response, $controller->Users_model->writes];
    };
    foreach (['vendor', 'gate_pass', 'ptw_applicant'] as $portal) {
        foreach (['arabic', 'English'] as $language) {
            [$response, $writes] = $call('POST', ['language' => $language, 'user_id' => 999, 'is_admin' => 1],
                ['id' => 17, 'is_' . $portal . '_only_identity' => true]);
            $check($response->code === 200 && $response->body['success'], "$portal language saves");
            $check($writes === [[17, ['language' => strtolower($language)]]], 'Only session owner and language are modified');
        }
    }
    foreach ([null, '', '../arabic', 'unsupported', ['arabic']] as $invalid) {
        [$response, $writes] = $call('POST', ['language' => $invalid], ['id' => 17, 'is_gate_pass_only_identity' => true]);
        $check($response->code === 422 && !$writes, 'Unsupported or malformed language must not write');
    }
    foreach ([['GET', ['id' => 17, 'is_gate_pass_only_identity' => true], 405],
              ['POST', ['id' => 17], 403], ['POST', ['id' => 0, 'is_gate_pass_only_identity' => true], 403]] as [$method, $user, $expected]) {
        [$response, $writes] = $call($method, ['language' => 'arabic'], $user);
        $check($response->code === $expected && !$writes, 'Invalid method/identity must not write');
    }
    $defaults = require dirname(__DIR__) . '/app/Language/arabic/default_lang.php';
    $check($defaults['language_locale'] === 'ar' && $defaults['text_direction'] === 'rtl', 'Arabic locale and RTL defaults exist');
    $check(is_file(dirname(__DIR__) . '/assets/js/summernote/lang/summernote-' . $defaults['language_locale_long'] . '.js'), 'Arabic editor locale is bundled');
    $check(isset($defaults['save']) && $defaults['save'] !== '', 'Untranslated keys have a readable fallback');
    echo "OK: $checks portal language preference checks." . PHP_EOL;
}
