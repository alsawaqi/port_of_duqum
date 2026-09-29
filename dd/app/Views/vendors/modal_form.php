<?php echo form_open(get_uri("vendors/save"), array("id" => "vendors-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">

        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <div class="form-group">
            <div class="row">
                <label for="vendor_group_id" class=" col-md-3"><?php echo app_lang('vendor_group'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "vendor_group_id",
                        $vendor_groups_dropdown,
                        $model_info->vendor_group_id ?? "",
                        "class='select2 validate-hidden' id='vendor_group_id' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="vendor_grade_id" class=" col-md-3"><?php echo app_lang('vendor_grade'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "vendor_grade_id",
                        $vendor_grades_dropdown,
                        $model_info->vendor_grade_id ?? "",
                        "class='select2' id='vendor_grade_id'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="vendor_name" class=" col-md-3"><?php echo app_lang('vendor_name'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "vendor_name",
                        "name" => "vendor_name",
                        "value" => $model_info->vendor_name ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang('vendor_name'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="cr_number" class=" col-md-3">
                    <?php echo app_lang('cr_number'); ?>
                    <?php if (empty($model_info->id)) : ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "cr_number",
                        "name" => "cr_number",
                        "value" => $model_info->cr_number ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang('cr_number'),
                        "data-rule-required" => empty($model_info->id) ? "true" : null,
                        "data-msg-required" => app_lang('field_required')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="email" class=" col-md-3"><?php echo app_lang('email'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "email",
                        "name" => "email",
                        "value" => $model_info->email ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang('email'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required'),
                        "data-rule-email" => true,
                        "data-msg-email" => app_lang('enter_valid_email')
                    ));
                    ?>

                    <!-- Vendor email alert (optional, we show field invalid-feedback too) -->
                    <div id="vendor-email-error" class="alert alert-danger mt10 d-none"></div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="country_id" class=" col-md-3"><?php echo app_lang('country'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "country_id",
                        $countries_dropdown,
                        $model_info->country_id ?? "",
                        "class='select2' id='country_id'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="region_id" class=" col-md-3"><?php echo app_lang('region'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "region_id",
                        $regions_dropdown,
                        $model_info->region_id ?? "",
                        "class='select2' id='region_id'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="city_id" class=" col-md-3"><?php echo app_lang('city'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "city_id",
                        $cities_dropdown,
                        $model_info->city_id ?? "",
                        "class='select2' id='city_id'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <hr>

        <div class="form-group">
            <div class="row">
                <label for="address" class=" col-md-3"><?php echo app_lang('address'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "address",
                        "name" => "address",
                        "value" => $model_info->address ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang('address')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="po_box" class=" col-md-3"><?php echo app_lang('po_box'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "po_box",
                        "name" => "po_box",
                        "value" => $model_info->po_box ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang('po_box')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="postal_code" class=" col-md-3"><?php echo app_lang('postal_code'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "postal_code",
                        "name" => "postal_code",
                        "value" => $model_info->postal_code ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang('postal_code')
                    ));
                    ?>
                </div>
            </div>
        </div>


        <div class="form-group">
            <div class="row">
                <label for="currency" class=" col-md-3"><?php echo app_lang('currency'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "currency",
                        $currency_dropdown,
                        $model_info->currency ?? "",
                        "class='select2 validate-hidden' id='currency' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="payment_terms" class=" col-md-3"><?php echo app_lang('payment_terms'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown(
                        "payment_terms",
                        $payment_terms_dropdown,
                        $model_info->payment_terms ?? "",
                        "class='select2 validate-hidden' id='payment_terms' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>


        <?php if (empty($model_info->id)) : ?>
        <h5 class="mb10"><?php echo app_lang("login_user"); ?></h5>

        <div class="form-group">
            <div class="row">
                <label for="user_name" class=" col-md-3"><?php echo app_lang('name'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "user_name",
                        "name" => "user_name",
                        "value" => "",
                        "class" => "form-control",
                        "placeholder" => app_lang('name'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="user_email" class=" col-md-3"><?php echo app_lang('email'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "user_email",
                        "name" => "user_email",
                        "value" => "",
                        "class" => "form-control",
                        "placeholder" => app_lang('email'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required'),
                        "data-rule-email" => true,
                        "data-msg-email" => app_lang('enter_valid_email')
                    ));
                    ?>

                    <!-- User email alert (optional, we show field invalid-feedback too) -->
                    <div id="user-email-error" class="alert alert-danger mt10 d-none"></div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="password" class=" col-md-3"><?php echo app_lang('password'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_password(array(
                        "id" => "password",
                        "name" => "password",
                        "value" => "",
                        "class" => "form-control",
                        "placeholder" => app_lang('password'),
                        "autocomplete" => "new-password"
                    ));
                    ?>
                    <small class="text-muted"><?php echo app_lang("vendor_admin_password_reuse_hint"); ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('cancel'); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang('save'); ?></button>
</div>

<?php echo form_close(); ?>

<script>
    $(document).ready(function() {

        $("#vendors-form .select2").select2();

        // dependent dropdowns
        $("#country_id").change(function() {
            var country_id = $(this).val() || 0;
            $("#region_id").html("<option value=''>- <?php echo app_lang('select_region'); ?> -</option>").trigger("change");
            $("#city_id").html("<option value=''>- <?php echo app_lang('select_city'); ?> -</option>").trigger("change");

            if (country_id) {
                $("#region_id").load("<?php echo get_uri('vendors/get_regions_dropdown_by_country'); ?>/" + country_id, function() {
                    $("#region_id").trigger("change");
                });
            }
        });

        $("#region_id").change(function() {
            var region_id = $(this).val() || 0;
            $("#city_id").html("<option value=''>- <?php echo app_lang('select_city'); ?> -</option>").trigger("change");

            if (region_id) {
                $("#city_id").load("<?php echo get_uri('vendors/get_cities_dropdown_by_region'); ?>/" + region_id, function() {
                    $("#city_id").trigger("change");
                });
            }
        });

        function clearErrors() {
            $("#vendors-form .is-invalid").removeClass("is-invalid").removeAttr("aria-invalid");
            $("#vendors-form .vendor-field-error").remove();
            $("#vendor-email-error").addClass("d-none").text("");
            $("#user-email-error").addClass("d-none").text("");
        }

        function showFieldError($input, message) {
            if (!$input.length) return;
            $input.addClass("is-invalid").attr("aria-invalid", "true");
            $input.closest(".form-group").find(".vendor-field-error").remove();
            $("<div>").addClass("invalid-feedback d-block vendor-field-error")
                .text(message).insertAfter($input);
        }

        $("#vendors-form").appForm({
            onSubmit: function() {
                clearErrors();
            },

            onError: function(result) {
                clearErrors();

                var errors = result && result.errors ? result.errors : {};
                if (result && result.field && result.message && !errors[result.field]) {
                    errors[result.field] = result.message;
                }
                $.each(errors, function(field, message) {
                    showFieldError($("#vendors-form :input[name]").filter(function() {
                        return this.name === field;
                    }), message);
                });
                // appForm must unmask the form so the user can correct and resubmit it.
                return true;
            },

            onSuccess: function(result) {
                $("#vendors-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });

        $("#vendors-form .validate-hidden").on("change", function() {
            $(this).valid();
        });

    });
</script>
