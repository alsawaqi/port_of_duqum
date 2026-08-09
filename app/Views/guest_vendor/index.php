<div id="page-content" class="page-wrapper clearfix gv-page">
    <style>
        .gv-page {
            --gv-ink: #102a43;
            --gv-muted: #5f6f80;
            --gv-teal: #0f766e;
            --gv-teal-dark: #0b4f51;
            --gv-green: #2f855a;
            --gv-amber: #c77700;
            --gv-blue: #2563eb;
            --gv-red: #dc2626;
            --gv-paper: #ffffff;
            --gv-soft: #f3f7f7;
            --gv-border: rgba(16, 42, 67, .12);
            --gv-shadow: 0 24px 60px rgba(16, 42, 67, .14);
            min-height: calc(100vh - 70px);
            padding: 30px 18px 48px;
            overflow-x: hidden;
            background:
                linear-gradient(180deg, #e8f4f1 0, #f6f9fb 260px, #f8fafc 100%),
                repeating-linear-gradient(135deg, rgba(15, 118, 110, .07) 0, rgba(15, 118, 110, .07) 1px, transparent 1px, transparent 18px);
        }

        .gv-shell {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        .gv-card {
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            border: 1px solid rgba(16, 42, 67, .10);
            border-radius: 20px;
            background: var(--gv-paper);
            box-shadow: var(--gv-shadow);
            opacity: 0;
            transform: translateY(12px);
            transition: opacity .55s ease, transform .55s ease;
        }

        .gv-ready .gv-card {
            opacity: 1;
            transform: translateY(0);
        }

        .gv-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 24px;
            align-items: stretch;
            padding: 30px;
            max-width: 100%;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(135deg, #083344 0%, #0f766e 58%, #b7791f 100%);
        }

        .gv-hero-main {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 224px;
        }

        .gv-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            color: rgba(255, 255, 255, .82);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .gv-kicker-line {
            width: 32px;
            height: 3px;
            border-radius: 999px;
            background: #facc15;
        }

        .gv-hero h1 {
            max-width: 760px;
            margin: 0;
            color: #fff;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: 0;
            overflow-wrap: break-word;
        }

        .gv-hero p {
            max-width: 760px;
            margin: 10px 0 0;
            color: rgba(255, 255, 255, .84);
            font-size: 14px;
            line-height: 1.65;
        }

        .gv-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 20px;
        }

        .gv-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 7px 11px;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 999px;
            background: rgba(255, 255, 255, .12);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            backdrop-filter: blur(8px);
        }

        .gv-badge i,
        .gv-badge svg {
            width: 15px;
            height: 15px;
        }

        .gv-process {
            display: flex;
            flex-direction: column;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, .20);
            border-radius: 18px;
            background: rgba(255, 255, 255, .12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .15);
            padding: 20px;
            backdrop-filter: blur(10px);
        }

        .gv-process-title {
            margin-bottom: 14px;
            color: rgba(255, 255, 255, .86);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .gv-process-list {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .gv-process-step {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            min-height: 42px;
            padding: 9px 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .13);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: break-word;
        }

        .gv-process-no {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 26px;
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: #facc15;
            color: #173b32;
            font-size: 12px;
            font-weight: 900;
        }

        .gv-body {
            padding: 24px 28px 12px;
            background: var(--gv-soft);
        }

        .gv-section {
            --section-color: var(--gv-teal);
            position: relative;
            margin-bottom: 16px;
            padding: 18px 18px 8px;
            border: 1px solid var(--gv-border);
            border-left: 5px solid var(--section-color);
            border-radius: 16px;
            background: var(--gv-paper);
            box-shadow: 0 10px 28px rgba(16, 42, 67, .06);
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .45s ease, transform .45s ease, border-color .2s ease, box-shadow .2s ease;
        }

        .gv-section:nth-child(2) {
            --section-color: var(--gv-blue);
        }

        .gv-section:nth-child(3) {
            --section-color: var(--gv-green);
        }

        .gv-section:nth-child(4) {
            --section-color: var(--gv-amber);
        }

        .gv-ready .gv-section {
            opacity: 1;
            transform: translateY(0);
        }

        .gv-ready .gv-section:nth-child(1) {
            transition-delay: .04s;
        }

        .gv-ready .gv-section:nth-child(2) {
            transition-delay: .08s;
        }

        .gv-ready .gv-section:nth-child(3) {
            transition-delay: .12s;
        }

        .gv-ready .gv-section:nth-child(4) {
            transition-delay: .16s;
        }

        .gv-section:hover {
            border-color: rgba(15, 118, 110, .22);
            box-shadow: 0 14px 34px rgba(16, 42, 67, .09);
        }

        .gv-section-title {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
        }

        .gv-section-title h5 {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            color: var(--gv-ink);
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .gv-section-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #ecf8f6;
            background: color-mix(in srgb, var(--section-color) 14%, white);
            color: var(--section-color);
        }

        .gv-section-icon i,
        .gv-section-icon svg {
            width: 17px;
            height: 17px;
        }

        .gv-help {
            max-width: 360px;
            margin: 4px 0 0;
            color: var(--gv-muted);
            font-size: 12px;
            line-height: 1.45;
            text-align: end;
        }

        .gv-form .form-group {
            margin-bottom: 15px;
        }

        .gv-form label {
            margin-bottom: 7px;
            color: var(--gv-ink);
            font-size: 13px;
            font-weight: 700;
        }

        .gv-form .form-control,
        .gv-form .select2-container--default .select2-selection--single {
            width: 100%;
            max-width: 100%;
            min-height: 44px;
            border: 1px solid rgba(16, 42, 67, .16) !important;
            border-radius: 12px !important;
            background-color: #fbfcfd;
            color: var(--gv-ink);
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        .gv-form input[type="file"].form-control {
            height: auto;
            padding: 9px 12px;
        }

        .gv-form .input-group .form-control {
            width: 1%;
            min-width: 0;
            flex: 1 1 auto;
        }

        .gv-form .form-control:focus {
            border-color: rgba(15, 118, 110, .70) !important;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, .13) !important;
        }

        .gv-form .select2-container {
            width: 100% !important;
        }

        .gv-form .select2-container--default .select2-selection--single {
            display: flex;
            align-items: center;
            padding: 0 12px;
        }

        .gv-form .select2-container--default .select2-selection__rendered {
            width: 100%;
            padding: 0;
            color: var(--gv-ink);
            line-height: 42px;
        }

        .gv-form .select2-container--default .select2-selection__arrow {
            height: 42px;
        }

        .gv-phone-grid {
            display: grid;
            grid-template-columns: minmax(190px, .8fr) minmax(220px, 1.2fr);
            gap: 12px;
        }

        .gv-password-toggle {
            flex: 0 0 46px;
            min-width: 46px;
            border-color: rgba(16, 42, 67, .16);
            border-radius: 12px;
        }

        .gv-divider {
            height: 1px;
            margin: 8px 0 16px;
            background: linear-gradient(90deg, rgba(15, 118, 110, .22), rgba(16, 42, 67, .06));
        }

        .gv-document-row {
            margin-bottom: 13px;
            padding: 14px 14px 2px;
            border: 1px dashed rgba(199, 119, 0, .32);
            border-radius: 14px;
            background: #fffaf0;
        }

        .gv-document-row-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 9px;
        }

        .gv-document-row-title {
            color: var(--gv-ink);
            font-size: 13px;
            font-weight: 800;
        }

        .gv-document-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 999px;
            background: rgba(220, 38, 38, .10);
            color: #b91c1c;
        }

        .gv-add-document {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            border-radius: 12px;
            border-color: rgba(199, 119, 0, .28);
            color: #8a5200;
            font-weight: 800;
        }

        .gv-footer {
            position: sticky;
            bottom: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 28px;
            border-top: 1px solid rgba(16, 42, 67, .10);
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(12px);
        }

        .gv-footer .note {
            max-width: 720px;
            margin: 0;
            color: var(--gv-muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .gv-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 44px;
            padding: 0 18px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--gv-teal) 0%, var(--gv-green) 100%) !important;
            box-shadow: 0 12px 24px rgba(15, 118, 110, .28);
            font-weight: 800;
            white-space: nowrap;
        }

        .gv-btn:hover,
        .gv-btn:focus {
            box-shadow: 0 14px 28px rgba(15, 118, 110, .34);
        }

        .gv-btn .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .55);
            border-top-color: #fff;
            border-radius: 999px;
            animation: gvSpin .8s linear infinite;
        }

        .gv-submitting .gv-btn {
            pointer-events: none;
            opacity: .94;
        }

        .gv-submitting .gv-btn .spinner {
            display: inline-block;
        }

        @keyframes gvSpin {
            to {
                transform: rotate(360deg);
            }
        }

        .gv-shake {
            animation: gvShake .28s ease-in-out 0s 2;
        }

        @keyframes gvShake {
            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-6px);
            }

            75% {
                transform: translateX(6px);
            }
        }

        .gv-form .is-invalid {
            border-color: rgba(220, 38, 38, .72) !important;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, .12) !important;
        }

        html[dir="rtl"] .gv-section {
            border-right: 5px solid var(--section-color);
            border-left: 1px solid var(--gv-border);
        }

        html[dir="rtl"] .gv-help {
            text-align: start;
        }

        html[dir="rtl"] .gv-btn svg {
            transform: scaleX(-1);
        }

        @media (max-width: 991px) {
            .gv-page {
                padding: 18px 12px 34px;
            }

            .gv-hero {
                grid-template-columns: 1fr;
                padding: 24px;
            }

            .gv-hero-main {
                min-height: auto;
            }

            .gv-hero h1 {
                font-size: 25px;
            }

            .gv-process {
                padding: 16px;
            }

            .gv-body {
                padding: 18px;
            }

            .gv-section-title {
                display: block;
            }

            .gv-help {
                max-width: none;
                margin-top: 8px;
                text-align: start;
            }
        }

        @media (max-width: 575px) {
            .gv-hero {
                padding: 20px;
            }

            .gv-hero h1 {
                font-size: 21px;
            }

            .gv-hero p {
                font-size: 13px;
            }

            .gv-badge {
                min-height: 32px;
                font-size: 11px;
            }

            .gv-body {
                padding: 14px;
            }

            .gv-section {
                padding: 15px 14px 5px;
            }

            .gv-phone-grid {
                grid-template-columns: 1fr;
            }

            .gv-footer {
                position: static;
                flex-direction: column;
                align-items: stretch;
                padding: 14px;
            }

            .gv-btn {
                width: 100%;
                white-space: normal;
            }
        }
    </style>

    <div class="gv-shell">
        <div class="gv-card">
            <div class="gv-hero">
                <div class="gv-hero-main">
                    <div class="gv-kicker"><span class="gv-kicker-line"></span><?php echo app_lang("vendor_onboarding"); ?></div>
                    <h1><?php echo app_lang("guest_vendor_application"); ?></h1>
                    <p><?php echo app_lang("guest_vendor_application_subtitle"); ?></p>

                    <div class="gv-badges">
                        <span class="gv-badge"><i data-feather="shield"></i> <?php echo app_lang("secure_submission"); ?></span>
                        <span class="gv-badge"><i data-feather="clock"></i> <?php echo app_lang("quick_review"); ?></span>
                        <span class="gv-badge"><i data-feather="check-circle"></i> <?php echo app_lang("clear_validation"); ?></span>
                    </div>
                </div>

                <div class="gv-process" aria-label="<?php echo app_lang("vendor_registration_steps"); ?>">
                    <div class="gv-process-title"><?php echo app_lang("vendor_registration_steps"); ?></div>
                    <div class="gv-process-list">
                        <div class="gv-process-step"><span class="gv-process-no">1</span><?php echo app_lang("step_company_details"); ?></div>
                        <div class="gv-process-step"><span class="gv-process-no">2</span><?php echo app_lang("step_account_access"); ?></div>
                        <div class="gv-process-step"><span class="gv-process-no">3</span><?php echo app_lang("step_documents"); ?></div>
                        <div class="gv-process-step"><span class="gv-process-no">4</span><?php echo app_lang("step_procurement_review"); ?></div>
                    </div>
                </div>
            </div>

            <?php echo form_open(
                get_uri("guest_vendor/save"),
                [
                    "id"    => "guest-vendor-form",
                    "class" => "general-form gv-form",
                    "role"  => "form",
                    "enctype" => "multipart/form-data"
                ]
            ); ?>


            <div class="gv-body">
                <input type="hidden" name="id" value="" />

                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="gv-section-icon"><i data-feather="briefcase"></i></span><?php echo app_lang("vendor_information"); ?></h5>
                        <p class="gv-help"><?php echo app_lang("company_profile_details"); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="vendor_group_id"><?php echo app_lang('vendor_group'); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_dropdown(
                                    "vendor_group_id",
                                    $vendor_groups_dropdown,
                                    "",
                                    "class='select2 validate-hidden' id='vendor_group_id'
                                     data-rule-required='true'
                                     data-msg-required='" . app_lang('field_required') . "'"
                                );
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="vendor_name"><?php echo app_lang('vendor_name'); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_input([
                                    "id" => "vendor_name",
                                    "name" => "vendor_name",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('vendor_name'),
                                    "data-rule-required" => true,
                                    "data-msg-required" => app_lang('field_required')
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="email"><?php echo app_lang('email'); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_input([
                                    "id" => "email",
                                    "name" => "email",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('email'),
                                    "data-rule-required" => true,
                                    "data-msg-required" => app_lang('field_required'),
                                    "data-rule-email" => true,
                                    "data-msg-email" => app_lang('enter_valid_email')
                                ]);
                                ?>
                                <div id="vendor-email-error" class="alert alert-danger mt10 d-none"></div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="cr_number"><?php echo app_lang("cr_number"); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_input([
                                    "id" => "cr_number",
                                    "name" => "cr_number",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang("commercial_registration_number"),
                                    "data-rule-required" => true,
                                    "data-msg-required" => app_lang('field_required')
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <?php
                                $intl_dial_codes = $intl_dial_codes ?? [];
                                $default_dial = "+968";
                                ?>
                                <label for="phone_local"><?php echo app_lang('phone'); ?> <span class="text-danger">*</span></label>
                                <div class="gv-phone-grid">
                                    <select name="phone_country_code" id="phone_country_code" class="form-control" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" title="<?php echo app_lang("vendor_phone_country_code"); ?>">
                                        <?php foreach ($intl_dial_codes as $d) {
                                            $sel = ($d["code"] === $default_dial) ? " selected" : "";
                                            echo "<option value=\"" . esc($d["code"]) . "\"{$sel}>" . esc($d["country"] . " (" . $d["code"] . ")") . "</option>\n";
                                        } ?>
                                    </select>
                                    <?php
                                    echo form_input([
                                        "id" => "phone_local",
                                        "name" => "phone_local",
                                        "value" => "",
                                        "class" => "form-control gv-digits-only",
                                        "placeholder" => "71234567",
                                        "inputmode" => "numeric",
                                        "maxlength" => "15",
                                        "autocomplete" => "tel-national",
                                        "data-rule-required" => true,
                                        "data-msg-required" => app_lang('field_required'),
                                        "data-rule-digits" => true,
                                        "data-msg-digits" => app_lang("vendor_phone_digits_only")
                                    ]);
                                    ?>
                                </div>
                                <small class="text-muted"><?php echo app_lang("vendor_phone_entry_hint"); ?></small>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="contact_person"><?php echo app_lang("contact_person"); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_input([
                                    "id" => "contact_person",
                                    "name" => "contact_person",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang("primary_contact_name"),
                                    "data-rule-required" => true,
                                    "data-msg-required" => app_lang('field_required')
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="contact_designation"><?php echo app_lang("contact_designation"); ?></label>
                                <?php
                                echo form_input([
                                    "id" => "contact_designation",
                                    "name" => "contact_designation",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang("role_designation")
                                ]);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="gv-section-icon"><i data-feather="map-pin"></i></span><?php echo app_lang("location"); ?></h5>
                        <p class="gv-help"><?php echo app_lang("select_country_region_city"); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="country_id"><?php echo app_lang('country'); ?></label>
                                <?php
                                echo form_dropdown(
                                    "country_id",
                                    $countries_dropdown,
                                    "",
                                    "class='select2' id='country_id'"
                                );
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="region_id"><?php echo app_lang('region'); ?></label>
                                <?php
                                echo form_dropdown(
                                    "region_id",
                                    $regions_dropdown,
                                    "",
                                    "class='select2' id='region_id'"
                                );
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="city_id"><?php echo app_lang('city'); ?></label>
                                <?php
                                echo form_dropdown(
                                    "city_id",
                                    $cities_dropdown,
                                    "",
                                    "class='select2' id='city_id'"
                                );
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="gv-divider"></div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="address"><?php echo app_lang('address'); ?></label>
                                <?php
                                echo form_input([
                                    "id" => "address",
                                    "name" => "address",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('address')
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="po_box"><?php echo app_lang('po_box'); ?></label>
                                <?php
                                echo form_input([
                                    "id" => "po_box",
                                    "name" => "po_box",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('po_box')
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="postal_code"><?php echo app_lang('postal_code'); ?></label>
                                <?php
                                echo form_input([
                                    "id" => "postal_code",
                                    "name" => "postal_code",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('postal_code')
                                ]);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="gv-section-icon"><i data-feather="user-check"></i></span><?php echo app_lang("login_user"); ?></h5>
                        <p class="gv-help"><?php echo app_lang("vendor_login_user_help"); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="user_name"><?php echo app_lang('name'); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_input([
                                    "id" => "user_name",
                                    "name" => "user_name",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('name'),
                                    "data-rule-required" => true,
                                    "data-msg-required" => app_lang('field_required')
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="user_email"><?php echo app_lang('email'); ?> <span class="text-danger">*</span></label>
                                <?php
                                echo form_input([
                                    "id" => "user_email",
                                    "name" => "user_email",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('email'),
                                    "data-rule-required" => true,
                                    "data-msg-required" => app_lang('field_required'),
                                    "data-rule-email" => true,
                                    "data-msg-email" => app_lang('enter_valid_email')
                                ]);
                                ?>
                                <div id="user-email-error" class="alert alert-danger mt10 d-none"></div>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="password"><?php echo app_lang('password'); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <?php
                                    echo form_password([
                                        "id" => "password",
                                        "name" => "password",
                                        "value" => "",
                                        "class" => "form-control",
                                        "placeholder" => app_lang('password'),
                                        "autocomplete" => "current-password",
                                        "data-rule-required" => true,
                                        "data-msg-required" => app_lang('field_required')
                                    ]);
                                    ?>
                                    <button class="btn btn-outline-secondary gv-password-toggle" type="button" id="toggle-password" aria-label="<?php echo app_lang("toggle_password_visibility"); ?>">
                                        <i data-feather="eye" class="icon-16"></i>
                                    </button>
                                </div>
                                <small class="text-muted"><?php echo app_lang("vendor_password_reuse_hint"); ?></small>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label for="password_confirm"><?php echo app_lang('password_confirm'); ?></label>
                                <?php
                                echo form_password([
                                    "id" => "password_confirm",
                                    "name" => "password_confirm",
                                    "value" => "",
                                    "class" => "form-control",
                                    "placeholder" => app_lang('password_confirm'),
                                    "autocomplete" => "new-password",
                                    "data-rule-equalTo" => "#password",
                                    "data-msg-equalTo" => app_lang("passwords_do_not_match")
                                ]);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="gv-section-icon"><i data-feather="file-text"></i></span><?php echo app_lang("documents"); ?></h5>
                        <p class="gv-help">
                            <?php echo app_lang("guest_vendor_documents_help"); ?>
                        </p>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="vendor_document_type_id">
                                    <?php echo app_lang('document_type'); ?> <span class="text-danger">*</span>
                                </label>
                                <?php
                                echo form_dropdown(
                                    "vendor_document_type_id[]",
                                    $vendor_document_types_dropdown,
                                    "",
                                    "class='select2 validate-hidden gv-document-type' id='vendor_document_type_id'
                     data-rule-required='true'
                     data-msg-required='" . app_lang('field_required') . "'"
                                );
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="document_file">
                                    <?php echo app_lang('document'); ?> <span class="text-danger">*</span>
                                </label>
                                <?php
                                echo form_upload([
                                    "id"   => "document_file",
                                    "name" => "file",
                                    "class" => "form-control",
                                    "data-rule-required" => true,
                                    "data-msg-required"  => app_lang('field_required')
                                ]);
                                ?>
                                <small class="text-muted">
                                    <?php echo app_lang("allowed_document_formats"); ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="issued_at"><?php echo app_lang('issued_at'); ?></label>
                                <?php
                                echo form_input([
                                    "id"   => "issued_at",
                                    "name" => "issued_at[]",
                                    "type" => "date",
                                    "class" => "form-control",
                                ]);
                                ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="expires_at"><?php echo app_lang('expires_at'); ?></label>
                                <?php
                                echo form_input([
                                    "id"   => "expires_at",
                                    "name" => "expires_at[]",
                                    "type" => "date",
                                    "class" => "form-control",
                                ]);
                                ?>
                            </div>
                        </div>
                    </div>
                    <div id="guest-vendor-extra-documents"></div>
                    <button type="button" class="btn btn-default gv-add-document" data-gv-document-add>
                        <i data-feather="plus-circle" class="icon-16"></i> <?php echo app_lang("add_document"); ?>
                    </button>
                </div>

            </div>

            <div class="gv-footer">
                <?php echo view("signin/re_captcha"); ?>
                <p class="note mb0">
                    <?php echo app_lang("guest_vendor_submit_note"); ?>
                </p>

                <button type="submit" class="btn btn-primary gv-btn">
                    <span class="spinner"></span>
                    <span class="btn-text"><?php echo app_lang('submit_vendor_application'); ?></span>
                    <i data-feather="arrow-right" class="icon-16"></i>
                </button>
            </div>

            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        setTimeout(function() {
            $(".gv-page").addClass("gv-ready");
            if (window.feather) feather.replace();
        }, 60);

        $("#vendor_group_id, #country_id, #region_id, #city_id, #vendor_document_type_id").select2({
            width: "100%",
            placeholder: "",
            allowClear: true
        });

        $("#phone_country_code").select2({
            width: "100%"
        });

        $("#document_file").attr("name", "file[]");

        function bindDigitsOnly($el) {
            var strip = function() {
                var value = String($el.val() || "").replace(/\D/g, "").slice(0, 15);
                if (value !== $el.val()) {
                    $el.val(value);
                }
            };

            $el.on("input change blur", strip);
            $el.on("paste", function(e) {
                e.preventDefault();
                var text = (e.originalEvent || e).clipboardData.getData("text") || "";
                $el.val(text.replace(/\D/g, "").slice(0, 15));
            });
        }

        bindDigitsOnly($("#phone_local"));

        var documentTypeOptionsHtml = <?php
            $document_type_options_html = "";
            foreach ($vendor_document_types_dropdown as $value => $label) {
                $document_type_options_html .= "<option value=\"" . esc($value) . "\">" . esc($label) . "</option>";
            }
            echo json_encode($document_type_options_html);
        ?>;

        var documentIndex = 1;
        $("[data-gv-document-add]").on("click", function() {
            documentIndex++;
            var row = $(`
                <div class="gv-document-row" data-gv-document-row>
                    <div class="gv-document-row-header">
                        <span class="gv-document-row-title"><?php echo app_lang("document"); ?> ${documentIndex}</span>
                        <button type="button" class="gv-document-remove" data-gv-document-remove aria-label="<?php echo app_lang("remove"); ?>">
                            <i data-feather="x" class="icon-14"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label><?php echo app_lang('document_type'); ?> <span class="text-danger">*</span></label>
                                <select name="vendor_document_type_id[]" class="select2 validate-hidden gv-document-type" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>">
                                    ${documentTypeOptionsHtml}
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label><?php echo app_lang('document'); ?> <span class="text-danger">*</span></label>
                                <input type="file" name="file[]" class="form-control" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>">
                                <small class="text-muted"><?php echo app_lang("allowed_document_formats"); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label><?php echo app_lang('issued_at'); ?></label>
                                <input type="date" name="issued_at[]" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label><?php echo app_lang('expires_at'); ?></label>
                                <input type="date" name="expires_at[]" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            `);
            $("#guest-vendor-extra-documents").append(row);
            row.find(".gv-document-type").select2({
                width: "100%",
                placeholder: "",
                allowClear: true
            });
            if (window.feather) feather.replace();
        });

        $(document).on("click", "[data-gv-document-remove]", function() {
            $(this).closest("[data-gv-document-row]").remove();
        });

        $("#toggle-password").on("click", function() {
            const $pw = $("#password");
            const isText = $pw.attr("type") === "text";
            $pw.attr("type", isText ? "password" : "text");
            $(this).find("svg").remove();
            $(this).append(`<i data-feather="${isText ? "eye" : "eye-off"}" class="icon-16"></i>`);
            if (window.feather) feather.replace();
        });

        function clearErrors() {
            $("#vendor-email-error").addClass("d-none").text("");
            $("#user-email-error").addClass("d-none").text("");

            $("#email, #user_email, #cr_number, #phone_country_code, #phone_local, #password, #password_confirm").removeClass("is-invalid gv-shake");
        }

        function shake($el) {
            $el.removeClass("gv-shake");
            void $el[0].offsetWidth; // reflow
            $el.addClass("gv-shake");
        }

        function showError(field, message) {
            if (field === "email") {
                const $i = $("#email");
                $i.addClass("is-invalid");
                shake($i);
                $("#vendor-email-error").removeClass("d-none").text(message);
            }
            if (field === "user_email") {
                const $i = $("#user_email");
                $i.addClass("is-invalid");
                shake($i);
                $("#user-email-error").removeClass("d-none").text(message);
            }
            if (field !== "email" && field !== "user_email") {
                const $i = $("#" + field);
                if ($i.length) {
                    $i.addClass("is-invalid");
                    shake($i);
                }
                appAlert.error(message);
            }
        }

        $("#email").on("input", function() {
            $("#email").removeClass("is-invalid gv-shake");
            $("#vendor-email-error").addClass("d-none").text("");
        });

        $("#user_email").on("input", function() {
            $("#user_email").removeClass("is-invalid gv-shake");
            $("#user-email-error").addClass("d-none").text("");
        });

        $("#country_id").on("change", function() {
            var country_id = $(this).val() || 0;

            $("#region_id").html("<option value=''>- <?php echo app_lang('select_region'); ?> -</option>").trigger("change");
            $("#city_id").html("<option value=''>- <?php echo app_lang('select_city'); ?> -</option>").trigger("change");

            if (country_id) {
                $("#region_id").load("<?php echo get_uri('guest_vendor/get_regions_dropdown_by_country'); ?>/" + country_id, function() {
                    $("#region_id").trigger("change");
                });
            }
        });

        $("#region_id").on("change", function() {
            var region_id = $(this).val() || 0;

            $("#city_id").html("<option value=''>- <?php echo app_lang('select_city'); ?> -</option>").trigger("change");

            if (region_id) {
                $("#city_id").load("<?php echo get_uri('guest_vendor/get_cities_dropdown_by_region'); ?>/" + region_id, function() {});
            }
        });

        $("#guest-vendor-form").appForm({
            isModal: false,
            onSubmit: function() {
                $("#phone_local").val(String($("#phone_local").val() || "").replace(/\D/g, "").slice(0, 15));
                clearErrors();
                $(".gv-card").addClass("gv-submitting");
                appLoader.show();
            },
            onSuccess: function(res) {
                appLoader.hide();
                $(".gv-card").removeClass("gv-submitting");

                appAlert.success(res.message || <?php echo json_encode(app_lang("submitted_successfully")); ?>, {
                    duration: 10000
                });

                $("#guest-vendor-form")[0].reset();
                $("#vendor_group_id, #country_id, #region_id, #city_id, #vendor_document_type_id").val("").trigger("change");
                $("#phone_country_code").val("+968").trigger("change");
                $("#guest-vendor-extra-documents").empty();
                documentIndex = 1;

                $(".gv-section").css({
                    opacity: 0,
                    transform: "translateY(8px)"
                });
                setTimeout(function() {
                    $(".gv-section").css({
                        opacity: "",
                        transform: ""
                    });
                }, 120);
            },
            onError: function(result) {
                appLoader.hide();
                $(".gv-card").removeClass("gv-submitting");

                var res = (result && result.responseJSON) ? result.responseJSON : result;

                if (res && res.errors) {
                    if (res.errors.email) showError("email", res.errors.email);
                    if (res.errors.user_email) showError("user_email", res.errors.user_email);
                    if (res.errors.cr_number) showError("cr_number", res.errors.cr_number);
                    if (res.errors.phone_country_code) showError("phone_country_code", res.errors.phone_country_code);
                    if (res.errors.phone_local) showError("phone_local", res.errors.phone_local);
                    if (res.errors.password) showError("password", res.errors.password);
                    if (res.errors.password_confirm) showError("password_confirm", res.errors.password_confirm);

                    if (res.errors.email || res.errors.user_email || res.errors.cr_number || res.errors.phone_country_code || res.errors.phone_local || res.errors.password || res.errors.password_confirm) {
                        return false;
                    }
                }

                if (res && res.field && res.message) {
                    showError(res.field, res.message);
                    return false;
                }

                appAlert.error((res && res.message) ? res.message : <?php echo json_encode(app_lang("something_went_wrong")); ?>);
                return false;
            }
        });

        if (window.feather) feather.replace();
    });
</script>
