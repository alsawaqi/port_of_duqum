<?php $mfaCodeLength = (int) config('AuthSecurity')->mfaCodeLength; ?>
<div class="card mb15">
    <div class="card-header text-center">
        <h2>Verify your sign-in</h2>
    </div>
    <div class="card-body p30 rounded-bottom">
        <p>
            Enter the one-time code sent to
            <strong><?php echo esc($destination_hint ?? 'your registered contact method'); ?></strong>.
        </p>

        <?php echo form_open("signin/verify_mfa", [
            "id" => "mfa-challenge-form",
            "class" => "general-form",
            "role" => "form",
        ]); ?>
        <div class="form-group">
            <label for="code">Verification code</label>
            <?php echo form_input([
                "id" => "code",
                "name" => "code",
                "type" => "text",
                "class" => "form-control p10",
                "inputmode" => "numeric",
                "pattern" => "[0-9]*",
                "maxlength" => $mfaCodeLength,
                "autocomplete" => "one-time-code",
                "data-rule-required" => true,
                "data-rule-digits" => true,
                "data-rule-minlength" => $mfaCodeLength,
                "data-rule-maxlength" => $mfaCodeLength,
                "autofocus" => true,
            ]); ?>
        </div>
        <p class="text-muted">
            <?php echo (int) ($remaining_attempts ?? 0); ?> attempt(s) remain.
            The code expires shortly and can be used only once.
        </p>
        <button class="w-100 btn btn-lg btn-primary btn-block" type="submit">
            Verify and continue
        </button>
        <?php echo form_close(); ?>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#mfa-challenge-form").appForm({
            isModal: false,
            onSubmit: function () {
                appLoader.show();
            },
            onSuccess: function (result) {
                appLoader.hide();
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                }
            },
            onError: function (result) {
                appLoader.hide();
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                    return false;
                }
                appAlert.error(result.message, {container: '.card-body', animate: false});
                return false;
            }
        });
    });
</script>
