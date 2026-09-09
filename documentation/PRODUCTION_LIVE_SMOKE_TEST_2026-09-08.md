# Production live smoke test — 8 September 2026

Target: `https://poderp.bedots.site/`, PHP 8.1.34. This is a live test run using clearly named synthetic records, separate browser sessions and normal application forms. It is **not yet an acceptance sign-off**. A page-load check is not a full transaction test.

## Evidence and test accounts

Private local evidence directory: `C:\Users\Admin\AppData\Local\Temp\pod-live-smoke-20260908`.

- `test-credentials.json`: generated credentials for synthetic users. Do not upload or commit this file.
- `results.json`: transaction checks and actual responses; some responses contain synthetic account details.
- `admin-page-scan.json`: 66 unique admin menu destinations checked in authenticated browser sessions. All returned HTTP 200, with no observed application JavaScript exceptions or failed same-origin HTTP requests during this scan. This does not exercise every button, edit or export.
- Test PDFs and PNGs are visibly synthetic. No real identity or commercial evidence was uploaded.
- The existing admin account was used for approvals; no new broad Admin account was created.

## Workflow coverage

| Area | Live evidence | Status |
| --- | --- | --- |
| Vendor guest registration | Vendor A 33 / CR SMOKE20260908A; Vendor B 34 / CR SMOKE20260908B | Passed initial registrations and contact approvals |
| Vendor login | Initial unapproved contact refused; approved owner signs in; email shows two CRs; selecting B opens B; direct CR A login opens A | Passed |
| Contact access | Viewer contact 47 approved via request 102; email and own-password CR A login work; CR B refused; controls read-only; contact save with valid CSRF redirected to forbidden | Passed |
| Access boundaries | Vendor Viewer refused staff accounting, PTW 9 issued PDF, and another applicant's gate pass | Passed representative checks |
| Vendor profile | Bank account 14, branch 15, specialty 17, required OCCI document 24 approved | Passed |
| Vendor location regression | Vendor B 34 saved with country Oman and blank optional region/city; reopening preserved the blanks and payment terms 45 | Passed; initial missing-payment-terms refusal had an unclear generic message |
| Vendor revision/rejection | Specialty request 98 returned, amended and approved; replacement OCCI request 101 approved after rejecting synthetic upload 23/request 100 | Passed |
| Vendor registration payment | OMR 30 checkout reached Bank Muscat UAT; correct merchant and reference POD5EB4F9584C85C2534EBC92 | Handoff passed; successful payment and vendor final approval pending |
| Gate pass normal fee | GP-2026-000045: guest account, visitor 49, vehicle 26, mandatory files, department return/amend/resubmit/approve, commercial fee OMR 3 | Passed to payment |
| Gate bank checkout/return | Correct OMR 3 and reference PODDE4AEEEBA21472EB18E2FE; Cancel returned to application (303 then result HTTP 200) | Return captured; bank verification unresolved; remains unpaid |
| Gate fee waiver | Separate GP-2026-000046, visitor 50: department request, commercial waiver approval, Security approval, ROP approval | Passed to Issued; not a substitute for normal payment test |
| Gate issued documents | Pass GP-2026-000046-V000050 / QR ID 20; QR PNG downloaded and decoded; one-page pass PDF 106,086 bytes; visitor photo downloaded byte-identically | Passed |
| Gate QR actions | Valid issued QR entered in actual Scan QR form; Entry, Exit and Check each saved one event; duplicate Entry refused HTTP 409 with zero saves | Passed; requester history shows exactly three events. No physical entry/exit or camera hardware test |
| PTW | PTW-2026-000009: full applicant form, mandatory documents and PPE, signature, submit, HSSE revision/resubmit, HSSE/HMO/Terminal approvals | Passed; Approved / Completed |
| Issued PTW | Authenticated final permit download HTTP 200, 110,544 bytes, three readable PDF pages with the correct reference | Passed |
| Tender request | Request 12 / POD-SMOKE-20260908-T1: scoped department applicant, separate department-manager login, finance approval, separate committee-chair login/approval | Passed |
| Tender setup | Tender 12 draft, OMR 0.500 fee, normal milestones, distinct team, four required bid sections and RFP PDF 13 | Passed |
| Tender procurement review | Scoped procurement upload; manager return; procurement amendment/resubmission; manager approval | Passed; draft approved for publishing |
| Tender participation and award | Publication to synthetic approved vendors, document purchase, clarification, bids, three-key opening, evaluations and award | Not yet tested; vendor payment/approval prerequisite pending |
| Accounting | Vendor transaction 1 and gate transaction 2 display payer, reason, reference, amount, timestamps and status with readable labels | Passed pending/needs-review visibility; no verified paid receipt tested |
| Accounting exports | Four filtered CSV exports returned HTTP 200: vendor and gate each one transaction, PTW and tender headers only because no payment exists | Passed |
| File access | Bank letterhead plus gate ID/licence/Mulkiyah, two PTW attachments and tender RFP downloaded byte-identically to original test PDF | Passed |
| SMS/OTP | Live settings were off; mandatory OTP off; 38 accounts lack valid mobiles | Not tested in this run; no SMS sent |
| SMTP | User deferred SMTP previously | Not tested |
| Recurring jobs | Hosting scheduler access unavailable | Not installed or executed in this run |

## Fixes made and verified during this run

1. **Omani registration plate displayed as a dash.** `gate_pass_vehicle_plate_display()` incorrectly preferred an empty international-plate field. It now chooses the appropriate field for the plate type, with a legacy fallback. Nine regression cases pass on PHP 8.1.34; related gate-pass helper, payment-clearance and issuance suites also passed. Live request 45 now displays `T 90826` correctly.
   - Changed: `app/Helpers/general_helper.php`; added `tests/GatePassPlateDisplayTest.php`.
   - Verified deployed SHA-256: `5b31b038d40e6662e41e7fdadb05a1f84190049f67ccd63e3b3e2e0d5f53c2e4`.
   - Remote backup: `/writable/codex-deploy-20260908-plate-display/general_helper.php.backup`.
2. **Department gate-pass queue failed to initialize.** The `building` icon was unavailable in the bundled Feather version; its exception stopped table initialization. Changed to the supported `briefcase` icon. PHP 8.1 syntax check and actual live queue loading passed.
   - Changed: `app/Views/gate_pass_department_requests/index.php`.
   - Verified deployed SHA-256: `631d3a43fa66253c890f55bf7fe7ef7430620f827b107350ca58bf0017a404bf`.
   - Remote backup: `/writable/codex-deploy-20260908-department-icon/index.php.backup`.

3. **Tender request dropdowns failed to initialize.** The form supplied options incompatible with bundled Select2 3.x. Native selects now use their existing `multiple` attributes; AJAX supplier search uses the supported input widget, with initial selections and the existing submitted payload preserved. Existing-widget detection now uses Select2's instance data. PHP 8.1 syntax check and the committee-selection, evaluation-weights and vendor-targeting tests pass. Live form opening, Close tender type, supplier AJAX search and team selection were retested.
   - Changed: `app/Views/tender_requests/modal_form.php`.
   - Verified deployed SHA-256: `7a25fcbce82c8ea6915ff570b63241ec8f07008d369bbb0a49d6eb2464adc9b5`.
   - Remote backup: `/writable/codex-deploy-20260908-tender-select2/modal_form.php.backup`.

All three deployments compared the live baseline, preserved backups, staged the replacement and verified uploaded bytes. Existing unrelated working-tree changes were preserved.

## Open findings and limitations

- **Bank independent verification is unresolved.** The vendor recheck and gate Cancel return did not yield a verified receipt. Both records remain unpaid and marked for accounting review. The gate cancellation return was decrypted and recorded with bank reference `407000957335`, OMR 3.000 and bank time 08/09/2026 21:09:12. This proves return capture, not independently confirmed settlement/cancellation. No card was submitted in this run. The test-card/SMS question is pending. The limited server-log read did not establish a current bank API failure cause; do not present a historic timeout as a newly proven diagnosis.
- **Vendor required-field validation — resolved in the application-fixes follow-up below.** The initial missing Payment Terms refusal returned a generic error. Both browser validation and production server responses now identify the field; successful correction and saving were retested.
- **SMS follow-up: existing production rejection confirmed.** A later read of Settings → SMS found the earlier `PRODUCTION-CHECK-20260908` test at 12:34:30 UTC, with provider rejection code 20 (server IP blocked). The saved diagnostic result agrees; the same-day network diagnostic measured outbound IP `92.205.177.137`. The SMS connection, workflow switches, real sending and mandatory OTP are currently off; username, sender and a saved password are present. The adapter already sends the provider's HTTP GET parameters with URL encoding. This is historical rejection evidence, not a new send or proof that the provider still blocks the IP now. No SMS was sent or settings changed during this follow-up. The earlier statement that no SMS failure had been confirmed omitted this existing production record. The local iSmartSMS integration regression passed 123 checks without network requests. Provider access and a successful server-origin test still need confirmation; automated workflow notifications also require queue processing, while login OTP sends immediately.
- **Blank optional dates — resolved in the application-fixes follow-up below.** Zero/invalid dates now display as missing instead of negative years. A missing document expiry also no longer receives an incorrect Expired badge. Normal datepicker typing remains distinct from the earlier automation `.fill()` issue.
- **PTW sidebar filter — resolved in the application-fixes follow-up below.** Legacy saved menu entries beneath PTW now resolve to the permission-filtered PTW Request List. The actual live sidebar link and populated destination were verified.
- Vendor credential CRUD, vendor final approval/renewal, paid gate issuance, PTW fee paths if configured, tender bid/evaluation/award, exhaustive company isolation, notification delivery, scheduling, mobile layouts and every master-data CRUD operation remain outside completed coverage.
- Tender 12 still targets the existing Local group. Retarget to approved synthetic vendors before publication to avoid involving other vendors. No tender was published or awarded.
- Normal date-based tender milestones were retained (closing 10 September; evaluation deadlines 13 September). No workflow stage override, database payment bypass, bulk cron execution or authentication bypass was used.
- Browser automation had selector/date-entry/timing and confirmation-dialog problems. These were checked against persisted UI state and are not counted as application failures.

## Resume points

### Payment configuration and verification retry — 8 September, 18:20–18:22 UTC

After the user asked to try the updated settings, a read-only FTPS check confirmed that the production `.env` contains the merchant ID and access code, a 32-character alphanumeric working key, the exact UAT checkout URL, and the correct website base URL. There were no duplicate payment fields or detected placeholder values; no separate Status API credential overrides were set. Secret values were neither printed nor copied into this report. Presence and syntax do not prove that the bank accepts a credential.

Actual admin **Recheck bank status** actions were run for gate payment 2 and vendor payment 1. The ledger recorded new requests/results at 18:20:54–18:20:59 UTC and 18:21:56–18:22:01 UTC respectively. Both still show **Needs bank verification / Needs accounting review**, with no independent bank confirmation. This retry did not submit a new card payment or change either payment to paid. It confirms that the updated configuration is being used by a functioning verification action, but does not establish the precise provider/network failure. The earlier network diagnostic reported a UAT Status API timeout; current server-to-bank access remains a follow-up item.

Private evidence: `payment-config-retest.json`, `payment-verification-retest.json`, `vendor-payment-verification-retest.png` in the evidence directory above.

### Remaining work

1. Resolve the bank confirmation issue, then complete a UAT success using approved test-card details and verify one receipt, the correct workflow transition and readable accounting history. Continue paid gate request 45 through issuance after payment is independently confirmed.
2. Finish vendor approval, renewal and approved-vendor tender eligibility.
3. Publish tender 12 only to synthetic vendors and exercise purchase, clarification, bid submission, opening, evaluation and award with separate roles. Its actual schedule and approval rules must remain respected.
4. Test SMS/OTP after the pending test-message question and provider/configuration readiness; SMTP remains deferred.
5. Continue remaining master-data, permission and mobile acceptance coverage. The known UI findings above are resolved; the 66-page scan is not a replacement for every transaction.
6. Retain the clearly marked synthetic fixtures for review. No automatic cleanup has been performed; they include an issued waiver pass valid on the test date. Existing business data must remain unchanged.

## Application fixes follow-up — 8 September 2026

The owner requested fixes for the remaining application findings while leaving payment verification and SMS provider access pending. No bank or SMS requests were made in this follow-up, no payment was bypassed, and integration credentials/settings were preserved.

### Changes deployed and verified

- **Vendor validation:** Currency and Payment Terms participate in Select2 validation. The save action checks permissions first and returns readable, field-specific validation errors in production. All returned field errors are displayed as text beside the relevant input. Duplicate-CR refusal leaves the modal unmasked and usable. Vendor B 34 was saved with its existing values and reopened; region/city remained blank and Payment Terms remained 45.
- **Dates:** The shared date helper rejects zero and impossible calendar dates before DateTime can normalize them. Tender review displays a dash for the missing required-on date. Vendor documents/credentials display dashes for missing dates; the expiry badge only classifies valid dates. Actual vendor document rows with valid 2026/2027 dates remain unchanged, while the rejected legacy row has two dashes and no Expired badge. No bulk date/data repair was performed.
- **PTW navigation:** A built-in gate filter mistakenly stored beneath PTW in an older saved menu is normalized to the existing PTW request-list item before permission resolution. Explicit custom URLs and Gate Pass/ROP groups are preserved. Selecting the corrected live sidebar item opened the PTW list containing approved permit 9.

Six application files were deployed through the saved FTPS connection after exact baseline comparisons, protected backup creation and staged replacement. Uploaded bytes were verified. The first five files are backed up under `/writable/codex-deploy-20260908-app-smoke-fixes`; the vendor expiry follow-up is backed up under `/writable/codex-deploy-20260908-vendor-date-display`.

| File | Deployed SHA-256 |
| --- | --- |
| `app/Controllers/Vendors.php` | `deeaf140f0d5157a8dc44aeacc533583ba66123b9ae4fc2bf94e09dd76e15d26` |
| `app/Views/vendors/modal_form.php` | `5d7dcb735f6113949d35cd28bb6d8c05645db39cce8321008e4e8af7f41f1fcc` |
| `app/Helpers/date_time_helper.php` | `403ae00597f0820756e4ba2fdd69ced3dcf690f3473e148c312d34fac08362ed` |
| `app/Views/tender_procurement_manager_inbox/details.php` | `a1a8305acc1b505f0ed8365fd46ff70d2f26269f1f5517196d7aeb8b39957641` |
| `app/Libraries/Left_menu.php` | `4d904d5e3982fbdfdd504fc26eaba66c1d5dcd9cb72df026df470992c0f79590` |
| `app/Controllers/Vendor_portal.php` | `68f59526d5285a066d88d3689860834cbfe529fd4b2f8d1284fd637faf3a1781` |

### Verification

- All six changed PHP files pass syntax checks on PHP 8.1.34.
- 70 related vendor, gate-pass, PTW, tender and menu regression scripts passed. These contain a mixture of behavioral and source-contract tests; they are not 70 full live workflows.
- `VendorLocationDatabaseTest.php`: 114 checks passed (previously 94), including complete row equality after invalid submissions. Tests use an isolated local database and clean it up; no delivery occurs.
- `OptionalDateDisplayTest.php`: 44 checks passed, covering invalid dates, leap years, valid dates and timezone conversion.
- `PtwSavedMenuFilterTest.php`: 14 checks passed (included in the 70), including refusal when only gate-pass permissions are present.
- `VendorDocumentExpiryDisplayTest.php`: nine further checks passed. Vendor workflow/role regressions and the date tests passed again after the expiry follow-up.
- 18 live checks passed: missing field feedback, production response, corrected save/reopen, duplicate CR refusal, mobile layout, PTW navigation, populated PTW list, tender/vendor date displays, three stale PTW review refusals, and eight PTW access-boundary checks for vendor Viewer and gate applicant sessions. Stale HSSE/HMO/Terminal decisions were refused and the completed PTW list record was identical before/after. No application JavaScript exceptions were recorded on the monitored fix pages.
- Desktop/mobile screenshots were inspected. Early automation attempts addressed a hidden Select2 input, used the wrong link role for a tab, or captured a table before its AJAX load; they were corrected and do not count as application failures. The final vendor screenshot and assertion explicitly wait for all three document rows.

Private evidence: `app-fixes-deployment.json`, `vendor-date-display-deployment.json`, `app-fixes-regressions.json`, `app-fixes-live-results.json`, `vendor-required-field-desktop.png`, `vendor-required-field-mobile.png`, `ptw-filter-corrected-live.png`, `tender-optional-date-live.png`, and `vendor-document-dates-live.png` in the evidence directory listed above.

The known application defects listed in this report are resolved. This is still not a complete acceptance sign-off: bank/SMS verification remains deferred, payment-dependent vendor/gate/tender stages remain uncompleted, SMTP is deferred, and the hosting administrator must install the previously prepared recurring jobs. No new approval request or broad cron execution was attempted.
