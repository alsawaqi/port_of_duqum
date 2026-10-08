# Point 4 — vendor email/CR login verification

Verified on 3 October 2026 against the current local application, including points 1–3. No application-code or database-schema change was needed for this point. No production deployment was performed.

## Confirmed behavior

| Login | Result |
| --- | --- |
| Registered email + password, one accessible CR | Opens that vendor company directly. |
| Registered email + password, multiple accessible CRs | Shows company names and CR numbers. The user selects one before accessing the portal. |
| CR + the linked person's password | Opens the entered CR directly, including when that person belongs to other companies. |
| Approved, active vendor contact | Uses their own login identity and their assigned access level for the selected company. |
| Applicant awaiting payment or waiver approval | May sign in to the limited registration status/payment page; profile and tender actions remain locked. |

The sign-in placeholder already says **Email or CR number**. A user with several CRs can subsequently use **Switch CR**. The internal vendor ID sent by the browser is checked against that user's accessible memberships; changing it or a query string cannot grant access to another company.

Adding a contact does not by itself grant active access: the contact/account must complete the existing approval and credential-activation flow. Disabled accounts and suspended, invited or deleted memberships cannot be used to enter that CR. Revoked company access is rechecked on subsequent portal requests.

For internal staff who also have vendor memberships, ordinary email login retains the staff dashboard routing; an explicit authorized CR login opens the vendor company.

## OTP and passwords

When OTP is enabled for the user, password verification is followed by OTP before the authenticated session is established. A CR chosen through verified credentials is retained through OTP and checked again afterward. Email login for several CRs still presents company selection after OTP succeeds.

OTP uses the matched person's registered mobile number, not an arbitrary company/contact number submitted with the login. Tests checked the matched destination, wrong and expired codes, and revocation of CR access while OTP was pending.

If two active people on the same CR share the same password, the CR/password pair cannot identify which person is signing in. The system rejects that ambiguous pair and offers registered email login. Email login remains available. There is no separate company-wide CR password.

## Checks performed

- **38 database/controller integration checks:** owner and contact credentials, CR normalization and leading zeros, email/company selection, unrelated CR and invalid password refusals, disabled/deleted accounts, inactive/deleted memberships, unavailable vendors, ambiguous passwords, legacy-password upgrade and OTP behavior. Transactional fixtures were rolled back.
- **34 HTTP workflow checks:** three synthetic users (owner, single-CR contact, multi-CR contact), authorized company selection and switching, attempted company-ID overrides, revoked access, disabled accounts, and pending-registration restrictions.
- **11 browser checks:** visible sign-in placeholder, email/CR submission, company choices, selection and switching, correct workspace, desktop/mobile layouts and console/runtime health. No browser errors were observed.
- Existing multi-CR, contact workflow, contact ownership/security, portal confinement/authorization, identity-foundation and billing checks passed; billing reported 35 passing checks.

Tests ran against a separate disposable MySQL data directory and local HTTP server. OTP delivery was simulated in memory; no live SMS, email or bank request was sent. Production provider delivery was not retested. Test servers were stopped afterward. The main development database and production settings were not changed.

## Deployment

There are no additional PHP files or SQL changes to upload for point 4. This document records verification only. Points 1–3 retain their own deployment instructions and SQL requirements.
