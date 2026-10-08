PORT OF DUQM - TENDER DOCUMENT TEMPLATES
Date: 04 October 2026

PURPOSE
Use the supplied PODC Bid Opening Record and Regret Letter templates in the tender workflow. The supplied first-page and continuation letterheads are bundled locally. The layout, fields and wording are reproduced with the existing bundled Unicode PDF font.

HOW TO USE
1. After the bid opening has been unlocked, open its Bid Opening Form from the tender report or the assigned committee inbox.
2. Click Print / Download Record. The new tab opens the printable PDF; use the PDF viewer's print or download control.
3. On final tender award, each unsuccessful vendor who actually submitted a non-draft bid receives their personalised regret letter as a PDF email attachment. The recipient is the vendor's saved email address. The winning vendor continues to receive the award notification, not a regret letter.
4. Vendors can open the same saved letter through View / print result letter in their tender portal.
5. If an email fails, procurement can use the existing Retry failed result emails button. Successful emails are not automatically resent. Existing historical letters keep their saved wording; this update does not bulk-send old results.

LETTER CONTENT
The regret letter includes a bidder-specific REGRET reference, issue date, company name, address, mobile, email, tender reference/title and the bidder's submission date. It uses the supplied template's wording and signatory: Buthaina Al Zadjali. The full message is saved when the award is made, so later profile edits do not change the letter on retry. Missing contact details/dates are not invented.

The bid-opening record includes the tender details, budget, actual opening date/time, submission deadline, submitted bids and committee signature rows. Time & Place uses the existing PODC Tender Committee Meeting description; there is no new venue field. Draft bids are excluded. Continuation pages repeat table headings. Valid stored signatures are used where GD is available; otherwise the recorded signer/date is shown. A missing or damaged signature does not crash printing. Blank signature cells can be signed on the printed record.

INSTALLATION FOR THE ENGINEER
This is an incremental update for the current application, including the completed vendor/tender points 1-6. It is not a standalone project.
Back up the matching server files, then upload all eight files under UPLOAD into the existing project root, retaining folders. Upload BOTH assets/tender_documents PNG files. The runtime uses the existing app/Libraries/Pdf.php, bundled TCPDF and DejaVu Sans fonts. No Word/LibreOffice installation is required on the server.
No database tables, columns, SQL scripts, migration, environment keys, SMTP settings or cron changes are required for this update.
The web-server account must be able to write writable/cache/pdf and writable/cache/tender-letters. Temporary mail attachments are private and deleted after each send attempt. Do not upload the sample PDFs as real tender records.
If PHP OPcache does not refresh changed files automatically, ask the server administrator to reload PHP after uploading.

VERIFICATION COMPLETED LOCALLY
- 17 PDF behavior/rendering checks passed on PHP 8.3 and on PHP 8.2.
- 42 tender workflow database checks passed with an injected mail sender: correct recipients, PDF attachment, PDF filename/MIME compatibility, no resend of successful messages, failure handling, private temporary-file cleanup and stable retry content.
- 11 authenticated HTTP checks passed: procurement/assigned committee print routes, portal regret PDF, winning award letter, anonymous/vendor/unassigned access refusals, and locked-opening refusals.
- Seven existing tender/date/opening/authorization/document suites passed.
- Sample PDFs visually inspected. A 65-bid roster spans five pages, with all bids present, repeating headers and draft bids excluded.
No live SMTP messages were sent. Actual delivery depends on the existing server SMTP configuration and network access.

FILES TO UPLOAD
app/Controllers/Tender_reports.php
app/Controllers/Tender_committee_opening_inbox.php
app/Controllers/Vendor_portal.php
app/Libraries/Tender_document_pdf.php
app/Libraries/Tender_award_letters.php
app/Views/tender_reports/bid_opening_form.php
assets/tender_documents/letterhead-first.png
assets/tender_documents/letterhead-continuation.png
