<?php
$is_requester = !empty($is_requester);
$can_approve = !empty($can_approve);
$show_approval_box = ($request->status === "submitted" || $request->status === "returned");

$status = $request->status ?? "";
$stage  = $request->stage ?? "";

if (!isset($status_label)) {
    $s = strtolower(trim((string) $status));
    $status_label = ($s === "" || $s === "-") ? "-" : (($s === "rop_approved") ? "ROP Approved" : ucwords(str_replace("_", " ", $s)));
}

$status_class = "badge-soft-secondary";
if ($status === "submitted") $status_class = "badge-soft-warning";
if ($status === "returned")  $status_class = "badge-soft-danger";
if ($status === "approved" || $status === "department_approved" || $status === "commercial_approved" || $status === "security_approved" || $status === "rop_approved") $status_class = "badge-soft-success";
if ($status === "rejected")  $status_class = "badge-soft-danger";
if ($status === "issued")    $status_class = "badge-soft-success";

$show_add_visitor_vehicle = $is_requester && $stage === "department";
$show_pay_button = $is_requester && $status === "department_approved";
$show_fee_block = ($status === "department_approved" || $status === "commercial_approved");
$fee_amount_display = "-";
if ($show_fee_block && property_exists($request, "fee_amount") && ($request->fee_amount !== null && $request->fee_amount !== "")) {
    $currency = property_exists($request, "currency") ? trim((string)$request->currency) : "";
    $amount = is_numeric($request->fee_amount) ? number_format((float)$request->fee_amount, 2) : (string)$request->fee_amount;
    $fee_amount_display = $currency ? ($currency . " " . $amount) : $amount;
}

$stage_class = "badge-soft-secondary";
if ($stage === "department") $stage_class = "badge-soft-primary";
if ($stage === "commercial") $stage_class = "badge-soft-info";
if ($stage === "security")   $stage_class = "badge-soft-warning";
if ($stage === "rop")        $stage_class = "badge-soft-danger";
if ($stage === "issued")     $stage_class = "badge-soft-success";

$show_qr_section = ($status === "rop_approved" && !empty($gate_pass) && !empty($gate_pass->qr_token));
$qr_image_url = $show_qr_section ? ("https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($gate_pass->qr_token)) : "";
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page gp-portal-pro">

    <!-- Premium Header -->
    <div class="card gp-card mb15">
        <div class="gp-header">
            <div class="gp-title">
                <div class="gp-title-row">
                    <h2 class="gp-h2 mb0">
                        <?php echo app_lang("gate_pass_request_details"); ?>
                        <span class="text-off">#<?php echo esc($request->reference); ?></span>
                    </h2>

                    <div class="gp-badges">
                        <?php if ($status): ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <i data-feather="info" class="icon-14"></i>
                                <?php echo esc($status_label); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($stage): ?>
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
                <?php if ($show_add_visitor_vehicle): ?>
                    <div class="gp-actions-row">
                        <?php echo modal_anchor(
                            get_uri("gate_pass_portal/visitor_modal_form"),
                            "<i data-feather='user-plus' class='icon-16'></i> " . app_lang("add_visitor"),
                            ["class" => "btn btn-default gp-btn gp-pro-btn-secondary", "data-post-gate_pass_request_id" => $request->id, "title" => "Add Visitor"]
                        ); ?>
                        <?php echo modal_anchor(
                            get_uri("gate_pass_portal/vehicle_modal_form"),
                            "<i data-feather='truck' class='icon-16'></i> " . app_lang("add_vehicle"),
                            ["class" => "btn btn-default gp-btn gp-pro-btn-secondary", "data-post-gate_pass_request_id" => $request->id, "title" => "Add Vehicle"]
                        ); ?>
                    </div>
                <?php endif; ?>
                <?php if ($show_pay_button): ?>
                    <div class="gp-actions-row">
                        <button type="button" id="gp-pay-btn" class="btn btn-primary gp-btn-primary gp-pro-btn">
                            <i data-feather="credit-card" class="icon-16"></i> <?php echo app_lang("pay"); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Key Info Grid -->
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
                <?php if ($show_fee_block): ?>
                <div class="gp-info">
                    <div class="gp-info-icon"><i data-feather="dollar-sign" class="icon-18"></i></div>
                    <div>
                        <div class="gp-label"><?php echo app_lang("fee_amount"); ?></div>
                        <div class="gp-value"><?php echo esc($fee_amount_display); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approval Box -->
    <?php if ($show_approval_box && $can_approve): ?>
        <div class="card gp-card mb15 gp-accent">
            <div class="gp-section-title">
                <div class="d-flex align-items-center">
                    <span class="gp-section-icon"><i data-feather="check-square" class="icon-18"></i></span>
                    <div>
                        <h4 class="mt0 mb0"><?php echo app_lang("department_review"); ?></h4>
                        <div class="text-off font-13"><?php echo app_lang("add_comment_if_needed"); ?></div>
                    </div>
                </div>
            </div>

            <div class="p20 pt10">
                <?php echo form_open(get_uri("gate_pass_portal/save_approval"), ["id" => "gp-portal-approval-form", "class" => "general-form", "role" => "form"]); ?>
                <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

                <div class="row">
                    <div class="col-md-4 col-sm-12 mb15">
                        <label class="form-label gp-label2"><?php echo app_lang("decision"); ?></label>
                        <select name="decision" id="gp-decision" class="form-control gp-control" required>
                            <option value="approved"><?php echo app_lang("approve"); ?></option>
                            <option value="returned"><?php echo app_lang("return_for_review"); ?></option>
                            <option value="rejected"><?php echo app_lang("reject"); ?></option>
                        </select>
                        <div class="gp-hint"><?php echo app_lang("workflow_action_hint"); ?></div>
                    </div>

                    <div class="col-md-8 col-sm-12 mb15">
                        <label class="form-label gp-label2"><?php echo app_lang("comment"); ?></label>
                        <textarea name="comment" id="gp-comment" class="form-control gp-control" rows="3"
                                  placeholder="<?php echo app_lang("comment"); ?>..."></textarea>
                        <div class="gp-hint" id="gp-comment-hint">
                            <?php echo app_lang("comment_optional"); ?>
                        </div>
                    </div>
                </div>

                <div class="gp-footer">
                    <button type="submit" class="btn btn-primary gp-btn-primary gp-pro-btn">
                        <i data-feather="send" class="icon-14"></i> <?php echo app_lang("submit"); ?>
                    </button>
                </div>

                <?php echo form_close(); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Approval History (Timeline) -->
    <?php if (!empty($approval_history) && is_array($approval_history)): ?>
        <div class="card gp-card mb15">
            <div class="gp-section-title">
                <div class="d-flex align-items-center">
                    <span class="gp-section-icon"><i data-feather="activity" class="icon-18"></i></span>
                    <div>
                        <h4 class="mt0 mb0"><?php echo app_lang("approval_history"); ?></h4>
                        <div class="text-off font-13"><?php echo app_lang("all_actions_logged"); ?></div>
                    </div>
                </div>
            </div>

            <div class="p20 pt10">
                <div class="gp-timeline">
                    <?php foreach ($approval_history as $a): ?>
                        <?php
                        $decision = $a->decision ?? "";
                        $badge = "badge-soft-secondary";
                        $icon  = "circle";

                        if ($decision === "approved") { $badge = "badge-soft-success"; $icon = "check-circle"; }
                        if ($decision === "rejected") { $badge = "badge-soft-danger";  $icon = "x-circle"; }
                        if ($decision === "returned") { $badge = "badge-soft-warning"; $icon = "corner-up-left"; }

                        $who = trim(($a->first_name ?? "") . " " . ($a->last_name ?? ""));
                        $when = !empty($a->decided_at) ? format_to_datetime($a->decided_at) : "-";
                        $reason_title = trim((string)($a->reason_title ?? ""));
                        ?>
                        <div class="gp-timeline-item">
                            <div class="gp-timeline-dot <?php echo $badge; ?>">
                                <i data-feather="<?php echo $icon; ?>" class="icon-16"></i>
                            </div>

                            <div class="gp-timeline-body">
                                <div class="gp-timeline-top">
                                    <div class="gp-timeline-title">
                                        <span class="badge <?php echo $badge; ?>"><?php echo esc($decision ?: "-"); ?></span>
                                        <span class="text-off">•</span>
                                        <span class="text-off"><?php echo app_lang("stage"); ?>:</span>
                                        <b><?php echo esc($a->stage ?? "-"); ?></b>
                                    </div>
                                    <div class="gp-timeline-meta">
                                        <span class="text-off"><?php echo esc($who ?: "-"); ?></span>
                                        <span class="gp-dot">•</span>
                                        <span class="text-off"><?php echo esc($when); ?></span>
                                    </div>
                                </div>

                                <?php if ($reason_title !== "" || !empty($a->comment)): ?>
                                    <div class="gp-timeline-comment">
                                        <?php if ($reason_title !== ""): ?>
                                            <div><b><?php echo app_lang("reason"); ?>:</b> <?php echo esc($reason_title); ?></div>
                                        <?php endif; ?>
                                        <?php echo nl2br(esc($a->comment)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="gp-timeline-comment gp-muted">
                                        <?php echo app_lang("no_comment"); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Gate Pass QR Code (only when status is rop_approved) -->
    <?php if ($show_qr_section): ?>
        <div class="card gp-card mb15">
            <div class="gp-section-title">
                <div class="d-flex align-items-center">
                    <span class="gp-section-icon"><i data-feather="maximize-2" class="icon-18"></i></span>
                    <div>
                        <h4 class="mt0 mb0"><?php echo app_lang("gate_pass_qr_code"); ?></h4>
                        <div class="text-off font-13"><?php echo app_lang("gate_pass_qr_code_hint"); ?></div>
                    </div>
                </div>
            </div>
            <div class="p20 pt10">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <?php if ($qr_image_url): ?>
                        <div class="gp-qr-wrap">
                            <img src="<?php echo esc($qr_image_url); ?>" alt="QR Code" class="gp-qr-img" width="200" height="200" />
                        </div>
                    <?php endif; ?>
                    <div>
                        <a href="<?php echo get_uri("gate_pass_portal/download_qr/" . (int)$request->id); ?>" class="btn btn-primary gp-btn-primary gp-pro-btn" target="_blank" download>
                            <i data-feather="download" class="icon-16"></i> <?php echo app_lang("download_qr_code"); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Visitors -->
    <div class="card gp-card mb15">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="users" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("visitors"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("visitor_list"); ?></div>
                </div>
            </div>
        </div>

        <div class="table-responsive p15 p-sm-10 gp-table-wrap">
            <table id="gp-visitors-table" class="display" width="100%" cellspacing="0"></table>
        </div>
    </div>

    <!-- Vehicles -->
    <div class="card gp-card">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="truck" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("vehicles"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("vehicle_list"); ?></div>
                </div>
            </div>
        </div>

        <div class="table-responsive p15 p-sm-10 gp-table-wrap">
            <table id="gp-vehicles-table" class="display" width="100%" cellspacing="0"></table>
        </div>
    </div>

</div>

<style>
/* ===== Gate Pass Premium UI ===== */
.gp-card { border-radius: 14px; border: 1px solid rgba(0,0,0,.06); overflow: hidden; }
.gp-header { padding: 18px 20px; background: linear-gradient(180deg, rgba(0,0,0,.03), rgba(0,0,0,0)); border-bottom: 1px solid rgba(0,0,0,.06); display:flex; gap:16px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; }
.gp-title { min-width: 260px; flex: 1; }
.gp-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-h2 { font-size: 20px; font-weight: 700; letter-spacing: .2px; }
.gp-badges { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.gp-sub { margin-top: 10px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.gp-chip { display:inline-flex; align-items:center; gap:8px; padding: 6px 10px; border-radius: 999px; background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.06); font-size: 13px; }
.gp-dot { opacity: .45; }

.gp-actions { display:flex; align-items:center; }
.gp-actions-row { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.gp-btn { border-radius: 10px; font-weight: 600; transition: all .2s ease; }
.gp-btn:hover { transform: translateY(-1px); }
.gp-btn-primary { border-radius: 12px; padding: 10px 14px; font-weight: 600; box-shadow: 0 8px 18px rgba(37, 99, 235, .22); }

.gp-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.gp-info { display:flex; gap:12px; padding: 14px; border-radius: 12px; border: 1px solid rgba(0,0,0,.06); background:#fff; }
.gp-info-icon { width:36px; height:36px; border-radius: 10px; background: rgba(0,0,0,.03); display:flex; align-items:center; justify-content:center; flex: 0 0 auto; }
.gp-label { font-size: 12px; letter-spacing:.2px; color: rgba(0,0,0,.55); margin-bottom: 3px; }
.gp-value { font-size: 14px; font-weight: 650; }

.gp-section-title { padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,.06); background: rgba(0,0,0,.02); }
.gp-section-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-section-icon { width:36px; height:36px; border-radius: 12px; background: rgba(0,0,0,.04); display:flex; align-items:center; justify-content:center; margin-right: 10px; }

.gp-accent { border-left: 5px solid rgba(13,110,253,.45); }
.gp-label2 { font-weight: 700; font-size: 13px; }
.gp-control { border-radius: 12px; padding: 10px 12px; }
.gp-hint { font-size: 12px; color: rgba(0,0,0,.55); margin-top: 6px; }
.gp-footer { display:flex; justify-content:flex-end; margin-top: 6px; }

.gp-table-wrap { overflow-x:auto; -webkit-overflow-scrolling: touch; }
#page-content .table-responsive { min-height: 0; }

/* Timeline */
.gp-timeline { display:flex; flex-direction:column; gap:14px; }
.gp-timeline-item { display:flex; gap:12px; }
.gp-timeline-dot { width:40px; height:40px; border-radius: 14px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(0,0,0,.06); background:#fff; flex: 0 0 auto; }
.gp-timeline-body { flex:1; border:1px solid rgba(0,0,0,.06); border-radius: 14px; padding: 12px 14px; background:#fff; }
.gp-timeline-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-timeline-title { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.gp-timeline-meta { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.gp-timeline-comment { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.06); }
.gp-muted { color: rgba(0,0,0,.55); }
.gp-qr-wrap { padding: 8px; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
.gp-qr-img { display: block; border-radius: 8px; }

/* Soft badges */
.badge-soft-secondary { background: rgba(108,117,125,.15); color:#2f343a; border:1px solid rgba(108,117,125,.25); }
.badge-soft-primary { background: rgba(13,110,253,.15); color:#084298; border:1px solid rgba(13,110,253,.25); }
.badge-soft-success { background: rgba(25,135,84,.15); color:#0f5132; border:1px solid rgba(25,135,84,.25); }
.badge-soft-warning { background: rgba(255,193,7,.20); color:#664d03; border:1px solid rgba(255,193,7,.35); }
.badge-soft-danger { background: rgba(220,53,69,.15); color:#842029; border:1px solid rgba(220,53,69,.25); }
.badge-soft-info { background: rgba(13,202,240,.15); color:#055160; border:1px solid rgba(13,202,240,.25); }

/* Responsive */
@media (max-width: 1200px) {
  .gp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 576px) {
  .gp-grid { grid-template-columns: 1fr; }
  .gp-header { padding: 16px; }
  .gp-section-title { padding: 14px 16px; }
  .gp-timeline-dot { width:36px; height:36px; border-radius: 12px; }
}
</style>

<script>
$(document).ready(function () {

    $("#gp-visitors-table").appTable({
        source: "<?php echo_uri('gate_pass_portal/visitors_list_data/'.$request->id); ?>",
        columns: [
            { title: "<?php echo app_lang('full_name'); ?>" },
            { title: "<?php echo app_lang('id_type'); ?>" },
            { title: "<?php echo app_lang('id_number'); ?>" },
            { title: "<?php echo app_lang('nationality'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('role'); ?>" },
            { title: "<?php echo app_lang('blocked'); ?>" },
            { title: "<?php echo app_lang('reason'); ?>" },
            { title: "<?php echo app_lang('primary'); ?>", class: "text-center" },
            { title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120" }
        ]
    });

    $("#gp-vehicles-table").appTable({
        source: "<?php echo_uri('gate_pass_portal/vehicles_list_data/'.$request->id); ?>",
        columns: [
            { title: "<?php echo app_lang('plate_no'); ?>" },
            { title: "<?php echo app_lang('make'); ?>" },
            { title: "<?php echo app_lang('model'); ?>" },
            { title: "<?php echo app_lang('color'); ?>" },
            { title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120" }
        ]
    });

    <?php if ($show_pay_button): ?>
    $("#gp-pay-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);
        $.ajax({
            url: "<?php echo get_uri('gate_pass_portal/save_payment'); ?>",
            type: "POST",
            data: { gate_pass_request_id: "<?php echo (int)$request->id; ?>", "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>" },
            dataType: "json",
            success: function (res) {
                if (res.success) {
                    appAlert.success(res.message);
                    window.location.reload();
                } else {
                    appAlert.error(res.message || "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function () {
                appAlert.error("<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });
    <?php endif; ?>

    <?php if ($show_approval_box && $can_approve): ?>
    // Make comment required for Returned/Rejected (optional but professional)
    function updateCommentRequirement() {
        var d = $("#gp-decision").val();
        var require = (d === "returned" || d === "rejected");
        $("#gp-comment").prop("required", require);
        $("#gp-comment-hint").text(require ? "<?php echo app_lang('comment_required_for_this_action'); ?>" : "<?php echo app_lang('comment_optional'); ?>");
    }
    $("#gp-decision").on("change", updateCommentRequirement);
    updateCommentRequirement();

    $("#gp-portal-approval-form").appForm({
        onSuccess: function () {
            window.location.reload();
        }
    });
    <?php endif; ?>

    if (typeof feather !== "undefined") feather.replace();
});
</script>
