<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang($module . '_accounting'); ?></h1>
        </div>
        <div class="p15">
            <p class="text-off"><?php echo app_lang('payment_accounting_hint'); ?></p>
            <?php if ($module === 'ptw'): ?><p class="alert alert-info"><?php echo app_lang('payment_accounting_ptw_hint'); ?></p><?php endif; ?>
            <?php if (!$ready): ?>
                <div class="alert alert-warning"><?php echo app_lang('payment_accounting_not_ready'); ?></div>
            <?php else: ?>
                <form id="payment-accounting-filters" class="row g-3 align-items-end">
                    <?php foreach (['start_date', 'end_date', 'vendor', 'payer', 'reference'] as $filter): ?>
                        <div class="col-md-2">
                            <label class="form-label" for="payment-filter-<?php echo $filter; ?>"><?php echo app_lang('payment_accounting_filter_' . $filter); ?></label>
                            <input id="payment-filter-<?php echo $filter; ?>" name="<?php echo $filter; ?>" type="<?php echo in_array($filter, ['start_date', 'end_date'], true) ? 'date' : 'text'; ?>" class="form-control" maxlength="200">
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-2">
                        <label class="form-label" for="payment-filter-status"><?php echo app_lang('status'); ?></label>
                        <select id="payment-filter-status" name="status" class="form-control">
                            <option value=""><?php echo app_lang('all'); ?></option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo esc($status); ?>"><?php echo app_lang('payment_accounting_status_' . $status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo app_lang('filter'); ?></button>
                        <button type="reset" class="btn btn-default"><?php echo app_lang('reset'); ?></button>
                        <?php if ($can_export): ?><a id="payment-accounting-export" class="btn btn-default" href="<?php echo_uri('payment_accounting/export_csv/' . $module); ?>"><?php echo app_lang('payment_accounting_export'); ?></a><?php endif; ?>
                    </div>
                </form>
                <div id="payment-accounting-error" role="alert" class="alert alert-warning mt15" style="display:none"></div>
            <?php endif; ?>
        </div>
        <?php if ($ready): ?><div class="table-responsive"><table id="payment-accounting-table" class="display" width="100%" cellspacing="0"></table></div><?php endif; ?>
    </div>
</div>
<?php if ($ready): ?>
<script>
$(document).ready(function () {
    var $table = $('#payment-accounting-table'), $form = $('#payment-accounting-filters'), $error = $('#payment-accounting-error');
    $table.on('xhr.dt', function (event, settings, data, xhr) {
        if (data && data.message) {
            $error.text(data.message).show();
        } else if (data && data.truncated) {
            $error.text(<?php echo json_encode(app_lang('payment_accounting_list_limit')); ?>).show();
        } else if (xhr && xhr.status >= 400) {
            $error.text(<?php echo json_encode(app_lang('error_occurred')); ?>).show();
        } else {
            $error.hide();
        }
    });
    $table.appTable({
        source: <?php echo json_encode(get_uri('payment_accounting/list_data/' . $module)); ?>,
        columnShowHideOption: false,
        printColumns: [],
        xlsColumns: [],
        displayLength: 25,
        columns: [
            <?php foreach (['initiated_at', 'transaction', 'payment_type', 'subject_reference', 'vendor', 'payer', 'company', 'amount', 'status', 'bank_reference', 'paid_at'] as $column): ?>
            {title: <?php echo json_encode(app_lang('payment_accounting_' . $column)); ?>},
            <?php endforeach; ?>
        ],
        order: [[0, 'desc']]
    });
    function applyFilters() {
        var values = {};
        $.each($form.serializeArray(), function (_, field) { values[field.name] = field.value; });
        $table.appTable({reload: true, filterParams: values});
        $('#payment-accounting-export').attr('href', <?php echo json_encode(get_uri('payment_accounting/export_csv/' . $module)); ?> + '?' + $.param(values));
    }
    $form.on('submit', function (event) { event.preventDefault(); applyFilters(); });
    $form.on('reset', function () { setTimeout(applyFilters, 0); });
});
</script>
<?php endif; ?>
