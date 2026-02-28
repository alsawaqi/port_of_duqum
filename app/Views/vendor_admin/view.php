<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo esc($vendor->vendor_name); ?> <span class="text-muted">(#<?php echo (int)$vendor->id; ?>)</span></h1>
        </div>

        <ul class="nav nav-tabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#docs"><?php echo app_lang("documents"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#contacts"><?php echo app_lang("contacts"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#bank"><?php echo app_lang("bank"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#branches"><?php echo app_lang("branches"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#credentials"><?php echo app_lang("credentials"); ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#specialties"><?php echo app_lang("specialties"); ?></a></li>
        </ul>

        <div class="tab-content p15">
            <div class="tab-pane fade show active" id="docs">
                <table id="docs-table" class="display" width="100%"></table>
            </div>
            <div class="tab-pane fade" id="contacts">
                <table id="contacts-table" class="display" width="100%"></table>
            </div>
            <div class="tab-pane fade" id="bank">
                <table id="bank-table" class="display" width="100%"></table>
            </div>
            <div class="tab-pane fade" id="branches">
                <table id="branches-table" class="display" width="100%"></table>
            </div>
            <div class="tab-pane fade" id="credentials">
                <table id="credentials-table" class="display" width="100%"></table>
            </div>
            <div class="tab-pane fade" id="specialties">
                <table id="specialties-table" class="display" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        const id = <?php echo (int)$vendor->id; ?>;

        $("#docs-table").appTable({
            source: "<?php echo_uri('vendor_admin/documents_list_data/'); ?>" + id,
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
                    title: "<?php echo app_lang('view'); ?>"
                }
            ]
        });

        $("#contacts-table").appTable({
            source: "<?php echo_uri('vendor_admin/contacts_list_data/'); ?>" + id,
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
                    title: "<?php echo app_lang('position'); ?>"
                }
            ]
        });

        $("#bank-table").appTable({
            source: "<?php echo_uri('vendor_admin/bank_list_data/'); ?>" + id,
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
                    title: "<?php echo app_lang('status'); ?>"
                }
            ]
        });

        $("#branches-table").appTable({
            source: "<?php echo_uri('vendor_admin/branches_list_data/'); ?>" + id,
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
                    title: "<?php echo app_lang('status'); ?>"
                }
            ]
        });

        $("#credentials-table").appTable({
            source: "<?php echo_uri('vendor_admin/credentials_list_data/'); ?>" + id,
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
                }
            ]
        });

        $("#specialties-table").appTable({
            source: "<?php echo_uri('vendor_admin/specialties_list_data/'); ?>" + id,
            columns: [{
                    title: "<?php echo app_lang('specialty'); ?>"
                },
                {
                    title: "<?php echo app_lang('status'); ?>"
                }
            ]
        });
    });
</script>