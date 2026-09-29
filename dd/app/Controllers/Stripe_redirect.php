<?php

namespace App\Controllers;

use App\Libraries\Stripe;
use Throwable;

//don't extend this controller from Pre_loader 
//because this will be called by Stripe 
//and login check is not required since we'll validate the data

class Stripe_redirect extends App_Controller {

    protected $Stripe_ipn_model;
    private $stripe;

    function __construct() {
        parent::__construct();
        $this->Stripe_ipn_model = model('App\Models\Stripe_ipn_model');
        $this->stripe = new Stripe();
    }

    function index($payment_verification_code = "") {
        if (preg_match('/^[a-f0-9]{32}$/D', $payment_verification_code) !== 1) {
            show_404();
        }

        try {
            $result = $this->stripe->settle_invoice_attempt($payment_verification_code);
        } catch (Throwable $exception) {
            log_message('warning', 'STRIPE INVOICE REDIRECT REJECTED: {class}', [
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
        if ($verificationCode !== '') {
            $redirect_to = "pay_invoice/index/$verificationCode";
        } else {
            $redirect_to = "invoices/preview/$invoiceId";
        }

        app_redirect($redirect_to);
    }

    function subscription($payment_verification_code = "") {
        if (!$payment_verification_code) {
            show_404();
        }

        $stripe_ipn_info = $this->Stripe_ipn_model->get_one_payment_where($payment_verification_code);
        if (!($stripe_ipn_info && $stripe_ipn_info->subscription_id)) {
            show_404();
        }

        $customer_id = $this->Stripe_ipn_model->get_customer_id($stripe_ipn_info->subscription_id);
        $subscription_info = $this->Subscriptions_model->get_details(array("id" => $stripe_ipn_info->subscription_id))->getRow();

        $stripe = new Stripe();
        $stripe_payment_method_id = $stripe->retrieve_setup_intent($stripe_ipn_info->setup_intent)->payment_method;
        $stripe_product_info = $stripe->retrieve_product($subscription_info->stripe_product_id);
        $subscription_item_info = $this->Subscription_items_model->get_one_where(array("subscription_id" => $subscription_info->id, "deleted" => 0));

        $tax_rates = array();
        if ($subscription_info->stripe_tax_id) {
            array_push($tax_rates, $subscription_info->stripe_tax_id);
        }
        if ($subscription_info->stripe_tax_id2) {
            array_push($tax_rates, $subscription_info->stripe_tax_id2);
        }


        $subscription_data = array();

        //create subscription with this payment method
        $stripe_subscription_data = array(
            "customer" => $customer_id,
            "items" => array(
                array(
                    "price" => $subscription_info->stripe_product_price_id,
                    "quantity" => $subscription_item_info->quantity,
                    "tax_rates" => $tax_rates
                )
            ),
            "default_payment_method" => $stripe_payment_method_id,
            "metadata" => array(
                "subscription_id" => $stripe_ipn_info->subscription_id,
                "contact_user_id" => $stripe_ipn_info->contact_user_id,
                "payment_method_id" => $stripe_ipn_info->payment_method_id,
            ),
            "proration_behavior" => "none"
        );

        $billing_cycle_anchor = $subscription_info->bill_date;
        $today = get_my_local_time("Y-m-d H:i:s");
        if ($billing_cycle_anchor > $today) {
            $stripe_subscription_data["billing_cycle_anchor"] = strtotime($billing_cycle_anchor);
        } else {
            $subscription_data["bill_date"] = $today;
        }



        //prepare the last billed date 
        if ($subscription_info->no_of_cycles) {
            $last_billed_date = $subscription_info->bill_date;
            for ($i = 0; $i < $subscription_info->no_of_cycles; $i++) {
                $last_billed_date = add_period_to_date($last_billed_date, $subscription_info->repeat_every, $subscription_info->repeat_type);
            }

            //add one more day to work on stripe
            $last_billed_date = add_period_to_date($last_billed_date, 1, "days");

            $stripe_subscription_data["cancel_at"] = strtotime($last_billed_date);
        }

        try {
            $stripe_subscription_info = $stripe->create_subscription($stripe_subscription_data);

            //save subscription id on the subscription
            //it'll also take the first payment now
            //grab that with the same webhook
            $subscription_data["stripe_subscription_id"] = $stripe_subscription_info->id;
            $subscription_data["status"] = "active";
            $this->Subscriptions_model->ci_save($subscription_data, $stripe_ipn_info->subscription_id);

            //save the last 4 digits of card to clients table        
            $client_data = array("stripe_card_ending_digit" => $stripe->retrieve_payment_method($stripe_payment_method_id)->card->last4);
            $this->Clients_model->ci_save($client_data, $stripe_ipn_info->client_id);

            //delete the ipn data
            $this->Stripe_ipn_model->delete($stripe_ipn_info->id);

            log_notification("subscription_started", array("subscription_id" => $stripe_ipn_info->subscription_id));

            $this->session->setFlashdata("success_message", app_lang("subscription_success_message"));
            app_redirect("subscriptions/preview/$stripe_ipn_info->subscription_id");
        } catch (\Exception $ex) {
            echo json_encode(array("success" => false, "message" => $ex->getMessage()));
        }
    }
}

/* End of file Stripe_redirect.php */
/* Location: ./app/controllers/Stripe_redirect.php */
