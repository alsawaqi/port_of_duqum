<div id="page-content" class="page-wrapper clearfix ggp-page pod-gate-pass-public">
    <style>
        .ggp-page {
            --ggp-navy: #0b1f3a;
            --ggp-blue: #1d4ed8;
            --ggp-cyan: #0891b2;
            --ggp-teal: #0f766e;
            --ggp-amber: #d97706;
            --ggp-ink: #102033;
            --ggp-muted: #5f6f84;
            --ggp-border: rgba(15, 35, 62, .12);
            --ggp-soft: #f4f8fb;
            padding: 30px 16px 44px;
            background:
                linear-gradient(115deg, rgba(8, 145, 178, .12), rgba(15, 118, 110, .04) 34%, rgba(217, 119, 6, .08) 100%),
                linear-gradient(180deg, #f8fbfc 0%, #eef5f7 100%);
            min-height: calc(100vh - 64px);
        }

        .ggp-shell {
            max-width: 1080px;
            margin: 0 auto;
        }

        .ggp-card {
            overflow: hidden;
            border: 1px solid var(--ggp-border);
            border-radius: 8px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 18px 46px rgba(11, 31, 58, .13);
            opacity: 0;
            transform: translateY(12px);
            transition: opacity .5s ease, transform .5s ease;
        }

        .ggp-ready .ggp-card {
            opacity: 1;
            transform: translateY(0);
        }

        .ggp-hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(290px, .75fr);
            gap: 28px;
            padding: 28px;
            color: #fff;
            background:
                linear-gradient(135deg, rgba(11, 31, 58, .97), rgba(12, 76, 101, .94) 52%, rgba(15, 118, 110, .92)),
                linear-gradient(90deg, rgba(255, 255, 255, .06), rgba(255, 255, 255, 0));
        }

        .ggp-hero::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: 0;
            width: 42%;
            height: 100%;
            opacity: .18;
            background:
                linear-gradient(135deg, transparent 0 42%, rgba(255, 255, 255, .85) 42% 43%, transparent 43% 58%, rgba(255, 255, 255, .75) 58% 59%, transparent 59%),
                repeating-linear-gradient(90deg, rgba(255, 255, 255, .24) 0 1px, transparent 1px 34px);
            pointer-events: none;
        }

        .ggp-hero-main,
        .ggp-process {
            position: relative;
            z-index: 1;
        }

        .ggp-kicker {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
            color: rgba(255, 255, 255, .78);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .ggp-kicker-mark {
            width: 28px;
            height: 2px;
            border-radius: 999px;
            background: var(--ggp-amber);
        }

        .ggp-hero h1 {
            margin: 0;
            max-width: 680px;
            color: #fff;
            font-size: 30px;
            line-height: 1.18;
            font-weight: 850;
            letter-spacing: 0;
        }

        .ggp-hero p {
            margin: 10px 0 0;
            max-width: 760px;
            color: rgba(255, 255, 255, .82);
            font-size: 14px;
            line-height: 1.7;
        }

        .ggp-alert-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            max-width: 760px;
            margin-top: 16px;
            padding: 12px 14px;
            border: 1px solid rgba(255, 255, 255, .20);
            border-radius: 8px;
            background: rgba(255, 255, 255, .10);
            color: rgba(255, 255, 255, .86);
            backdrop-filter: blur(8px);
            font-size: 12px;
            line-height: 1.55;
        }

        .ggp-alert-note svg {
            flex: 0 0 auto;
            margin-top: 2px;
            color: #fbbf24;
        }

        .ggp-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
        }

        .ggp-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 0 12px;
            border: 1px solid rgba(255, 255, 255, .20);
            border-radius: 999px;
            background: rgba(255, 255, 255, .10);
            color: rgba(255, 255, 255, .90);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .ggp-badge svg {
            width: 15px;
            height: 15px;
        }

        .ggp-process {
            align-self: stretch;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            background: rgba(255, 255, 255, .10);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12);
            backdrop-filter: blur(10px);
            padding: 16px;
        }

        .ggp-process-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            color: #fff;
            font-size: 13px;
            font-weight: 850;
        }

        .ggp-process-logo {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #fff;
        }

        .ggp-process-logo img {
            display: block;
            max-width: 30px;
            max-height: 30px;
        }

        .ggp-process-list {
            display: grid;
            gap: 10px;
        }

        .ggp-process-step {
            display: grid;
            grid-template-columns: 30px 1fr;
            gap: 10px;
            align-items: center;
            min-height: 40px;
            color: rgba(255, 255, 255, .86);
            font-size: 12px;
            font-weight: 700;
        }

        .ggp-step-no {
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: 12px;
            font-weight: 850;
        }

        .ggp-body {
            padding: 24px 28px 8px;
        }

        .ggp-section {
            --section-color: var(--ggp-cyan);
            display: grid;
            grid-template-columns: 210px minmax(0, 1fr);
            gap: 22px;
            padding: 22px 0;
            border-top: 1px solid rgba(15, 35, 62, .10);
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .45s ease, transform .45s ease;
        }

        .ggp-section:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .ggp-ready .ggp-section {
            opacity: 1;
            transform: translateY(0);
        }

        .ggp-ready .ggp-section:nth-child(1) {
            transition-delay: .05s;
        }

        .ggp-ready .ggp-section:nth-child(2) {
            transition-delay: .10s;
        }

        .ggp-ready .ggp-section:nth-child(3) {
            transition-delay: .15s;
        }

        .ggp-section-meta {
            padding-inline-start: 14px;
            border-inline-start: 4px solid var(--section-color);
        }

        .ggp-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .ggp-section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: color-mix(in srgb, var(--section-color) 14%, white);
            color: var(--section-color);
        }

        .ggp-section-icon svg {
            width: 17px;
            height: 17px;
        }

        .ggp-section-title h5 {
            margin: 0;
            color: var(--ggp-ink);
            font-size: 15px;
            font-weight: 850;
            letter-spacing: 0;
        }

        .ggp-help,
        .ggp-form small {
            margin: 0;
            color: var(--ggp-muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .ggp-form .form-group {
            margin-bottom: 16px;
        }

        .ggp-form label {
            margin-bottom: 7px;
            color: var(--ggp-ink);
            font-size: 13px;
            font-weight: 800;
        }

        .ggp-form .form-control {
            min-height: 44px;
            border: 1px solid rgba(15, 35, 62, .16) !important;
            border-radius: 8px !important;
            background: #fbfcfd;
            color: var(--ggp-ink);
            transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
        }

        .ggp-form .form-control:focus {
            border-color: rgba(8, 145, 178, .58) !important;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(8, 145, 178, .14) !important;
        }

        .ggp-form .select2-container .select2-selection {
            min-height: 44px;
            border-color: rgba(15, 35, 62, .16);
            border-radius: 8px;
            background: #fbfcfd;
        }

        .ggp-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            color: var(--ggp-ink);
        }

        .ggp-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
        }

        .ggp-phone-grid {
            display: grid;
            grid-template-columns: minmax(210px, .8fr) minmax(220px, 1.2fr);
            gap: 12px;
        }

        .ggp-password-wrap .input-group {
            flex-wrap: nowrap;
        }

        .ggp-password-wrap .form-control {
            border-start-end-radius: 0 !important;
            border-end-end-radius: 0 !important;
        }

        .ggp-password-toggle {
            min-width: 44px;
            border-color: rgba(15, 35, 62, .16);
            border-start-start-radius: 0 !important;
            border-end-start-radius: 0 !important;
            border-start-end-radius: 8px !important;
            border-end-end-radius: 8px !important;
            background: #fff;
            color: var(--ggp-muted);
        }

        .ggp-password-toggle:hover,
        .ggp-password-toggle:focus {
            color: var(--ggp-teal);
            background: #eef9f7;
        }

        .ggp-form .is-invalid {
            border-color: rgba(220, 38, 38, .75) !important;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, .12) !important;
        }

        .ggp-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 28px;
            border-top: 1px solid rgba(15, 35, 62, .10);
            background: linear-gradient(180deg, rgba(244, 248, 251, .78), rgba(255, 255, 255, .95));
        }

        .ggp-footer .note {
            max-width: 650px;
            margin: 0;
            color: var(--ggp-muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .ggp-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 44px;
            padding: 0 18px;
            border: 0;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--ggp-blue), var(--ggp-teal)) !important;
            box-shadow: 0 12px 24px rgba(15, 118, 110, .26);
            color: #fff;
            font-weight: 850;
            white-space: nowrap;
        }

        .ggp-btn:hover,
        .ggp-btn:focus {
            box-shadow: 0 14px 28px rgba(15, 118, 110, .34);
        }

        .ggp-btn .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .58);
            border-top-color: #fff;
            border-radius: 999px;
            animation: ggpSpin .8s linear infinite;
        }

        .ggp-submitting .ggp-btn {
            pointer-events: none;
            opacity: .94;
        }

        .ggp-submitting .ggp-btn .spinner {
            display: inline-block;
        }

        @keyframes ggpSpin {
            to {
                transform: rotate(360deg);
            }
        }

        html[dir="rtl"] .ggp-btn svg {
            transform: scaleX(-1);
        }

        @media (max-width: 991px) {
            .ggp-page {
                padding: 20px 12px 36px;
            }

            .ggp-hero {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 24px;
            }

            .ggp-hero::after {
                width: 100%;
                opacity: .10;
            }

            .ggp-hero h1 {
                font-size: 25px;
            }

            .ggp-body {
                padding: 20px 22px 4px;
            }

            .ggp-section {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .ggp-section-meta {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 575px) {
            .ggp-hero {
                padding: 18px;
            }

            .ggp-hero h1 {
                font-size: 21px;
            }

            .ggp-hero p {
                font-size: 13px;
                line-height: 1.55;
            }

            .ggp-alert-note {
                margin-top: 12px;
                padding: 10px 11px;
                line-height: 1.45;
            }

            .ggp-badge {
                min-height: 30px;
                justify-content: flex-start;
                white-space: normal;
                font-size: 11px;
            }

            .ggp-process {
                padding: 12px;
            }

            .ggp-process-head {
                margin-bottom: 10px;
            }

            .ggp-process-logo {
                width: 34px;
                height: 34px;
            }

            .ggp-process-list {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .ggp-process-step {
                grid-template-columns: 26px minmax(0, 1fr);
                gap: 8px;
                min-height: 32px;
                font-size: 11px;
            }

            .ggp-step-no {
                width: 26px;
                height: 26px;
                font-size: 11px;
            }

            .ggp-body {
                padding: 16px 16px 0;
            }

            .ggp-phone-grid {
                grid-template-columns: 1fr;
            }

            .ggp-footer {
                flex-direction: column;
                align-items: stretch;
                padding: 16px;
            }

            .ggp-btn {
                width: 100%;
                white-space: normal;
            }
        }
    </style>

    <div class="ggp-shell">
        <div class="ggp-card">
            <div class="ggp-hero">
                <div class="ggp-hero-main">
                    <div class="ggp-kicker"><span class="ggp-kicker-mark"></span><?php echo app_lang("gate_pass_access_portal"); ?></div>
                    <h1><?php echo app_lang("gate_pass_account_application"); ?></h1>
                    <p><?php echo app_lang("gate_pass_account_application_subtitle"); ?></p>

                    <div class="ggp-alert-note">
                        <i data-feather="info" class="icon-16"></i>
                        <span><?php echo app_lang("gate_pass_signup_vs_gate_pass_account"); ?></span>
                    </div>

                    <div class="ggp-badges">
                        <span class="ggp-badge"><i data-feather="shield"></i> <?php echo app_lang("gate_pass_secure_submission"); ?></span>
                        <span class="ggp-badge"><i data-feather="zap"></i> <?php echo app_lang("gate_pass_fast_approval_workflow"); ?></span>
                        <span class="ggp-badge"><i data-feather="check-circle"></i> <?php echo app_lang("gate_pass_smart_validation"); ?></span>
                    </div>
                </div>

                <div class="ggp-process" aria-label="<?php echo app_lang("gate_pass_registration_steps"); ?>">
                    <div class="ggp-process-head">
                        <span class="ggp-process-logo"><img src="<?php echo get_logo_url(); ?>" alt=""></span>
                        <span><?php echo app_lang("gate_pass_registration_steps"); ?></span>
                    </div>
                    <div class="ggp-process-list">
                        <div class="ggp-process-step"><span class="ggp-step-no">1</span><?php echo app_lang("gate_pass_step_account"); ?></div>
                        <div class="ggp-process-step"><span class="ggp-step-no">2</span><?php echo app_lang("gate_pass_step_contact"); ?></div>
                        <div class="ggp-process-step"><span class="ggp-step-no">3</span><?php echo app_lang("gate_pass_step_request"); ?></div>
                        <div class="ggp-process-step"><span class="ggp-step-no">4</span><?php echo app_lang("gate_pass_step_track"); ?></div>
                    </div>
                </div>
            </div>

            <?php echo form_open(
                get_uri("guest_gate_pass/save"),
                [
                    "id"    => "guest-gp-form",
                    "class" => "general-form ggp-form",
                    "role"  => "form"
                ]
            ); ?>

            <div class="ggp-body">
                <div class="ggp-section" style="--section-color: var(--ggp-cyan);">
                    <div class="ggp-section-meta">
                        <div class="ggp-section-title">
                            <span class="ggp-section-icon"><i data-feather="user"></i></span>
                            <h5><?php echo app_lang("gate_pass_account_information"); ?></h5>
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

                <div class="ggp-section" style="--section-color: var(--ggp-teal);">
                    <div class="ggp-section-meta">
                        <div class="ggp-section-title">
                            <span class="ggp-section-icon"><i data-feather="smartphone"></i></span>
                            <h5><?php echo app_lang("gate_pass_contact_security"); ?></h5>
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
                            <div class="col-lg-4">
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
                                        <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
                                        <button class="btn btn-outline-secondary ggp-password-toggle" type="button" id="toggle-password" aria-label="<?php echo app_lang("toggle_password_visibility"); ?>">
                                            <i data-feather="eye" class="icon-16"></i>
                                        </button>
                                    </div>
                                    <div id="password-error" class="alert alert-danger mt10 d-none"></div>
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
                                <h5><?php echo app_lang("gate_pass_verification"); ?></h5>
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
