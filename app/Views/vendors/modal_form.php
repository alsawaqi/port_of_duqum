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
                        "class='select2' id='currency' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
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
                        "class='select2' id='payment_terms' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>


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
                </div>
            </div>
        </div>

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

        var $vendorEmail = $("#email");
        var $userEmail = $("#user_email");

        function clearErrors() {
            // remove invalid states
            $vendorEmail.removeClass("is-invalid");
            $userEmail.removeClass("is-invalid");

            // remove invalid-feedback blocks created by us
            $vendorEmail.closest(".form-group").find(".invalid-feedback").remove();
            $userEmail.closest(".form-group").find(".invalid-feedback").remove();

            // hide alert blocks
            $("#vendor-email-error").addClass("d-none").text("");
            $("#user-email-error").addClass("d-none").text("");
        }

        function showFieldError($input, message) {
            $input.addClass("is-invalid");

            var $group = $input.closest(".form-group");
            // remove old feedback if any
            $group.find(".invalid-feedback").remove();

            // insert new
            $input.after('<div class="invalid-feedback">' + message + "</div>");
        }

        function showAlert(targetId, message) {
            $(targetId).removeClass("d-none").text(message);
        }

        $("#vendors-form").appForm({
            onSubmit: function() {
                clearErrors();
            },

            onError: function(result) {
                clearErrors();

                // ✅ Best: use "errors" map from backend (you already return this)
                if (result && result.errors) {
                    if (result.errors.email) {
                        showFieldError($vendorEmail, result.errors.email);
                        showAlert("#vendor-email-error", result.errors.email);
                    }
                    if (result.errors.user_email) {
                        showFieldError($userEmail, result.errors.user_email);
                        showAlert("#user-email-error", result.errors.user_email);
                    }

                    // If we handled field errors, stop default modal-body error
                    if (result.errors.email || result.errors.user_email) {
                        return false;
                    }
                }

                // ✅ Fallback: use "field" + "message"
                if (result && result.field && result.message) {
                    if (result.field === "email") {
                        showFieldError($vendorEmail, result.message);
                        showAlert("#vendor-email-error", result.message);
                        return false;
                    }
                    if (result.field === "user_email") {
                        showFieldError($userEmail, result.message);
                        showAlert("#user-email-error", result.message);
                        return false;
                    }
                }

                // Let appForm show the generic error message (modal-body)
                return true;
            },

            onSuccess: function(result) {
                $("#vendors-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });

    });
</script>