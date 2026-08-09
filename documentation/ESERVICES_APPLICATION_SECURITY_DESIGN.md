# Port of Duqm e-Services Application Security Design and Plan

## 1. Document status

This is the project-specific application security design for the CodeIgniter 4 Port of Duqm e-Services application. It records controls found in the current source tree and the remaining application assurance work. It is not a penetration-test report, a server-hardening certificate, or proof that a production deployment is secure.

Status terms used here:

| Status | Meaning |
|---|---|
| Implemented and locally checked | The control exists in the reviewed source and has a passing local contract or unit test. |
| Partial | A useful application control exists, but an important part still requires implementation or runtime verification. |
| Pending verification | The source control exists, but it has not been proven against a deployed staging or production environment. |
| Deferred | Intentionally outside the current release. |
| Server/operations owned | Must be configured and evidenced by the production hosting or security team. |

## 2. Scope and exclusions

The scope is application-owned security for:

- internal administrator and operational users;
- the vendor portal and vendor contacts linked to one or more Commercial Registrations (CRs);
- gate-pass requests and QR scanning;
- Permit to Work (PTW);
- tenders, bids, committee opening, clarifications, and protected documents;
- application sessions, authorization, input handling, uploads, audit events, and application secrets.

The following are explicitly outside this implementation phase:

| Area | Current treatment |
|---|---|
| Email delivery, email OTP, and email-dependent recovery/invitation delivery | Deferred. No completion claim is made. |
| SMS and MFA/OTP | Deferred. MFA is not a production requirement for this phase and no completion claim is made. |
| Payment gateway, payment processing, and payment-provider callbacks | Deferred. No completion claim is made. |
| TLS certificates and ciphers, web application firewall, network firewall, OS and web-server hardening, server patching, DNS/CDN, database backup/replication, SIEM, infrastructure monitoring, and server malware-scanning service | Server/operations owned. Application files may express requirements, but their presence does not prove that the production server enforces them. |

WordPress is not part of this design.

## 3. Trust boundaries and security model

The browser, public/vendor portal, internal portal, filesystem, database, and outbound integrations are separate trust boundaries. Every request is treated as untrusted until the application has authenticated the account, validated session state, resolved the selected business identity, authorized the requested action and resource, and validated input.

The central rules are:

1. Authentication proves the person or account.
2. Authorization is checked again at the controller or model boundary for the requested action.
3. Vendor data is scoped to the CR selected for the current session; a user-to-vendor relationship does not grant access to another CR automatically.
4. Workflow objects are checked against their parent, company, role, and current stage.
5. Secrets and protected files are never treated as ordinary public content.
6. Security-relevant state changes use POST and CSRF protection and are designed to fail closed.

## 4. Implemented application controls

### 4.1 Authentication and account protection

| Control | Design | Status/evidence |
|---|---|---|
| Password storage | Passwords are handled with PHP password hashing and are not stored as plaintext. | Implemented and locally checked by AuthenticationHardeningTest.php and Phase2AuthenticationHardeningTest.php. |
| Password policy | New passwords are constrained to 10–72 characters and require upper-case, lower-case, numeric, and special characters. | Implemented and locally checked. |
| Login abuse protection | Generic authentication errors, persistent failure tracking, throttling, and lockout behavior reduce account enumeration and brute-force risk. | Implemented and locally checked; live concurrent behavior remains pending staging verification. |
| Self-service password change | The signed-in user must supply the current password before replacing it; changing the password invalidates older authenticated session state. | Implemented and locally checked by OperationalSelfPasswordChangeHardeningTest.php and VendorPasswordChangeTest.php. |
| Account state | Disabled or deleted accounts are rejected, and account/session state is rechecked after login. | Implemented and locally checked. |
| Redirect handling | Sign-in redirects are constrained to safe local destinations. | Implemented and locally checked by SigninRedirectSecurityTest.php. |
| Password recovery delivery | Recovery logic must not be counted as complete until the deferred email delivery channel is implemented and tested. | Deferred. |

### 4.2 Sessions and cookies

| Control | Design | Status/evidence |
|---|---|---|
| Server-side session storage | Authentication state uses server-side database sessions rather than trusting identity data supplied by the browser. | Implemented and locally checked; live database verification is pending. |
| Session lifetime and renewal | The configured session lifetime is two hours and the identifier is regenerated periodically, including authentication transitions. | Implemented and locally checked by AuthenticationHardeningTest.php. |
| Cookie attributes | Production configuration requires Secure, HttpOnly, and SameSite cookie protection. | Implemented in application configuration; HTTPS behavior must be verified on the deployed server. |
| Session invalidation | A per-user session version permits password and account security changes to invalidate older sessions atomically. | Implemented and locally checked by AuthSessionVersionAtomicityTest.php. |
| Pending CR selection | A multi-CR vendor login uses a short-lived pending identity state before an authorized CR is selected. | Implemented and locally checked by VendorMultiCrAuthenticationTest.php. |

### 4.3 Roles, permissions, and business isolation

| Area | Design | Status/evidence |
|---|---|---|
| Internal access | Existing application role/menu permissions are supplemented by controller and workflow authorization checks. | Implemented and locally checked by RoleMenuPermissionCoverageTest.php and relevant workflow tests. |
| External identity boundary | External vendor, PTW, gate-pass, and other portal identities are confined from internal-only controllers and actions. | Implemented and locally checked by PortalIdentityBoundarySecurityTest.php, MixedExternalPortalAccessTest.php, PrivateControllerActionSecurityTest.php, and ExternalControllerEscapeHardeningTest.php. |
| Vendor multi-CR scope | A person can be linked to more than one CR. The selected vendor/CR is revalidated and all vendor records are queried in that scope. | Implemented and locally checked by VendorIdentityFoundationTest.php, VendorMultiCrAuthenticationTest.php, VendorMultiCrRegistrationTest.php, and VendorPortalConfinementTest.php. |
| Vendor contact uniqueness | The same normalized contact email cannot be registered twice under the same CR; database and application safeguards support this rule. | Implemented and locally checked by VendorContactSecuritySchemaTest.php. Existing production data and indexes must be verified after the approved database upgrade. |
| Vendor roles | OWNER, EDITOR, BIDDER, VIEWER, and CONTACT capabilities are enforced centrally and default to denial when no permission is granted. | Implemented and locally checked by VendorPortalRoleAuthorizationTest.php and VendorContactOwnerAuthorizationTest.php. |
| PTW company scope | Applicant assignments and PTW records are constrained to the authorized company. | Implemented and locally checked by PtwApplicantAuthorizationTest.php and PtwCompanyScopeSecurityTest.php. |
| Tender scope | Tender access checks company targeting, tender parentage, committee assignment, role, and workflow stage. | Implemented and locally checked by TenderCompanyAuthorizationHardeningTest.php and related tender security tests. |
| Gate-pass scope | Requests and documents are bound to the correct parent, stage, and authorized operational role. | Implemented and locally checked by GatePassParentBindingAuthorizationTest.php and GatePassStageScopedDocumentAuthorizationTest.php. |

### 4.4 Requests, input, output, and data access

| Control | Design | Status/evidence |
|---|---|---|
| CSRF | Session-based CSRF protection is enabled globally and security-relevant mutations are expected to use protected POST requests. | Implemented and locally checked by VendorCsrfHardeningTest.php and SafeHttpMutationCoverageTest.php. Browser verification remains pending. |
| HTTP methods | Sensitive mutations reject unsafe methods instead of changing state through GET. | Implemented and locally checked. |
| Clarification abuse protection | Vendor clarification submissions and staff clarification replies are limited to ten requests per minute per authenticated user and hashed source IP, with HTTP 429 and Retry-After responses before database writes. | Implemented and locally checked by TenderClarificationRateLimitTest.php. |
| Validation | Controllers and models use explicit validation and normalized identifiers before business operations. | Implemented across reviewed security paths; full dynamic fuzzing is pending. |
| Database access | Bound query parameters, strict database settings, transactions, and explicit runtime-schema ownership are used in hardened paths. | Implemented and locally checked by DatabaseStrictModeSecurityTest.php and RuntimeDdlHardeningTest.php; live MySQL verification is pending. |
| Safe serialization | Untrusted serialized values are decoded through constrained helpers rather than unrestricted object instantiation. | Implemented and locally checked by SafeUnserializeTest.php. |
| Output handling | Reviewed external and portal output paths use escaping and safer JSON/response handling. | Partially checked by ExternalControllerEscapeHardeningTest.php. This is not a complete XSS test. |
| CSV export | Export values that can be interpreted as spreadsheet formulas are neutralized. | Implemented and locally checked by GatePassCsvSecurityTest.php. |

### 4.5 Files and document access

The application centralizes file security checks for allowed extensions, MIME type, content signature, size, generated storage name, and safe path handling. Protected documents are stored outside direct public routing where applicable and are streamed only after authorization. Sensitive downloads use private/no-store and no-sniff response controls. Google Drive access uses a signed, authorized private stream rather than exposing a reusable public URL.

These controls are locally checked by UploadSecurityHardeningTest.php, GoogleDrivePrivateFileSecurityTest.php, TenderProtectedStorageSecurityTest.php, and TenderDocumentPreviewAccessTest.php.

Malware scanning is only an integration point at present. A production malware-scanning engine or service is not implemented by this repository, so malware-scanning compliance is Partial and must not be marked complete.

### 4.6 Workflow integrity and replay protection

| Workflow | Control | Status/evidence |
|---|---|---|
| Gate-pass QR | QR tokens use cryptographically secure randomness, database uniqueness, request and stage authorization, one-time scan evidence, and transactional row locking to resist replay and concurrent reuse. | Implemented and locally checked by GatePassQrReplayProtectionTest.php and GatePassIssuanceConcurrencyTest.php; real MySQL concurrency testing is pending. |
| Notification processor | Signed requests, nonce storage, freshness checking, and replay rejection protect processor calls. | Implemented and locally checked by NotificationProcessorSecurityTest.php; deployed integration testing is pending. |
| OAuth and outbound callbacks | State validation, destination restrictions, and protected callback handling exist in the reviewed paths. | Implemented and locally checked by OauthStateHardeningTest.php, OutboundIntegrationSecurityTest.php, and LegacyWebhookHardeningTest.php. Deferred payment-provider behavior is excluded. |
| Tender three-key opening | Six-digit role codes are generated securely, HMAC-protected, encrypted with AES-256-GCM and context binding, revealed only to the assigned active role, rate-limited, serialized with row locks, and scrubbed at expiry or terminal state. | Implemented and locally checked by TenderOpeningSecretHardeningTest.php. Deployment key management is defined in TENDER_3KEY_SECRET_HARDENING.md. |

### 4.7 Secrets, logging, and audit

- Deployment secrets belong in a protected environment file or an approved secret manager, never in Git, examples, SQL, documentation, logs, tickets, or chat.
- Public example values are intentionally invalid and the tender secret vault fails closed when keys are absent, short, placeholders, or identical.
- Security logs redact sensitive values and record useful authentication and workflow security events.
- Application activity records support accountability for important workflow decisions.
- Log collection, retention, alerting, SIEM forwarding, and incident monitoring are server/operations responsibilities and need deployment evidence.

## 5. Known limitations and open application assurance work

The following items must not be represented as fully complete:

1. Most local security tests are source-contract or focused unit tests. They do not replace HTTP integration tests, live database tests, DAST, or an independent penetration test.
2. No production environment was inspected by this review.
3. There is no complete automated XSS or SQL-injection attack suite. Reviewed paths contain defenses, but dynamic coverage is pending.
4. The current Content Security Policy retains unsafe-inline and unsafe-eval compatibility allowances. Removing them requires a planned front-end refactor and regression test.
5. The upload layer exposes a malware-scanner hook, but this repository does not provide the scanning engine.
6. The current release has no repository CI pipeline demonstrating automated SAST, dependency vulnerability scanning, secret scanning, or the local security suite on every change.
7. Database constraints, upgrade scripts, indexes, locking, and replay behavior must be verified on a copy of the target MySQL schema.
8. General application encryption-key strength remains deployment-sensitive for legacy compatibility. Production secrets require separate review and must not reuse the tender keys.

## 6. Delivery plan

| Phase | Application work | Exit evidence |
|---|---|---|
| 1. Current source baseline | Preserve the implemented authentication, session, access-control, request, upload, replay, and tender controls. Maintain the local security contract/unit suite. | All in-scope local tests pass and the result is recorded in SECURITY_TEST_SCENARIO.md. |
| 2. Pre-staging verification | Apply the approved database upgrade to a non-production copy; verify unique constraints, foreign keys, indexes, strict mode, migrations, and rollback/backup procedure. Add HTTP integration tests for login, logout, password change, CR switching, authorization denial, CSRF, upload/download, and replay. | Database verification record and reproducible integration-test report. |
| 3. Staging security validation | Test real roles and cross-company/CR IDOR, concurrent QR scans, tender key deployment/rotation, session/cookie headers, security headers, upload isolation, throttling, and audit output. Run SAST, dependency, secret, and DAST scans. | Staging evidence with findings resolved or formally accepted. |
| 4. Independent assurance | Conduct an independent penetration test against the agreed staging release and remediate findings before production approval. | Signed penetration-test report and closure evidence. |
| Later integrations | Implement and separately threat-model email, SMS/MFA, and payment when their providers and requirements are approved. | Provider-specific design, code review, integration tests, and security test evidence. |

## 7. Release decision

This design supports a development-security checklist, but it does not itself authorize production. Production readiness requires the application verification in Phases 2–4 plus separate evidence from the server/operations owner for the controls expressly outside this document.
