<?php

namespace App\Controllers;

use App\Libraries\Paypal;

// Public provider redirect. Authentication is the opaque attempt id plus an
// exact server-to-server verification of the PayPal payment.
class Paypal_redirect extends App_Controller
{
    function index($payment_verification_code = '')
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $payment_verification_code) !== 1) {
            show_404();
        }

        $query = $this->request->getGet();
        if (!is_array($query) || count($query) < 1 || count($query) > 12) {
            show_404();
        }

        try {
            $result = (new Paypal())->settle_invoice_attempt($payment_verification_code, $query);
        } catch (\Throwable $exception) {
            log_message('warning', 'PAYPAL INVOICE REDIRECT REJECTED: {class}', [
                'class' => get_class($exception),
            ]);
            show_404();
        }

        $invoiceId = (int)$result['invoice_id'];
        $verificationCode = (string)$result['verification_code'];
        if (!$result['success']) {
            $this->session->setFlashdata('error_message', app_lang('error_occurred'));
        } else {
            $this->session->setFlashdata('success_message', app_lang('payment_success_message'));
        }

        $redirectTo = $verificationCode !== ''
            ? 'pay_invoice/index/' . $verificationCode
            : 'invoices/preview/' . $invoiceId;
        app_redirect($redirectTo);
    }
}
