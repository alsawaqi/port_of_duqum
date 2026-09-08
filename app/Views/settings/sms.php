<?php use App\Libraries\Sms\IsmartSmsGateway; use App\Libraries\Auth\OmanMobileNumber; ?>
<div id="page-content" class="page-wrapper clearfix">
 <div class="row">
  <div class="col-sm-3 col-lg-2"><?php echo view('settings/tabs', ['active_tab' => 'sms']); ?></div>
  <div class="col-sm-9 col-lg-10">
   <div class="card">
    <div class="card-header"><h4><?php echo app_lang('sms'); ?></h4></div>
    <div class="card-body">
     <p>Send approval, revision, rejection and tender notices to the registered mobile number of the applicant or linked vendor user.</p>
     <div class="alert <?php echo $sms_ready ? 'alert-success' : 'alert-info'; ?>">
      <?php echo $sms_ready ? 'iSmartSMS connection is enabled and credentials are present. Review workflow delivery results below.' : 'iSmartSMS is disabled or credentials are incomplete. Notification previews work without sending SMS.'; ?>
     </div>
     <?php echo form_open(get_uri('settings/save_sms_settings'), ['id' => 'sms-settings-form', 'class' => 'general-form']); ?>
     <div id="sms-form-error" class="alert alert-danger d-none" role="alert"></div>
     <h5>iSmartSMS connection</h5>
     <div class="mb-3"><label for="ismartsms_user_id">SMS account username</label>
      <input id="ismartsms_user_id" name="ismartsms_user_id" type="text" class="form-control" maxlength="190" autocomplete="off" value="<?php echo esc($sms_config->userId, 'attr'); ?>">
     </div>
     <div class="mb-3"><label for="ismartsms_password">SMS account password</label>
      <input id="ismartsms_password" name="ismartsms_password" type="password" class="form-control" maxlength="512" autocomplete="new-password" value="">
      <small class="text-muted"><?php echo $sms_config->password !== '' ? 'A password is configured. Leave this blank to keep it.' : 'Enter the password supplied by iSmartSMS.'; ?></small>
     </div>
     <div class="mb-3"><label><?php echo form_checkbox('ismartsms_enabled', '1', $sms_config->enabled, 'id="ismartsms_enabled" class="form-check-input me-2"'); ?>Enable the SMS connection</label></div>
     <div class="mb-3"><label for="ismartsms_header">Approved sender name (Header)</label>
      <input id="ismartsms_header" name="ismartsms_header" type="text" class="form-control" maxlength="11" value="<?php echo esc($sms_config->header, 'attr'); ?>">
      <small class="text-muted">Enter the exact sender name approved by Infocomm, including spaces.</small>
     </div>
     <p class="text-muted">Settings saved here override .env. The password is stored encrypted and is never displayed. Recipients and messages come from each notification or login code.</p>
     <hr>
     <?php foreach (['sms_notifications_enabled' => 'Record workflow SMS notifications', 'sms_vendor_enabled' => 'Vendor registration, renewal and review decisions', 'sms_gate_pass_enabled' => 'Gate pass review decisions and issuance', 'sms_ptw_enabled' => 'PTW review decisions and issuance', 'sms_tender_enabled' => 'Tender decisions and published messages', 'sms_live_notifications' => 'Send real SMS (leave off for previews)'] as $name => $label): ?>
      <div class="mb-3"><label><?php echo form_checkbox($name, '1', (bool) get_setting($name), "id='$name' class='form-check-input me-2'"); ?><?php echo esc($label); ?></label></div>
     <?php endforeach; ?>
     <div class="mb-3"><label for="sms_language">Notification language</label>
      <?php echo form_dropdown('sms_language', ['0' => 'English', '64' => 'Arabic'], (string) get_setting('sms_language'), 'id="sms_language" class="form-select"'); ?>
     </div>
     <p class="text-muted">Notifications are queued with the decision and processed by the scheduled job. Turning on real SMS does not send old previews. Uncertain sends are not retried automatically.</p>
     <button type="submit" class="btn btn-primary">Save SMS settings</button>
     <?php echo form_close(); ?>
    </div>
   </div>
   <div class="card" id="sms-login-verification"><div class="card-header"><h4>SMS login verification</h4></div><div class="card-body">
    <p><strong><?php echo $auth_config->mfaEnabled ? 'Login verification is enabled.' : 'Login verification is currently disabled.'; ?></strong></p>
    <p>After a correct password, users enter a six-digit SMS code. Codes expire after five minutes and allow five attempts. Every user needs their own registered Oman mobile number before SMS login is enabled.</p>
    <?php echo form_open(get_uri('settings/save_sms_login_settings'), ['id' => 'sms-login-settings-form', 'class' => 'general-form']); ?>
     <div id="sms-login-form-error" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
     <input type="hidden" name="sms_login_otp_required" value="0">
     <div class="form-check form-switch mb-3">
      <input type="checkbox" role="switch" class="form-check-input" id="sms_login_otp_required" name="sms_login_otp_required" value="1" aria-describedby="sms-login-help" <?php echo $auth_config->mfaEnabled ? 'checked' : ''; ?>>
      <label class="form-check-label fw-semibold" for="sms_login_otp_required">Require SMS OTP for all logins</label>
     </div>
     <p id="sms-login-help" class="text-muted">Applies to administrators, staff, vendor contacts, gate pass users and every other login account from their next sign-in. Existing signed-in sessions remain active. Workflow notification settings are separate.</p>
     <?php if ($sms_login_setup_error): ?><div class="alert alert-warning"><?php echo esc($sms_login_setup_error); ?></div><?php endif; ?>
     <button type="submit" class="btn btn-primary mb-3">Save login verification</button>
    <?php echo form_close(); ?>
    <p>Active login accounts missing a valid mobile number: <strong><?php echo count($missing_mobile_users); ?></strong></p>
    <?php if ($missing_mobile_users): ?><details><summary>Show accounts to update</summary>
     <p class="text-muted mt-3">Enter each person's own Oman mobile number in their account profile. Resolve these accounts before requiring SMS login for everyone.</p>
     <div class="table-responsive"><table class="table"><thead><tr><th>Account</th><th>Email</th><th>Mobile issue</th><th>Action</th></tr></thead><tbody>
     <?php foreach ($missing_mobile_users as $user): ?><tr>
      <td><?php echo esc(trim($user['first_name'] . ' ' . $user['last_name'])) . ' (#' . (int) $user['id'] . ')'; ?></td>
      <td><?php echo esc($user['email']); ?></td>
      <td><?php echo trim((string) $user['phone']) === '' ? 'Missing' : 'Not a valid Oman mobile'; ?></td>
      <td><?php if ($user['user_type'] === 'staff') { echo anchor('team_members/view/' . (int) $user['id'] . '/general', 'Update account'); } else { echo 'Update the client contact profile'; } ?></td>
     </tr><?php endforeach; ?>
     </tbody></table></div></details><?php endif; ?>
    <details class="mt-3"><summary>Connection setup for your system administrator</summary>
     <p>Use the switch above to manage mandatory SMS login. Once saved, it overrides the MFA enabled, provider and user-type defaults in .env. Keep the verification security key in .env. You can set SMS credentials above or use .env as the initial configuration. Infocomm must enable the HTTP GET API for the account. Requests include the approved sender name, the message language and normal (non-flash) delivery. Messages are sent immediately.</p>
     <pre class="bg-light p-3">ISMARTSMS_ENABLED = false
ISMARTSMS_USER_ID = ""
ISMARTSMS_PASSWORD = ""
ISMARTSMS_HEADER = ""

AUTH_SECURITY_MFA_ENABLED = false
AUTH_SECURITY_MFA_PROVIDER = "ismartsms"
AUTH_SECURITY_MFA_PROVIDER_MAP = ""
AUTH_SECURITY_MFA_USER_TYPES = "*"
AUTH_SECURITY_MFA_HMAC_KEY = "independent random secret, at least 32 bytes"</pre>
     <p>Enable the connection after adding the provider credentials. Enable login verification only after successful real SMS testing and correcting the accounts above. Workflow preview mode never bypasses login verification.</p>
    </details>
   </div></div>
   <div class="card"><div class="card-header"><h4>Recent SMS notifications</h4></div><div class="card-body">
    <p>Latest 100 records. All times are UTC. “Accepted” means accepted by the provider; it does not confirm delivery to the phone.</p>
    <?php echo form_open(get_uri('settings/process_sms_queue'), ['id' => 'sms-process-form']); ?>
    <button type="submit" class="btn btn-default mb-3">Process pending notifications</button><?php echo form_close(); ?>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Recorded</th><th>Section / Reference</th><th>Recipient</th><th>Message</th><th>Result</th></tr></thead><tbody>
     <?php foreach ($sms_records as $record): ?><tr>
      <td><?php echo esc($record['created_at']); ?></td>
      <td><?php echo esc(['vendor' => 'Vendor', 'gate_pass' => 'Gate pass', 'ptw' => 'PTW', 'tender' => 'Tender'][$record['module']] ?? $record['module']); ?><br><?php echo esc($record['reference']); ?></td>
      <td><?php echo esc($record['recipient_name']); ?><br><?php echo esc(OmanMobileNumber::mask((string) $record['mobile'])); ?></td>
      <td style="min-width:220px"><?php echo esc($record['message']); ?></td>
      <td style="min-width:180px"><?php echo esc(IsmartSmsGateway::describe($record['status'], $record['provider_code'] === null ? null : (int) $record['provider_code'])); ?><br><small><?php echo esc($record['processed_at'] ?? ''); ?></small></td>
     </tr><?php endforeach; ?>
     <?php if (!$sms_records): ?><tr><td colspan="5">No SMS notifications recorded yet.</td></tr><?php endif; ?>
    </tbody></table></div>
   </div></div>
  </div>
 </div>
</div>
<script>
$(document).ready(function () {
 $('#sms-settings-form, #sms-process-form, #sms-login-settings-form').on('submit', function (event) {
  event.preventDefault();
  var form = $(this), button = form.find('button[type=submit]');
  var isLoginForm = form.attr('id') === 'sms-login-settings-form';
  var errorBox = $(isLoginForm ? '#sms-login-form-error' : '#sms-form-error');
  errorBox.addClass('d-none').text('');
  function showError(message) {
   errorBox.removeClass('d-none').text(message).trigger('focus');
   if (isLoginForm) {
    $('#sms_login_otp_required').prop('checked', <?php echo $auth_config->mfaEnabled ? 'true' : 'false'; ?>);
   }
  }
  button.prop('disabled', true);
  $.ajax({url: form.attr('action'), type: 'POST', data: form.serialize(), dataType: 'json',
   success: function (r) { if (r.success) { location.reload(); } else { showError(r.message || 'Unable to save SMS settings.'); } },
   error: function (xhr) {
    showError((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to complete this request. Please try again.');
    if (form.attr('id') === 'sms-settings-form') {
     $('#sms_live_notifications').prop('checked', <?php echo get_setting('sms_live_notifications') ? 'true' : 'false'; ?>);
    }
   },
   complete: function () {button.prop('disabled', false);}
  });
 });
});
</script>
