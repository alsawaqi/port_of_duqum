<?php echo form_open(get_uri("vendor_portal/save_contact_password"), [
    "id" => "vendor-contact-password-form",
    "class" => "general-form",
    "role" => "form",
]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />

        <div class="alert alert-warning">
            This contact was created under the retired invitation flow. Set their initial password here.
            The password belongs to this person's login identity and will apply to every CR linked to the same email.
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("name"); ?></label>
                <div class="col-md-9 pt-2"><?php echo esc($model_info->contacts_name); ?></div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("email"); ?></label>
                <div class="col-md-9 pt-2"><?php echo esc($model_info->email); ?></div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Initial password</label>
                <div class="col-md-9">
                    <?php echo form_password([
                        "name" => "initial_password",
                        "id" => "legacy-contact-initial-password",
                        "class" => "form-control",
                        "autocomplete" => "new-password",
                        "data-rule-required" => true,
                        "data-rule-minlength" => 10,
                        "data-rule-maxlength" => 72,
                    ]); ?>
                    <small class="text-muted">Use at least 10 characters (maximum 72 UTF-8 bytes).</small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Confirm password</label>
                <div class="col-md-9">
                    <?php echo form_password([
                        "name" => "initial_password_confirm",
                        "class" => "form-control",
                        "autocomplete" => "new-password",
                        "data-rule-required" => true,
                        "data-rule-equalTo" => "#legacy-contact-initial-password",
                    ]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary">Save password and activate</button>
</div>

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#vendor-contact-password-form").appForm({
            onSuccess: function (result) {
                $("#vendor-contacts-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });
    });
</script>
