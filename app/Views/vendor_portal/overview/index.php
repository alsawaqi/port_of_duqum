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

                <div class="ps-stat-card" style="animation-delay:.05s;">
                    <div class="ps-stat-icon">
                        <i data-feather="tag" class="icon-20"></i>
                    </div>
                    <div>
                        <div class="ps-stat-label"><?php echo app_lang("status"); ?></div>
                        <div class="ps-stat-value"><?php echo esc($vendor_info->status ? ucfirst($vendor_info->status) : '-'); ?></div>
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

        </div>
    </div>

</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") feather.replace();
});
</script>
