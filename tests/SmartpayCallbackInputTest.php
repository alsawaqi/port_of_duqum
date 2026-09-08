<?php

// Exercise the real HTTP controller with isolated request/response and manager
// doubles. No app boot, database, credentials or bank calls are involved.
namespace CodeIgniter {
    class Controller { public $request; public $response; }
}
namespace App\Libraries\Payments {
    class Eservice_payment_manager {
        public static array $received = [];
        public function processSmartpayReturn(string $encoded, string $orderId): array {
            self::$received[] = [$encoded, $orderId];
            return ['success'=>false, 'status_code'=>400, 'message'=>'Synthetic encrypted response received.'];
        }
    }
}
namespace {
    require_once __DIR__ . '/../app/Controllers/Eservice_payment.php';
    function view($path, $data) { return $data['message'] ?? ''; }
    final class CallbackInputRequest {
        public function __construct(private array $post, private string $method = 'POST', private ?string $raw = null,
            private string $contentType = 'application/x-www-form-urlencoded') {}
        public function getMethod() { return $this->method; }
        public function getPost($key) { return $this->post[$key] ?? null; }
        public function getHeaderLine($name) { return $this->contentType; }
        public function getBody() { return $this->raw ?? http_build_query($this->post); }
        public function getGet($key) { throw new \RuntimeException('Query parameters cannot authorize a bank return.'); }
    }
    final class CallbackInputResponse {
        public int $status = 200;
        public array $headers = [];
        public string $body = '';
        public function setStatusCode($value) { $this->status = $value; return $this; }
        public function setHeader($name, $value) { $this->headers[$name] = $value; return $this; }
        public function setBody($value) { $this->body = $value; return $this; }
    }
    $checks = 0;
    $assert = static function ($condition, $message) use (&$checks): void {
        if (!$condition) { throw new \RuntimeException($message); }
        $checks++;
    };
    $run = static function (array $post, string $method = 'POST', ?string $raw = null, string $type = 'application/x-www-form-urlencoded'): array {
        \App\Libraries\Payments\Eservice_payment_manager::$received = [];
        $controller = new \App\Controllers\Eservice_payment();
        $controller->request = new CallbackInputRequest($post, $method, $raw, $type);
        $controller->response = new CallbackInputResponse();
        $controller->return_from_bank();
        return [$controller->response, \App\Libraries\Payments\Eservice_payment_manager::$received];
    };
    $cipher = str_repeat('a', 66);
    foreach ([['encResp'=>$cipher,'orderNo'=>'POD-test'], ['encResponse'=>$cipher,'order_id'=>'POD-test'], ['enc_response'=>$cipher,'orderId'=>'POD-test'],
        ['enc_response'=>$cipher,'order_id'=>'POD-test'], ['encResponse'=>$cipher,'orderId'=>'POD-test'],
        ['encResponse'=>$cipher,'enc_response'=>$cipher,'order_id'=>'POD-test','orderId'=>'POD-test']] as $body) {
        [$response,$received] = $run($body);
        $assert($received === [[$cipher,'POD-test']], 'Supported aliases reach authenticated processing exactly once and unchanged.');
    }
    foreach ([['encResp'=>$cipher,'encResponse'=>str_repeat('b',66)], ['orderNo'=>'POD-a','order_id'=>'POD-b','encResp'=>$cipher],
        ['encResp'=>[$cipher]], ['encResp'=>str_repeat('a',131073)],
        ['encResponse'=>$cipher,'enc_response'=>str_repeat('b',66)], ['order_id'=>'POD-a','orderId'=>'POD-b','enc_response'=>$cipher],
        ['encResponse'=>[$cipher]], ['enc_response'=>123], ['orderId'=>['POD-test'],'enc_response'=>$cipher],
        ['enc_response'=>str_repeat('a',131073)], ['orderId'=>str_repeat('p',129),'enc_response'=>$cipher],
        ['encResponse'=>'','enc_response'=>$cipher]] as $body) {
        [$response,$received] = $run($body);
        $assert($response->status === 400 && !$received, 'Conflicting, array, non-string and oversized fields fail before payment processing.');
    }
    foreach (['encResp', 'encResponse', 'enc_response', 'orderNo', 'order_id', 'orderId'] as $key) {
        [$response,$received] = $run(['enc_response'=>$cipher,'orderId'=>'POD-test'], 'POST', $key.'=first&'.$key.'=second');
        $assert($response->status === 400 && !$received, 'A repeated conflicting raw form field cannot be silently overwritten: '.$key);
    }
    [$response,$received] = $run(['enc_response'=>$cipher,'orderId'=>'POD-test'], 'POST', 'enc_response='.$cipher.'&enc_response='.$cipher.'&orderId=POD-test');
    $assert($received === [[$cipher,'POD-test']], 'Identical repeated fields do not create ambiguous authority.');
    [$response,$received] = $run(['enc_response'=>$cipher], 'POST', null, 'multipart/form-data; boundary=synthetic');
    $assert($received === [[$cipher,'']], 'Multipart form fields accept the alias and optional outer order hint.');
    [$response,$received] = $run(['enc_response'=>$cipher,'orderId'=>'POD-test'], 'GET');
    $assert($response->status === 405 && ($response->headers['Allow'] ?? '') === 'POST' && !$received, 'GET returns cannot enter payment processing.');
    [$response,$received] = $run([], 'POST', '');
    $assert($received === [['','']], 'Missing encrypted data remains subject to the gateway authentication rejection and audit.');
    [$response,$received] = $run(['enc_response'=>$cipher], 'POST', str_repeat('x',263169));
    $assert($response->status === 400 && !$received, 'Oversized URL-encoded bodies are rejected.');
    echo 'SmartPay callback input: '.$checks." checks passed.\n";
}
