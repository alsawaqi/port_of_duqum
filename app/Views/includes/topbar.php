<?php
$user = $login_user->id;
$is_accounting_only = \App\Libraries\Payments\Payment_accounting_policy::isAccountingOnly($login_user);
$is_vendor_only_identity = !empty($login_user->is_vendor_only_identity);
$is_gate_pass_only_identity = !empty($login_user->is_gate_pass_only_identity);
$is_ptw_applicant_only_identity = !empty($login_user->is_ptw_applicant_only_identity);
$is_external_portal_only_identity = $is_vendor_only_identity
    || $is_gate_pass_only_identity
    || $is_ptw_applicant_only_identity;
$has_active_vendor_portal_access = !empty($login_user->has_active_vendor_portal_access);
$has_active_gate_pass_portal_access = !empty($login_user->has_active_gate_pass_portal_access);
$has_active_ptw_portal_access = !empty($login_user->has_active_ptw_portal_access);
$external_portal_home = $has_active_vendor_portal_access
    ? "vendor_portal"
    : ($has_active_gate_pass_portal_access
        ? "gate_pass_portal"
        : ($has_active_ptw_portal_access ? "ptw_portal" : "portal_account/change_password"));
?>

<nav class="navbar navbar-expand fixed-top navbar-light navbar-custom" role="navigation" id="default-navbar">
    <div class="container-fluid">
        <div class="collapse navbar-collapse pod-topbar-collapse">
            <ul class="navbar-nav me-auto mb-lg-0 pod-topbar-left">
                <li class="nav-item hidden-xs sidebar-toggle-btn-li">
                    <a class="nav-link sidebar-toggle-btn" aria-current="page" href="#">
                        <i data-feather="menu" class="icon"></i>
                    </a>
                </li>

                <li class="nav-item d-block d-sm-none">
                    <?php
                    $user = $login_user->id;
                    $dashboard_link = get_uri($is_accounting_only ? \App\Libraries\Payments\Payment_accounting_policy::home($login_user) : ($is_external_portal_only_identity ? $external_portal_home : "dashboard"));
                    $user_dashboard = get_setting("user_" . $user . "_dashboard");
                    if ($user_dashboard && !$is_external_portal_only_identity && !$is_accounting_only) {
                        $dashboard_link = get_uri("dashboard/view/" . $user_dashboard);
                    }
                    ?>
                    <a id="dashboard-link" class="brand-logo" href="<?php echo $dashboard_link; ?>"><img class="dashboard-image" src="<?php echo get_logo_url(); ?>" /></a>

                </li>

                <?php
                //get the array of hidden topbar menus
                $hidden_topbar_menus = explode(",", get_setting("user_" . $user . "_hidden_topbar_menus"));

                // if (!in_array("to_do", $hidden_topbar_menus)) {
                //     echo view("todo/topbar_icon");
                // }
                // if (!in_array("favorite_projects", $hidden_topbar_menus) && !(get_setting("disable_access_favorite_project_option_for_clients") && $login_user->user_type == "client") && !($login_user->user_type == "staff" && get_array_value($login_user->permissions, "do_not_show_projects"))) {
                //     echo view("projects/star/topbar_icon");
                // }
                // if (!in_array("favorite_clients", $hidden_topbar_menus)) {
                //     echo view("clients/star/topbar_icon");
                // }
                // if (!in_array("dashboard_customization", $hidden_topbar_menus) && (get_setting("disable_new_dashboard_icon") != 1)) {
                //     echo view("dashboards/list/topbar_icon");
                // }
                ?>

                <?php
                // if (has_my_open_timers()) {
                //     echo view("projects/open_timers_topbar_icon");
                // }

                if ($login_user->user_type === "client") {
                    show_clients_of_this_client_contact($login_user);
                }
                ?>
            </ul>

            <div class="d-flex w-auto pod-topbar-actions">
                <ul class="navbar-nav pod-topbar-right">

                    <?php
                    // if (!in_array("quick_add", $hidden_topbar_menus)) {
                    //     echo view("settings/topbar_parts/quick_add");
                    // }
                    ?>

                    <li class="nav-item pod-theme-toggle-item">
                        <button type="button" id="pod-theme-toggle" class="nav-link pod-theme-toggle" aria-label="Switch to dark mode" aria-pressed="false" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Switch to dark mode">
                            <i data-feather="moon" class="icon pod-theme-icon pod-theme-moon"></i>
                            <i data-feather="sun" class="icon pod-theme-icon pod-theme-sun"></i>
                            <span class="visually-hidden pod-theme-toggle-label">Light mode</span>
                        </button>
                    </li>

                    <?php if (!$is_external_portal_only_identity && !in_array("language", $hidden_topbar_menus) && (($login_user->user_type == "staff" && !get_setting("disable_language_selector_for_team_members")) || ($login_user->user_type == "client" && !get_setting("disable_language_selector_for_clients")))) { ?>

                        <li id="topbar-language-dropdown" class="nav-item dropdown pod-language-item">
                            <?php
                            $user_language = $login_user->language;
                            $system_language = get_setting("language");
                            $current_language = $user_language ? $user_language : $system_language;
                            $current_language_code = strtoupper(substr($current_language ? $current_language : "en", 0, 2));

                            echo js_anchor("<i data-feather='globe' class='icon'></i><span class='pod-language-current'>" . esc($current_language_code) . "</span>", array(
                                "id" => "personal-language-icon",
                                "class" => "nav-link dropdown-toggle pod-language-toggle",
                                "data-bs-toggle" => "dropdown",
                                "aria-label" => app_lang("language"),
                                "aria-expanded" => "false"
                            ));


                            ?>

                            <ul class="dropdown-menu dropdown-menu-end language-dropdown pod-language-menu">
                                <li>
                                    <?php
                                    foreach (get_language_list() as $language) {
                                        $language_key = strtolower($language);
                                        $is_active_language = ($user_language == $language_key || (!$user_language && $system_language == $language_key));
                                        $language_code = strtoupper(substr($language_key, 0, 2));
                                        $language_flag_class = $language_key === "arabic" ? "pod-language-flag-ar" : "pod-language-flag-en";
                                        $language_option_class = "dropdown-item pod-language-option" . ($is_active_language ? " active" : "");

                                        $language_text = "<span class='pod-language-flag " . $language_flag_class . "' aria-hidden='true'></span>"
                                            . "<span class='pod-language-copy'>"
                                            . "<span class='pod-language-name'>" . esc($language) . "</span>"
                                            . "<span class='pod-language-meta'>" . esc($language_code) . " language</span>"
                                            . "</span>"
                                            . "<i data-feather='check' class='icon pod-language-check'></i>";

                                        if ($login_user->user_type == "staff") {
                                            echo ajax_anchor(get_uri("team_members/save_personal_language/$language"), $language_text, array("class" => $language_option_class, "data-reload-on-success" => "1"));
                                        } else {
                                            echo ajax_anchor(get_uri("clients/save_personal_language/$language"), $language_text, array("class" => $language_option_class, "data-reload-on-success" => "1"));
                                        }
                                    }
                                    ?>
                                </li>
                            </ul>
                        </li>

                    <?php } ?>

                    <?php if (!$is_accounting_only && can_access_reminders_module()) { ?>
                        <li class="nav-item dropdown">
                            <?php

                            // echo modal_anchor(get_uri("events/reminders"), "<i data-feather='clock' class='icon'></i>", array("class" => "nav-link", "id" => "reminder-icon", "data-post-reminder_view_type" => "global", "title" => app_lang('reminders') . " (" . app_lang('private') . ")")); 


                            ?>
                        </li>
                        <?php

                        //  reminders_widget(); 

                        ?>
                    <?php } ?>

                    <?php if (!$is_accounting_only) { ?>
                    <li class="nav-item dropdown pod-notification-item">
                        <?php

                        echo js_anchor("<span class='pod-notification-dot' aria-hidden='true'><span></span></span><i data-feather='bell' class='icon'></i><span class='notification-badge-container'></span>", array(
                            "id" => "web-notification-icon",
                            "class" => "nav-link dropdown-toggle pod-header-icon-btn pod-notification-trigger",
                            "data-bs-toggle" => "dropdown",
                            "data-count_url" => get_uri('notifications/count_notifications'),
                            "data-list_url" => get_uri('notifications/get_notifications'),
                            "data-status_update_url" => get_uri('notifications/update_notification_checking_status'),
                            "data-fetch_interval" => get_setting('check_notification_after_every'),
                            "aria-label" => app_lang("notifications")
                        ));


                        ?>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown pod-notification-menu">
                            <div class="card m0 pod-notification-panel">
                                <div class="pod-notification-head">
                                    <h5><?php echo app_lang("notifications"); ?></h5>
                                    <button type="button" class="pod-dropdown-close" data-pod-close-dropdown aria-label="<?php echo app_lang("close"); ?>">
                                        <i data-feather="x" class="icon"></i>
                                    </button>
                                </div>
                                <div class="dropdown-details bg-white m0 pod-notification-body">
                                    <div class="list-group">
                                        <span class="list-group-item inline-loader p10"></span>
                                    </div>
                                </div>
                                <div class="card-footer text-center pod-notification-footer">
                                    <?php echo anchor("notifications", app_lang('see_all'), array("class" => "w-100 d-block pod-notification-view-all")); ?>
                                </div>
                            </div>
                        </div>
                    </li>

                    <?php } ?>
                    <!-- <?php if (!$is_accounting_only && get_setting("module_message") && can_access_messages_module()) { ?>
                        <li class="nav-item dropdown hidden-sm <?php echo ($login_user->user_type === "client" && !get_setting("client_message_users")) ? "hide" : ""; ?>">
                            <?php echo js_anchor("<i data-feather='mail' class='icon'></i><span class='notification-badge-container'></span>", array(
                                    "id" => "message-notification-icon",
                                    "class" => "nav-link dropdown-toggle",
                                    "data-bs-toggle" => "dropdown",
                                    "data-count_url" => get_uri('messages/count_notifications'),
                                    "data-list_url" => get_uri('messages/get_notifications'),
                                    "data-status_update_url" => get_uri('messages/update_notification_checking_status'),
                                    "data-fetch_interval" => get_setting('check_notification_after_every'),
                                )); ?>
                            <div class="dropdown-menu dropdown-menu-end w300 message-dropdown">
                                <div class="card m0">
                                    <div class="dropdown-details bg-white">
                                        <div class="list-group">
                                            <span class="list-group-item inline-loader p10"></span>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center">
                                        <?php echo anchor("messages", app_lang('see_all'), array("class" => "w-100 d-block")); ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php } ?> -->

                    <li class="nav-item dropdown pod-user-menu-item">
                        <a id="user-dropdown" href="#" class="nav-link pod-user-dropdown" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                            <span class="avatar-xs avatar me-1">
                                <img alt="..." src="<?php echo get_avatar($login_user->image); ?>">
                            </span>
                            <span class="user-name ml10"><?php echo $login_user->first_name . " " . $login_user->last_name; ?></span>
                            <i data-feather="chevron-down" class="icon pod-user-chevron"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu pod-user-menu">
                            <li class="pod-user-menu-head">
                                <span class="pod-user-menu-name"><?php echo esc($login_user->first_name . " " . $login_user->last_name); ?></span>
                                <span class="pod-user-menu-email"><?php echo esc($login_user->email); ?></span>
                            </li>
                            <?php if ($is_accounting_only) { ?>
                                <li><a href="<?php echo get_uri('portal_account/change_password'); ?>" class="dropdown-item"><i data-feather="key" class="icon-16 me-2"></i><?php echo app_lang('change_password'); ?></a></li>
                            <?php } else if ($is_external_portal_only_identity) { ?>
                                <?php if ($has_active_vendor_portal_access) { ?>
                                    <li><a href="<?php echo get_uri("vendor_portal"); ?>" class="dropdown-item"><i data-feather="briefcase" class="icon-16 me-2"></i>Vendor Portal</a></li>
                                <?php } ?>
                                <?php if ($has_active_gate_pass_portal_access) { ?>
                                    <li><a href="<?php echo get_uri("gate_pass_portal"); ?>" class="dropdown-item"><i data-feather="key" class="icon-16 me-2"></i><?php echo app_lang("gate_pass_portal"); ?></a></li>
                                <?php } ?>
                                <?php if ($has_active_ptw_portal_access) { ?>
                                    <li><a href="<?php echo get_uri("ptw_portal"); ?>" class="dropdown-item"><i data-feather="shield" class="icon-16 me-2"></i><?php echo app_lang("ptw_portal"); ?></a></li>
                                <?php } ?>
                                <?php if ($has_active_vendor_portal_access) { ?>
                                    <li><a href="<?php echo get_uri("vendor_portal/change_password"); ?>" class="dropdown-item"><i data-feather="key" class="icon-16 me-2"></i><?php echo app_lang("change_password"); ?></a></li>
                                <?php } else { ?>
                                    <li><a href="<?php echo get_uri("portal_account/change_password"); ?>" class="dropdown-item"><i data-feather="key" class="icon-16 me-2"></i><?php echo app_lang("change_password"); ?></a></li>
                                <?php } ?>
                            <?php } else if ($login_user->user_type == "client") { ?>
                                <div class="company-switch-option d-none"><?php show_clients_of_this_client_contact($login_user, true); ?></div>
                                <li><?php echo get_client_contact_profile_link($login_user->id . '/general', "<i data-feather='user' class='icon-16 me-2'></i>" . app_lang('my_profile'), array("class" => "dropdown-item")); ?></li>
                                <li><?php echo get_client_contact_profile_link($login_user->id . '/account', "<i data-feather='key' class='icon-16 me-2'></i>" . app_lang('change_password'), array("class" => "dropdown-item")); ?></li>
                                <li><?php echo get_client_contact_profile_link($login_user->id . '/my_preferences', "<i data-feather='settings' class='icon-16 me-2'></i>" . app_lang('my_preferences'), array("class" => "dropdown-item")); ?></li>
                            <?php } else { ?>
                                <li><?php echo get_team_member_profile_link($login_user->id . '/general', "<i data-feather='user' class='icon-16 me-2'></i>" . app_lang('my_profile'), array("class" => "dropdown-item")); ?></li>
                                <li><?php echo get_team_member_profile_link($login_user->id . '/account', "<i data-feather='key' class='icon-16 me-2'></i>" . app_lang('change_password'), array("class" => "dropdown-item")); ?></li>
                                <li><?php echo get_team_member_profile_link($login_user->id . '/my_preferences', "<i data-feather='settings' class='icon-16 me-2'></i>" . app_lang('my_preferences'), array("class" => "dropdown-item")); ?></li>
                            <?php } ?>

                            <?php if (!$is_accounting_only && get_setting("show_theme_color_changer") === "yes") { ?>

                                <li class="dropdown-divider"></li>
                                <li class="pl10 ms-2 mt10 theme-changer">
                                    <?php echo get_custom_theme_color_list(); ?>
                                </li>

                            <?php } ?>

                            <li class="dropdown-divider"></li>
                            <li>
                                <?php echo form_open("signin/sign_out", ["class" => "m-0"]); ?>
                                    <button type="submit" class="dropdown-item pod-signout-link">
                                        <i data-feather="log-out" class='icon-16 me-2'></i> <?php echo app_lang('sign_out'); ?>
                                    </button>
                                <?php echo form_close(); ?>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div><!--/.nav-collapse -->
    </div>
</nav>

<script type="text/javascript">
    //close navbar collapse panel on clicking outside of the panel
    $(document).click(function(e) {
        if (!$(e.target).is('#navbar') && isMobile()) {
            $('#navbar').collapse('hide');
        }
    });

    $(document).ready(async function() {
        $('body').on('click', "#reminder-icon", function() {
            $("#ajaxModal").addClass("reminder-modal");
        });

        $("body").on("click", ".notification-dropdown a[data-act='ajax-modal'], #js-quick-add-task, #js-quick-add-multiple-task, #task-details-edit-btn, #task-modal-view-link, #parent-task-link", function() {
            if ($(".task-preview").length) {
                // Store the current location
                var currentLocation = window.location.href;

                //remove task details view when it's already opened to prevent selector duplication
                $("#page-content").remove();
                $('#ajaxModal').on('hidden.bs.modal', function() {
                    window.location.href = currentLocation;
                });
            }
        });

        $('[data-bs-toggle="tooltip"]').tooltip();

        $("body").on("click", "[data-pod-close-dropdown]", function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $dropdown = $(this).closest(".dropdown");
            var toggle = $dropdown.find("[data-bs-toggle='dropdown']")[0];
            if (toggle && window.bootstrap && bootstrap.Dropdown) {
                bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
            } else {
                $dropdown.find(".dropdown-menu").removeClass("show");
                $dropdown.removeClass("show");
            }
        });

        if (isMobile()) {
            moveTopbarButtonsToLeftMenu($("#topbar-timer-dropdown"));
        }
    });

    function moveTopbarButtonsToLeftMenu($element) {
        if ($element.html()) {
            $("#left-menu-topbar-button-container").append("<div class='menu-item d-block d-sm-none dropdown float-end'>" + $element.html() + "</div>");
            $element.remove();
        }
    }
</script>
