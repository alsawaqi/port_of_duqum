PORT OF DUQM - FOUR-MODULE STABILITY UPDATE
27 September 2026

This is a cumulative update for the existing application, not a complete installation.
It includes the current Gate Pass/OTP changes and the earlier September 13 fixes.
No production files or databases were changed during this review.

WHAT WAS FIXED
1. Gate Pass submission failed when pod_gate_pass_notification_outbox was missing.
   Install the supplied SQL. The code also checks readiness before writing, keeps
   the draft, and gives a clear message if setup is incomplete.
2. Gate Pass QR/PDF and immediate notification dispatch used an uninitialized
   database connection. The connection is now initialized. Authorization, blocked
   visitor checks, and audit rules remain enforced.
3. QR generation now works without GD/Imagick. PDF temporary images use the
   application's writable/cache/pdf directory instead of the host's upload temp path.
4. Add Member failed under strict SQL when language was omitted or hire date blank.
   New accounts inherit the portal language; an empty hire date is saved as NULL.
   Reading/saving employment details now restores the shared model to the users
   table before profile/login checks. This also fixes an auth_session_version
   error that could occur even when that column already existed in pod_users.
5. Duplicate country/region/city codes now produce readable validation. Deleted
   records still reserve their codes, consistent with the existing unique indexes.
6. The older database structure prevented PTW pages from initializing because two
   optional requirement references were NOT NULL. The SQL makes them nullable and
   creates the missing PTW applicant-assignment table without granting new access.
7. Gate Pass request/visitor options and hints now use the selected language.
   Known master labels are translated; custom names remain as entered.
   Visit calendar dates no longer gain a day from timezone conversion.

INSTALL IN THIS ORDER
1. Back up the destination database and application. Use a brief maintenance window.
2. In MySQL Workbench select the correct application database. Run:
      documentation/MODULE_STABILITY_REPAIR_2026-09-27.sql
   This uses the existing pod_ prefix. No migration command is required.
   It is safe to rerun. It does NOT delete requests, users, uploaded documents,
   audit history, payment records or fee rules, and does NOT reset official tariffs.
   It adds missing login/notification/tariff-support fields, the notification queue,
   the PTW applicant table, and the required waiver-decision enum value; it makes
   two PTW reference columns nullable. Review all SQL errors before continuing.
3. Run the read-only check:
      documentation/MODULE_SCHEMA_CHECK_2026-09-27.sql
   Check selected_database first. The missing_columns result must be EMPTY.
   Both listed PTW reference columns must show IS_NULLABLE = YES.
   The approval decision must support fee_waiver_rejected.
   The payment and notification index lists must be present.
   Review the final active-tariff list: the per-person rules should use flat rates
   and the agreed induction amounts. This repair does not replace old fee rules.
   If legacy daily/weekly/monthly rules remain, finish the previously approved
   tariff setup before testing charges; do not assume schema repair sets prices.
   If anything is missing, stop and retain the result for review. This repair is
   intentionally not a reconstruction of every historical application table.
4. Extract FOUR_MODULE_STABILITY_FIX_2026-09-27.zip into the EXISTING application
   root, preserving paths. Replace the included runtime files together.
   See MODULE_STABILITY_FILES_2026-09-27.txt for exact paths and SHA256.txt for hashes.
   The package excludes .env, app/Config/Database.php, .htaccess, uploaded files,
   provider credentials, local test databases and production logs. Keep the
   destination's own configuration and uploads.
5. Ensure the PHP service account can create/write writable/cache/pdf and can write
   writable/logs and the existing session/cache folders. Do not use chmod 777.
   Required PDF/QR extension: zlib. Retain the application's other PHP extensions.
   Reload PHP/clear OPcache using the hosting team's normal process, then refresh.
6. Sign in again. If a missing auth_session_version column was newly added, older
   sessions may require a fresh login. Do not disable OTP to work around this.
7. Retry a saved Gate Pass draft and verify department routing, fee, notification
   queue record and subsequent approval steps. Download an authorized issued pass
   as PNG and PDF. Verify blocked/unrelated visitors cannot use these endpoints.
8. Add a staff member with blank optional fields and the intended OTP method.
   Check the user list first: an earlier failed Add Member response may still have
   saved the account before failing to render its row. Avoid duplicate creation.
9. Check PTW applicant form/list and reviewer inboxes; vendor registration/contact
   login; tender request, participation and bid pages. Check the Arabic Gate Pass
   form, document download, and newly uploaded document access.
10. Separately confirm destination SMTP, SMS/OTP and bank UAT callbacks/accounting,
    plus the existing scheduled jobs. Local tests intercepted delivery; successful
    queueing is not evidence that an email/SMS reached its recipient. This update
    does not change SMTP/SMS/bank credentials or require a new scheduled job.

ROLLBACK
Keep the file/database backups from the same point in time. Database ALTER commands
are not rolled back by a later SQL error. Keep users out until the update and checks
finish. Do not restore an old database over new production transactions.

VALIDATION AND LIMITS
See the separate MODULE_STABILITY_QA_2026-09-27.md supplied with this update
for exact tests, results and evidence. Latest checks: 132/132 PHP scripts;
191 new database checks; 326 download checks; 49 pages; 75 PHP syntax checks.
The supplied logs and complaint screenshots were reviewed. Local tests used PHP
8.2 and PHP 8.3.33, strict SQL, and a disposable database with synthetic records.
The original XAMPP data directory was not repaired or overwritten.
This verifies the tested scenarios, not every production data combination,
concurrent request, permit subtype, network provider or server configuration.
