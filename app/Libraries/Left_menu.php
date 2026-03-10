<?php

namespace App\Libraries;

use Config\Database;
use App\Libraries\Permission_manager;
use App\Controllers\Security_Controller;

class Left_menu
{

    private $ci = null;

    public function __construct()
    {
        $this->ci = new Security_Controller(false);
    }

    private function _get_sidebar_menu_items($type = "")
    {
        $dashboard_menu = array("name" => "dashboard", "url" => "dashboard", "class" => "monitor");

        $selected_dashboard_id = get_setting("user_" . $this->ci->login_user->id . "_dashboard");
        if ($selected_dashboard_id) {
            $dashboard_menu = array("name" => "dashboard", "url" => "dashboard/view/" . $selected_dashboard_id, "class" => "monitor", "custom_class" => "dashboard-menu");
        }



        if ($this->ci->login_user->user_type == "staff" && $type !== "client_default") {

            $permission_manager = new Permission_manager($this->ci);
            $sidebar_menu = array("dashboard" => $dashboard_menu);

            $permissions = $this->ci->login_user->permissions;

            $access_expense = get_array_value($permissions, "expense");
            $access_invoice = get_array_value($permissions, "invoice");
            $access_ticket = get_array_value($permissions, "ticket");
            $access_client = get_array_value($permissions, "client");
            $access_lead = get_array_value($permissions, "lead");
            $access_timecard = get_array_value($permissions, "attendance");
            $access_leave = get_array_value($permissions, "leave");
            $access_estimate = get_array_value($permissions, "estimate");
            $access_contract = get_array_value($permissions, "contract");
            $access_subscription = get_array_value($permissions, "subscription");
            $access_proposal = get_array_value($permissions, "proposal");
            $access_order = get_array_value($permissions, "order");
            $access_vendor = get_array_value($permissions, "vendor");

            $client_message_users = get_setting("client_message_users");
            $client_message_users_array = explode(",", $client_message_users);
            $access_messages = ($this->ci->login_user->is_admin || get_array_value($permissions, "message_permission") !== "no" || in_array($this->ci->login_user->id, $client_message_users_array));
            $access_file_manager = get_array_value($permissions, "file_manager");
            $access_timeline = ($this->ci->login_user->is_admin || get_array_value($permissions, "timeline_permission") !== "no");


            $sidebar_menu["ptw_portal"] = array(
                "name"  => "ptw_portal",
                "url"   => "ptw_portal",
                "class" => "shield"
            );


            $master_data_submenu = array();

            // helper: can view a master data section
            $can_view_master = function ($key) use ($permissions) {
                // admin sees all
                if ($this->ci->login_user->is_admin) {
                    return true;
                }
                return (bool) get_array_value($permissions, $key);
            };

            // ✅ Legal Types
            if ($can_view_master("can_view_legal_types")) {
                $master_data_submenu[] = array("name" => "legal_types", "url" => "legal_types", "class" => "file-text");
            }

            // ✅ Vendor Categories
            if ($can_view_master("can_view_vendor_categories")) {
                $master_data_submenu[] = array("name" => "vendor_categories", "url" => "vendor_categories", "class" => "file-text");
            }

            // ✅ Vendor Sub Categories
            if ($can_view_master("can_view_vendor_sub_categories")) {
                $master_data_submenu[] = array("name" => "vendor_sub_categories", "url" => "vendor_sub_categories", "class" => "file-text");
            }

            // ✅ Countries
            if ($can_view_master("can_view_countries")) {
                $master_data_submenu[] = array(
                    "name" => "countries",
                    "url" => "country",
                    "class" => "globe",
                );
            }

            // ✅ Regions
            if ($can_view_master("can_view_regions")) {
                $master_data_submenu[] = array("name" => "regions", "url" => "regions", "class" => "map");
            }

            // ✅ Cities
            if ($can_view_master("can_view_cities")) {
                $master_data_submenu[] = array("name" => "cities", "url" => "cities", "class" => "map-pin");
            }

            // ✅ Only show Master Data parent if any child exists
            if (count($master_data_submenu)) {
                $sidebar_menu["master_data"] = array(
                    "name" => "master_data",
                    "class" => "database",
                    "url"   => "#",
                    "submenu" => $master_data_submenu
                );
            }


            $vendors_master_submenu = array();

            if ($this->ci->login_user->is_admin) {
                $vendors_master_submenu[] = array("name" => "vendor_group_fees", "url" => "vendor_group_fees", "class" => "dollar-sign");
                $vendors_master_submenu[] = array("name" => "vendor_document_types", "url" => "vendor_document_types", "class" => "dollar-sign");

                $vendors_master_submenu[] = array(
                    "name" => "vendor_update_requests",
                    "url"  => "vendor_update_requests",
                    "class" => "check-square"
                );

                $vendors_master_submenu[] = array(
                    "name" => "vendor_update_requests_by_vendor",
                    "url"  => "vendor_update_requests/vendors",
                    "class" => "users"
                );

                // ✅ NEW: specialties_filter under Vendors Master
                $vendors_master_submenu[] = array(
                    "name" => "specialties_filter",
                    "url"  => "vendors/specialties_filter",
                    "class" => "filter"   // you can use 'filter', 'layers', 'sliders', etc.
                );

                $vendors_master_submenu[] = array("name" => "vendors", "url" => "vendors", "class" => "briefcase");
            }

            if (!$this->ci->login_user->is_admin && $can_view_master("can_view_vendor_group_fees")) {
                $vendors_master_submenu[] = array("name" => "vendor_group_fees", "url" => "vendor_group_fees", "class" => "dollar-sign");
            }

            if ($this->ci->login_user->is_admin || $can_view_master("can_view_vendor_groups")) {
                $vendors_master_submenu[] = array("name" => "vendor_groups", "url" => "vendor_groups", "class" => "users");
            }

            if (!$this->ci->login_user->is_admin && $can_view_master("can_view_vendor_document_types")) {
                $vendors_master_submenu[] = array("name" => "vendor_document_types", "url" => "vendor_document_types", "class" => "dollar-sign");
            }

            if (!$this->ci->login_user->is_admin && $can_view_master("can_view_vendors")) {
                $vendors_master_submenu[] = array("name" => "vendors", "url" => "vendors", "class" => "briefcase");
            }
            



            // Tender Master (Admin only)
            if ($this->ci->login_user->is_admin) {
                $tender_master_submenu = [
                    ["name" => "tender_department_users",          "url" => "tender_department_users",          "class" => "user-plus"],
                    ["name" => "tender_department_manager_users",  "url" => "tender_department_manager_users",  "class" => "user-check"],
                    ["name" => "tender_finance_users",             "url" => "tender_finance_users",             "class" => "dollar-sign"],
                    ["name" => "tender_committee_users",           "url" => "tender_committee_users",           "class" => "users"],
                    ["name" => "tender_procurement_users",         "url" => "tender_procurement_users",         "class" => "briefcase"],
                    ["name" => "tender_technical_users",           "url" => "tender_technical_users",           "class" => "tool"],
                    ["name" => "tender_commercial_users",          "url" => "tender_commercial_users",          "class" => "bar-chart-2"],
                ];

                $sidebar_menu["tender_master"] = [
                    "name" => "tender_master",
                    "class" => "layers",
                    "url" => "#",
                    "submenu" => $tender_master_submenu
                ];
            }


            $tender_submenu = array();
            $tender_view = function ($key) use ($permissions) {
                return $this->ci->login_user->is_admin || (bool) get_array_value($permissions, "can_view_tender_" . $key);
            };
            if ($tender_view("requests")) {
                $tender_submenu[] = array("name" => "tender_requests", "url" => "tender_requests", "class" => "file-text");
            }
            if ($tender_view("manager_inbox")) {
                $tender_submenu[] = array("name" => "tender_department_manager_inbox", "url" => "tender_department_manager_inbox", "class" => "user-check");
            }
            if ($tender_view("finance_inbox")) {
                $tender_submenu[] = array("name" => "tender_finance_inbox", "url" => "tender_finance_inbox", "class" => "dollar-sign");
            }
            if ($tender_view("committee")) {
                $tender_submenu[] = array("name" => "tender_committee_inbox", "url" => "tender_committee_inbox", "class" => "users");
            }
            if ($tender_view("procurement")) {
                $tender_submenu[] = array("name" => "tender_procurement_inbox", "url" => "tender_procurement_inbox", "class" => "briefcase");
            }
            if ($tender_view("technical_eval")) {
                $tender_submenu[] = array("name" => "tender_technical_inbox", "url" => "tender_technical_inbox", "class" => "tool");
            }
            
            if ($tender_view("committee") && ($this->ci->login_user->is_admin || (bool) get_array_value($permissions, "can_tender_open_bids_3key"))) {
                $tender_submenu[] = array("name" => "tender_committee_opening_inbox", "url" => "tender_committee_opening_inbox", "class" => "unlock");
            }
            
            if ($tender_view("commercial_eval")) {
                $tender_submenu[] = array("name" => "tender_commercial_inbox", "url" => "tender_commercial_inbox", "class" => "bar-chart-2");
            }
            if (count($tender_submenu)) {
                $sidebar_menu["tender"] = array(
                    "name" => "tender",
                    "class" => "clipboard",
                    "url"   => "#",
                    "submenu" => $tender_submenu
                );
            }

            


            $gitpass_master_submenu = array();
            $gp_view = function ($key) use ($permissions) {
                return $this->ci->login_user->is_admin || (bool) get_array_value($permissions, "can_view_gate_pass_" . $key);
            };
            if ($gp_view("companies")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_companies", "url" => "gate_pass_companies", "class" => "briefcase");
            }
            if ($gp_view("departments")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_departments", "url" => "gate_pass_departments", "class" => "layers");
            }
            if ($gp_view("visitors")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_visitors", "url" => "gate_pass_visitors", "class" => "users");
            }
            if ($gp_view("purposes")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_purposes", "url" => "gate_pass_purposes", "class" => "file-text");
            }
            if ($gp_view("reasons")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_reasons", "url" => "gate_pass_reasons", "class" => "alert-circle");
            }
            if ($gp_view("department_users")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_department_users", "url" => "gate_pass_department_users", "class" => "user-plus");
            }
            if ($gp_view("commercial_users")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_commercial_users", "url" => "gate_pass_commercial_users", "class" => "dollar-sign");
            }
            if ($gp_view("security_users")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_security_users", "url" => "gate_pass_security_users", "class" => "shield");
            }
            if ($gp_view("rop_users")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_rop_users", "url" => "gate_pass_rop_users", "class" => "file-check");
            }
            if ($gp_view("request_list")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_filter_requests", "url" => "gate_pass_request_list", "class" => "filter");
            }
            if ($gp_view("fee_rules")) {
                $gitpass_master_submenu[] = array("name" => "gate_pass_fee_rules", "url" => "gate_pass_fee_rules", "class" => "dollar-sign");
            }
            if (count($gitpass_master_submenu)) {
                $sidebar_menu["gitpass_master"] = array(
                    "name" => "gitpass_master",
                    "class" => "key",
                    "url"   => "#",
                    "submenu" => $gitpass_master_submenu
                );
            }

                // Vendor Specialties Filter (Vendors Master)
                if (
                    !$this->ci->login_user->is_admin
                    && (
                        $can_view_master("can_view_vendor_specialties")
                        || $can_view_master("can_filter_vendor_specialties")
                    )
                ) {
                    $vendors_master_submenu[] = array(
                        "name" => "specialties_filter",
                        "url"  => "vendors/specialties_filter",
                        "class" => "filter"
                    );
                }

                if (!$this->ci->login_user->is_admin) {
                    // Show VUR menu if user can view OR review/approve/reject
                    $has_vur_access = $can_view_master("can_view_vendor_update_requests")
                        || $can_view_master("can_review_vendor_update_requests")
                        || $can_view_master("can_approve_vendor_update_requests")
                        || $can_view_master("can_reject_vendor_update_requests");

                    if ($has_vur_access) {
                        $vendors_master_submenu[] = array(
                            "name" => "vendor_update_requests",
                            "url"  => "vendor_update_requests",
                            "class" => "check-square"
                        );
                    }

                    // Group by Vendor (separate permission)
                    if ($has_vur_access && $can_view_master("can_view_vendor_update_requests_by_vendor")) {
                        $vendors_master_submenu[] = array(
                            "name" => "vendor_update_requests_by_vendor",
                            "url"  => "vendor_update_requests/vendors",
                            "class" => "users"
                        );
                    }
                }

                if (count($vendors_master_submenu)) {
                    $sidebar_menu["vendors_master"] = array(
                        "name" => "vendors_master",
                        "class" => "briefcase",
                        "url"   => "#",
                        "submenu" => $vendors_master_submenu
                    );
                }



                // PTW Master — permission-gated (admin always gets all; non-admins get what their role allows)
                $ptw_view = function ($section) use ($can_view_master) {
                    return $this->ci->login_user->is_admin || $can_view_master("can_view_ptw_{$section}");
                };

                $ptw_master_submenu = array();

                if ($ptw_view("hsse_users"))      $ptw_master_submenu[] = array("name" => "ptw_hsse_users",           "url" => "ptw_hsse_users",                              "class" => "users");
                if ($ptw_view("hmo_users"))       $ptw_master_submenu[] = array("name" => "ptw_hmo_users",            "url" => "ptw_hmo_users",                               "class" => "users");
                if ($ptw_view("terminal_users"))  $ptw_master_submenu[] = array("name" => "ptw_terminal_users",       "url" => "ptw_terminal_users",                          "class" => "users");
                if ($ptw_view("request_list"))    $ptw_master_submenu[] = array("name" => "ptw_request_list",         "url" => "ptw_request_list",                            "class" => "filter");
                if ($ptw_view("hazard_documents"))$ptw_master_submenu[] = array("name" => "ptw_hazard_documents_master","url" => "ptw_requirement_definitions/hazard_documents","class" => "file-text");
                if ($ptw_view("ppe"))             $ptw_master_submenu[] = array("name" => "ptw_ppe_master",           "url" => "ptw_requirement_definitions/ppe",             "class" => "shield");
                if ($ptw_view("preparation"))     $ptw_master_submenu[] = array("name" => "ptw_preparation_master",   "url" => "ptw_requirement_definitions/preparation",     "class" => "check-square");
                if ($ptw_view("reasons"))         $ptw_master_submenu[] = array("name" => "ptw_reasons_master",       "url" => "ptw_reasons",                                 "class" => "alert-circle");

                if (count($ptw_master_submenu)) {
                    $sidebar_menu["ptw_master"] = array(
                        "name"    => "ptw_master",
                        "url"     => "#",
                        "class"   => "tool",
                        "submenu" => $ptw_master_submenu,
                    );
                }


                        $db = db_connect();
                        $ptw_hsse_users_table = $db->prefixTable("ptw_hsse_users");

                        $my_ptw_hsse_user = $db->query(
                            "SELECT id FROM $ptw_hsse_users_table
                            WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
                            [$this->ci->login_user->id]
                        )->getRow();

                        if ($my_ptw_hsse_user || $this->ci->login_user->is_admin) {
                            $sidebar_menu["ptw_hsse_inbox"] = array(
                                "name"  => "ptw_hsse_inbox",
                                "url"   => "ptw_hsse_inbox",
                                "class" => "shield"
                            );
                        }

                        $ptw_hmo_users_table = $db->prefixTable("ptw_hmo_users");
                        $my_ptw_hmo_user = $db->query(
                            "SELECT id FROM $ptw_hmo_users_table
                             WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
                            [$this->ci->login_user->id]
                        )->getRow();

                        if ($my_ptw_hmo_user || $this->ci->login_user->is_admin) {
                            $sidebar_menu["ptw_hmo_inbox"] = array(
                                "name"  => "ptw_hmo_inbox",
                                "url"   => "ptw_hmo_inbox",
                                "class" => "clipboard"
                            );
                        }

                        $ptw_terminal_users_table = $db->prefixTable("ptw_terminal_users");
                        $my_ptw_terminal_user = $db->query("SELECT id FROM $ptw_terminal_users_table WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$this->ci->login_user->id])->getRow();

                        if ($my_ptw_terminal_user || $this->ci->login_user->is_admin) {
                            $sidebar_menu["ptw_terminal_inbox"] = array(
                                "name"  => "ptw_terminal_inbox",
                                "url"   => "ptw_terminal_inbox",
                                "class" => "clipboard"
                            );
                        }


                $db = Database::connect();
                $vendor_users_table = $db->prefixTable("vendor_users");
                $my_vendor = $db->query(
                    "SELECT vendor_id 
                        FROM $vendor_users_table 
                        WHERE user_id=? AND deleted=0 AND status='active'
                        ORDER BY is_owner DESC, id DESC
                        LIMIT 1",
                    [$this->ci->login_user->id]
                )->getRow();

                if ($my_vendor) {
                    $sidebar_menu["vendor_portal"] = array(
                        "name" => "vendor_portal",
                        "url"  => "vendor_portal",
                        "class" => "briefcase"
                    );
                }



                $db = Database::connect();
                $gate_pass_users_table = $db->prefixTable("gate_pass_users");

                $my_gate_pass_user = $db->query(
                    "SELECT id 
                        FROM $gate_pass_users_table 
                        WHERE user_id=? AND deleted=0 AND status='active'
                        ORDER BY id DESC
                        LIMIT 1",
                    [$this->ci->login_user->id]
                )->getRow();

                if ($my_gate_pass_user) {
                    $sidebar_menu["gate_pass_portal"] = array(
                        "name"  => "gate_pass_portal",
                        "url"   => "gate_pass_portal",
                        "class" => "key"
                    );
                }





                $gate_pass_dept_users_table = $db->prefixTable("gate_pass_department_users");
                    $my_gate_pass_dept_user = $db->query(
                        "SELECT id FROM $gate_pass_dept_users_table 
                        WHERE user_id=? AND deleted=0 AND status='active'
                        LIMIT 1",
                        [$this->ci->login_user->id]
                    )->getRow();

                    if ($my_gate_pass_dept_user) {
                        $sidebar_menu["gate_pass_department_requests"] = array(
                            "name"  => "gate_pass_department_requests",
                            "url"   => "gate_pass_department_requests",
                            "class" => "layers"
                        );
                    }

                    // Commercial users: show "Commercial Requests" in sidebar when logged in
                    try {
                        $gate_pass_commercial_users_table = $db->prefixTable("gate_pass_commercial_users");
                        $my_gate_pass_commercial_user = $db->query(
                            "SELECT id FROM $gate_pass_commercial_users_table 
                            WHERE user_id=? AND deleted=0 AND status='active'
                            LIMIT 1",
                            [$this->ci->login_user->id]
                        )->getRow();
                        if ($my_gate_pass_commercial_user) {
                            $sidebar_menu["gate_pass_commercial_requests"] = array(
                                "name"  => "gate_pass_commercial_requests",
                                "url"   => "gate_pass_commercial_inbox",
                                "class" => "dollar-sign"
                            );
                        }
                    } catch (\Throwable $e) {
                        // Table may not exist yet; do not add menu item
                    }

                    // Security users: show "Security Requests" in sidebar when logged in
                    try {
                        $gate_pass_security_users_table = $db->prefixTable("gate_pass_security_users");
                        $my_gate_pass_security_user = $db->query(
                            "SELECT id FROM $gate_pass_security_users_table 
                            WHERE user_id=? AND deleted=0 AND status='active'
                            LIMIT 1",
                            [$this->ci->login_user->id]
                        )->getRow();
                        if ($my_gate_pass_security_user) {
                            $sidebar_menu["gate_pass_security_requests"] = array(
                                "name"  => "gate_pass_security_requests",
                                "url"   => "gate_pass_security_inbox",
                                "class" => "shield"
                            );
                        }
                    } catch (\Throwable $e) {
                        // Table may not exist yet; do not add menu item
                    }

                    // ROP users: show "ROP Requests" in sidebar when logged in
                    try {
                        $gate_pass_rop_users_table = $db->prefixTable("gate_pass_rop_users");
                        $my_gate_pass_rop_user = $db->query(
                            "SELECT id FROM $gate_pass_rop_users_table 
                            WHERE user_id=? AND deleted=0 AND status='active'
                            LIMIT 1",
                            [$this->ci->login_user->id]
                        )->getRow();
                        if ($my_gate_pass_rop_user) {
                            $sidebar_menu["gate_pass_rop_requests"] = array(
                                "name"  => "gate_pass_rop_requests",
                                "url"   => "gate_pass_rop_inbox",
                                "class" => "file-check"
                            );
                        }
                    } catch (\Throwable $e) {
                        // Table may not exist yet; do not add menu item
                    }

 



            // show for now to admin only (ignore permissions as you requested)
            if ($this->ci->login_user->is_admin) {
                $sidebar_menu["master_data"] = array(
                    "name" => "master_data",
                    "class" => "database",
                    "url"   => "#",
                    "submenu" => $master_data_submenu
                );
            }

            if (get_setting("module_event") == "1") {
                $sidebar_menu["events"] = array("name" => "events", "url" => "events", "class" => "calendar");
            }


            if ($this->ci->login_user->is_admin || $access_client) {
                $sidebar_menu["clients"] = array("name" => "clients", "url" => "clients", "class" => "briefcase");
            }





            if ($this->ci->login_user->is_admin || !get_array_value($this->ci->login_user->permissions, "do_not_show_projects")) {
                $sidebar_menu["projects"] = array("name" => "projects", "url" => "projects/all_projects", "class" => "command");
            }

            $sidebar_menu["tasks"] = array("name" => "tasks", "url" => "tasks/all_tasks", "class" => "check-circle");

            if (get_setting("module_lead") == "1" && ($this->ci->login_user->is_admin || $access_lead)) {
                $sidebar_menu["leads"] = array("name" => "leads", "url" => "leads", "class" => "layers");
            }

            if (get_setting("module_subscription") && ($this->ci->login_user->is_admin || $access_subscription)) {
                $sidebar_menu["subscriptions"] = array("name" => "subscriptions", "url" => "subscriptions", "class" => "repeat");
            }

            $sales_submenu = array();

            if (get_setting("module_invoice") == "1" && ($this->ci->login_user->is_admin || $access_invoice)) {
                $sales_submenu[] = array("name" => "invoices", "url" => "invoices", "class" => "file-text");
            }

            if (get_setting("module_order") == "1" && ($this->ci->login_user->is_admin || $access_order)) {
                $sales_submenu[] = array("name" => "orders_list", "url" => "orders", "class" => "truck");
                $sales_submenu[] = array("name" => "store", "url" => "store", "class" => "list");
            }

            if (get_setting("module_invoice") == "1" && ($this->ci->login_user->is_admin || $access_invoice)) {
                $sales_submenu[] = array("name" => "invoice_payments", "url" => "invoice_payments", "class" => "compass");
            }

            $access_items = $permission_manager->can_manage_items();

            if ($access_items) {
                $sales_submenu[] = array("name" => "items", "url" => "items", "class" => "list");
            }

            if (get_setting("module_contract") && ($this->ci->login_user->is_admin || $access_contract)) {
                $sales_submenu[] = array("name" => "contracts", "url" => "contracts", "class" => "book-open");
            }

            if (count($sales_submenu)) {
                $sidebar_menu["sales"] = array("name" => "sales", "class" => "shopping-cart", "url" => "#", "submenu" => $sales_submenu);
            }


            $prospects_submenu = array();

            if (get_setting("module_estimate") && ($this->ci->login_user->is_admin || $access_estimate)) {

                $prospects_submenu["estimates"] = array("name" => "estimate_list", "url" => "estimates", "class" => "file");

                if (get_setting("module_estimate_request")) {
                    $prospects_submenu["estimate_requests"] = array("name" => "estimate_requests", "url" => "estimate_requests", "class" => "file");
                    $prospects_submenu["estimate_forms"] = array("name" => "estimate_forms", "url" => "estimate_requests/estimate_forms", "class" => "file");
                }
            }

            if (get_setting("module_proposal") && ($this->ci->login_user->is_admin || $access_proposal)) {
                $prospects_submenu["proposals"] = array("name" => "proposals", "url" => "proposals", "class" => "coffee");
            }

            if (count($prospects_submenu)) {
                $sidebar_menu["prospects"] = array("name" => "prospects", "url" => "estimates", "class" => "anchor", "submenu" => $prospects_submenu);
            }



            if (get_setting("module_note") == "1") {
                $sidebar_menu["notes"] = array("name" => "notes", "url" => "notes", "class" => "book");
            }

            if (get_setting("module_message") == "1" && $access_messages) {
                $sidebar_menu["messages"] = array("name" => "messages", "url" => "messages", "class" => "message-circle", "badge" => count_unread_message(), "badge_class" => "bg-primary");
            }



            $team_submenu = array();

            if ($this->ci->login_user->is_admin && get_array_value($this->ci->login_user->permissions, "hide_team_members_list") != "1") {
                $team_submenu["team_members"] = array("name" => "team_members", "url" => "team_members", "class" => "users");
            }


            if (get_setting("module_attendance") == "1" && ($this->ci->login_user->is_admin || $access_timecard)) {
                $team_submenu["attendance"] = array("name" => "attendance", "url" => "attendance", "class" => "clock");
            } else if (get_setting("module_attendance") == "1") {
                $team_submenu["attendance"] = array("name" => "attendance", "url" => "attendance/attendance_info", "class" => "clock");
            }


            if (get_setting("module_leave") == "1" && ($this->ci->login_user->is_admin || $access_leave)) {
                $team_submenu["leaves"] = array("name" => "leaves", "url" => "leaves", "class" => "log-out");
            } else if (get_setting("module_leave") == "1") {
                $team_submenu["leaves"] = array("name" => "leaves", "url" => "leaves/leave_info", "class" => "log-out");
            }



            if (get_setting("module_timeline") == "1" && $access_timeline) {
                $team_submenu["timeline"] = array("name" => "timeline", "url" => "timeline", "class" => "send");
            }


            if (get_setting("module_announcement") == "1") {
                $team_submenu["announcements"] = array("name" => "announcements", "url" => "announcements", "class" => "bell");
            }

            if (get_setting("module_help")) {
                $team_submenu["help"] = array("name" => "help", "url" => "help", "class" => "help-circle");
            }

            if ($this->ci->login_user->is_admin && count($team_submenu)) {
                $first_team_submenu = reset($team_submenu);
                $team_menu_url = isset($team_submenu["team_members"]) ? "team_members" : get_array_value($first_team_submenu, "url");
                if (!$team_menu_url) {
                    $team_menu_url = "team_members";
                }

                $sidebar_menu["team"] = array("name" => "team", "url" => $team_menu_url, "class" => "users", "submenu" => $team_submenu);
            }


            if (get_setting("module_ticket") == "1" && ($this->ci->login_user->is_admin || $access_ticket)) {

                $ticket_badge = 0;
                if ($this->ci->login_user->is_admin || $access_ticket === "all") {
                    $ticket_badge = count_new_tickets();
                } else if ($access_ticket === "specific") {
                    $specific_ticket_permission = get_array_value($permissions, "ticket_specific");
                    $ticket_badge = count_new_tickets($specific_ticket_permission);
                } else if ($access_ticket === "assigned_only") {
                    $ticket_badge = count_new_tickets("", $this->ci->login_user->id);
                }

                $sidebar_menu["tickets"] = array("name" => "tickets", "url" => "tickets", "class" => "life-buoy", "badge" => $ticket_badge, "badge_class" => "bg-primary");
            }

            $manage_help_and_knowledge_base = ($this->ci->login_user->is_admin || get_array_value($permissions, "help_and_knowledge_base"));

            if (get_setting("module_knowledge_base") == "1" && $manage_help_and_knowledge_base) {
                $sidebar_menu["knowledge_base"] = array(
                    "name" => "knowledge_base",
                    "url" => "knowledge_base",
                    "class" => "help-circle",
                    "sub_pages" => array(
                        "help/knowledge_base_articles",
                        "help/knowledge_base_categories"
                    )
                );
            }

            $access_file_manager = true;
            if (get_setting("module_file_manager") == "1" && ($this->ci->login_user->is_admin || $access_file_manager)) {
                $sidebar_menu["file_manager"] = array("name" => "files", "url" => "file_manager", "class" => "folder");
            }

            if (get_setting("module_expense") == "1" && ($this->ci->login_user->is_admin || $access_expense)) {
                $sidebar_menu["expenses"] = array("name" => "expenses", "url" => "expenses", "class" => "arrow-right-circle");
            }

            $sidebar_menu["reports"] = array(
                "name" => "reports",
                "url" => "reports/index",
                "class" => "pie-chart",
                "sub_pages" => array(
                    "invoices/invoices_summary",
                    "invoices/invoice_details",
                    "orders/orders_summary",
                    "projects/all_timesheets",
                    "expenses/income_vs_expenses",
                    "invoice_payments/payments_summary",
                    "expenses/summary",
                    "projects/team_members_summary",
                    "leads/converted_to_client_report",
                    "tickets/tickets_chart_report"
                )
            );

            if ($this->ci->login_user->is_admin || get_array_value($this->ci->login_user->permissions, "can_manage_all_kinds_of_settings")) {
                $sidebar_menu["settings"] = array(
                    "name" => "settings",
                    "url" => "settings/general",
                    "class" => "settings",
                    "sub_pages" => array(
                        "email_templates/index",
                        "left_menu/index",
                        "updates/index",
                        "roles/index",
                        "roles/user_roles",
                        "team/index",
                        "dashboard/client_default_dashboard",
                        "left_menus/index",
                        "company/index",
                        "item_categories/index",
                        "payment_methods/index",
                        "custom_fields/view",
                        "client_groups/index",
                        "expense_categories/index",
                        "leave_types/index",
                        "ticket_types/index",
                        "lead_status/index",
                        "pages/index",
                        "rise_plugins/index"
                    )
                );
            }

            $sidebar_menu = app_hooks()->apply_filters('app_filter_staff_left_menu', $sidebar_menu);
        } else {
            //client menu

            $sidebar_menu[] = $dashboard_menu;

            if ($this->ci->can_client_access("event")) {
                $sidebar_menu[] = array("name" => "events", "url" => "events", "class" => "calendar");
            }

            if ($this->ci->can_client_access("note") && get_setting("client_can_access_notes")) {
                $sidebar_menu[] = array("name" => "notes", "url" => "notes", "class" => "book");
            }

            //check message access settings for clients
            if ($this->ci->can_client_access("message") && get_setting("client_message_users")) {
                $sidebar_menu[] = array("name" => "messages", "url" => "messages", "class" => "message-circle", "badge" => count_unread_message());
            }

            if ($this->ci->can_client_access("project", false)) {
                $sidebar_menu[] = array("name" => "projects", "url" => "projects/all_projects", "class" => "command");
            }

            if ($this->ci->can_client_access("contract")) {
                $sidebar_menu[] = array("name" => "contracts", "url" => "contracts", "class" => "book-open");
            }

            if ($this->ci->can_client_access("proposal")) {
                $sidebar_menu[] = array("name" => "proposals", "url" => "proposals", "class" => "coffee");
            }

            if ($this->ci->can_client_access("estimate")) {
                $sidebar_menu[] = array("name" => "estimates", "url" => "estimates", "class" => "file");
            }

            if ($this->ci->can_client_access("subscription")) {
                $sidebar_menu["subscriptions"] = array("name" => "subscriptions", "url" => "subscriptions", "class" => "repeat");
            }

            if ($this->ci->can_client_access("invoice")) {
                if ($this->ci->can_client_access("invoice")) {
                    $sidebar_menu[] = array("name" => "invoices", "url" => "invoices", "class" => "file-text");
                }
                if ($this->ci->can_client_access("payment", false)) {
                    $sidebar_menu[] = array("name" => "invoice_payments", "url" => "invoice_payments", "class" => "compass");
                }
            }

            if ($this->ci->can_client_access("store", false) && get_setting("client_can_access_store")) {
                $sidebar_menu[] = array("name" => "store", "url" => "store", "class" => "truck");
                $sidebar_menu[] = array("name" => "orders", "url" => "orders", "class" => "shopping-cart");
            }

            if ($this->ci->can_client_access("ticket")) {
                $sidebar_menu[] = array("name" => "tickets", "url" => "tickets", "class" => "life-buoy");
            }

            if ($this->ci->can_client_access("announcement")) {
                $sidebar_menu[] = array("name" => "announcements", "url" => "announcements", "class" => "bell");
            }

            $sidebar_menu[] = array("name" => "users", "url" => "clients/users", "class" => "users");

            if (get_setting("client_can_view_files")) {
                $sidebar_menu[] = array("name" => "files", "url" => "clients/files/" . $this->ci->login_user->id . "/page_view", "class" => "image");
            }

            $sidebar_menu[] = array("name" => "my_profile", "url" => "clients/contact_profile/" . $this->ci->login_user->id, "class" => "settings");

            if ($this->ci->can_client_access("knowledge_base")) {
                $sidebar_menu[] = array("name" => "knowledge_base", "url" => "knowledge_base", "class" => "help-circle");
            }

            $sidebar_menu = app_hooks()->apply_filters('app_filter_client_left_menu', $sidebar_menu);
        }

        return $this->position_items_for_default_left_menu($sidebar_menu);
    }

    function _get_active_menu($sidebar_menu = array())
    {
        $router = service('router');
        $controller_name = strtolower(get_actual_controller_name($router));
        $uri_string = uri_string();
        $current_url = get_uri($uri_string);
        $method_name = $router->methodName();

        $found_url_active_key = null;

        foreach ($sidebar_menu as $key => $menu) {
            if (isset($menu["name"])) {
                $menu_name = get_array_value($menu, "name");
                $menu_url = get_array_value($menu, "url");

                //compare with controller name
                if ($controller_name == $menu_url) {
                    $found_url_active_key = $key;
                }

                //compare with current url
                if ($menu_url && ($menu_url === $current_url || get_uri($menu_url) === $current_url)) {
                    $sidebar_menu[$key]["is_active_menu"] = 1;
                    return $sidebar_menu;
                }

                // check for controller match only if no active key is set
                if ($found_url_active_key === null && ($controller_name == $menu_url || $menu_name === $controller_name)) {
                    $found_url_active_key = $key;
                }

                //check in submenu values
                $submenu = get_array_value($menu, "submenu");
                if ($submenu && count($submenu)) {
                    foreach ($submenu as $sub_menu) {
                        if (isset($sub_menu['name'])) {

                            $sub_menu_url = get_array_value($sub_menu, "url");

                            if ($controller_name == $sub_menu_url) {
                                $found_url_active_key = $key;
                            }

                            //compare with current url
                            if ($sub_menu_url === $current_url || get_uri($sub_menu_url) === $current_url) {
                                $found_url_active_key = $key;
                            }

                            //compare with controller name
                            if (get_array_value($sub_menu, "name") === $controller_name) {
                                $found_url_active_key = $key;
                            } else if (get_array_value($sub_menu, "category") === $controller_name) {
                                $found_url_active_key = $key;
                            }
                        }
                    }
                }


                $sub_pages = get_array_value($menu, "sub_pages");
                if ($sub_pages) {
                    foreach ($sub_pages as $sub_page_ur) {
                        if ($sub_page_ur == $controller_name . "/" . $method_name) {
                            $found_url_active_key = $key;
                        }
                    }
                }
            }
        }

        if (!is_null($found_url_active_key)) {
            $sidebar_menu[$found_url_active_key]["is_active_menu"] = 1;
        }


        return $sidebar_menu;
    }

    function get_available_items($type = "default")
    {
        $items_array = $this->_prepare_sidebar_menu_items($type);

        $default_left_menu_items = $this->_get_left_menu_from_setting($type);

        if ($default_left_menu_items && is_array($default_left_menu_items) && count($default_left_menu_items)) {
            //remove used items
            foreach ($default_left_menu_items as $default_item) {
                unset($items_array[get_array_value($default_item, "name")]);
            }
        } else {
            //since all menu items will be added to the customization area when there is no item, don't show anything here
            $items_array = array();
        }

        $items = "";
        foreach ($items_array as $item) {
            $items .= $this->_get_item_data($item, true);
        }

        return $items ? $items : "<span class='text-off empty-area-text'>" . app_lang('no_more_items_available') . "</span>";
    }

    private function _prepare_sidebar_menu_items($type = "", $return_sub_menu_data = false)
    {
        $final_items_array = array();
        $items_array = $this->_get_sidebar_menu_items($type);

        foreach ($items_array as $item) {
            $main_menu_name = get_array_value($item, "name");

            if (isset($item["submenu"])) {
                //first add this menu removing the submenus
                $main_menu = $item;
                unset($main_menu["submenu"]);
                $final_items_array[$main_menu_name] = $main_menu;

                $submenu = get_array_value($item, "submenu");
                foreach ($submenu as $key => $s_menu) {

                    if ($return_sub_menu_data) {
                        $s_menu["is_sub_menu"] = true;
                    }

                    if (get_array_value($s_menu, "class")) {
                        $final_items_array[get_array_value($s_menu, "name")] = $s_menu;
                    }
                }
            } else {
                $final_items_array[$main_menu_name] = $item;
            }
        }

        //add todo
        $final_items_array["todo"] = array("name" => "todo", "url" => "todo", "class" => "check-square");

        return $final_items_array;
    }

    private function _get_left_menu_from_setting_for_rander($is_preview = false, $type = "default")
    {
        $user_left_menu = get_setting("user_" . $this->ci->login_user->id . "_left_menu");
        $default_left_menu = ($type == "client_default" || $this->ci->login_user->user_type == "client") ? get_setting("default_client_left_menu") : get_setting("default_left_menu");
        $custom_left_menu = "";

        //for preview, show the edit type preview
        if ($is_preview) {
            $custom_left_menu = $default_left_menu; //default preview
            if ($type == "user") {
                $custom_left_menu = $user_left_menu ? $user_left_menu : $default_left_menu; //user level preview
            }
        } else {
            $custom_left_menu = $user_left_menu ? $user_left_menu : $default_left_menu; //page rander
        }

        return $custom_left_menu ? json_decode(json_encode(@unserialize($custom_left_menu)), true) : array();
    }

    private function _get_left_menu_from_setting($type)
    {
        if ($type == "client_default") {
            $default_left_menu = get_setting("default_client_left_menu");
        } else if ($type == "user") {
            $default_left_menu = get_setting("user_" . $this->ci->login_user->id . "_left_menu");
        } else {
            $default_left_menu = get_setting("default_left_menu");
        }

        $result = $default_left_menu ? json_decode(json_encode(@unserialize($default_left_menu)), true) : array();

        if (!is_array($result)) {
            $result = array();
        }

        return $result;
    }

    public function _get_item_data($item, $is_default_item = false)
    {
        $name = get_array_value($item, "name");
        $language_key = get_array_value($item, "language_key");
        $url = get_array_value($item, "url");
        $is_sub_menu = get_array_value($item, "is_sub_menu");
        $open_in_new_tab = get_array_value($item, "open_in_new_tab");
        $icon = get_array_value($item, "icon");

        if ($name) {
            $sub_menu_class = "";
            $clickable_menu_class = "make-sub-menu";
            $clickable_icon = "<i data-feather='corner-right-down' class='icon-14'></i>";
            if ($is_sub_menu) {
                $sub_menu_class = "ml20";
                $clickable_menu_class = "make-root-menu";
                $clickable_icon = "<i data-feather='corner-up-left' class='icon-14'></i>";
            }

            $extra_attr = "";
            $edit_button = "";
            $name_lang = "";
            if ($is_default_item || !$url) {
                $name_lang = app_lang($name);
            } else {
                if ($language_key) {
                    $name_lang = app_lang($language_key);
                } else {
                    $name_lang = $name;
                }

                //custom menu item
                $extra_attr = "data-url='$url' data-icon='$icon' data-custom_menu_item_id='" . rand(2000, 400000000) . "' data-open_in_new_tab='$open_in_new_tab' data-language_key='$language_key'";
                $edit_button = modal_anchor(get_uri("left_menus/add_menu_item_modal_form"), "<i data-feather='edit' class='icon-14'></i> ", array("title" => app_lang('edit'), "class" => "custom-menu-edit-button", "data-post-title" => $name, "data-post-url" => $url, "data-post-is_sub_menu" => $is_sub_menu, "data-post-icon" => $icon, "data-post-open_in_new_tab" => $open_in_new_tab, "data-post-language_key" => $language_key));
            }

            return "<div data-value='" . $name . "' $extra_attr class='left-menu-item mb5 widget clearfix p10 bg-white $sub_menu_class'>
                        <span class='float-start text-start'><i data-feather='move' class='icon-16 text-off mr5'></i> " . $name_lang . "</span>
                        <span class='float-end invisible'>
                            <span class='clickable $clickable_menu_class toggle-menu-icon' title='" . app_lang("make_previous_items_sub_menu") . "'>$clickable_icon</span>
                            $edit_button
                            <span class='clickable delete-left-menu-item' title=" . app_lang("delete") . "><i data-feather='x' class='icon-14 text-danger'></i></span>
                        </span>
                    </div>";
        }
    }

    function get_sortable_items($type = "default")
    {
        $items = "<div id='menu-item-list-2' class='js-left-menu-scrollbar add-column-drop text-center p15 menu-item-list sortable-items-container'>";

        $default_left_menu_items = $this->_get_left_menu_from_setting($type);
        if (count($default_left_menu_items)) {
            foreach ($default_left_menu_items as $item) {
                $items .= $this->_get_item_data($item);
            }
        } else {
            //if there has no item in the customization panel, show the default items
            $items_array = $this->_prepare_sidebar_menu_items($type, true);
            foreach ($items_array as $item) {
                $items .= $this->_get_item_data($item, true);
            }
        }

        $items .= "</div>";

        return $items;
    }

    function rander_left_menu($is_preview = false, $type = "default")
    {
        $final_left_menu_items = array();
        $custom_left_menu_items = $this->_get_left_menu_from_setting_for_rander($is_preview, $type);

        if ($custom_left_menu_items) {
            $left_menu_items = $this->_prepare_sidebar_menu_items($type);
            $last_final_menu_item = ""; //store the last menu item of final left menu to add submenu to this item
            $permissions = $this->ci->login_user->user_type === "staff" ? $this->ci->login_user->permissions : array();
            $root_names = [];
$submenu_names = [];

            foreach ($custom_left_menu_items as $custom_left_menu_item) {
                $item_value_array = $this->_get_item_array_value($custom_left_menu_item, $left_menu_items);
                $is_sub_menu = get_array_value($custom_left_menu_item, "is_sub_menu");
                
                $item_name = get_array_value($item_value_array, "name");

                if ($is_sub_menu) {
                    if (!$last_final_menu_item || !$item_name) {
                        continue;
                    }
                
                    if (!isset($submenu_names[$last_final_menu_item])) {
                        $submenu_names[$last_final_menu_item] = [];
                    }
                
                    // ✅ prevent duplicate submenu items
                    if (isset($submenu_names[$last_final_menu_item][$item_name])) {
                        continue;
                    }
                
                    $submenu_names[$last_final_menu_item][$item_name] = true;
                    $final_left_menu_items[$last_final_menu_item]["submenu"][] = $item_value_array;
                } else {

                    if ($item_name) {
                        // ✅ prevent duplicate root items
                        if (isset($root_names[$item_name])) {
                            continue;
                        }
                        $root_names[$item_name] = true;
                    }
                    
                    $final_left_menu_items[] = $item_value_array;
                    $last_final_menu_item = end($final_left_menu_items);
                    $last_final_menu_item = key($final_left_menu_items);


                    if (!isset($submenu_names[$last_final_menu_item])) {
                        $submenu_names[$last_final_menu_item] = [];
                    }

                    // Gate Pass Master: attach submenu when building from custom menu (same idea as Master Data sub-items)
                    if ($this->ci->login_user->user_type === "staff" && get_array_value($item_value_array, "name") === "gitpass_master") {
                        $gp_sub = array();
                        $gp_view = function ($key) use ($permissions) {
                            return $this->ci->login_user->is_admin || (bool) get_array_value($permissions, "can_view_gate_pass_" . $key);
                        };
                        if ($gp_view("companies")) $gp_sub[] = array("name" => "gate_pass_companies", "url" => "gate_pass_companies", "class" => "briefcase");
                        if ($gp_view("departments")) $gp_sub[] = array("name" => "gate_pass_departments", "url" => "gate_pass_departments", "class" => "layers");
                        if ($gp_view("visitors")) $gp_sub[] = array("name" => "gate_pass_visitors", "url" => "gate_pass_visitors", "class" => "users");
                        if ($gp_view("purposes")) $gp_sub[] = array("name" => "gate_pass_purposes", "url" => "gate_pass_purposes", "class" => "file-text");
                        if ($gp_view("reasons")) $gp_sub[] = array("name" => "gate_pass_reasons", "url" => "gate_pass_reasons", "class" => "alert-circle");
                        if ($gp_view("department_users")) $gp_sub[] = array("name" => "gate_pass_department_users", "url" => "gate_pass_department_users", "class" => "user-plus");
                        if ($gp_view("commercial_users")) $gp_sub[] = array("name" => "gate_pass_commercial_users", "url" => "gate_pass_commercial_users", "class" => "dollar-sign");
                        if ($gp_view("security_users")) $gp_sub[] = array("name" => "gate_pass_security_users", "url" => "gate_pass_security_users", "class" => "shield");
                        if ($gp_view("rop_users")) $gp_sub[] = array("name" => "gate_pass_rop_users", "url" => "gate_pass_rop_users", "class" => "file-check");
                        if ($gp_view("request_list")) $gp_sub[] = array("name" => "gate_pass_filter_requests", "url" => "gate_pass_request_list", "class" => "filter");
                        if ($gp_view("fee_rules")) $gp_sub[] = array("name" => "gate_pass_fee_rules", "url" => "gate_pass_fee_rules", "class" => "dollar-sign");
                        $final_left_menu_items[$last_final_menu_item]["submenu"] = $gp_sub;
                        $final_left_menu_items[$last_final_menu_item]["url"] = "#";

                        // keep submenu de-dupe map in sync with injected submenu
                        $submenu_names[$last_final_menu_item] = [];
                        foreach ($gp_sub as $sm) {
                            $sm_name = get_array_value($sm, "name");
                            if ($sm_name) {
                                $submenu_names[$last_final_menu_item][$sm_name] = true;
                            }
                        }
                    }
                    // Tender: attach submenu when building from custom menu
                    if ($this->ci->login_user->user_type === "staff" && get_array_value($item_value_array, "name") === "tender") {
                        $tender_sub = array();
                        $tender_view = function ($key) use ($permissions) {
                            return $this->ci->login_user->is_admin || (bool) get_array_value($permissions, "can_view_tender_" . $key);
                        };
                    
                        if ($tender_view("requests")) $tender_sub[] = array("name" => "tender_requests", "url" => "tender_requests", "class" => "file-text");
                        if ($tender_view("manager_inbox")) $tender_sub[] = array("name" => "tender_department_manager_inbox", "url" => "tender_department_manager_inbox", "class" => "user-check");
                        if ($tender_view("finance_inbox")) $tender_sub[] = array("name" => "tender_finance_inbox", "url" => "tender_finance_inbox", "class" => "dollar-sign");
                        if ($tender_view("committee")) $tender_sub[] = array("name" => "tender_committee_inbox", "url" => "tender_committee_inbox", "class" => "users");
                        if ($tender_view("procurement")) $tender_sub[] = array("name" => "tender_procurement_inbox", "url" => "tender_procurement_inbox", "class" => "briefcase");
                        if ($tender_view("technical_eval")) $tender_sub[] = array("name" => "tender_technical_inbox", "url" => "tender_technical_inbox", "class" => "tool");
                        if ($tender_view("committee") && ($this->ci->login_user->is_admin || (bool) get_array_value($permissions, "can_tender_open_bids_3key"))) {
                            $tender_sub[] = array("name" => "tender_committee_opening_inbox", "url" => "tender_committee_opening_inbox", "class" => "unlock");
                        }
                        if ($tender_view("commercial_eval")) $tender_sub[] = array("name" => "tender_commercial_inbox", "url" => "tender_commercial_inbox", "class" => "bar-chart-2");
                    
                        $final_left_menu_items[$last_final_menu_item]["submenu"] = $tender_sub;
                        $final_left_menu_items[$last_final_menu_item]["url"] = "#";

                        // keep submenu de-dupe map in sync with injected submenu
                        $submenu_names[$last_final_menu_item] = [];
                        foreach ($tender_sub as $sm) {
                            $sm_name = get_array_value($sm, "name");
                            if ($sm_name) {
                                $submenu_names[$last_final_menu_item][$sm_name] = true;
                            }
                        }
                    }
                    // Tender Master (admin only): attach submenu when building from custom menu
                    if ($this->ci->login_user->user_type === "staff" && $this->ci->login_user->is_admin && get_array_value($item_value_array, "name") === "tender_master") {
                        $tender_master_sub = [
                            ["name" => "tender_department_users",  "url" => "tender_department_users",  "class" => "user-plus"],
                            ["name" => "tender_department_manager_users",  "url" => "tender_department_manager_users",  "class" => "user-check"],
                            ["name" => "tender_finance_users",     "url" => "tender_finance_users",     "class" => "dollar-sign"],
                            ["name" => "tender_committee_users",   "url" => "tender_committee_users",   "class" => "users"],
                            ["name" => "tender_procurement_users", "url" => "tender_procurement_users", "class" => "briefcase"],
                            ["name" => "tender_technical_users", "url" => "tender_technical_users", "class" => "tool"],
                            ["name" => "tender_commercial_users", "url" => "tender_commercial_users", "class" => "bar-chart-2"],
                        ];
                        $final_left_menu_items[$last_final_menu_item]["submenu"] = $tender_master_sub;
                        $final_left_menu_items[$last_final_menu_item]["url"] = "#";

                        // keep submenu de-dupe map in sync with injected submenu
                        $submenu_names[$last_final_menu_item] = [];
                        foreach ($tender_master_sub as $sm) {
                            $sm_name = get_array_value($sm, "name");
                            if ($sm_name) {
                                $submenu_names[$last_final_menu_item][$sm_name] = true;
                            }
                        }
                    }
                }
            }
        }

        if (count($final_left_menu_items)) {
            $view_data["sidebar_menu"] = $final_left_menu_items;
        } else {
            $view_data["sidebar_menu"] = $this->_get_sidebar_menu_items($type);
        }

        $view_data["is_preview"] = $is_preview;
        $view_data["login_user"] = $this->ci->login_user;


        // ✅ Ensure Vendors Master + specialties_filter are visible when user has access
        //    (even if default/user left menu is customized and doesn't include new items yet)
        if (!$is_preview && $this->ci->login_user->user_type === "staff") {

            // get the default menu version (permission-filtered, with submenu)
            $default_menu = $this->_get_sidebar_menu_items($type);
            $default_vendors_master = $default_menu["vendors_master"] ?? null;

            // Only inject if the user actually has any Vendors Master items available
            if ($default_vendors_master) {
                $found = false;

                foreach ($view_data["sidebar_menu"] as $k => $m) {
                    if (get_array_value($m, "name") === "vendors_master") {
                        $found = true;

                        $submenu = get_array_value($m, "submenu");
                        if (!$submenu || !count($submenu)) {
                            // submenu is missing/empty in customized menu, inject full default submenu
                            $view_data["sidebar_menu"][$k]["submenu"] = get_array_value($default_vendors_master, "submenu");
                            $view_data["sidebar_menu"][$k]["class"]  = get_array_value($default_vendors_master, "class");
                            $view_data["sidebar_menu"][$k]["url"]  = "#";
                        } else {
                            // submenu exists, but may miss new items (e.g., specialties_filter)
                            $existing_names = array();
                            foreach ($submenu as $sm) {
                                $existing_names[] = get_array_value($sm, "name");
                            }

                            $default_submenu = get_array_value($default_vendors_master, "submenu") ?: array();
                            foreach ($default_submenu as $dsm) {
                                $dname = get_array_value($dsm, "name");
                                if ($dname === "specialties_filter" && !in_array($dname, $existing_names, true)) {
                                    $view_data["sidebar_menu"][$k]["submenu"][] = $dsm;
                                }
                            }
                        }

                        break;
                    }
                }

                // if Vendors Master not present at all, append it (default submenu already permission-filtered)
                if (!$found) {
                    $view_data["sidebar_menu"][] = $default_vendors_master;
                }
            }

            // ✅ Ensure Gate Pass Master has submenu and expand behavior (even if left menu is customized)
            $default_gitpass_master = $default_menu["gitpass_master"] ?? null;
            $gate_pass_submenu = $default_gitpass_master ? (array) get_array_value($default_gitpass_master, "submenu") : array();
            // Build submenu from current user permissions if default didn't have it (ensures dropdown works)
            if (!count($gate_pass_submenu)) {
                $gp_perms = $this->ci->login_user->permissions;
                $gp_view = function ($key) use ($gp_perms) {
                    return $this->ci->login_user->is_admin || (bool) get_array_value($gp_perms, "can_view_gate_pass_" . $key);
                };
                if ($gp_view("companies")) $gate_pass_submenu[] = array("name" => "gate_pass_companies", "url" => "gate_pass_companies", "class" => "briefcase");
                if ($gp_view("departments")) $gate_pass_submenu[] = array("name" => "gate_pass_departments", "url" => "gate_pass_departments", "class" => "layers");
                if ($gp_view("visitors")) $gate_pass_submenu[] = array("name" => "gate_pass_visitors", "url" => "gate_pass_visitors", "class" => "users");
                if ($gp_view("purposes")) $gate_pass_submenu[] = array("name" => "gate_pass_purposes", "url" => "gate_pass_purposes", "class" => "file-text");
                if ($gp_view("reasons")) $gate_pass_submenu[] = array("name" => "gate_pass_reasons", "url" => "gate_pass_reasons", "class" => "alert-circle");
                if ($gp_view("department_users")) $gate_pass_submenu[] = array("name" => "gate_pass_department_users", "url" => "gate_pass_department_users", "class" => "user-plus");
                if ($gp_view("commercial_users")) $gate_pass_submenu[] = array("name" => "gate_pass_commercial_users", "url" => "gate_pass_commercial_users", "class" => "dollar-sign");
                if ($gp_view("security_users")) $gate_pass_submenu[] = array("name" => "gate_pass_security_users", "url" => "gate_pass_security_users", "class" => "shield");
                if ($gp_view("rop_users")) $gate_pass_submenu[] = array("name" => "gate_pass_rop_users", "url" => "gate_pass_rop_users", "class" => "file-check");
                if ($gp_view("request_list")) $gate_pass_submenu[] = array("name" => "gate_pass_filter_requests", "url" => "gate_pass_request_list", "class" => "filter");
                if ($gp_view("fee_rules")) $gate_pass_submenu[] = array("name" => "gate_pass_fee_rules", "url" => "gate_pass_fee_rules", "class" => "dollar-sign");
            }
            $found_gitpass = false;
            foreach ($view_data["sidebar_menu"] as $k => $m) {
                if (get_array_value($m, "name") === "gitpass_master") {
                    $found_gitpass = true;
                    $submenu = get_array_value($m, "submenu");
                    if (!$submenu || !count($submenu)) {
                        $view_data["sidebar_menu"][$k]["submenu"] = $gate_pass_submenu;
                        $view_data["sidebar_menu"][$k]["class"]   = $default_gitpass_master ? get_array_value($default_gitpass_master, "class") : "key";
                    }
                    // Always use # so click toggles dropdown instead of navigating
                    $view_data["sidebar_menu"][$k]["url"] = "#";
                    break;
                }
            }
            if (!$found_gitpass && count($gate_pass_submenu)) {
                $view_data["sidebar_menu"][] = array(
                    "name" => "gitpass_master",
                    "class" => "key",
                    "url" => "#",
                    "submenu" => $gate_pass_submenu
                );
            }

            // ✅ Ensure Tender menu and submenu are visible (even if left menu is customized)
            $default_tender = $default_menu["tender"] ?? null;
            $tender_submenu = $default_tender ? (array) get_array_value($default_tender, "submenu") : array();
            if (!count($tender_submenu)) {
                $tender_perms = $this->ci->login_user->permissions;
                $tender_view = function ($key) use ($tender_perms) {
                    return $this->ci->login_user->is_admin || (bool) get_array_value($tender_perms, "can_view_tender_" . $key);
                };
            
                if ($tender_view("requests")) $tender_submenu[] = array("name" => "tender_requests", "url" => "tender_requests", "class" => "file-text");
                if ($tender_view("manager_inbox")) $tender_submenu[] = array("name" => "tender_department_manager_inbox", "url" => "tender_department_manager_inbox", "class" => "user-plus");
                if ($tender_view("finance_inbox")) $tender_submenu[] = array("name" => "tender_finance_inbox", "url" => "tender_finance_inbox", "class" => "dollar-sign");
                if ($tender_view("committee")) $tender_submenu[] = array("name" => "tender_committee_inbox", "url" => "tender_committee_inbox", "class" => "users");
                if ($tender_view("procurement")) $tender_submenu[] = array("name" => "tender_procurement_inbox", "url" => "tender_procurement_inbox", "class" => "briefcase");
                if ($tender_view("technical_eval")) $tender_submenu[] = array("name" => "tender_technical_inbox", "url" => "tender_technical_inbox", "class" => "tool");
                if ($tender_view("committee") && ($this->ci->login_user->is_admin || (bool) get_array_value($tender_perms, "can_tender_open_bids_3key"))) {
                    $tender_submenu[] = array("name" => "tender_committee_opening_inbox", "url" => "tender_committee_opening_inbox", "class" => "unlock");
                }
                if ($tender_view("commercial_eval")) $tender_submenu[] = array("name" => "tender_commercial_inbox", "url" => "tender_commercial_inbox", "class" => "bar-chart-2");
            }
            $found_tender = false;
            foreach ($view_data["sidebar_menu"] as $k => $m) {
                if (get_array_value($m, "name") === "tender") {
                    $found_tender = true;
                    $submenu = get_array_value($m, "submenu");
                    if (!$submenu || !count($submenu)) {
                        $view_data["sidebar_menu"][$k]["submenu"] = $tender_submenu;
                        $view_data["sidebar_menu"][$k]["class"] = $default_tender ? get_array_value($default_tender, "class") : "clipboard";
                    }
                    $view_data["sidebar_menu"][$k]["url"] = "#";
                    break;
                }
            }
            if (!$found_tender && count($tender_submenu)) {
                $view_data["sidebar_menu"][] = array(
                    "name" => "tender",
                    "class" => "clipboard",
                    "url" => "#",
                    "submenu" => $tender_submenu
                );
            }

            // ✅ Ensure Tender Master menu is visible for admin (even if left menu is customized)
            if ($this->ci->login_user->is_admin) {
                $default_tender_master = $default_menu["tender_master"] ?? null;
                $tender_master_submenu = $default_tender_master ? (array) get_array_value($default_tender_master, "submenu") : [];
                if (!count($tender_master_submenu)) {
                    $tender_master_submenu = [
                        ["name" => "tender_department_users",          "url" => "tender_department_users",          "class" => "user-plus"],
                        ["name" => "tender_department_manager_users",  "url" => "tender_department_manager_users",  "class" => "user-check"],
                        ["name" => "tender_finance_users",             "url" => "tender_finance_users",             "class" => "dollar-sign"],
                        ["name" => "tender_committee_users",           "url" => "tender_committee_users",           "class" => "users"],
                        ["name" => "tender_procurement_users",         "url" => "tender_procurement_users",         "class" => "briefcase"],
                        ["name" => "tender_technical_users",           "url" => "tender_technical_users",           "class" => "tool"],
                        ["name" => "tender_commercial_users",          "url" => "tender_commercial_users",          "class" => "bar-chart-2"],
                    ];
                }
                $found_tender_master = false;
                foreach ($view_data["sidebar_menu"] as $k => $m) {
                    if (get_array_value($m, "name") === "tender_master") {
                        $found_tender_master = true;
                        $submenu = get_array_value($m, "submenu");
                        if (!$submenu || !count($submenu)) {
                            $view_data["sidebar_menu"][$k]["submenu"] = $tender_master_submenu;
                            $view_data["sidebar_menu"][$k]["class"] = $default_tender_master ? get_array_value($default_tender_master, "class") : "layers";
                        }
                        $view_data["sidebar_menu"][$k]["url"] = "#";
                        break;
                    }
                }
                if (!$found_tender_master) {
                    $view_data["sidebar_menu"][] = [
                        "name" => "tender_master",
                        "class" => "layers",
                        "url" => "#",
                        "submenu" => $tender_master_submenu
                    ];
                }
            }

            // ✅ Ensure PTW Portal is visible for all staff users (even if left menu is customized)
            $ptw_portal_exists = false;
            foreach ($view_data["sidebar_menu"] as $m) {
                if (get_array_value($m, "name") === "ptw_portal") {
                    $ptw_portal_exists = true;
                    break;
                }
            }
            if (!$ptw_portal_exists) {
                $view_data["sidebar_menu"][] = array(
                    "name"  => "ptw_portal",
                    "url"   => "ptw_portal",
                    "class" => "shield"
                );
            }

            // ✅ Ensure Gate Pass Portal is visible for gate-pass linked users (even if left menu is customized)
            $default_gate_pass_portal = $default_menu["gate_pass_portal"] ?? null;
            if ($default_gate_pass_portal) {
                $exists = false;
                foreach ($view_data["sidebar_menu"] as $m) {
                    if (get_array_value($m, "name") === "gate_pass_portal") {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $view_data["sidebar_menu"][] = $default_gate_pass_portal;
                }
            }

            // ✅ Ensure Gate Pass Department Requests is visible (even if left menu is customized)
            $default_gate_pass_dept_requests = $default_menu["gate_pass_department_requests"] ?? null;
            if ($default_gate_pass_dept_requests) {
                $exists = false;
            
                foreach ($view_data["sidebar_menu"] as $m) {
                    if (get_array_value($m, "name") === "gate_pass_department_requests") {
                        $exists = true;
                        break;
                    }
                }
            
                if (!$exists) {
                    $view_data["sidebar_menu"][] = $default_gate_pass_dept_requests;
                }
            }

            // ✅ Ensure Commercial Requests is visible for commercial users (even if left menu is customized)
            $default_gate_pass_commercial_requests = $default_menu["gate_pass_commercial_requests"] ?? null;
            if ($default_gate_pass_commercial_requests) {
                $exists = false;
                foreach ($view_data["sidebar_menu"] as $m) {
                    if (get_array_value($m, "name") === "gate_pass_commercial_requests") {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $view_data["sidebar_menu"][] = $default_gate_pass_commercial_requests;
                }
            }

            // ✅ Ensure Security Requests is visible for security users (even if left menu is customized)
            $default_gate_pass_security_requests = $default_menu["gate_pass_security_requests"] ?? null;
            if ($default_gate_pass_security_requests) {
                $exists = false;
                foreach ($view_data["sidebar_menu"] as $m) {
                    if (get_array_value($m, "name") === "gate_pass_security_requests") {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $view_data["sidebar_menu"][] = $default_gate_pass_security_requests;
                }
            }

            // ✅ Ensure ROP Requests is visible for ROP users (even if left menu is customized)
            $default_gate_pass_rop_requests = $default_menu["gate_pass_rop_requests"] ?? null;
            if ($default_gate_pass_rop_requests) {
                $exists = false;
                foreach ($view_data["sidebar_menu"] as $m) {
                    if (get_array_value($m, "name") === "gate_pass_rop_requests") {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $view_data["sidebar_menu"][] = $default_gate_pass_rop_requests;
                }
            }

            // ✅ Ensure PTW Master is visible for admins and users with any PTW permission (even if left menu is customized)
            $default_ptw_master = $default_menu["ptw_master"] ?? null;
            if ($default_ptw_master) {
                $found_ptw_master = false;
                foreach ($view_data["sidebar_menu"] as $k => $m) {
                    if (get_array_value($m, "name") === "ptw_master") {
                        $found_ptw_master = true;
                        // Always replace submenu with the freshly permission-filtered default
                        // so that non-admins see only their allowed items and the dropdown works
                        $view_data["sidebar_menu"][$k]["submenu"] = get_array_value($default_ptw_master, "submenu");
                        $view_data["sidebar_menu"][$k]["class"]   = get_array_value($default_ptw_master, "class");
                        $view_data["sidebar_menu"][$k]["url"]     = "#";
                        break;
                    }
                }
                if (!$found_ptw_master) {
                    $view_data["sidebar_menu"][] = $default_ptw_master;
                }
            }

            // ✅ Ensure PTW HSSE Inbox is visible for HSSE users only (not admin)
            if (!$this->ci->login_user->is_admin) {
                $default_ptw_hsse_inbox = $default_menu["ptw_hsse_inbox"] ?? null;
                if ($default_ptw_hsse_inbox) {
                    $exists = false;
                    foreach ($view_data["sidebar_menu"] as $m) {
                        if (get_array_value($m, "name") === "ptw_hsse_inbox") {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $view_data["sidebar_menu"][] = $default_ptw_hsse_inbox;
                    }
                }
            }

            // ✅ Ensure PTW HMO Inbox is visible for HMO users only (not admin)
            if (!$this->ci->login_user->is_admin) {
                $default_ptw_hmo_inbox = $default_menu["ptw_hmo_inbox"] ?? null;
                if ($default_ptw_hmo_inbox) {
                    $exists = false;
                    foreach ($view_data["sidebar_menu"] as $m) {
                        if (get_array_value($m, "name") === "ptw_hmo_inbox") {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $view_data["sidebar_menu"][] = $default_ptw_hmo_inbox;
                    }
                }
            }

            // ✅ Ensure PTW Terminal Inbox is visible for Terminal users only (not admin)
            if (!$this->ci->login_user->is_admin) {
                $default_ptw_terminal_inbox = $default_menu["ptw_terminal_inbox"] ?? null;
                if ($default_ptw_terminal_inbox) {
                    $exists = false;
                    foreach ($view_data["sidebar_menu"] as $m) {
                        if (get_array_value($m, "name") === "ptw_terminal_inbox") {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $view_data["sidebar_menu"][] = $default_ptw_terminal_inbox;
                    }
                }
            }
        }


        // ✅ Force Vendor Portal menu for vendor-linked staff users (even if left menu is customized)
        if (!$is_preview && $this->ci->login_user->user_type === "staff") {
            $db = Database::connect();
            $vendor_users_table = $db->prefixTable("vendor_users");

            $default_menu = $this->_get_sidebar_menu_items($type);

            $my_vendor = $db->query(
                    "SELECT vendor_id
                    FROM $vendor_users_table
                    WHERE user_id=? AND deleted=0 AND status='active'
                    ORDER BY is_owner DESC, id DESC
                    LIMIT 1",
                            [$this->ci->login_user->id]
                        )->getRow();

            if ($my_vendor) {
                $exists = false;
                foreach ($view_data["sidebar_menu"] as $m) {
                    if (get_array_value($m, "name") === "vendor_portal") {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $view_data["sidebar_menu"][] = array(
                        "name" => "vendor_portal",
                        "url"  => "vendor_portal",
                        "class" => "briefcase"
                    );
                }
            }
        }

        // mark active after any injected menu items are added
        if (!$is_preview) {
            $view_data["sidebar_menu"] = $this->_get_active_menu($view_data["sidebar_menu"]);
        }

        return view("includes/left_menu", $view_data);
    }

    private function _get_item_array_value($data_array, $left_menu_items)
    {
        $name = get_array_value($data_array, "name");
        $language_key = get_array_value($data_array, "language_key");
        $url = get_array_value($data_array, "url");
        $icon = get_array_value($data_array, "icon");
        $open_in_new_tab = get_array_value($data_array, "open_in_new_tab");
        $item_value_array = array();

        if ($url) { //custom menu item
            $item_value_array = array("name" => $name, "language_key" => $language_key, "url" => $url, "is_custom_menu_item" => true, "class" => "$icon", "open_in_new_tab" => $open_in_new_tab);
        } else if (array_key_exists($name, $left_menu_items)) { //default menu items
            $item_value_array = get_array_value($left_menu_items, $name);
        }

        return $item_value_array;
    }

    //position items for plugins
    private function position_items_for_default_left_menu($sidebar_menu = array())
    {
        foreach ($sidebar_menu as $key => $menu) {
            $position = get_array_value($menu, "position");
            if ($position) {
                $position = $position - 1;
                $sidebar_menu = array_slice($sidebar_menu, 0, $position, true) +
                    array($key => $menu) +
                    array_slice($sidebar_menu, $position, NULL, true);
            }
        }

        return $sidebar_menu;
    }
}
