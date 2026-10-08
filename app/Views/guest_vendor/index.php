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
                                <p id="registration-group-summary" class="small text-muted mt-2" role="status"></p>
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
                    <div class="alert alert-info"><?php echo esc(app_lang('vendor_shared_account_hint')); ?></div>

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
                                <div id="password-error" class="alert alert-danger mt10 d-none" role="alert"></div>
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
                            <?php echo app_lang("vendor_registration_documents_help"); ?>
                        </p>
                    </div>

                    <div id="registration-fee-summary" class="alert alert-info" role="status">Choose a vendor group to see the registration fee.</div>
                    <input type="hidden" name="registration_amount" id="registration-quoted-amount" value="">
                    <p id="registration-documents-hint" class="text-muted" role="status"></p>
                    <div id="registration-documents-changed" class="alert alert-warning d-none" role="status"><?php echo app_lang('vendor_registration_documents_group_changed'); ?></div>
                    <div id="registration-required-documents"></div>
                    <div id="guest-vendor-extra-documents"></div>
                    <button type="button" class="btn btn-default gv-add-document" data-gv-document-add>
                        <i data-feather="plus-circle" class="icon-16"></i> <?php echo app_lang("add_document"); ?>
                    </button>
                </div>

            </div>

            <div class="gv-footer">
                <?php echo view("signin/re_captcha"); ?>
                <p class="note mb0">
                    Paid registrations continue to Bank Muscat. Waived registrations require admin approval. You can sign in to check your status and retry payment.
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

        $("#vendor_group_id, #country_id, #region_id, #city_id").select2({
            width: "100%",
            placeholder: "",
            allowClear: true
        });

        $("#phone_country_code").select2({
            width: "100%"
        });

        var registrationQuotes = <?php echo json_encode($registration_quotes ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var registrationDocumentTypes = <?php echo json_encode($registration_document_types ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        $('#vendor_group_id').on('change', function () {
            var quote = registrationQuotes[this.value];
            var ready = quote && !quote.error;
            $('#registration-quoted-amount').val(ready ? quote.amount : '');
            $('#registration-fee-summary, #registration-group-summary').text(!quote ? 'Choose a vendor group to see the registration fee.' :
                quote.error || (quote.waiver ? 'Fee waiver requested: upload Riyadha and wait for admin approval.' :
                    'Registration fee: ' + quote.currency + ' ' + quote.amount + '. Payment completes your registration.'));
            $('#guest-vendor-form button[type=submit]').prop('disabled', !ready);
            renderRegistrationDocuments(ready ? (registrationDocumentTypes[this.value] || []) : [], !!ready);
        });

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

        var documentIndex = 0;
        var activeDocumentTypes = [];
        var requiredLabel = <?php echo json_encode(app_lang('required')); ?>;
        var optionalLabel = <?php echo json_encode(app_lang('optional')); ?>;

        function documentOptions($select, selected) {
            $select.empty().append($('<option></option>').val('').text(<?php echo json_encode('- ' . app_lang('select_document_type') . ' -'); ?>));
            activeDocumentTypes.forEach(function (type) {
                $select.append($('<option></option>').val(type.id).text(type.name + (type.code ? ' (' + type.code + ')' : '')));
            });
            $select.val(selected || '').trigger('change');
        }

        function documentRow(type) {
            var fixed = !!type;
            var key = fixed ? 'required-' + type.id : 'extra-' + (++documentIndex);
            var row = $(`
                <div class="gv-document-row" data-gv-document-row>
                    <div class="gv-document-row-header">
                        <span class="gv-document-row-title" data-document-title></span>
                        <button type="button" class="gv-document-remove" data-gv-document-remove aria-label="<?php echo app_lang('remove'); ?>"><i data-feather="x" class="icon-14"></i></button>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 form-group">
                            <label data-document-label="type"><?php echo app_lang('document_type'); ?></label>
                            <select class="form-control gv-document-type validate-hidden" name="vendor_document_type_id[]" required></select>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label data-document-label="file"><?php echo app_lang('document'); ?> <span data-required-mark class="text-danger">*</span></label>
                            <input type="file" name="file[]" class="form-control" data-document-file>
                            <small class="text-muted"><?php echo app_lang('allowed_document_formats'); ?></small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 form-group">
                            <label data-document-label="issued"><?php echo app_lang('issued_at'); ?></label>
                            <input type="date" name="issued_at[]" class="form-control" data-document-issued>
                        </div>
                        <div class="col-lg-6 form-group">
                            <label data-document-label="expires"><?php echo app_lang('expires_at'); ?></label>
                            <input type="date" name="expires_at[]" class="form-control" data-document-expires>
                        </div>
                    </div>
                </div>
            `);
            var $type = row.find('select');
            if (fixed) {
                row.attr('data-fixed-type', type.id);
                row.find('[data-gv-document-remove]').remove();
                $type = $('<input type="text" class="form-control" disabled>').val(type.name + (type.code ? ' (' + type.code + ')' : '')).replaceAll($type);
                row.find('[data-document-file]').attr('name', 'registration_file_' + type.id);
                row.find('[data-document-issued]').attr('name', 'registration_issued_at[' + type.id + ']');
                row.find('[data-document-expires]').attr('name', 'registration_expires_at[' + type.id + ']');
            } else {
                row.find('[data-document-title]').text(<?php echo json_encode(app_lang('document')); ?> + ' ' + documentIndex);
                documentOptions($type);
            }
            ['type', 'file', 'issued', 'expires'].forEach(function (field) {
                var input = field === 'type' ? $type : row.find('[data-document-' + field + ']');
                input.attr('id', 'registration-' + key + '-' + field);
                row.find('[data-document-label="' + field + '"]').attr('for', input.attr('id'));
            });
            row.data('required', fixed ? !!type.required : true);
            row.find('[data-document-file]').prop('required', row.data('required'));
            return row;
        }

        function renderRegistrationDocuments(types, ready) {
            activeDocumentTypes = types;
            var removedUpload = false;
            var existing = {};
            $('#registration-required-documents [data-fixed-type]').each(function () { existing[$(this).attr('data-fixed-type')] = $(this).detach(); });
            types.forEach(function (type) {
                var row = existing[type.id] || documentRow(type);
                delete existing[type.id];
                row.data('required', !!type.required);
                row.find('[data-document-title]').text(type.name + ' — ' + (type.required ? requiredLabel : optionalLabel));
                row.find('[data-required-mark]').toggle(!!type.required);
                row.find('[data-document-file]').prop('required', !!type.required || !!row.find('[data-document-issued]').val() || !!row.find('[data-document-expires]').val());
                $('#registration-required-documents').append(row);
            });
            Object.values(existing).forEach(function (row) {
                removedUpload = removedUpload || !!row.find('[data-document-file]').val();
                row.remove();
            });
            $('#guest-vendor-extra-documents [data-gv-document-row]').each(function () {
                var row = $(this), selected = row.find('select').val();
                if (!ready || (selected && !types.some(function (type) { return String(type.id) === selected; }))) {
                    removedUpload = removedUpload || !!row.find('[data-document-file]').val();
                    row.remove();
                } else { documentOptions(row.find('select'), selected); }
            });
            $('#registration-documents-changed').toggleClass('d-none', !removedUpload);
            $('#registration-documents-hint').text(!ready ? <?php echo json_encode(app_lang('vendor_registration_documents_choose_group')); ?> :
                (types.some(function (type) { return type.required; }) ? '' : <?php echo json_encode(app_lang('vendor_registration_documents_none_required')); ?>));
            $('[data-gv-document-add]').prop('disabled', !ready || !types.length);
            if (window.feather) feather.replace();
        }

        $('[data-gv-document-add]').on('click', function () {
            var row = documentRow(null);
            $('#guest-vendor-extra-documents').append(row);
            row.find('select').select2({width: '100%'});
            if (window.feather) feather.replace();
        });
        $(document).on('click', '[data-gv-document-remove]', function () { $(this).closest('[data-gv-document-row]').remove(); });
        $(document).on('change', '[data-document-file]', function () {
            var validator = $('#guest-vendor-form').data('validator');
            if (validator) { validator.element(this); }
        });
        $(document).on('change', '[data-document-issued], [data-document-expires]', function () {
            var row = $(this).closest('[data-gv-document-row]');
            row.find('[data-document-file]').prop('required', row.data('required') || !!row.find('[data-document-issued]').val() || !!row.find('[data-document-expires]').val());
        });
        $('#vendor_group_id').trigger('change');

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
            $("#password-error").addClass("d-none").text("");

            $("#email, #user_email, #cr_number, #phone_country_code, #phone_local, #user_mobile, #password, #password_confirm").removeClass("is-invalid gv-shake");
        }

        function shake($el) {
            $el.removeClass("gv-shake");
            void $el[0].offsetWidth; // reflow
            $el.addClass("gv-shake");
        }

        function showError(field, message) {
            if (field === 'registration_documents') {
                document.getElementById('gv-documents').scrollIntoView({block: 'start', behavior: 'smooth'});
                $('#gv-documents').trigger('focus');
                appAlert.error(message);
                return;
            }
            if (field === "password") {
                $("#password").addClass("is-invalid");
                $("#password-error").removeClass("d-none").text(message);
                document.getElementById("gv-account").scrollIntoView({block: "start", behavior: "smooth"});
                $("#password").trigger("focus");
                return;
            }
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

                if (res.checkout_url) {
                    window.location.assign(res.checkout_url);
                    return;
                }
                var confirmation = $('<div class="alert alert-success m-4" role="status"></div>').text(res.message);
                confirmation.append($('<p class="mt-3"></p>').append($('<a></a>').attr('href', <?php echo json_encode(get_uri('signin')); ?>).text('Sign in to check your application')));
                $('#guest-vendor-form').hide().after(confirmation);
                confirmation[0].scrollIntoView({block: 'center'});
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
