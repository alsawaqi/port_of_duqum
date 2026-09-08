# Local UAT smoke-test fixtures

Created on 2026-09-05 for the user's authorized localhost payment smoke test. These are test records, not real supplier registrations, procurement or port access approvals. No existing business rows were changed to create them.

| Record | ID | Details |
| --- | --- | --- |
| Vendor group | 5 | POD-UAT-SMOKE Payment Test; code POD-UAT-SMOKE-20260905; 30-day registration validity |
| Registration fee | 5 | OMR 0.100, effective 2026-09-05 |
| Renewal fee | 6 | OMR 0.100, effective 2026-09-05 |
| Required document type | 5 | POD-UAT-SMOKE CR Test Document, only for test group 5 |
| Guest-created vendor | 27 | POD UAT Smoke Vendor 20260905; CR UAT20260905; created through guest UI |
| Guest-created owner user | 96 | Test identity; password not recorded here |
| Owner contact | 40 | Initial contact approved separately through the admin UI |
| Synthetic bank account | 14 | Pending; not a real account; update request 91 |
| Synthetic specialty | 17 | Pending; IT/Maintenance; update request 92 |
| Renewal prerequisite vendor | 28 | POD-UAT-SMOKE Renewal Vendor 20260905; CR UATREN20260905; existing owner user 96 |
| Fresh registration fixture | 30 | POD-UAT-SMOKE New Vendor B 20260905; CR UATNEW20260905B; existing owner user 96 |
| Fresh registration fixture C | 31 | POD-UAT-SMOKE New Vendor C 20260905; CR UATNEW20260905C; existing owner user 96 |
| Gate-pass request | 43 | POD-UAT-SMOKE-20260905-GP; requester is local admin ID 1; company 11 / IT department 3; OMR 0.100 |
| Tender request | 12 | POD-UAT-SMOKE-20260905-TR; local test request with budget OMR 200.000 |
| Tender | 12 | POD-UAT-SMOKE-20260905-TN; published/open/bidding; OMR 0.100; closes 2026-09-12 |

The gate-pass fixture was created directly at department-approved/commercial stage with a marked test approval and synthetic visitor. The tender fixture was created directly at published stage with marked test procurement prerequisites. These fixture operations do not test the preceding departmental/procurement approvals. Their purpose is to reach the payment and accounting stages using new isolated rows.

The guest vendor is created through the UI separately. Guest registration creates an invited vendor membership and pending contact approval. A staff member must approve that contact before the new vendor identity can sign in. Registration review must still require verified payment; this script does not mark payments as paid or approve vendor registration.

Vendor 28 is a separate historical-state fixture created specifically for renewal testing while vendor 27's first registration remains unpaid. Its synthetic registration is expired (valid 2026-08-06 through 2026-09-04), with an active owner membership and approved synthetic profile records. This is a test prerequisite, not evidence that an earlier registration payment happened. No historical payment ledger rows were created. The document reuses the harmless uploaded smoke-test PDF belonging to the same test owner.

Vendor 30 is a separate new-registration fixture prepared after the bank-registered origin was confirmed as `http://www.localhost:1044`. Its initial status is `new` with no registration validity dates or payment records. Its synthetic profile is complete and its owner contact is activated as a local test prerequisite. The original vendor 27 and unresolved registration payment 1 remain intact. Vendor 30 is also a specific target for tender 12.

Vendor 31 is the next fresh registration fixture after the current project's credentials still produced merchant-authentication errors at the correct origin. The root test operator changed to the user's example credentials, which allowed the renewal checkout to reach the hosted card form. Vendor 31 starts at `new`, with no dates or payment records, and uses the same synthetic profile/active owner prerequisite. It is also targeted for tender 12. Creating it does not alter any earlier failed or unresolved payment attempt.

`documentation/tools/local_uat_smoke_fixtures.php` is CLI-only and refuses production or non-local database settings. It provides `--inspect`, `--masters`, `--create-group`, `--create-flows`, `--create-document`, `--complete-profile`, `--create-renewal-fixture`, `--create-new-registration-fixture`, `--create-new-registration-fixture-c` and `--status`. Creation operations reuse the exact named fixtures on repeat runs; they never delete records or call the bank. `--complete-profile` is restricted to the exact guest-created smoke vendor, adds pending bank/specialty records and their approval requests, and targets tender 12 to that vendor; it does not activate contacts or approve registration. `--status` reports only the fixture records and related payment states, with no bank keys or raw payment payloads.

`documentation/tools/POD-UAT-SMOKE-test-document.pdf` is a synthetic document for testing the upload workflow. It is plainly labeled as a local test document and contains no personal or bank information.

Account passwords and payment credentials are not recorded here.
