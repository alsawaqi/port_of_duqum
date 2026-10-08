<div id="page-content" class="page-wrapper clearfix pod-page-shell pod-vendor-page">
    <?php echo view('includes/pod_page_header', ['title' => 'Vendor registration',
        'subtitle' => 'Track your company application and complete registration payment.', 'icon' => 'briefcase']); ?>
    <div class="card"><div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div><h2 class="h4"><?php echo esc($vendor->vendor_name); ?></h2><p>CR: <strong><?php echo esc($vendor->cr_number); ?></strong></p></div>
            <?php if (count($vendor_memberships) > 1): ?><a class="btn btn-outline-primary" href="<?php echo get_uri('signin/vendor_selection'); ?>">Switch company / CR</a><?php endif; ?>
        </div>
        <?php if ($application->status === 'pending_review'): ?>
            <div class="alert alert-info"><h3 class="h5">Pending waiver approval</h3>
                Your Riyadha document has been submitted. Procurement must approve the fee waiver before your vendor registration becomes active.
            </div>
        <?php else: ?>
            <div class="alert alert-warning"><h3 class="h5">Registration payment required</h3>
                Pay <strong><?php echo esc($application->currency . ' ' . number_format((float) $application->amount, 3)); ?></strong> through Bank Muscat to complete registration.
            </div>
            <?php if ($can_pay): ?>
                <?php echo form_open(get_uri('vendor_portal/pay_registration'), ['id' => 'vendor-registration-payment']); ?>
                    <button class="btn btn-primary" type="submit"><i data-feather="credit-card" class="icon-16"></i> Pay registration fee</button>
                <?php echo form_close(); ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($application->review_note): ?><div class="mt-3"><strong>Procurement message</strong><p><?php echo nl2br(esc($application->review_note)); ?></p></div><?php endif; ?>
        <p class="text-muted mt-3 mb-0">Your vendor profile, contacts and tender features will become available after registration is completed.</p>
    </div></div>
</div>
<script>
$(function () {
    $('#vendor-registration-payment').appForm({isModal: false, onSuccess: function (result) {
        if (result.checkout_url) { window.location.assign(result.checkout_url); }
    }});
    if (window.feather) { feather.replace(); }
});
</script>
