# Port of Duqm e-Services Production Security Runbook

Purpose: convert the hardened CodeIgniter application into a controlled, testable production service.  
Audience: PODC infrastructure/security, database administrator, supplier release lead, application owner, payment/iBulk owners.  
Rule: **do not go live until every blocking gate is signed and its evidence is retained.**

This runbook contains no production secret values. Record evidence identifiers and vault references only; never paste credentials, OTPs, customer documents, private keys, or payment data into tickets or logs.

## 1. Roles and release controls

Assign named people for:

- Release/change owner and rollback decision.
- PODC security/WAF/SIEM owner.
- Database migration and restore owner.
- Files/upload migration and malware-scanner owner.
- Secret/key custodian.
- iBulk, reCAPTCHA, email and payment provider owners.
- Application smoke/UAT owner for Admin, Gate Pass, Vendor, Tender and PTW.
- Independent penetration tester and final acceptors.

Create one approved change record containing the release commit, artifact checksum, migration plan, maintenance window, backups, smoke tests, rollback trigger, incident bridge and evidence location. Freeze application and schema changes after approval.

## 2. Gate A - backup and proved restoration

Complete this before altering Git history, schema, stored paths, secrets, or production code.

1. Quiesce writes or use a database-consistent snapshot method.
2. Back up the database, `WRITEPATH/uploads`, any remaining approved legacy upload roots, provider configuration references, and the current release artifact. Store secrets separately in the secret manager, not in the application backup.
3. Encrypt backups with a PODC-managed key; restrict access; capture object size, SHA-256 checksum, timestamp, retention and custodian.
4. Restore the backup into an isolated non-production environment using new test credentials.
5. Reconcile database row counts and upload inventory/checksums. Test representative Admin, Vendor, Tender, Gate Pass and PTW records without exposing real data to unauthorized personnel.
6. Measure restore time and data-loss point; compare them with approved RTO/RPO.
7. Obtain DBA and application-owner sign-off. A successful backup job without a full restore is not acceptance evidence.

Rollback packages containing historical plaintext or formerly public documents remain sensitive. Encrypt and restrict them, then expire or securely destroy them according to PODC retention policy.

## 3. Gate B - source-control and credential incident cleanup

The current index exclusion and `.gitignore` prevent new commits of local runtime data, but they do not erase earlier Git objects.

1. Mirror the repository into a restricted recovery location and preserve the approved legal/audit copy if required.
2. Inventory all refs, tags, pull-request refs, forks, CI caches and release bundles for environment files, deployment profiles, logs, session artifacts and uploaded customer/tender/permit documents. Do not print their content into scan output.
3. Use an approved history-rewrite procedure such as `git filter-repo` to remove the exact sensitive paths and values from every ref. Coordinate the force update; invalidate old clones and CI caches; require fresh clones.
4. Run a full-history secret/data scan and demonstrate that the objects cannot be retrieved from the rewritten remote.
5. Rotate or revoke every value that may have appeared in source or deployment profiles, including:
   - SFTP/hosting credentials and SSH keys;
   - database, SMTP/IMAP and integration credentials;
   - OAuth client secrets/tokens;
   - application encryption and signing keys;
   - payment/webhook keys;
   - cron, notification, file-stream and tender-opening keys.
6. Rotation of the application encryption key can invalidate existing encoded identifiers or encrypted settings. Inventory dependants, migrate/re-encrypt them in a controlled window, then retire the prior key. Never silently replace it.
7. Record who rotated each credential, when, where the new secret is held, and proof that the old value is rejected. Do not record the value itself.

Do not proceed while any exposed credential remains valid or sensitive customer object remains retrievable from an unauthorized remote/ref.

## 4. Gate C - build and production configuration

Build a clean artifact from the reviewed commit; never deploy a working directory or local `.env`.

1. Confirm the checksum-pinned artifact bundles **CodeIgniter 4.7.4 or a later security-supported release**. Run `php tests/FrameworkRuntimeVersionTest.php` and `php tests/FrameworkConfigCompatibilityTest.php`, and independently inspect `CodeIgniter::CI_VERSION`; retain the production minimum-version guard in `app/Config/App.php`. The configuration contract covers the merged CodeIgniter 4.7 properties, CLI-safe `App`/`Rise` bootstrap, JSON encoding depth, migration locking and related compatibility requirements. Version 4.7.4 fixes trusted-proxy HTTPS spoofing, Query Builder `deleteBatch()` SQL injection, default upload-move path traversal, and upload extension/content validation bypasses. Review the [4.7.4 security changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html).
2. Keep the reviewed root-layout `spark` and `index.php` entry points aligned. Both now reject PHP below **8.2**, which is the CodeIgniter 4.7 application floor; run `php tests/CliMigrationEntryPointTest.php` and `php spark --version` from the repository root.
3. Deploy on a fully patched **PHP 8.3 or 8.4** runtime with required extensions. The PHP 8.2 entry-point minimum permits development/CLI compatibility but is not the production recommendation. Resolve any 4.7/PHP deprecations and rerun the complete application/security suite plus production-like HTTP/upload/proxy/database/session regression tests. Verify current support dates on the [official PHP support table](https://www.php.net/supported-versions.php).
4. Set `CI_ENVIRONMENT=production` through host/orchestrator configuration.
5. Use `.env.example` only as a key-name checklist. Replace every `CHANGE_ME` in a deployment secret manager; do not copy a real `.env` into Git or the public web tree.
6. Provision independent random values for:
   - `PODC_APP_ENCRYPTION_KEY`;
   - `AUTH_SECURITY_MFA_HMAC_KEY` and approved iBulk credentials/sender;
   - reCAPTCHA site/secret and exact hostname/action policy;
   - e-service Stripe credentials and webhook secret;
   - tender-opening HMAC and AES-GCM keys, generated independently as documented in `TENDER_3KEY_SECRET_HARDENING.md`;
   - cron, notification processor and private file-stream HMAC keys;
   - any explicitly enabled legacy webhook provider secret.
7. Keep SMS delivery, legacy webhooks and e-service payments disabled for the current release. Each requires a separate approved activation and live acceptance gate.
8. Set an explicit canonical HTTPS `PODC_BASE_URL`; configure only known reverse-proxy IPs; reject untrusted forwarded headers.
9. Confirm production boot fails when the framework is below 4.7.4 or required current-release keys, HTTPS base URL or non-root database credentials are missing. Staff SMS MFA remains an explicit deferred checklist item until PODC approves and activates it.
10. Ensure `display_errors=Off`, `log_errors=On`, supported PHP version/modules only, and no debug toolbar/profiler or phpinfo endpoint.
11. Generate a configuration manifest containing component versions, variable names, source/vault references and validation result only - never values.

## 5. Gate D - database and migration ownership

### 5.1 Resolve schema authority first

Production must have one authoritative migration path. Reconcile the current database schema, CodeIgniter migration ledger, `app/Database/Migrations`, approved manual SQL and historical dumps. Resolve orphaned foreign keys, inconsistent prefixes, charset/collation, strict-mode, zero-date and enum differences before applying new changes.

**Assessment snapshot, 3 August 2026:** the restored root `php spark migrate:status` command enumerates 24 application migrations and shows all 24 with no migrated date or batch. Treat every migration as pending until the target database and ledger are reconciled; this runbook does not claim that any migration has been applied.

Normal web requests must not require `CREATE`, `ALTER` or data-scrubbing privileges. The release moves runtime schema ownership into `app/Database/Migrations/2026_08_03_150000_runtime_schema_ownership_hardening.php` or its checksum-reviewed manual-SQL equivalent, while `app/Libraries/Runtime_schema_guard.php` performs read-only, fail-closed readiness checks. `tests/RuntimeDdlHardeningTest.php` scans Models, Controllers and Libraries for schema-mutation statements and must pass. Applying the migration and proving all workflows under a DML-only application identity remain deployment prerequisites.

### 5.2 Dry run and apply

1. Clone the restored production backup into staging.
2. Run `php spark migrate:status` against the restored staging clone and compare it with the reviewed release manifest. Explain and approve every difference from the 24-pending assessment snapshot before changing state.
3. Choose **one** method per environment:
   - CodeIgniter migrations, normally `php spark migrate --all`; or
   - reviewed, checksum-pinned manual SQL from `app/Database/SQL` where the deployment process requires it.
4. Do not run both the migration and equivalent manual SQL. Confirm the correct DB prefix/database name and remove any hard-coded target mismatch before execution.
5. For this release, apply the reviewed non-payment authentication, vendor multi-CR/contact, runtime-schema ownership, gate-pass replay, vendor role, notification nonce, PTW company/applicant and tender-opening upgrades in the order documented in `documentation/MANUAL_SECURITY_DATABASE_UPGRADE.md`. Defer payment/invoice-payment upgrades until payment activation. Protected tender-storage migration remains a separate filesystem-aware procedure.
6. Capture start/end time, operator, script checksums, affected schema objects, output and post-migration schema diff. Redact connection strings and values.
7. Run application/security tests and complete role/company/CR smoke tests against the migrated staging clone.
8. Repeat in production during the maintenance window using the approved migration identity.

### 5.3 Least privilege and database transport

After migration:

- Revoke schema-alter/create/drop, grant and file privileges from the application account.
- Allow only the minimum SELECT/INSERT/UPDATE/DELETE/EXECUTE needed by the application; use a separate migration identity stored in the deployment system.
- Require TLS with certificate and hostname verification for a remote database. Restrict database ingress to application/migration hosts.
- Enable strict SQL mode, disable production DB debug, use supported `utf8mb4` collation, and verify time zone policy.
- Enable PODC-approved database/disk encryption at rest, encrypted replicas and backups; document key custody and rotation.
- Alert on failed logins, privilege changes, schema changes, unusual export volume and audit-table tampering.

## 6. Gate E - protected files and malware scanning

1. Place `WRITEPATH` outside the public document root where possible. If hosting constraints prevent that, deny it at virtual-host and directory level and prove direct access is blocked.
2. Run the tender migration described in `TENDER_DOCUMENT_STORAGE_MIGRATION.md`; reconcile every database row, size and checksum before deleting any approved rollback copy.
3. Inventory all historical Gate Pass, Vendor, Tender, PTW, identity, signature, receipt and report paths. Move every sensitive or executable-risk object to protected storage and serve it only through an authorized controller.
4. Give the web process write access only to approved runtime directories. Target directory/file modes are 0750/0640 or stricter; code/config are read-only; the process must not own release code.
5. Register `app_filter_secure_upload_malware_scan` with the approved scanner. It must return configured/clean only after a completed clean verdict. Production keeps `UPLOAD_MALWARE_SCAN_FAIL_CLOSED=true`.
6. Test allowed jpg/jpeg/png/pdf files, extension/MIME/magic mismatch, double extension, polyglot/truncated file, oversize file, traversal filename, archive bomb where applicable, scanner timeout, and a safe EICAR test in an isolated environment.
7. Confirm scanner errors reject the upload and generate a redacted alert. Define quarantine, analyst access, retention and deletion.
8. Align reverse proxy, Apache and PHP body limits with each application context. A higher upstream limit must not bypass the application limit.
9. Scan public URLs for legacy file roots and executable extensions. Confirm `/files/tender_files/...`, `/writable/...`, source, logs and database artifacts return 403/404 without content.

## 7. Gate F - web server, TLS and HTTP hardening

Configure equivalent controls at the virtual host/reverse proxy even when `.htaccess` contains a fallback.

1. Install the complete trusted certificate chain; automate renewal and alert before expiry.
2. Permit only approved TLS 1.2/1.3 protocols and ciphers; disable SSL, TLS 1.0/1.1 and insecure renegotiation/compression.
3. Redirect HTTP to the canonical HTTPS host before application routing. Resolve mixed content and secure every cookie.
4. Enable HSTS only after HTTPS validation; use the approved max-age and includeSubDomains/preload decision.
5. Set Apache `TraceEnable Off` at server/vhost level. Permit only GET, HEAD, POST and OPTIONS when CORS/preflight is required; otherwise remove OPTIONS. Reject PUT, PATCH, DELETE, CONNECT and other unused methods at the edge.
6. Set `ServerTokens Prod`, `ServerSignature Off`, `expose_php=Off`; remove default pages, samples, readmes, licenses, backups, editor files and debug/status endpoints.
7. Disable directory listing, symlink escape, CGI and script execution in upload roots. Deny `.git`, environment, config, SQL, logs, tests, application source, dependency and writable paths.
8. Align request/header/body/time limits at CDN/WAF/proxy/Apache/PHP/application. Use conservative connection, read and upstream timeouts.
9. Return generic 400/401/403/404/405/429/500 responses with correlation IDs; never reveal stack traces, paths, SQL or secrets.
10. Verify headers on success, redirect, error, login, download and API responses: HSTS, CSP, X-Content-Type-Options, X-Frame-Options or CSP frame-ancestors, Referrer-Policy, Permissions-Policy, cache controls and secure cookies. Root rules should unset inherited/dynamic X-Content-Type-Options, X-Frame-Options and Referrer-Policy values before setting one canonical value; reject duplicate/conflicting instances and run `tests/ServerExposureHardeningTest.php`. `X-XSS-Protection: 0` is the modern safe value; CSP/output encoding are the actual XSS controls.
11. The current legacy CSP includes `unsafe-inline` and `unsafe-eval`. Remove them by moving inline code/styles to static assets and adopting nonces/hashes, or obtain a documented, time-bound security exception with monitoring and a retirement date.
12. Run external TLS, HTTP method, banner, directory/source-disclosure, header and port scans. Store the reports with the release.

## 8. Gate G - WAF, network and service hardening

PODC security must approve the policy before public DNS is enabled.

- Restrict the origin so normal traffic reaches it only through the approved WAF/load balancer and authorized management network.
- Enable maintained managed-rule groups for injection, XSS, traversal, file inclusion/RCE, scanners and protocol anomalies.
- Add endpoint-specific rate limits for login, password reset, MFA, registration, uploads, clarifications, payments, QR lookup and signed callbacks.
- Do not blindly bypass webhook endpoints. Limit body/method/content type, verify provider signatures in the application and rate-limit abusive sources.
- Enforce upload/body limits and reject abnormal encodings, duplicate/conflicting headers and invalid host names.
- Run false-positive tests for Arabic/English content, multipart upload, payment callbacks and provider IP changes.
- Send WAF events to SIEM with synchronized UTC time and correlation; test block, alert, exception expiry and emergency disable procedures.
- Permit only required public and management ports. Disable unused OS services, default accounts, weak SSH authentication and unnecessary PHP/Apache modules.
- Patch and harden the OS, web server, PHP, database client and scanner; retain benchmark/configuration scan evidence.

## 9. Gate H - identity, CAPTCHA and session acceptance; SMS activation deferred

1. Apply the authentication migration and confirm auth lock/audit/reset/MFA tables exist with expected indexes.
2. Inventory every account class: Admin/staff, Gate Pass visitor, Vendor-only contact, mixed staff/vendor, PTW applicant/reviewer and service identity. Confirm portal-only identities cannot reach the internal dashboard. Test a mixed identity with an inactive historical Vendor CR and valid Gate Pass/PTW access; each active membership must resolve independently. Confirm a role-less identity with no active external membership is denied at login and loses an existing session after its final membership is revoked. Confirm authenticated external identities cannot escape through public-capable controller subclasses using `parent(false)`, while anonymous public actions and exact internal utility instances still work; run `tests/MixedExternalPortalAccessTest.php` and `tests/ExternalControllerEscapeHardeningTest.php`.
3. Approve the role/company/CR authority matrix. Test each allowed and denied operation with direct HTTP requests, not only hidden menu items.
4. Identify legacy MD5 password hashes, force a secure reset or controlled migration, then remove MD5 fallback from the release before final accreditation.
5. Test password creation/change/reset policy, current-password verification, reset token expiry/single use, enumeration resistance and revocation of prior sessions. Exercise `Portal_account` with Gate Pass and PTW identities and `Vendor_portal` with vendor identities; prove that neither surface can target another user ID. Run `tests/AuthSessionVersionAtomicityTest.php` to confirm the user row is locked, password and version commit together, the retained session updates only after commit, and missing/legacy/malformed session-version state fails closed. Run `tests/VendorPasswordChangeTest.php` to confirm the vendor limiter executes before current-password verification.
6. For the current release, verify SMS MFA is disabled and retain PODC's written deferral acceptance. Before later iBulk activation, test approved Oman formats, sender name, delivery, retry, expiry, maximum attempts, replay, throttling, gateway timeout and unavailable-provider behavior. Confirm OTP values never enter DB plaintext, logs, notifications or support tickets.
7. At SMS activation, verify required user types cannot authenticate when MFA configuration/destination is missing and document any approved vendor email-OTP policy.
8. Test reCAPTCHA key/hostname/action/score restriction, missing token, replay, provider outage, accessibility and automated abuse.
9. Inspect cookies and sessions for Secure/HttpOnly/SameSite, ID regeneration after password and MFA, database locking, idle/absolute expiry, logout destruction, password-change/reset revocation and fixation/replay resistance.
10. Test post-login redirect handling against the configured origin. Allow only an exact scheme, host and effective-port match; reject cross-origin, scheme downgrade, alternate-port, userinfo and CR/LF payloads. Run `tests/SigninRedirectSecurityTest.php` and repeat with HTTP requests through the production proxy.
11. Inspect every credential-notification template and rendered message. A reusable initial or current password must never be sent by email; only the approved non-secret notice and activation/change instructions may be included. Run `tests/CredentialEmailHardeningTest.php` and retain redacted samples in English and Arabic.

## 10. Gate I - future payment activation

Payments are outside the current release and must remain disabled. Complete this entire gate before any later payment activation; it is not a current-release launch dependency while all payment entry points are inaccessible.

1. Apply e-service and legacy invoice payment migrations and confirm uniqueness/idempotency indexes.
2. Provision provider credentials and webhook secrets from the vault. Restrict dashboard access and enable provider MFA/audit alerts.
3. Review the data flow for PCI scope: never store PAN/CVV; store only approved provider references and minimum receipt data; redact logs and reports.
4. In sandbox, test server-derived amount/currency/method, OMR minor units, signed raw-body webhooks, timestamp tolerance, duplicate/out-of-order events, callback replay, expired checkout, cancelled/failed/async payment, balance change, refund and reconciliation.
5. Verify transaction settlement is single-use and atomic and that a client cannot alter amount, currency, invoice/tender/vendor or provider reference.
6. For Stripe, validate checkout and PaymentIntent amount/status/metadata plus signed webhooks. For any enabled PayPal/Paytm legacy flow, verify TLS, authenticated API result, exact binding and callback single use.
7. Execute an approved small live transaction and refund for every enabled provider. Reconcile provider dashboard, application status, receipt, audit and accounting output.
8. Test role/object access to receipts, transaction history, exports and logs. Confirm payment failure never advances Gate Pass/Tender workflow.
9. Enable the provider only after payment owner, finance, security and application owner sign the evidence.

## 11. Gate J - Tender, Gate Pass, Vendor and PTW acceptance

Run tests with at least two companies, two vendors, one multi-CR contact, and users for every operational role.

- Vendor: duplicate contact prevention within CR/email, owner-set initial password, existing shared identity behavior, self password change, multi-CR selection, revoked membership, OWNER/EDITOR/BIDDER/VIEWER permissions, and cross-CR document denial.
- Tender: request/company scope, invitation/eligibility, verified fee, 72-hour download boundary, technical/commercial separation, protected download, clarification isolation, concurrency, scheduled transitions, three distinct opening roles and expired-secret cleanup.
- Gate Pass: applicant ownership, department/commercial/security/ROP order, parent/stage document checks, block/reblock/unblock visibility, concurrent issuance, QR forgery/replay, entry/exit sequence and blocked visitor denial.
- PTW: assigned applicant company only, immutable company after creation, HSSE/HMO/Terminal company assignment, stage order, attachment authorization and cross-company denial.
- Reports/notifications: direct export URL denial, CSV formula neutralization, least-necessary recipients/content, signed callback replay denial and audit completeness.

Retain a role/action/object result matrix with request ID, expected/actual result and evidence link. Do not include sensitive payloads.

## 12. Gate K - Google Drive and external integrations

New application behavior keeps Drive uploads private and streams them through a signed, session-bound application URL. Historical provider state must be repaired separately.

1. Inventory every Drive object created by the application, including inherited folders.
2. Remove `anyone`, `anyoneWithLink`, domain-wide or unintended group/user permissions; review inherited sharing and shared-drive policy.
3. Verify the service identity has only required folders/scopes and cannot list unrelated PODC data.
4. Revoke unused OAuth grants, rotate exposed client secrets/tokens and re-authorize through the one-time state-bound flow.
5. Test signed stream expiry, current-user binding, tampering, direct provider URL denial, cache controls and access after user/vendor revocation.
6. Repeat the permission audit on a schedule and alert on creation of public links.
7. Inventory every other integration/webhook, keep unused ones disabled, use TLS and signed callbacks, and set an owner/rotation/incident procedure.
8. For Slack and Pusher notification delivery, retain the application allowlists for official HTTPS destinations/identifier formats, TLS peer and hostname verification, redirect denial and bounded timeouts. Run `tests/OutboundIntegrationSecurityTest.php` and production-like negative tests for non-HTTPS, look-alike/unapproved hosts, malformed identifiers, redirects, TLS failure and timeout.

## 13. Gate L - logging, SIEM, monitoring and backup operations

1. Ship application, authentication/audit, WAF/proxy, web/PHP, database, OS, malware-scanner and scheduler logs to a tamper-resistant PODC SIEM. Add payment and iBulk logs before those services are activated.
2. Synchronize all systems with approved NTP and normalize to UTC while retaining the displayed business time zone where required.
3. Preserve event time, actor, role/company/CR, action, object ID, outcome, source and correlation ID. Do not log passwords, OTPs, session/cookie values, tokens, keys, full provider payloads or protected document content.
4. Restrict log/report access; encrypt transport/storage; define retention, integrity protection, privacy minimization and disposal.
5. Alert and test response for lockout/credential stuffing, privilege/role changes, block/unblock, workflow override, repeated document denial, upload malware, QR replay, WAF critical events, scheduler failure, schema change and backup failure. Add MFA-failure and payment-replay alerts before those features are activated.
6. Monitor uptime and latency for login, approval, QR and protected downloads; monitor queue, DB, disk/inode, certificate, scanner and backup capacity. Add OTP/payment transaction monitors before activation.
7. Run a tabletop incident covering compromised vendor credentials, public document exposure and tender-opening key compromise. Add payment callback abuse to the activation-stage tabletop.
8. Schedule automated encrypted backups and recurring isolated restore drills; review RPO/RTO and alert tests at least quarterly or per PODC policy.

## 14. Gate M - security verification and independent test

Test the exact release candidate in a production-like environment after migrations and hardening.

1. Run secret and sensitive-file scans over the worktree and complete Git history.
2. Generate a unified SBOM and run dependency/SCA checks for Composer, bundled PHP SDKs, JS/CSS/font packages, PHP and server packages.
   - The current read-only snapshot found eight independent top-level Composer lockfiles, 53 production package entries, zero `packages-dev` entries and zero lock-embedded abandoned flags. Treat this as inventory evidence only, not as proof of zero vulnerabilities.
   - A live `composer audit` could not run because approved advisory-network egress was unavailable and no cached advisory database existed. Before go-live, audit every independent lockfile against a current approved advisory source and retain the dated results.
   - PHP-Hooks, Paytm, php-duration-master, recaptcha and tcpdf have no lockfile/SBOM coverage. Record their exact source, version, path, checksum, license, support status and advisory mapping, then include them in SCA and remediation decisions.
3. Run SAST focused on raw SQL, output contexts, file/path operations, external requests, crypto, deserialization, dynamic include and authorization.
4. Run authenticated DAST for every role plus unauthenticated testing. Cover SQL/command injection, XSS, CSRF, IDOR/BOLA, traversal, file upload/RCE, inclusion, SSRF, brute force/lockout, open redirect, host-header/proxy trust, session fixation/replay, cache leakage and error disclosure.
5. Run current-release business-logic and concurrency tests for vendor isolation, approval order, block/unblock, QR, three-key opening and scheduled workflow. Test payment and paid 72-hour access in their later activation gate.
6. Run TLS/header/WAF/source-disclosure/directory/method/banner/port scans from outside the PODC network.
7. Engage an independent tester. Remediate all Critical/High findings, retest them, and record risk-owner/date for any accepted lower-severity exception.
8. Attach the clean test transcript and independent report to the PODC checklist; obtain Supplier Security Lead, PODC Project Manager and PODC Security Team signatures.

Automated source-shape tests are regression controls; they do not replace HTTP, database, concurrency, provider, infrastructure or penetration testing.

## 15. Gate N - training, go-live and rollback

### Training and handover

Train administrators and operational users on password hygiene, phishing, role assignment, protected exports/documents, suspicious activity, incident reporting, backup/restore and safe updates. Add MFA operation and payment reconciliation training before those features are activated. Retain material, attendance, assessment and acknowledgements for the agreed PODC participants.

Hand over architecture, authority matrix, data classification, runbooks, monitoring dashboards, incident contacts, secret/key ownership, provider support, patch calendar, backup policy, known accepted risks and warranty/support obligations.

### Go-live sequence

1. Confirm every gate owner has signed and there are no open Critical/High findings or unapproved exceptions.
2. Announce maintenance and incident bridge; stop writes/background jobs as planned.
3. Take the final consistent backup and verify its checksum.
4. Apply migrations with the migration identity; reconcile schema and data.
5. Deploy the checksum-pinned artifact; apply immutable ownership/permissions and production secrets.
6. Start the signed scheduler; confirm tender progression and secret-expiry cleanup health.
7. Run current-release smoke tests for HTTPS/login, role denial, vendor CR switch, upload/scan, protected download, approval, QR, notification and export. Run MFA and payment-status smoke tests only in their activation releases.
8. Enable WAF/public routing gradually; watch SIEM, application errors, latency and provider callbacks.
9. Obtain business/security confirmation and close the change only after the observation period.

### Rollback

Define objective triggers: authentication outage, data corruption, authorization bypass, provider mismatch, failed migration, abnormal error/latency rate or security alert. The release owner pauses traffic and invokes rollback; the security owner preserves evidence.

- Do not assume every migration has a reversible `down()`. The tender protected-storage migration deliberately does not copy documents back to a public path.
- Restore code, database and files from the same coordinated recovery point; do not mix pre/post-migration states.
- Keep compromised credentials revoked during rollback; issue new ones rather than restoring exposed values.
- Reconcile row/file checksums and rerun security smoke tests before reopening.
- Record the cause, timeline, affected data, recovery proof and required corrective action.

## 16. Final go/no-go record

The release is **NO-GO** if any of these remain:

- sensitive Git history or unrotated exposed credential/key;
- CodeIgniter below 4.7.4, an unpatched/unsupported PHP runtime, or failed framework/runtime regression tests;
- missing/failed migration, runtime web DDL requirement, or unreconciled schema/data;
- public sensitive document path or inactive fail-closed malware scanner;
- missing production HTTPS/TLS/WAF/port/header evidence;
- missing current-release reCAPTCHA, SMS/payment enabled without their approval and acceptance evidence, or any enabled-but-untested provider;
- unencrypted sensitive production storage/backups or excessive DB/file permissions;
- failed restore drill, scheduler, SIEM/monitoring or critical alert path;
- incomplete unified SBOM, unavailable current advisory scan, or unassessed no-lock component;
- unresolved Critical/High test finding;
- absent independent retest, training, authority matrix, supplier security-accountability/warranty evidence or required signatures.

Only the named PODC and supplier approvers may change NO-GO to GO, and only by referencing the complete evidence pack and formally accepted residual risks.
