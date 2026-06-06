<!DOCTYPE html>
<html lang="en">
    <head>
        <?php echo view('includes/head'); ?>
    </head>
    <body class="public-view signin-page pod-auth-page">
        <?php
        $signin_logo_url = base_url("assets/images/port-duqum-signin-logo.png");

        if (get_setting("show_background_image_in_signin_page") === "yes") {
            $background_url = get_file_from_setting("signin_page_background");
            ?>
            <style type="text/css">
                body.pod-auth-page .pod-auth-brand-panel {
                    background-image:
                        linear-gradient(145deg, rgba(8, 20, 35, .95), rgba(8, 20, 35, .78)),
                        url('<?php echo esc($background_url); ?>');
                    background-size: cover;
                    background-position: center;
                }
            </style>
        <?php } ?>

        <div class="scrollable-page pod-auth-scroll">
            <div class="pod-auth-shell">
                <aside class="pod-auth-brand-panel" aria-label="Port of Duqm portal">
                    <div>
                        <div class="pod-auth-brand-lockup">
                            <span class="pod-auth-logo-mark">
                                <img src="<?php echo $signin_logo_url; ?>" alt="Port of Duqm" />
                            </span>
                            <span>
                                <strong>Port of Duqm</strong>
                                <small>Operational Portal</small>
                            </span>
                        </div>

                        <div class="pod-auth-copy">
                            <span>Secure Workspace</span>
                            <h1>Sign in to manage port operations.</h1>
                            <p>Access tendering, gate pass, permit to work, and vendor workflows from one professional portal.</p>
                        </div>
                    </div>

                    <div class="pod-auth-module-list" aria-label="Core portal modules">
                        <div class="pod-auth-module-item">
                            <i data-feather="file-text" class="icon-18"></i>
                            <span>Tender Management</span>
                        </div>
                        <div class="pod-auth-module-item">
                            <i data-feather="shield" class="icon-18"></i>
                            <span>Gate Pass Control</span>
                        </div>
                        <div class="pod-auth-module-item">
                            <i data-feather="clipboard" class="icon-18"></i>
                            <span>Permit to Work</span>
                        </div>
                        <div class="pod-auth-module-item">
                            <i data-feather="briefcase" class="icon-18"></i>
                            <span>Vendor Services</span>
                        </div>
                    </div>
                </aside>

                <main class="pod-auth-form-panel">
                    <div class="form-signin pod-auth-card">
                        <?php
                        if (isset($form_type) && $form_type == "request_reset_password") {
                            echo view("signin/reset_password_form");
                        } else if (isset($form_type) && $form_type == "new_password") {
                            echo view('signin/new_password_form');
                        } else {
                            echo view("signin/signin_form");
                        }
                        ?>
                    </div>
                </main>
            </div>
        </div>

        <script>
            $(document).ready(function () {
                initScrollbar('.scrollable-page', {
                    setHeight: $(window).height()
                });

                if (typeof feather !== "undefined") {
                    feather.replace();
                }
            });
        </script>

        <?php echo view("includes/footer"); ?>
    </body>
</html>
