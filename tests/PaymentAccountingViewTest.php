<?php

// Render the actual PHP templates with synthetic data; never boot the app or DB.
require_once __DIR__ . '/../app/Libraries/Payments/Payment_accounting_policy.php';
require_once __DIR__ . '/../app/Libraries/Payments/Payment_gateway_interface.php';
require_once __DIR__ . '/../app/Libraries/Payments/Bank_muscat_gateway.php';
require_once __DIR__ . '/../app/Libraries/Payments/Payment_accounting_presenter.php';
$translations = require __DIR__ . '/../app/Language/english/custom_lang.php';
function app_lang($key) { global $translations; return $translations[$key] ?? $key; }
function esc($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function get_uri($uri) { return 'http://127.0.0.1:8095/' . $uri; }
function echo_uri($uri) { echo esc(get_uri($uri)); }
function anchor($uri, $title, $attrs = []) { return '<a href="' . esc($uri) . '">' . $title . '</a>'; }
function form_open($uri, $attrs = []) { return '<form action="' . esc($uri) . '" id="' . esc($attrs['id'] ?? '') . '" method="post"><input type="hidden" name="csrf_test" value="synthetic">'; }
function form_close() { return '</form>'; }
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); exit(1); }
};
$render = static function (string $view, array $data): string {
    extract($data);
    ob_start();
    require __DIR__ . '/../app/Views/payment_accounting/' . $view . '.php';
    return str_replace('\\/', '/', ob_get_clean());
};
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
foreach (array_keys(App\Libraries\Payments\Payment_accounting_policy::MODULES) as $module) {
    $html = $render('index', ['module' => $module, 'ready' => true, 'can_export' => true,
        'statuses' => App\Libraries\Payments\Payment_accounting_policy::STATUSES]);
    $assert(str_contains($html, 'payment_accounting/list_data/' . $module), 'Module ledger has a correct data endpoint.');
    $assert(str_contains($html, 'payment_accounting/export_csv/' . $module), 'Export follows the selected module.');
    $html = $render('index', ['module' => $module, 'ready' => true, 'can_export' => false,
        'statuses' => App\Libraries\Payments\Payment_accounting_policy::STATUSES]);
    $assert(!str_contains($html, 'id="payment-accounting-export"'), 'A view-only user has no export control.');
}
$html = $render('index', ['module' => 'vendor', 'ready' => false, 'can_export' => true,
    'statuses' => App\Libraries\Payments\Payment_accounting_policy::STATUSES]);
$assert(!str_contains($html, 'id="payment-accounting-table"'), 'Unavailable schema does not start an AJAX table.');
$payment = (object) [
    'id'=>44, 'public_id'=>'ref<script>alert(1)</script>', 'subject_type'=>'vendor_renewal',
    'status'=>'paid', 'settlement_status'=>'review_required', 'has_verification_issues'=>1,
    'currency'=>'OMR', 'amount'=>'10.123', 'subject_reference'=>'CR44', 'subject_id'=>44,
    'vendor_id'=>44, 'vendor_name'=>'<img src=x onerror=alert(1)>', 'cr_number'=>'CR44',
    'payer_name'=>'Test Payer', 'payer_email'=>'payer@example.test', 'user_id'=>33, 'company_name'=>null,
    'provider'=>'bank_muscat', 'gateway_merchant_id'=>'test', 'provider_checkout_id'=>'order-test',
    'provider_payment_id'=>'tracking-test', 'bank_reference'=>'bank-test', 'initiated_at'=>'2026-09-05 10:00:00',
    'paid_at'=>'2026-09-05 10:05:00', 'verified_at'=>'2026-09-05 10:05:00', 'failed_at'=>null,
    'failure_code'=>null, 'verification_issues'=>'["bank_tracking_reference_conflict"]',
    'description'=>'Annual vendor renewal',
    'response_json'=>'{"order_status":"Success","payment_mode":"OPTCRDC","card_number":"4111111111111111","status_message":"<script>alert(2)</script>","cvv":"876"}',
    'status_response_json'=>'{"order_status":"Shipped","order_bank_ref_no":"bank-confirmed-test","order_amt":"10.123","order_currency":"OMR"}',
];
$event = (object) ['received_at'=>'2026-09-05 10:05:00', 'event_type'=>'return.received', 'status'=>'recorded',
    'provider_event_id'=>'event-test', 'processed_at'=>'2026-09-05 10:05:00', 'payload_sha256'=>str_repeat('a',64),
    'verification_issues'=>'[]', 'response_json'=>'{"status_message":"<script>alert(2)</script>"}'];
$data = ['module'=>'vendor', 'payment'=>$payment, 'can_responses'=>true, 'can_reconcile'=>true,
    'presentation'=>App\Libraries\Payments\Payment_accounting_presenter::detail($payment, true, [$event])];
$html = $render('detail', $data);
$assert(str_contains($html, 'OMR 10.123'), 'View preserves the third OMR decimal.');
$assert(str_contains($html, 'payment_accounting/recheck/vendor/44'), 'Recheck submits the correct scoped payment.');
$assert(str_contains($html, 'method="post"') && str_contains($html, 'name="csrf_test"'), 'Recheck uses the form helper POST and CSRF field.');
$assert(str_contains($html, app_lang('payment_accounting_issue_reference')), 'Conflicting bank reference remains visibly flagged in plain language.');
$assert(!str_contains($html, '<script>alert(') && !str_contains($html, '<img src=x'), 'User and bank strings are HTML escaped.');
$assert(str_contains($html, 'Payment return from the bank') && str_contains($html, 'Independent confirmation from the bank') && str_contains($html, 'bank-confirmed-test'), 'Original and independently verified responses are both shown with readable headings.');
$assert(str_contains($html, 'Annual vendor renewal') && str_contains($html, 'Test Payer') && str_contains($html, 'payer@example.test'), 'The payment purpose and payer are readable.');
$assert(str_contains($html, '**** 1111') && str_contains($html, 'Credit card') && !str_contains($html, '4111111111111111') && !str_contains($html, '876'), 'Only masked last four and a readable payment method are displayed.');
$assert(!str_contains($html, '<pre') && !str_contains($html, 'SHA-256') && !str_contains($html, 'order_status') && !str_contains($html, 'bank_tracking_reference_conflict'), 'JSON, hashes and internal verification codes are replaced by labeled fields and explanations.');
$assert(str_contains($html, 'Customer returned from the bank') && !str_contains($html, 'return.received'), 'Activity timeline uses plain-language events.');
$assert(str_contains($html, 'UTC') && !str_contains($html, 'payment_accounting_next_'), 'Application times are unambiguous and guidance is translated.');
$data['can_responses'] = false;
$data['can_reconcile'] = false;
$data['presentation'] = App\Libraries\Payments\Payment_accounting_presenter::detail($payment, false, [$event]);
$html = $render('detail', $data);
$assert(!str_contains($html, 'payment-accounting-recheck') && !str_contains($html, '**** 1111') && !str_contains($html, 'Customer returned from the bank') && !str_contains($html, 'bank-confirmed-test'), 'A view-only user receives no bank details, history or recheck control.');
$assert($data['presentation']['bank_return'] === [] && $data['presentation']['bank_check'] === [] && $data['presentation']['timeline'] === [], 'The presenter excludes bank evidence before rendering for view-only users.');
$unknown = App\Libraries\Payments\Payment_accounting_presenter::issues('["unknown_internal_debug_secret"]');
$assert(count($unknown) === 1 && !str_contains($unknown[0], 'unknown_internal_debug_secret'), 'Unrecognized issue codes receive a safe useful explanation.');
$unknownBank = App\Libraries\Payments\Payment_accounting_presenter::bankFields('{"debug":"secret","expiry":"12/30","cvv":"876","card_token":"secret"}');
$assert($unknownBank === [], 'Unknown bank fields and security data never reach presentation.');
$pendingApply = clone $payment;
$pendingApply->settlement_status = 'pending';
$pendingApply->has_verification_issues = 0;
$pendingPresentation = App\Libraries\Payments\Payment_accounting_presenter::detail($pendingApply, false);
$assert(str_contains($pendingPresentation['next_step'], 'not yet been applied') && str_contains($pendingPresentation['next_step'], 'Do not ask'), 'Confirmed funds awaiting application do not imply completed service or request another payment.');
$queued = clone $payment;
$queued->status = 'processing';
$queued->settlement_status = 'pending';
$queued->has_verification_issues = 0;
$queued->handed_off_at = null;
$queuedView = App\Libraries\Payments\Payment_accounting_presenter::detail($queued, false);
$assert($queuedView['status'] === 'Awaiting checkout' && !str_contains($queuedView['next_step'], 'sent to the bank'), 'An attempt created before bank handoff is clearly awaiting checkout.');
$queued->handed_off_at = '2026-09-05 10:01:00';
$handedView = App\Libraries\Payments\Payment_accounting_presenter::detail($queued, false);
$assert($handedView['status'] === 'Processing' && str_contains($handedView['next_step'], 'sent to the bank'), 'Only a recorded handoff says the customer was sent to the bank.');
$missingOrder = clone $queued;
$missingOrder->status = 'verification_required';
$missingOrder->has_verification_issues = 1;
$missingOrder->status_response_json = '{"status":"1","error_code":"51313","error_desc":"No record found"}';
$missingOrder->verification_issues = '["order_id_mismatch","amount_missing_or_invalid","currency_missing_or_mismatch","bank_status_query_error"]';
$missingView = App\Libraries\Payments\Payment_accounting_presenter::detail($missingOrder, true);
$assert(count($missingView['issues']) === 1 && str_contains($missingView['issues'][0], 'could not find this payment order'), 'A bank no-record response is explained once rather than as multiple misleading mismatches.');
$assert(str_contains($missingView['next_step'], 'Payment is not confirmed'), 'No bank record can be interpreted as a successful payment.');
$assert(!str_contains(json_encode($missingView), '51313'), 'Bank internal error codes stay out of the readable presentation.');
foreach (['pending', 'processing', 'failed', 'cancelled', 'expired', 'verification_required', 'paid'] as $state) {
    $sample = clone $payment;
    $sample->status = $state;
    $sample->settlement_status = $state === 'paid' ? 'applied' : 'pending';
    $sample->has_verification_issues = 0;
    $presented = App\Libraries\Payments\Payment_accounting_presenter::detail($sample, false);
    $assert($presented['next_step'] !== '' && !str_starts_with($presented['next_step'], 'payment_accounting_'), 'Every outcome provides translated next-step guidance.');
}
$arabic = require __DIR__ . '/../app/Language/arabic/custom_lang.php';
foreach ($translations as $key => $value) {
    if (str_starts_with($key, 'payment_accounting_')) {
        $assert(isset($arabic[$key]) && $arabic[$key] !== '', 'Arabic accounting translation exists for ' . $key);
    }
}
// Render the shared header as a dedicated accountant. Operational helpers must
// not be reached, even if a previous dashboard/theme setting is still present.
function get_setting($key) { return ['language'=>'english', 'user_33_dashboard'=>'old-operational-dashboard', 'show_theme_color_changer'=>'yes', 'module_message'=>'1'][$key] ?? ''; }
function get_logo_url() { return '/test-logo.svg'; }
function get_avatar($image) { return '/test-avatar.svg'; }
function get_language_list() { return ['english', 'arabic']; }
function js_anchor($label, $attrs = []) { return '<a href="#" id="' . esc($attrs['id'] ?? '') . '">' . $label . '</a>'; }
function ajax_anchor($uri, $label, $attrs = []) { return anchor($uri, $label, $attrs); }
function can_access_reminders_module() { throw new RuntimeException('Accounting header must not load operational reminders.'); }
function can_access_messages_module() { throw new RuntimeException('Accounting header must not load operational messages.'); }
function get_custom_theme_color_list() { throw new RuntimeException('Accounting header must not load team preference controls.'); }
$login_user = (object)['id'=>33, 'user_type'=>'staff', 'first_name'=>'Test', 'last_name'=>'Accountant',
    'email'=>'accountant@example.test', 'image'=>'', 'language'=>'english', 'is_admin'=>0,
    'permissions'=>['accounting_only'=>'1', 'can_view_gate_pass_accounting'=>'1']];
ob_start();
require __DIR__ . '/../app/Views/includes/topbar.php';
$header = ob_get_clean();
$assert(str_contains($header, 'payment_accounting/index/gate_pass') && !str_contains($header, 'old-operational-dashboard'), 'Accountant brand links to a permitted ledger rather than a previous dashboard.');
$assert(!str_contains($header, 'web-notification-icon') && !str_contains($header, 'notifications/count_notifications'), 'Accountant header does not poll inaccessible operational notifications.');
$assert(str_contains($header, 'portal_account/change_password') && str_contains($header, 'signin/sign_out'), 'Accountant retains password and sign-out controls.');
$assert(str_contains($header, 'team_members/save_personal_language/english') && !str_contains($header, 'team_members/view'), 'Accountant can change their own language without a team profile link.');
restore_error_handler();
echo "Accounting views render safely; readable fields, masked cards, all outcome guidance, bilingual labels, evidence permissions and POST recheck controls passed.\n";
