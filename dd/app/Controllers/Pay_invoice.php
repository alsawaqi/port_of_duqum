<?php

namespace App\Controllers;

use App\Libraries\Paypal;
use App\Libraries\Paytm;
use App\Libraries\Stripe;

class Pay_invoice extends App_Controller {

    function __construct() {
        parent::__construct();
    }

    function index($verification_code = "") {
        if (!get_setting("client_can_pay_invoice_without_login")) {
            app_redirect("forbidden");
        }

        if (!$verification_code) {
            show_404();
        }

        // Accept existing 10-character links during migration; newly issued
        // invoice capabilities are 32 alphanumeric characters.
        if (preg_match('/^(?:[A-Za-z0-9]{10}|[A-Za-z0-9]{32})$/D', $verification_code) !== 1) {
            show_404();
        }

        $options = array("code" => $verification_code, "type" => "invoice_payment");
        $verification_info = $this->Verification_model->get_details($options)->getRow();
        if (!($verification_info && $verification_info->id)) {
            show_404();
        }

        $invoice_data = safe_unserialize((string)$verification_info->params);
        if (!is_array($invoice_data)) {
            show_404();
        }

        $invoice_id = get_array_value($invoice_data, "invoice_id");
        $client_id = get_array_value($invoice_data, "client_id");
        $contact_id = get_array_value($invoice_data, "contact_id");

        $this->_log("invoice_id:$invoice_id, client_id:$client_id, contact_id:$contact_id");

        if ($invoice_id && is_numeric($invoice_id) && $client_id && is_numeric($client_id) && $contact_id && is_numeric($contact_id)) {
            $view_data = get_invoice_making_data($invoice_id);
            $view_data['payment_methods'] = $this->Payment_methods_model->get_available_online_payment_methods();

            //check access of this invoice
            $this->_check_access_of_invoice($view_data);

            $view_data['invoice_preview'] = prepare_invoice_pdf($view_data, "html");

            $view_data['invoice_id'] = $invoice_id;

            $paytm = new Paytm();
            $view_data['paytm_url'] = $paytm->get_paytm_url();

            $view_data['contact_id'] = $contact_id;
            $view_data['verification_code'] = clean_data($verification_code);

            return $this->template->view("invoices/public_invoice_preview", $view_data);
        } else {
            show_404();
        }
    }

    private function _check_access_of_invoice($view_data) {
        if (count($view_data) && !get_array_value($view_data, "client_info")->disable_online_payment) {
            return true;
        } else {
            app_redirect("forbidden");
        }
    }

    function get_stripe_checkout_session() {
        if (!get_setting("client_can_pay_invoice_without_login")) {
            app_redirect("forbidden");
        }

        $stripe = new Stripe();

        try {
            $session = $stripe->get_stripe_checkout_session($this->request->getPost("input_data"));
            if ($session->id) {
                echo json_encode(array("success" => true, "checkout_url" => $session->url));
            } else {
                echo json_encode(array('success' => false, 'message' => app_lang('error_occurred')));
            }
        } catch (\Throwable $ex) {
            log_message('warning', 'PUBLIC STRIPE INVOICE CHECKOUT REJECTED: {class}', ['class' => get_class($ex)]);
            echo json_encode(array("success" => false, "message" => app_lang('error_occurred')));
        }
    }

    function get_paypal_checkout_url() {
        if (!get_setting("client_can_pay_invoice_without_login")) {
            app_redirect("forbidden");
        }

        $paypal = new Paypal();

        try {
            $checkout_url = $paypal->get_paypal_checkout_url($this->request->getPost("input_data"));
            if ($checkout_url) {
                echo json_encode(array("success" => true, "checkout_url" => $checkout_url));
            } else {
                echo json_encode(array('success' => false, 'message' => app_lang('error_occurred')));
            }
        } catch (\Throwable $ex) {
            log_message('warning', 'PUBLIC PAYPAL INVOICE CHECKOUT REJECTED: {class}', ['class' => get_class($ex)]);
            echo json_encode(array("success" => false, "message" => app_lang('error_occurred')));
        }
    }

    private function _log($text = "") {
        if ($text && get_setting("enable_public_pay_invoice_logging")) {
            // Use the protected application logger; never create a payment log
            // beside index.php where a web server could expose it.
            log_message('info', 'PUBLIC INVOICE ACCESS: {context}', [
                'context' => mb_substr((string)$text, 0, 250),
            ]);
        }
    }

    function get_paytm_checksum_hash() {
        if (!get_setting("client_can_pay_invoice_without_login")) {
            app_redirect("forbidden");
        }
        try {
            $requestData = $this->request->getPost('payment_request');
            if (!is_array($requestData)) {
                throw new \InvalidArgumentException('Invalid invoice payment request.');
            }
            $paymentData = (new Paytm())->get_paytm_checksum_hash($requestData);
            echo json_encode([
                'success' => true,
                'checksum_hash' => $paymentData['checksum_hash'],
                'payment_verification_code' => $paymentData['payment_verification_code'],
                'input_data' => $paymentData['input_data'],
            ]);
        } catch (\Throwable $exception) {
            log_message('warning', 'PUBLIC PAYTM CHECKOUT CREATION REJECTED: {class}', [
                'class' => get_class($exception),
            ]);
            echo json_encode(['success' => false, 'message' => app_lang('error_occurred')]);
        }
    }
}

/* End of file Pay_invoice.php */
/* Location: ./app/controllers/Pay_invoice.php */
