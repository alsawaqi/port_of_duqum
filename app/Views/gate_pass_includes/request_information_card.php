<?php
/**
 * Gate pass "Request information" block (aligned with portal request details).
 *
 * Expected: $request (object). Optional: $can_edit_request_core (bool) for portal edit control.
 */
$request = $request ?? null;
if (!$request) {
    return;
}

$can_edit_request_core = !empty($can_edit_request_core);

$visit_from_disp = !empty($request->visit_from) ? format_to_date($request->visit_from) : "-";
$visit_to_disp = !empty($request->visit_to) ? format_to_date($request->visit_to) : "-";
$visit_type_disp = ucwords(str_replace("_", " ", trim((string)($request->visit_type ?? "visitor"))));
$req_type_raw = strtolower(trim((string)($request->request_type ?? "both")));
$request_type_disp = $req_type_raw === "person"
    ? app_lang("gate_pass_request_type_display_person")
    : app_lang("gate_pass_request_type_display_both");
$veh_type_raw = strtolower(trim((string)($request->vehicle_type ?? "none")));
$vehicle_type_disp = ($veh_type_raw === "" || $veh_type_raw === "none") ? "—" : ucfirst($veh_type_raw);
$currency_disp = property_exists($request, "currency") ? trim((string)$request->currency) : "";
$currency_disp = $currency_disp !== "" ? $currency_disp : "—";
$fee_spec_disp = "—";
if (property_exists($request, "fee_amount") && $request->fee_amount !== null && $request->fee_amount !== "" && is_numeric($request->fee_amount)) {
    $fee_spec_disp = ($currency_disp !== "—" ? $currency_disp . " " : "") . number_format((float)$request->fee_amount, 2);
}
$purpose_notes_disp = trim((string)($request->purpose_notes ?? ""));

static $gp_gate_pass_request_information_styles_loaded = false;
if (!$gp_gate_pass_request_information_styles_loaded) {
    $gp_gate_pass_request_information_styles_loaded = true;
    ?>
<style>
/* Shared: gate pass request specification card (portal + inboxes) */
.gp-gate-pass-request-info {
    --gp-d-ink: #0f172a;
    --gp-d-muted: #64748b;
    --gp-d-line: rgba(15,23,42,.08);
}
.gp-gate-pass-request-info .gp-spec-card {
    border: 1px solid var(--gp-d-line);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
}
.gp-gate-pass-request-info .gp-spec-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 18px 22px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.07);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.95) 0%, #fff 100%);
}
.gp-gate-pass-request-info .gp-spec-card-title { font-size: 16px; font-weight: 800; color: var(--gp-d-ink); letter-spacing: -0.01em; }
.gp-gate-pass-request-info .gp-spec-card-actions .gp-spec-edit-btn {
    border-radius: 11px;
    font-weight: 700;
    padding: 9px 16px;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.22);
}
.gp-gate-pass-request-info .gp-spec-card-body { padding: 20px 22px 22px; }
.gp-gate-pass-request-info .gp-spec-field {
    height: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
}
.gp-gate-pass-request-info .gp-spec-field-block { min-height: auto; }
.gp-gate-pass-request-info .gp-spec-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--gp-d-muted);
    margin-bottom: 6px;
}
.gp-gate-pass-request-info .gp-spec-value {
    display: block;
    font-size: 15px;
    font-weight: 650;
    color: var(--gp-d-ink);
    line-height: 1.35;
    word-break: break-word;
}
.gp-gate-pass-request-info .gp-spec-notes {
    font-size: 14px;
    line-height: 1.5;
    color: #334155;
}
</style>
    <?php
}
?>

<div class="gp-gate-pass-request-info">
    <div class="card gp-card gp-spec-card mb15">
        <div class="gp-spec-card-head">
            <div>
                <h4 class="gp-spec-card-title mt0 mb0"><?php echo app_lang("gate_pass_request_information_section"); ?></h4>
                <div class="text-off font-13"><?php echo app_lang("gate_pass_request_information_subtitle"); ?></div>
            </div>
            <?php if ($can_edit_request_core): ?>
                <div class="gp-spec-card-actions">
                    <?php echo modal_anchor(
                        get_uri("gate_pass_portal/request_modal_form"),
                        "<i data-feather='edit-2' class='icon-16'></i> " . app_lang("gate_pass_edit_header_request"),
                        [
                            "class" => "btn btn-primary gp-spec-edit-btn",
                            "data-post-id" => (int)$request->id,
                            "data-modal-title" => app_lang("gate_pass_portal_browser_title"),
                        ]
                    ); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="gp-spec-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("company"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($request->company_name ?? "—"); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("department"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($request->department_name ?? "—"); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("purpose"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($request->purpose_name ?? "—"); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("gate_pass_visit_type"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($visit_type_disp); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("gate_pass_request_type_label"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($request_type_disp); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("gate_pass_vehicle_type_label"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($vehicle_type_disp); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("visit_from"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($visit_from_disp); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("visit_to"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($visit_to_disp); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("currency"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($currency_disp); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gp-spec-field">
                        <span class="gp-spec-label"><?php echo app_lang("fee_amount"); ?></span>
                        <span class="gp-spec-value"><?php echo esc($fee_spec_disp); ?></span>
                    </div>
                </div>
                <div class="col-12">
                    <div class="gp-spec-field gp-spec-field-block">
                        <span class="gp-spec-label"><?php echo app_lang("notes"); ?></span>
                        <div class="gp-spec-notes"><?php echo $purpose_notes_disp !== "" ? nl2br(esc($purpose_notes_disp)) : "—"; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
