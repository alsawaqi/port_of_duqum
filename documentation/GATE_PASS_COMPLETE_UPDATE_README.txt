PORT OF DUQM - GATE PASS COMPLETE UPDATE
20 September 2026

This is an update to the existing application, not a complete application ZIP.
It contains 47 application files. It includes the current Gate Pass discussion
fixes AND the previously prepared tariff, shared-account registration, and
per-user OTP delivery changes. Use this package instead of overlaying the
older September 19/20 packages afterwards: they share files.
The HSSE instructions/PDF redesign is deferred, as requested.

WHAT IS FIXED
1. A blocked visitor cannot be added, approved, admitted using an old QR,
   or have their active pass QR/PDF downloaded. Checks use both the visitor
   record and the central blocked-ID registry. Another unblocked visitor's
   individually assigned pass remains available. A person already inside
   can still be recorded as exiting.
2. Visitor ID type/number and ID attachment are clearly mandatory. Existing
   uploaded ID/Mulkiyah files remain valid on edit; users need not re-upload
   them. International vehicle country/plate and Mulkiyah are clearly marked.
3. Submitting to Department queues email to BOTH the requester and active
   reviewers assigned to that exact company and department. Sending is
   attempted after the request commits. Blocking queues a requester email
   and an SMS to the affected visitor's saved mobile number.
4. All department-approved requests still go to Commercial. The action now
   says "Review fee" and waiver labels explain the actual state. Approval
   history and request status save together; blocked refusals do not leave
   misleading approval records. Approving a department waiver is atomic too.
5. Gate Pass Master > Filter Requests includes waiver states and counts:
   no waiver requested, awaiting review, approved, rejected. Counts apply to
   the form filters, before the waiver selector; table text search does not
   change these counts. Each request is counted once.
6. A block after checkout creation prevents bank handoff. If the bank has
   already received payment, paid evidence is retained for Accounting review
   and does not approve the blocked request. Rechecking the same payment
   after the block is resolved can apply it without charging again.
7. English/Arabic language loading now includes all the recently added
   registration and OTP labels as well as the new Gate Pass labels.

INSTALLATION - IT TEAM
1. Back up the destination database and matching application files. Use a
   maintenance window so users cannot submit while SQL/files are being changed.
2. In MySQL Workbench select the DESTINATION application database. The scripts
   use the pod_ table prefix. No CodeIgniter migration command is required.
3. Check/install these SQL files in this order:
   a) GATE_PASS_FEE_WAIVER_FIX.sql - needed if approvals.decision does not yet
      contain fee_waiver_rejected. Read its existing-enum warning: retain any
      extra enum values your server has. Skip if already installed.
   b) GATE_PASS_PER_PERSON_TARIFF.sql - needed if the September 19 tariff update
      is not installed. Adds requests.fee_breakdown and rules.induction_amount
      and installs the approved six tariff bands. Rerunning RESTORES those
      rates and deactivates other OMR rules; do not rerun unnecessarily if
      administrators have intentionally changed rates. Existing requests and
      bank transactions are never repriced by this SQL.
   c) USER_LOGIN_OTP_CHANNEL.sql - adds users.otp_delivery_channel if missing.
      Safe to rerun; existing account selections are retained.
   d) GATE_PASS_DISCUSSION_FIX.sql - NEW for this update. Creates
      pod_gate_pass_notification_outbox. Safe to rerun, preserving history.
      Install this before allowing new submissions with the updated PHP files.
4. Upload/merge the ZIP's app/ folder into the CURRENT application root,
   preserving every relative path and replacing matching files. Do not delete
   the destination app folder. For /var/www/html this means app/ remains
   /var/www/html/app/. Do not nest a second project folder accidentally.
5. No .env, database credentials, SMTP credentials or SMS credentials are
   supplied or replaced in this package. Retain the destination configuration.
   Files under documentation/ are for the IT team, not required in the web root.
6. Ask the host to reload PHP/clear OPcache if it does not detect changed files.
   Keep writable/ writable by the web/cron user. Do not use chmod 777.
7. Keep the EXISTING scheduled notification worker running. The updated
   sms:process command also processes these Gate Pass email/visitor notices.
   The existing authenticated /cron endpoint also processes them. No third
   cron job is required. Use the already prepared scheduler installation
   instructions for your server; this package does not install server cron.
8. Check the acceptance steps below before reopening normal access.

SETTINGS AND DELIVERY
- Email uses Settings > Email and the current SMTP settings. Department
  reviewers need active assignments and valid account email addresses.
- Block notices use pod_gate_pass_request_visitors.phone for the visitor;
  requester email comes from pod_users.email. Oman numbers are normalized;
  the iSmartSMS gateway sends the number without the leading plus sign.
- SMS requires SMS notifications, Gate Pass notifications, and live sending
  enabled, plus working credentials/network access. Old preview records are
  not released when live sending is enabled later.
- The existing per-user OTP selection controls LOGIN codes only, not these
  business notification channels. Global login OTP must be enabled to enforce it.
- Delivery failures never undo an already saved request. Outbox status retains
  sent, failed, unknown, disabled, dry_run, invalid_destination,
  recipient_changed or expired. A queued record older than 24 hours expires.
  A worker interrupted while sending becomes unknown after ten minutes.
  Sent SMS means provider acceptance, not confirmation of handset delivery.
- Failed/unknown records are NOT retried automatically because the gateway
  has no idempotency key. IT should inspect the provider before any manual
  resend. There is no new notification-history UI in this patch.
- IT can inspect delivery history (read only):
  SELECT id, request_id, visitor_id, recipient_user_id, channel, status,
         created_at, claimed_at, processed_at
  FROM pod_gate_pass_notification_outbox ORDER BY id DESC LIMIT 100;
  Delivery/queue problems are also logged under writable/logs/.

ACCEPTANCE ON THE DESTINATION SERVER
1. Submit a test request with a valid visitor/document. Confirm the requester
   and assigned department reviewer receive email; unrelated departments must
   not receive it. Verify SMTP from this server, not an external workstation.
2. Department approve once without waiver and once requesting a waiver. Both
   must appear in Commercial. Approve/reject the waiver and inspect status,
   amount, history and the four waiver filter counts.
3. Block a test visitor. Verify a new visitor entry with that same ID is refused,
   request approval is refused, QR/PDF downloads disappear and direct download
   URLs refuse access. An already printed QR must refuse ENTRY when scanned.
4. Confirm requester email and visitor SMS arrive via the scheduled worker.
   Check disabled/preview behavior with synthetic accounts if required.
5. Resolve the block and verify the allowed workflow can continue. Do not create
   a second bank payment when Accounting already shows money received.
6. Check both languages and the visitor/vehicle forms at desktop/mobile sizes.

VALIDATION PERFORMED LOCALLY
117 existing standalone test scripts passed. Six focused database test scripts
passed 420 checks in total, including 81 new discussion checks, 166 tariff
checks, 47 shared-registration checks, 72 OTP-delivery checks, 27 SMS-settings
checks and 27 visitor-identity query checks. PHP lint passed for all 47 files.
Browser tests covered actual routes, waiver filtering/counts, the fee button,
mandatory form controls, international vehicle selection, blocked downloads,
English/Arabic and a 390px mobile viewport. See the QA report for details.
Mail/SMS transports were captured or disabled. Dummy bank keys were used for
local payment tests; no bank transaction was sent. This is not a live server
acceptance test and does not prove the destination network/SMTP works.
Test runtime: PHP 8.2.12 + local MySQL/MariaDB + Chrome. PHP 8.1/8.3 and the
new host must receive the acceptance check above. No external libraries or
framework version changes are included.

STATUS
Prepared and tested locally. Not uploaded to FileZilla or the live server.
