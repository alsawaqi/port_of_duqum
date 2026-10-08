# Vendor registration — point 1

This change applies to new guest registrations. Existing vendors and renewal requests retain their existing workflow.

## Before uploading

1. Back up the application and database.
2. Run `app/Database/SQL/vendor_registration_payment_first_pod.sql` against the destination database using MySQL Workbench or phpMyAdmin. It uses the `pod_` prefix. No migration command is needed.
3. Upload the changed application files below, keeping their folders. No `.env`, database credentials or bank keys are included in this change.
4. In **Vendors Master → Vendor Groups**, edit each available group. Set a positive validity in days and its registration fee in OMR. Enter **0** for a waiver. The field uses the same fee records as **Vendor Group Fees**. Effective dates and scheduled future fees still apply.
5. Confirm the existing Bank Muscat configuration is enabled for the destination domain. Payment activation still requires bank verification. Keep the existing reconciliation jobs running.

The SQL adds `pod_vendor_registration_applications` and enables an existing non-deleted Riyadha document type, or creates one if absent. It does not delete or replace existing vendor, fee or payment records. It is safe to rerun.

## Applicant flow

- **Positive group fee:** save the registration, continue to Bank Muscat, and complete payment. Verified payment approves the registration and starts the group's configured validity period automatically.
- **Zero group fee:** uploading Riyadha is mandatory. The application waits for staff review.
- While pending, the applicant may sign in by email or CR to see registration status and pay. Vendor profile changes, contacts and tender actions are blocked for that CR. Existing access to other CRs or other portals remains subject to its usual permissions.
- The existing account password must be supplied when reusing an email. Multiple CRs still use the company selector.
- If checkout is temporarily unavailable, the application remains saved. The applicant can sign in and retry. Do not create another application for the same CR.

## Staff review

Open **Vendors → View vendor → Registration review**. Users with the existing vendor-review permission can:

- Review the uploaded Riyadha certificate and **Approve waiver and register vendor**.
- Choose **Require payment**, enter a positive amount with up to three decimal places and a reason visible to the applicant. The applicant pays this amount; verified settlement completes registration.

The ordinary vendor status dropdown cannot bypass a pending registration. Payment records continue to appear in **Vendor accounting**. Approval activates only the original registration contact; subsequently added contacts retain their separate review process. Point 2 now requires all active, required document types applicable to the selected group before registration is saved. Other profile information and optional documents can be completed later. See `VENDOR_REGISTRATION_POINT_2_README.md` for the additional files and setup.

## Application files to upload

```
app/Controllers/Guest_vendor.php
app/Controllers/Vendor_groups.php
app/Controllers/Vendor_group_fees.php
app/Controllers/Vendor_portal.php
app/Controllers/Vendors.php
app/Libraries/Payments/Vendor_registration_service.php
app/Libraries/Payments/Vendor_billing_service.php
app/Libraries/Payments/Vendor_payment_settlement.php
app/Views/guest_vendor/index.php
app/Views/vendor_groups/modal_form.php
app/Views/vendor_portal/registration_status.php
app/Views/vendors/details.php
app/Views/vendors/registration_review.php
```

Tests are for the development copy and do not need to be uploaded. This change does not unhide the previously hidden Vendor Registration Application sign-in button; the existing guest registration URL remains available.

## Verification

Local tests cover paid and waived registration, missing Riyadha, waiver approval, custom payment requests, sign-in and CR selection, restricted URLs, duplicate payment initiation, verified settlement and replay, and legacy billing/renewal behavior. Bank verification was simulated in the isolated database; no live payment or SMS was sent for this change. Complete one end-to-end UAT payment on the bank-authorized domain after deployment.
