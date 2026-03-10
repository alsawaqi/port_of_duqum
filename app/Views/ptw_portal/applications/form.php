<?php
$app          = $model_info ?? null;
$responses    = $responses_index ?? [];
$duration_days = $duration_days ?? null;
$errors       = $ptw_errors ?? [];
$field_errors = $ptw_field_errors ?? [];
$old          = $ptw_old_input ?? [];
$submit_stage_label = $submit_stage_label ?? 'HSSE';

// Helper: get old input value (falls back to model, then default)
function ptw_old(string $field, $model_val = '', array $old = []): string {
    return esc(isset($old[$field]) ? $old[$field] : $model_val);
}

// Helper: get response for a requirement definition
function ptw_resp($responses, $def_id) {
    return get_array_value($responses, (int)$def_id);
}

// Helper: field error class + message
function ptw_field_err(string $field, array $field_errors): string {
    if (!isset($field_errors[$field])) return '';
    return '<div class="ptw-field-error"><i data-feather="alert-circle" class="icon-12"></i> ' . esc($field_errors[$field]) . '</div>';
}

function ptw_input_class(string $field, array $field_errors): string {
    return isset($field_errors[$field]) ? ' is-invalid' : '';
}

// Determine which step has errors for auto-scroll
$step_has_error = [1 => false, 2 => false, 3 => false, 4 => false, 5 => false, 6 => false];
$step1_fields = ['company_name','applicant_name','applicant_position','contact_phone','contact_email'];
$step2_fields = ['work_description','work_from','work_to','exact_location','work_supervisor_name','supervisor_contact_details','total_workers'];
$step6_fields = ['declaration_agreed','declaration_responsible_name','declaration_function','signature_file'];
foreach ($step1_fields as $f) { if (isset($field_errors[$f])) $step_has_error[1] = true; }
foreach ($step2_fields as $f) { if (isset($field_errors[$f])) $step_has_error[2] = true; }
foreach ($field_errors as $k => $v) {
    if (strpos($k, 'req_') === 0) { $step_has_error[3] = true; $step_has_error[4] = true; $step_has_error[5] = true; }
}
foreach ($step6_fields as $f) { if (isset($field_errors[$f])) $step_has_error[6] = true; }

$first_error_step = 1;
foreach ($step_has_error as $s => $has) { if ($has) { $first_error_step = $s; break; } }
?>

<style>
/* ── PTW Wizard Styles ─────────────────────────────────────────────────── */
.ptw-wizard-wrap { max-width: 960px; margin: 0 auto; }

/* Progress stepper */
.ptw-stepper { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 32px; position: relative; }
.ptw-stepper::before {
    content: '';
    position: absolute;
    top: 20px; left: 40px; right: 40px;
    height: 2px;
    background: #e2e8f0;
    z-index: 0;
}
.ptw-step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; z-index: 1; cursor: pointer; }
.ptw-step-circle {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #94a3b8;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px;
    border: 2px solid #e2e8f0;
    transition: all .25s;
}
.ptw-step.active .ptw-step-circle  { background: #3b82f6; border-color: #3b82f6; color: #fff; box-shadow: 0 0 0 4px rgba(59,130,246,.18); }
.ptw-step.done .ptw-step-circle    { background: #10b981; border-color: #10b981; color: #fff; }
.ptw-step.error .ptw-step-circle   { background: #ef4444; border-color: #ef4444; color: #fff; }
.ptw-step-label { font-size: 11px; color: #64748b; margin-top: 6px; text-align: center; font-weight: 500; max-width: 80px; line-height: 1.3; }
.ptw-step.active .ptw-step-label   { color: #3b82f6; font-weight: 600; }
.ptw-step.error .ptw-step-label    { color: #ef4444; }

/* Step panels */
.ptw-panel { display: none; }
.ptw-panel.active { display: block; animation: ptw-fadein .2s ease; }
@keyframes ptw-fadein { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

/* Section card */
.ptw-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.ptw-card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 20px;
    display: flex; align-items: center; gap: 10px;
}
.ptw-card-header h4 { margin: 0; font-size: 15px; font-weight: 600; color: #1e293b; }
.ptw-card-header .section-badge {
    background: #3b82f6; color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 2px 8px; border-radius: 20px;
}
.ptw-card-body { padding: 20px; }

/* Field error */
.ptw-field-error { color: #ef4444; font-size: 12px; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
.ptw-field-error svg { flex-shrink: 0; }
.form-control.is-invalid, .form-select.is-invalid { border-color: #ef4444 !important; background-image: none !important; }
.form-check-input.is-invalid { border-color: #ef4444 !important; }

/* Error summary banner */
.ptw-error-banner {
    background: #fef2f2; border: 1px solid #fecaca;
    border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
}
.ptw-error-banner .ptw-err-title { font-weight: 600; color: #dc2626; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.ptw-error-banner ul { margin: 0; padding-left: 18px; }
.ptw-error-banner li { color: #b91c1c; font-size: 13px; line-height: 1.8; }

/* Navigation buttons */
.ptw-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
.ptw-nav .btn { min-width: 120px; }

/* Requirement rows */
.ptw-req-row {
    border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 12px 14px; margin-bottom: 10px;
    transition: border-color .2s, background .2s;
}
.ptw-req-row:hover { border-color: #93c5fd; background: #f8faff; }
.ptw-req-row.has-error { border-color: #fca5a5; background: #fff5f5; }
.ptw-req-row.is-checked { border-color: #6ee7b7; background: #f0fdf4; }

/* PPE / Prep grid */
.ptw-check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.ptw-check-item {
    border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 12px; cursor: pointer;
    transition: all .2s;
}
.ptw-check-item:hover { border-color: #93c5fd; background: #f8faff; }
.ptw-check-item.is-checked { border-color: #6ee7b7; background: #f0fdf4; }
.ptw-check-item.has-error { border-color: #fca5a5; background: #fff5f5; }

/* Declaration */
.ptw-declaration-box {
    background: #fffbeb; border: 1px solid #fde68a;
    border-radius: 10px; padding: 16px 18px; margin-bottom: 16px;
    font-size: 13px; color: #78350f; line-height: 1.7;
}

/* Step summary (review) */
.ptw-review-row { display: flex; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.ptw-review-row:last-child { border-bottom: none; }
.ptw-review-label { color: #64748b; min-width: 200px; font-weight: 500; }
.ptw-review-value { color: #1e293b; }

/* Responsive */
@media (max-width: 576px) {
    .ptw-stepper::before { display: none; }
    .ptw-step-label { display: none; }
    .ptw-step-circle { width: 32px; height: 32px; font-size: 12px; }
}
</style>

<div class="ptw-wizard-wrap">

    <!-- Page header -->
    <div class="page-title clearfix mb-3">
        <h1 class="mb-0"><?php echo $app ? "Edit PTW Application" : "New PTW Application"; ?></h1>
        <div class="title-button-group">
            <?php echo anchor(get_uri("ptw_portal"), "<i data-feather='arrow-left' class='icon-14'></i> Back", ["class" => "btn btn-default btn-sm"]); ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="ptw-error-banner" id="ptw-error-banner">
        <div class="ptw-err-title"><i data-feather="alert-triangle" class="icon-16"></i> Please fix the following errors before saving:</div>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?php echo esc($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Stepper -->
    <div class="ptw-stepper" id="ptw-stepper">
        <?php
        $steps = [
            1 => ['label' => 'Applicant',   'icon' => '1'],
            2 => ['label' => 'Scope',        'icon' => '2'],
            3 => ['label' => 'Hazards',      'icon' => '3'],
            4 => ['label' => 'PPE',          'icon' => '4'],
            5 => ['label' => 'Preparation',  'icon' => '5'],
            6 => ['label' => 'Declaration',  'icon' => '6'],
            7 => ['label' => 'Review',       'icon' => '7'],
        ];
        foreach ($steps as $n => $s):
            $cls = ($n === $first_error_step && !empty($errors)) ? 'error' : '';
        ?>
        <div class="ptw-step <?php echo $cls; ?>" data-step="<?php echo $n; ?>" onclick="ptwGoStep(<?php echo $n; ?>)">
            <div class="ptw-step-circle"><?php echo $s['icon']; ?></div>
            <div class="ptw-step-label"><?php echo $s['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Form -->
    <?php echo form_open_multipart(get_uri("ptw_portal/save_application"), ["id" => "ptw-form", "class" => ""]); ?>
    <input type="hidden" name="id" value="<?php echo (string)($app->id ?? 0); ?>">
    <input type="hidden" name="submit_mode" id="submit_mode" value="draft">

    <!-- ═══ STEP 1: Permit Applicant ═══ -->
    <div class="ptw-panel" id="ptw-step-1">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">1</span>
                <h4>Permit Applicant</h4>
            </div>
            <div class="ptw-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                        <?php
                        $selected_company = isset($old['company_name']) ? $old['company_name'] : ($app->company_name ?? '');
                        ?>
                        <select name="company_name" class="form-select<?php echo ptw_input_class('company_name', $field_errors); ?>" required>
                            <option value="">— Select Company —</option>
                            <?php foreach (($companies_list ?? []) as $co): ?>
                                <option value="<?php echo esc($co->name); ?>" <?php echo ($selected_company === $co->name) ? 'selected' : ''; ?>>
                                    <?php echo esc($co->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php echo ptw_field_err('company_name', $field_errors); ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Applicant Name <span class="text-danger">*</span></label>
                        <input type="text" name="applicant_name" class="form-control<?php echo ptw_input_class('applicant_name', $field_errors); ?>"
                               value="<?php echo ptw_old('applicant_name', $app->applicant_name ?? trim(($login_user->first_name ?? '') . ' ' . ($login_user->last_name ?? '')), $old); ?>" placeholder="Full name" required>
                        <?php echo ptw_field_err('applicant_name', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Applicant Position <span class="text-danger">*</span></label>
                        <input type="text" name="applicant_position" class="form-control<?php echo ptw_input_class('applicant_position', $field_errors); ?>"
                               value="<?php echo ptw_old('applicant_position', $app->applicant_position ?? '', $old); ?>" placeholder="Job title / position" required>
                        <?php echo ptw_field_err('applicant_position', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" name="contact_phone" class="form-control<?php echo ptw_input_class('contact_phone', $field_errors); ?>"
                               value="<?php echo ptw_old('contact_phone', $app->contact_phone ?? ($login_user->phone ?? ''), $old); ?>" placeholder="+60 12 345 6789" required>
                        <?php echo ptw_field_err('contact_phone', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="contact_email" class="form-control<?php echo ptw_input_class('contact_email', $field_errors); ?>"
                               value="<?php echo ptw_old('contact_email', $app->contact_email ?? ($login_user->email ?? ''), $old); ?>" placeholder="email@example.com" required>
                        <?php echo ptw_field_err('contact_email', $field_errors); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="ptw-nav">
            <div></div>
            <button type="button" class="btn btn-primary" onclick="ptwNext(1)">Next: Scope of Works <i data-feather="arrow-right" class="icon-14 ms-1"></i></button>
        </div>
    </div>

    <!-- ═══ STEP 2: Scope of Works ═══ -->
    <div class="ptw-panel" id="ptw-step-2">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">2</span>
                <h4>Scope of Works</h4>
            </div>
            <div class="ptw-card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Work Description <span class="text-danger">*</span></label>
                        <textarea name="work_description" class="form-control<?php echo ptw_input_class('work_description', $field_errors); ?>" rows="3"
                                  placeholder="Describe the scope and nature of the work to be performed..." required><?php echo ptw_old('work_description', $app->work_description ?? '', $old); ?></textarea>
                        <?php echo ptw_field_err('work_description', $field_errors); ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Starting Date/Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" id="work_from" name="work_from"
                               class="form-control<?php echo ptw_input_class('work_from', $field_errors); ?>"
                               value="<?php
                                   $wf_old = $old['work_from'] ?? (!empty($app->work_from) ? date('Y-m-d\TH:i', strtotime($app->work_from)) : '');
                                   echo esc($wf_old);
                               ?>" required>
                        <?php echo ptw_field_err('work_from', $field_errors); ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Completion Date/Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" id="work_to" name="work_to"
                               class="form-control<?php echo ptw_input_class('work_to', $field_errors); ?>"
                               value="<?php
                                   $wt_old = $old['work_to'] ?? (!empty($app->work_to) ? date('Y-m-d\TH:i', strtotime($app->work_to)) : '');
                                   echo esc($wt_old);
                               ?>" required>
                        <?php echo ptw_field_err('work_to', $field_errors); ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Duration (Days)</label>
                        <input type="text" id="duration_days" class="form-control bg-light" readonly
                               value="<?php echo $duration_days !== null ? (int)$duration_days : ''; ?>"
                               placeholder="Auto">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Work Location <span class="text-danger">*</span></label>
                        <input type="text" name="exact_location" class="form-control<?php echo ptw_input_class('exact_location', $field_errors); ?>"
                               value="<?php echo ptw_old('exact_location', $app->exact_location ?? '', $old); ?>" placeholder="Building / area / zone" required>
                        <?php echo ptw_field_err('exact_location', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Work Supervisor Name <span class="text-danger">*</span></label>
                        <input type="text" name="work_supervisor_name" class="form-control<?php echo ptw_input_class('work_supervisor_name', $field_errors); ?>"
                               value="<?php echo ptw_old('work_supervisor_name', $app->work_supervisor_name ?? '', $old); ?>" placeholder="Supervisor full name" required>
                        <?php echo ptw_field_err('work_supervisor_name', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Supervisor Contact</label>
                        <input type="text" name="supervisor_contact_details" class="form-control"
                               value="<?php echo ptw_old('supervisor_contact_details', $app->supervisor_contact_details ?? '', $old); ?>" placeholder="Phone / email">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Total Workers <span class="text-danger">*</span></label>
                        <input type="number" min="1" name="total_workers" class="form-control<?php echo ptw_input_class('total_workers', $field_errors); ?>"
                               value="<?php echo ptw_old('total_workers', $app->total_workers ?? '', $old); ?>" placeholder="e.g. 5" required>
                        <?php echo ptw_field_err('total_workers', $field_errors); ?>
                    </div>
                </div>

                <!-- Map Data sub-section -->
                <hr class="my-4">
                <h6 class="fw-semibold text-muted mb-3"><i data-feather="map-pin" class="icon-14 me-1"></i> Planned Area of Work (Map Data)</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="location_lat" class="form-control"
                               value="<?php echo ptw_old('location_lat', $app->location_lat ?? '', $old); ?>" placeholder="e.g. 3.1234">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="location_lng" class="form-control"
                               value="<?php echo ptw_old('location_lng', $app->location_lng ?? '', $old); ?>" placeholder="e.g. 101.5678">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sector Name</label>
                        <input type="text" name="location_sector_name" class="form-control"
                               value="<?php echo ptw_old('location_sector_name', $app->location_sector_name ?? '', $old); ?>" placeholder="Zone / sector">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location Description</label>
                        <input type="text" name="location_description" class="form-control"
                               value="<?php echo ptw_old('location_description', $app->location_description ?? '', $old); ?>" placeholder="Additional notes">
                    </div>
                </div>
            </div>
        </div>
        <div class="ptw-nav">
            <button type="button" class="btn btn-default" onclick="ptwGoStep(1)"><i data-feather="arrow-left" class="icon-14 me-1"></i> Back</button>
            <button type="button" class="btn btn-primary" onclick="ptwNext(2)">Next: Hazards <i data-feather="arrow-right" class="icon-14 ms-1"></i></button>
        </div>
    </div>

    <!-- ═══ STEP 3: Hazards & Attachments ═══ -->
    <div class="ptw-panel" id="ptw-step-3">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">3</span>
                <h4>Hazards &amp; Attachments</h4>
            </div>
            <div class="ptw-card-body">
                <?php
                $hazard_defs = $definitions_grouped['hazard_document'] ?? [];
                if (empty($hazard_defs)):
                ?>
                    <p class="text-muted mb-0">No hazard / document requirements defined.</p>
                <?php else: foreach ($hazard_defs as $def):
                    $r = ptw_resp($responses, $def->id);
                    $checked = isset($old["req_{$def->id}_checked"]) ? (int)$old["req_{$def->id}_checked"] : ($r ? (int)$r->is_checked : 0);
                    $has_row_error = isset($field_errors["req_{$def->id}_checked"]) || isset($field_errors["req_{$def->id}_text"]) || isset($field_errors["req_{$def->id}_file"]);
                    $row_class = $has_row_error ? 'has-error' : ($checked ? 'is-checked' : '');
                ?>
                    <div class="ptw-req-row <?php echo $row_class; ?>" id="req-row-<?php echo $def->id; ?>">
                        <div class="row align-items-start g-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input ptw-req-check<?php echo isset($field_errors["req_{$def->id}_checked"]) ? ' is-invalid' : ''; ?>"
                                           type="checkbox"
                                           id="req_<?php echo $def->id; ?>_checked"
                                           name="req_<?php echo $def->id; ?>_checked"
                                           value="1"
                                           data-row="req-row-<?php echo $def->id; ?>"
                                           <?php echo $checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="req_<?php echo $def->id; ?>_checked">
                                        <?php echo esc($def->label); ?>
                                        <?php if ((int)$def->is_mandatory === 1): ?><span class="text-danger ms-1">*</span><?php endif; ?>
                                    </label>
                                </div>
                                <?php if (!empty($def->help_text)): ?>
                                    <small class="text-muted d-block ms-4"><?php echo esc($def->help_text); ?></small>
                                <?php endif; ?>
                                <?php echo ptw_field_err("req_{$def->id}_checked", $field_errors); ?>
                            </div>
                            <div class="col-md-3">
                                <?php if ((int)$def->has_text_input === 1): ?>
                                    <input type="text"
                                           name="req_<?php echo $def->id; ?>_text"
                                           class="form-control form-control-sm<?php echo isset($field_errors["req_{$def->id}_text"]) ? ' is-invalid' : ''; ?>"
                                           placeholder="<?php echo esc($def->text_label ?: 'Specify'); ?>"
                                           value="<?php echo esc($old["req_{$def->id}_text"] ?? ($r->value_text ?? '')); ?>">
                                    <?php echo ptw_field_err("req_{$def->id}_text", $field_errors); ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <input type="file" name="req_<?php echo $def->id; ?>_file"
                                       class="form-control form-control-sm<?php echo isset($field_errors["req_{$def->id}_file"]) ? ' is-invalid' : ''; ?>">
                                <?php if (!empty($def->allowed_extensions)): ?>
                                    <small class="text-muted"><?php echo esc($def->allowed_extensions); ?></small>
                                <?php endif; ?>
                                <?php echo ptw_field_err("req_{$def->id}_file", $field_errors); ?>
                            </div>
                            <div class="col-md-2 text-end">
                                <?php if (!empty($r->attachment_path ?? '')): ?>
                                    <?php echo anchor(get_uri('ptw_portal/download_attachment/' . ($r->id ?? 0)), '<i data-feather="paperclip" class="icon-13"></i> Current file', ['class' => 'btn btn-outline-secondary btn-sm']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="ptw-nav">
            <button type="button" class="btn btn-default" onclick="ptwGoStep(2)"><i data-feather="arrow-left" class="icon-14 me-1"></i> Back</button>
            <button type="button" class="btn btn-primary" onclick="ptwNext(3)">Next: PPE <i data-feather="arrow-right" class="icon-14 ms-1"></i></button>
        </div>
    </div>

    <!-- ═══ STEP 4: Proposed PPE ═══ -->
    <div class="ptw-panel" id="ptw-step-4">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">4</span>
                <h4>Proposed PPE</h4>
            </div>
            <div class="ptw-card-body">
                <?php
                $ppe_defs = $definitions_grouped['ppe'] ?? [];
                if (empty($ppe_defs)):
                ?>
                    <p class="text-muted mb-0">No PPE requirements defined.</p>
                <?php else: ?>
                <div class="ptw-check-grid">
                    <?php foreach ($ppe_defs as $def):
                        $r = ptw_resp($responses, $def->id);
                        $checked = isset($old["req_{$def->id}_checked"]) ? (int)$old["req_{$def->id}_checked"] : (!empty($r) && (int)$r->is_checked === 1 ? 1 : 0);
                        $has_err = isset($field_errors["req_{$def->id}_checked"]) || isset($field_errors["req_{$def->id}_text"]);
                    ?>
                        <div class="ptw-check-item <?php echo $has_err ? 'has-error' : ($checked ? 'is-checked' : ''); ?>" id="req-row-<?php echo $def->id; ?>">
                            <div class="form-check">
                                <input class="form-check-input ptw-req-check<?php echo $has_err ? ' is-invalid' : ''; ?>"
                                       type="checkbox"
                                       id="req_<?php echo $def->id; ?>_checked"
                                       name="req_<?php echo $def->id; ?>_checked"
                                       value="1"
                                       data-row="req-row-<?php echo $def->id; ?>"
                                       <?php echo $checked ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="req_<?php echo $def->id; ?>_checked">
                                    <?php echo esc($def->label); ?>
                                    <?php if ((int)$def->is_mandatory === 1): ?><span class="text-danger ms-1">*</span><?php endif; ?>
                                </label>
                            </div>
                            <?php if ((int)$def->has_text_input === 1): ?>
                                <input type="text"
                                       name="req_<?php echo $def->id; ?>_text"
                                       class="form-control form-control-sm mt-2<?php echo isset($field_errors["req_{$def->id}_text"]) ? ' is-invalid' : ''; ?>"
                                       placeholder="<?php echo esc($def->text_label ?: 'Specify'); ?>"
                                       value="<?php echo esc($old["req_{$def->id}_text"] ?? ($r->value_text ?? '')); ?>">
                                <?php echo ptw_field_err("req_{$def->id}_text", $field_errors); ?>
                            <?php endif; ?>
                            <?php echo ptw_field_err("req_{$def->id}_checked", $field_errors); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="ptw-nav">
            <button type="button" class="btn btn-default" onclick="ptwGoStep(3)"><i data-feather="arrow-left" class="icon-14 me-1"></i> Back</button>
            <button type="button" class="btn btn-primary" onclick="ptwNext(4)">Next: Preparations <i data-feather="arrow-right" class="icon-14 ms-1"></i></button>
        </div>
    </div>

    <!-- ═══ STEP 5: Work Area Preparations ═══ -->
    <div class="ptw-panel" id="ptw-step-5">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">5</span>
                <h4>Work Area Preparations</h4>
            </div>
            <div class="ptw-card-body">
                <?php
                $prep_defs = $definitions_grouped['preparation'] ?? [];
                if (empty($prep_defs)):
                ?>
                    <p class="text-muted mb-0">No preparation requirements defined.</p>
                <?php else: ?>
                <div class="ptw-check-grid">
                    <?php foreach ($prep_defs as $def):
                        $r = ptw_resp($responses, $def->id);
                        $checked = isset($old["req_{$def->id}_checked"]) ? (int)$old["req_{$def->id}_checked"] : (!empty($r) && (int)$r->is_checked === 1 ? 1 : 0);
                        $has_err = isset($field_errors["req_{$def->id}_checked"]) || isset($field_errors["req_{$def->id}_text"]);
                    ?>
                        <div class="ptw-check-item <?php echo $has_err ? 'has-error' : ($checked ? 'is-checked' : ''); ?>" id="req-row-<?php echo $def->id; ?>">
                            <div class="form-check">
                                <input class="form-check-input ptw-req-check<?php echo $has_err ? ' is-invalid' : ''; ?>"
                                       type="checkbox"
                                       id="req_<?php echo $def->id; ?>_checked"
                                       name="req_<?php echo $def->id; ?>_checked"
                                       value="1"
                                       data-row="req-row-<?php echo $def->id; ?>"
                                       <?php echo $checked ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="req_<?php echo $def->id; ?>_checked">
                                    <?php echo esc($def->label); ?>
                                </label>
                            </div>
                            <?php if ((int)$def->has_text_input === 1): ?>
                                <input type="text"
                                       name="req_<?php echo $def->id; ?>_text"
                                       class="form-control form-control-sm mt-2<?php echo isset($field_errors["req_{$def->id}_text"]) ? ' is-invalid' : ''; ?>"
                                       placeholder="<?php echo esc($def->text_label ?: 'Specify'); ?>"
                                       value="<?php echo esc($old["req_{$def->id}_text"] ?? ($r->value_text ?? '')); ?>">
                                <?php echo ptw_field_err("req_{$def->id}_text", $field_errors); ?>
                            <?php endif; ?>
                            <?php echo ptw_field_err("req_{$def->id}_checked", $field_errors); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="ptw-nav">
            <button type="button" class="btn btn-default" onclick="ptwGoStep(4)"><i data-feather="arrow-left" class="icon-14 me-1"></i> Back</button>
            <button type="button" class="btn btn-primary" onclick="ptwNext(5)">Next: Declaration <i data-feather="arrow-right" class="icon-14 ms-1"></i></button>
        </div>
    </div>

    <!-- ═══ STEP 6: Declaration & Signature ═══ -->
    <div class="ptw-panel" id="ptw-step-6">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">6</span>
                <h4>Declaration &amp; Signature</h4>
            </div>
            <div class="ptw-card-body">
                <div class="ptw-declaration-box">
                    <strong>Declaration:</strong> I hereby declare that the information provided in this Permit to Work application is true and accurate.
                    I understand that any false information may result in the rejection of this application and/or disciplinary action.
                    I confirm that all safety measures will be adhered to during the execution of the described works.
                </div>

                <div class="mb-4">
                    <div class="form-check form-check-lg <?php echo isset($field_errors['declaration_agreed']) ? 'border border-danger rounded p-2' : ''; ?>">
                        <input class="form-check-input<?php echo ptw_input_class('declaration_agreed', $field_errors); ?>"
                               type="checkbox" id="declaration_agreed" name="declaration_agreed" value="1"
                               <?php echo (!empty($app) && (int)$app->declaration_agreed === 1) || isset($old['declaration_agreed']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="declaration_agreed">
                            I agree to the above declaration <span class="text-danger">*</span>
                        </label>
                    </div>
                    <?php echo ptw_field_err('declaration_agreed', $field_errors); ?>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Name (Responsible Party) <span class="text-danger">*</span></label>
                        <input type="text" name="declaration_responsible_name"
                               class="form-control<?php echo ptw_input_class('declaration_responsible_name', $field_errors); ?>"
                               value="<?php echo ptw_old('declaration_responsible_name', $app->declaration_responsible_name ?? '', $old); ?>"
                               placeholder="Full name">
                        <?php echo ptw_field_err('declaration_responsible_name', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Function / Role <span class="text-danger">*</span></label>
                        <input type="text" name="declaration_function"
                               class="form-control<?php echo ptw_input_class('declaration_function', $field_errors); ?>"
                               value="<?php echo ptw_old('declaration_function', $app->declaration_function ?? '', $old); ?>"
                               placeholder="e.g. Site Manager">
                        <?php echo ptw_field_err('declaration_function', $field_errors); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Signature (file upload)
                            <?php if (empty($app->signature_file_path ?? '')): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <input type="file" name="signature_file"
                               class="form-control<?php echo ptw_input_class('signature_file', $field_errors); ?>">
                        <small class="text-muted">Accepted: PDF, DOCX, JPG, PNG, WEBP</small>
                        <?php if (!empty($app->signature_file_path ?? '')): ?>
                            <div class="mt-1">
                                <?php echo anchor(get_uri('ptw_portal/download_signature/' . $app->id), '<i data-feather="download" class="icon-12"></i> Current signature', ['class' => 'link']); ?>
                                <small class="text-muted ms-1">(upload new to replace)</small>
                            </div>
                        <?php endif; ?>
                        <?php echo ptw_field_err('signature_file', $field_errors); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="ptw-nav">
            <button type="button" class="btn btn-default" onclick="ptwGoStep(5)"><i data-feather="arrow-left" class="icon-14 me-1"></i> Back</button>
            <button type="button" class="btn btn-primary" onclick="ptwNext(6)">Review &amp; Submit <i data-feather="arrow-right" class="icon-14 ms-1"></i></button>
        </div>
    </div>

    <!-- ═══ STEP 7: Review & Submit ═══ -->
    <div class="ptw-panel" id="ptw-step-7">
        <div class="ptw-card">
            <div class="ptw-card-header">
                <span class="section-badge">7</span>
                <h4>Review &amp; Submit</h4>
            </div>
            <div class="ptw-card-body">
                <p class="text-muted mb-4">Please review your application before submitting. You can go back to any section to make changes.</p>

                <h6 class="fw-semibold text-primary mb-2">Section 1 — Permit Applicant</h6>
                <div class="ptw-review-row"><span class="ptw-review-label">Company Name</span><span class="ptw-review-value" id="rev-company_name">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Applicant Name</span><span class="ptw-review-value" id="rev-applicant_name">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Position</span><span class="ptw-review-value" id="rev-applicant_position">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Contact</span><span class="ptw-review-value" id="rev-contact_phone">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Email</span><span class="ptw-review-value" id="rev-contact_email">—</span></div>

                <h6 class="fw-semibold text-primary mt-4 mb-2">Section 2 — Scope of Works</h6>
                <div class="ptw-review-row"><span class="ptw-review-label">Work Description</span><span class="ptw-review-value" id="rev-work_description">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Start</span><span class="ptw-review-value" id="rev-work_from">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">End</span><span class="ptw-review-value" id="rev-work_to">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Location</span><span class="ptw-review-value" id="rev-exact_location">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Supervisor</span><span class="ptw-review-value" id="rev-work_supervisor_name">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Total Workers</span><span class="ptw-review-value" id="rev-total_workers">—</span></div>

                <h6 class="fw-semibold text-primary mt-4 mb-2">Section 6 — Declaration</h6>
                <div class="ptw-review-row"><span class="ptw-review-label">Responsible Party</span><span class="ptw-review-value" id="rev-declaration_responsible_name">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Function</span><span class="ptw-review-value" id="rev-declaration_function">—</span></div>
                <div class="ptw-review-row"><span class="ptw-review-label">Declaration Agreed</span><span class="ptw-review-value" id="rev-declaration_agreed">—</span></div>
            </div>
        </div>

        <div class="ptw-nav">
            <button type="button" class="btn btn-default" onclick="ptwGoStep(6)"><i data-feather="arrow-left" class="icon-14 me-1"></i> Back</button>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="save_draft_btn">
                    <i data-feather="save" class="icon-14 me-1"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" id="submit_btn">
                    <i data-feather="send" class="icon-14 me-1"></i> Submit to <?php echo esc($submit_stage_label); ?>
                </button>
            </div>
        </div>
    </div>

    <?php echo form_close(); ?>
</div><!-- /.ptw-wizard-wrap -->

<script>
(function () {
    var TOTAL_STEPS = 7;
    var currentStep = <?php echo (int)$first_error_step; ?>;
    var hasErrors   = <?php echo !empty($errors) ? 'true' : 'false'; ?>;

    /* ── Step navigation ─────────────────────────────────────── */
    function ptwGoStep(n) {
        // hide all
        for (var i = 1; i <= TOTAL_STEPS; i++) {
            var p = document.getElementById('ptw-step-' + i);
            if (p) p.classList.remove('active');
            var s = document.querySelector('.ptw-step[data-step="' + i + '"]');
            if (s) {
                s.classList.remove('active', 'done');
                if (i < n) s.classList.add('done');
            }
        }
        var panel = document.getElementById('ptw-step-' + n);
        if (panel) panel.classList.add('active');
        var step = document.querySelector('.ptw-step[data-step="' + n + '"]');
        if (step) { step.classList.remove('done'); step.classList.add('active'); }
        currentStep = n;

        // If going to review step, populate summary
        if (n === 7) populateReview();

        // Scroll to top of wizard
        var wrap = document.querySelector('.ptw-wizard-wrap');
        if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (typeof feather !== 'undefined') feather.replace();
    }
    window.ptwGoStep = ptwGoStep;

    function ptwNext(fromStep) {
        ptwGoStep(fromStep + 1);
    }
    window.ptwNext = ptwNext;

    /* ── Review panel population ─────────────────────────────── */
    function getVal(name) {
        var el = document.querySelector('[name="' + name + '"]');
        if (!el) return '—';
        if (el.type === 'checkbox') return el.checked ? 'Yes ✓' : 'No';
        return el.value || '—';
    }
    function setRev(id, name) {
        var el = document.getElementById('rev-' + id);
        if (el) el.textContent = getVal(name || id);
    }
    function populateReview() {
        var fields = ['company_name','applicant_name','applicant_position','contact_phone','contact_email',
                      'work_description','work_from','work_to','exact_location','work_supervisor_name',
                      'total_workers','declaration_responsible_name','declaration_function'];
        fields.forEach(function(f) { setRev(f); });
        setRev('declaration_agreed', 'declaration_agreed');
    }

    /* ── Checkbox row highlight ──────────────────────────────── */
    document.querySelectorAll('.ptw-req-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var rowId = this.dataset.row;
            if (!rowId) return;
            var row = document.getElementById(rowId);
            if (!row) return;
            row.classList.remove('is-checked', 'has-error');
            if (this.checked) row.classList.add('is-checked');
        });
    });

    /* ── Duration calculator ─────────────────────────────────── */
    function calcDays() {
        var from = document.getElementById('work_from');
        var to   = document.getElementById('work_to');
        var out  = document.getElementById('duration_days');
        if (!from || !to || !out) return;
        if (!from.value || !to.value) { out.value = ''; return; }
        var a = new Date(from.value), b = new Date(to.value);
        if (isNaN(a) || isNaN(b) || b < a) { out.value = '0'; return; }
        out.value = Math.ceil((b - a) / (1000 * 60 * 60 * 24));
    }
    var wf = document.getElementById('work_from');
    var wt = document.getElementById('work_to');
    if (wf) wf.addEventListener('change', calcDays);
    if (wt) wt.addEventListener('change', calcDays);
    calcDays();

    /* ── Submit buttons ──────────────────────────────────────── */
    document.getElementById('save_draft_btn').addEventListener('click', function () {
        document.getElementById('submit_mode').value = 'draft';
        document.getElementById('ptw-form').submit();
    });
    document.getElementById('submit_btn').addEventListener('click', function () {
        document.getElementById('submit_mode').value = 'submit';
        document.getElementById('ptw-form').submit();
    });

    /* ── Init: go to first error step (or step 1) ────────────── */
    ptwGoStep(currentStep);

    // Mark error steps in stepper
    <?php if (!empty($step_has_error)): ?>
    <?php foreach ($step_has_error as $sn => $has): if ($has): ?>
    (function() {
        var s = document.querySelector('.ptw-step[data-step="<?php echo $sn; ?>"]');
        if (s) { s.classList.remove('done'); s.classList.add('error'); }
    })();
    <?php endif; endforeach; ?>
    <?php endif; ?>

})();
</script>
