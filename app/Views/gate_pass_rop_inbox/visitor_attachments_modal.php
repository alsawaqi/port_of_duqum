<?php
if (empty($visitor)) {
    echo "<p class='p15 text-off'>" . app_lang("record_not_found") . "</p>";
    return;
}
if (is_array($visitor)) {
    $visitor = (object) $visitor;
}

$attachment_fields = [
    "id_attachment_path"    => app_lang("id_attachment"),
    "visa_attachment_path"  => app_lang("visa_attachment"),
    "photo_attachment_path" => app_lang("photo_attachment"),
    "driving_license_attachment_path" => app_lang("driving_license_attachment"),
];
$has_any = false;
foreach ($attachment_fields as $field => $label) {
    $val = isset($visitor->{$field}) ? trim((string)$visitor->{$field}) : "";
    if ($val !== "") {
        $has_any = true;
    }
}
?>

<div class="modal-body clearfix">
    <p class="mb15"><strong><?php echo app_lang("full_name"); ?>:</strong> <?php echo esc($visitor->full_name ?? "-"); ?></p>

    <?php if (!$has_any): ?>
        <p class="text-off p15"><?php echo app_lang("no_attachments"); ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb0">
                <thead class="bg-default">
                    <tr>
                        <th><?php echo app_lang("attachment"); ?></th>
                        <th class="text-center" style="width: 180px;"><?php echo app_lang("actions"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attachment_fields as $field => $label): ?>
                        <?php $path = isset($visitor->{$field}) ? trim((string)$visitor->{$field}) : ""; if ($path !== ""): ?>
                        <tr>
                            <td><?php echo esc($label); ?></td>
                            <td class="text-center">
                                <?php
                                $view_url = get_uri("gate_pass_rop_inbox/visitor_attachment_download/" . $visitor->id . "/" . $field);
                                $download_url = $view_url . "?download=1";
                                ?>
                                <a href="<?php echo esc($view_url); ?>" class="btn btn-default btn-sm" target="_blank" title="<?php echo app_lang("view"); ?>">
                                    <i data-feather="eye" class="icon-14"></i> <?php echo app_lang("view"); ?>
                                </a>
                                <a href="<?php echo esc($download_url); ?>" class="btn btn-default btn-sm" target="_blank" title="<?php echo app_lang("download"); ?>">
                                    <i data-feather="download" class="icon-14"></i> <?php echo app_lang("download"); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") feather.replace();
});
</script>
