PORT OF DUQM - GATE PASS DEPARTMENT EMAIL
04 October 2026

WHEN IT SENDS
When a requester submits a completed gate pass for department approval, the assigned department reviewers receive the new branded email. Saving or editing a draft does not send it. Existing department resubmission notifications also use this template. The requester's separate submission confirmation is retained.

RECIPIENTS
Recipients come from Gate Pass Master > Gate Pass Department Users: active assignments matching BOTH the request's company and department. Accounts must be active, not deleted and not disabled, with a valid email address. The email does not go to all administrators automatically; administrators who should review a department's requests should have the corresponding department assignment. A removed assignment or changed recipient email is checked again before dispatch.

EMAIL CONTENT
Port of Duqm logo; a greeting naming the department; requester name; saved request reference; company; department; purpose; visit dates; and a View dashboard button opening the existing gate_pass_department_requests page. The link and logo use the application's configured domain. The design uses a white card and light grey outer background, with blue and gold brand accents. Names and other user-entered values are HTML-escaped. No identity documents, passport numbers or payment details are included in this email.

INSTALLATION
Back up these four files and upload every file under UPLOAD into the existing project root, retaining the paths. No database or .env changes are required for this update. The existing gate_pass_notification_outbox table from the earlier gate pass release must already be installed.
Use the application's current Settings > Email SMTP configuration. There are no new credentials or switches for this template.
The existing immediate after-submit dispatcher sends the email; the existing scheduled worker also processes queued notifications. No additional scheduled job is required. Existing status tracking, delivery failure logging and duplicate protection remain in place. This update does not automatically resend old notifications.
The email logo is served from the configured application URL. Email clients may initially block external images; the notification remains readable and usable without its logo.

LOCAL VERIFICATION
13 standalone template checks passed on PHP 8.3 and PHP 8.2.
86 gate pass database behavior checks passed, including drafts, submission transactions, rollback, recipient scope, HTML delivery, repeated processing, SMTP failure, revoked assignments and existing block/waiver/payment behavior.
208 existing module stability checks passed against an isolated local database.
Desktop (900px) and mobile (390px) browser previews passed: no horizontal overflow, loaded logo, correct dashboard link, no JavaScript errors or failed page assets. This is browser rendering verification, not a test in Outlook/Gmail inboxes.
All mail/SMS transports in database tests were injected; no real messages were sent. Production SMTP delivery was not retested.

FILES TO UPLOAD
app/Libraries/Gate_pass_notifications.php
app/Libraries/Gate_pass_email.php
app/Views/emails/gate_pass_department_review.php
assets/images/port-duqum-email-logo.png
