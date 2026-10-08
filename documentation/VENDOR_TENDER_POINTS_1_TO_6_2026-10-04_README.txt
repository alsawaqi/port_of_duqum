PORT OF DUQM - VENDOR AND TENDER UPDATE, POINTS 1-6
Prepared: 4 October 2026 (Asia/Muscat)

START HERE
This is an update package for the existing, already-working Port of Duqm
application. It is NOT a complete project or a replacement database.
It contains all current changes for the six agreed vendor/tender points.
It does not replace the earlier gate pass/PTW repair package.

CONTENTS
- UPLOAD/app/: 44 application PHP files. Upload ONLY the contents of UPLOAD
  into the existing project root, preserving every folder and filename.
- DATABASE/: two schema/data updates (01 and 02), plus two READ-ONLY checks
  (00 and 03). Run the SQL manually in Workbench/phpMyAdmin. No migration
  command is needed. These scripts use the pod_ table prefix.
- FILE_LIST.txt: exact upload paths and SHA256 hashes, including baseline
  hashes to help identify independent server customizations.
- SHA256SUMS.txt: checksums for the package contents.
- VERIFICATION/: developer tests and a concise record of acceptance checks.
  These are reference material, not files to upload to the public website.
- README_ENGINEER.txt: this guide. Give it to the engineer; keep it outside
  the public website. Do the same for DATABASE and the checksum files.

No .env, Database.php, bank/SMS credentials, SMTP settings, customer uploads,
logs, sessions, temporary test files, test accounts or database dump are
included. Existing server settings and user files must be preserved.

INSTALL IN THIS ORDER
1. Back up the destination database and application files being replaced.
   Keep backups outside the public web directory. Record the current server
   configuration. Arrange a short maintenance window so that no application
   writes or scheduled jobs overlap the file replacement.

2. Extract this ZIP to a staging folder OUTSIDE /var/www/html.
   On Linux, from inside the extracted package folder, verify:
       sha256sum -c SHA256SUMS.txt
   Every result must be OK. On Windows, the provided checksum list can also
   be checked with PowerShell Get-FileHash -Algorithm SHA256.

3. In MySQL Workbench/phpMyAdmin, SELECT THE CORRECT EXISTING APPLICATION
   DATABASE, then run DATABASE/00_CHECK_EXISTING_SCHEMA.sql.
   Check the displayed database name. All prerequisite rows must say OK.
   A missing or incompatible result means the destination lacks an earlier
   database update. Stop and compare that schema before installing; do not
   disable foreign keys, delete tables or import the local test database.
   This is a focused prerequisite check, not a complete schema comparison.

4. Run DATABASE/01_VENDOR_REGISTRATION.sql.
   This creates pod_vendor_registration_applications if absent. It enables
   an existing non-deleted RIYADHA/RIYADA document type, or inserts an active
   Riyadha Certificate type if none exists. It does not delete existing
   vendors, fee requests or payment history. It does not set group prices.

5. Run DATABASE/02_VENDOR_CODE.sql.
   This adds nullable pod_vendors.vendor_code VARCHAR(64) if absent. It does
   not assign codes or change existing vendor values.
   Both update scripts are repeatable if those points were already installed.

6. Run DATABASE/03_VERIFY_INSTALLATION.sql.
   Confirm the registration table exists, vendor_code is varchar(64) with
   nullable YES, and an active Riyadha type is present. Compare the displayed
   registration table definition with file 01, including its two unique keys.
   CREATE TABLE IF NOT EXISTS cannot repair an incompatible existing table.

7. Merge the CONTENTS of UPLOAD into the existing project root.
   Example mapping:
       UPLOAD/app/Controllers/Guest_vendor.php
       -> /var/www/html/app/Controllers/Guest_vendor.php
   Add the new files as well as replacing existing files. FileZilla is fine.
   Linux option, run from the extracted package directory:
       rsync -av --no-owner --no-group --no-perms UPLOAD/ /var/www/html/
   Do not use --delete. Use the actual project root if it differs. Compare
   any independent server customizations with FILE_LIST.txt before replacing
   them. Do not copy the outer package directory into the public website.

8. Ensure the web server can read the new PHP files. Preserve the application's
   existing file ownership/permissions and write access to writable/uploads,
   writable/cache, writable/logs and writable/session. Do not use chmod 777.
   Reload the application's PHP-FPM/Apache worker if required to clear OPcache
   so that all requests use the updated files. Re-enable the existing scheduled
   jobs and normal access after the checks below. No new cron job is introduced.

SERVER CONFIGURATION
- This update has no new required .env entry and includes no config replacement.
  Keep production database credentials, domain/base URL and payment callback
  URL unchanged if they already work on this server.
- Preserve the existing Bank Muscat merchant/access/working keys and the
  correct bank-approved UAT or production environment. Do not copy local keys.
- Preserve existing SMS/OTP and database-backed Email/SMTP settings. Keep the
  configured authentication policy. QA did NOT disable production OTP.
- Preserve AUTH_SECURITY_MFA_HMAC_KEY and the existing, DIFFERENT tender
  opening secrets TENDER_OPENING_CODE_HMAC_KEY and
  TENDER_OPENING_CODE_ENCRYPTION_KEY. These protect the three opening codes.
  If either tender secret is missing, it must be configured before using
  three-key opening (each must contain at least 32 bytes of strong random
  material). Do not overwrite existing secrets during this update.
- Leave TENDER_TESTING_STAGE_ENABLED unset or false in normal operation.
  The testing shortcut is disabled in production regardless of this value.
- Keep the existing scheduler/reconciliation jobs running. They are still
  needed for queued notifications and the existing background workflows.
  This package does not install or change the hosting scheduler.

APPLICATION SETTINGS TO REVIEW
- Vendors Master > Vendor Groups: confirm each active group's registration
  fee in OMR and positive validity in days. Zero means waiver review; positive
  means payment. Existing fee effective dates remain respected.
- Vendor Document Types: configure active required types for each group.
  Types assigned to All vendor groups also apply. Ensure RIYADHA/RIYADA is
  active and applicable to each waived group. The SQL seeds the type but
  does not replace your group/document policy.
- Staff roles and company assignments: confirm procurement, procurement
  manager, technical, commercial and all three opening committee roles are
  assigned to the correct company. Different committee members are required.
- The previously hidden Vendor Registration Application sign-in button stays
  hidden. The direct /index.php/guest_vendor page remains available.

EXPECTED BEHAVIOR AFTER THIS UPDATE
1. Registration: paid groups remain restricted until independently verified
   settlement; zero-fee groups require Riyadha and staff waiver approval.
   Staff may reject the waiver and request a specific positive fee with a
   reason. Pending users may sign in for status/payment only for that CR.
2. Documents: choosing a group shows its required upload rows automatically.
   Required document types cannot be changed by applicants. Optional/additional
   applicable documents remain available. Server validation enforces the rules.
3. Vendor Code: authorized staff can assign/edit an optional code after the
   company is fully registered. Each CR has its own code.
4. Login: registered email or CR plus the person's password is retained.
   Shared-email contacts with several authorized CRs choose a company; direct
   CR login selects that company. OTP, when enabled, still precedes access.
5. Closed tender audience: chosen filters ALL match together. Individually
   selected vendors are added as extra invitees. A shared login does not make
   one CR's invitation accessible to another CR.
6. Tender workflow: draft -> Submit for Manager Approval -> manager decision
   -> procurement Publish -> eligible bids -> three-key opening/signatures
   -> technical/commercial evaluation -> final award. Pending late evaluations
   require review. Invalid dates/teams and repeated final actions are rejected.
   Published changes/documents await manager approval before becoming visible.
   Clarifications and attachments go through procurement. Internal team replies
   stay internal until procurement shares them with the vendor or all vendors.
   Pre-bid enquiries can also receive a reply before the vendor decides to bid.
   Actual bidders receive printable award/regret letters in their portals.
   Procurement can see result-email delivery status and retry failed messages.

PRODUCTION ACCEPTANCE - DO BEFORE HANDOVER
Use designated test users and a bank-approved UAT transaction when testing
payments. If this domain has production-only bank credentials, coordinate an
approved production test; do not assume a UAT card can be used in production.
- Register a paid vendor with required documents; complete checkout and return
  from the bank. Confirm the exact fee, verified payment/accounting record,
  approved registration, readable documents and full vendor access.
- Test a cancelled/failed payment: registration must remain restricted. Retrying
  or revisiting a successful return must not create a second activation/fee.
- Register a waived vendor with Riyadha. Check pending access, waiver approval,
  and a separate waiver changed to a custom payable fee.
- Assign a Vendor Code; sign in by email and CR, including a multi-CR account.
  Confirm real OTP delivery using the user's registered destination.
- Create a tender with the correct dates and staff. Submit as procurement and
  approve as a separate manager. Publish and confirm the closed audience.
- Submit bids with documents, exchange private/public/internal clarifications
  and attachments, perform opening/evaluations, then award one bidder. Check
  the winner letter, unsuccessful bidder regret letter and private access.
- Confirm actual SMTP/email and SMS delivery, delivery status and retry behavior
  from this whitelisted server. A stored portal letter does not prove email
  reached the recipient's inbox. Review writable/logs/log-YYYY-MM-DD.php and
  the web-server/PHP error log for new errors during acceptance.

WHAT WAS VERIFIED LOCALLY
See VERIFICATION/ACCEPTANCE_RESULTS.txt for counts and limits. The combined
run used newly registered vendors through the final tender award, not only
pre-existing approved vendor fixtures. SQL was checked on a disposable schema
both for first installation and repeat execution. No application-code changes
were necessary during this final combined test.
Actual bank confirmation was SIMULATED in the isolated database. No real card
charge, provider SMS or SMTP delivery was performed. Production credentials,
network access, permissions and live services therefore need the acceptance
checks above. Local success is not a guarantee for every production scenario.

IF A DEPLOYMENT PROBLEM OCCURS
Keep the site restricted and stop new writes while diagnosing. Preserve error
logs. Restore the backed-up application files as one set; do not mix versions.
The SQL is additive: do not drop the registration table or vendor_code to undo
an upload. Keep new records. Older code does not enforce the new pending
registration gate, so leave access restricted if rolling back after registrations
have started. Database restoration requires reconciling any writes made after
backup; do not overwrite live payments/registrations with an old dump.
