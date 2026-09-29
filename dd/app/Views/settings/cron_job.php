<div id="page-content" class="page-wrapper clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?php
            $tab_view['active_tab'] = "cron_job";
            echo view("settings/tabs", $tab_view);
            ?>
        </div>

        <div class="col-sm-9 col-lg-10">

            <div class="card">
                <div class="card-header">
                    <h4><?php echo app_lang("cron_job"); ?></h4>
                </div>
                <div class="card-body general-form dashed-row">
                    <div class="form-group clearfix">
                        <div class="row">
                            <label for="cron_job_link" class=" col-md-2"><?php echo app_lang('cron_job_link'); ?></label>
                            <div class=" col-md-10">
                                <?php
                                echo get_uri("cron");
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group clearfix">
                        <div class="row">
                            <label for="last_cron_job_run" class=" col-md-2"><?php echo app_lang('last_cron_job_run'); ?></label>
                            <div class=" col-md-10">
                                <?php
                                $status_class = "bg-dark";
                                $last_cron_job_time = get_setting('last_cron_job_time');
                                if ($last_cron_job_time) {
                                    $text = format_to_datetime(date('Y-m-d H:i:s', $last_cron_job_time));

                                    //show success color if last execution time is less then 60 min
                                    if (round(abs($last_cron_job_time - strtotime(get_current_utc_time())) / 60) <= 60) {
                                        $status_class = "bg-success";
                                    }
                                } else {
                                    $text = app_lang('never');
                                    $status_class = "bg-danger";
                                }

                                echo "<span class='badge $status_class'>" . $text . "</span>";
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group clearfix">
                        <div class="row">
                            <label for="recommended_execution_intervals" class=" col-md-2"><?php echo app_lang('recommended_execution_interval'); ?></label>
                            <div class=" col-md-10">
                                Every 5 minutes for general application jobs; every minute for workflow SMS.
                            </div>
                        </div>
                    </div>
                    <div class="form-group clearfix">
                        <div class="row">
                            <label class=" col-md-2">Hosting scheduler commands</label>
                            <div class=" col-md-10">
                                <div>
                                    <p>General application jobs require an authenticated POST. Ask your hosting administrator to create a private curl configuration file at the path below, with the request URL, POST method, and the <code>X-PODC-Cron-Key</code> header matching <code>PODC_CRON_KEY</code> in the production environment.</p>
                                    <pre><?php echo esc('/usr/bin/curl --config ' . escapeshellarg(WRITEPATH . 'cron-http.conf')); ?></pre>
                                    <p>Run workflow SMS every minute using the hosting account's PHP command. Replace the PHP path if your host uses a different location.</p>
                                    <pre><?php echo esc('cd ' . escapeshellarg(ROOTPATH) . ' && /opt/cpanel/ea-php81/root/usr/bin/php spark sms:process'); ?></pre>
                                </div>

                                <div class="">
                                    <p class="text-muted">Opening the cron URL in a browser does not run jobs. Keep the cron configuration file private, set its permissions to 600, and write scheduler errors to a protected log. The last-run indicator above confirms when general jobs have executed.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
