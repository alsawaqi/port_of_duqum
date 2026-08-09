# Current Release Security Test Scenario

Assessment date: 5 August 2026
Scope: CodeIgniter 4 `port_of_duqum` application security verification for the current non-payment release.
Deferred from this scenario: payment activation, SMS OTP activation and outbound email service acceptance.

## Test Objective

Verify that the application prevents unauthorized access, session misuse, upload abuse, workflow bypass, cross-CR/vendor access, QR replay, unsafe callbacks and sensitive export/document leakage before production handover.

## Required Test Identities

- internal administrator;
- department, commercial, security officer and ROP Gate Pass users;
- procurement, procurement manager, technical, commercial, finance, committee and tender manager users;
- HSSE, HMO and Terminal PTW users;
- one vendor owner linked to one CR;
- one vendor contact linked to multiple CRs;
- one vendor viewer/bidder/editor;
- one Gate Pass applicant;
- one PTW applicant;
- one role-less external identity with no active membership.

## Application Test Cases

1. Authentication and sessions: invalid login throttling, persistent lockout, generic errors, session regeneration after login/password change, logout destruction, reset token single use and session-version revocation.
2. Cookie security: confirm session and CSRF cookies are `Secure`, `HttpOnly` where applicable, and have the configured `SameSite` value over production HTTPS.
3. Vendor multi-CR: verify a multi-CR contact must select a CR and only sees bank details, branches, credentials, specialties, documents, contacts and tenders for that selected CR.
4. Vendor duplicate contact safeguard: attempt to add the same normalized email under the same CR twice and confirm the application/database rejects the duplicate.
5. Vendor role authorization: verify OWNER, EDITOR, BIDDER, VIEWER and legacy CONTACT permissions using direct requests, not only hidden buttons.
6. Internal role authorization: verify direct URL denial for users without Gate Pass, PTW, Tender, Vendor Master, reports and export permissions.
7. Object-level authorization: forge IDs for another vendor, another CR, another PTW company, another Gate Pass request and another tender; verify denial and no data leakage.
8. CSRF and unsafe methods: submit protected forms without valid CSRF and try unsafe mutation through GET/HEAD/OPTIONS where not explicitly allowed.
9. Input and output handling: test SQL injection, XSS payloads, path traversal, invalid enums, invalid IDs, long strings and CSV formula payloads in public and authenticated workflows.
10. Upload security: test allowed `jpg`, `jpeg`, `png`, `pdf`; mismatch extension/MIME/magic; double extensions; traversal names; oversize files; scanner unavailable; scanner rejection; and a safe EICAR-style scanner test in an isolated environment.
11. Protected documents: attempt direct public URL access and forged download/preview IDs for tender, vendor, Gate Pass and PTW attachments; verify authorization, no-store cache controls and no script execution.
12. Workflow integrity: try skipped/repeated/out-of-order approvals for Gate Pass, Vendor update, Tender and PTW; verify actor/time history and race-condition handling.
13. Block/unblock: verify only authorized security/ROP users can block, unblock and reblock visitors; confirm downstream workflows respect active blocks.
14. QR scanning: verify only issued passes can be scanned, token format is strict, scan proof is one-time, entry/exit replay is rejected, and concurrent scans do not create inconsistent movement history.
15. Tender integrity: verify vendor clarification and bid isolation, technical/commercial submission separation, opening role separation, secret expiry and report/document restrictions. Paid-download behavior is deferred until payment activation.
16. Notification processor: verify HMAC signature, timestamp tolerance, nonce replay rejection, payload sanitization and recipient-claim rejection for the signed processor path.
17. Exports and reports: inventory CSV/PDF/Excel/download endpoints and verify only approved staff roles can access them; confirm CSV formula neutralization.
18. Error and disclosure behavior: verify forbidden/not-found/error responses do not expose stack traces, SQL, filesystem paths, secrets or internal object data.

## Regression Test Evidence

Run the current security-focused PHP contracts before handoff:

```powershell
& 'C:\xampp\php\php.exe' tests\AuthenticationHardeningTest.php
& 'C:\xampp\php\php.exe' tests\Phase2AuthenticationHardeningTest.php
& 'C:\xampp\php\php.exe' tests\VendorMultiCrAuthenticationTest.php
& 'C:\xampp\php\php.exe' tests\VendorPortalRoleAuthorizationTest.php
& 'C:\xampp\php\php.exe' tests\VendorPasswordChangeTest.php
& 'C:\xampp\php\php.exe' tests\VendorCsrfHardeningTest.php
& 'C:\xampp\php\php.exe' tests\UploadSecurityHardeningTest.php
& 'C:\xampp\php\php.exe' tests\GatePassQrReplayProtectionTest.php
& 'C:\xampp\php\php.exe' tests\NotificationProcessorSecurityTest.php
& 'C:\xampp\php\php.exe' tests\RuntimeDdlHardeningTest.php
& 'C:\xampp\php\php.exe' tests\TenderOpeningSecretHardeningTest.php
& 'C:\xampp\php\php.exe' tests\TenderCompanyAuthorizationHardeningTest.php
& 'C:\xampp\php\php.exe' tests\PtwCompanyScopeSecurityTest.php
& 'C:\xampp\php\php.exe' tests\PtwApplicantAuthorizationTest.php
```

These automated tests are application regression evidence. They do not replace production HTTP testing, SAST/SCA, DAST, a penetration test, migration evidence, scanner evidence, TLS/WAF/server scans or PODC sign-off.

## Acceptance Rule

The current release can be treated as application-ready only when:

- every non-deferred application test case above passes;
- the combined database upgrade or approved migration path is applied once and verified;
- all Critical/High security findings are fixed and retested;
- payment, SMS and email activation remain disabled or explicitly accepted as deferred;
- PODC and supplier approvers sign the final evidence pack.
