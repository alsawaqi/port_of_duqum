# Port of Duqm Application Security Test Scenario

## Scope and evidence limits

This scenario covers application-owned controls that can be checked locally without sending email or SMS, invoking MFA, processing payment, or changing a server. Email, SMS/MFA, and payment are deferred. TLS, firewall/WAF, OS, Apache/cPanel, database backup, SIEM, and infrastructure monitoring require separate server/operations evidence.

The PHP files under tests are mainly focused unit tests and static source-contract checks. A passing result proves that the inspected source still contains the expected control and, for some libraries, that the isolated behavior works. It does not prove that production is configured correctly, that every route is vulnerability-free, or that a penetration test has passed.

## Local prerequisites

- Run from the repository root.
- Use the project's supported PHP CLI with OpenSSL, fileinfo, and the database extensions required by the test being run.
- Use only non-production test data.
- Do not place real credentials or tender keys in the shell, repository, or test output.

## Reproducible local security suite

The following PowerShell command runs the current application-security suite while excluding the deferred email, SMS/MFA, and payment-specific tests:

    $tests = @(
      'AnnouncementExternalIdentityIsolationTest.php',
      'AuthenticationHardeningTest.php',
      'AuthSessionVersionAtomicityTest.php',
      'CliMigrationEntryPointTest.php',
      'CombinedManualSecuritySqlTest.php',
      'CronEndpointHardeningTest.php',
      'DatabaseStrictModeSecurityTest.php',
      'ExternalControllerEscapeHardeningTest.php',
      'ExternalPortalAccountSecurityTest.php',
      'FrameworkConfigCompatibilityTest.php',
      'FrameworkRuntimeVersionTest.php',
      'GatePassCsvSecurityTest.php',
      'GatePassIssuanceConcurrencyTest.php',
      'GatePassParentBindingAuthorizationTest.php',
      'GatePassQrReplayProtectionTest.php',
      'GatePassStageScopedDocumentAuthorizationTest.php',
      'GoogleDrivePrivateFileSecurityTest.php',
      'LegacyWebhookHardeningTest.php',
      'MixedExternalPortalAccessTest.php',
      'NotificationProcessorSecurityTest.php',
      'OauthStateHardeningTest.php',
      'OperationalSelfPasswordChangeHardeningTest.php',
      'OutboundIntegrationSecurityTest.php',
      'Phase2AuthenticationHardeningTest.php',
      'PortalIdentityBoundarySecurityTest.php',
      'PrivateControllerActionSecurityTest.php',
      'ProductionTransportHardeningTest.php',
      'PtwApplicantAuthorizationTest.php',
      'PtwCompanyScopeSecurityTest.php',
      'PublicAbuseProtectionTest.php',
      'RoleMenuPermissionCoverageTest.php',
      'RuntimeDdlHardeningTest.php',
      'SafeHttpMutationCoverageTest.php',
      'SafeUnserializeTest.php',
      'ServerComplianceHardeningTest.php',
      'ServerExposureHardeningTest.php',
      'SigninRedirectSecurityTest.php',
      'TenderCombinedVendorTargetingTest.php',
      'TenderClarificationRateLimitTest.php',
      'TenderCompanyAuthorizationHardeningTest.php',
      'TenderDocumentPreviewAccessTest.php',
      'TenderOpeningSecretHardeningTest.php',
      'TenderProtectedStorageSecurityTest.php',
      'TenderWorkflowSchedulerSecurityTest.php',
      'UploadSecurityHardeningTest.php',
      'VendorContactAccessWorkflowTest.php',
      'VendorContactOwnerAuthorizationTest.php',
      'VendorContactSecuritySchemaTest.php',
      'VendorCsrfHardeningTest.php',
      'VendorIdentityFoundationTest.php',
      'VendorMultiCrAuthenticationTest.php',
      'VendorMultiCrRegistrationTest.php',
      'VendorPasswordChangeTest.php',
      'VendorPortalConfinementTest.php',
      'VendorPortalRoleAuthorizationTest.php',
      'VendorUpdateRequestDecisionLockTest.php'
    )

    $failed = @()
    foreach ($test in $tests) {
      & php (Join-Path 'tests' $test)
      if ($LASTEXITCODE -ne 0) { $failed += $test }
    }
    "Executed: $($tests.Count); Failed: $($failed.Count)"
    $failed

## What the local suite covers

| Test group | Principal checks | Evidence type |
|---|---|---|
| Authentication/session | Password hashing and policy, rate limiting, lockout contracts, session renewal/versioning, safe redirects, current-password change | Static contracts plus isolated library/model tests |
| Authorization/isolation | Internal-versus-external boundary, private actions, role/menu coverage, vendor CR selection and scope, PTW company scope, tender company scope | Predominantly static source contracts |
| Request/data safety | CSRF and POST-only mutations, strict DB/runtime DDL rules, safe unserialize, abuse protection, output-escaping contracts | Static contracts and focused unit checks |
| File/document safety | Upload type/path contracts, protected tender storage, authorized document preview, signed Google Drive stream, CSV formula neutralization | Static contracts and focused helper tests |
| Replay/workflow integrity | Gate-pass QR uniqueness/replay/concurrency design, notification signatures/nonces, OAuth state, webhook/outbound restrictions | Static contracts plus focused cryptographic/helper tests |
| Tender opening | CSPRNG code creation, HMAC, AES-256-GCM, context binding, role assignment, failure limits, row locks, expiry/scrubbing, key-deployment documentation | Runtime vault checks plus static contracts |
| Deployment contracts | Environment examples, transport/server expectation files, schema upgrade safety, cron authentication | Static contracts only; not live server proof |

## Local execution result

The result table below must be updated only from an actual local run of the exact suite above.

| Date/environment | PHP | Executed | Passed | Failed | Meaning |
|---|---|---:|---:|---:|---|
| 2026-08-08, local Windows/XAMPP workspace (Asia/Muscat) | PHP 8.2.12 | 56 | 56 | 0 | The exact suite above passed locally. This is contract/unit evidence only, not staging or production verification. |

## Pending staging and independent security checks

These checks remain pending even when every local PHP test passes:

| Pending check | Required staging evidence |
|---|---|
| Authentication and sessions | Browser/HTTP tests for login, logout, expiry, identifier regeneration, lockout, disabled account, password change, multi-CR selection, and actual Secure/HttpOnly/SameSite cookies. |
| Authorization and IDOR | A role-by-role matrix attempting cross-user, cross-company, cross-CR, wrong-parent, and wrong-stage access through real routes and identifiers. |
| CSRF and HTTP methods | Requests with missing/invalid tokens and unsafe methods against every security-sensitive mutation. |
| Database integrity | Target-version MySQL tests for applied constraints/indexes, duplicate prevention, transactions, row locks, nonce replay, and concurrent QR scans. |
| Uploads and downloads | Malicious filenames, double extensions, MIME/signature mismatch, oversized files, traversal, unauthorized reads, cache headers, and the selected malware-scanning service. |
| Dynamic application testing | Authenticated and unauthenticated DAST for XSS, SQL injection, path traversal, request smuggling exposure, security headers, and common OWASP web risks. |
| Supply chain and secrets | CI evidence for dependency vulnerability scanning, SAST, secret scanning, and repeatable execution of the local suite. |
| Tender key operations | Staging deployment, fail-closed readiness, active-role display, attempt limits, expiry cleanup, atomic rotation, and incident procedure. |
| Independent penetration test | A qualified independent report against the release-candidate staging build, followed by remediation and retest evidence. |

No production verification or production-security certification is claimed by this document.
