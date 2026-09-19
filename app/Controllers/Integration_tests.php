<?php

namespace App\Controllers;

use App\Libraries\Integration_test_service;
use App\Libraries\Payments\Eservice_payment_manager;
use App\Libraries\Payments\Payment_accounting_presenter;
use App\Libraries\Sms\SmsConnectionSettings;

final class Integration_tests extends Security_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->access_only_admin();
        Integration_test_service::assertAdmin($this->login_user);
    }

    public function index() { return $this->page(); }

    public function payment(string $publicId = '')
    {
        $payment = $this->testPayment($publicId);
        if (!$payment) { return $this->response->setStatusCode(404)->setBody('Test payment not found.'); }
        return $this->page($payment);
    }

    private function page(?object $payment = null)
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $db = db_connect();
        $sms = SmsConnectionSettings::load();
        $service = new Integration_test_service($db, $sms);
        $rows = [];
        if ($db->tableExists('eservice_payments')) {
            $rows = $db->table('eservice_payments')->where(['subject_type' => Eservice_payment_manager::INTEGRATION_TEST, 'deleted' => 0])
                ->orderBy('id', 'DESC')->limit(25)->get()->getResult();
        }
        $smsRows = $db->tableExists('sms_outbox') ? $db->table('sms_outbox')->where(['module' => 'system', 'reason' => 'integration_test'])
            ->orderBy('id', 'DESC')->limit(25)->get()->getResultArray() : [];
        $actorIds = array_unique(array_merge(array_map(static fn($r) => (int)$r->user_id, $rows), array_column($smsRows, 'source_id')));
        $actors = [];
        if ($actorIds) {
            foreach ($db->table('users')->select('id, first_name, last_name')->whereIn('id', $actorIds)->get()->getResult() as $actor) {
                $actors[$actor->id] = trim($actor->first_name . ' ' . $actor->last_name);
            }
        }
        return $this->template->rander('integration_tests/index', [
            'sms_ready' => $service->smsReady(), 'sms_enabled' => $sms->enabled, 'sms_header' => $sms->header,
            'otp_enabled' => config('AuthSecurity')->mfaEnabled, 'bank' => config('EservicesPayments'),
            'bank_ready' => (new \App\Models\Payment_accounting_model($db))->ready(),
            'request_token' => bin2hex(random_bytes(16)), 'payment_rows' => $rows, 'sms_rows' => $smsRows,
            'actors' => $actors, 'selected_payment' => $payment,
            'bank_return' => $payment ? Payment_accounting_presenter::bankFields($payment->response_json ?? '') : [],
            'bank_check' => $payment ? Payment_accounting_presenter::bankFields($payment->status_response_json ?? '') : [],
        ]);
    }

    public function send_sms()
    {
        return $this->action('sms', function () {
            $result = (new Integration_test_service())->sendSms($this->login_user, (array)$this->request->getPost());
            $result['next_token'] = bin2hex(random_bytes(16));
            return $result;
        });
    }

    public function check_bank_connection()
    {
        return $this->action('bank', fn() => (new Integration_test_service())->checkBank($this->login_user, config('EservicesPayments')));
    }

    public function start_payment()
    {
        return $this->action('payment', function () {
            $result = (new Integration_test_service())->startPayment($this->login_user, $this->request->getPost('request_token'), config('EservicesPayments'));
            if (!empty($result['success'])) { $result['redirect_url'] = $result['checkout_url']; }
            return $result;
        });
    }

    public function recheck_payment(string $publicId = '')
    {
        return $this->action('recheck', function () use ($publicId) {
            $payment = $this->testPayment($publicId);
            if (!$payment) { return ['success' => false, 'status_code' => 404, 'message' => 'Test payment not found.']; }
            return (new Eservice_payment_manager())->recheckSmartpayPayment((int)$payment->id);
        });
    }

    private function testPayment(string $publicId): ?object
    {
        $payment = (new Eservice_payment_manager())->smartpayPaymentByPublicId($publicId);
        return $payment && $payment->subject_type === Eservice_payment_manager::INTEGRATION_TEST ? $payment : null;
    }

    private function action(string $kind, callable $callback)
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        Integration_test_service::assertAdmin($this->login_user);
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'POST')->setJSON(['success' => false, 'message' => 'Use the test button to submit this request.']);
        }
        $limiter = service('throttler');
        if (!$limiter->check('integration_test_' . $kind . '_' . (int)$this->login_user->id, 1, 15)
            || !$limiter->check('integration_test_hour_' . $kind . '_' . (int)$this->login_user->id, 20, 3600)) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', '15')->setJSON(['success' => false, 'message' => 'Please wait before running another test.']);
        }
        try {
            $result = $callback();
            return $this->response->setStatusCode($result['status_code'] ?? 200)->setJSON($result);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', 'Integration test failed: {class}', ['class' => get_class($e)]);
            return $this->response->setStatusCode(503)->setJSON(['success' => false,
                'message' => 'The test could not be completed or its result saved. Check the test history before trying again.']);
        }
    }
}
