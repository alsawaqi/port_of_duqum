<?php $passwordPolicy = config("AuthSecurity"); ?>
<div id="page-content" class="page-wrapper clearfix ggp-page guest-registration pod-gate-pass-public">
<div class="ggp-shell">
        <div class="ggp-card">
            <header class="registration-heading">
                <h1><?php echo app_lang("gate_pass_account_application"); ?></h1>
                <p><?php echo app_lang("gate_pass_account_application_subtitle"); ?></p>
                <div class="ggp-alert-note"><i data-feather="info" class="icon-18"></i><span><?php echo app_lang("gate_pass_signup_vs_gate_pass_account"); ?></span></div>
            </header>
            <div class="registration-layout">
            <?php echo view('includes/public/registration_navigation', ['registration_sections' => [['ggp-identity', 'gate_pass_account_information'], ['ggp-security', 'gate_pass_contact_security']]]); ?>

            <?php echo form_open(
                get_uri("guest_gate_pass/save"),
                [
                    "id"    => "guest-gp-form",
                    "class" => "general-form ggp-form",
                    "role"  => "form"
                ]
            ); ?>

            <div class="ggp-body">
                <div class="ggp-section" id="ggp-identity" tabindex="-1">
                    <div class="ggp-section-meta">
                        <div class="ggp-section-title">
                            <span class="ggp-section-icon"><i data-feather="user"></i></span>
                            <h2>01. <?php echo app_lang("gate_pass_account_information"); ?></h2>
                        </div>
                        <p class="ggp-help"><?php echo app_lang("gate_pass_account_information_help"); ?></p>
                    </div>

                    <div class="ggp-section-fields">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="first_name"><?php echo app_lang("first_name"); ?> <span class="text-danger">*</span></label>
                                    <input id="first_name" name="first_name" class="form-control" required autocomplete="given-name">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="last_name"><?php echo app_lang("last_name"); ?></label>
                                    <input id="last_name" name="last_name" class="form-control" autocomplete="family-name">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label for="email"><?php echo app_lang("email"); ?> <span class="text-danger">*</span></label>
                                    <input name="email" id="email" class="form-control" required type="email" autocomplete="email">
                                    <div id="email-error" class="alert alert-danger mt10 d-none"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ggp-section" id="ggp-security" tabindex="-1">
                    <div class="ggp-section-meta">
                        <div class="ggp-section-title">
                            <span class="ggp-section-icon"><i data-feather="smartphone"></i></span>
                            <h2>02. <?php echo app_lang("gate_pass_contact_security"); ?></h2>
                        </div>
                        <p class="ggp-help"><?php echo app_lang("gate_pass_contact_security_help"); ?></p>
                    </div>

                    <div class="ggp-section-fields">
                        <?php
                        $intl_dial_codes = $intl_dial_codes ?? [];
                        $default_dial = "+968";
                        ?>

                        <div class="form-group">
                            <label for="phone_local"><?php echo app_lang("gate_pass_phone_number"); ?> <span class="text-danger">*</span></label>
                            <div class="ggp-phone-grid">
                                <select name="phone_country_code" id="phone_country_code" class="form-control" required title="<?php echo app_lang("gate_pass_country_code"); ?>">
                                    <?php foreach ($intl_dial_codes as $d) {
                                        $sel = ($d["code"] === $default_dial) ? " selected" : "";
                                        echo "<option value=\"" . esc($d["code"]) . "\"{$sel}>" . esc($d["country"] . " (" . $d["code"] . ")") . "</option>\n";
                                    } ?>
                                </select>
                                <input name="phone_local" id="phone_local" class="form-control ggp-digits-only" required type="text" inputmode="numeric" pattern="[0-9]{4,15}" maxlength="15" autocomplete="tel-national" placeholder="71234567" title="<?php echo app_lang("gate_pass_numbers_only_hint"); ?>">
                            </div>
                            <small><?php echo app_lang("gate_pass_phone_entry_hint"); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="emergency_local"><?php echo app_lang("gate_pass_emergency_number"); ?> <span class="text-danger">*</span></label>
                            <div class="ggp-phone-grid">
                                <select name="emergency_country_code" id="emergency_country_code" class="form-control" required title="<?php echo app_lang("gate_pass_country_code"); ?>">
                                    <?php foreach ($intl_dial_codes as $d) {
                                        $sel = ($d["code"] === $default_dial) ? " selected" : "";
                                        echo "<option value=\"" . esc($d["code"]) . "\"{$sel}>" . esc($d["country"] . " (" . $d["code"] . ")") . "</option>\n";
                                    } ?>
                                </select>
                                <input name="emergency_local" id="emergency_local" class="form-control ggp-digits-only" required type="text" inputmode="numeric" pattern="[0-9]{4,15}" maxlength="15" autocomplete="tel-national" placeholder="71234567" title="<?php echo app_lang("gate_pass_numbers_only_hint"); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label for="otp_channel"><?php echo app_lang("gate_pass_otp_channel"); ?> <span class="text-danger">*</span></label>
                                    <select id="otp_channel" name="otp_channel" class="form-control">
                                        <option value="phone"><?php echo app_lang("gate_pass_otp_phone"); ?></option>
                                        <option value="email"><?php echo app_lang("gate_pass_otp_email"); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="form-group ggp-password-wrap">
                                    <label for="password"><?php echo app_lang("password"); ?></label>
                                    <div class="input-group">
                                        <input type="password" id="password" name="password" class="form-control"
                                               minlength="<?php echo (int) $passwordPolicy->passwordMinLength; ?>"
                                               maxlength="<?php echo (int) $passwordPolicy->passwordMaxLength; ?>"
                                               autocomplete="new-password">
                                        <button class="btn btn-outline-secondary ggp-password-toggle" type="button" id="toggle-password" aria-label="<?php echo app_lang("toggle_password_visibility"); ?>">
                                            <i data-feather="eye" class="icon-16"></i>
                                        </button>
                                    </div>
                                    <div id="password-error" class="alert alert-danger mt10 d-none"></div>
                                    <small>
                                        Use <?php echo (int) $passwordPolicy->passwordMinLength; ?>-<?php echo (int) $passwordPolicy->passwordMaxLength; ?>
                                        characters with uppercase, lowercase, number, and special character.
                                    </small>
                                    <small><?php echo app_lang("gate_pass_password_reuse_hint"); ?></small>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="password_confirm"><?php echo app_lang("password_confirm"); ?></label>
                                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" autocomplete="new-password" data-rule-equalTo="#password" data-msg-equalTo="<?php echo app_lang("passwords_do_not_match"); ?>">
                                    <div id="password-confirm-error" class="alert alert-danger mt10 d-none"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (get_setting("re_captcha_secret_key")) { ?>
                    <div class="ggp-section" style="--section-color: var(--ggp-amber);">
                        <div class="ggp-section-meta">
                            <div class="ggp-section-title">
                                <span class="ggp-section-icon"><i data-feather="lock"></i></span>
                                <h2><?php echo app_lang("gate_pass_verification"); ?></h2>
                            </div>
                            <p class="ggp-help"><?php echo app_lang("gate_pass_signup_recaptcha_hint"); ?></p>
                        </div>
                        <div class="ggp-section-fields">
                            <?php echo view("signin/re_captcha"); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="ggp-footer">
                <p class="note">
                    <?php echo app_lang("gate_pass_submit_note"); ?>
                </p>

                <a class="registration-back" href="<?php echo get_uri('signin'); ?>"><i data-feather="arrow-left" class="icon-16"></i><?php echo app_lang('guest_back_signin'); ?></a>

                <button type="submit" class="btn btn-primary ggp-btn">
                    <span class="spinner"></span>
                    <span class="btn-text"><?php echo app_lang("gate_pass_submit_account_application"); ?></span>
                    <i data-feather="arrow-right" class="icon-16"></i>
                </button>
            </div>

            <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    setTimeout(function () {
        $(".ggp-page").addClass("ggp-ready");
        if (window.feather) feather.replace();
    }, 60);

    if ($.fn.select2) {
        $("#phone_country_code, #emergency_country_code, #otp_channel").select2({
            width: "100%"
        });
    }

    function bindDigitsOnly($el) {
        var strip = function () {
            var value = String($el.val() || "").replace(/\D/g, "").slice(0, 15);
            if (value !== $el.val()) {
                $el.val(value);
            }
        };

        $el.on("input change blur", strip);
        $el.on("paste", function (e) {
            e.preventDefault();
            var text = (e.originalEvent || e).clipboardData.getData("text") || "";
            $el.val(text.replace(/\D/g, "").slice(0, 15));
        });
    }

    bindDigitsOnly($("#phone_local"));
    bindDigitsOnly($("#emergency_local"));

    $("#toggle-password").on("click", function() {
        var $password = $("#password");
        var isText = $password.attr("type") === "text";
        $password.attr("type", isText ? "password" : "text");
        $(this).find("svg").remove();
        $(this).append('<i data-feather="' + (isText ? "eye" : "eye-off") + '" class="icon-16"></i>');
        if (window.feather) feather.replace();
    });

    function clearErrors() {
        $("#email-error").addClass("d-none").text("");
        $("#password-error, #password-confirm-error").addClass("d-none").text("");
        $("#email, #password, #password_confirm").removeClass("is-invalid");
    }

    function showError(field, message) {
        if (field === "email") {
            $("#email").addClass("is-invalid");
            $("#email-error").removeClass("d-none").text(message);
        } else if (field === "password") {
            $("#password").addClass("is-invalid");
            $("#password-error").removeClass("d-none").text(message);
        } else if (field === "password_confirm") {
            $("#password_confirm").addClass("is-invalid");
            $("#password-confirm-error").removeClass("d-none").text(message);
        }
    }

    $("#guest-gp-form").appForm({
        isModal: false,
        onSubmit: function () {
            $("#phone_local, #emergency_local").each(function () {
                var $input = $(this);
                $input.val(String($input.val() || "").replace(/\D/g, "").slice(0, 15));
            });

            clearErrors();
            $(".ggp-card").addClass("ggp-submitting");
            appLoader.show();
        },
        onSuccess: function (res) {
            appLoader.hide();
            $(".ggp-card").removeClass("ggp-submitting");

            appAlert.success(res.message || <?php echo json_encode(app_lang("submitted_successfully")); ?>, {duration: 10000});
            $("#guest-gp-form")[0].reset();
            $("#phone_country_code, #emergency_country_code").val("+968").trigger("change");
            $("#otp_channel").val("phone").trigger("change");
        },
        onError: function (result) {
            appLoader.hide();
            $(".ggp-card").removeClass("ggp-submitting");

            var res = (result && result.responseJSON) ? result.responseJSON : result;

            if (res && res.errors) {
                if (res.errors.email) showError("email", res.errors.email);
                if (res.errors.password) showError("password", res.errors.password);
                if (res.errors.password_confirm) showError("password_confirm", res.errors.password_confirm);
                return false;
            }

            appAlert.error((res && res.message) ? res.message : <?php echo json_encode(app_lang("something_went_wrong")); ?>);
            return false;
        }
    });

    if (window.feather) feather.replace();
});
</script>
