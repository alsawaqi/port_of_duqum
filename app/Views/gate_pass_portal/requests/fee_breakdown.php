<?php
$quote = $quote ?? \App\Libraries\Gate_pass_tariff::snapshot($request);
?>
<style>
body:not(.pod-auth-page) .table-responsive > table.gp-tariff-table { width: 100%; min-width: 0 !important; table-layout: fixed; }
.gp-tariff-table th, .gp-tariff-table td { white-space: normal !important; overflow-wrap: anywhere; }
.gp-tariff-table th:first-child { width: 31%; }
@media (max-width: 600px) {
    body:not(.pod-auth-page) .table-responsive > table.gp-tariff-table th,
    body:not(.pod-auth-page) .table-responsive > table.gp-tariff-table td { font-size: 12px; padding: 9px 5px !important; }
}
</style>
<div class="card mb15" data-tariff-total="<?php echo esc(($quote['currency'] ?? $request->currency ?? 'OMR') . ' ' . ($quote['total'] ?? number_format((float)($request->fee_amount ?? 0), 3))); ?>" aria-label="<?php echo esc(app_lang('gate_pass_tariff_breakdown')); ?>">
    <div class="card-body">
        <h4><?php echo app_lang('gate_pass_tariff_breakdown'); ?></h4>
        <?php if ($quote): ?>
            <p class="text-muted"><?php echo sprintf(app_lang('gate_pass_tariff_duration'), (int)$quote['days'], (int)$quote['min_days'], (int)$quote['max_days']); ?></p>
            <div class="d-flex flex-wrap justify-content-between gap-3 mb15">
                <div><span class="text-muted"><?php echo app_lang('gate_pass_tariff_visitors'); ?></span><div class="fs-5 fw-bold"><?php echo (int)$quote['visitor_count']; ?></div></div>
                <div><span class="text-muted"><?php echo app_lang('total'); ?></span><div class="fs-5 fw-bold"><?php echo esc($quote['currency'] . ' ' . $quote['total']); ?></div></div>
            </div>
            <div class="table-responsive">
                <table class="table gp-tariff-table mb0">
                    <thead><tr>
                        <th><?php echo app_lang('description'); ?></th>
                        <th><?php echo app_lang('gate_pass_tariff_unit'); ?></th>
                        <th><?php echo app_lang('gate_pass_tariff_visitors'); ?></th>
                        <th><?php echo app_lang('amount'); ?></th>
                    </tr></thead>
                    <tbody>
                        <tr><td><?php echo app_lang('gate_pass_tariff_base'); ?></td>
                            <td><?php echo esc($quote['currency'] . ' ' . $quote['unit_amount']); ?></td>
                            <td><?php echo (int)$quote['visitor_count']; ?></td>
                            <td><?php echo esc($quote['currency'] . ' ' . $quote['tariff_subtotal']); ?></td></tr>
                        <?php if ((float)$quote['induction_unit_amount'] > 0): ?>
                        <tr><td><?php echo app_lang('gate_pass_tariff_induction'); ?></td>
                            <td><?php echo esc($quote['currency'] . ' ' . $quote['induction_unit_amount']); ?></td>
                            <td><?php echo (int)$quote['visitor_count']; ?></td>
                            <td><?php echo esc($quote['currency'] . ' ' . $quote['induction_subtotal']); ?></td></tr>
                        <?php endif; ?>
                        <tr class="fw-bold"><td colspan="3"><?php echo app_lang('total'); ?></td>
                            <td><?php echo esc($quote['currency'] . ' ' . $quote['total']); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php if (!$quote['visitor_count']): ?><p class="mt10 mb0 text-muted"><?php echo app_lang('gate_pass_tariff_add_visitors'); ?></p><?php endif; ?>
        <?php else: ?>
            <p><?php echo esc(($request->currency ?? 'OMR') . ' ' . number_format((float)($request->fee_amount ?? 0), 3)); ?></p>
            <p class="mb0 text-muted"><?php echo app_lang('gate_pass_tariff_legacy'); ?></p>
        <?php endif; ?>
        <?php if (!empty($request->fee_is_waived)): ?><p class="mt10 mb0 text-success"><?php echo app_lang('waived'); ?></p><?php endif; ?>
    </div>
</div>
