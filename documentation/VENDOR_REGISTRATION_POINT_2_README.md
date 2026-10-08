# Vendor registration — point 2: documents by vendor group

This update uses the existing Vendor Document Types settings. It builds on point 1 (registration payments and waiver approval).

## Configure the requirements

In **Vendors Master → Vendor Document Types**, set each type's vendor group, Required and Active flags. A type assigned to **All vendor groups** applies to every group. Inactive and deleted types are excluded.

For example, assign two active, required document types to SME. Choosing SME on the guest registration form displays those two fixed upload rows, plus any types assigned to All vendor groups. Applicants cannot change the document type on these rows. Optional uploads may be left empty. **Add document** permits additional files using types applicable to the selected group.

Zero-fee groups still require Riyadha for the point 1 waiver review. Keep an active document type with code `RIYADHA` or `RIYADA` assigned to that group or All vendor groups. The waiver requirement uses the same document row; there is no separate duplicate Riyadha upload field.

Changing the selected group updates the form. Files already selected for shared types remain selected; files for types that no longer apply are removed with a notice. Required documents are checked again on the server against the current settings before records are created. If an administrator changes the requirements while an applicant has the form open, the applicant may need to refresh the form.

Files are saved in the existing vendor Documents records and document review requests. This does not change the separate document-review or payment-verification workflows.

## Upload these application files

Upload with their existing folder paths:

```
app/Controllers/Guest_vendor.php
app/Controllers/Vendor_document_types.php
app/Libraries/Vendor_registration_documents.php
app/Libraries/Payments/Vendor_registration_service.php
app/Views/guest_vendor/index.php
app/Language/english/custom_lang.php
app/Language/arabic/custom_lang.php
```

**There is no additional SQL or migration for point 2.** If point 1 has not been installed, also upload all files listed in `VENDOR_REGISTRATION_POINT_1_README.md` and run its `app/Database/SQL/vendor_registration_payment_first_pod.sql`. Point 2 files alone do not install point 1.

No changes to `.env`, bank credentials, SMS settings, sign-in visibility or tendering are required. Tests and this README are for development/engineer reference; they do not need to be served publicly.

## Verification

Local automated checks cover group and shared requirements, optional and additional uploads, invalid or already-moved uploads, invalid dates, inactive/deleted/other-group document types, changed requirements, and the waiver's single Riyadha upload. HTTP checks use a separate disposable database and verify refusal rollback, actual stored files, previews, and waiver approval. Browser checks cover desktop and mobile layouts, switching groups, preserving shared files, validation messages and a complete registration submission. Live bank and SMS requests are disabled in that test environment.

Verification on 3 October 2026: 38 document-policy checks, 35 billing checks, 32 HTTP workflow checks and 25 browser checks passed. Existing guest form, multiple-CR, upload security and document expiry tests also passed. Changed PHP files passed syntax checks on PHP 8.2 and 8.3.

After upload, open the guest registration page, select each configured group and confirm its required list. Submit a test application with the required documents and inspect them under the vendor's Documents section. Test payment on the bank-authorized UAT domain as described in point 1.
