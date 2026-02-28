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
                <div class="col-md-9"><?php echo form_input(array("name" => "email", "value" => $model_info->email, "class" => "form-control")); ?></div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("mobile"); ?></label>
                <div class="col-md-9"><?php echo form_input(array("name" => "mobile", "value" => $model_info->mobile, "class" => "form-control")); ?></div>
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