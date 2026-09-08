<div id="page-content" class="page-wrapper clearfix pod-page-shell pod-vendor-page pod-vendor-detail-page">
    <?php
    $vendor_name = $vendor->vendor_name ?? "";
    echo view("includes/pod_page_header", [
        "title" => app_lang("vendor") . ": " . $vendor_name,
        "subtitle" => "Inspect documents, contacts, bank accounts, branches, credentials, and specialties for this vendor.",
        "icon" => "briefcase",
        "breadcrumbs" => [
            ["label" => app_lang("vendors"), "url" => get_uri("vendors")],
            ["label" => $vendor_name]
        ]
    ]);
    ?>

    <div class="card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title">
                    <?php echo esc($vendor_name); ?>
                    <span class="text-muted">#<?php echo (int)$vendor->id; ?></span>
                </h2>
                <p class="pod-vendor-card-subtitle">Vendor compliance and master-data profile.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
            <div class="pod-vendor-summary-grid">
                <div class="pod-vendor-summary-item">
                    <span><?php echo app_lang("vendor_grade"); ?></span>
                    <strong><?php echo esc(vendor_grade_label($vendor->vendor_grade_name ?? "", $vendor->vendor_grade_code ?? "")); ?></strong>
                </div>
                <div class="pod-vendor-summary-item">
                    <span><?php echo app_lang("status"); ?></span>
                    <strong><?php echo esc($vendor->status ?? "-"); ?></strong>
                </div>
                <?php foreach (['registration_valid_from', 'registration_valid_to'] as $registration_date): ?>
                    <?php if (!empty($vendor->$registration_date)): ?>
                    <div class="pod-vendor-summary-item">
                        <span><?php echo app_lang('vendor_' . $registration_date); ?></span>
                        <strong><?php echo esc(format_to_date($vendor->$registration_date, false)); ?></strong>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (($vendor->status ?? "") === vendor_blocked_status()) { ?>
                    <div class="pod-vendor-summary-item">
                        <span><?php echo app_lang("reason"); ?></span>
                        <strong><?php echo esc($vendor->blocked_reason ?? "-"); ?></strong>
                    </div>
                <?php } ?>
            </div>
        </div>

        <ul class="nav nav-tabs pod-vendor-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-docs"><?php echo app_lang("documents"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-contacts"><?php echo app_lang("contacts"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-bank"><?php echo app_lang("bank"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-branches"><?php echo app_lang("branches"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-credentials"><?php echo app_lang("credentials"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-specialties"><?php echo app_lang("specialties"); ?></a></li>
        </ul>

        <div class="tab-content pod-vendor-card-body">
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
                },
                {
                    title: "Portal access",
                    class: "text-center"
                },
                {
                    title: "<i data-feather='menu' class='icon-16'></i>",
                    class: "text-center option w100"
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
