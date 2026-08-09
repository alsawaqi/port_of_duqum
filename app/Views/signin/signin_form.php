<?php
$session = \Config\Services::session();
$signin_validation_errors = $session->getFlashdata("signin_validation_errors");
$signin_logo_url = base_url("assets/images/port-duqum-signin-logo.png");
?>

<div class="pod-signin-card">
    <div class="pod-signin-head">
        <span class="pod-signin-logo">
            <img src="<?php echo $signin_logo_url; ?>" alt="Port of Duqm" />
        </span>
        <div>
            <span class="pod-signin-kicker">Port of Duqm Portal</span>
            <h2><?php echo app_lang('signin'); ?></h2>
            <p>Use your registered email and password to continue.</p>
        </div>
    </div>

    <div id="signin-inline-alert" class="pod-signin-alert" role="alert" <?php echo ($signin_validation_errors && is_array($signin_validation_errors)) ? "" : "hidden"; ?>>
        <?php
        if ($signin_validation_errors && is_array($signin_validation_errors)) {
            foreach ($signin_validation_errors as $validation_error) {
                ?>
                <span>
                    <i data-feather="alert-circle" class="icon-16"></i>
                    <?php echo esc($validation_error); ?>
                </span>
                <?php
            }
        }
        ?>
    </div>

    <?php echo form_open("signin/authenticate", array("id" => "signin-form", "class" => "general-form pod-signin-form", "role" => "form", "autocomplete" => "on")); ?>

    <div class="form-group">
        <label class="pod-auth-label" for="email"><?php echo app_lang('email'); ?></label>
        <div class="pod-input-wrap">
            <i data-feather="mail" class="pod-input-icon icon-18"></i>
            <?php
            echo form_input(array(
                "type" => "email",
                "id" => "email",
                "name" => "email",
                "class" => "form-control pod-auth-input",
                "placeholder" => app_lang('email'),
                "autofocus" => true,
                "autocomplete" => "username",
                "data-rule-required" => true,
                "data-msg-required" => app_lang("field_required")
            ));
            ?>
        </div>
    </div>

    <div class="form-group">
        <label class="pod-auth-label" for="password"><?php echo app_lang('password'); ?></label>
        <div class="pod-input-wrap">
            <i data-feather="lock" class="pod-input-icon icon-18"></i>
            <?php
            echo form_password(array(
                "id" => "password",
                "name" => "password",
                "class" => "form-control pod-auth-input",
                "placeholder" => app_lang('password'),
                "autocomplete" => "current-password",
                "data-rule-required" => true,
                "data-msg-required" => app_lang("field_required")
            ));
            ?>
        </div>
    </div>

    <input type="hidden" name="redirect" value="<?php echo isset($redirect) ? esc($redirect) : ""; ?>" />

    <?php echo view("signin/re_captcha"); ?>

    <button id="signin-submit" class="w-100 btn btn-lg btn-primary pod-auth-submit" type="submit">
        <span class="pod-submit-label"><?php echo app_lang('signin'); ?></span>
        <span class="pod-submit-loading">
            <span class="pod-spinner" aria-hidden="true"></span>
            Signing in...
        </span>
    </button>

    <?php echo form_close(); ?>

    <div class="pod-signin-links">
        <?php echo anchor("signin/request_reset_password", app_lang("forgot_password")); ?>

        <?php if (!get_setting("disable_client_signup")) { ?>
            <span><?php echo app_lang("you_dont_have_an_account") ?> <?php echo anchor("signup", app_lang("signup")); ?></span>
        <?php } ?>
    </div>

    <?php
    app_hooks()->do_action('app_hook_signin_extension');
    ?>
</div>

<div id="pod-welcome-overlay" class="pod-welcome-overlay" hidden>
    <div class="pod-welcome-card" role="status" aria-live="polite">
        <span class="pod-welcome-check">
            <i data-feather="check" class="icon-22"></i>
        </span>
        <h3 id="pod-welcome-title">Welcome back.</h3>
        <p>Taking you to your dashboard...</p>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        var $form = $("#signin-form");
        var $button = $("#signin-submit");
        var $alert = $("#signin-inline-alert");
        var $overlay = $("#pod-welcome-overlay");
        var $welcomeTitle = $("#pod-welcome-title");
        var fallbackDashboardUrl = <?php echo json_encode(get_uri("dashboard")); ?>;
        var genericErrorMessage = <?php echo json_encode(app_lang("error_occurred")); ?>;

        function setSigninLoading(isLoading) {
            $form.toggleClass("is-loading", isLoading);
            $button.toggleClass("is-loading", isLoading).prop("disabled", isLoading);
        }

        function clearSigninAlert() {
            $alert.attr("hidden", "hidden").empty();
        }

        function showSigninAlert(message) {
            $alert.html(message || genericErrorMessage).removeAttr("hidden");
        }

        $form.appForm({
            isModal: false,
            showLoader: false,
            onSubmit: function () {
                clearSigninAlert();
                setSigninLoading(true);
            },
            onSuccess: function (result) {
                var redirectUrl = result.redirect_url || fallbackDashboardUrl;

                setSigninLoading(true);
                $welcomeTitle.text(result.message || "Welcome back.");
                $overlay.removeAttr("hidden").addClass("is-visible");

                if (typeof feather !== "undefined") {
                    feather.replace();
                }

                setTimeout(function () {
                    window.location.href = redirectUrl;
                }, 950);
            },
            onError: function (result) {
                setSigninLoading(false);
                showSigninAlert(result && result.message ? result.message : null);
                return false;
            }
        });
    });
</script>
