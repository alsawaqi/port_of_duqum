<ul class="nav nav-tabs" id="gp-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active"
            data-bs-toggle="tab"
            href="#gp-requests"
            data-load-url="<?= get_uri('gate_pass_portal/requests'); ?>">
            My Gate Pass Requests
        </a>
    </li>
</ul>

<div class="tab-content pt15" id="gp-tabs-content">
    <div class="tab-pane fade show active" id="gp-requests" role="tabpanel"></div>
</div>

<script>
(function() {

    function loadTab($link) {
        const target = $link.attr("href");
        const url = $link.data("load-url");
        if (!target || !url) return;

        const $pane = $(target);

        $pane.html(typeof PortalUI !== "undefined" ? PortalUI.tabLoadingHtml() : "<div class='p15 text-muted'>Loading...</div>");

        $.ajax({
            url: url,
            type: "GET",
            success: function(res) {
                $pane.html(res);
                if (typeof feather !== "undefined") feather.replace();
            },
            error: function(xhr) {
                $pane.html(typeof PortalUI !== "undefined"
                    ? PortalUI.tabErrorHtml()
                    : "<div class='p15 text-danger'>Failed to load.</div>");
                if (typeof feather !== "undefined") feather.replace();
                console.error("Gate pass portal tab load error:", xhr.responseText);
            }
        });
    }

    $(document).on("shown.bs.tab", "#gp-tabs a[data-bs-toggle='tab']", function(e) {
        loadTab($(e.target));
    });

    loadTab($("#gp-tabs a.active"));

})();
</script>
