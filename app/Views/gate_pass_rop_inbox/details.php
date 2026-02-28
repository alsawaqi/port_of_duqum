<div id="page-content" class="page-wrapper clearfix gp-details-page">
    <?php
    $status = $request->status ?? "";
    $stage  = $request->stage ?? "";

    if (!isset($status_label)) {
        $s = strtolower(trim((string) $status));
        $status_label = ($s === "" || $s === "-") ? "-" : (($s === "rop_approved") ? "ROP Approved" : ucwords(str_replace("_", " ", $s)));
    }

    $status_class = "gp-badge-soft-secondary";
    if ($status === "submitted") $status_class = "gp-badge-soft-warning";
    if ($status === "returned")  $status_class = "gp-badge-soft-danger";
    if (in_array($status, ["department_approved", "commercial_approved", "security_approved", "rop_approved"])) $status_class = "gp-badge-soft-success";
    if ($status === "rejected")  $status_class = "gp-badge-soft-danger";

    $stage_class = "gp-badge-soft-secondary";
    if ($stage === "department") $stage_class = "gp-badge-soft-primary";
    if ($stage === "commercial") $stage_class = "gp-badge-soft-info";
    if ($stage === "security")   $stage_class = "gp-badge-soft-warning";
    if ($stage === "rop")        $stage_class = "gp-badge-soft-danger";
    if ($stage === "issued")     $stage_class = "gp-badge-soft-success";

    $show_review_btn = ($status === "security_approved" && $stage === "rop");
    ?>

    <div class="card gp-card mb15">
        <div class="gp-header">
            <div class="gp-title">
                <div class="gp-title-row">
                    <h2 class="gp-h2 mb0">
                        <?php echo app_lang("gate_pass_request_details"); ?>
                        <span class="text-off">#<?php echo esc($request->reference); ?></span>
                    </h2>
                    <div class="gp-badges">
                        <?php if (!empty($status)): ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <i data-feather="info" class="icon-14"></i>
                                <?php echo esc($status_label); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($stage)): ?>
                            <span class="badge <?php echo $stage_class; ?>">
                                <i data-feather="layers" class="icon-14"></i>
                                <?php echo esc($stage); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="gp-sub">
                    <span class="gp-chip">
                        <i data-feather="briefcase" class="icon-14"></i>
                        <?php echo esc($request->company_name ?? "-"); ?>
                    </span>
                    <span class="gp-dot">•</span>
                    <span class="gp-chip">
                        <i data-feather="grid" class="icon-14"></i>
                        <?php echo esc($request->department_name ?? "-"); ?>
                    </span>
                    <span class="gp-dot">•</span>
                    <span class="gp-chip">
                        <i data-feather="clipboard" class="icon-14"></i>
                        <?php echo esc($request->purpose_name ?? "-"); ?>
                    </span>
                </div>
            </div>
            <div class="gp-actions">
                <div class="title-button-group gp-actions-row">
                    <?php if ($show_review_btn): ?>
                        <?php echo modal_anchor(
                            get_uri("gate_pass_rop_inbox/approval_modal_form"),
                            "<i data-feather='check-square' class='icon-16'></i> " . app_lang("review"),
                            ["class" => "btn btn-primary gp-btn-primary", "title" => app_lang("review"), "data-post-id" => $request->id]
                        ); ?>
                    <?php endif; ?>
                    <?php echo modal_anchor(
                        get_uri("gate_pass_rop_inbox/visitor_block_modal_form"),
                        "<i data-feather='slash' class='icon-16'></i> Block Visitor",
                        ["class" => "btn btn-warning gp-btn", "title" => "Block/Unblock Visitor", "data-post-request_id" => $request->id]
                    ); ?>
                    <?php echo modal_anchor(
                        get_uri("gate_pass_rop_inbox/approval_history_modal"),
                        "<i data-feather='history' class='icon-16'></i> " . app_lang("approval_history"),
                        ["class" => "btn btn-default gp-btn", "title" => app_lang("approval_history"), "data-post-id" => $request->id]
                    ); ?>
                </div>
            </div>
        </div>

        <div class="p20 pt10">
            <div class="gp-grid">
                <div class="gp-info">
                    <div class="gp-info-icon"><i data-feather="calendar" class="icon-18"></i></div>
                    <div>
                        <div class="gp-label"><?php echo app_lang("visit_from"); ?></div>
                        <div class="gp-value"><?php echo esc($request->visit_from ?? "-"); ?></div>
                    </div>
                </div>
                <div class="gp-info">
                    <div class="gp-info-icon"><i data-feather="calendar" class="icon-18"></i></div>
                    <div>
                        <div class="gp-label"><?php echo app_lang("visit_to"); ?></div>
                        <div class="gp-value"><?php echo esc($request->visit_to ?? "-"); ?></div>
                    </div>
                </div>
                <div class="gp-info">
                    <div class="gp-info-icon"><i data-feather="tag" class="icon-18"></i></div>
                    <div>
                        <div class="gp-label"><?php echo app_lang("status"); ?></div>
                        <div class="gp-value">
                            <span class="badge <?php echo $status_class; ?>"><?php echo esc($status_label); ?></span>
                        </div>
                    </div>
                </div>
                <div class="gp-info">
                    <div class="gp-info-icon"><i data-feather="layers" class="icon-18"></i></div>
                    <div>
                        <div class="gp-label"><?php echo app_lang("stage"); ?></div>
                        <div class="gp-value">
                            <span class="badge <?php echo $stage_class; ?>"><?php echo esc($stage ?: "-"); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="gp-stepper mt15">
                <?php
                $steps = ["department", "commercial", "security", "rop", "issued"];
                $current_index = array_search(strtolower($stage), $steps);
                if ($current_index === false) $current_index = -1;
                foreach ($steps as $i => $s):
                    $is_done = ($current_index >= $i);
                    $is_active = ($current_index === $i);
                    $cls = $is_active ? "active" : ($is_done ? "done" : "");
                ?>
                    <div class="gp-step <?php echo $cls; ?>">
                        <div class="gp-step-dot">
                            <?php if ($is_done): ?>
                                <i data-feather="check" class="icon-14"></i>
                            <?php else: ?>
                                <span><?php echo $i + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="gp-step-label"><?php echo esc(ucfirst($s)); ?></div>
                        <?php if ($i < count($steps) - 1): ?>
                            <div class="gp-step-line"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="gp-rop-visitors-card" class="card gp-card mb15">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="users" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("visitors"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("list"); ?></div>
                </div>
            </div>
        </div>
        <div class="table-responsive p15 p-sm-10 gp-table-wrap">
            <table id="gp-rop-visitors-table" class="display" width="100%" cellspacing="0"></table>
        </div>
    </div>

    <div id="gp-rop-vehicles-card" class="card gp-card mb15">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="truck" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("vehicles"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("list"); ?></div>
                </div>
            </div>
        </div>
        <div class="table-responsive p15 p-sm-10 gp-table-wrap">
            <table id="gp-rop-vehicles-table" class="display" width="100%" cellspacing="0"></table>
        </div>
    </div>
</div>

<style>
.gp-details-page#page-content { overflow-y: auto !important; overflow-x: hidden; height: auto !important; min-height: 100%; padding-bottom: 40px; }
.gp-card { border-radius: 14px; border: 1px solid rgba(0,0,0,.06); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.gp-header { padding: 18px 20px; background: linear-gradient(180deg, rgba(0,0,0,.03), rgba(0,0,0,0)); border-bottom: 1px solid rgba(0,0,0,.06); display:flex; gap:16px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; }
.gp-title { min-width: 260px; flex: 1; }
.gp-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-h2 { font-size: 20px; font-weight: 800; letter-spacing: .2px; }
.gp-badges { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.gp-sub { margin-top: 10px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.gp-chip { display:inline-flex; align-items:center; gap:8px; padding: 6px 10px; border-radius: 999px; background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.06); font-size: 13px; }
.gp-dot { opacity: .45; }
.gp-table-wrap { overflow-x: auto !important; overflow-y: visible !important; -webkit-overflow-scrolling: touch; max-width: 100%; }
.gp-table-wrap .dataTables_wrapper { overflow: visible !important; }
.gp-table-wrap .dataTables_scrollBody { overflow-x: auto !important; overflow-y: visible !important; max-height: none !important; height: auto !important; }
.gp-actions { display:flex; align-items:center; }
.gp-actions-row { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.gp-btn { border-radius: 10px; }
.gp-btn-primary { border-radius: 12px; padding: 10px 14px; font-weight: 650; }
.gp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.gp-info { display:flex; gap:12px; padding: 14px; border-radius: 12px; border: 1px solid rgba(0,0,0,.06); background:#fff; transition: all 0.2s ease; }
.gp-info:hover { border-color: rgba(0,0,0,.12); box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.gp-info-icon { width:36px; height:36px; border-radius: 10px; background: rgba(0,0,0,.03); display:flex; align-items:center; justify-content:center; flex: 0 0 auto; }
.gp-label { font-size: 12px; letter-spacing:.2px; color: rgba(0,0,0,.55); margin-bottom: 3px; }
.gp-value { font-size: 14px; font-weight: 650; }
.gp-section-title { padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,.06); background: rgba(0,0,0,.02); }
.gp-section-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-section-icon { width:36px; height:36px; border-radius: 12px; background: rgba(0,0,0,.04); display:flex; align-items:center; justify-content:center; margin-right: 10px; }
.gp-badge-soft-secondary { background: rgba(108,117,125,.15); color:#2f343a; border:1px solid rgba(108,117,125,.25); }
.gp-badge-soft-primary { background: rgba(13,110,253,.15); color:#084298; border:1px solid rgba(13,110,253,.25); }
.gp-badge-soft-success { background: rgba(25,135,84,.15); color:#0f5132; border:1px solid rgba(25,135,84,.25); }
.gp-badge-soft-warning { background: rgba(255,193,7,.20); color:#664d03; border:1px solid rgba(255,193,7,.35); }
.gp-badge-soft-danger { background: rgba(220,53,69,.15); color:#842029; border:1px solid rgba(220,53,69,.25); }
.gp-badge-soft-info { background: rgba(13,202,240,.15); color:#055160; border:1px solid rgba(13,202,240,.25); }
.gp-stepper { display:flex; gap:0; align-items:center; flex-wrap:wrap; }
.gp-step { display:flex; align-items:center; gap:10px; position:relative; padding: 6px 10px; }
.gp-step-dot { width:28px; height:28px; border-radius: 10px; border:1px solid rgba(0,0,0,.12); background:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; }
.gp-step-line { width:28px; height:2px; background: rgba(0,0,0,.12); margin-left: 10px; }
.gp-step-label { font-size: 13px; color: rgba(0,0,0,.65); font-weight: 650; }
.gp-step.done .gp-step-dot { background: rgba(25,135,84,.12); border-color: rgba(25,135,84,.35); color:#0f5132; }
.gp-step.active .gp-step-dot { background: rgba(13,110,253,.12); border-color: rgba(13,110,253,.35); color:#084298; }
.gp-step.done .gp-step-line { background: rgba(25,135,84,.35); }
.gp-step.active .gp-step-line { background: rgba(13,110,253,.35); }
@media (max-width: 576px) { .gp-header { padding: 16px; } .gp-actions-row { flex-direction: column; } .gp-actions-row .btn { width: 100%; } }
</style>

<script>
$(document).ready(function () {
    var requestId = <?php echo (int)($request->id ?? 0); ?>;

    $("#gp-rop-visitors-table").appTable({
        source: "<?php echo_uri('gate_pass_rop_inbox/visitors_list_data/'); ?>" + requestId,
        scrollY: false,
        scrollCollapse: false,
        columns: [
            { title: "<?php echo app_lang('full_name'); ?>", data: 0 },
            { title: "<?php echo app_lang('id_type'); ?>", data: 1 },
            { title: "<?php echo app_lang('id_number'); ?>", data: 2 },
            { title: "<?php echo app_lang('nationality'); ?>", data: 3 },
            { title: "<?php echo app_lang('phone'); ?>", data: 4 },
            { title: "<?php echo app_lang('role'); ?>", data: 5 },
            { title: "<?php echo app_lang('blocked'); ?>", data: 6, class: "text-center" },
            { title: "<?php echo app_lang('reason'); ?>", data: 7 },
            { title: "<?php echo app_lang('primary'); ?>", data: 8, class: "text-center" }
        ],
        order: [[0, "asc"]]
    });

    $("#gp-rop-vehicles-table").appTable({
        source: "<?php echo_uri('gate_pass_rop_inbox/vehicles_list_data/'); ?>" + requestId,
        scrollY: false,
        scrollCollapse: false,
        columns: [
            { title: "<?php echo app_lang('plate_no'); ?>", data: 0 },
            { title: "<?php echo app_lang('make'); ?>", data: 1 },
            { title: "<?php echo app_lang('model'); ?>", data: 2 },
            { title: "<?php echo app_lang('color'); ?>", data: 3 }
        ],
        order: [[0, "asc"]]
    });

    setTimeout(function() {
        $('.gp-table-wrap .dataTables_scrollBody').css({ 'overflow-y': 'visible', 'max-height': 'none', 'height': 'auto' });
    }, 100);

    if (typeof feather !== "undefined") feather.replace();
});
</script>
