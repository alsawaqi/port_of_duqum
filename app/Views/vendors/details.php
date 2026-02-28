<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1>
                <?php echo app_lang("vendor"); ?>:
                <?php echo esc($vendor->vendor_name ?? ""); ?>
                <span class="text-muted">(#<?php echo (int)$vendor->id; ?>)</span>
            </h1>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-docs"><?php echo app_lang("documents"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-contacts"><?php echo app_lang("contacts"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-bank"><?php echo app_lang("bank"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-branches"><?php echo app_lang("branches"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-credentials"><?php echo app_lang("credentials"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-specialties"><?php echo app_lang("specialties"); ?></a></li>
        </ul>

        <div class="tab-content p15">
            <div class="tab-pane fade show active" id="tab-docs">
                <table id="vendor-docs-table" class="display" width="100%"></table>
            </div>

            <div class="tab-pane fade" id="tab-contacts">
                <table id="vendor-contacts-table" class="display" width="100%"></table>
            </div>

            <div class="tab-pane fade" id="tab-bank">
                <table id="vendor-bank-table" class="display" width="100%"></table>
            </div>

            <div class="tab-pane fade" id="tab-branches">
                <table id="vendor-branches-table" class="display" width="100%"></table>
            </div>

            <div class="tab-pane fade" id="tab-credentials">
                <table id="vendor-credentials-table" class="display" width="100%"></table>
            </div>

            <div class="tab-pane fade" id="tab-specialties">
                <table id="vendor-specialties-table" class="display" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const vendorId = <?php echo (int)$vendor->id; ?>;

        // Documents (optional: still works even if type name is not joined)
        $("#vendor-docs-table").appTable({
            source: "<?php echo_uri('vendors/vendor_documents_list_data/'); ?>" + vendorId,
            columns: [{
                    title: "<?php echo app_lang('type'); ?>"
                },
                {
                    title: "<?php echo app_lang('file'); ?>"
                },
                {
                    title: "<?php echo app_lang('issued_at'); ?>"
                },
                {
                    title: "<?php echo app_lang('expires_at'); ?>"
                },
                {
                    title: "<?php echo app_lang('size'); ?>"
                },
                {
                    title: "<i data-feather='menu' class='icon-16'></i>",
                    class: "text-center option w150"
                }
            ]
        });

        // Contacts: contacts_name, email, phone/mobile, designation, role, primary, active
        $("#vendor-contacts-table").appTable({
            source: "<?php echo_uri('vendors/vendor_contacts_list_data/'); ?>" + vendorId,
            columns: [{
                    title: "<?php echo app_lang('name'); ?>"
                },
                {
                    title: "<?php echo app_lang('email'); ?>"
                },
                {
                    title: "<?php echo app_lang('phone'); ?>"
                },
                {
                    title: "<?php echo app_lang('designation'); ?>"
                },
                {
                    title: "<?php echo app_lang('role'); ?>"
                },
                {
                    title: "<?php echo app_lang('primary'); ?>",
                    class: "text-center w10p"
                },
                {
                    title: "<?php echo app_lang('active'); ?>",
                    class: "text-center w10p"
                }
            ]
        });

        // Bank accounts: bank_name, bank_account_no, iban, swift, branch
        $("#vendor-bank-table").appTable({
            source: "<?php echo_uri('vendors/vendor_bank_list_data/'); ?>" + vendorId,
            columns: [{
                    title: "<?php echo app_lang('bank'); ?>"
                },
                {
                    title: "<?php echo app_lang('account_no'); ?>"
                },
                {
                    title: "<?php echo app_lang('iban'); ?>"
                },
                {
                    title: "SWIFT"
                },
                {
                    title: "<?php echo app_lang('branch'); ?>"
                }
            ]
        });

        // Branches: name, address, phone, email
        $("#vendor-branches-table").appTable({
            source: "<?php echo_uri('vendors/vendor_branches_list_data/'); ?>" + vendorId,
            columns: [{
                    title: "<?php echo app_lang('branch'); ?>"
                },
                {
                    title: "<?php echo app_lang('address'); ?>"
                },
                {
                    title: "<?php echo app_lang('phone'); ?>"
                },
                {
                    title: "<?php echo app_lang('email'); ?>"
                }
            ]
        });

        // Credentials: type, number, issue_date, expiry_date
        $("#vendor-credentials-table").appTable({
            source: "<?php echo_uri('vendors/vendor_credentials_list_data/'); ?>" + vendorId,
            columns: [{
                    title: "<?php echo app_lang('type'); ?>"
                },
                {
                    title: "<?php echo app_lang('number'); ?>"
                },
                {
                    title: "<?php echo app_lang('issue_date'); ?>"
                },
                {
                    title: "<?php echo app_lang('expiry_date'); ?>"
                },
                {
                    title: "<?php echo app_lang('notes'); ?>"
                }
            ]
        });

        // Specialties: specialty_type, specialty_name, specialty_description
        $("#vendor-specialties-table").appTable({
            source: "<?php echo_uri('vendors/vendor_specialties_list_data/'); ?>" + vendorId,
            columns: [{
                    title: "<?php echo app_lang('category'); ?>"
                }, // Category name
                {
                    title: "<?php echo app_lang('sub_category'); ?>"
                }, // Sub-category name
                {
                    title: "<?php echo app_lang('description'); ?>"
                } // Description
            ]
        });


        // ✅ Fix hidden-tab DataTable rendering
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        });
    });
</script>