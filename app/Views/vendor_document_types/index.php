<?php
$vendor_page_actions = "";

if (!empty($can_create_vendor_document_types)) {
    $vendor_page_actions = modal_anchor(
        get_uri("vendor_document_types/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_document_type'),
        ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon pod-action-btn", "title" => app_lang('add_vendor_document_type')]
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-vendor-page pod-vendor-master-page">
    <?php
    echo view("includes/pod_page_header", array(
        "title" => app_lang("vendor_document_types"),
        "subtitle" => "Control required supplier documents by group, code, and verification status.",
        "icon" => "file-text",
        "breadcrumbs" => array(
            array("label" => app_lang("vendors_master")),
            array("label" => app_lang("vendor_document_types"))
        ),
        "actions" => $vendor_page_actions
    ));
    ?>

    <div class="card gp-pro-card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title"><?php echo app_lang('vendor_document_types'); ?></h2>
                <p class="pod-vendor-card-subtitle">Keep document requirements clear for vendor registration and renewal.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
            <div class="table-responsive gp-pro-table-shell">
                <table id="vendor-document-types-table" class="display" cellspacing="0" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-document-types-table").appTable({
            source: '<?php echo_uri("vendor_document_types/list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("vendor_group"); ?>',
                    "class": "w20p"
                },
                {
                    title: '<?php echo app_lang("name"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("code"); ?>',
                    "class": "w15p"
                },
                {
                    title: '<?php echo app_lang("required"); ?>',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_vendor_document_types) || !empty($can_delete_vendor_document_types)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>
