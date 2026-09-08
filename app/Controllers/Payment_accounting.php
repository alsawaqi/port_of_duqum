<?php

namespace App\Controllers;

use App\Libraries\Payments\Eservice_payment_manager;
use App\Libraries\Payments\Payment_accounting_policy;
use App\Libraries\Payments\Payment_accounting_presenter;
use App\Models\Payment_accounting_model;

class Payment_accounting extends Security_Controller
{
    private Payment_accounting_model $ledger;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->ledger = new Payment_accounting_model();
        // CI initializes the controller response after construction.
        service('response')->setHeader('Cache-Control', 'no-store, private');
    }

    public function index(string $module = 'vendor')
    {
        $this->access_only_payment_accounting($module);
        return $this->template->rander('payment_accounting/index', [
            'module' => $module,
            'ready' => $this->ledger->ready(),
            'can_export' => $this->can_payment_accounting($module, 'export'),
            'statuses' => Payment_accounting_policy::STATUSES,
        ]);
    }

    public function list_data(string $module = 'vendor')
    {
        $this->access_only_payment_accounting($module);
        if (!$this->ledger->ready()) {
            return $this->response->setStatusCode(503)->setJSON(['data' => [], 'message' => app_lang('payment_accounting_not_ready')]);
        }
        try {
            $rows = $this->ledger->rows($this->login_user, $module, $this->filters(), 'view', 2001);
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['data' => [], 'message' => $exception->getMessage()]);
        }
        $truncated = count($rows) > 2000;
        $data = [];
        foreach (array_slice($rows, 0, 2000) as $row) {
            $data[] = [
                esc($row->initiated_at . ' UTC'),
                anchor(get_uri('payment_accounting/detail/' . $module . '/' . (int) $row->id), esc($row->public_id)),
                esc(app_lang('payment_accounting_' . $row->subject_type)),
                esc($row->subject_reference ?: ('#' . $row->subject_id)),
                esc(trim(($row->vendor_name ?? '') . ' / ' . ($row->cr_number ?? ''), ' /')),
                esc(trim(($row->payer_name ?? '') . ' / ' . ($row->payer_email ?? ''), ' /')),
                esc($row->company_name ?? ''),
                esc($row->currency . ' ' . $row->amount),
                esc(Payment_accounting_presenter::status($row)),
                esc($row->bank_reference ?: ($row->provider_payment_id ?? '')),
                esc(empty($row->paid_at) ? '' : $row->paid_at . ' UTC'),
            ];
        }
        return $this->response->setJSON(['data' => $data, 'truncated' => $truncated]);
    }

    public function detail(string $module = 'vendor', $id = 0)
    {
        $this->access_only_payment_accounting($module);
        if (!$this->ledger->ready()) {
            return $this->response->setStatusCode(503)->setBody(esc(app_lang('payment_accounting_not_ready')));
        }
        $payment = $this->ledger->payment($this->login_user, $module, (int) $id);
        if (!$payment) {
            show_404();
            return;
        }
        $canResponses = $this->can_payment_accounting($module, 'responses');
        $events = $canResponses ? $this->ledger->events($this->login_user, $module, (int) $payment->id) : [];
        return $this->template->rander('payment_accounting/detail', [
            'module' => $module,
            'payment' => $payment,
            'can_responses' => $canResponses,
            'can_reconcile' => $this->can_payment_accounting($module, 'reconcile') && $payment->provider === 'bank_muscat' && !empty($payment->handed_off_at),
            'presentation' => Payment_accounting_presenter::detail($payment, $canResponses, $events),
        ]);
    }

    public function export_csv(string $module = 'vendor')
    {
        helper('csv_security');
        $this->access_only_payment_accounting($module, 'export');
        if (!$this->ledger->ready()) {
            return $this->response->setStatusCode(503)->setBody(esc(app_lang('payment_accounting_not_ready')));
        }
        try {
            $rows = $this->ledger->rows($this->login_user, $module, $this->filters(), 'export', 10001);
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setBody(esc($exception->getMessage()));
        }
        // Never silently truncate an accounting export.
        if (count($rows) > 10000) {
            return $this->response->setStatusCode(422)->setBody(esc(app_lang('payment_accounting_export_limit')));
        }
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Transaction', 'Payment purpose', 'Reason for payment', 'Related record ID', 'Reference', 'Vendor ID', 'Vendor', 'CR',
            'Payer ID', 'Payer', 'Payer email', 'Company', 'Currency', 'Amount', 'Status', 'Settlement status', 'Accounting review needed', 'Provider',
            'Merchant ID', 'Gateway order', 'Gateway payment', 'Bank reference', 'Initiated at (UTC)', 'Paid at (UTC)', 'Verified at (UTC)']);
        foreach ($rows as $row) {
            fputcsv($stream, csv_safe_row([$row->public_id, app_lang('payment_accounting_' . $row->subject_type),
                \App\Libraries\Payments\Bank_muscat_gateway::redactCardText((string)($row->description ?? '')), $row->subject_id,
                $row->subject_reference, $row->vendor_id, $row->vendor_name, $row->cr_number,
                $row->user_id, $row->payer_name, $row->payer_email, $row->company_name, $row->currency,
                $row->amount, Payment_accounting_presenter::status($row), app_lang('payment_accounting_settlement_' . $row->settlement_status),
                (!empty($row->has_verification_issues) || $row->status === 'verification_required' || $row->settlement_status === 'review_required') ? 'Yes' : 'No',
                $row->provider === 'bank_muscat' ? 'Bank Muscat SmartPay' : $row->provider, $row->gateway_merchant_id, $row->provider_checkout_id,
                $row->provider_payment_id, $row->bank_reference, $row->initiated_at, $row->paid_at,
                $row->verified_at]));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $module . '_payments_' . date('Y-m-d') . '.csv"')
            ->setBody($csv);
    }

    public function recheck(string $module = 'vendor', $id = 0)
    {
        $this->access_only_payment_accounting($module, 'reconcile');
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'POST')->setJSON(['success' => false]);
        }
        if (!$this->ledger->ready()) {
            return $this->response->setStatusCode(503)->setJSON(['success' => false, 'message' => app_lang('payment_accounting_not_ready')]);
        }
        $payment = $this->ledger->payment($this->login_user, $module, (int) $id, 'reconcile');
        if (!$payment || $payment->provider !== 'bank_muscat') {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => app_lang('record_not_found')]);
        }
        $key = 'payment_recheck_' . (int) $payment->id;
        $session = session();
        if ((int) $session->get($key) > time() - 30) {
            return $this->response->setStatusCode(429)->setJSON(['success' => false, 'message' => app_lang('payment_accounting_recheck_wait')]);
        }
        $session->set($key, time());
        try {
            $result = (new Eservice_payment_manager())->recheckSmartpayPayment((int) $payment->id);
            // A saved, unresolved check still changes the ledger. Refresh it while
            // preserving the manager's payment outcome and avoiding refresh on errors.
            $result['refresh'] = in_array((int)($result['status_code'] ?? 0), [200, 202], true);
            return $this->response->setJSON($result);
        } catch (\Throwable $exception) {
            log_message('error', 'Accounting bank recheck failed for payment {id}: {type}', ['id' => (int) $payment->id, 'type' => get_class($exception)]);
            return $this->response->setStatusCode(502)->setJSON(['success' => false, 'message' => app_lang('payment_accounting_recheck_error')]);
        }
    }

    private function filters(): array
    {
        return Payment_accounting_policy::filters((array) $this->request->getGetPost());
    }

}
