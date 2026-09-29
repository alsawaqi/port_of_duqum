<?php
$profile_checklist = $profile_checklist ?? ["items" => [], "completed" => 0, "total" => 0, "percent" => 0, "expiring_documents" => []];
$expiring_documents = $profile_checklist["expiring_documents"] ?? [];
$vendor_status = strtolower((string)($vendor_info->status ?? ""));
$profile_complete = (int)($profile_checklist["completed"] ?? 0) >= (int)($profile_checklist["total"] ?? 0);
$billing = $billing ?? [];
$billing_type = $billing['type'] ?? 'registration';
$billing_quote = $billing['quote'] ?? [];
$billing_settled = !empty($billing['settled']);
$billing_zero = isset($billing_quote['amount']) && (string) $billing_quote['amount'] === '0.000';
$can_submit_for_review = !empty($can_pay_vendor_fee) && !empty($billing['can_start']) && empty($billing['error']);
$billing_button = $billing_settled || $billing_zero ? 'Submit for review' : 'Pay ' . $billing_type . ' fee';
?>

<div class="vp-overview ps-ready p15">

    <div class="ps-shell">
        <div class="ps-inner">

            <div class="ps-header" style="margin-bottom: 16px;">
                <div class="ps-header-left">
                    <div class="ps-header-icon">
                        <i data-feather="briefcase" class="icon-16"></i>
                    </div>
                    <div>
                        <h4 class="ps-header-title"><?php echo esc($vendor_info->vendor_name); ?></h4>
                        <p class="ps-header-sub"><?php echo app_lang("vendor"); ?> Overview</p>
                    </div>
                </div>
            </div>

            <div class="ps-stats-grid">
                <?php foreach (['registration_valid_from', 'registration_valid_to'] as $registration_date): ?>
                    <?php if (!empty($vendor_info->$registration_date)): ?>
                    <div class="ps-stat-card">
                        <div class="ps-stat-icon ps-icon-neutral"><i data-feather="calendar" class="icon-20"></i></div>
                        <div>
                            <div class="ps-stat-label"><?php echo app_lang('vendor_' . $registration_date); ?></div>
                            <div class="ps-stat-value" style="font-size:14px;"><?php echo esc(format_to_date($vendor_info->$registration_date, false)); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="ps-stat-card" style="animation-delay:.05s;">
                    <div class="ps-stat-icon">
                        <i data-feather="tag" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label"><?php echo app_lang("status"); ?></div>
                        <div class="ps-stat-value"><?php echo esc($vendor_info->status ? ucfirst(str_replace('_', ' ', $vendor_info->status)) : '-'); ?></div>
                    </div>
                </div>

                <div class="ps-stat-card" style="animation-delay:.10s;">
                    <div class="ps-stat-icon ps-icon-neutral">
                        <i data-feather="mail" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label"><?php echo app_lang("email"); ?></div>
                        <div class="ps-stat-value" style="font-size:13px;word-break:break-all;"><?php echo esc($vendor_info->email ?? '-'); ?></div>
                    </div>
                </div>

                <?php if (!empty($vendor_info->phone)): ?>
                <div class="ps-stat-card" style="animation-delay:.15s;">
                    <div class="ps-stat-icon ps-icon-success">
                        <i data-feather="phone" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label"><?php echo app_lang("phone"); ?></div>
                        <div class="ps-stat-value" style="font-size:14px;"><?php echo esc($vendor_info->phone); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($vendor_info->vat_number)): ?>
                <div class="ps-stat-card" style="animation-delay:.20s;">
                    <div class="ps-stat-icon ps-icon-warning">
                        <i data-feather="hash" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label">VAT Number</div>
                        <div class="ps-stat-value" style="font-size:14px;"><?php echo esc($vendor_info->vat_number); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($vendor_info->cr_number)): ?>
                <div class="ps-stat-card" style="animation-delay:.25s;">
                    <div class="ps-stat-icon ps-icon-neutral">
                        <i data-feather="file-text" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label">CR Number</div>
                        <div class="ps-stat-value" style="font-size:14px;"><?php echo esc($vendor_info->cr_number); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($vendor_info->address)): ?>
                <div class="ps-stat-card" style="animation-delay:.30s;">
                    <div class="ps-stat-icon ps-icon-success">
                        <i data-feather="map-pin" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label"><?php echo app_lang("address"); ?></div>
                        <div class="ps-stat-value" style="font-size:13px;"><?php echo esc($vendor_info->address); ?></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="row mt20">
                <div class="col-md-7 mb15">
                    <div class="vp-profile-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb10">
                            <div>
                                <h4 class="vp-card-title">Profile Checklist</h4>
                                <div class="text-muted">Required onboarding readiness for procurement review.</div>
                            </div>
                            <div class="vp-progress-score"><?php echo (int) ($profile_checklist["percent"] ?? 0); ?>%</div>
                        </div>
                        <div class="progress mb15" style="height:8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int) ($profile_checklist["percent"] ?? 0); ?>%;"></div>
                        </div>
                        <div class="vp-checklist">
                            <?php foreach (($profile_checklist["items"] ?? []) as $item) { ?>
                                <div class="vp-checklist-row">
                                    <span class="<?php echo !empty($item["done"]) ? "is-done" : "is-pending"; ?>">
                                        <i data-feather="<?php echo !empty($item["done"]) ? "check" : "clock"; ?>" class="icon-14"></i>
                                    </span>
                                    <div>
                                        <strong><?php echo esc($item["label"]); ?></strong>
                                        <small><?php echo esc($item["hint"]); ?></small>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="mt15 p15 border rounded">
                            <strong><?php echo $billing_type === 'renewal' ? 'Registration renewal' : 'Registration payment'; ?></strong>
                            <?php if (!empty($billing['error'])) { ?>
                                <div class="text-danger mt5"><?php echo esc($billing['error']); ?></div>
                            <?php } elseif ($billing_quote) { ?>
                                <div class="mt5"><?php echo esc($billing_quote['currency'] . ' ' . $billing_quote['amount']); ?></div>
                                <div class="text-muted small mt5"><?php echo $billing_settled
                                    ? 'Payment is recorded for this registration period. Revisions do not require another payment.'
                                    : ($billing_zero ? 'Your vendor group has an explicitly configured zero fee.'
                                        : 'Continue to Bank Muscat Smart Gateway. Procurement review starts after payment is verified.'); ?></div>
                            <?php } ?>
                        </div>
                        <?php if ($can_submit_for_review) { ?>
                            <div class="mt15">
                                <button type="button" id="vendor-submit-review-btn" class="btn btn-primary" <?php echo $profile_complete ? "" : "disabled"; ?>>
                                    <i data-feather="credit-card" class="icon-16"></i> <?php echo esc($billing_button); ?>
                                </button>
                                <?php if (!$profile_complete) { ?>
                                    <div class="text-muted mt10"><?php echo app_lang("vendor_profile_incomplete"); ?></div>
                                <?php } ?>
                            </div>
                        <?php } else if ($vendor_status === "submitted") { ?>
                            <div class="alert alert-info mt15 mb0"><?php echo app_lang("vendor_profile_pending_review"); ?></div>
                        <?php } ?>
                    </div>
                </div>
                <div class="col-md-5 mb15">
                    <div class="vp-profile-card">
                        <h4 class="vp-card-title">Document Expiry</h4>
                        <?php if (!$expiring_documents) { ?>
                            <div class="text-muted">No documents expiring within the next 30 days.</div>
                        <?php } ?>
                        <?php foreach ($expiring_documents as $doc) {
                            $expires_ts = strtotime((string) ($doc->expires_at ?? ""));
                            $days = $expires_ts ? (int) floor(($expires_ts - strtotime(date("Y-m-d"))) / 86400) : null;
                            $badge = $days !== null && $days < 0 ? "bg-danger" : "bg-warning text-dark";
                        ?>
                            <div class="vp-expiry-row">
                                <div>
                                    <strong><?php echo esc($doc->document_type_name ?? $doc->original_name ?? "-"); ?></strong>
                                    <small><?php echo !empty($doc->expires_at) ? format_to_date($doc->expires_at, false) : "-"; ?></small>
                                </div>
                                <span class="badge <?php echo $badge; ?>"><?php echo $days !== null && $days < 0 ? "Expired" : $days . "d"; ?></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") feather.replace();

    $("#vendor-submit-review-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);

        $.ajax({
            url: "<?php echo get_uri($billing_type === 'renewal' ? 'vendor_portal/renew_registration' : 'vendor_portal/submit_for_review'); ?>",
            type: "POST",
            dataType: "json",
            data: {
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            },
            success: function (result) {
                if (result && result.success) {
                    if (result.checkout_url) {
                        window.location.assign(result.checkout_url);
                        return;
                    }
                    appAlert.success(result.message || "<?php echo app_lang('record_saved'); ?>");
                    setTimeout(function () {
                        location.reload();
                    }, 600);
                } else {
                    appAlert.error((result && result.message) ? result.message : "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function (xhr) {
                appAlert.error((xhr.responseJSON && xhr.responseJSON.message) || "<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });
});
</script>

<style>
.vp-profile-card {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
    padding: 16px;
    min-height: 100%;
}
.vp-card-title {
    margin: 0 0 5px;
    font-size: 16px;
    color: #0f172a;
}
.vp-progress-score {
    border: 1px solid rgba(34, 197, 94, .24);
    border-radius: 999px;
    background: rgba(34, 197, 94, .08);
    color: #166534;
    font-weight: 800;
    padding: 6px 10px;
}
.vp-checklist {
    display: grid;
    gap: 10px;
}
.vp-checklist-row,
.vp-expiry-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid rgba(15, 23, 42, .07);
    border-radius: 12px;
    padding: 10px 12px;
    background: #fbfdff;
}
.vp-checklist-row {
    justify-content: flex-start;
}
.vp-checklist-row span:first-child {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.vp-checklist-row .is-done {
    background: rgba(34, 197, 94, .12);
    color: #15803d;
}
.vp-checklist-row .is-pending {
    background: rgba(245, 158, 11, .14);
    color: #a16207;
}
.vp-checklist-row small,
.vp-expiry-row small {
    display: block;
    color: #64748b;
    margin-top: 2px;
}
</style>
