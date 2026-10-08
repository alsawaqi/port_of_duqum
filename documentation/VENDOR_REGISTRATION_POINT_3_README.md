# Vendor registration — point 3: Vendor Code

The optional Vendor Code is a staff-assigned company reference, stored on the vendor's CR record. It is separate from the CR number and does not change email/CR login or contact access.

## Install

1. Back up the destination database and the files being replaced.
2. Select the application's database in MySQL Workbench or phpMyAdmin. Run `app/Database/SQL/vendor_code_upgrade_pod.sql`. It uses the `pod_` prefix and adds one nullable `VARCHAR(64)` column, `pod_vendors.vendor_code`. It does not remove or replace records and can be run again safely. No migration command is required.
3. Upload these files, preserving their paths:

```
app/Controllers/Vendors.php
app/Views/vendors/modal_form.php
app/Views/vendors/details.php
app/Language/english/custom_lang.php
app/Language/arabic/custom_lang.php
```

These files include the earlier point 1/2 changes where applicable. Install those points using their respective READMEs if they have not already been deployed; this list is only the additional point 3 change. Do not replace the database with a development dump.

## Use

Open **Vendors Master → Vendors → Edit**. After the vendor is approved and its registration application is approved, enter **Vendor Code** and save. Previously approved vendors without a point 1 application row are also supported.

The field uses the existing **Update vendors** permission, including full administrators. Applicants and vendor contacts cannot edit it. While registration is pending, or the vendor is rejected, suspended or blocked, the field is disabled and the server refuses changes to the code. Existing codes remain stored and displayed.

The code is optional, allows up to 64 characters on one line, and can be edited or cleared. Leaving the field out of a request preserves its existing value. It appears under the vendor name in the master list and in the vendor details summary. Separate CR records retain separate codes even when they share a login/contact.

If the SQL has not been installed, the field remains disabled with a setup message; ordinary vendor edits remain available. No `.env`, payment, SMS or login configuration change is needed.

## Verification — 3 October 2026

Tested locally in a disposable database with bank, email and SMS delivery disabled:

- SQL installation, repeat execution, nullable column and graceful handling before installation.
- Save, edit, clear, omitted-field preservation, 64-character limit, Arabic text and safe HTML display.
- Pending payment/application refusals leave the vendor row unchanged; vendor contacts and anonymous users cannot assign a code.
- Codes stay separate across CRs sharing one contact; payment, registration and contact records remain unchanged by a code edit.
- Browser save without a password, reload, list/details display, pending-state hint and desktop/mobile layout. No browser errors occurred.

The checks comprised 31 HTTP/database checks, including the four SQL/setup checks, and 11 browser checks. The existing billing (35), registration documents (38), unblock/payment-boundary (6), multiple-CR, grade-view and portal-authorization checks also passed. The five changed PHP files passed syntax checks on PHP 8.2 and 8.3.

The isolated fixture has no optional `pod_currencies` table. The existing vendor form used its fallback currency list successfully; its existing catch/fallback behavior logged that missing table. This is separate from the Vendor Code change.

This work has not been uploaded to a production server. The SQL above has only been applied to the isolated test database.
