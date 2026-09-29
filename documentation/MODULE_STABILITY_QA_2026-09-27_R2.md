# Four-module verification and fixes - Revision 2

27 September 2026. Local verification only; nothing uploaded to FileZilla or the production server during this review.

## Result

The tested Gate Pass, PTW, vendor and tender workflows complete successfully after the fixes below. This is not a claim that every possible production data set, role combination or concurrent request has been tested.

## Additional bugs reproduced and fixed

1. **Gate Pass rejection could be overwritten.** An old approval request could advance a Security-rejected request to ROP, or issue an ROP-rejected request. Approval now requires the appropriate waiting status. Repeated rejected approvals leave the complete request, approval-history and pass rows unchanged. Rejected history remains readable without edit/review controls. Invalid department decision values are rejected before SQL.
2. **Malformed PTW dates reached MySQL.** An invalid work date produced a database error and lost the form entries on redirect. Dates are now validated before saving, with a field error and retained input. Blank draft dates remain supported; leap days and invalid times are covered.
3. **Malformed tender request values reached SQL.** Invalid announcement/type/evaluation values could cause enum errors. They are now restricted to supported choices. Negative and non-numeric budgets are refused. The five refusal cases preserve the tender request table. These were negative-input probes, not failures using the normal dropdown choices.
4. **Tender requester SMS ownership used the wrong column.** The queued recipient was captured from requester_id, but send-time validation used created_by. It now rechecks requester_id. Database tests cover the correct requester, an unrelated recipient, a changed requester and a deleted source. No SMS was sent to a real phone during this review.
5. **Tender schedules displayed four hours late.** A release entered as 09:00 appeared as 13:00. Schedule/deadline displays now preserve the stored Oman business time, including dates near midnight and opening-form dates. UTC creation, bid and payment audit timestamps retain their existing conversion. No stored dates were rewritten.
6. **Gate Pass inbox/QR dates could display the following day.** Visit end times at 23:59:59 were converted a second time. Department, Commercial, Security, ROP, the request list, CSV export and QR lookup now preserve the saved visit/validity dates. Real list responses, QR responses and English/Arabic row rendering were checked.

The previous September 27 fixes remain included: missing schema/outbox handling, Gate Pass notifications/QR/PDF, user-model table reset, optional user fields under strict SQL, duplicate master codes, PTW nullable references and Gate Pass translations/calendar dates.

## Workflows actually exercised

| Module | Completed locally | Negative checks |
| --- | --- | --- |
| Vendor | Guest registration with PDF; initial contact/document approval; owner login; bank account and specialty review; zero-fee registration submission and procurement approval | Duplicate CR; unpaid vendor approval refused |
| Gate Pass | Existing draft copied; submit; department return/resubmit; waiver request; Commercial waiver approval; Security return/resubmit/approval; ROP approval; issued PNG/PDF; QR entry and exit | Invalid decision; repeated department/ROP approval; stale approvals after Security/ROP rejection with raw-row equality; consumed scan nonce replay; unrelated-account access |
| PTW | New application; HSSE revision/resubmission; HSSE, HMO and Terminal approvals; final permit PDF; registered non-admin creates own draft | Invalid dates with retained input; another applicant's details/PDF and reviewer inbox denied |
| Tender | Request and manager/finance/committee approvals; RFQ and procurement-manager approval; publication; vendor participation approval; four bid documents; three separate committee logins, codes and signatures; procurement release; technical and commercial evaluation; final award | Finance before manager; publish before manager approval; bid before participation approval; missing bank guarantee; malformed fields/budget; re-publication attempt refused |

Test IDs are disposable only: issued Gate Pass 67, Security-rejected 68, ROP-rejected 69, issued PTW 24, non-admin draft PTW 26, vendor 97, tender 26/request 15. They are not production records.

Tender deadlines were advanced in the isolated database to exercise the real scheduler without waiting days. Vendor registration and tender participation used a test zero-fee setup; Gate Pass used an approved waiver. These do not certify a completed paid Bank Muscat transaction.

## Automated results

- **134/134 PHP test scripts passed** in the latest verification on PHP 8.3.33 (132 existing plus two new scripts). The collection includes static contracts, controller tests, rendering tests and real database tests; these are not 134 browser workflows.
- 196 checks in ModuleStabilityDatabaseTest, including five new tender SMS recipient checks; 74 model constructors are exercised there.
- 37 workflow-state/date checks; 346 QR/PDF/English-Arabic calendar-display checks; 17 real tender-view date checks.
- All 34 tender scripts and all 18 Gate Pass scripts rerun after their respective runtime edits. The expanded download/calendar test was rerun after its final assertions were added.
- Four focused suites also passed on XAMPP PHP 8.2.12.
- 91/91 runtime PHP files passed PHP 8.3 syntax checks; git diff whitespace check passed with Windows CRLF recognized.
- Four headless Edge pages returned 200 with no JavaScript page errors: issued Gate Pass, rejected Security history, issued PTW and awarded tender. The awarded-tender screenshot now shows the release as 09:00.
- Five Gate Pass list endpoints checked 14 displayed records against saved visit dates; QR validity dates matched the saved pass.

The initial full-suite run had one safety-guard refusal: SmsWorkflowDatabaseTest detected notifications queued by the new local workflows. Those 41 synthetic notices were processed in preview mode with transport disabled, then the affected SMS suites passed. No queue rows were deleted to force a passing result. Application logs contained the intentional pre-fix malformed-date/enum probes and expected intercepted-mail failures; no new unhandled error was found in the final tested routes.

## What can still cause deployment errors / what remains unverified

- **Destination schema:** install/check the supplied SQL against the actual target database. Missing notification, login or PTW-support columns/tables can still break an older deployment. The repair is additive and does not replace every historical schema upgrade.
- **Server access and configuration:** PHP extensions, writable cache/session/log folders, upload permissions/limits, complete code upload and OPcache refresh still matter. The target Linux server and its current data were not accessed in this review.
- **Reviewer setup:** the correct company/department assignments and active roles must exist. A missing separate department reviewer can leave no reviewer to notify.
- **Tender opening secrets:** the existing TENDER_OPENING_CODE_HMAC_KEY and TENDER_OPENING_CODE_ENCRYPTION_KEY must be set to independent secrets on the destination. QA used isolated fake secrets, which are not included.
- **External services:** live SMS/OTP, internal SMTP, Bank Muscat checkout/callback/accounting and their credentials/network allowlists still require destination verification. MFA was disabled only inside the isolated QA router; application/production OTP settings were not changed.
- **Scheduled jobs:** existing tender progression and notification/reconciliation jobs must run on the destination. This package introduces no new scheduled job.
- **Untested breadth:** every permit subtype/requirement configuration, every role combination, manual tender stage reopening, production-volume/concurrency behavior, network failures and the full paid path were not exhaustively tested. No guarantee of zero future bugs is made.

## Package / database

Use FOUR_MODULE_STABILITY_FIX_2026-09-27_R2.zip for the latest cumulative file patch. It contains 93 runtime files, including 22 changed/new files relative to the original September 27 ZIP. Keep the original archive for history; do not install it over R2.

**No additional schema changes were introduced by R2.** MODULE_STABILITY_REPAIR_2026-09-27.sql and MODULE_SCHEMA_CHECK_2026-09-27.sql are byte-identical to the previous package. Run the repair if the target has not received it, then run the read-only schema check. This does not reset fee rules, users, requests, payments or documents.

See MODULE_STABILITY_README_2026-09-27_R2.txt for installation, MODULE_STABILITY_FILES_2026-09-27_R2.txt for all runtime paths and MODULE_STABILITY_CHANGED_SINCE_R1_2026-09-27.txt for the 22 follow-up file paths.
