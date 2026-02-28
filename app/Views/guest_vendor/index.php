<div id="page-content" class="page-wrapper clearfix gv-page">
    <style>
        /* =========================
           Guest Vendor – Pro UI
        ========================== */
        .gv-page {
            --gv-radius: 18px;
            --gv-shadow: 0 10px 30px rgba(16, 24, 40, .08);
            --gv-border: rgba(15, 23, 42, .08);
        }

        .gv-shell {
            max-width: 1100px;
            margin: 0 auto;
        }

        .gv-card {
            border: 1px solid var(--gv-border);
            border-radius: var(--gv-radius);
            box-shadow: var(--gv-shadow);
            overflow: hidden;
            background: #fff;
            transform: translateY(12px);
            opacity: 0;
            transition: transform .6s ease, opacity .6s ease;
        }

        .gv-ready .gv-card {
            transform: translateY(0);
            opacity: 1;
        }

        .gv-hero {
            padding: 26px 26px 18px;
            background:
                radial-gradient(900px 260px at 20% 0%, rgba(59, 130, 246, .20), transparent 60%),
                radial-gradient(700px 220px at 85% 30%, rgba(34, 197, 94, .18), transparent 55%),
                linear-gradient(180deg, rgba(15, 23, 42, .04), rgba(15, 23, 42, 0));
            border-bottom: 1px solid var(--gv-border);
        }

        .gv-hero h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.2px;
            color: #0f172a;
        }

        .gv-hero p {
            margin: 6px 0 0;
            color: #475569;
            font-size: 13px;
        }

        .gv-badges {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .gv-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid var(--gv-border);
            background: rgba(255, 255, 255, .6);
            font-size: 12px;
            color: #334155;
            backdrop-filter: blur(6px);
        }

        .gv-badge i {
            width: 14px;
            height: 14px;
        }

        .gv-body {
            padding: 22px 26px 10px;
        }

        .gv-section {
            border: 1px solid var(--gv-border);
            border-radius: 16px;
            padding: 16px 16px 6px;
            background: #fff;
            margin-bottom: 14px;
        }

        .gv-section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .gv-section-title h5 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .gv-section-title .dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .9);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .16);
        }

        .gv-help {
            margin: 0;
            color: #64748b;
            font-size: 12px;
        }

        /* Form polish */
        .gv-form .form-group {
            margin-bottom: 14px;
        }

        .gv-form label {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .gv-form .form-control,
        .gv-form .select2-container--default .select2-selection--single {
            border-radius: 12px !important;
            border: 1px solid rgba(15, 23, 42, .14) !important;
            height: 44px;
            transition: box-shadow .2s ease, border-color .2s ease, transform .15s ease;
        }

        .gv-form .form-control:focus {
            border-color: rgba(59, 130, 246, .55) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .15) !important;
        }

        .gv-form .select2-container--default .select2-selection--single {
            display: flex;
            align-items: center;
            padding: 0 12px;
        }

        .gv-form .select2-container--default .select2-selection__arrow {
            height: 42px;
        }

        .gv-form .select2-container {
            width: 100% !important;
        }

        .gv-divider {
            height: 1px;
            background: rgba(15, 23, 42, .08);
            margin: 10px 0 14px;
        }

        /* Footer / submit bar */
        .gv-footer {
            padding: 14px 26px;
            border-top: 1px solid var(--gv-border);
            background: rgba(255, 255, 255, .78);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .gv-footer .note {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .gv-btn {
            border-radius: 12px;
            height: 44px;
            padding: 0 16px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .gv-btn .spinner {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, .55);
            border-top-color: rgba(255, 255, 255, 1);
            animation: gvSpin .8s linear infinite;
            display: none;
        }

        .gv-submitting .gv-btn .spinner {
            display: inline-block;
        }

        .gv-submitting .gv-btn .btn-text {
            opacity: .9;
        }

        .gv-submitting .gv-btn {
            pointer-events: none;
            opacity: .95;
        }

        @keyframes gvSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Error animation */
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
            border-color: rgba(239, 68, 68, .7) !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, .12) !important;
        }

        /* Micro fade-in for sections */
        .gv-section {
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .5s ease, transform .5s ease;
        }

        .gv-ready .gv-section {
            opacity: 1;
            transform: translateY(0);
        }

        .gv-ready .gv-section:nth-child(1) {
            transition-delay: .05s;
        }

        .gv-ready .gv-section:nth-child(2) {
            transition-delay: .10s;
        }

        .gv-ready .gv-section:nth-child(3) {
            transition-delay: .15s;
        }
    </style>

    <div class="gv-shell">
        <div class="card gv-card">
            <div class="gv-hero">
                <h1>Guest Vendor Application</h1>
                <p>Fill in the details below. Once submitted, our team will review your application.</p>

                <div class="gv-badges">
                    <span class="gv-badge"><i data-feather="shield"></i> Secure submission</span>
                    <span class="gv-badge"><i data-feather="clock"></i> Quick review</span>
                    <span class="gv-badge"><i data-feather="check-circle"></i> Clear validation</span>
                </div>
            </div>

            <?php echo form_open(
                get_uri("guest_vendor/save"),
                [
                    "id"    => "guest-vendor-form",
                    "class" => "general-form gv-form",
                    "role"  => "form",
                    "enctype" => "multipart/form-data" // 👈 IMPORTANT for file upload
                ]
            ); ?>


            <div class="gv-body">
                <input type="hidden" name="id" value="" />

                <!-- Section 1: Vendor info -->
                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span>Vendor Information</h5>
                        <p class="gv-help">Company profile details</p>
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

                        <div class="col-lg-12">
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
                    </div>
                </div>

                <!-- Section 2: Location -->
                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span>Location</h5>
                        <p class="gv-help">Select your country, region, and city</p>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
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

                        <div class="col-lg-4">
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

                        <div class="col-lg-4">
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

                <!-- Section 3: Login user -->
                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span>Login User</h5>
                        <p class="gv-help">Account details for vendor portal access</p>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
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

                        <div class="col-lg-4">
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

                        <div class="col-lg-4">
                            <div class="form-group">
                                <label for="password"><?php echo app_lang('password'); ?></label>
                                <div class="input-group">
                                    <?php
                                    echo form_password([
                                        "id" => "password",
                                        "name" => "password",
                                        "value" => "",
                                        "class" => "form-control",
                                        "placeholder" => app_lang('password'),
                                        "autocomplete" => "new-password"
                                    ]);
                                    ?>
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-password" style="border-radius: 12px;">
                                        <i data-feather="eye" class="icon-16"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Use a strong password for better security.</small>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- Section 4: Documents -->
                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span>Documents</h5>
                        <p class="gv-help">
                            Upload at least one company document. Your application will be reviewed based on these.
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
                                    "vendor_document_type_id",
                                    $vendor_document_types_dropdown,
                                    "",
                                    "class='select2 validate-hidden' id='vendor_document_type_id'
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
                                    "name" => "file", // 👈 name must be 'file' to match controller
                                    "class" => "form-control",
                                    "data-rule-required" => true,
                                    "data-msg-required"  => app_lang('field_required')
                                ]);
                                ?>
                                <small class="text-muted">
                                    Allowed formats: PDF, image files, etc.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Optional dates (if you want them) -->
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="issued_at"><?php echo app_lang('issued_at'); ?></label>
                                <?php
                                echo form_input([
                                    "id"   => "issued_at",
                                    "name" => "issued_at",
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
                                    "name" => "expires_at",
                                    "type" => "date",
                                    "class" => "form-control",
                                ]);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="gv-footer">
                <p class="note mb0">
                    By submitting, you confirm the information is accurate and you agree to our review process.
                </p>

                <button type="submit" class="btn btn-primary gv-btn">
                    <span class="spinner"></span>
                    <span class="btn-text"><?php echo app_lang('save'); ?></span>
                    <i data-feather="arrow-right" class="icon-16"></i>
                </button>
            </div>

            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        // Page entrance animation
        setTimeout(function() {
            $(".gv-page").addClass("gv-ready");
            if (window.feather) feather.replace();
        }, 60);

        // Select2 init
        $("#vendor_group_id, #country_id, #region_id, #city_id, #vendor_document_type_id").select2({
            width: "100%",
            placeholder: "",
            allowClear: true
        });

        // Password show/hide
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

            $("#email").removeClass("is-invalid gv-shake");
            $("#user_email").removeClass("is-invalid gv-shake");
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
        }

        $("#email").on("input", function() {
            $("#email").removeClass("is-invalid gv-shake");
            $("#vendor-email-error").addClass("d-none").text("");
        });

        $("#user_email").on("input", function() {
            $("#user_email").removeClass("is-invalid gv-shake");
            $("#user-email-error").addClass("d-none").text("");
        });

        // Cascading dropdowns
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

        // appForm
        $("#guest-vendor-form").appForm({
            isModal: false,
            onSubmit: function() {
                clearErrors();
                $(".gv-card").addClass("gv-submitting");
                appLoader.show();
            },
            onSuccess: function(res) {
                appLoader.hide();
                $(".gv-card").removeClass("gv-submitting");

                appAlert.success(res.message || "Submitted", {
                    duration: 10000
                });

                $("#guest-vendor-form")[0].reset();
                $("#vendor_group_id, #country_id, #region_id, #city_id").val("").trigger("change");

                // subtle success “reveal”
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

                    if (res.errors.email || res.errors.user_email) {
                        return false;
                    }
                }

                if (res && res.field && res.message) {
                    showError(res.field, res.message);
                    return false;
                }

                appAlert.error((res && res.message) ? res.message : "Error occurred");
                return false;
            }
        });

        if (window.feather) feather.replace();
    });
</script>