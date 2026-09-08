<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang($module . '_accounting'); ?></h1>
            <div class="title-button-group"><?php echo anchor(get_uri('payment_accounting/index/' . $module), app_lang('back'), ['class' => 'btn btn-default']); ?></div>
        </div>
        <div class="card-body">
            <p class="text-off mb5"><?php echo app_lang('payment_accounting_transaction'); ?></p>
            <h4 class="text-break"><?php echo esc($payment->public_id); ?></h4>
            <div class="alert alert-<?php echo esc($presentation['tone']); ?> mt15" role="status">
                <strong><?php echo esc($presentation['status']); ?></strong>
                <p class="mb0 mt5"><?php echo esc($presentation['next_step']); ?></p>
            </div>
            <dl class="row">
                <?php foreach ($presentation['fields'] as $field): ?>
                    <dt class="col-sm-3 mb10"><?php echo esc($field['label']); ?></dt>
                    <dd class="col-sm-9 mb10 text-break"><?php echo esc($field['value']); ?></dd>
                <?php endforeach; ?>
            </dl>
            <?php if ($can_reconcile): ?>
                <?php echo form_open(get_uri('payment_accounting/recheck/' . $module . '/' . (int)$payment->id), ['id' => 'payment-accounting-recheck', 'class' => 'general-form']); ?>
                    <button type="submit" class="btn btn-default"><?php echo app_lang('payment_accounting_recheck'); ?></button>
                    <span class="text-off ml10"><?php echo app_lang('payment_accounting_recheck_hint'); ?></span>
                <?php echo form_close(); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($can_responses): ?>
        <div class="card">
            <div class="card-header"><h4><?php echo app_lang('payment_accounting_bank_details'); ?></h4></div>
            <div class="card-body">
                <p class="text-off"><?php echo app_lang('payment_accounting_response_hint'); ?></p>
                <?php if ($presentation['issues']): ?>
                    <div class="alert alert-warning">
                        <strong><?php echo app_lang('payment_accounting_review_reasons'); ?></strong>
                        <ul class="mb0 mt10"><?php foreach ($presentation['issues'] as $issue): ?><li><?php echo esc($issue); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <?php foreach (['bank_return' => 'bank_response', 'bank_check' => 'status_response'] as $section => $title): ?>
                        <div class="col-lg-6">
                            <h5><?php echo app_lang('payment_accounting_' . $title); ?></h5>
                            <?php if (!$presentation[$section]): ?>
                                <p class="text-off"><?php echo app_lang('payment_accounting_no_response'); ?></p>
                            <?php else: ?>
                                <dl class="row">
                                    <?php foreach ($presentation[$section] as $field): ?>
                                        <dt class="col-sm-5 mb10"><?php echo esc($field['label']); ?></dt>
                                        <dd class="col-sm-7 mb10 text-break"><?php echo esc($field['value']); ?></dd>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-off mb0"><?php echo app_lang('payment_accounting_bank_time_hint'); ?></p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h4><?php echo app_lang('payment_accounting_events'); ?></h4></div>
            <div class="card-body">
                <?php if (!$presentation['timeline']): ?><p><?php echo app_lang('no_record_found'); ?></p><?php endif; ?>
                <?php foreach ($presentation['timeline'] as $event): ?>
                    <div class="border-start ps-3 pb15 mb15">
                        <p class="text-off mb5"><?php echo esc($event['date']); ?></p>
                        <h5><?php echo esc($event['title']); ?> <span class="text-off">— <?php echo esc($event['status']); ?></span></h5>
                        <?php foreach ($event['issues'] as $issue): ?><p class="text-warning mb5"><?php echo esc($issue); ?></p><?php endforeach; ?>
                        <?php if ($event['fields']): ?>
                            <details>
                                <summary><?php echo app_lang('payment_accounting_show_bank_details'); ?></summary>
                                <dl class="row mt15">
                                    <?php foreach ($event['fields'] as $field): ?>
                                        <dt class="col-sm-3 mb10"><?php echo esc($field['label']); ?></dt>
                                        <dd class="col-sm-9 mb10 text-break"><?php echo esc($field['value']); ?></dd>
                                    <?php endforeach; ?>
                                </dl>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if ($can_reconcile): ?>
<script>
$(document).ready(function () {
    $('#payment-accounting-recheck').appForm({
        onAjaxSuccess: function (result) {
            if (result.refresh === true) { window.location.reload(); }
        },
        onError: function (result) { return result.refresh !== true; }
    });
});
</script>
<?php endif; ?>
