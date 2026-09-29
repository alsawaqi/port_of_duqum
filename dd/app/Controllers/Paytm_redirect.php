<?php

namespace App\Controllers;

use App\Libraries\Paytm;

// Public Paytm callback. The signed payload is verified and exact-matched to a
// server-created attempt before any invoice state can change.
class Paytm_redirect extends App_Controller
{
    function index($payment_verification_code = '')
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $payment_verification_code) !== 1
            || strtoupper((string)$this->request->getMethod()) !== 'POST') {
            show_404();
        }

        $postData = $this->request->getPost();
        if (!is_array($postData) || !isset($postData['CHECKSUMHASH'])) {
            show_404();
        }

        try {
            $result = (new Paytm())->settle_invoice_attempt($payment_verification_code, $postData);
        } catch (\Throwable $exception) {
            log_message('warning', 'PAYTM INVOICE CALLBACK REJECTED: {class}', [
                'class' => get_class($exception),
            ]);
            show_404();
        }

        if (!$result['success']) {
            $this->session->setFlashdata('error_message', app_lang('error_occurred'));
        } else {
            $this->session->setFlashdata('success_message', app_lang('payment_success_message'));
        }

        $invoiceId = (int)$result['invoice_id'];
        $verificationCode = (string)$result['verification_code'];
        $redirectTo = $verificationCode !== ''
            ? 'pay_invoice/index/' . $verificationCode
            : 'invoices/preview/' . $invoiceId;
        app_redirect($redirectTo);
    }
}
