# Gate pass PDF template — 4 October 2026

This update replaces the gate pass download layout with the supplied Port of Duqm transcript design and places the supplied bilingual HSSE instruction sheet on page 2.

## Upload

Back up the corresponding server files. Copy the contents of `UPLOAD/` in the accompanying ZIP into the application root, preserving the paths:

```
app/Controllers/Gate_pass_portal.php
app/Models/Gate_pass_requests_model.php
app/Libraries/Gate_pass_pdf.php
assets/gate_pass/letterhead.jpg
assets/gate_pass/hsse-instructions.png
```

Upload all five files together. No SQL, migration, configuration, credentials, or new PHP extension is required. The existing TCPDF library, payment ledger, and writable/cache directory are used. If PHP OPcache does not check for changed files, the engineer must reload PHP or clear OPcache using the hosting facility.

This package is only the gate pass PDF update. It does not replace the separate vendor/tender points 1–6 release package. Nothing in this update has been uploaded to a server by Codex.

After uploading, open an issued gate pass in the application and use its existing PDF download button. Check both pages, the visitor name, validity dates, QR code and payment references. The sample PDF uses fictional data and a non-operational QR token; it is for visual review only.

## Design and data

- The letterhead is the unchanged JPEG extracted from the supplied `Port of Duqm - GenerateTranscript (3).pdf`. It already includes the logo, gold logistics lines, slogan and footer. The separate `Logistics Lines_AW-06.png` contains white lines with transparency, so the reference's visible gold letterhead is used instead.
- The original preview-mode banner is omitted. The blue title, black grid, companions/payment sections and red English/Arabic approval wording follow the reference. Existing bundled Helvetica/DejaVu Sans fonts render dynamic text without requiring Windows fonts on Linux.
- Page 2 reproduces the supplied `Port HSSE Instruction.pdf` as a lossless 300-dpi image on portrait A4, with the same landscape content placement as the reference. Wording, Arabic, safety icons and the evacuation map are preserved. This page is an image, so its text is not selectable. No PDF conversion runs on the server.
- Name, card/passport ID and mobile come from the assigned visitor. Email comes from the requester's user account. Dates retain their calendar date, including end-of-day validity. Company, purpose and request date come from the request. Vehicle numbers use the existing Oman/international plate formatter.
- Current visitor-specific passes remain limited to their assigned visitor and QR token. They do not automatically authorize other visitors; the companions grid stays empty. Legacy request-level passes can show their other visitors as companions.
- The application has no separate escort field, per-visitor vehicle assignment or companion remarks field. These display a dash; request-level vehicle numbers appear in the main vehicle field. No new database fields are introduced and no values are fabricated.
- Payment amounts and references are printed only for a Bank Muscat payment verified, settled and matched to this request, amount and currency. The amount is the total for the request, with three decimals. Payment Id is the local accounting row ID; Reference No is the merchant order ID; Transaction Number is the bank reference, falling back to the gateway tracking ID. Waived/free requests show “Waived”/“No charge”; missing verified evidence shows “Not recorded”. Raw bank JSON and card details are not printed.
- Long fields, extra vehicles and more than four companions continue from page 3 onward, leaving HSSE instructions on page 2. Text is printed literally, not interpreted as HTML.
- Existing authorization, issued/active eligibility, blocked-visitor checks, QR payloads, filename handling and print auditing remain in place. The renderer also refuses unissued/cancelled/mismatched passes.

## Verification

Standalone tests:

```
php tests/GatePassPdfTemplateTest.php
php tests/GatePassDownloadPortabilityTest.php
php tests/GatePassPlateDisplayTest.php
php tests/GatePassParentBindingAuthorizationTest.php
php tests/GatePassStageScopedDocumentAuthorizationTest.php
php tests/GatePassQrReplayProtectionTest.php
php tests/GatePassIssuanceConcurrencyTest.php
php tests/GatePassSecurityScanDisplayTest.php
php tests/GatePassPaymentClearanceTest.php
```

The new template test has 47 checks; portability/QR coverage has 348 checks. Template rendering was checked on PHP 8.2 and PHP 8.3, including PHP 8.3 with GD/Imagick absent. Paid, waived, person-only, Arabic, legacy, long-field, multi-page and literal-text samples were generated. Source HSSE artwork and embedded page-2 pixels were compared. Existing plate, authorization, issuance, scan and payment-clearance checks passed.

HTTP checks used the isolated local QA database: two existing issued passes downloaded as HTTP 200 PDF files; unauthenticated access and a pass ID belonging to another request were refused. No production records, live messages or bank transactions were used. The first cold QA sign-in timed out while loading application classes; retrying succeeded. This was separate from PDF generation.

Test files changed/added (not required on the production server):

```
tests/GatePassDownloadPortabilityTest.php
tests/GatePassPdfTemplateTest.php
```

The old HTML-render test now exercises the real two-page renderer; calendar-date assertions and QR pixel checks are retained. No other previous vendor/tender changes were altered.
