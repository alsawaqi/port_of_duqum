<div id="page-content" class="page-wrapper clearfix pod-page-shell">
    <?php
    echo view("includes/pod_page_header", [
        "title" => app_lang("change_password"),
        "subtitle" => "Update the password used for your Port of Duqm account.",
        "icon" => "key",
        "breadcrumbs" => [
            ["label" => app_lang("change_password")],
        ],
    ]);
    ?>

    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb0"><?php echo app_lang("change_password"); ?></h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        This password belongs to your login identity and applies to every portal and CR linked to your email.
                    </div>

                    <?php echo form_open(get_uri("portal_account/save_password"), [
                        "id" => "portal-password-form",
                        "class" => "general-form dashed-row",
                        "role" => "form",
                    ]); ?>

                    <div class="form-group">
                        <label for="current_password">Current password</label>
                        <?php echo form_password([
                            "id" => "current_password",
                            "name" => "current_password",
                            "class" => "form-control",
                            "autocomplete" => "current-password",
                            "data-rule-required" => true,
                            "data-msg-required" => app_lang("field_required"),
                        ]); ?>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New password</label>
                        <?php echo form_password([
                            "id" => "new_password",
                            "name" => "new_password",
                            "class" => "form-control",
                            "autocomplete" => "new-password",
                            "data-rule-required" => true,
                            "data-rule-minlength" => 10,
                            "data-rule-maxlength" => 72,
                            "data-msg-required" => app_lang("field_required"),
                            "data-msg-minlength" => "Please enter at least 10 characters.",
                            "data-msg-maxlength" => "Please enter no more than 72 characters.",
                        ]); ?>
                        <small class="text-muted">Use at least 10 characters (maximum 72 UTF-8 bytes).</small>
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirm">Confirm new password</label>
                        <?php echo form_password([
                            "id" => "new_password_confirm",
                            "name" => "new_password_confirm",
                            "class" => "form-control",
                            "autocomplete" => "new-password",
                            "data-rule-required" => true,
                            "data-rule-equalTo" => "#new_password",
                            "data-msg-required" => app_lang("field_required"),
                            "data-msg-equalTo" => app_lang("enter_same_value"),
                        ]); ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i data-feather="check-circle" class="icon-16"></i>
                        <?php echo app_lang("change_password"); ?>
                    </button>

                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#portal-password-form").appForm({
            isModal: false,
            onSuccess: function(result) {
                appAlert.success(result.message);
                document.getElementById("portal-password-form").reset();
            },
            onError: function(result) {
                appAlert.error(result.message);
            }
        });
    });
</script>
