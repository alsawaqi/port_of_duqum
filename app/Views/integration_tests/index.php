<?php
use App\Libraries\Payments\Payment_accounting_presenter;
use App\Libraries\Sms\IsmartSmsGateway;
$canCheckout = $bank_ready && $bank->provider === 'bank_muscat' && $bank->isReady() && $bank->smartpayEnvironment === 'uat';
$status = static fn($row) => Payment_accounting_presenter::status($row);
?>
<div id="page-content" class="page-wrapper clearfix integration-tests">
 <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div><h1 class="h3 mb-2"><?= app_lang('integration_tests_title') ?></h1><p class="text-muted mb-0"><?= app_lang('integration_tests_intro') ?></p></div>
  <a href="<?= esc(get_uri('dashboard'), 'attr') ?>" class="btn btn-default"><?= app_lang('dashboard') ?></a>
 </div>
 <div class="row">
  <div class="col-lg-6 mb-4">
   <div class="card h-100 mb-0"><div class="card-header d-flex align-items-center justify-content-between"><h2 class="h5 mb-0"><?= app_lang('integration_tests_sms') ?></h2><i data-feather="message-square" class="icon-20"></i></div>
    <div class="card-body">
     <p><?= app_lang('integration_tests_sms_hint') ?></p>
     <div class="alert alert-light border">
      <div><?= app_lang('integration_tests_sender') ?>: <strong><?= esc($sms_header ?: '—') ?></strong></div>
      <div><?= app_lang('integration_tests_connection') ?>: <strong><?= app_lang($sms_enabled ? 'integration_tests_enabled' : 'integration_tests_disabled') ?></strong></div>
      <div><?= app_lang('integration_tests_otp') ?>: <strong><?= app_lang($otp_enabled ? 'integration_tests_enabled' : 'integration_tests_disabled') ?></strong></div>
     </div>
     <?php if (!$sms_ready): ?><div class="alert alert-warning"><?= app_lang('integration_tests_sms_setup') ?></div><?php endif; ?>
     <?= form_open(get_uri('integration_tests/send_sms'), ['class' => 'integration-test-form', 'id' => 'integration-sms-form']) ?>
      <input type="hidden" name="request_token" value="<?= esc($request_token, 'attr') ?>">
      <div class="mb-3"><label for="test-mobile" class="form-label"><?= app_lang('integration_tests_mobile') ?></label>
       <input id="test-mobile" name="mobile" class="form-control" type="tel" dir="ltr" maxlength="40" placeholder="+968 9XXXXXXX" autocomplete="off" required></div>
      <div class="mb-3"><label for="test-language" class="form-label"><?= app_lang('language') ?></label>
       <select id="test-language" name="language" class="form-select"><option value="0">English</option><option value="64">العربية / Unicode</option></select></div>
      <div class="mb-3"><label for="test-message" class="form-label"><?= app_lang('message') ?></label>
       <textarea id="test-message" name="message" rows="4" maxlength="765" class="form-control" style="min-height:112px" required><?= app_lang('integration_tests_default_sms') ?></textarea>
       <small class="text-muted"><?= app_lang('integration_tests_max_characters') ?>: <span id="test-message-limit">765</span></small></div>
      <button type="submit" class="btn btn-primary" <?= $sms_ready ? '' : 'disabled' ?>><?= app_lang('integration_tests_send') ?></button>
      <a class="btn btn-link" href="<?= esc(get_uri('settings/sms'), 'attr') ?>"><?= app_lang('integration_tests_sms_settings') ?></a>
      <div class="test-result alert mt-3 d-none" role="status" aria-live="polite" tabindex="-1"></div>
     <?= form_close() ?>
    </div>
   </div>
  </div>
  <div class="col-lg-6 mb-4">
   <div class="card h-100 mb-0"><div class="card-header d-flex align-items-center justify-content-between"><h2 class="h5 mb-0">Bank Muscat SmartPay</h2><i data-feather="credit-card" class="icon-20"></i></div>
    <div class="card-body">
     <div class="alert alert-light border">
      <div><?= app_lang('integration_tests_environment') ?>: <strong><?= esc(['uat' => 'UAT / Test', 'production' => 'Live'][$bank->smartpayEnvironment] ?? 'Not configured') ?></strong></div>
      <div><?= app_lang('integration_tests_configuration') ?>: <strong><?= app_lang($bank->provider === 'bank_muscat' && $bank->isReady() ? 'integration_tests_config_present' : 'integration_tests_config_missing') ?></strong></div>
      <div class="text-break"><?= app_lang('integration_tests_return_url') ?>: <?= esc($bank->smartpayPublicBaseUrl . '/eservice_payment/return_from_bank') ?></div>
     </div>
     <p><?= app_lang('integration_tests_connection_hint') ?></p>
     <?= form_open(get_uri('integration_tests/check_bank_connection'), ['class' => 'integration-test-form', 'id' => 'integration-bank-form']) ?>
      <button type="submit" class="btn btn-default"><?= app_lang('integration_tests_check_connection') ?></button>
      <div class="test-result alert mt-3 d-none" role="status" aria-live="polite" tabindex="-1"></div>
     <?= form_close() ?>
     <hr class="my-4">
     <h3 class="h6"><?= app_lang('integration_tests_checkout') ?></h3>
     <p><?= app_lang('integration_tests_checkout_hint') ?></p>
     <?php if (!$canCheckout): ?><div class="alert alert-warning"><?= app_lang('integration_tests_checkout_setup') ?></div><?php endif; ?>
     <?= form_open(get_uri('integration_tests/start_payment'), ['class' => 'integration-test-form', 'id' => 'integration-payment-form']) ?>
      <input type="hidden" name="request_token" value="<?= esc($request_token, 'attr') ?>">
      <button type="submit" class="btn btn-primary" <?= $canCheckout ? '' : 'disabled' ?>><?= app_lang('integration_tests_start_checkout') ?></button>
      <div class="test-result alert mt-3 d-none" role="status" aria-live="polite" tabindex="-1"></div>
     <?= form_close() ?>
    </div>
   </div>
  </div>
 </div>
 <?php if ($selected_payment): ?>
 <div class="card" id="test-payment-detail"><div class="card-header"><h2 class="h5 mb-0"><?= app_lang('integration_tests_payment_result') ?></h2></div><div class="card-body">
  <div class="alert alert-info"><strong><?= esc($status($selected_payment)) ?></strong><div><?= app_lang('integration_tests_no_business_fee') ?></div></div>
  <dl class="row">
   <?php foreach ([app_lang('reference') => $selected_payment->provider_checkout_id, app_lang('amount') => 'OMR ' . $selected_payment->amount,
      app_lang('integration_tests_started_by') => $actors[$selected_payment->user_id] ?? ('#' . $selected_payment->user_id),
      app_lang('integration_tests_verified') => $selected_payment->verified_at ?: '—'] as $label => $value): ?>
    <dt class="col-sm-4 mb-2"><?= esc($label) ?></dt><dd class="col-sm-8 mb-2 text-break"><?= esc($value) ?></dd>
   <?php endforeach; ?>
  </dl>
  <?= form_open(get_uri('integration_tests/recheck_payment/' . $selected_payment->public_id), ['class' => 'integration-test-form']) ?>
   <button class="btn btn-default" type="submit" <?= empty($selected_payment->handed_off_at) ? 'disabled' : '' ?>><?= app_lang('integration_tests_recheck') ?></button>
   <a class="btn btn-link" href="<?= esc(get_uri('integration_tests/payment/' . $selected_payment->public_id), 'attr') ?>"><?= app_lang('integration_tests_refresh') ?></a>
   <div class="test-result alert mt-3 d-none" role="status" aria-live="polite" tabindex="-1"></div>
  <?= form_close() ?>
  <div class="row mt-4">
   <?php foreach ([app_lang('integration_tests_bank_return') => $bank_return, app_lang('integration_tests_bank_verification') => $bank_check] as $title => $fields): ?>
   <div class="col-lg-6"><h3 class="h6"><?= esc($title) ?></h3>
    <?php if (!$fields): ?><p class="text-muted"><?= app_lang('integration_tests_no_response') ?></p><?php endif; ?>
    <dl><?php foreach ($fields as $field): ?><dt><?= esc($field['label']) ?></dt><dd class="text-break"><?= esc($field['value']) ?></dd><?php endforeach; ?></dl>
   </div>
   <?php endforeach; ?>
  </div>
 </div></div>
 <?php endif; ?>
 <div class="card"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 mb-0"><?= app_lang('integration_tests_history') ?></h2><a class="btn btn-default btn-sm" href="<?= esc(get_uri('integration_tests'), 'attr') ?>"><?= app_lang('integration_tests_refresh') ?></a></div>
  <div class="card-body"><p class="text-muted"><?= app_lang('integration_tests_history_hint') ?></p>
   <h3 class="h6"><?= app_lang('integration_tests_payments') ?></h3>
   <div class="table-responsive"><table class="table"><thead><tr><th><?= app_lang('date') ?> (UTC)</th><th><?= app_lang('reference') ?></th><th><?= app_lang('integration_tests_started_by') ?></th><th><?= app_lang('amount') ?></th><th><?= app_lang('status') ?></th><th><?= app_lang('actions') ?></th></tr></thead><tbody>
    <?php foreach ($payment_rows as $row): ?><tr>
     <td><?= esc($row->initiated_at) ?></td><td><?= esc($row->provider_checkout_id) ?></td><td><?= esc($actors[$row->user_id] ?? ('#' . $row->user_id)) ?></td><td>OMR <?= esc($row->amount) ?></td><td><?= esc($status($row)) ?></td>
     <td><a href="<?= esc(get_uri('integration_tests/payment/' . $row->public_id), 'attr') ?>"><?= app_lang('integration_tests_view_result') ?></a>
     <?php if ($row->status === 'processing' && empty($row->handed_off_at) && strtotime($row->expires_at . ' UTC') > time()): ?>
     <br><a href="<?= esc(get_uri('eservice_payment/checkout/' . $row->public_id), 'attr') ?>"><?= app_lang('integration_tests_continue') ?></a><?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$payment_rows): ?><tr><td colspan="6"><?= app_lang('integration_tests_no_history') ?></td></tr><?php endif; ?>
   </tbody></table></div>
   <h3 class="h6 mt-4"><?= app_lang('integration_tests_sms') ?></h3>
   <div class="table-responsive"><table class="table"><thead><tr><th><?= app_lang('date') ?> (UTC)</th><th><?= app_lang('integration_tests_started_by') ?></th><th><?= app_lang('integration_tests_mobile') ?></th><th><?= app_lang('message') ?></th><th><?= app_lang('status') ?></th></tr></thead><tbody>
    <?php foreach ($sms_rows as $row): ?><tr><td><?= esc($row['created_at']) ?></td><td><?= esc($actors[$row['source_id']] ?? ('#' . $row['source_id'])) ?></td><td dir="ltr"><?= esc($row['mobile']) ?></td><td style="min-width:180px;max-width:360px;white-space:pre-wrap;overflow-wrap:anywhere"><?= esc($row['message']) ?></td><td style="min-width:200px"><?= esc(IsmartSmsGateway::describe($row['status'], isset($row['provider_code']) ? (int)$row['provider_code'] : null)) ?><?php if (isset($row['provider_code'])): ?><br><?= app_lang('integration_tests_provider_code') ?>: <?= (int)$row['provider_code'] ?><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$sms_rows): ?><tr><td colspan="5"><?= app_lang('integration_tests_no_history') ?></td></tr><?php endif; ?>
   </tbody></table></div>
  </div>
 </div>
</div>
<script>
$(function () {
 $('#test-language').on('change', function () {
  var limit = this.value === '64' ? 335 : 765;
  $('#test-message').attr('maxlength', limit);
  $('#test-message-limit').text(limit);
 });
 $('.integration-test-form').on('submit', function (event) {
  event.preventDefault();
  var form = $(this), button = form.find('button[type=submit]'), box = form.find('.test-result');
  if (button.prop('disabled')) return;
  button.prop('disabled', true);
  box.removeClass('d-none alert-success alert-danger').addClass('alert-info').text(<?= json_encode(app_lang('integration_tests_running'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
  function show(result) {
   box.removeClass('alert-info alert-success alert-danger').addClass(result.success ? 'alert-success' : 'alert-danger').empty();
   $('<div>').text(result.message || 'Unable to complete this test.').appendTo(box);
   if (result.reference) $('<div>').text('Reference: ' + result.reference).appendTo(box);
   if (result.provider_code !== null && result.provider_code !== undefined) $('<div>').text('Provider code: ' + result.provider_code).appendTo(box);
   (result.checks || []).forEach(function (check) {
    var block = $('<div class="mt-3 text-break">').appendTo(box);
    $('<strong>').text(check.name + ' — ' + check.host).appendTo(block);
    $('<div>').text(check.message).appendTo(block);
    if (check.error) $('<small>').text('Connection code: ' + check.error).appendTo(block);
   });
   if (result.next_token) form.find('[name=request_token]').val(result.next_token);
   box.trigger('focus');
   if (result.success && result.redirect_url) window.location.assign(result.redirect_url);
  }
  $.ajax({url: form.attr('action'), type: 'POST', data: form.serialize(), dataType: 'json',
   success: show,
   error: function (xhr) { show(xhr.responseJSON || {success:false, message:'Unable to complete this test. Check the test history before retrying.'}); },
   complete: function () { button.prop('disabled', false); }
  });
 });
});
</script>
