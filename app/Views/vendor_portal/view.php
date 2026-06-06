<div id="page-content" class="page-wrapper clearfix pod-page-shell pod-vendor-page pod-vendor-portal-page">
    <?php
    echo view("includes/pod_page_header", [
        "title" => "Vendor Portal",
        "subtitle" => "Maintain company registration details, documents, contacts, bank accounts, specialties, and tender activity.",
        "icon" => "briefcase",
        "breadcrumbs" => [
            ["label" => "Vendor Portal"]
        ]
    ]);
    ?>

    <div class="card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title">Vendor Workspace</h2>
                <p class="pod-vendor-card-subtitle">Switch between profile sections and keep vendor information ready for review.</p>
            </div>
        </div>

        <ul class="nav nav-tabs pod-vendor-tabs" id="vendor-portal-tabs" role="tablist">

            <li class="nav-item">
                <a class="nav-link active"
                    data-bs-toggle="tab"
                    href="#vp-overview"
                    data-load-url="<?= get_uri('vendor_portal/overview'); ?>">
                    <i data-feather="activity" class="icon-16"></i> Overview
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-contacts"
                    data-load-url="<?= get_uri('vendor_portal/contacts'); ?>">
                    <i data-feather="users" class="icon-16"></i> Contacts
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-bank"
                    data-load-url="<?= get_uri('vendor_portal/bank'); ?>">
                    <i data-feather="credit-card" class="icon-16"></i> Bank Accounts
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-branches"
                    data-load-url="<?= get_uri('vendor_portal/branches'); ?>">
                    <i data-feather="map-pin" class="icon-16"></i> Branches
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-credentials"
                    data-load-url="<?= get_uri('vendor_portal/credentials'); ?>">
                    <i data-feather="award" class="icon-16"></i> Credentials
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-specialties"
                    data-load-url="<?= get_uri('vendor_portal/specialties'); ?>">
                    <i data-feather="layers" class="icon-16"></i> Specialties
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-documents"
                    data-load-url="<?= get_uri('vendor_portal/documents'); ?>">
                    <i data-feather="file-text" class="icon-16"></i> Documents
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link"
                    data-bs-toggle="tab"
                    href="#vp-tenders"
                    data-load-url="<?= get_uri('vendor_portal/tenders'); ?>">
                    <i data-feather="send" class="icon-16"></i> Tenders
                </a>
            </li>
        </ul>

        <div class="tab-content pod-vendor-card-body" id="vendor-portal-tabs-content">
            <div class="tab-pane fade show active" id="vp-overview" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-contacts" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-bank" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-branches" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-credentials" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-specialties" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-documents" role="tabpanel"></div>
            <div class="tab-pane fade" id="vp-tenders" role="tabpanel"></div>
        </div>
    </div>
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
                    console.error("Vendor portal tab load error:", xhr.responseText);
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

        const initialHash = window.location.hash;
        let $initialTab = $();
        if (initialHash) {
            $("#vendor-portal-tabs a[data-bs-toggle='tab']").each(function() {
                if ($(this).attr("href") === initialHash) {
                    $initialTab = $(this);
                }
            });
        }

        if ($initialTab.length) {
            $("#vendor-portal-tabs a[data-bs-toggle='tab']").removeClass("active").attr("aria-selected", "false");
            $("#vendor-portal-tabs-content .tab-pane").removeClass("show active");
            $initialTab.addClass("active").attr("aria-selected", "true");
            $($initialTab.attr("href")).addClass("show active");
        }

        // Load initial tab
        loadTab($("#vendor-portal-tabs a.active"));

    })();
</script>
