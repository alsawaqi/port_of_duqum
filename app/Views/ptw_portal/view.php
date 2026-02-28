<ul class="nav nav-tabs" id="ptw-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active"
           data-bs-toggle="tab"
           href="#ptw-applications"
           data-load-url="<?= get_uri('ptw_portal/applications'); ?>">
            My PTW Applications
        </a>
    </li>
</ul>

<div class="tab-content pt15" id="ptw-tabs-content">
    <div class="tab-pane fade show active" id="ptw-applications" role="tabpanel"></div>
</div>

<script>
(function () {
    function loadTab($link) {
        const target = $link.attr("href");
        const url = $link.data("load-url");
        if (!target || !url) return;

        const $pane = $(target);
        $pane.html("<div class='p15 text-muted'>Loading...</div>");

        $.ajax({
            url: url,
            type: "GET",
            success: function (res) {
                $pane.html(res);
                if (typeof feather !== "undefined") feather.replace();
            },
            error: function (xhr) {
                $pane.html("<div class='p15 text-danger'>Failed to load PTW tab.</div>");
                console.error("PTW tab load error:", xhr.responseText);
            }
        });
    }

    $(document).on("shown.bs.tab", "#ptw-tabs a[data-bs-toggle='tab']", function (e) {
        loadTab($(e.target));
    });

    loadTab($("#ptw-tabs a.active"));
})();
</script>