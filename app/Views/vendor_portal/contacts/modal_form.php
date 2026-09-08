<?php echo form_open(get_uri("vendor_portal/save_contact"), array("id" => "vendor-contact-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "name" => "contacts_name",
                        "value" => $model_info->contacts_name,
                        "class" => "form-control",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("designation"); ?></label>
                <div class="col-md-9"><?php echo form_input(array("name" => "designation", "value" => $model_info->designation, "class" => "form-control")); ?></div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("email"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array_merge(
                        array(
                            "name" => "email",
                            "value" => $model_info->email,
                            "class" => "form-control",
                            "autocomplete" => "email",
                            "data-rule-required" => true,
                            "data-rule-email" => true,
                            "data-msg-required" => app_lang("field_required"),
                        ),
                        !empty($model_info->user_id) ? array("readonly" => "readonly") : array()
                    )); ?>
                    <small class="text-muted">
                        This is the contact's login email. It must be unique within this CR.
                        <?php if (!empty($model_info->user_id)) { ?>The email is locked because the contact is already linked to an account.<?php } ?>
                    </small>
                </div>
            </div>
        </div>

        <?php if (empty($model_info->user_id)) { ?>
            <div class="form-group">
                <div class="row">
                    <label class="col-md-3">Portal access</label>
                    <div class="col-md-9">
                        <?php echo form_dropdown(
                            "access_role",
                            $portal_access_roles ?? [],
                            $portal_access_role ?? "VIEWER",
                            "class='form-control select2' data-rule-required='true'"
                        ); ?>
                        <small class="text-muted">
                            Viewer is read-only. Bidder can participate in tenders. Editor can also maintain CR profile data.
                            Only the CR owner can manage contacts.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label class="col-md-3">Initial password</label>
                    <div class="col-md-9">
                        <?php echo form_password(array(
                            "name" => "initial_password",
                            "id" => "vendor-contact-initial-password",
                            "class" => "form-control",
                            "autocomplete" => "new-password",
                            "data-rule-minlength" => 10,
                            "data-rule-maxlength" => 72,
                            "data-msg-minlength" => "Use at least 10 characters."
                        )); ?>
                        <small class="text-muted">
                            Required for a new login email. If this email already has an active portal account,
                            its existing password is retained and applies to every linked CR.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label class="col-md-3">Confirm password</label>
                    <div class="col-md-9">
                        <?php echo form_password(array(
                            "name" => "initial_password_confirm",
                            "id" => "vendor-contact-initial-password-confirm",
                            "class" => "form-control",
                            "autocomplete" => "new-password",
                            "data-rule-equalTo" => "#vendor-contact-initial-password",
                            "data-msg-equalTo" => app_lang("enter_same_value")
                        )); ?>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <input type="hidden" name="access_role" value="<?php echo esc($portal_access_role ?? "VIEWER"); ?>" />
        <?php } ?>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("mobile"); ?></label>
                <div class="col-md-9"><?php echo form_input(array("name" => "mobile", "value" => $model_info->mobile, "class" => "form-control", "type" => "tel", "maxlength" => 30)); ?>
                    <small class="text-muted"><?php echo app_lang('vendor_contact_login_mobile_help'); ?></small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("role"); ?></label>
                <div class="col-md-9"><?php echo form_input(array("name" => "role", "value" => $model_info->role, "class" => "form-control")); ?></div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1"
                            <?php echo ($model_info->id ? ($model_info->is_active ? "checked" : "") : "checked"); ?>>
                        <label class="form-check-label"><?php echo app_lang("active"); ?></label>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" name="is_primary" value="1"
                            <?php echo ($model_info->is_primary ? "checked" : ""); ?>>
                        <label class="form-check-label"><?php echo app_lang("primary"); ?></label>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang('save'); ?></button>

</div>

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function() {
        const $form = $("#vendor-contact-form");

        $form.appForm({
            beforeSubmit: function() {
                $form.find("button[type='submit']").prop("disabled", true);
            },
            onSuccess: function(result) {
                $("#vendor-contacts-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
                $form.find("button[type='submit']").prop("disabled", false);
            },
            onError: function() {
                $form.find("button[type='submit']").prop("disabled", false);
            }
        });
    });
</script>
