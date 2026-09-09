# Vendor sign-in using email or CR

Requested 8 September 2026. This supersedes the email-only credential behavior recorded in the earlier local smoke reports; those reports remain historical evidence.

- Email and personal password keep the existing flow: one accessible vendor opens directly; multiple accessible vendors show CR selection. Internal staff email login keeps its existing dashboard route.
- CR and personal password resolve one active, login-enabled vendor member and open that exact vendor. CR comparison trims whitespace, ignores letter case, and preserves leading zeros. Roles and permissions remain those of the matched person.
- A contact may use their own password with a CR they can access. If multiple eligible people on the CR share that password, CR login refuses and directs the user to their registered email; it never selects the first matching account. There is no shared company password.
- Existing account, vendor and membership eligibility applies. Deleted, rejected or suspended access cannot be recovered by supplying a CR. Vendor statuses otherwise follow the existing portal policy, including application and renewal access.
- OTP remains subject to the configured MFA policy. It is delivered to the matched user's destination. The verified CR is held in the server-side pending OTP session, rechecked after verification, and cleared on completion or expiry. Revocation during OTP denies access rather than opening another CR. Posted vendor IDs cannot override this context.
- Label and placeholder say **Email or CR number**, with English and Arabic helper/error text. Password recovery still uses email. No database or environment changes are required.

## Verification

`tests/VendorCrSigninDatabaseTest.php` exercises the real credential model, sign-in/select/OTP controller methods, membership queries, password upgrade and one-time challenge storage against the local database. Only SMS delivery is replaced with an in-memory provider and HTTP/session state is supplied by the test. Its 38 checks passed on PHP 8.1.34 and 8.2.12; all fixture and audit writes were rolled back and no SMS was sent.

Coverage includes both CRs for one owner, contact-specific identity, single- and multiple-CR email login, forged selection, case/whitespace/leading-zero handling, invalid credentials, inactive users/memberships/vendors, shared-password ambiguity, MD5 upgrade, mandatory OTP, wrong/expired OTP, post-OTP context binding and revocation.

All 23 related authentication, vendor and SMS non-database suites passed on PHP 8.1.34. Changed PHP files passed syntax checks and `git diff --check` passed. The existing `VendorMultiCrAuthenticationTest.php` assertions now describe the requested shared email/CR input and explicit CR routing; its membership and session safeguards remain asserted.

## Deployment

Deployed the five changed application files to `https://poderp.bedots.site/` on 8 September 2026. Each production original matched the reviewed Git baseline (ignoring line endings) before upload, and all five deployed files matched local SHA-256 hashes afterward. Originals are retained under `/writable/codex-deploy-20260908-vendor-cr-signin/backup/` on the server. No database, credential or environment settings were changed.

Reloaded the production page in Chrome and inspected its DOM and screenshot: the label, placeholder and helper text display both methods correctly. No production customer login or live SMS was attempted for this change. Production SMS delivery remains subject to the previously reported provider IP block; this change does not enable or disable OTP/SMS settings.
