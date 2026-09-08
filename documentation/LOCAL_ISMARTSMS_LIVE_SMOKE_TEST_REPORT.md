# Live SMS workflow smoke test — 7 September 2026

Local application: http://www.localhost:1044/
Provider: iSmartSMS, approved sender Port Duqm.
Authorized test recipient: Oman mobile ending 5872.
Completed (UTC): 2026-09-07 11:06:46

## Results

| Flow | Provider result | Phone / application confirmation |
| --- | --- | --- |
| Vendor revision | Accepted, code 1 | User confirmed receipt |
| Gate pass return for revision | Accepted, code 1 | User confirmed receipt |
| PTW approval | Accepted, code 1 | User confirmed receipt |
| Tender technical rejection | Accepted, code 1 | User confirmed receipt |
| Login OTP | Application recorded successful send | User supplied received OTP; browser reached dashboard |

The four workflow cases exercised actual model state changes, event capture, recipient resolution, queued message processing, the live gateway, and stored provider results. Synthetic business records were rolled back; actual SMS results remain in notification history. This was a representative SMS smoke test, not a new end-to-end acceptance test of every business approval transition or every template.

## Authentication and duplicate checks

- Correct password triggered SMS verification for the temporary test identity.
- Opening the dashboard before OTP verification returned to the verification page.
- The received OTP completed sign-in and was marked consumed in the database.
- Repeating the vendor state update did not queue another message.
- Repeating the worker run did not resend processed messages.
- All test destinations were checked against the authorized number before dispatch.
- 123 gateway/integration checks and 27 database workflow/security checks passed after cleanup. These automated checks sent no SMS.

## Cleanup and current state

- Temporary user 110 disabled, soft-deleted, and its temporary password replaced.
- Reloading the protected dashboard after disabling the user returned to sign-in.
- Temporary MFA scope removed; original authentication behavior restored.
- SMS connection, automatic live workflow notifications, and login OTP remain disabled.
- Validated credentials and approved sender are retained for later activation.
- No OTP value or provider password is recorded in this report.
