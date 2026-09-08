<div id="page-content" class="page-wrapper clearfix gv-page guest-registration">
<div class="gv-shell">
        <div class="gv-card">
            <header class="registration-heading">
                <h1><?php echo app_lang("guest_vendor_application"); ?></h1>
                <p><?php echo app_lang("guest_vendor_application_subtitle"); ?></p>
            </header>
            <div class="registration-layout">
            <?php echo view('includes/public/registration_navigation', ['registration_sections' => [['gv-company', 'step_company_details'], ['gv-location', 'location'], ['gv-account', 'step_account_access'], ['gv-documents', 'documents']]]); ?>

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

                <div class="gv-section" id="gv-company" tabindex="-1">
                    <div class="gv-section-title">
                        <h2>01. <span class="gv-section-icon"><i data-feather="briefcase"></i></span><?php echo app_lang("vendor_information"); ?></h2>
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

                <div class="gv-section" id="gv-location" tabindex="-1">
                    <div class="gv-section-title">
                        <h2>02. <span class="gv-section-icon"><i data-feather="map-pin"></i></span><?php echo app_lang("location"); ?></h2>
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

                <div class="gv-section" id="gv-account" tabindex="-1">
                    <div class="gv-section-title">
                        <h2>03. <span class="gv-section-icon"><i data-feather="user-check"></i></span><?php echo app_lang("login_user"); ?></h2>
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
                                <label for="user_mobile"><?php echo app_lang('vendor_login_mobile'); ?> <span class="text-danger">*</span></label>
                                <input id="user_mobile" name="user_mobile" type="tel" class="form-control" maxlength="30"
                                    autocomplete="tel" placeholder="+968 9XXXXXXX" data-rule-required="true"
                                    data-msg-required="<?php echo app_lang('field_required'); ?>">
                                <small class="text-muted"><?php echo app_lang('vendor_login_mobile_help'); ?></small>
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

                <div class="gv-section" id="gv-documents" tabindex="-1">
                    <div class="gv-section-title">
                        <h2>04. <span class="gv-section-icon"><i data-feather="file-text"></i></span><?php echo app_lang("documents"); ?></h2>
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

                <a class="registration-back" href="<?php echo get_uri('signin'); ?>"><i data-feather="arrow-left" class="icon-16"></i><?php echo app_lang('guest_back_signin'); ?></a>

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
                                <label for="document-type-${documentIndex}"><?php echo app_lang('document_type'); ?> <span class="text-danger">*</span></label>
                                <select id="document-type-${documentIndex}" name="vendor_document_type_id[]" class="select2 validate-hidden gv-document-type" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>">
                                    ${documentTypeOptionsHtml}
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="document-file-${documentIndex}"><?php echo app_lang('document'); ?> <span class="text-danger">*</span></label>
                                <input id="document-file-${documentIndex}" type="file" name="file[]" class="form-control" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>">
                                <small class="text-muted"><?php echo app_lang("allowed_document_formats"); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="document-issued-${documentIndex}"><?php echo app_lang('issued_at'); ?></label>
                                <input id="document-issued-${documentIndex}" type="date" name="issued_at[]" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="document-expires-${documentIndex}"><?php echo app_lang('expires_at'); ?></label>
                                <input id="document-expires-${documentIndex}" type="date" name="expires_at[]" class="form-control">
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

            $("#email, #user_email, #cr_number, #phone_country_code, #phone_local, #user_mobile, #password, #password_confirm").removeClass("is-invalid gv-shake");
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
                    if (res.errors.user_mobile) showError("user_mobile", res.errors.user_mobile);
                    if (res.errors.password) showError("password", res.errors.password);
                    if (res.errors.password_confirm) showError("password_confirm", res.errors.password_confirm);

                    if (res.errors.email || res.errors.user_email || res.errors.cr_number || res.errors.phone_country_code || res.errors.phone_local || res.errors.user_mobile || res.errors.password || res.errors.password_confirm) {
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
