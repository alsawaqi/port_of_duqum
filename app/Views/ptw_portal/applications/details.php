<?php
$app = $app ?? null;
$responses = $responses_index ?? [];

$format_meta_value = static function ($value): string {
    if ($value === null || $value === "") {
        return "-";
    }

    if (is_bool($value)) {
        return $value ? "Yes" : "No";
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    if (is_array($value)) {
        $items = [];
        $all_scalar = true;
        foreach ($value as $v) {
            if (is_array($v) || is_object($v)) {
                $all_scalar = false;
                break;
            }

            if ($v === null || $v === "") {
                $items[] = "-";
            } elseif (is_bool($v)) {
                $items[] = $v ? "Yes" : "No";
            } else {
                $items[] = (string) $v;
            }
        }

        if ($all_scalar) {
            return implode(", ", $items);
        }
    }

    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $encoded !== false ? $encoded : "-";
};

$format_audit_meta = static function ($meta) use ($format_meta_value): string {
    $raw = trim((string) $meta);
    if ($raw === "") {
        return "-";
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (empty($decoded)) {
            return "-";
        }

        $html = "<div class='mt-2' style='background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:8px;'>";
        foreach ($decoded as $key => $value) {
            $label = ucwords(str_replace("_", " ", (string) $key));
            $text = $format_meta_value($value);
            $html .= "<div style='margin-bottom:4px; white-space:pre-wrap; word-break:break-word;'><strong>" . esc($label) . ":</strong> " . esc($text) . "</div>";
        }
        $html .= "</div>";

        return $html;
    }

    return "<div class='mt-2' style='white-space:pre-wrap; word-break:break-word;'>" . esc($raw) . "</div>";
};
?>
<div class="page-title clearfix">
    <h1>PTW Details - <?php echo esc($app->reference); ?></h1>
    <div class="title-button-group">
        <?php echo anchor(get_uri("ptw_portal"), "<i data-feather='arrow-left' class='icon-14'></i> Back", ["class" => "btn btn-default"]); ?>
        <?php if (!empty($can_edit)) {
            echo anchor(get_uri("ptw_portal/application_form/" . $app->id), "<i data-feather='edit' class='icon-14'></i> Edit", ["class" => "btn btn-primary"]);
        } ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header"><h4 class="mb-0">Application Summary</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6"><strong>Company:</strong> <?php echo esc($app->company_name); ?></div>
                    <div class="col-md-6"><strong>Applicant:</strong> <?php echo esc($app->applicant_name); ?></div>
                    <div class="col-md-6"><strong>Email:</strong> <?php echo esc($app->contact_email); ?></div>
                    <div class="col-md-6"><strong>Phone:</strong> <?php echo esc($app->contact_phone); ?></div>
                    <div class="col-md-6"><strong>Supervisor:</strong> <?php echo esc($app->work_supervisor_name); ?></div>
                    <div class="col-md-6"><strong>Workers:</strong> <?php echo (int)$app->total_workers; ?></div>
                    <div class="col-md-6"><strong>Start:</strong> <?php echo esc($app->work_from); ?></div>
                    <div class="col-md-6"><strong>End:</strong> <?php echo esc($app->work_to); ?></div>
                    <div class="col-md-6"><strong>Duration (days):</strong> <?php echo (int)($duration_days ?? 0); ?></div>
                    <div class="col-md-6"><strong>Status:</strong> <?php echo esc($app->status); ?> / <?php echo esc($app->stage); ?></div>
                    <div class="col-md-12 mt-2"><strong>Work Description:</strong><br><?php echo nl2br(esc($app->work_description)); ?></div>
                    <div class="col-md-12 mt-2"><strong>Work Location:</strong> <?php echo esc($app->exact_location); ?></div>
                    <div class="col-md-12 mt-2"><strong>Map:</strong> Sector <?php echo esc($app->location_sector_name); ?> | Lat <?php echo esc($app->location_lat); ?> | Lng <?php echo esc($app->location_lng); ?> | <?php echo esc($app->location_description); ?></div>
                    <?php if (!empty($app->signature_file_path)) { ?>
                        <div class="col-md-12 mt-2"><strong>Signature File:</strong> <?php echo anchor(get_uri('ptw_portal/download_signature/' . $app->id), esc($app->signature_file_name ?: 'Download')); ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php foreach ([
            'hazard_document' => 'Hazards & Attachments',
            'ppe' => 'Proposed PPE',
            'preparation' => 'Work Area Preparations'
        ] as $cat => $label) { ?>
        <div class="card mb-3">
            <div class="card-header"><h4 class="mb-0"><?php echo $label; ?></h4></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th width="100">Checked</th>
                                <th>Text</th>
                                <th>Attachment</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($definitions_grouped[$cat] ?? []) as $def) { $r = get_array_value($responses, (int)$def->id); ?>
                            <tr>
                                <td><?php echo esc($def->label); ?></td>
                                <td><?php echo (!empty($r) && (int)$r->is_checked === 1) ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc($r->value_text ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($r->attachment_path ?? '')) {
                                        echo anchor(get_uri('ptw_portal/download_attachment/' . ($r->id ?? 0)), 'Download');
                                    } else {
                                        echo '-';
                                    } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><h4 class="mb-0">Audit Trail</h4></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach (($audit_logs ?? []) as $log) { ?>
                        <div class="list-group-item">
                            <div class="fw-semibold"><?php echo esc($log->action); ?></div>
                            <small class="text-muted"><?php echo esc($log->user_name ?: ('User #' . $log->user_id)); ?> • <?php echo esc($log->created_at); ?></small>
                            <?php if (!empty($log->meta)) { echo $format_audit_meta($log->meta); } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>