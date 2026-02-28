<ul class="nav nav-tabs" id="vendor-portal-tabs" role="tablist">

    <li class="nav-item">
        <a class="nav-link active"
            data-bs-toggle="tab"
            href="#vp-overview"
            data-load-url="<?= get_uri('vendor_portal/overview'); ?>">
            Overview
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link"
            data-bs-toggle="tab"
            href="#vp-contacts"
            data-load-url="<?= get_uri('vendor_portal/contacts'); ?>">
            Contacts
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link"
            data-bs-toggle="tab"
            href="#vp-bank"
            data-load-url="<?= get_uri('vendor_portal/bank'); ?>">
            Bank Accounts
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link"
            data-bs-toggle="tab"
            href="#vp-branches"
            data-load-url="<?= get_uri('vendor_portal/branches'); ?>">
            Branches
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link"
            data-bs-toggle="tab"
            href="#vp-credentials"
            data-load-url="<?= get_uri('vendor_portal/credentials'); ?>">
            Credentials
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link"
            data-bs-toggle="tab"
            href="#vp-specialties"
            data-load-url="<?= get_uri('vendor_portal/specialties'); ?>">
            Specialties
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link"
            data-bs-toggle="tab"
            href="#vp-documents"
            data-load-url="<?= get_uri('vendor_portal/documents'); ?>">
            Documents
        </a>
    </li>
</ul>


<div class="tab-content pt15" id="vendor-portal-tabs-content">
    <div class="tab-pane fade show active" id="vp-overview" role="tabpanel"></div>
    <div class="tab-pane fade" id="vp-contacts" role="tabpanel"></div>
    <div class="tab-pane fade" id="vp-bank" role="tabpanel"></div>
    <div class="tab-pane fade" id="vp-branches" role="tabpanel"></div>
    <div class="tab-pane fade" id="vp-credentials" role="tabpanel"></div>
    <div class="tab-pane fade" id="vp-specialties" role="tabpanel"></div>
    <div class="tab-pane fade" id="vp-documents" role="tabpanel"></div>
</div>



<script>
    (function() {

        function loadTab($link) {
            const target = $link.attr("href");
            const url = $link.data("load-url");
            if (!target || !url) return;

            const $pane = $(target);

            $pane.html("<div class='p15 text-muted'>Loading...</div>");

            $.ajax({
                url: url,
                type: "GET",
                success: function(res) {
                    $pane.html(res);
                    feather.replace(); // icons
                },
                error: function() {
                    $pane.html("<div class='p15 text-danger'>Failed to load.</div>");
                }
            });
        }

        // Load tab on click
        $(document).on(
            "shown.bs.tab",
            "#vendor-portal-tabs a[data-bs-toggle='tab']",
            function(e) {
                loadTab($(e.target));
            }
        );

        // Load initial tab
        loadTab($("#vendor-portal-tabs a.active"));

    })();
</script>