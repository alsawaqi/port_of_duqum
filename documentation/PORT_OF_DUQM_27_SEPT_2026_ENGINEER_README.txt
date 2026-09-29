PORT OF DUQM - ENGINEER HANDOVER
27 September 2026 - CONSOLIDATED APPLICATION FIXES AND SQL REPAIR

START HERE
This single package combines the September 27 log/stability fixes, the follow-up
R2 fixes, and the latest Oman plate-code update. It includes required earlier
supporting files so the engineer does not need to combine separate archives.
Use THIS package instead of dd/changes.zip, the original stability ZIP, R2 ZIP
and the separate plate-code ZIP. Do not upload those older archives afterward.

This is an UPDATE to the existing PHP 8.3 application, not a complete application
or fresh database installer. It contains 93 application/runtime files.
No production upload or database change was performed while preparing it.

PACKAGE CONTENTS
  upload/                  Files to merge into the EXISTING application root.
  database/01_REPAIR.sql    The database repair to run first.
  database/02_VERIFY.sql   Read-only schema check to run after the repair.
  documentation/           Change summary, exact upload list, tests, cron guide,
                           plate-code reference and review of changes.zip.
  SHA256SUMS.txt            Checksums of all the other files in this package.

ONLY UPLOAD THE CONTENTS OF upload/
Example mappings if the application is /var/www/html:
  upload/app/Controllers/Gate_pass_portal.php
    -> /var/www/html/app/Controllers/Gate_pass_portal.php
  upload/app/Helpers/general_helper.php
    -> /var/www/html/app/Helpers/general_helper.php
  upload/assets/css/portal-shared.css
    -> /var/www/html/assets/css/portal-shared.css
  upload/index.php -> /var/www/html/index.php
  upload/server-check.php -> /var/www/html/server-check.php

Merge folders and replace the listed files. Do NOT delete or replace the whole
existing app or assets folders. Do NOT create /var/www/html/upload/ by mistake.
Keep database/ and documentation/ outside the public web root; they are engineer
handover files, not application files to upload. Extract the ZIP on a workstation
or into a private staging directory before following these steps.

INSTALLATION ORDER
1. Confirm the target is the intended production installation using PHP 8.3.
   Back up the full existing application, uploads and database. Arrange a short
   maintenance window; pause existing application scheduled jobs during it.

2. In MySQL Workbench, select the EXISTING application database as the default
   schema. Open database/01_REPAIR.sql and run the WHOLE script.
   The first result, selected_database, must identify that intended database.
   These scripts assume the existing pod_ table prefix. No migration command is
   needed. If the prefix differs, stop for review instead of blindly running it.

   This is the SAME SQL repair supplied earlier on September 27, not a new or
   competing repair. It is safe to rerun on the intended supported schema. If it
   was already applied successfully, proceed to the verification in step 3.

   It creates the missing Gate Pass notification outbox and PTW applicant table,
   adds missing login/OTP and tariff-support columns, enables the waiver-rejection
   decision, and makes the two optional PTW reference columns nullable.
   It does not delete users, requests, payment records, documents or audit history.
   It does NOT reset official tariff rules or change fees. Do not run a fee-reset
   or unrelated historical SQL file as part of this handover.
   MySQL schema changes are not all rolled back if a later statement fails.
   If any SQL statement fails, retain the error and resolve it before continuing.

3. Run the WHOLE database/02_VERIFY.sql against that same database.
   Expected results:
   - selected_database is the intended application database.
   - The missing_columns result set is EMPTY (no listed columns are missing).
   - Both PTW reference fields report IS_NULLABLE = YES.
   - The approval decision supports fee_waiver_rejected.
   - Payment/outbox index results are present.
   - Review the displayed active tariffs: the current calculator uses flat,
     per-person duration bands and separate induction amounts. This script only
     reports tariff data; it does not replace the configured prices.
   If other missing tables/columns remain, stop and report the output. The repair
   handles the reported deployment gaps, not every possible historical schema.

4. Upload every file under upload/ to its matching location in the application
   root, preserving filename case. The list is in documentation/FILES_TO_UPLOAD.txt.
   The production .env, app/Config/Database.php, .htaccess, system framework,
   provider keys, uploaded documents and existing cron configuration are excluded.
   Keep those destination-specific files and settings in place. Do not copy any
   localhost credentials, test database, test users or test uploads.

5. Ensure the website PHP account can write writable/logs, its session/cache
   folders, writable/cache/pdf and the existing upload/temp folders. Preserve
   normal server ownership and permissions; do not apply chmod 777.
   Confirm required PHP extensions, including zlib for QR PNG generation.
   Clear/reload PHP OPcache through the host's normal deployment procedure if
   updated files are not picked up automatically. Refresh and sign in again.

   Optional read-only diagnostics from the application root:
     /usr/bin/php8.3 server-check.php
     /usr/bin/php8.3 server-check.php --database
   Verify the executable path first. These checks do not send SMS/email or make
   bank requests. server-check.php is a CLI-only diagnostic tool, not a required
   runtime dependency. index.php is the application entry file and includes the
   improved error logging. Both supplied files should be uploaded with this set.

6. Resume existing scheduled jobs. If they are absent, have the server
   administrator install the two entries in documentation/CRON_SETUP.txt.
   FileZilla uploads alone do NOT install scheduled jobs. Avoid duplicate entries.
   Review pending notifications before enabling a previously stopped queue.
   The owner has confirmed SMS and bank connectivity works after whitelisting;
   this update does not change those credentials or enable/disable OTP.

7. Before ending maintenance, use approved test records to check:
   - Sign-in and the configured SMS/email OTP method; do not disable OTP to bypass
     a deployment failure. Older sessions may need a fresh sign-in.
   - Gate Pass: save a draft, visitor/vehicle and document; submit; department and
     Commercial review; Security and ROP; approved fee waiver/payment conditions;
     issuance; QR/PDF; entry/exit; rejection and returned-request handling.
   - Oman plate codes: check SS/YY and Arabic equivalents in the customer and
     Security vehicle forms. Save/reopen and check the selected code and digits.
   - Vendor registration/contact approval and portal access; PTW application,
     revisions/reviews and permit PDF; tender request/reviews/publication/bids,
     opening/evaluation and award using the site's permitted test procedures.
   - Check writable/logs and the hosting PHP log for new errors. Confirm workflow
     notifications are processed and tender scheduled transitions run.
   The local QA report records what was already tested and the remaining limits.

ROLLBACK
Keep matching application and database backups. If deployment fails, keep the
site in maintenance and restore the matching pre-deployment state with hosting
support. Do not restore an old database over newer user transactions. Do not
attempt ad-hoc column/table removal as a shortcut rollback.
