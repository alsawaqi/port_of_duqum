PORT OF DUQM - ACCOUNT, ARCHIVE AND FORM FIXES - 29 SEPTEMBER 2026

This is an incremental update to the current project, including the earlier
27 September module fixes. It is not a complete application or database dump.
No production upload has been performed for this update.

INSTALLATION
1. Back up the current project and database. Pause web traffic and scheduled
   jobs while applying the database and matching application files.
2. In MySQL Workbench/phpMyAdmin, select the EXISTING application database.
   Run database/SEPT29_SOFT_DELETE_REPAIR.sql in full. The table prefix is pod_.
   Stop if any statement fails; do not ignore database errors.
   This is plain SQL: no CodeIgniter migration command is required.
3. Copy the CONTENTS of upload/ into the existing application root, preserving
   the paths and replacing matching files. Include index.php and the new
   assets/js/portal-form-navigation.js and app/Libraries/Soft_delete.php.
   Do not create an extra upload/ directory inside the application.
4. Keep the server's .env, database configuration, credentials, uploaded files
   and writable directory. None is included or replaced by this package.
5. Refresh PHP's opcode cache through the normal hosting/service mechanism if
   necessary. Hard-refresh the browser to load the new JavaScript.
6. Resume traffic/jobs and perform the checks below with disposable test data.

DATABASE CHANGES
- Creates pod_soft_delete_items, which records exactly which rows each archive
  operation affected. Undo can then avoid reviving previously deleted children.
- Adds the generated _live_row discriminator and adjusts ordinary unique
  indexes in the selected master/account/relation tables. Active duplicates
  remain forbidden, but multiple deleted copies no longer reserve the value.
- Keeps existing generated CR/contact identities and payment/QR identifiers.
- Does not change passwords, mobile numbers, tariffs or bank transactions.
- Requires support for stored generated columns (MySQL 5.7+/8.x or MariaDB
  10.2+). Verified on the isolated local MariaDB database; run first on a backup
  of the destination database. ALTER statements are not one atomic transaction.
- The script is safe to run again. Back up before running it. Do not drop the
  journal after deployment; it is required for reliable restoration.

WHAT CHANGED
- Password fields in operational assignment edits and account settings are
  disabled until Change password is selected. Creating a new account still
  requires a password. Blank/omitted passwords preserve the existing hash.
- An unchecked Disable login box now saves 0 instead of NULL in staff and
  client account settings. This fixes a real strict-database save failure.
- Archive/delete cascades through the owned records of vendor, gate pass,
  PTW and tender aggregates, including access assignments and linked details.
  It is transactional. Used shared master records are refused with a clear
  message rather than deleting other customers' records.
- Removing one gate-pass registration or vendor relationship preserves the
  shared global user and their other CR/module memberships.
- Undo refuses a collision with an active unique value or a deleted parent;
  it restores only children archived by that operation.
- Historical bank ledger, fee-request and scan/audit records without a deleted
  column remain historical records. Uploaded file bytes are not removed.
  This update does not guess or rewrite historical deletion relationships made
  before the archive journal existed. Old roots without a journal restore only
  the root; historical orphan data requires a separate data review.
- Tender invitation updates retain archived invitation history instead of
  physically deleting old copies to work around the previous unique index.
- Legal-type duplicates such as LLC/SAOC return a readable validation message.
- Next/Previous moves to the displayed step in PTW, tender and the shared
  staff/import wizards. Invalid fields receive appropriate scroll/focus.
- CSRF tokens are provided even when the old csrf_protection setting is absent
  or off, matching the protection already enabled in the server filters.
  Same-origin AJAX includes the token header for JSON/multipart requests too.
  CSRF is NOT disabled. Expired forms need a refresh, not an automatic retry.
- Expected 404/403 exceptions retain their HTTP status instead of becoming 500.
  AJAX errors return a clear message. Diagnostics omit query strings and error
  messages that could contain credentials; they include route, class and line.
- Default SMS-OTP users are checked for a valid registered mobile during edits.
  Login gives a clear missing-destination message after password verification,
  and the private configuration log identifies the affected user ID.

EXISTING OTP ACCOUNTS STILL NEED REAL CONTACT DETAILS
The log's missing-destination errors are account data problems. No telephone
numbers were invented or replaced. Correct affected users' registered mobiles,
or select email OTP when the server's SMTP configuration is working. OTP is not
bypassed and credentials/provider settings are unchanged.

VERIFICATION
- Full existing PHP test run plus additions: 137/137 test scripts passed on
  PHP 8.3. Relevant regressions rerun after the last dependency/editor fixes.
- Archive lifecycle: 67 database checks, including cascading four modules,
  shared identities, repeated value reuse, restore conflicts and journal rerun.
- Account/OTP delivery: 87 database checks, including both real staff/client
  account-save controllers, missing checkbox/password fields, preserved hash
  and session version, OTP selection and missing-default-SMS mobile refusals.
- HTTP entry-point errors: 23 checks for real framework 404/403/500 exceptions,
  text/JSON responses, refresh instructions and safe logging.
- Desktop (1440x900) and mobile (390x844) Chrome browser checks passed for user
  editing, original-password login after edit, PTW/tender navigation, Team
  account editing, staff Next and CSRF JSON/multipart submission. No application
  JavaScript errors occurred in those flows. Browser plugin was not available;
  regular Playwright was used against isolated localhost QA.
- PHP syntax checks and JavaScript syntax check passed. The tests used a
  disposable local database; no external SMS, SMTP or bank request was sent.

EXISTING TEST EXPECTATIONS INTENTIONALLY UPDATED
- ModuleStabilityDatabaseTest: old 'archived code reserved' -> new archived
  code reusable; active duplicate/restore conflicts still refused. Added undo
  after freeing the value and repeated archive checks.
- PtwApplicantAuthorizationTest: old removal kept deleted=0 and inactive ->
  new removal uses deleted=1 and inactive, with a readable failure response.
  The active-only unique index now allows adding the assignment again.
- GatePassTariffDatabaseTest: archive journal added to the isolated schema.
  All 166 tariff/payment-lock checks remain unchanged and pass.
- IntegrationToolsDatabaseTest/SmsWorkflowDatabaseTest: generated _live_row
  omitted when cloning fixture users. Assertions were not weakened.
- UserOtpDeliveryDatabaseTest: added default-SMS contact checks and real account
  controller checks; existing OTP and permission assertions retained.

POST-UPLOAD ACCEPTANCE
1. Create a disposable operational user with a password. Edit name/phone with
   Change password unchecked. Confirm save succeeds and the old password works.
2. Edit a staff/client account with Disable login unchecked; confirm save works.
3. Add an unused test legal code; delete it; create the code again. An active
   duplicate must fail. Undo of the first row must fail until the value is free.
4. Delete a disposable parent with owned children; confirm active lists exclude
   them. Where Undo is offered, confirm it restores only that deletion's rows.
5. Verify a shared vendor/gate-pass account retains its other registrations when
   one relationship is removed.
6. Use Next in PTW/tender on desktop and mobile. Confirm the new step is visible
   from the top (or the natural end-of-page limit for shorter steps).
7. Refresh any old browser tabs before submitting forms. Review writable/logs
   and the server PHP error log if a request fails.

DEFERRED / LIMITS
- The unknown broken-page URLs in the supplied logs were left aside at the
  user's request. Correct 404 handling does not repair an unidentified link.
- The separate tender-manager approval/bypass question remains deferred.
- No production end-to-end test, real bank payment, SMS delivery or SMTP test
  was performed in this change. The checks above are local regression evidence,
  not a guarantee against every possible production-data or hosting issue.
- On rollback, restore matching code AND the pre-deployment database backup
  during maintenance. Old global unique indexes may not be re-creatable once
  archived values have been reused. Never resolve this by deleting history.

FILE_LIST.txt lists upload paths. SHA256SUMS.txt verifies package file bytes.
Keep this README, database/ and tests/ with the engineer; only upload/ contents
belong in the web project. Review evidence under tests/ is optional for staging.
