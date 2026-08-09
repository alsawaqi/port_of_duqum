# Port of Duqm e-Services Application Security Design

Assessment date: 5 August 2026
Scope: CodeIgniter 4 `port_of_duqum` admin and e-services application.
Excluded from current release acceptance: WordPress website, production server/WAF/TLS operations, SMS OTP activation, outbound email activation evidence, and payment activation.

## Security Boundary

The application separates internal staff/admin workflows from external e-service identities. External identities can hold one or more active portal memberships, including Vendor CR membership, Gate Pass membership, and PTW applicant membership. Access is resolved from the authenticated user and current membership context, not from user-supplied CR numbers or object identifiers.

Vendor portal data is scoped to the selected vendor CR. A contact can be linked to multiple CRs through `pod_vendor_users`; if more than one active CR is available, the user chooses a CR before portal data is shown. Bank details, branches, credentials, specialties, documents, tenders and contact management are then resolved against that active CR.

## Identity And Session Controls

The login layer uses CodeIgniter sessions with secure cookie settings for production. Session identifiers are regenerated after login and sensitive state changes. Passwords are stored with PHP password hashing. Failed login and password-change attempts are throttled and persistent lockout metadata is stored in the database.

External portal identities are confined to their assigned portal surfaces. A role-less user with no active Vendor, Gate Pass or PTW membership is denied access. If the final active membership is removed, existing protected access fails closed.

## Authorization Model

Internal staff access uses the application role and permission matrix exposed through the Roles module and enforced by `Security_Controller` access helpers. Gate Pass, PTW and Tender permissions are checked both at menu level and controller/action level.

Vendor portal authorization uses selected-CR context plus vendor portal roles:

- OWNER: manages profile, contacts and tender participation for the selected CR.
- EDITOR: edits selected-CR profile data, but cannot provision contacts.
- BIDDER: participates in tenders for the selected CR, but cannot edit profile data.
- VIEWER: read-only access to the selected CR.
- CONTACT: legacy read-only compatibility role.

Object-level checks are required for tender visibility, clarification conversations, bids, protected documents, PTW company assignment, Gate Pass parent records and report/download routes.

## Request, CSRF And Input Protections

CSRF protection is enabled globally with explicit exclusions only for approved callback-style endpoints. Unsafe HTTP mutations are rejected by the safe-method filter when a route is not expected to mutate state. Hardened controllers use CodeIgniter validation rules, strict integer IDs/enums, bound SQL parameters or Query Builder calls, and output escaping in views.

Public and semi-public flows use reCAPTCHA support and/or throttling for abuse resistance. Production reCAPTCHA keys and provider evidence are deployment items.

## File And Document Protection

The upload layer uses context-specific allowlists, size limits, fileinfo MIME checks, magic/signature validation, randomized names and path containment. Security-document contexts allow `jpg`, `jpeg`, `png` and `pdf`. Sensitive new tender documents are stored under protected `WRITEPATH` storage and served through authorized controllers, not direct public paths.

The application includes a malware-scanning hook and supports fail-closed behavior when configured. A live scanner, quarantine and alert workflow must be supplied in production before the upload/malware checklist row can be fully closed.

## Workflow Integrity

Gate Pass, Vendor, Tender and PTW workflows enforce expected stage/order checks and record actor/time evidence in audit or approval history tables. Tender automatic progression is restricted to the signed scheduler path. Decision paths use locking or post-lock status rechecks where needed to reduce race conditions.

Gate Pass QR tokens are generated from cryptographic randomness, protected by a unique database index, checked only for issued passes, and recorded through a scan recorder that prevents entry/exit replay using transactional locks. Scan writes consume a short-lived one-time proof issued during QR lookup.

## Notifications, Logs And Secrets

Notification processor callback mode uses HMAC signature, timestamp tolerance and one-time nonce claims. Payload guard logic rejects caller-selected recipient lists and non-normalized entity IDs. Application logging redacts common secret fields, and sensitive values such as passwords, OTPs, session cookies, API keys and private keys must not be logged.

Outbound email and SMS activation evidence is deferred for this release. Credential email code is hardened so reusable passwords are not sent by email, but a full production email-template/recipient review remains a separate acceptance activity.

## Deferred Or External Controls

The following are not completed by source code alone:

- trusted HTTPS certificate, HSTS verification, server headers, WAF, port/method/banner hardening and OS/PHP permissions;
- database least privilege, DB TLS and encryption at rest evidence;
- encrypted backup and restore drill;
- production malware scanner activation;
- independent SAST/SCA/DAST/penetration testing and retest;
- user security training and signed supplier/PODC acceptance records;
- SMS OTP, email operations and payment activation.

These controls are tracked in `documentation/SECURITY_COMPLIANCE_REPORT.md`, `documentation/PRODUCTION_SECURITY_RUNBOOK.md` and `documentation/PROJECT_RISK_REGISTER.md`.
