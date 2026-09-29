<?php
$captcha_enabled = getenv("PODC_RECAPTCHA_ENABLED");
if ($captcha_enabled !== false && trim((string) $captcha_enabled) !== ""
    && filter_var($captcha_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false) {
    return;
}

$re_captcha_protocol = strtolower(trim((string) (getenv("PODC_RECAPTCHA_PROTOCOL") ?: get_setting("re_captcha_protocol") ?: "v2")));
$site_key = trim((string) (getenv("PODC_RECAPTCHA_SITE_KEY") ?: get_setting("re_captcha_site_key")));
$secret_key = trim((string) (getenv("PODC_RECAPTCHA_SECRET_KEY") ?: get_setting("re_captcha_secret_key")));

if ($site_key && $secret_key) {
    if ($re_captcha_protocol === "v2") {
?>

        <div class="form-group">
            <div class="g-recaptcha" data-sitekey="<?php echo esc($site_key); ?>"></div>
        </div>

        <script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=<?php echo rawurlencode(app_lang('language_locale')); ?>"></script>

    <?php } else if ($re_captcha_protocol === "v3") { ?>

        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo rawurlencode($site_key); ?>&hl=<?php echo rawurlencode(app_lang('language_locale')); ?>"></script>

        <input type="hidden" name="re_captcha_token" id="re_captcha_token">

        <div class="form-group">
            <div id="recaptcha-container"></div>
        </div>

        <script>
            grecaptcha.ready(function() {
                grecaptcha.execute(<?php echo json_encode($site_key); ?>, {
                    action: 'submit'
                }).then(function(token) {
                    document.getElementById('re_captcha_token').value = token;
                });
            });
        </script>

<?php }
} ?>
