# Local SmartPay smoke test — 5 September 2026

The initial 8082 run below was blocked before card entry. The later **www.localhost:1044 follow-up succeeded** for registration, renewal, tender and gate-pass fees; see the follow-up section below. Historical failures are preserved as evidence.

## Browser results

| Check | Observed result |
| --- | --- |
| Guest vendor registration | Created vendor 27 / user 96 through the public form with a synthetic PDF. Optional location and document dates can be blank. |
| Initial contact approval | Admin approved contact request 89 through Vendor Update Requests. Owner membership became active; the new user signed in to Vendor Portal. |
| Profile completion | Synthetic bank/specialty fixtures completed the five-item checklist; registration fee shown as OMR 0.100. |
| Registration checkout | Payment 1, vendor 27, OMR 0.100, order PODC2B23DB366514D1B48FF55. Reached Bank Muscat UAT; rejected with error 10002 before the card form. |
| Duplicate-payment guard | Clicking registration payment again did not create a second charge; the portal required resolution of the existing bank attempt. |
| Renewal checkout | Separate expired vendor 28 selected through Switch CR. Complete profile, OMR 0.100 renewal, payment 9, distinct order PODB3DC196D85596FF8051ED7. Local checkout prepared; not sent to bank. |
| Tender checkout | Vendor 27 opened published test tender 12. OMR 0.100, payment 2, order PODB4EB96DB1B00E821C1B174. Local checkout prepared; not sent to bank. |
| Gate-pass checkout | Department-approved test request 43, admin requester. OMR 0.100, payment 10, order POD9770CE9B5D7197CFA5E1B6. Local checkout prepared; not sent to bank. |
| Vendor accounting | Registration and renewal shown as distinct records with payer, CR, purpose, amount and state. |
| Gate-pass accounting | Record shows requester POD ADMIN, company Port of Duqm Company, request reference, reason and exact OMR amount. |
| Tender accounting | Record shows vendor/CR, payer, company, tender reference, OMR 0.100 and Awaiting checkout. |
| PTW accounting | Page loads and clearly explains that no PTW fee is configured. No PTW charge was invented. |
| Bank status recheck | Actual registration order lookup returned bank code 51313, No record found. Safe response JSON and events persisted; accounting displays a readable explanation and keeps the payment unconfirmed. |
| Recheck display refresh | Admin repeated the lookup after the display fix. The page automatically refreshed and showed the new 13:47:18 UTC request/response events with the same unconfirmed bank result. |
| Accounting role save | Role 16 created through admin UI. Accounting-only, Vendor view/bank-details/export, Gate Pass view for company 11, PTW view for company 11. Tender access and reconciliation deliberately left off for boundary tests. Saved settings verified in the database. |
| Accountant login | New local test user 98 lands directly in Vendor accounting. Menus and profile controls contain permitted accounting and own-account options. |
| Read-only financial scope | Accountant can open company 11 gate-pass transaction detail without operational assignments. No bank-response history, export or recheck control appears for that module. |
| Direct URL enforcement | Accountant receives 403 Forbidden for operational gate-pass request 43, Tender accounting and Gate Pass CSV export. |
| Browser errors | Final readable accounting-detail page has no browser warning/error logs. |

## Fixes made from smoke-test findings

- Restored SQL NULL for optional guest location IDs and document dates after the text sanitizer converted null to an empty string. Successful repeat signup verified the fix.
- Corrected the gate-pass payment action to use its actual database connection. Repeat payment initiation reached the correct checkout.
- Corrected accounting response-header initialization before CodeIgniter initializes controller response properties.
- Replaced raw JSON, hashes and internal verification codes with readable fields, statuses, next steps and activity history. Unopened checkouts are labelled Awaiting checkout.
- Made completed bank rechecks refresh saved state even when the bank has not confirmed payment; authorization/throttling failures remain errors.
- Translated bank No record found into one useful explanation instead of several missing-field alarms.
- Retained only the card last four digits, including the bank's documented merchant_param6 field. Full PAN, CVV, expiry and payment tokens are excluded.
- Corrected guest success guidance to explain initial contact approval before sign-in; made pending payment status readable.
- Added PTW accounting and independent accounting company scopes, plus an Accounting-only role restriction covering direct routes, menus, generic chat and mobile controls.

## Verification and test data

36 relevant automated suites passed with no failures, covering payment cryptography/configuration, accounting, vendor fees/workflows, gate-pass clearance, tender gates and authentication boundaries. The gateway suite includes 55 checks, configuration 37, vendor billing 23, unblock boundary 6 and gate-pass clearance 10. Real MariaDB accounting-scope tests passed 76 checks; their inserted records were rolled back. PHP lint passed the modified/new PHP files; final follow-up view and authorization checks passed after presentation refinements.

The guest registration, contact/document approvals, payment initiation and role selection above were real browser operations. Bank/specialty profile completion, the expired renewal vendor, gate-pass pre-payment stage and published tender were prepared as clearly marked synthetic local fixtures. Those fixture preparations do not count as end-to-end tests of earlier procurement/departmental approvals. See LOCAL_UAT_SMOKE_FIXTURES.md and its guarded CLI helper for exact records. No fixture marks a bank payment paid.

Only the existing targeted PTW company-scope migration was added during this smoke test and recorded in the migration ledger. Payment/authentication prerequisites had already been applied in earlier setup work. Docker containers and port mappings were not changed; the application remains on localhost:8082 and XAMPP MariaDB on 3306.

## Bank blocker and remaining UAT

Bank endpoint: https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction.

Observed bank checkout result: Merchant Authentication failed, error 10002. The official SmartPay Integration Guide, page 14, checklist item 1 states that initiation from a domain which is not registered/whitelisted produces this error. Protocol comparison against the supplied Node sample and official guide found matching encryption and hosted POST field names. This points to merchant website registration; it does not prove every supplied credential or merchant setting is correct.

The current application origin is http://localhost:8082. The earlier key labels mention http://www.localhost:1044. Confirmation of the exact bank-enabled origin and matching keys is required before changing the local origin or retrying the hosted flow. The test cards and OTP are present on page 2 of the supplied bank email PDF and have not yet been entered because the bank has not displayed its card form.

Still required after that is resolved: bank-approved success, decline and cancellation; encrypted return and independent successful status verification; actual post-payment registration/renewal review, gate-pass Commercial clearance and tender access; interrupted-return reconciliation; duplicate callback idempotency; and actual returned masked card/reference fields. Automated tests cover these rules but are not evidence of a successful hosted bank transaction.

Registration payment 1 remains verification_required and unsettled. Other attempts are unused local checkouts. Preserve these records as UAT evidence; do not change them to paid to pass the test. No credentials, test passwords, full card numbers or raw sensitive bank payloads are recorded in this report.


## Follow-up at www.localhost:1044 — successful bank UAT

The user confirmed the bank-registered local origin. Apache now serves it on loopback port 1044 alongside 8082, without changing Docker listeners. phpMyAdmin on 1044 was verified in the browser.

Using the previous project's secrets at the corrected origin still returned 10002 (registration payment 11/vendor 30). The user's Bank Muscat example contained a different access-code/working-key pair. With those two values copied into `.env`, merchant 162 was accepted by the official bank UAT checkout. The former values are preserved under the current Windows user with DPAPI encryption outside the web root; no secrets are included here.

| Payment | Fee and subject | Bank order | Tracking reference | Outcome |
| --- | --- | --- | --- | --- |
| 12 | Renewal, vendor 28 | PODA25306E3408B726314652B | 407000889227 | Paid, independently verified and applied; recovered via Status API during callback diagnosis |
| 13 | Registration, vendor 31 | POD0B8CCC8525870160C5DC75 | 407000889230 | Bank-confirmed cancellation; not applied |
| 14 | Registration, vendor 31 | POD4D211DACE05E872F95C2BE | 407000889231 | Paid, independently verified and applied; stored return compared with Status API after date-format fix |
| 15 | Tender 12, vendor 31 | POD184D94433BC35148DEAFCA | 407000889232 | Paid/applied automatically after bank return; bid form unlocked |
| 16 | Gate pass 43 | PODE34B9543EB4FEAC0344DDC | 407000889233 | Cancellation recovered through admin accounting Recheck bank status |
| 17 | Gate pass 43 | POD55A1806AF59707E0147C42 | 407000889234 | Cancellation confirmed automatically; request stayed at Commercial and Pay remained available |
| 18 | Gate pass 43 | POD4C2D69C98E60DFB2017217 | 407000889235 | Paid/applied automatically; request advanced to Security |

All successful amounts were OMR 0.100. Only the bank PDF's test-card details and dummy OTP were used on the UAT bank hostname. No live funds or real procurement were involved.

### Fixes established by actual bank responses

- Hosted POST names are `encResp` and `orderNo`; accept these and the example aliases, reject conflicting input, and preserve authenticated order binding.
- Actual bank times are Oman time. Normalize hosted DD/MM/YYYY versus API YYYY-MM-DD, comparing exact seconds and fractions where both responses supply them.
- Treat literal `null` in optional cancellation fields as absent. Mandatory independent API timestamp, tracking, amount, currency and order checks remain enforced.
- Keep public payment processing stateless so the cross-site bank POST does not replace the user's existing login session. Vendor and admin return-to-portal behavior passed.
- Give invalid return audit entries a bounded failure reason without retaining unauthenticated payloads or sensitive data. Temporary field-name-only diagnosis was removed after the actual field names were established.

### Final verification

The final payment records show verified/applied status, payer, fee purpose, subject and bank references. Actual returned card information renders as Visa / Debit card / masked last four, with readable history rather than JSON. The final gate-pass payment has a matching Commercial approval reference and a ledger subject link to request 43. Its older unused legacy `payment_transaction_id` field is not the authority; clearance checks the verified ledger.

Read-only assertions against the real local UAT database passed **28 checks**: four successful verified settlements, exactly one settlement each, three unapplied confirmed cancellations, one Commercial approval, one tender fee record, both vendors submitted to Procurement, unchanged approval-controlled renewal dates and no sensitive card/credential fields in retained responses. Admin rechecking paid gate pass 18 did not create another settlement or approval.

Updated focused suites passed: **63** gateway checks, **38** environment checks and **29** callback-input checks. Payment security, gate-pass clearance, accounting views/authorization, vendor billing and tender fee boundary suites also passed after the relevant changes. Earlier role/company scope test evidence remains above.

### Remaining acceptance coverage

A bank-provided decline scenario, full staff approval of paid registration/renewal and final registration validity dates remain to be exercised in a later acceptance pass. Manual replay of the original bank POST was blocked by automatic approval review because of possible repeated processing; it was not forced. Automated duplicate/late callback tests and the actual safe admin recheck establish the tested idempotency coverage. Prior wrong-key attempts remain unresolved evidence and were never forced to paid.
