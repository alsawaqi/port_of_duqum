PORT OF DUQM PORTAL - NEW SERVER MIGRATION README
Prepared: 9 September 2026
For: Hosting / infrastructure team and application administrator

PURPOSE
Move the current application and its complete current database from
https://poderp.bedots.site/ to the new server and domain. Preserve existing
users, permissions, applications, payment records, settings and documents.
This is an installation checklist, not confirmation that the new server has
been configured or that all integrations have passed acceptance testing.

All paths below are relative to the project folder unless an absolute path
is shown. Replace every YOUR-... or /PROJECT/PATH placeholder before use.
This document deliberately contains no passwords, payment keys or OTP keys.

DESTINATION DETAILS - HOSTING TEAM TO COMPLETE
New public HTTPS address: ______________________________________________
Application folder, containing index.php and spark: ____________________
PHP command-line executable: ___________________________________________
Hosting account / job owner: ___________________________________________
New server's actual outgoing public IP: ________________________________
Database host and database name: _______________________________________
Bank environment agreed with owner and bank: UAT / LIVE
Cutover date and responsible person: ___________________________________

The scheduled-job instructions below are for Linux hosting. If the new
server is Windows, IT must configure equivalent tasks in Task Scheduler.
FileZilla transfers files; it does not install scheduled jobs.


1. BACK UP AND COPY THE CORRECT SOURCE

[ ] Take a complete, consistent export of the CURRENT database: structure
    AND data, including views, triggers, routines and events if used.
    A structure-only export is not sufficient.
[ ] Back up the current application, its live .env, configuration, and all
    uploaded documents and images. Keep a matching set for rollback.
[ ] Copy the current PHP 8.1.34 compatibility build, including the latest
    fixes. Include app/, system/, vendor/, assets/, plugins/, index.php,
    spark, files/, persistent writable/ contents and all .htaccess files.
    Keep any other runtime folders shipped with this application.
[ ] Transfer hidden .env and .htaccess files explicitly; ZIP/export tools
    sometimes omit them. Use the LIVE .env belonging to the copied database,
    not the localhost .env or a fresh copy of .env.example.
[ ] Include both public uploads in files/ and private uploads in writable/.
    Preserve their directory structure. A database export does not contain
    the actual uploaded files. Copy any configured external storage too.
[ ] Keep ZIPs, SQL dumps and backup copies outside public web access. Remove
    the deployment archive from the public website after extraction.

Before the final export, pause writes on the old site. If preparing the new
server earlier, repeat the database export and final upload-file sync at
cutover so new registrations and payments are not lost.


2. PREPARE THE WEB SERVER AND RESTORE THE DATABASE

[ ] Use the supplied PHP 8.1.34 compatibility build. Preserve its patched
    system/ directory, index.php and spark. Replacing system/ with a stock
    framework release or running composer update is not part of this move.
[ ] Enable the required PHP extensions for BOTH web PHP and command-line
    PHP: mysqli, curl, openssl, intl, mbstring, fileinfo, GD and ZIP.
[ ] Install a valid HTTPS certificate and configure DNS and the website's
    document root. This build uses index.php in the PROJECT ROOT; do not
    assume a standard CodeIgniter public/ document root.
[ ] Preserve .htaccess access protections and configure equivalent rules
    if using a web server that does not read .htaccess. Configure front-
    controller routing as well; the bundled root .htaccess alone does not
    provide a general rewrite of application routes to index.php.
[ ] If installing below a subfolder instead of the domain root, adjust the
    base URL and web-server route/access-rule prefixes for that subfolder.
[ ] Give the web application and job account the necessary access to
    writable/ and upload folders. Keep private documents behind the existing
    authenticated application download routes. Do not make writable/ public
    or solve permission problems by making the entire project writable.
[ ] Match working PHP upload/post limits and provide a writable upload
    temporary directory. Ensure the server clock is synchronized.
[ ] Restore the full current database with the pod_ table prefix intact.
    Check import errors, row counts and any routine/event DEFINER accounts.
    Do not rerun historical migrations or old schema patches blindly.
[ ] Update app/Config/Database.php with the new connection details, as the
    owner requested. Use a dedicated password-protected database account.
    The application requires TLS for a remote database connection.
[ ] Check that old database.default.* environment overrides, if present,
    do not override the new values in Database.php.


3. UPDATE .env IN THE PROJECT ROOT

Edit the existing live .env, alongside index.php. Merge these settings;
do not replace the whole file with this example:

    CI_ENVIRONMENT = production
    PODC_BASE_URL = "https://YOUR-NEW-DOMAIN/"
    PODC_RECAPTCHA_ENABLED = false
    PODC_RECAPTCHA_REQUIRED = false

Use the exact chosen hostname, including www if applicable. End the base
URL with a slash. Include the application subfolder if one is used, but do
not include index.php/signin or another page path. Make other base-URL
overrides consistent if they exist.

reCAPTCHA must remain DISABLED for now. Keep its code and settings available
so the owner can enable it later; do not remove the integration.

PRESERVE EXISTING SECRET VALUES FROM THIS SAME INSTALLATION:
  - Application encryption key, under whichever name is currently used:
    PODC_APP_ENCRYPTION_KEY, APP_ENCRYPTION_KEY, or app.encryption_key.
  - AUTH_SECURITY_MFA_HMAC_KEY.
  - TENDER_OPENING_CODE_HMAC_KEY.
  - TENDER_OPENING_CODE_ENCRYPTION_KEY.
  - PODC_CRON_KEY, matching the private cron configuration.
  - Other existing signing/integration secrets used by this installation.

Moving the SAME application/database does not require rotating these keys.
Replacing them can invalidate saved encrypted settings, identifiers or
active codes. The SMS password saved in Settings depends on the application
encryption key. Do not replace established values with CHANGE_ME placeholders.


4. BANK MUSCAT SMARTPAY

[ ] Confirm the new domain, callback address, account and UAT/LIVE environment
    with Bank Muscat. A domain move does not automatically change test keys
    into live keys. Retain UAT unless the owner and bank agree to live use.
[ ] Set these four fields in .env using the matching bank-issued values:

    SMARTPAY_MERCHANT_ID = "BANK_ISSUED_MERCHANT_ID"
    SMARTPAY_ACCESS_CODE = "BANK_ISSUED_ACCESS_CODE"
    SMARTPAY_WORKING_KEY = "BANK_ISSUED_WORKING_KEY"
    SMARTPAY_GATEWAY_URL = "SELECT_ONE_EXACT_URL_BELOW"

    UAT checkout URL:
    https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction

    LIVE checkout URL:
    https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction

[ ] Correct any existing eservices.payment.smartpayPublicBaseUrl override.
    Normally it is not needed: the bank return base follows PODC_BASE_URL.
[ ] Check eservices.payment.provider if explicitly set. A value of disabled
    overrides the SMARTPAY fields. Preserve deliberate settings and remove
    a stale disabled override only when checkout is intended to be active.
[ ] Separate eservices.payment.smartpayApiAccessCode and
    eservices.payment.smartpayApiWorkingKey are needed ONLY if the bank
    issued a separate Status API pair. Otherwise checkout credentials are
    used for status verification. Do not mix different credential pairs.
[ ] Register and route this callback, adjusted for any application subfolder:
    https://YOUR-NEW-DOMAIN/eservice_payment/return_from_bank
    It must reach PHP without requiring index.php in the address and accept
    the bank return request. Keep application verification enabled.
[ ] Allow server-side outbound HTTPS, including DNS and certificate checks:
    UAT: spayuattrns.bmtest.om and spayuatapi.bmtest.om
    LIVE: smartpaytrns.bankmuscat.com and smartpayapi.bankmuscat.com
    Give the bank the NEW outgoing public IP if its access policy requires it.

The previous host recorded UAT connection timeouts. That does not establish
the cause or prove the new host will have the same issue. Recheck from the
destination server. Successful browser checkout alone does not prove the
server can verify a payment with the bank.


5. SMS AND LOGIN OTP - SETTINGS ARE IN THE DATABASE

[ ] Retain Settings > SMS values when restoring the database. Saved SMS
    settings override ISMARTSMS_* defaults in .env, so changing .env alone
    may not change the active SMS account or enabled state.
[ ] Retain the approved iSmartSMS account and sender header (Port Duqm).
    If the provider issues new credentials, update them in Settings > SMS.
[ ] Permit outgoing HTTPS to the configured HTTP GET API:
    https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx
    Ask iSmartSMS to allow the NEW server's actual outgoing public IP.
    The previous server received provider code 20: client IP blocked.
[ ] Once provider access is available, verify delivery to an owner-approved
    test recipient, then enable the required connection/workflow switches
    in Settings > SMS: recording, Vendor/Gate Pass/PTW/Tender, and real SMS.
    Existing disabled settings remain disabled after a database copy.
[ ] Review any queued test notifications before enabling workers. Preview
    records do not become real messages merely by enabling real sending.
[ ] Verify the administrator's own valid mobile and OTP delivery before
    enabling Require SMS OTP for all logins. Check the current missing-mobile
    list; each user needs their own number. Do not fill accounts with one
    shared test number.

Workflow SMS is processed by the scheduled worker in section 7. Login OTP
sends immediately and does not depend on a cron job.


6. EMAIL / SMTP - PRESERVE THE SAVED SETTINGS; DO NOT TEST

These settings were saved through Settings > Email and move with the full
database. SMTP is not configured through .env for this installation.

    Protocol:            SMTP
    Email / From Email:  eservices@portofduqm.om
    SMTP server:         10.157.25.12
    Port:                25 (TCP)
    Authentication:      No
    SMTP username:       Empty
    SMTP password:       Empty
    Encryption:          None

[ ] Preserve the existing sender name and these saved settings.
[ ] Have infrastructure staff provide routing/firewall access from the new
    application server to this internal mail relay and permit its source.
    Internet hosting alone does not provide access to this private address.

OWNER INSTRUCTION: Do not send a test email or run an SMTP connection test
as part of this handover. The internal mail team handles validation later.


7. INSTALL THE TWO SCHEDULED JOBS

Run jobs under the hosting/application account with access to the project,
.env, private cron configuration and protected log directory.

Replace /PROJECT/PATH and /PHP/PATH/php below with the REAL absolute paths.
The previous cPanel PHP path was /opt/cpanel/ea-php81/root/usr/bin/php;
do not assume that path exists on the new server. Confirm curl's path too.

A. PREPARE THE GENERAL JOB'S PRIVATE CONFIGURATION

Copy writable/cron-http.conf from the existing live server. Update its URL
to the NEW site's cron URL shown in Settings > Cron Job. Preserve its POST
method and its X-PODC-Cron-Key matching PODC_CRON_KEY in the new .env.
Adjust any absolute file paths in this configuration if present.

If rebuilding the file is necessary, the following is a template only:

    url = "https://YOUR-NEW-DOMAIN/index.php/cron"
    request = "POST"
    header = "X-PODC-Cron-Key: COPY_THE_EXISTING_PODC_CRON_KEY_VALUE_HERE"
    silent
    show-error
    fail
    connect-timeout = 10
    max-time = 360

Include the application subfolder in the URL if applicable. Copy the actual
key privately into this configuration; curl does not substitute a .env
variable automatically. Use the final HTTPS address without a redirect.
Keep certificate verification enabled. Set this file's permissions to 600
and keep it owned by the account running the job. Do not put its key in a
ticket, this README, or the visible scheduler command.

B. CPANEL INSTALLATION

Open cPanel > Advanced > Cron Jobs > Add New Cron Job.
Create each entry separately, with the following fields and command.

Job 1 - workflow SMS, every minute:
  Minute: *   Hour: *   Day: *   Month: *   Weekday: *
  Command (one line):
  cd '/PROJECT/PATH' && '/PHP/PATH/php' spark sms:process >> '/PROJECT/PATH/writable/logs/sms-cron.log' 2>&1

Job 2 - general tasks, every five minutes:
  Minute: */5   Hour: *   Day: *   Month: *   Weekday: *
  Command (one line):
  /usr/bin/curl --config '/PROJECT/PATH/writable/cron-http.conf' >> '/PROJECT/PATH/writable/logs/general-cron.log' 2>&1

Click Add New Cron Job for each. Do not paste the five schedule fields into
cPanel's Command box; they belong in its separate scheduling fields.

C. SSH ALTERNATIVE - USE THIS OR CPANEL, NOT BOTH

If there is no hosting panel, the administrator can use crontab -e under
the hosting account. Preserve existing entries and append these two lines
after replacing all paths. These are USER crontab entries, without a username:

* * * * * cd '/PROJECT/PATH' && '/PHP/PATH/php' spark sms:process >> '/PROJECT/PATH/writable/logs/sms-cron.log' 2>&1
*/5 * * * * /usr/bin/curl --config '/PROJECT/PATH/writable/cron-http.conf' >> '/PROJECT/PATH/writable/logs/general-cron.log' 2>&1

D. ACTIVATION AND CONFIRMATION

[ ] Enable these jobs at cutover, after reviewing due work and integration
    settings. The general job performs real scheduled tender transitions,
    expired opening-session cleanup and other configured reminders/tasks;
    it is not a harmless connectivity test. It also processes workflow SMS.
[ ] Disable matching jobs on the old server so only the new installation
    runs background work for the migrated application/database.
[ ] Confirm Settings > Cron Job > Last run advances and review both logs.
    The SMS command processes up to 20 queued records per run. A processed
    count is not proof of delivery: inspect SMS statuses and receipt too.
[ ] Configure log rotation and check scheduling continues after reboot.

Opening the cron URL in a browser does not execute the general job. HTTP
execution requires POST and the configured authentication key.


8. UPLOADS AND EXISTING DOCUMENTS

[ ] Check an existing public logo and an existing authorized private document.
[ ] Upload a new PDF and image through the application, then preview/download
    them as the correct user. Verify another user cannot read private files.
[ ] Preserve the current corrected .htaccess rules. The previous logo 403
    came from an inherited routing/access rule, not a missing chmod setting.
[ ] Check the effective upload-scanning configuration. The application has
    a scanner hook; the reviewed project did not include a scanner adapter.
    UPLOAD_MALWARE_SCAN_FAIL_CLOSED=true rejects uploads unless a scanner
    returns a clean result. Do not copy the sample .env and assume scanning
    is installed. Preserve the existing policy during migration and have IT
    resolve the scanner integration separately if enforcement is required.

Files already missing from the source cannot be recovered by importing
database rows; report such files separately rather than changing permissions.


9. CUTOVER AND APPLICATION CHECKS

[ ] During the agreed maintenance window, pause old-site writes, take the
    final matching database/upload snapshot, and restore/sync it to the new
    server. Activate the new hostname and avoid two independently writable
    copies of the portal.
[ ] Verify sign-in, guest vendor and guest gate-pass pages over HTTPS.
[ ] Verify existing admin login and vendor login by email and by CR number.
    Test multi-CR selection and the correct company's portal permissions.
[ ] Verify users, roles, fees, balances/payment records, uploaded files and
    existing applications survived the import. Preserve unpaid/pending
    payment states; never mark them paid merely to finish migration.
[ ] Check representative vendor, gate-pass, PTW and tender workflows and
    their master/accounting pages with the proper roles.
[ ] When bank access is ready, complete an owner-approved payment in the
    agreed environment: success, cancellation/failure, return capture,
    independent bank verification and correct workflow continuation.
    Check the readable accounting record identifies payer, purpose, amount,
    bank reference and status. Use test cards only in UAT.
[ ] When SMS access is ready, check a new workflow notification and an OTP
    login to the intended customer's mobile. Keep unresolved checks marked
    pending. Do not claim payment/SMS success from configuration alone.
[ ] Confirm reCAPTCHA remains inactive, jobs run and application logs show
    no new deployment failures. No SMTP test is included by owner request.

The previous live smoke test did not complete every paid workflow or the
full tender bid-to-award process. Bank/SMS provider verification remains
pending; copying the application does not complete that acceptance work.

If rollback is necessary, stop new-site writes and jobs first, reconcile any
transactions received after cutover, and restore/reroute to one consistent
application/database/file set. Do not blindly overwrite new transactions
with the old backup. Keep the previous backup until handover is accepted.


10. HOSTING TEAM HANDOVER

[ ] Provide final domain, application/PHP paths and actual outgoing public IP.
[ ] Confirm HTTPS/routing, PHP extensions, database restore and upload access.
[ ] Confirm both job schedules and provide their last successful run times.
[ ] Confirm bank/SMS network/provider status and list remaining actions.
[ ] Confirm SMTP settings were preserved without sending a test message.
[ ] Record completed application checks and any failures still outstanding.
[ ] Keep passwords and keys in private deployment configuration, not this file.

Application Settings > Cron Job also displays the job commands for the
deployed project location; the PHP executable path still needs confirmation.
cPanel reference: https://docs.cpanel.net/cpanel/advanced/cron-jobs/

END OF README
