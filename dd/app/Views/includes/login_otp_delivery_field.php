<?php
$otpUserId = (int) ($otp_user_id ?? 0);
$otpActor = $login_user ?? model('App\Models\Users_model')->get_one((int) service('session')->get('user_id'));
$otpChoice = (new \App\Libraries\Auth\UserOtpPreference())->get($otpUserId);
$otpFieldId = 'login-otp-' . uniqid();
?>
<div class="form-group login-otp-delivery">
    <div class="row">
        <label class="col-md-3" for="<?php echo esc($otpFieldId); ?>"><?php echo app_lang('login_otp_delivery'); ?></label>
        <div class="col-md-9">
            <select id="<?php echo esc($otpFieldId); ?>" name="otp_delivery_channel" class="form-control js-login-otp-channel"
                <?php echo $otpUserId && empty($otpActor->is_admin) ? 'disabled' : ''; ?>>
                <?php foreach (['' => 'login_otp_system_default', 'sms' => 'login_otp_sms', 'email' => 'login_otp_email'] as $value => $label): ?>
                    <option value="<?php echo esc($value); ?>" <?php echo $otpChoice === $value ? 'selected' : ''; ?>><?php echo app_lang($label); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted d-block"><?php echo app_lang('login_otp_delivery_help'); ?></small>
        </div>
    </div>
</div>
