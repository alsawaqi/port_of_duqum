<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h4 class="mb-0">My PTW Applications</h4>
        <?php echo anchor(get_uri("ptw_portal/application_form"), "<i data-feather='plus-circle' class='icon-16'></i> New PTW", ["class" => "btn btn-primary"]); ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="ptw-apps-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#ptw-apps-table").appTable({
        source: '<?php echo_uri("ptw_portal/applications_list_data"); ?>',
        columns: [
            {title: 'Reference'},
            {title: 'Company'},
            {title: 'Applicant'},
            {title: 'Supervisor'},
            {title: 'Start'},
            {title: 'End'},
            {title: 'Status', class: 'text-center w15p'},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: 'text-center option w120'}
        ],
        onDrawCallback: function () { if (window.feather) feather.replace(); }
    });
    if (window.feather) feather.replace();
});
</script>