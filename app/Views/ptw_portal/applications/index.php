<div class="ptw-apps ps-ready p15">

    <div class="ps-shell">
        <div class="ps-inner">

            <div class="ps-header">
                <div class="ps-header-left">
                    <div class="ps-header-icon">
                        <i data-feather="clipboard" class="icon-16"></i>
                    </div>
                    <div>
                        <h4 class="ps-header-title">My PTW Applications</h4>
                        <p class="ps-header-sub">Submit, track, and manage all your Permit to Work applications.</p>
                    </div>
                </div>

                <div class="ps-add-btn">
                    <?php echo anchor(
                        get_uri("ptw_portal/application_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> New PTW",
                        ["class" => "btn btn-default"]
                    ); ?>
                </div>
            </div>

            <div class="ps-table-wrap">
                <div class="table-responsive mb0">
                    <table id="ptw-apps-table" class="display" width="100%"></table>
                </div>
            </div>

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
        onDrawCallback: function () {
            if (typeof PortalUI !== "undefined") {
                PortalUI.animateRows("#ptw-apps-table");
            }
            if (typeof feather !== "undefined") feather.replace();
        }
    });

    if (typeof feather !== "undefined") feather.replace();
});
</script>