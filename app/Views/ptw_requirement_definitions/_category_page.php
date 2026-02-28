<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix gp-pro-title">
            <h1><?php echo esc($category_label); ?></h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("ptw_requirement_definitions/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . esc($add_button_text ?? app_lang("add")),
                    [
                        "class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon",
                        "data-post-category" => $category
                    ]
                ); ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="ptw-requirement-definitions-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#ptw-requirement-definitions-table").appTable({
        source: '<?php echo_uri("ptw_requirement_definitions/list_data?category=" . $category); ?>',
        columns: [
            {title: "Label"},
            {title: "Code", class: "w15p"},
            {title: "Group Key", class: "w15p"},
            {title: "Flags", class: "w20p"},
            {title: "Extensions", class: "w15p"},
            {title: "<?php echo app_lang("sort_order"); ?>", class: "w10p"},
            {title: "<?php echo app_lang("status"); ?>", class: "w10p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
        ]
    });
});
</script>