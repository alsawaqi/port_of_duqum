<div id="page-content" class="page-wrapper clearfix gp-pro-page gp-sec-hub-page">
    <div class="gp-sec-hub-page-inner p15">
        <?php echo view("gate_pass_security_inbox/_hub_nav", ["active" => $security_nav_active ?? "dashboard"]); ?>

        <div class="gp-sec-page-head mb15">
            <h1 class="gp-sec-page-title"><?php echo app_lang("gate_pass_security_dashboard"); ?></h1>
            <p class="gp-sec-page-desc text-off"><?php echo app_lang("gate_pass_security_dashboard_hint"); ?></p>
        </div>

        <?php $kpis = $kpis ?? []; ?>
        <?php echo view("gate_pass_includes/dashboard_kpis_widget", ["kpis" => $kpis]); ?>

        <div class="row g-3 mt5">
            <div class="col-md-6">
                <a class="card gp-sec-action-card h-100 text-decoration-none" href="<?php echo get_uri("gate_pass_security_inbox"); ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="gp-sec-action-icon"><i data-feather="list" class="icon-22"></i></span>
                        <div>
                            <div class="fw-bold text-default"><?php echo app_lang("gate_pass_security_nav_requests"); ?></div>
                            <div class="text-off small"><?php echo app_lang("gate_pass_security_nav_requests_hint"); ?></div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a class="card gp-sec-action-card h-100 text-decoration-none" href="<?php echo get_uri("gate_pass_security_inbox/scan"); ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="gp-sec-action-icon gp-sec-action-icon--scan"><i data-feather="camera" class="icon-22"></i></span>
                        <div>
                            <div class="fw-bold text-default"><?php echo app_lang("gate_pass_security_scan_qr"); ?></div>
                            <div class="text-off small"><?php echo app_lang("gate_pass_security_nav_scan_hint"); ?></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
<style>
.gp-sec-hub-page .gp-sec-hub-page-inner { max-width: 1100px; }
.gp-sec-action-card {
    border-radius: 14px;
    border: 1px solid rgba(15, 23, 42, .08);
    box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
    transition: transform .15s ease, box-shadow .15s ease;
    color: inherit;
}
.gp-sec-action-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 23, 42, .08); }
.gp-sec-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(37, 99, 235, .1);
    color: #1d4ed8;
    flex-shrink: 0;
}
.gp-sec-action-icon--scan { background: rgba(22, 163, 74, .12); color: #15803d; }
</style>
<script>
$(document).ready(function () { if (typeof feather !== "undefined") feather.replace(); });
</script>
