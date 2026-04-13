<?php
/**
 * Gate Pass Portal — main shell (requests + dashboard tabs).
 */
?>
<div class="gp-portal-home clearfix">
    <style>
        .gp-portal-home {
            --gpph-ink: #0f172a;
            --gpph-muted: #64748b;
            --gpph-line: rgba(15, 23, 42, 0.08);
            --gpph-surface: #ffffff;
            --gpph-accent: #2563eb;
            --gpph-accent-soft: rgba(37, 99, 235, 0.12);
            --gpph-radius: 16px;
            --gpph-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
            --gpph-font: inherit;
        }

        .gp-portal-home .gp-portal-hero {
            position: relative;
            border-radius: var(--gpph-radius);
            border: 1px solid var(--gpph-line);
            background: var(--gpph-surface);
            box-shadow: var(--gpph-shadow);
            padding: 22px 24px 20px;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .gp-portal-home .gp-portal-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(900px 200px at 0% 0%, rgba(37, 99, 235, 0.14), transparent 55%),
                radial-gradient(700px 180px at 100% 0%, rgba(16, 185, 129, 0.10), transparent 50%),
                linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 0));
            pointer-events: none;
        }

        .gp-portal-home .gp-portal-hero-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px 20px;
        }

        .gp-portal-home .gp-portal-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gpph-accent);
            margin-bottom: 6px;
        }

        .gp-portal-home .gp-portal-kicker span {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--gpph-accent);
            box-shadow: 0 0 0 4px var(--gpph-accent-soft);
        }

        .gp-portal-home .gp-portal-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--gpph-ink);
            line-height: 1.25;
        }

        .gp-portal-home .gp-portal-sub {
            margin: 8px 0 0;
            max-width: 560px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--gpph-muted);
        }

        .gp-portal-home .gp-portal-hero-badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--gpph-line);
            background: rgba(255, 255, 255, 0.85);
            font-size: 12px;
            font-weight: 600;
            color: var(--gpph-muted);
        }

        .gp-portal-home .gp-portal-hero-badge i {
            color: var(--gpph-accent);
        }

        /* Segmented tabs */
        .gp-portal-home .gp-portal-tabs-card {
            border-radius: var(--gpph-radius);
            border: 1px solid var(--gpph-line);
            background: var(--gpph-surface);
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
            padding: 8px;
            margin-bottom: 16px;
        }

        .gp-portal-home #gp-tabs.gp-portal-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            border: none;
            margin: 0;
            padding: 0;
        }

        .gp-portal-home #gp-tabs.gp-portal-nav .nav-item {
            flex: 1 1 200px;
            margin: 0;
        }

        .gp-portal-home #gp-tabs.gp-portal-nav .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            margin: 0;
            padding: 12px 16px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            color: var(--gpph-muted);
            background: transparent;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .gp-portal-home #gp-tabs.gp-portal-nav .nav-link:hover {
            color: var(--gpph-ink);
            background: rgba(15, 23, 42, 0.04);
        }

        .gp-portal-home #gp-tabs.gp-portal-nav .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 55%, #1e40af 100%);
            box-shadow: 0 8px 22px rgba(37, 99, 235, 0.35);
        }

        .gp-portal-home #gp-tabs.gp-portal-nav .nav-link .gp-tab-icon {
            display: inline-flex;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.06);
            color: var(--gpph-ink);
        }

        .gp-portal-home #gp-tabs.gp-portal-nav .nav-link.active .gp-tab-icon {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .gp-portal-home #gp-tabs-content.gp-portal-tab-panels {
            border-radius: var(--gpph-radius);
            border: 1px solid var(--gpph-line);
            background: var(--gpph-surface);
            box-shadow: var(--gpph-shadow);
            min-height: 200px;
            overflow: hidden;
        }

        .gp-portal-home #gp-tabs-content .tab-pane {
            padding: 0;
        }

        .gp-portal-home #gp-tabs-content .tab-pane > .p15:first-child {
            padding-top: 18px !important;
        }

        @media (max-width: 576px) {
            .gp-portal-home .gp-portal-title {
                font-size: 19px;
            }
            .gp-portal-home #gp-tabs.gp-portal-nav .nav-link {
                padding: 11px 12px;
                font-size: 13px;
            }
        }
    </style>

    <div class="gp-portal-hero">
        <div class="gp-portal-hero-inner">
            <div>
                <div class="gp-portal-kicker">
                    <span></span>
                    <?php echo app_lang("gate_pass_portal"); ?>
                </div>
                <h1 class="gp-portal-title"><?php echo app_lang("gate_pass_portal_home_title"); ?></h1>
                <p class="gp-portal-sub"><?php echo app_lang("gate_pass_portal_home_subtitle"); ?></p>
            </div>
            <div class="gp-portal-hero-badge">
                <i data-feather="shield" class="icon-16"></i>
                <span><?php echo app_lang("gate_pass_portal_badge_secure"); ?></span>
            </div>
        </div>
    </div>

    <div class="gp-portal-tabs-card">
        <ul class="nav gp-portal-nav" id="gp-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active"
                   id="gp-tab-requests-link"
                   data-bs-toggle="tab"
                   href="#gp-requests"
                   data-load-url="<?php echo get_uri("gate_pass_portal/requests"); ?>"
                   role="tab"
                   aria-controls="gp-requests"
                   aria-selected="true">
                    <span class="gp-tab-icon"><i data-feather="clipboard" class="icon-16"></i></span>
                    <?php echo app_lang("gate_pass_portal_tab_requests"); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link"
                   id="gp-tab-dashboard-link"
                   data-bs-toggle="tab"
                   href="#gp-dashboard"
                   data-load-url="<?php echo get_uri("gate_pass_portal/dashboard"); ?>"
                   role="tab"
                   aria-controls="gp-dashboard"
                   aria-selected="false">
                    <span class="gp-tab-icon"><i data-feather="pie-chart" class="icon-16"></i></span>
                    <?php echo app_lang("gate_pass_portal_dashboard"); ?>
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content gp-portal-tab-panels pt0" id="gp-tabs-content">
        <div class="tab-pane fade show active" id="gp-requests" role="tabpanel" aria-labelledby="gp-tab-requests-link"></div>
        <div class="tab-pane fade" id="gp-dashboard" role="tabpanel" aria-labelledby="gp-tab-dashboard-link"></div>
    </div>
</div>

<script>
(function() {

    function loadTab($link) {
        var target = $link.attr("href");
        var url = $link.data("load-url");
        if (!target || !url) return;

        var $pane = $(target);

        $pane.html(typeof PortalUI !== "undefined" ? PortalUI.tabLoadingHtml() : "<div class='p20 text-muted'>Loading…</div>");

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
                    : "<div class='p20 text-danger'>Failed to load.</div>");
                if (typeof feather !== "undefined") feather.replace();
                console.error("Gate pass portal tab load error:", xhr.responseText);
            }
        });
    }

    $(document).on("shown.bs.tab", "#gp-tabs a[data-bs-toggle='tab']", function(e) {
        loadTab($(e.target));
    });

    loadTab($("#gp-tabs a.active"));
    if (typeof feather !== "undefined") feather.replace();

})();
</script>
