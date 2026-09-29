<?php echo form_open(get_uri("vendor_group_fees/save"), array("id" => "vendor-group-fees-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="vendor_group_id" class="col-md-3"><?php echo app_lang('vendor_groups'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "vendor_group_id",
                        $vendor_groups_dropdown,
                        $model_info->vendor_group_id,
                        "id='vendor_group_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="fee_type" class="col-md-3"><?php echo app_lang('fee_type'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "fee_type",
                        $fee_types_dropdown,
                        $model_info->fee_type,
                        "id='fee_type' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="currency" class="col-md-3"><?php echo app_lang('currency'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "currency",
                        "name" => "currency",
                        "value" => $model_info->currency ? $model_info->currency : "OMR",
                        "class" => "form-control",
                        "placeholder" => "OMR",
                        "maxlength" => 3,
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="amount" class="col-md-3"><?php echo app_lang('amount'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "amount",
                        "name" => "amount",
                        "type" => "number",
                        "step" => "0.001",
                        "value" => $model_info->amount,
                        "class" => "form-control",
                        "placeholder" => "0.000",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required')
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="active_from" class="col-md-3"><?php echo app_lang('active_from'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "active_from",
                        "name" => "active_from",
                        "value" => $model_info->active_from,
                        "class" => "form-control",
                        "placeholder" => "YYYY-MM-DD"
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="active_to" class="col-md-3"><?php echo app_lang('active_to'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "active_to",
                        "name" => "active_to",
                        "value" => $model_info->active_to,
                        "class" => "form-control",
                        "placeholder" => "YYYY-MM-DD"
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('status'); ?></label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                               <?php echo ($model_info->id ? ($model_info->is_active ? "checked" : "") : "checked"); ?>>
                        <label class="form-check-label" for="is_active"><?php echo app_lang('active'); ?></label>
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
$(document).ready(function () {

    $('#vendor_group_id').select2();
    $('#fee_type').select2();

    setDatePicker('#active_from');
    setDatePicker('#active_to');

    $('#vendor-group-fees-form').appForm({
        onSuccess: function (result) {
            $('#vendor-group-fees-table').appTable({newData: result.data, dataId: result.id});
        }
    });

});
</script>
