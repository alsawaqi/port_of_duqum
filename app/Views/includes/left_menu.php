<div class="sidebar sidebar-off">
    <?php
    $user = $login_user->id;
    $is_vendor_only_identity = !empty($login_user->is_vendor_only_identity);
    $is_gate_pass_only_identity = !empty($login_user->is_gate_pass_only_identity);
    $is_ptw_applicant_only_identity = !empty($login_user->is_ptw_applicant_only_identity);
    $is_accounting_only_identity = \App\Libraries\Payments\Payment_accounting_policy::isAccountingOnly($login_user);
    $is_external_portal_only_identity = $is_vendor_only_identity
        || $is_gate_pass_only_identity
        || $is_ptw_applicant_only_identity;
    $has_active_vendor_portal_access = !empty($login_user->has_active_vendor_portal_access);
    $has_active_gate_pass_portal_access = !empty($login_user->has_active_gate_pass_portal_access);
    $has_active_ptw_portal_access = !empty($login_user->has_active_ptw_portal_access);
    $portal_home = $has_active_vendor_portal_access
        ? "vendor_portal"
        : ($has_active_gate_pass_portal_access
            ? "gate_pass_portal"
            : ($has_active_ptw_portal_access ? "ptw_portal" : "portal_account/change_password"));
    $dashboard_link = get_uri($is_accounting_only_identity
        ? \App\Libraries\Payments\Payment_accounting_policy::home($login_user)
        : ($is_external_portal_only_identity ? $portal_home : "dashboard"));
    $app_title = get_setting("app_title") ? get_setting("app_title") : "Port of Duqm";
    $user_dashboard = get_setting("user_" . $user . "_dashboard");
    if ($user_dashboard && !$is_external_portal_only_identity && !$is_accounting_only_identity) {
        $dashboard_link = get_uri("dashboard/view/" . $user_dashboard);
    }
    ?>
    <a class="sidebar-toggle-btn hide" href="#">
        <i data-feather="x" class="icon mt0"></i>
    </a>
    <div id="left-menu-topbar-button-container" class="d-block d-sm-none float-end"></div>
    <a class="sidebar-brand brand-logo hidden-xs pod-sidebar-brand" href="<?php echo $dashboard_link; ?>" aria-label="<?php echo esc($app_title); ?> dashboard">
        <span class="pod-sidebar-logo-frame">
            <img class="dashboard-image" src="<?php echo get_logo_url(); ?>" alt="<?php echo esc($app_title); ?>" />
        </span>
        <span class="pod-sidebar-brand-copy">
            <strong><?php echo esc($app_title); ?></strong>
            <small>Operations Portal</small>
        </span>
    </a>
    <a class="sidebar-brand brand-logo-mini pod-sidebar-brand-mini" href="<?php echo $dashboard_link; ?>" aria-label="<?php echo esc($app_title); ?> dashboard">
        <span class="pod-sidebar-logo-frame">
            <img class="dashboard-image" src="<?php echo get_favicon_url(); ?>" alt="<?php echo esc($app_title); ?>" />
        </span>
    </a>

    <nav class="sidebar-scroll" aria-label="Primary navigation">
        <ul id="sidebar-menu" class="sidebar-menu">
            <?php
            foreach ($sidebar_menu as $main_menu) {
                $main_menu_name = get_array_value($main_menu, "name");
                if (!$main_menu_name) {
                    continue;
                }

                $is_custom_menu_item = get_array_value($main_menu, "is_custom_menu_item");
                $open_in_new_tab = get_array_value($main_menu, "open_in_new_tab");
                $url = get_array_value($main_menu, "url");
                $class = get_array_value($main_menu, "class");
                $custom_class = get_array_value($main_menu, "custom_class");
                $submenu = get_array_value($main_menu, "submenu");

                $has_any_submenu = false;
                $has_active_submenu = false;
                if ($submenu && count($submenu)) {

                    foreach ($submenu as $s_menu) {
                        if ($s_menu && count($s_menu)) {
                            $has_any_submenu = true;
                            if (get_array_value($s_menu, "is_active_menu")) {
                                $has_active_submenu = true;
                            }
                        }
                    }

                    if (!$has_any_submenu) {
                        $submenu = "";
                        $has_active_submenu = false;
                    }
                }


                $expend_class = $submenu ? " expand " : "";
                $active_class = get_array_value($main_menu, "is_active_menu") ? "active" : "";

                $submenu_open_class = "";
                if ($expend_class && $active_class) {
                    $submenu_open_class = " open ";
                }

                if ($is_custom_menu_item) {
                    $language_key = get_array_value($main_menu, "language_key");
                    if ($language_key) {
                        $main_menu_name = app_lang($language_key);
                    }
                } else {
                    $main_menu_name = app_lang($main_menu_name);
                }

                $badge = get_array_value($main_menu, "badge");
                $badge_class = get_array_value($main_menu, "badge_class");
                $target = ($is_custom_menu_item && $open_in_new_tab) ? "target='_blank' rel='noopener noreferrer'" : "";
            ?>

                <li class="<?php echo $active_class . " " . $expend_class . " " . $submenu_open_class . " "; ?> main pod-menu-item">
                    <a class="pod-menu-link" <?php echo $target; ?> href="<?php echo (!$url || $url === '#' || $url === '') ? '#' : ($is_custom_menu_item ? $url : get_uri($url)); ?>" data-menu-title="<?php echo esc($main_menu_name); ?>" <?php echo ($active_class && !$has_active_submenu) ? "aria-current='page'" : ""; ?> <?php echo $submenu ? "aria-expanded='" . ($submenu_open_class ? "true" : "false") . "'" : ""; ?>>
                        <i data-feather="<?php echo $class; ?>" class="icon pod-menu-icon"></i>
                        <span class="menu-text pod-menu-label <?php echo $custom_class; ?>"><?php echo $main_menu_name; ?></span>
                        <?php
                        if ($badge) {
                            echo "<span class='badge rounded-pill $badge_class'>$badge</span>";
                        }
                        ?>
                    </a>
                    <?php
                    if ($submenu) {
                        echo "<ul>";
                        foreach ($submenu as $s_menu) {
                            $s_menu_name = get_array_value($s_menu, "name");
                            if (!$s_menu_name) {
                                continue;
                            }

                            $is_custom_menu_item = get_array_value($s_menu, "is_custom_menu_item");
                            $url = get_array_value($s_menu, "url");

                            if ($is_custom_menu_item) {
                                $language_key = get_array_value($s_menu, "language_key");
                                if ($language_key) {
                                    $s_menu_name = app_lang($language_key);
                                }
                            } else {
                                $s_menu_name = app_lang($s_menu_name);
                            }

                            if ($s_menu_name) {
                                $open_in_new_tab = get_array_value($s_menu, "open_in_new_tab");
                                $sub_menu_target = ($is_custom_menu_item && $open_in_new_tab) ? "target='_blank' rel='noopener noreferrer'" : "";
                                $s_active_class = get_array_value($s_menu, "is_active_menu") ? "active is-active" : "";
                    ?>
                <li class="pod-submenu-item <?php echo $s_active_class; ?>">
                    <a class="pod-submenu-link" <?php echo $sub_menu_target; ?> href="<?php echo $is_custom_menu_item ? $url : get_uri($url); ?>" data-menu-title="<?php echo esc($s_menu_name); ?>" <?php echo $s_active_class ? "aria-current='page'" : ""; ?>>
                        <i data-feather='minus' width='12'></i>
                        <span><?php echo $s_menu_name; ?></span>
                    </a>
                </li>
    <?php
                            }
                        }
                        echo "</ul>";
                    }
    ?>
    </li>
<?php
            }
?>
        </ul>
    </nav>
</div><!-- sidebar menu end -->

<script type='text/javascript'>
    if (typeof feather !== "undefined") feather.replace();

    $(document).ready(function() {
        $("#sidebar-menu").on("click.podMenuActive", "a[href]", function() {
            var $link = $(this);
            var href = $link.attr("href") || "";

            if (!href || href === "#" || $link.attr("target")) {
                return;
            }

            var $item = $link.closest("li");
            var $parent = $item.closest("ul").closest("li.expand");

            $("#sidebar-menu li.active, #sidebar-menu li.is-active").removeClass("active is-active");
            $("#sidebar-menu a[aria-current='page']").removeAttr("aria-current");

            $item.addClass("active is-active");
            $link.attr("aria-current", "page");

            if ($parent.length) {
                $parent.addClass("active open");
                $parent.children("a").attr("aria-expanded", "true");
            }
        });
    });
</script>
