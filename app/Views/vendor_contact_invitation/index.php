<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo view("includes/head"); ?>
</head>
<body class="public-view signin-page pod-auth-page">
    <div class="scrollable-page pod-auth-scroll">
        <div class="pod-auth-shell">
            <aside class="pod-auth-brand-panel" aria-label="Port of Duqm vendor portal">
                <div>
                    <div class="pod-auth-brand-lockup">
                        <span class="pod-auth-logo-mark">
                            <img src="<?php echo base_url("assets/images/port-duqum-signin-logo.png"); ?>" alt="Port of Duqm" />
                        </span>
                        <span>
                            <strong>Port of Duqm</strong>
                            <small>Vendor Portal</small>
                        </span>
                    </div>
                    <div class="pod-auth-copy">
                        <span>Contact invitation</span>
                        <h1>Activate your vendor access.</h1>
                        <p>You have been approved as a contact for <?php echo esc($vendor_name); ?>.</p>
                    </div>
                </div>
            </aside>

            <main class="pod-auth-form-panel">
                <div class="form-signin pod-auth-card">
                    <div class="card mb15">
                        <div class="card-header text-center">
                            <h2>Set your password</h2>
                            <p class="text-muted mb0"><?php echo esc($email); ?></p>
                        </div>
                        <div class="card-body p30 rounded-bottom">
                            <?php echo form_open("vendor_contact_invitation/activate", [
                                "id" => "vendor-contact-activation-form",
                                "class" => "general-form",
                                "role" => "form",
                            ]); ?>
                            <input type="hidden" name="key" value="<?php echo esc($key); ?>" />

                            <div class="form-group">
                                <label for="password"><?php echo app_lang("password"); ?></label>
                                <?php echo form_password([
                                    "id" => "password",
                                    "name" => "password",
                                    "class" => "form-control p10",
                                    "autocomplete" => "new-password",
                                    "data-rule-required" => true,
                                    "data-rule-minlength" => 8,
                                    "data-msg-minlength" => "Please enter at least 8 characters.",
                                ]); ?>
                            </div>

                            <div class="form-group">
                                <label for="retype_password"><?php echo app_lang("retype_password"); ?></label>
                                <?php echo form_password([
                                    "id" => "retype_password",
                                    "name" => "retype_password",
                                    "class" => "form-control p10",
                                    "autocomplete" => "new-password",
                                    "data-rule-required" => true,
                                    "data-rule-equalTo" => "#password",
                                    "data-msg-equalTo" => app_lang("enter_same_value"),
                                ]); ?>
                            </div>

                            <button class="w-100 btn btn-lg btn-primary btn-block" type="submit">
                                Activate vendor access
                            </button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(function () {
            initScrollbar(".scrollable-page", {setHeight: $(window).height()});
            $("#vendor-contact-activation-form").appForm({
                isModal: false,
                onSubmit: function () {
                    appLoader.show();
                },
                onSuccess: function (result) {
                    appLoader.hide();
                    appAlert.success(result.message, {container: ".card-body", animate: false});
                    $("#vendor-contact-activation-form").remove();
                },
                onError: function (result) {
                    appLoader.hide();
                    appAlert.error(result.message, {container: ".card-body", animate: false});
                    return false;
                }
            });
        });
    </script>
    <?php echo view("includes/footer"); ?>
</body>
</html>
