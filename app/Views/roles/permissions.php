<div class="tab-content">
    <?php echo form_open(get_uri("roles/save_permissions"), array("id" => "permissions-form", "class" => "general-form dashed-row", "role" => "form")); ?>
    <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />
    <div class="card">
        <div class="card-header">
            <h4><?php echo app_lang('permissions') . ": " . $model_info->title; ?></h4>
        </div>
        <div class="card-body">

            <ul class="permission-list">

            <li>
                    <span data-feather="settings" class="icon-14 ml-20"></span>
                    <h5>Master Data Permissions:</h5>

                <div class="row">

                <!-- Countries -->
                <div class="col-md-6 mb-3">
                    <div class="fw-semibold">Countries</div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_view_countries", "1", ($can_view_countries ?? false) ? true : false, "id='can_view_countries' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_countries">View</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_create_countries", "1", ($can_create_countries ?? false) ? true : false, "id='can_create_countries' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_countries">Create</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_update_countries", "1", ($can_update_countries ?? false) ? true : false, "id='can_update_countries' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_countries">Update</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_delete_countries", "1", ($can_delete_countries ?? false) ? true : false, "id='can_delete_countries' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_countries">Delete</label>
                    </div>
                </div>

                <!-- Regions -->
                <div class="col-md-6 mb-3">
                    <div class="fw-semibold">Regions</div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_view_regions", "1", ($can_view_regions ?? false) ? true : false, "id='can_view_regions' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_regions">View</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_create_regions", "1", ($can_create_regions ?? false) ? true : false, "id='can_create_regions' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_regions">Create</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_update_regions", "1", ($can_update_regions ?? false) ? true : false, "id='can_update_regions' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_regions">Update</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_delete_regions", "1", ($can_delete_regions ?? false) ? true : false, "id='can_delete_regions' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_regions">Delete</label>
                    </div>
                </div>

                <!-- Cities -->
                <div class="col-md-6 mb-3">
                    <div class="fw-semibold">Cities</div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_view_cities", "1", ($can_view_cities ?? false) ? true : false, "id='can_view_cities' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_cities">View</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_create_cities", "1", ($can_create_cities ?? false) ? true : false, "id='can_create_cities' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_cities">Create</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_update_cities", "1", ($can_update_cities ?? false) ? true : false, "id='can_update_cities' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_cities">Update</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_delete_cities", "1", ($can_delete_cities ?? false) ? true : false, "id='can_delete_cities' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_cities">Delete</label>
                    </div>
                </div>

                <!-- Legal Types -->
                <div class="col-md-6 mb-3">
                    <div class="fw-semibold">Legal Types</div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_view_legal_types", "1", ($can_view_legal_types ?? false) ? true : false, "id='can_view_legal_types' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_legal_types">View</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_create_legal_types", "1", ($can_create_legal_types ?? false) ? true : false, "id='can_create_legal_types' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_legal_types">Create</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_update_legal_types", "1", ($can_update_legal_types ?? false) ? true : false, "id='can_update_legal_types' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_legal_types">Update</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_delete_legal_types", "1", ($can_delete_legal_types ?? false) ? true : false, "id='can_delete_legal_types' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_legal_types">Delete</label>
                    </div>
                </div>

                <!-- Vendor Categories -->
                <div class="col-md-6 mb-3">
                    <div class="fw-semibold">Vendor Categories</div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_view_vendor_categories", "1", ($can_view_vendor_categories ?? false) ? true : false, "id='can_view_vendor_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_vendor_categories">View</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_create_vendor_categories", "1", ($can_create_vendor_categories ?? false) ? true : false, "id='can_create_vendor_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_vendor_categories">Create</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_update_vendor_categories", "1", ($can_update_vendor_categories ?? false) ? true : false, "id='can_update_vendor_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_vendor_categories">Update</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_delete_vendor_categories", "1", ($can_delete_vendor_categories ?? false) ? true : false, "id='can_delete_vendor_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_vendor_categories">Delete</label>
                    </div>
                </div>

                <!-- Vendor Sub Categories -->
                <div class="col-md-6 mb-3">
                    <div class="fw-semibold">Vendor Sub Categories</div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_view_vendor_sub_categories", "1", ($can_view_vendor_sub_categories ?? false) ? true : false, "id='can_view_vendor_sub_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_vendor_sub_categories">View</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_create_vendor_sub_categories", "1", ($can_create_vendor_sub_categories ?? false) ? true : false, "id='can_create_vendor_sub_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_vendor_sub_categories">Create</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_update_vendor_sub_categories", "1", ($can_update_vendor_sub_categories ?? false) ? true : false, "id='can_update_vendor_sub_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_vendor_sub_categories">Update</label>
                    </div>

                    <div class="form-check">
                    <?php echo form_checkbox("can_delete_vendor_sub_categories", "1", ($can_delete_vendor_sub_categories ?? false) ? true : false, "id='can_delete_vendor_sub_categories' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_vendor_sub_categories">Delete</label>
                    </div>
                </div>

                </div>

             </li>


             <li>
    <span data-feather="briefcase" class="icon-14 ml-20"></span>
    <h5>Tender Permissions:</h5>

    <div class="row">
        <?php
        $tender_sections = [
            "requests"        => "Tender Requests (Internal)",
            "manager_inbox"   => "Department Manager Inbox (Department Manager Review)",
            "finance_inbox"   => "Finance Inbox (Budget Verification)",
            "procurement"     => "Procurement (Tender Setup)",
            "committee"       => "Tender Committee / ITC",
            "technical_eval"  => "Technical Evaluation",
            "commercial_eval" => "Commercial Evaluation",
            "reports"         => "Tender Reports",
            "portal"          => "Tender Vendor Portal"
        ];

        foreach ($tender_sections as $key => $label) {
            $v = ${"can_view_tender_" . $key} ?? false;
            $c = ${"can_create_tender_" . $key} ?? false;
            $u = ${"can_update_tender_" . $key} ?? false;
            $d = ${"can_delete_tender_" . $key} ?? false;
        ?>
            <div class="col-md-6 mb-3">
                <div class="fw-semibold"><?php echo $label; ?></div>

                <div class="form-check">
                    <?php echo form_checkbox("can_view_tender_" . $key, "1", $v ? true : false, "id='can_view_tender_" . $key . "' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_view_tender_<?php echo $key; ?>">View</label>
                </div>

                <div class="form-check">
                    <?php echo form_checkbox("can_create_tender_" . $key, "1", $c ? true : false, "id='can_create_tender_" . $key . "' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_create_tender_<?php echo $key; ?>">Create</label>
                </div>

                <div class="form-check">
                    <?php echo form_checkbox("can_update_tender_" . $key, "1", $u ? true : false, "id='can_update_tender_" . $key . "' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_update_tender_<?php echo $key; ?>">Update</label>
                </div>

                <div class="form-check">
                    <?php echo form_checkbox("can_delete_tender_" . $key, "1", $d ? true : false, "id='can_delete_tender_" . $key . "' class='form-check-input'"); ?>
                    <label class="form-check-label" for="can_delete_tender_<?php echo $key; ?>">Delete</label>
                </div>
            </div>
        <?php } ?>
    </div>

    <hr>

    <div class="mb-2 fw-semibold">Tender Workflow Actions:</div>
    <div class="form-check">
        <?php echo form_checkbox("can_tender_open_bids_3key", "1", ($can_tender_open_bids_3key ?? false) ? true : false, "id='can_tender_open_bids_3key' class='form-check-input'"); ?>
        <label class="form-check-label" for="can_tender_open_bids_3key">
            Allow 3-Key Bid Opening (Committee)
        </label>
    </div>
</li>

                <li>
                    <span data-feather="settings" class="icon-14 ml-20"></span>
                    <h5>Vendors Master Permissions:</h5>

                    <div class="row">
                        <!-- Vendor Groups -->
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold">Vendor Groups</div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendor_groups", "1", ($can_view_vendor_groups ?? false) ? true : false, "id='can_view_vendor_groups' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendor_groups">View</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_create_vendor_groups", "1", ($can_create_vendor_groups ?? false) ? true : false, "id='can_create_vendor_groups' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_create_vendor_groups">Create</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_update_vendor_groups", "1", ($can_update_vendor_groups ?? false) ? true : false, "id='can_update_vendor_groups' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_update_vendor_groups">Update</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_delete_vendor_groups", "1", ($can_delete_vendor_groups ?? false) ? true : false, "id='can_delete_vendor_groups' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_delete_vendor_groups">Delete</label>
                            </div>
                        </div>

                        <!-- Vendor Group Fees -->
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold">Vendor Group Fees</div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendor_group_fees", "1", ($can_view_vendor_group_fees ?? false) ? true : false, "id='can_view_vendor_group_fees' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendor_group_fees">View</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_create_vendor_group_fees", "1", ($can_create_vendor_group_fees ?? false) ? true : false, "id='can_create_vendor_group_fees' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_create_vendor_group_fees">Create</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_update_vendor_group_fees", "1", ($can_update_vendor_group_fees ?? false) ? true : false, "id='can_update_vendor_group_fees' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_update_vendor_group_fees">Update</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_delete_vendor_group_fees", "1", ($can_delete_vendor_group_fees ?? false) ? true : false, "id='can_delete_vendor_group_fees' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_delete_vendor_group_fees">Delete</label>
                            </div>
                        </div>

                        <!-- Vendor Document Types -->
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold">Vendor Document Types</div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendor_document_types", "1", ($can_view_vendor_document_types ?? false) ? true : false, "id='can_view_vendor_document_types' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendor_document_types">View</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_create_vendor_document_types", "1", ($can_create_vendor_document_types ?? false) ? true : false, "id='can_create_vendor_document_types' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_create_vendor_document_types">Create</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_update_vendor_document_types", "1", ($can_update_vendor_document_types ?? false) ? true : false, "id='can_update_vendor_document_types' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_update_vendor_document_types">Update</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_delete_vendor_document_types", "1", ($can_delete_vendor_document_types ?? false) ? true : false, "id='can_delete_vendor_document_types' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_delete_vendor_document_types">Delete</label>
                            </div>
                        </div>

                        <!-- Vendors -->
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold">Vendors</div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendors", "1", ($can_view_vendors ?? false) ? true : false, "id='can_view_vendors' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendors">View</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_create_vendors", "1", ($can_create_vendors ?? false) ? true : false, "id='can_create_vendors' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_create_vendors">Create</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_update_vendors", "1", ($can_update_vendors ?? false) ? true : false, "id='can_update_vendors' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_update_vendors">Update</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_delete_vendors", "1", ($can_delete_vendors ?? false) ? true : false, "id='can_delete_vendors' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_delete_vendors">Delete</label>
                            </div>
                        </div>

                        <!-- Vendor Specialties -->
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold">Vendor Specialties</div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendor_specialties", "1", ($can_view_vendor_specialties ?? false) ? true : false, "id='can_view_vendor_specialties' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendor_specialties">View</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_filter_vendor_specialties", "1", ($can_filter_vendor_specialties ?? false) ? true : false, "id='can_filter_vendor_specialties' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_filter_vendor_specialties">Filter (Specialties Filter)</label>
                            </div>
                        </div>

                        <!-- Vendor Update Requests -->
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold">Vendor Update Requests</div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendor_update_requests", "1", ($can_view_vendor_update_requests ?? false) ? true : false, "id='can_view_vendor_update_requests' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendor_update_requests">View</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_view_vendor_update_requests_by_vendor", "1", ($can_view_vendor_update_requests_by_vendor ?? false) ? true : false, "id='can_view_vendor_update_requests_by_vendor' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_vendor_update_requests_by_vendor">Group by Vendor</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_review_vendor_update_requests", "1", ($can_review_vendor_update_requests ?? false) ? true : false, "id='can_review_vendor_update_requests' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_review_vendor_update_requests">Review</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_approve_vendor_update_requests", "1", ($can_approve_vendor_update_requests ?? false) ? true : false, "id='can_approve_vendor_update_requests' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_approve_vendor_update_requests">Approve</label>
                            </div>

                            <div class="form-check">
                                <?php echo form_checkbox("can_reject_vendor_update_requests", "1", ($can_reject_vendor_update_requests ?? false) ? true : false, "id='can_reject_vendor_update_requests' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_reject_vendor_update_requests">Reject</label>
                            </div>
                        </div>
                    </div>

                </li>

                <li>
                    <span data-feather="key" class="icon-14 ml-20"></span>
                    <h5>Gate Pass Master Permissions:</h5>
                    <div class="row">
                        <?php
                        $gate_pass_sections = array(
                            'companies' => 'Companies',
                            'departments' => 'Departments',
                            'visitors' => 'Visitors',
                            'purposes' => 'Purposes',
                            'reasons' => 'Reasons',
                            'department_users' => 'Department Users',
                            'commercial_users' => 'Commercial Users',
                            'security_users' => 'Security Users',
                            'rop_users' => 'ROP Users',
                            'request_list' => 'Filter Requests',
                            'fee_rules' => 'Fee Rules',
                        );
                        foreach ($gate_pass_sections as $key => $label) {
                            $v = isset(${'can_view_gate_pass_'.$key}) ? ${'can_view_gate_pass_'.$key} : false;
                            $c = isset(${'can_create_gate_pass_'.$key}) ? ${'can_create_gate_pass_'.$key} : false;
                            $u = isset(${'can_update_gate_pass_'.$key}) ? ${'can_update_gate_pass_'.$key} : false;
                            $d = isset(${'can_delete_gate_pass_'.$key}) ? ${'can_delete_gate_pass_'.$key} : false;
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold"><?php echo $label; ?></div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_view_gate_pass_" . $key, "1", $v ? true : false, "id='can_view_gate_pass_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_gate_pass_<?php echo $key; ?>">View</label>
                            </div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_create_gate_pass_" . $key, "1", $c ? true : false, "id='can_create_gate_pass_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_create_gate_pass_<?php echo $key; ?>">Create</label>
                            </div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_update_gate_pass_" . $key, "1", $u ? true : false, "id='can_update_gate_pass_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_update_gate_pass_<?php echo $key; ?>">Update</label>
                            </div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_delete_gate_pass_" . $key, "1", $d ? true : false, "id='can_delete_gate_pass_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_delete_gate_pass_<?php echo $key; ?>">Delete</label>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </li>

                <li>
                    <span data-feather="tool" class="icon-14 ml-20"></span>
                    <h5>PTW Master Permissions:</h5>
                    <div class="row">
                        <?php
                        $ptw_sections = array(
                            'hsse_users'       => 'HSSE Users',
                            'hmo_users'        => 'HMO Users',
                            'terminal_users'   => 'Terminal Users',
                            'request_list'     => 'Request List',
                            'hazard_documents' => 'Hazard Documents',
                            'ppe'              => 'PPE',
                            'preparation'      => 'Work Area Preparation',
                            'reasons'          => 'Reasons',
                        );
                        foreach ($ptw_sections as $key => $label) {
                            $v = isset(${'can_view_ptw_'.$key})   ? ${'can_view_ptw_'.$key}   : false;
                            $c = isset(${'can_create_ptw_'.$key}) ? ${'can_create_ptw_'.$key} : false;
                            $u = isset(${'can_update_ptw_'.$key}) ? ${'can_update_ptw_'.$key} : false;
                            $d = isset(${'can_delete_ptw_'.$key}) ? ${'can_delete_ptw_'.$key} : false;
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold"><?php echo $label; ?></div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_view_ptw_" . $key, "1", $v ? true : false, "id='can_view_ptw_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_view_ptw_<?php echo $key; ?>">View</label>
                            </div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_create_ptw_" . $key, "1", $c ? true : false, "id='can_create_ptw_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_create_ptw_<?php echo $key; ?>">Create</label>
                            </div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_update_ptw_" . $key, "1", $u ? true : false, "id='can_update_ptw_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_update_ptw_<?php echo $key; ?>">Update</label>
                            </div>
                            <div class="form-check">
                                <?php echo form_checkbox("can_delete_ptw_" . $key, "1", $d ? true : false, "id='can_delete_ptw_" . $key . "' class='form-check-input'"); ?>
                                <label class="form-check-label" for="can_delete_ptw_<?php echo $key; ?>">Delete</label>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </li>

                <li>
                    <span data-feather="key" class="icon-14 ml-20"></span>
                    <h5><?php echo app_lang("set_project_permissions"); ?>:</h5>
                    <div>
                        <?php
                        echo form_checkbox("do_not_show_projects", "1", $do_not_show_projects ? true : false, "id='do_not_show_projects' class='manage_project_section form-check-input'");
                        ?>
                        <label for="do_not_show_projects"><?php echo app_lang("do_not_show_projects"); ?></label>
                    </div>

                    <div id="project_permission_details_area" class="form-group <?php echo $do_not_show_projects ? "hide" : ""; ?>">
                        <div>
                            <?php
                            echo form_checkbox("can_manage_all_projects", "1", $can_manage_all_projects ? true : false, "id='can_manage_all_projects' class='manage_project_section form-check-input'");
                            ?>
                            <label for="can_manage_all_projects"><?php echo app_lang("can_manage_all_projects"); ?></label>
                        </div>
                        <div id="individual_project_permission_area" class="<?php echo $can_manage_all_projects ? "hide" : ""; ?>">
                            <div>
                                <?php
                                echo form_checkbox("can_create_projects", "1", $can_create_projects ? true : false, "id='can_create_projects' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_create_projects"><?php echo app_lang("can_create_projects"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_edit_projects", "1", $can_edit_projects ? true : false, "id='can_edit_projects' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_edit_projects"><?php echo app_lang("can_edit_projects"); ?></label>
                            </div>
                            <div id="can_edit_only_own_created_projects_section" class="<?php echo $can_edit_projects ? "hide" : ""; ?>">
                                <?php
                                echo form_checkbox("can_edit_only_own_created_projects", "1", $can_edit_only_own_created_projects ? true : false, "id='can_edit_only_own_created_projects' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_edit_only_own_created_projects"><?php echo app_lang("can_edit_only_own_created_projects"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_delete_projects", "1", $can_delete_projects ? true : false, "id='can_delete_projects' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_delete_projects"><?php echo app_lang("can_delete_projects"); ?></label>
                            </div>
                            <div id="can_delete_only_own_created_projects_section" class="<?php echo $can_delete_projects ? "hide" : ""; ?>">
                                <?php
                                echo form_checkbox("can_delete_only_own_created_projects", "1", $can_delete_only_own_created_projects ? true : false, "id='can_delete_only_own_created_projects' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_delete_only_own_created_projects"><?php echo app_lang("can_delete_only_own_created_projects"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_add_remove_project_members", "1", $can_add_remove_project_members ? true : false, "id='can_add_remove_project_members' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_add_remove_project_members"><?php echo app_lang("can_add_remove_project_members"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_create_tasks", "1", $can_create_tasks ? true : false, "id='can_create_tasks' class='manage_project_section form-check-input'");
                                ?>
                                <label for="can_create_tasks"><?php echo app_lang("can_create_tasks"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_edit_tasks", "1", $can_edit_tasks ? true : false, "id='can_edit_tasks' class='form-check-input'");
                                ?>
                                <label for="can_edit_tasks"><?php echo app_lang("can_edit_tasks"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_delete_tasks", "1", $can_delete_tasks ? true : false, "id='can_delete_tasks' class='form-check-input'");
                                ?>
                                <label for="can_delete_tasks"><?php echo app_lang("can_delete_tasks"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_comment_on_tasks", "1", $can_comment_on_tasks ? true : false, "id='can_comment_on_tasks' class='form-check-input'");
                                ?>
                                <label for="can_comment_on_tasks"><?php echo app_lang("can_comment_on_tasks"); ?></label>
                            </div>
                            <div id="show_assigned_tasks_only_section">
                                <?php
                                echo form_checkbox("show_assigned_tasks_only", "1", $show_assigned_tasks_only ? true : false, "id='show_assigned_tasks_only' class='form-check-input'");
                                ?>
                                <label for="show_assigned_tasks_only"><?php echo app_lang("show_assigned_tasks_only"); ?></label>
                            </div>
                            <div id="can_update_only_assigned_tasks_status_section">
                                <?php
                                echo form_checkbox("can_update_only_assigned_tasks_status", "1", $can_update_only_assigned_tasks_status ? true : false, "id='can_update_only_assigned_tasks_status' class='form-check-input'");
                                ?>
                                <label for="can_update_only_assigned_tasks_status"><?php echo app_lang("can_update_only_assigned_tasks_status"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_create_milestones", "1", $can_create_milestones ? true : false, "id='can_create_milestones' class='form-check-input'");
                                ?>
                                <label for="can_create_milestones"><?php echo app_lang("can_create_milestones"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_edit_milestones", "1", $can_edit_milestones ? true : false, "id='can_edit_milestones' class='form-check-input'");
                                ?>
                                <label for="can_edit_milestones"><?php echo app_lang("can_edit_milestones"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_delete_milestones", "1", $can_delete_milestones ? true : false, "id='can_delete_milestones' class='form-check-input'");
                                ?>
                                <label for="can_delete_milestones"><?php echo app_lang("can_delete_milestones"); ?></label>
                            </div>

                            <div>
                                <?php
                                echo form_checkbox("can_upload_and_edit_files", "1", $can_upload_and_edit_files ? true : false, "id='can_upload_and_edit_files' class='form-check-input'");
                                ?>
                                <label for="can_upload_and_edit_files"><?php echo app_lang("can_upload_and_edit_files"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_view_files", "1", $can_view_files ? true : false, "id='can_view_files' class='form-check-input'");
                                ?>
                                <label for="can_view_files"><?php echo app_lang("can_view_files"); ?></label>
                            </div>
                            <div>
                                <?php
                                echo form_checkbox("can_delete_files", "1", $can_delete_files ? true : false, "id='can_delete_files' class='form-check-input'");
                                ?>
                                <label for="can_delete_files"><?php echo app_lang("can_delete_files"); ?></label>
                            </div>

                            <div>
                                <?php
                                echo form_checkbox("client_feedback_access_permission", "all", $client_feedback_access_permission == "all" ? true : false, "id='client_feedback_access_permission' class='form-check-input'");
                                ?>
                                <label for="client_feedback_access_permission"><?php echo app_lang("can_access_client_feedback_in_projects"); ?></label>
                            </div>

                            <div>
                                <?php
                                echo form_checkbox("can_comment_on_projects", "all", $can_comment_on_projects == "all" ? true : false, "id='can_comment_on_projects' class='form-check-input'");
                                ?>
                                <label for="can_comment_on_projects"><?php echo app_lang("can_comment_on_projects"); ?></label>
                            </div>
                        </div>

                    </div>

                </li>

                <?php if ($login_user->is_admin) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("administration_permissions"); ?>:</h5>
                        <div>
                            <?php
                            echo form_checkbox("can_manage_all_kinds_of_settings", "1", $can_manage_all_kinds_of_settings ? true : false, "id='can_manage_all_kinds_of_settings' class='form-check-input'");
                            ?>
                            <label for="can_manage_all_kinds_of_settings"><?php echo app_lang("can_manage_all_kinds_of_settings"); ?></label>
                        </div>
                        <div id="can_manage_user_role_and_permissions_container" class="<?php echo $can_manage_all_kinds_of_settings ? "" : "hide"; ?>">
                            <?php
                            echo form_checkbox("can_manage_user_role_and_permissions", "1", $can_manage_user_role_and_permissions ? true : false, "id='can_manage_user_role_and_permissions' class='form-check-input'");
                            ?>
                            <label for="can_manage_user_role_and_permissions"><?php echo app_lang("can_manage_user_role_and_permissions"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_checkbox("can_add_or_invite_new_team_members", "1", $can_add_or_invite_new_team_members ? true : false, "id='can_add_or_invite_new_team_members' class='form-check-input'");
                            ?>
                            <label for="can_add_or_invite_new_team_members"><?php echo app_lang("can_add_or_invite_new_team_members"); ?></label>
                        </div>

                        <div>
                            <?php
                            echo form_checkbox("can_activate_deactivate_team_members", "1", $can_activate_deactivate_team_members ? true : false, "id='can_activate_deactivate_team_members' class='form-check-input'");
                            ?>
                            <label for="can_activate_deactivate_team_members"><?php echo app_lang("can_activate_deactivate_team_members"); ?></label>
                        </div>

                        <div>
                            <?php
                            echo form_checkbox("can_delete_team_members", "1", $can_delete_team_members ? true : false, "id='can_delete_team_members' class='form-check-input'");
                            ?>
                            <label for="can_delete_team_members"><?php echo app_lang("can_delete_team_members"); ?></label>
                        </div>

                    </li>
                <?php } ?>

                <?php if (get_setting("module_event")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("set_event_permissions"); ?>:</h5>
                        <div>
                            <?php
                            echo form_checkbox("disable_event_sharing", "1", $disable_event_sharing ? true : false, "id='disable_event_sharing' class='form-check-input'");
                            ?>
                            <label for="disable_event_sharing"><?php echo app_lang("disable_event_sharing"); ?></label>
                        </div>
                    </li>
                <?php } ?>

                <li>
                    <span data-feather="key" class="icon-14 ml-20"></span>
                    <h5><?php echo app_lang("set_team_members_permission"); ?>:</h5>

                    <div>
                        <?php
                        echo form_checkbox("hide_team_members_list", "1", $hide_team_members_list ? true : false, "id='hide_team_members_list' class='form-check-input'");
                        ?>
                        <label for="hide_team_members_list"><?php echo app_lang("hide_team_members_list"); ?></label>
                    </div>

                    <div>
                        <?php
                        echo form_checkbox("hide_team_members_list_from_dropdowns", "1", $hide_team_members_list_from_dropdowns ? true : false, "id='hide_team_members_list_from_dropdowns' class='form-check-input'");
                        ?>
                        <label for="hide_team_members_list_from_dropdowns"><?php echo app_lang("hide_team_members_list_from_dropdowns"); ?></label>
                    </div>

                    <div id="individual_team_members_permission_area" class="<?php echo $hide_team_members_list ? "hide" : ""; ?>">
                        <div>
                            <?php
                            echo form_checkbox("can_view_team_members_contact_info", "1", $can_view_team_members_contact_info ? true : false, "id='can_view_team_members_contact_info' class='form-check-input'");
                            ?>
                            <label for="can_view_team_members_contact_info"><?php echo app_lang("can_view_team_members_contact_info"); ?></label>
                        </div>

                        <div>
                            <?php
                            echo form_checkbox("can_view_team_members_social_links", "1", $can_view_team_members_social_links ? true : false, "id='can_view_team_members_social_links' class='form-check-input'");
                            ?>
                            <label for="can_view_team_members_social_links"><?php echo app_lang("can_view_team_members_social_links"); ?></label>
                        </div>

                        <div>
                            <label for="can_update_team_members_general_info_and_social_links"><?php echo app_lang("can_update_team_members_general_info_and_social_links"); ?></label>
                            <div class="ml15">
                                <div>
                                    <?php
                                    if (is_null($team_member_update_permission)) {
                                        $team_member_update_permission = "";
                                    }
                                    echo form_radio(array(
                                        "id" => "team_member_update_permission_no",
                                        "name" => "team_member_update_permission",
                                        "value" => "",
                                        "class" => "team_member_update_permission toggle_specific form-check-input",
                                    ), $team_member_update_permission, ($team_member_update_permission === "") ? true : false);
                                    ?>
                                    <label for="team_member_update_permission_no"><?php echo app_lang("no"); ?></label>
                                </div>
                                <div>
                                    <?php
                                    echo form_radio(array(
                                        "id" => "team_member_update_permission_all",
                                        "name" => "team_member_update_permission",
                                        "value" => "all",
                                        "class" => "team_member_update_permission toggle_specific form-check-input",
                                    ), $team_member_update_permission, ($team_member_update_permission === "all") ? true : false);
                                    ?>
                                    <label for="team_member_update_permission_all"><?php echo app_lang("yes_all_members"); ?></label>
                                </div>
                                <div class="form-group">
                                    <?php
                                    echo form_radio(array(
                                        "id" => "team_member_update_permission_specific",
                                        "name" => "team_member_update_permission",
                                        "value" => "specific",
                                        "class" => "team_member_update_permission toggle_specific form-check-input",
                                    ), $team_member_update_permission, ($team_member_update_permission === "specific") ? true : false);
                                    ?>
                                    <label for="team_member_update_permission_specific"><?php echo app_lang("yes_specific_members_or_teams"); ?>:</label>
                                    <div class="specific_dropdown">
                                        <input type="text" value="<?php echo $team_member_update_permission_specific; ?>" name="team_member_update_permission_specific" id="team_member_update_permission_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php
                            echo form_checkbox("team_members_note_manage_permission", "all", $team_members_note_manage_permission == "all" ? true : false, "id='team_members_note_manage_permission' class='form-check-input'");
                            ?>
                            <label for="team_members_note_manage_permission"><?php echo app_lang("can_manage_team_members_notes"); ?></label>
                        </div>
                    </div>

                </li>
                <?php if (get_setting("module_message")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("set_message_permissions"); ?>:</h5>
                        <div>
                            <?php
                            echo form_checkbox("message_permission_no", "1", ($message_permission == "no") ? true : false, "id='message_permission_no' class='form-check-input'");
                            ?>
                            <label for="message_permission_no"><?php echo app_lang("cant_send_any_messages"); ?></label>
                        </div>
                        <div id="message_permission_specific_area" class="form-group <?php echo ($message_permission == "no") ? "hide" : ""; ?>">
                            <?php
                            echo form_checkbox("message_permission_specific_checkbox", "1", ($message_permission == "specific") ? true : false, "id='message_permission_specific_checkbox' class='message_permission_specific toggle_specific form-check-input'");
                            ?>
                            <label for="message_permission_specific_checkbox"><?php echo app_lang("can_send_messages_to_specific_members_or_teams"); ?></label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $message_permission_specific; ?>" name="message_permission_specific" id="message_permission_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                            </div>
                        </div>
                    </li>
                <?php } ?>


                <li>
                    <span data-feather="key" class="icon-14 ml-20"></span>
                    <h5><?php echo app_lang("can_manage_team_members_job_information"); ?></h5>
                    <div>
                        <?php
                        if (is_null($job_info_manage_permission)) {
                            $job_info_manage_permission = "";
                        }
                        echo form_radio(array(
                            "id" => "job_info_manage_permission_no",
                            "name" => "job_info_manage_permission",
                            "value" => "",
                            "class" => "form-check-input",
                        ), $job_info_manage_permission, ($job_info_manage_permission === "") ? true : false);
                        ?>
                        <label for="job_info_manage_permission_no"><?php echo app_lang("no"); ?> </label>
                    </div>
                    <div>
                        <?php
                        echo form_radio(array(
                            "id" => "job_info_manage_permission_yes",
                            "name" => "job_info_manage_permission",
                            "value" => "all",
                            "class" => "form-check-input",
                        ), $job_info_manage_permission, ($job_info_manage_permission === "all") ? true : false);
                        ?>
                        <label for="job_info_manage_permission_yes"><?php echo app_lang("yes"); ?></label>
                    </div>
                </li>

                <?php if (get_setting("module_leave")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_manage_team_members_leave"); ?> <span class="help" data-bs-toggle="tooltip" title="Assign, approve or reject leave applications"><span data-feather="help-circle" class="icon-14"></span></span> </h5>
                        <div>
                            <?php
                            if (is_null($leave)) {
                                $leave = "";
                            }
                            echo form_radio(array(
                                "id" => "leave_permission_no",
                                "name" => "leave_permission",
                                "value" => "",
                                "class" => "leave_permission toggle_specific form-check-input",
                            ), $leave, ($leave === "") ? true : false);
                            ?>
                            <label for="leave_permission_no"><?php echo app_lang("no"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "leave_permission_all",
                                "name" => "leave_permission",
                                "value" => "all",
                                "class" => "leave_permission toggle_specific form-check-input",
                            ), $leave, ($leave === "all") ? true : false);
                            ?>
                            <label for="leave_permission_all"><?php echo app_lang("yes_all_members"); ?></label>
                        </div>
                        <div class="form-group pb0 mb0 no-border">
                            <?php
                            echo form_radio(array(
                                "id" => "leave_permission_specific",
                                "name" => "leave_permission",
                                "value" => "specific",
                                "class" => "leave_permission toggle_specific form-check-input",
                            ), $leave, ($leave === "specific") ? true : false);
                            ?>
                            <label for="leave_permission_specific"><?php echo app_lang("yes_specific_members_or_teams") . " (" . app_lang("excluding_his_her_leaves") . ")"; ?>:</label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $leave_specific; ?>" name="leave_permission_specific" id="leave_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                            </div>

                        </div>
                        <div class="form-group">
                            <div>
                                <?php
                                echo form_checkbox("can_delete_leave_application", "1", $can_delete_leave_application ? true : false, "id='can_delete_leave_application' class='form-check-input'");
                                ?>
                                <label for="can_delete_leave_application"><?php echo app_lang("can_delete_leave_application"); ?> <span class="help" data-bs-toggle="tooltip" title="Can delete based on his/her access permission"><i data-feather="help-circle" class="icon-14"></i></span></label>
                            </div>
                        </div>
                    </li>
                <?php } ?>
                <?php if (get_setting("module_attendance")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_manage_team_members_timecards"); ?> <span class="help" data-bs-toggle="tooltip" title="Add, edit and delete time cards"><i data-feather="help-circle" class="icon-14"></i></span></h5>
                        <div>
                            <?php
                            if (is_null($attendance)) {
                                $attendance = "";
                            }
                            echo form_radio(array(
                                "id" => "attendance_permission_no",
                                "name" => "attendance_permission",
                                "value" => "",
                                "class" => "attendance_permission toggle_specific form-check-input",
                            ), $attendance, ($attendance === "") ? true : false);
                            ?>
                            <label for="attendance_permission_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "attendance_permission_all",
                                "name" => "attendance_permission",
                                "value" => "all",
                                "class" => "attendance_permission toggle_specific form-check-input",
                            ), $attendance, ($attendance === "all") ? true : false);
                            ?>
                            <label for="attendance_permission_all"><?php echo app_lang("yes_all_members"); ?></label>
                        </div>
                        <div class="form-group">
                            <?php
                            echo form_radio(array(
                                "id" => "attendance_permission_specific",
                                "name" => "attendance_permission",
                                "value" => "specific",
                                "class" => "attendance_permission toggle_specific form-check-input",
                            ), $attendance, ($attendance === "specific") ? true : false);
                            ?>
                            <label for="attendance_permission_specific"><?php echo app_lang("yes_specific_members_or_teams") . " (" . app_lang("excluding_his_her_time_cards") . ")"; ?>:</label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $attendance_specific; ?>" name="attendance_permission_specific" id="attendance_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                            </div>
                        </div>

                    </li>
                <?php } ?>
                <?php if (get_setting("module_project_timesheet")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_manage_team_members_project_timesheet"); ?></h5>
                        <div>
                            <?php
                            if (is_null($timesheet_manage_permission)) {
                                $timesheet_manage_permission = "";
                            }
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_no",
                                "name" => "timesheet_manage_permission",
                                "value" => "",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_no"><?php echo app_lang("no") . " (" . app_lang("can_add_own_timelogs_only") . ")"; ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_own",
                                "name" => "timesheet_manage_permission",
                                "value" => "own",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "own") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_own"><?php echo app_lang("yes_only_own_timelogs"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_all",
                                "name" => "timesheet_manage_permission",
                                "value" => "all",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "all") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_all"><?php echo app_lang("yes_all_members"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_own_project_members",
                                "name" => "timesheet_manage_permission",
                                "value" => "own_project_members",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "own_project_members") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_own_project_members"><?php echo app_lang("yes_only_own_project_members"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_own_project_members_excluding_own",
                                "name" => "timesheet_manage_permission",
                                "value" => "own_project_members_excluding_own",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "own_project_members_excluding_own") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_own_project_members_excluding_own"><?php echo app_lang("yes_only_own_project_members") . " (" . app_lang("excluding_his_her_timelogs") . ")"; ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_specific",
                                "name" => "timesheet_manage_permission",
                                "value" => "specific",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "specific") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_specific"><?php echo app_lang("yes_specific_members_or_teams"); ?>:</label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $timesheet_manage_permission_specific; ?>" name="timesheet_manage_permission_specific" id="timesheet_manage_permission_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                            </div>
                        </div>
                        <div class="form-group">
                            <?php
                            echo form_radio(array(
                                "id" => "timesheet_manage_permission_specific_excluding_own",
                                "name" => "timesheet_manage_permission",
                                "value" => "specific_excluding_own",
                                "class" => "timesheet_manage_permission toggle_specific form-check-input",
                            ), $timesheet_manage_permission, ($timesheet_manage_permission === "specific_excluding_own") ? true : false);
                            ?>
                            <label for="timesheet_manage_permission_specific_excluding_own"><?php echo app_lang("yes_specific_members_or_teams") . " (" . app_lang("excluding_his_her_timelogs") . ")"; ?>:</label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $timesheet_manage_permission_specific; ?>" name="timesheet_manage_permission_specific_excluding_own" id="timesheet_manage_permission_specific_excluding_own_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                            </div>
                        </div>
                    </li>
                <?php } ?>

                <?php if (get_setting("module_timeline")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("set_timeline_permissions"); ?>:</h5>
                        <div>
                            <?php
                            echo form_checkbox("timeline_permission_no", "1", ($timeline_permission == "no") ? true : false, "id='timeline_permission_no' class='form-check-input'");
                            ?>
                            <label for="timeline_permission_no"><?php echo app_lang("cant_see_the_timeline"); ?></label>
                        </div>
                        <div id="timeline_permission_specific_area" class="form-group <?php echo ($timeline_permission == "no") ? "hide" : ""; ?>">
                            <?php
                            echo form_checkbox("timeline_permission_specific_checkbox", "1", ($timeline_permission == "specific") ? true : false, "id='timeline_permission_specific_checkbox' class='timeline_permission_specific toggle_specific form-check-input'");
                            ?>
                            <label for="timeline_permission_specific_checkbox"><?php echo app_lang("can_see_timeline_posts_from_specific_members_or_teams"); ?></label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $timeline_permission_specific; ?>" name="timeline_permission_specific" id="timeline_permission_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_members_and_or_teams'); ?>" />
                            </div>
                        </div>
                    </li>
                <?php } ?>

                <?php if (get_setting("module_lead")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_leads_information"); ?></h5>
                        <div>
                            <?php
                            if (is_null($lead)) {
                                $lead = "";
                            }
                            echo form_radio(array(
                                "id" => "lead_no",
                                "name" => "lead_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $lead, ($lead === "") ? true : false);
                            ?>
                            <label for="lead_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "lead_yes",
                                "name" => "lead_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $lead, ($lead === "all") ? true : false);
                            ?>
                            <label for="lead_yes"><?php echo app_lang("yes_all_leads"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "lead_yes_own",
                                "name" => "lead_permission",
                                "value" => "own",
                                "class" => "form-check-input",
                            ), $lead, ($lead === "own") ? true : false);
                            ?>
                            <label for="lead_yes_own"><?php echo app_lang("yes_only_own_leads"); ?></label>
                        </div>
                    </li>
                <?php } ?>

                <li>
                    <span data-feather="key" class="icon-14 ml-20"></span>
                    <h5><?php echo app_lang("can_access_clients_information"); ?> <span class="help" data-bs-toggle="tooltip" title="Hides all information of clients except company name."><i data-feather="help-circle" class="icon-14"></i></span></h5>
                    <div>
                        <?php
                        if (is_null($client)) {
                            $client = "";
                        }
                        echo form_radio(array(
                            "id" => "client_no",
                            "name" => "client_permission",
                            "value" => "",
                            "class" => "client_permission toggle_specific form-check-input",
                        ), $client, ($client === "") ? true : false);
                        ?>
                        <label for="client_no"><?php echo app_lang("no"); ?> </label>
                    </div>
                    <div>
                        <?php
                        echo form_radio(array(
                            "id" => "client_yes",
                            "name" => "client_permission",
                            "value" => "all",
                            "class" => "client_permission toggle_specific form-check-input",
                        ), $client, ($client === "all") ? true : false);
                        ?>
                        <label for="client_yes"><?php echo app_lang("yes_all_clients"); ?></label>
                    </div>
                    <div>
                        <?php
                        echo form_radio(array(
                            "id" => "client_yes_own",
                            "name" => "client_permission",
                            "value" => "own",
                            "class" => "client_permission toggle_specific form-check-input",
                        ), $client, ($client === "own") ? true : false);
                        ?>
                        <label for="client_yes_own"><?php echo app_lang("yes_only_own_clients"); ?></label>
                    </div>
                    <div>
                        <?php
                        echo form_radio(array(
                            "id" => "client_read_only",
                            "name" => "client_permission",
                            "value" => "read_only",
                            "class" => "client_permission toggle_specific form-check-input",
                        ), $client, ($client === "read_only") ? true : false);
                        ?>
                        <label for="client_read_only"><?php echo app_lang("read_only"); ?></label>
                    </div>
                    <div class="form-group">
                        <?php
                        echo form_radio(array(
                            "id" => "client_specific",
                            "name" => "client_permission",
                            "value" => "specific",
                            "class" => "client_permission toggle_specific form-check-input",
                        ), $client, ($client === "specific") ? true : false);
                        ?>
                        <label for="client_specific"><?php echo app_lang("yes_specific_client_groups"); ?>:</label>
                        <div class="specific_dropdown">
                            <input type="text" value="<?php echo $client_specific; ?>" name="client_permission_specific" id="client_groups_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_client_groups'); ?>" />
                        </div>
                    </div>
                </li>

                <?php if (get_setting("module_ticket")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_tickets"); ?></h5>
                        <div>
                            <?php
                            if (is_null($ticket)) {
                                $ticket = "";
                            }
                            echo form_radio(array(
                                "id" => "ticket_permission_no",
                                "name" => "ticket_permission",
                                "value" => "",
                                "class" => "ticket_permission toggle_specific form-check-input",
                            ), $ticket, ($ticket === "") ? true : false);
                            ?>
                            <label for="ticket_permission_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "ticket_permission_all",
                                "name" => "ticket_permission",
                                "value" => "all",
                                "class" => "ticket_permission toggle_specific form-check-input",
                            ), $ticket, ($ticket === "all") ? true : false);
                            ?>
                            <label for="ticket_permission_all"><?php echo app_lang("yes_all_tickets"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "ticket_permission_assigned_only",
                                "name" => "ticket_permission",
                                "value" => "assigned_only",
                                "class" => "ticket_permission toggle_specific form-check-input",
                            ), $ticket, ($ticket === "assigned_only") ? true : false);
                            ?>
                            <label for="ticket_permission_assigned_only"><?php echo app_lang("yes_assigned_tickets_only"); ?></label>
                        </div>
                        <div class="form-group">
                            <?php
                            echo form_radio(array(
                                "id" => "ticket_permission_specific",
                                "name" => "ticket_permission",
                                "value" => "specific",
                                "class" => "ticket_permission toggle_specific form-check-input",
                            ), $ticket, ($ticket === "specific") ? true : false);
                            ?>
                            <label for="ticket_permission_specific"><?php echo app_lang("yes_specific_ticket_types"); ?>:</label>
                            <div class="specific_dropdown">
                                <input type="text" value="<?php echo $ticket_specific; ?>" name="ticket_permission_specific" id="ticket_types_specific_dropdown" class="w100p validate-hidden" data-rule-required="true" data-msg-required="<?php echo app_lang('field_required'); ?>" placeholder="<?php echo app_lang('choose_ticket_types'); ?>" />
                            </div>
                        </div>
                    </li>
                <?php } ?>

                <?php if (get_setting("module_help") || get_setting("module_knowledge_base")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_manage_help_and_knowledge_base"); ?></h5>
                        <div>
                            <?php
                            if (is_null($help_and_knowledge_base)) {
                                $help_and_knowledge_base = "";
                            }
                            echo form_radio(array(
                                "id" => "help_no",
                                "name" => "help_and_knowledge_base",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $help_and_knowledge_base, ($help_and_knowledge_base === "") ? true : false);
                            ?>
                            <label for="help_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "help_yes",
                                "name" => "help_and_knowledge_base",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $help_and_knowledge_base, ($help_and_knowledge_base === "all") ? true : false);
                            ?>
                            <label for="help_yes"><?php echo app_lang("yes"); ?></label>
                        </div>
                    </li>
                <?php } ?>


                <?php if (get_setting("module_invoice")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("set_invoice_permissions"); ?>:</h5>
                        <div>
                            <?php
                            if (is_null($invoice)) {
                                $invoice = "";
                            }
                            echo form_radio(array(
                                "id" => "invoice_no",
                                "name" => "invoice_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "") ? true : false);
                            ?>
                            <label for="invoice_no"><?php echo app_lang("can_not_access_invoices"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "invoice_yes",
                                "name" => "invoice_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "all") ? true : false);
                            ?>
                            <label for="invoice_yes"><?php echo app_lang("can_manage_all_invoices"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "invoice_read_only",
                                "name" => "invoice_permission",
                                "value" => "read_only",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "read_only") ? true : false);
                            ?>
                            <label for="invoice_read_only"><?php echo app_lang("can_view_all_invoices"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "manage_own_client_invoices",
                                "name" => "invoice_permission",
                                "value" => "manage_own_client_invoices",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "manage_own_client_invoices") ? true : false);
                            ?>
                            <label for="manage_own_client_invoices"><?php echo app_lang("can_manage_own_client_invoices"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "manage_own_client_invoices_except_delete",
                                "name" => "invoice_permission",
                                "value" => "manage_own_client_invoices_except_delete",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "manage_own_client_invoices_except_delete") ? true : false);
                            ?>
                            <label for="manage_own_client_invoices_except_delete"><?php echo app_lang("can_manage_own_client_invoices_except_delete"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "view_own_client_invoices",
                                "name" => "invoice_permission",
                                "value" => "view_own_client_invoices",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "view_own_client_invoices") ? true : false);
                            ?>
                            <label for="view_own_client_invoices"><?php echo app_lang("can_view_own_client_invoices"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "manage_only_own_created_invoices",
                                "name" => "invoice_permission",
                                "value" => "manage_only_own_created_invoices",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "manage_only_own_created_invoices") ? true : false);
                            ?>
                            <label for="manage_only_own_created_invoices"><?php echo app_lang("can_manage_only_own_created_invoices"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "manage_only_own_created_invoices_except_delete",
                                "name" => "invoice_permission",
                                "value" => "manage_only_own_created_invoices_except_delete",
                                "class" => "form-check-input",
                            ), $invoice, ($invoice === "manage_only_own_created_invoices_except_delete") ? true : false);
                            ?>
                            <label for="manage_only_own_created_invoices_except_delete"><?php echo app_lang("can_manage_only_own_created_invoices_except_delete"); ?></label>
                        </div>
                    </li>
                <?php } ?>
                <?php if (get_setting("module_estimate")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_estimates"); ?></h5>
                        <div>
                            <?php
                            if (is_null($estimate)) {
                                $estimate = "";
                            }
                            echo form_radio(array(
                                "id" => "estimate_no",
                                "name" => "estimate_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $estimate, ($estimate === "") ? true : false);
                            ?>
                            <label for="estimate_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "estimate_yes",
                                "name" => "estimate_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $estimate, ($estimate === "all") ? true : false);
                            ?>
                            <label for="estimate_yes"><?php echo app_lang("yes_all_estimates"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "estimate_yes_own",
                                "name" => "estimate_permission",
                                "value" => "own",
                                "class" => "form-check-input",
                            ), $estimate, ($estimate === "own") ? true : false);
                            ?>
                            <label for="estimate_yes_own"><?php echo app_lang("yes_only_own_estimates"); ?></label>
                        </div>
                    </li>
                <?php } ?>
                <?php if (get_setting("module_contract")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_contracts"); ?></h5>
                        <div>
                            <?php
                            if (is_null($contract)) {
                                $contract = "";
                            }
                            echo form_radio(array(
                                "id" => "contract_no",
                                "name" => "contract_permission",
                                "value" => "",
                                "class" => "form-check-input"
                            ), $contract, ($contract === "") ? true : false);
                            ?>
                            <label for="contract_no"><?php echo app_lang("can_not_access_contracts"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "contract_yes",
                                "name" => "contract_permission",
                                "value" => "all",
                                "class" => "form-check-input"
                            ), $contract, ($contract === "all") ? true : false);
                            ?>
                            <label for="contract_yes"><?php echo app_lang("can_manage_all_contracts"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "manage_only_own_client_contracts",
                                "name" => "contract_permission",
                                "value" => "manage_only_own_client_contracts",
                                "class" => "form-check-input"
                            ), $contract, ($contract === "manage_only_own_client_contracts") ? true : false);
                            ?>
                            <label for="manage_only_own_client_contracts"><?php echo app_lang("can_manage_only_own_client_contracts"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "see_only_own_client_contracts",
                                "name" => "contract_permission",
                                "value" => "see_only_own_client_contracts",
                                "class" => "form-check-input"
                            ), $contract, ($contract === "see_only_own_client_contracts") ? true : false);
                            ?>
                            <label for="see_only_own_client_contracts"><?php echo app_lang("can_see_only_own_client_contracts"); ?></label>
                        </div>
                    </li>
                <?php } ?>
                <?php if (get_setting("module_proposal")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_proposals"); ?></h5>
                        <div>
                            <?php
                            if (is_null($proposal)) {
                                $proposal = "";
                            }
                            echo form_radio(array(
                                "id" => "proposal_no",
                                "name" => "proposal_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $proposal, ($proposal === "") ? true : false);
                            ?>
                            <label for="proposal_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "proposal_yes",
                                "name" => "proposal_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $proposal, ($proposal === "all") ? true : false);
                            ?>
                            <label for="proposal_yes"><?php echo app_lang("yes_all_proposals"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "proposal_yes_own",
                                "name" => "proposal_permission",
                                "value" => "own",
                                "class" => "form-check-input",
                            ), $proposal, ($proposal === "own") ? true : false);
                            ?>
                            <label for="proposal_yes_own"><?php echo app_lang("yes_only_own_proposals"); ?></label>
                        </div>
                    </li>
                <?php } ?>

                <?php if (get_setting("module_order")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_orders"); ?></h5>
                        <div>
                            <?php
                            if (is_null($order)) {
                                $order = "";
                            }
                            echo form_radio(array(
                                "id" => "order_no",
                                "name" => "order_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $order, ($order === "") ? true : false);
                            ?>
                            <label for="order_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "order_yes",
                                "name" => "order_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $order, ($order === "all") ? true : false);
                            ?>
                            <label for="order_yes"><?php echo app_lang("yes"); ?></label>
                        </div>
                    </li>
                <?php } ?>


                <?php if (get_setting("module_subscription")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_subscriptions"); ?></h5>
                        <div>
                            <?php
                            if (is_null($subscription)) {
                                $subscription = "";
                            }
                            echo form_radio(array(
                                "id" => "subscription_no",
                                "name" => "subscription_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $subscription, ($subscription === "") ? true : false);
                            ?>
                            <label for="subscription_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "subscription_yes",
                                "name" => "subscription_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $subscription, ($subscription === "all") ? true : false);
                            ?>
                            <label for="subscription_yes"><?php echo app_lang("yes"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "subscription_read_only",
                                "name" => "subscription_permission",
                                "value" => "read_only",
                                "class" => "form-check-input",
                            ), $subscription, ($subscription === "read_only") ? true : false);
                            ?>
                            <label for="subscription_read_only"><?php echo app_lang("read_only"); ?></label>
                        </div>
                    </li>
                <?php } ?>
                <?php if (get_setting("module_expense")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_access_expenses"); ?></h5>
                        <div>
                            <?php
                            if (is_null($expense)) {
                                $expense = "";
                            }
                            echo form_radio(array(
                                "id" => "expense_no",
                                "name" => "expense_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $expense, ($expense === "") ? true : false);
                            ?>
                            <label for="expense_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "expense_yes",
                                "name" => "expense_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $expense, ($expense === "all") ? true : false);
                            ?>
                            <label for="expense_yes"><?php echo app_lang("yes_all_expenses"); ?></label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "expense_yes_own",
                                "name" => "expense_permission",
                                "value" => "manage_own_created_expenses",
                                "class" => "form-check-input",
                            ), $expense, ($expense === "manage_own_created_expenses") ? true : false);
                            ?>
                            <label for="expense_yes_own"><?php echo app_lang("can_manage_only_own_created_expenses"); ?></label>
                        </div>
                    </li>
                <?php } ?>
                <?php if (get_setting("module_announcement")) { ?>
                    <li>
                        <span data-feather="key" class="icon-14 ml-20"></span>
                        <h5><?php echo app_lang("can_manage_announcements"); ?></h5>
                        <div>
                            <?php
                            if (is_null($announcement)) {
                                $announcement = "";
                            }
                            echo form_radio(array(
                                "id" => "announcement_no",
                                "name" => "announcement_permission",
                                "value" => "",
                                "class" => "form-check-input",
                            ), $announcement, ($announcement === "") ? true : false);
                            ?>
                            <label for="announcement_no"><?php echo app_lang("no"); ?> </label>
                        </div>
                        <div>
                            <?php
                            echo form_radio(array(
                                "id" => "announcement_yes",
                                "name" => "announcement_permission",
                                "value" => "all",
                                "class" => "form-check-input",
                            ), $announcement, ($announcement === "all") ? true : false);
                            ?>
                            <label for="announcement_yes"><?php echo app_lang("yes"); ?></label>
                        </div>
                    </li>
                <?php } ?>


                <?php
                try {
                    app_hooks()->do_action('app_hook_role_permissions_extension');
                } catch (\Exception $ex) {
                    log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
                }
                ?>

            </ul>

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary mr10"><span data-feather="check-circle" class="icon-14"></span> <?php echo app_lang('save'); ?></button>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#permissions-form").appForm({
            isModal: false,
            onSuccess: function(result) {
                appAlert.success(result.message, {
                    duration: 10000
                });
            }
        });

        $("#leave_specific_dropdown, #attendance_specific_dropdown, #timesheet_manage_permission_specific_dropdown, #timesheet_manage_permission_specific_excluding_own_dropdown, #team_member_update_permission_specific_dropdown, #message_permission_specific_dropdown, #timeline_permission_specific_dropdown").select2({
            multiple: true,
            formatResult: teamAndMemberSelect2Format,
            formatSelection: teamAndMemberSelect2Format,
            data: <?php echo ($members_and_teams_dropdown); ?>
        }).on('select2-open change', function(e) {
            feather.replace();
        });

        feather.replace();

        $("#ticket_types_specific_dropdown").select2({
            multiple: true,
            data: <?php echo ($ticket_types_dropdown); ?>
        });

        $('[data-bs-toggle="tooltip"]').tooltip();

        $(".toggle_specific").click(function() {
            toggle_specific_dropdown();
        });

        toggle_specific_dropdown();

        function toggle_specific_dropdown() {
            var selectors = [".leave_permission", ".attendance_permission", ".timesheet_manage_permission", ".team_member_update_permission", ".ticket_permission", ".message_permission_specific", ".timeline_permission_specific", ".client_permission"];
            $.each(selectors, function(index, element) {
                var $element = $(element + ":checked");
                if (((element !== ".message_permission_specific" && $element.val() === "specific") || (element === ".message_permission_specific" && $element.is(":checked") && !$("#message_permission_specific_area").hasClass("hide"))) ||
                    ((element !== ".timeline_permission_specific" && $element.val() === "specific") || (element === ".timeline_permission_specific" && $element.is(":checked") && !$("#timeline_permission_specific_area").hasClass("hide"))) ||
                    ($element.val() === "specific_excluding_own" && $element.is(":checked"))) {

                    $(element).closest("li").find(".specific_dropdown").hide().find("input").removeClass("validate-hidden"); //hide other active dropdown first
                    $element.closest("div").find(".specific_dropdown").show().find("input").addClass("validate-hidden");
                } else {
                    $(element).closest("div").find(".specific_dropdown").hide().find("input").removeClass("validate-hidden");
                }
            });

        }

        //show/hide message permission checkbox
        $("#message_permission_no").click(function() {
            if ($(this).is(":checked")) {
                $("#message_permission_specific_area").addClass("hide");
            } else {
                $("#message_permission_specific_area").removeClass("hide");
            }

            toggle_specific_dropdown();
        });

        //show/hide role permission setting
        $("#can_manage_all_kinds_of_settings").click(function() {
            if ($(this).is(":checked")) {
                $("#can_manage_user_role_and_permissions_container").removeClass("hide");
            } else {
                $("#can_manage_user_role_and_permissions_container").addClass("hide");
            }
        });

        $("#do_not_show_projects").click(function() {
            if ($(this).is(":checked")) {
                $("#project_permission_details_area").addClass("hide");
            } else {
                $("#project_permission_details_area").removeClass("hide");
            }
        });

        var manageProjectSection = "#can_manage_all_projects, #can_create_projects, #can_edit_projects, #can_delete_projects, #can_add_remove_project_members, #can_create_tasks";
        var manageAssignedTasks = "#show_assigned_tasks_only, #can_update_only_assigned_tasks_status";
        var manageAssignedTasksSection = "#show_assigned_tasks_only_section, #can_update_only_assigned_tasks_status_section";

        if ($(manageProjectSection).is(':checked')) {
            $(manageAssignedTasksSection).addClass("hide");
        }

        $(manageProjectSection).click(function() {
            if ($(this).is(":checked")) {
                $(manageAssignedTasks).prop("checked", false);
                $(manageAssignedTasksSection).addClass("hide");
                if ($(this).attr("id") === "can_edit_projects") {
                    $("#can_edit_only_own_created_projects_section").addClass("hide");
                } else if ($(this).attr("id") === "can_delete_projects") {
                    $("#can_delete_only_own_created_projects_section").addClass("hide");
                }
            } else {
                if ($(this).attr("id") === "can_edit_projects") {
                    $("#can_edit_only_own_created_projects_section").removeClass("hide");
                } else if ($(this).attr("id") === "can_delete_projects") {
                    $("#can_delete_only_own_created_projects_section").removeClass("hide");
                }
            }
        });

        $('.manage_project_section').change(function() {
            var checkedStatus = $('.manage_project_section:checkbox:checked').length > 0;
            if (!checkedStatus) {
                $(manageAssignedTasksSection).removeClass("hide");
            }
        }).change();

        //show/hide timeline permission checkbox
        $("#timeline_permission_no").click(function() {
            if ($(this).is(":checked")) {
                $("#timeline_permission_specific_area").addClass("hide");
            } else {
                $("#timeline_permission_specific_area").removeClass("hide");
            }

            toggle_specific_dropdown();
        });
        $("#client_groups_specific_dropdown").select2({
            multiple: true,
            data: <?php echo ($client_groups_dropdown); ?>
        });

        $("#can_manage_all_projects").click(function() {
            if ($(this).is(":checked")) {
                $("#individual_project_permission_area").addClass("hide");
            } else {
                $("#individual_project_permission_area").removeClass("hide");
            }
        });

        $("#hide_team_members_list").click(function() {
            if ($(this).is(":checked")) {
                $("#individual_team_members_permission_area").addClass("hide");
            } else {
                $("#individual_team_members_permission_area").removeClass("hide");
            }
        });

        $("#can_upload_and_edit_files").change(function() {
            if ($(this).is(":checked")) {
                $("#can_view_files").prop("checked", true).trigger("change");
            }
        });



        function linkManageToView(manageId, viewId) {
  $(manageId).on("change", function () {
    if ($(this).is(":checked")) {
      $(viewId).prop("checked", true);
    }
  });
}

linkManageToView("#can_manage_countries", "#can_view_countries");
linkManageToView("#can_manage_regions", "#can_view_regions");
linkManageToView("#can_manage_cities", "#can_view_cities");
linkManageToView("#can_manage_legal_types", "#can_view_legal_types");

    });
</script>