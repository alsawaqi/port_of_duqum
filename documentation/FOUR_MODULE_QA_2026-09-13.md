# Four-module QA and deployment handover — 13 September 2026

The covered local workflows pass after the fixes below. This is a regression and smoke-test result, not a guarantee that every possible workflow or the destination server is error-free. No files were uploaded through FileZilla in this testing round.

## Environment and isolation

PHP 8.3.33, local MariaDB 10.4.32, strict SQL enabled. Browser tests used headless Microsoft Edge and synthetic users in a separate, generated QA database. The starting structure was the supplied 211-table production export (MariaDB 10.11.19, September 8); missing current application structures were added to the QA database. This does not establish the current production schema. No real customer transactions were created. Mail was intercepted, SMS/OTP delivery disabled for local workflow testing, and no external bank requests were sent.

## Workflows actually exercised

| Module | Result and scope |
| --- | --- |
| Vendor | Admin creation with empty optional location/grade; block and unblock; guest registration with CR document and blank optional document dates; contact/document approval; owner and additional contact login; bank, branch, credential and contact updates followed by approval; specialty selection; registration payment initiation with a stored checkout record. Passed. The registration payment was not completed at the bank. |
| PTW | Registered Gate Pass applicant without a PTW reviewer assignment creates a seven-step application, attaches risk assessment, selects PPE, signs and submits; HSSE returns for revision; applicant resubmits; HSSE, HMO and Terminal approve. Final PDF returned successfully. Another registered user was denied access. Passed. |
| Gate Pass | Draft, visitor and ID attachment, duplicate request, submit, department return, resubmit, department waiver request, commercial waiver approval, security and ROP approval, issued pass, PDF and CSV export. Separate waiver rejection tested after migration. Passed. Physical entry/exit scanning was not performed in this round. |
| Tendering | Request creation with optional amount blank; department manager, finance and committee approvals; RFQ creation, procurement approval and publication; vendor participation approval; bid with technical/priced/unpriced proposal documents and item pricing; three committee codes and signatures; technical evaluation, commercial evaluation and final award. Passed using a zero-fee tender. |
| Linked screens | 61 admin/master/inbox/accounting pages loaded with HTTP 200 and no captured page errors. This checks page loading, not every button or business-rule combination. |

Tender deadlines were advanced in the isolated test database to exercise the actual workflow scheduler without waiting days. Committee users needed both assignment and the relevant action permissions; permission refusals were preserved. These checks do not verify installation of the production cron jobs.

## Errors found and corrected

1. Shared input cleaning changed SQL NULL into an empty string. Under strict SQL this broke nullable numeric/date fields, including vendor unblocking and Gate Pass visitor saving. Both cleaning modes now preserve NULL while retaining string XSS filtering. Regression tests cover nested values, zero and empty strings.
2. Blank previous estimated tender amount reached a decimal field incorrectly. It now remains NULL; zero is retained and invalid/negative input is rejected clearly.
3. Duplicating a Gate Pass did not supply the required initial reference. The duplicate now receives a provisional unique reference and its final reference is checked within the existing transaction.
4. The legacy Gate Pass timestamp backfill compared DATETIME directly with a zero date under strict SQL. It now compares a character representation, with real migration tests for normal, null and legacy zero dates.
5. The supplied approval-history enum could not store fee_waiver_rejected. A small additive migration preserves existing enum values and audit records. Waiver rejection now saves request and history together in a transaction. Before the migration, a deliberately failing rejection left the raw request row unchanged; after migration, rejection and readable history passed.
6. Development boot error display had drifted from the project's existing log-only behavior. It was restored so PHP diagnostics do not corrupt JSON responses. Error logging remains available.

The cumulative patch also includes the earlier September 13 portal fixes: Gate Pass strict timestamp handling, vendor registration feedback, language switch/fallback logo, and PTW applicant visibility/ownership controls. Reviewer/master authorization is preserved.

## Automated results

- 117 standalone PHP regression scripts passed. The initial run was 114 passed / 3 failed. Two failures concerned development error-display settings, fixed in the runtime config. The payment-view test needed its missing get_file_uri test helper.
- 214 real database checks passed: VendorLocation 114; IntegrationTools 52; GatePassVisitorIdentityFilter 27; StrictSqlSanitization 21.
- 144 relevant PHP files passed syntax validation.
- 61 module pages passed the page-load scan.
- Git diff whitespace check passed.

The 117 scripts include source-contract checks and are not 117 full browser workflows. The browser transactions and database tests provide additional execution coverage. Integration-tool provider responses are isolated/mocked; they are not evidence of live SMS delivery or bank settlement.

Evidence JSON and screenshots are in documentation/FOUR_MODULE_QA_2026-09-13/. No account passwords, environment files, bank keys, OTPs or payment tokens are included.

## Destination database readiness

A database change IS required if gate_pass_request_approvals.decision does not support fee_waiver_rejected. Use the narrowly scoped command below. Do not assume this is a files-only patch.

The older supplied export also lacked pod_eservice_payments, pod_eservice_payment_events, pod_sms_outbox and pod_vendor_fee_requests. Verify these against the CURRENT destination database before testing paid/notification flows; they may already have been installed since the export. The old export also lacked the current migration ledger. Do not import local test records or overwrite production tables to resolve this. Do not blindly run every historical migration.

## Deployment steps

1. Back up the destination application and database. Schedule a brief maintenance window.
2. Extract FOUR_MODULE_FIXES_2026-09-13.zip into the application root, preserving paths. This is a cumulative patch for the current application, not a complete installation. It includes 21 runtime files plus this handover and a checksum manifest.
3. Retain the destination .env, app/Config/Database.php, .htaccess and uploaded files; this archive does not replace them. Check the runtime file list below before upload.
4. From the application root, use the PHP 8.3 CLI with the application's database configuration:

   cd /var/www/html
   php scripts/gate-pass-waiver-schema.php --check
   php scripts/gate-pass-waiver-schema.php --apply
   php scripts/gate-pass-waiver-schema.php --check

   Use php8.3 instead of php if required by the server. Before applying, confirm the command is running in the intended application's folder and a backup exists. The command loads that application's configuration. --apply changes only the approval-history decision column, is safe to repeat, and does not run unrelated migrations or mark the entire migration history as applied. The migration file can also be managed through your normal migration process. Existing audit rows are retained.
5. Verify current payment/SMS/vendor-fee tables with the server administrator. This patch does not reconstruct all earlier application migrations.
6. Reload PHP/clear its opcode cache using the destination host's normal procedure, then refresh the browser. Keep production mode enabled. Check writable/logs and the web server/PHP log for new errors.
7. Repeat a short destination acceptance check: vendor registration/contact login, Gate Pass create/list/duplicate and waiver decision, PTW submission/approval, tender request/bid/evaluation. Confirm the language switch and PTW applicant menu under a registered external account.
8. Separately verify destination SMS delivery and OTP login, SMTP routing, scheduled jobs, upload readability, and a bank UAT checkout/callback/accounting record with the domain's approved settings. Those network/server checks were not certified by this local run.

## Limits and remaining acceptance

Not exhaustively tested: every PTW permit subtype and renewal/closure variation; every vendor renewal/payment outcome; every tender procurement/evaluation variation; concurrency/load; physical gate scanning; live SMS/OTP, SMTP, bank callback/settlement, and Linux host permissions/extensions. A successful integration test button alone does not prove every paid workflow. Do not describe the entire production system as error-free on the strength of this report.

## Runtime files in this cumulative patch

- `app/Controllers/Gate_pass_portal.php`
- `app/Controllers/Guest_vendor.php`
- `app/Controllers/Portal_account.php`
- `app/Models/Gate_pass_requests_model.php`
- `app/Views/includes/topbar.php`
- `app/Views/includes/left_menu.php`
- `app/Views/guest_vendor/index.php`
- `app/Language/english/custom_lang.php`
- `app/Language/arabic/custom_lang.php`
- `app/Language/arabic/default_lang.php`
- `assets/images/port-duqum-signin-logo.png`
- `assets/css/portal-shared.css`
- `app/Controllers/Ptw_portal.php`
- `app/Libraries/Left_menu.php`
- `app/Libraries/Clean_data.php`
- `app/Controllers/Tender_requests.php`
- `app/Controllers/Gate_pass_commercial_inbox.php`
- `app/Config/Boot/development.php`
- `app/Database/Migrations/2026_04_14_120000_add_created_at_to_gate_pass_requests.php`
- `app/Database/Migrations/2026_09_13_180000_gate_pass_fee_waiver_decision.php`
- `scripts/gate-pass-waiver-schema.php`
