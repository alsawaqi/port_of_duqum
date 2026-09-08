# Production database review — 8 September 2026

The supplied production schema needs **four new application tables** before the current payment, accounting, and SMS code is deployed. They contain **84 fields in total**. No missing columns were found in the **209 tables shared by production and the local application database**.

This review used the supplied `production_schema.sql`, the current application code and migrations, and a read-only snapshot of the local database. No connection to production was made and no production changes were applied.

| Item | Result |
| --- | --- |
| Production database | `bedotscpanel_poderp`, prefix `pod_` |
| Production server reported by dump | MariaDB **10.11.19**; `5.5.5-` is the compatibility prefix |
| Export tool | MySQL Workbench / mysqldump 8.0.41 |
| Export completed | 2026-09-08 13:38:53, as recorded in the file |
| Production schema | 211 base tables; no exported views, triggers, routines, or events found |
| Local reference | 217 tables on MariaDB 10.4.32 |
| Expected production total after this patch | 215 tables |
| Required changes to existing production columns | None identified for this release |
| Test result | 229 checks passed on an isolated, empty copy of the production structure |

The source file's SHA-256 is recorded in `comparison-summary.json` so the reviewed export can be identified exactly. The production dump contains `DROP TABLE` statements as part of its normal export format; it is an analysis/backup input, **not** the upgrade script to execute on production.

## Required additions

| Missing table | Fields | Purpose and affected pages |
| --- | ---: | --- |
| `pod_eservice_payments` | 35 | Shared payment ledger for vendor registration/renewal, gate pass, tender fees, and any explicitly implemented PTW fee. Stores payer, subject, amount/currency, bank references, safe bank responses, verification and settlement state. All accounting pages depend on this ledger. |
| `pod_eservice_payment_events` | 12 | Bank callback/status-check history, processing status, payload digest, safe response details, and duplicate-event protection. |
| `pod_vendor_fee_requests` | 18 | Registration/renewal fee snapshot, vendor/group, registration period, payment link, requester and Procurement review details. |
| `pod_sms_outbox` | 19 | Workflow notification queue and history: module, record, recipient, message, provider result and processing timestamps. Settings → SMS and the scheduled worker depend on this table. Login OTP uses the existing authentication tables. |

The exact field definitions and indexes are in [FIELDS.md](FIELDS.md). The upgrade includes the final Bank Muscat accounting columns and its active-payment uniqueness rule, including `verification_required`. These are the structures established by:

- `2026_08_03_070000_eservice_payment_integrity.php`
- `2026_09_05_100000_bank_muscat_payment_accounting.php`
- `2026_09_05_110000_vendor_fee_requests.php`
- `2026_09_05_130000_sms_outbox.php`

The SQL patch creates the final table definitions directly; it does not require creating an older payment schema and then altering it. It carries no local AUTO_INCREMENT counters or test records. No PTW fee is invented: the accounting page can remain empty when no PTW fee flow is configured.

## Existing structures to retain

Production already has the user phone and session-version fields, authentication challenge/reset/audit tables, vendor contact identity and credential-readiness fields, vendor registration validity fields, PTW company scope and applicant assignments, and the existing tender/gate-pass payment and review fields.

Some production structures are more complete than localhost. Copying the entire local database structure would remove useful protections:

| Observed difference | Assessment |
| --- | --- |
| `pod_notification_processor_nonces` and `pod_ptw_applicant_users` exist only in production | Retain. They belong to existing notification security and PTW applicant access. |
| Gate-pass QR token is `VARCHAR(64)` with `uq_gate_passes_qr_token` in production; localhost has `VARCHAR(255)` without this unique index | Retain production's definition. The application's scan-replay migration uses 64-character tokens and the unique index. |
| Production scan log includes `idx_gate_pass_scan_movement_lock` | Retain this locking/query index. |
| PTW attachment/response requirement IDs are nullable in production but NOT NULL locally | Retain production's nullable definitions; they match the runtime-schema migration. |
| Vendor contact generated identity/index differs | Production uses a live-row marker with unique `(vendor_id,user_id,live_user_identity)`; localhost embeds `user_id` in the generated value. Both enforce one live contact per linked user within a vendor. Do not replace production's functioning constraint. |
| `pod_vendor_users.invited_by` is INT in production and BIGINT UNSIGNED locally | Production's INT range is consistent with `pod_users.id`; no widening is needed for the current code. |
| Authentication indexes have different names on four tables | Eleven indexes have equivalent uniqueness/column order despite different names. No duplicate indexes or renames are needed. |
| Authentication table collations differ: production uses `utf8mb4_general_ci`, localhost `utf8mb4_unicode_ci` | No application-required collation change identified. Preserve production's existing values and indexes. |
| Production retains additional foreign keys on vendor, tender and vehicle tables | Preserve them. No foreign keys are dropped by the patch. |
| `utf8mb3` versus `utf8` in dump/display output | These were normalized as aliases for comparison, not treated as missing fields or a request for a charset conversion. |

The three local tables ending in `_orphan_20260722` are historical local repair/archive tables. They are not required by current runtime code and are not included in the upgrade.

## Migration history

Production contains a legacy `migrations` table with `id`, `migration`, and `batch`. It does **not** contain CodeIgniter's prefixed `pod_migrations` table, which has a different layout. The schema-only export does not include the legacy migration rows.

The supplied manual patch is sufficient for these four missing runtime tables. It deliberately leaves migration ledgers unchanged and does not pretend older migrations have run. Do not copy localhost's migration history or run every pending migration just to populate a ledger. Older migrations include data changes, role updates, document-file migrations and constraint changes that require separate review. `01_preflight.sql` reads the legacy ledger so a future CLI migration baseline can be planned from actual production history.

## Production records still needing review

A structure-only export cannot establish whether settings, role permissions, fee rules, personal mobiles, company assignments, or historical payment records are correct. [01_preflight.sql](01_preflight.sql) provides read-only queries for those checks without exporting passwords, SMS credentials, or personal phone values.

The payment cutover matters for **existing in-progress records**:

- Vendor registration/renewal review now expects a fee request and verified payment, or an explicitly configured zero fee. Previously submitted records created through the old bypass may need an approved transition plan.
- Gate passes with positive, unwaived fees need verified ledger payment before further Security approval/issuance. An old payment-reference field alone is not sufficient proof for the new flow.
- Existing `pod_tender_fee_payments` rows are not automatically copied into the new verified bank ledger. Review historical tender fee records before deciding how to treat existing entitlements.

The upgrade does not fabricate payments, charge anyone, copy test payments, approve applications, or mass-update users. Do not re-charge an applicant solely because a newly created ledger is empty; reconcile existing evidence first. The 32 missing-mobile accounts previously discussed belong to localhost and tell us nothing about production's actual mobile readiness.

## Deployment order

1. Run **01_preflight.sql** against production and review the result grids. Confirm whether any of the four tables has appeared since this export. If a table now exists but is incomplete, this patch's `IF NOT EXISTS` intentionally preserves it rather than silently rewriting it; compare its full definition first.
2. Take a restorable production database backup and uploaded-files backup. Schedule the code/database cutover and pause application writes and workers for the deployment window.
3. Run **02_add_missing_tables.sql** in MySQL Workbench while connected to the intended production server. The script explicitly selects `bedotscpanel_poderp` and only creates the four missing tables.
4. Run **03_verify.sql**. Expect table column counts of **35, 12, 18, and 19** respectively, then review the returned definitions/indexes against FIELDS.md. A matching count by itself does not prove all definitions match.
5. Deploy the complete tested application release, including its current framework and dependencies. Merge production configuration into the server's existing environment; preserve the production application encryption key and existing uploads. Do not upload the localhost database, test accounts, localhost `.env`, or local scheduler scripts as production configuration.
6. Configure the services below, verify production account/fee/role data, and resolve any historical payment cutover items. Resume the intended workers and application traffic after smoke testing.

MariaDB DDL statements such as CREATE TABLE cause implicit commits, so wrapping this script in a transaction is not a substitute for a backup. If code must be rolled back after new payment records exist, retain the new ledger/history tables and reconcile pending payments; do not drop financial evidence. [MariaDB transaction documentation](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit)

### Configuration after upload

| Area | Required production action |
| --- | --- |
| Site/runtime | Correct production HTTPS base URL, `CI_ENVIRONMENT=production`, supported PHP/extensions and the included CodeIgniter 4.7.4 framework. Verify the deployment's existing dedicated database credentials. |
| Existing application encryption | Preserve `PODC_APP_ENCRYPTION_KEY` / the existing configured application key; replacing it can invalidate already-encrypted settings and identifiers. |
| Bank Muscat | Supply production `SMARTPAY_MERCHANT_ID`, `SMARTPAY_ACCESS_CODE`, `SMARTPAY_WORKING_KEY`, and `SMARTPAY_GATEWAY_URL` issued/approved for the production domain. The configured live URL is `https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction`. Verify the domain, return URL and outbound bank Status API access with the bank. |
| SMS connection | Settings → SMS: save the approved iSmartSMS username, password and sender Header, then enable the connection. Local DB settings do not travel with an application-file upload. Test delivery from the hosting server. |
| Workflow SMS | Choose Vendor, Gate Pass, PTW and Tender notifications as needed, enable recording and real SMS, and configure the hosting scheduler to run `php spark sms:process` from the project directory every minute. Use the hosting PHP executable and actual project path. The Windows localhost task is not installed on production. |
| Login OTP | Keep `AUTH_SECURITY_MFA_HMAC_KEY` in production `.env` with a strong independent random secret of at least 32 bytes. Initially keep mandatory OTP off, verify the admin's mobile and actual SMS delivery, then use Settings → SMS → Require SMS OTP for all logins once all active login accounts pass the readiness check. Login OTP sends immediately, independently of the outbox worker. |
| Accounting access | Assign the new module-specific accounting permissions through Roles. Set company scopes for Gate Pass, PTW and Tender where appropriate; choose Accounting only for users restricted to those pages. No localhost role IDs or test-role rows should be copied. |
| Fee/master data | Confirm actual production vendor registration/renewal fees, group validity, gate-pass fee/waiver configuration and tender fees. The export contains their columns, not their configured values. |

## Validation and limits

The SQL files were executed on a fresh local scratch database created from the 211 exported CREATE TABLE statements. All original table definitions remained unchanged after applying the patch. Exactly four tables were added, and a second application preserved their definitions. The four new definitions matched the current local runtime schema. Unique constraints rejected duplicate active/uncertain payments, duplicate bank events, duplicate vendor fee periods, and duplicate SMS events. The scratch database was removed after validation.

**229 checks passed.** See `validation-results.json`. Local testing used MariaDB 10.4.32, whereas production reports 10.11.19. The `utf8mb3` alias in the imported copy was normalized to `utf8` for the local version. Tests used empty source tables and synthetic new-table records; they do not prove production data quality, migration history, live bank/SMS connectivity, or data-volume performance. No production services were contacted, no bank payment or SMS was sent, and no production upgrade was executed.

The schema patch is prepared and tested. Production go-live approval still depends on the preflight results, service configuration, historical payment treatment and a smoke test of the deployed environment.
