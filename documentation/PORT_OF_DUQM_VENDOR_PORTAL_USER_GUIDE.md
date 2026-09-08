# Port of Duqm e-Services

## Vendor Portal — External User Guide

**Audience:** prospective vendors, vendor owners, authorised vendor contacts, and vendor bidders  
**Purpose:** create and maintain a vendor registration and, where eligible, take part in tenders  
**Guide version:** 19 August 2026

> **Before distribution:** replace `<PORTAL_BASE_URL>` in this guide with the organisation's published e-Services address. Do not give external users the `localhost` links; they are for local testing only.

## 1. What the Vendor Portal is for

The Vendor Portal is the external workspace for a supplier's Commercial Registration (CR). It is used to register a company, maintain its qualification information and supporting documents, manage authorised contacts, and view eligible tenders.

Each person must use their own email address and password. Do not share an Owner, Editor, Bidder, or Viewer account: personal access keeps the company audit trail accurate.

## 2. Links and how to use them

| Task | Published link | Local test link |
|---|---|---|
| Start a new vendor registration | `<PORTAL_BASE_URL>/index.php/guest_vendor` | `http://localhost:8082/index.php/guest_vendor` |
| Sign in | `<PORTAL_BASE_URL>/index.php/signin` | `http://localhost:8082/index.php/signin` |
| Open the Vendor Portal after sign-in | `<PORTAL_BASE_URL>/index.php/vendor_portal` | `http://localhost:8082/index.php/vendor_portal` |
| Reset a forgotten password | `<PORTAL_BASE_URL>/index.php/signin/request_reset_password` | `http://localhost:8082/index.php/signin/request_reset_password` |

Open the registration link only when the company does not already have a registration for the same CR. Open the sign-in link when you already have an account. The portal links require a signed-in account with active vendor access.

## 3. Before you start a registration

Prepare the following before opening the form:

- Legal company name and Commercial Registration number.
- Company email address, main contact name, position, and phone number.
- Selected vendor group.
- Company address, country, region, city, PO Box, and postal code where available.
- A personal work email address for the initial Vendor Owner and a strong password.
- At least one registration document, including its document type and file; issue/expiry dates are entered where they apply.

Use the company’s correct CR number. The portal does not allow a second active registration with the same CR number. Registration uploads must be PDF, JPG/JPEG, or PNG files up to 10 MB each. A new Owner password must be 10–72 characters and include uppercase, lowercase, a number, and a special character.

## 4. Create a vendor registration

1. Open **Vendor Registration Application** using the registration link above.
2. In **Vendor Information**, select the vendor group and enter the legal company name, company email, CR number, phone number, primary contact, and contact designation.
3. In **Location**, enter the available country, region, city, address, PO Box, and postal code details.
4. In **Login User**, enter the initial Owner’s name, work email, password, and password confirmation. This person is the initial company Owner contact.
5. In **Documents**, add each required document row. Select the document type, attach the file, and enter issue and expiry dates where they apply. Use **Add document** for additional records.
6. Complete the displayed anti-bot check and select **Submit Vendor Application**.
7. Keep the company CR number and Owner email for later reference.

If the portal reports that an email or CR already exists, do not submit another application. Use the existing account, use password recovery if required, or contact Vendor Administration/Procurement.

## 5. What happens after submission

The company is created in **New** vendor-onboarding status and the initial contact is recorded as the Owner. The Owner contact and its CR membership begin pending/invited; they are **not yet usable Vendor Portal access**. Vendor Administration/Procurement must approve the contact and activate the CR membership before the Owner can enter the Vendor Portal.

Do not expect a separate invitation email or immediate portal access. If sign-in does not show the Vendor Portal after registration, contact Vendor Administration/Procurement and quote the CR number and Owner email; do not create a duplicate registration.

Once you can enter the portal, choose the correct company/CR if the same email has access to more than one vendor registration. Roles and permissions are CR-specific, so a person may have a different role for each company.

## 6. Complete and maintain the vendor profile

Start at **Vendor Portal / Overview**. The overview shows the profile checklist and vendor status. Complete all sections required for the selected company and group:

| Section | What to maintain |
|---|---|
| Overview | Read the registered company facts, current vendor status, profile checklist, and document-expiry information. |
| Contacts | Authorised people, their email/mobile details, and their portal role. |
| Bank Accounts | Bank details and the supporting bank document requested by the portal. |
| Branches | Relevant company branch or location records. |
| Credentials | Licences, certificates, and their issue/expiry dates. |
| Specialties | The services, categories, and subcategories that accurately describe the company. |
| Documents | Required compliance documents and replacements for expired files. |

The Overview is read-only for core legal company facts. For a correction to the legal company name, CR number, or other core registered-company detail, contact Vendor Administration/Procurement rather than trying to edit it in the portal.

When the checklist is complete, select **Submit for Review** from the Overview. The button is available for New, Pending Payment, or Revise records only, and is unavailable until all required checklist items are complete. The checklist requires registered company fields, at least one contact, at least one acceptable bank account, at least one acceptable specialty, and the required documents for the vendor group. Some section changes create a pending update request; existing records in that section can be temporarily locked until it is reviewed. Avoid duplicate changes while one is pending.

### Managing contacts

The Owner should give each person only the access they need:

| Role | Typical use |
|---|---|
| Owner | Manages contacts, maintains permitted vendor information, and participates in tenders. |
| Editor | Maintains permitted vendor information and participates in tenders. |
| Bidder | Participates in tenders without contact administration. |
| Viewer / Legacy Contact | Reviews permitted profile and tender information only. |

Only the Owner can manage Contacts. To add a contact, open **Contacts**, enter the person’s correct name and email, choose the appropriate role, and set an initial password if the form requests one for a new account. Owners can assign Viewer, Bidder, or Editor access; they cannot create another Owner through this form. A new contact remains pending/invited until the internal approval process activates their access. The portal has no send/resend invitation action. Remove or deactivate access as soon as a person no longer represents the company.

## 7. Understand vendor status

| Status | What it means | What you should do |
|---|---|---|
| New | Registration/profile preparation is incomplete. | Complete the checklist and submit for review. |
| Pending Payment | The registration is awaiting the internal/payment instruction associated with it. | Follow the instruction from Vendor Administration/Procurement and monitor the status. Do not assume a generic registration-payment button will appear. |
| Submitted / Pending Review | Procurement or Vendor Administration is reviewing the profile. | Wait for feedback; do not duplicate the submission. |
| Revise | A reviewer needs a correction. | Read the remarks, correct the requested items, then resubmit. |
| Approved | The registration is approved for normal portal use. | Keep contacts, credentials, and documents current. |
| Rejected | The registration has been declined. | Follow the reviewer’s instruction before starting again; the portal is not available for normal use. |
| Suspended / Expired | Access or qualification has been restricted. | Contact Vendor Administration and renew/resolve the stated requirement; normal portal access is unavailable. |

## 8. Participate in tenders, when eligible

Tender access is controlled by the tender’s status, invitation/targeting, vendor eligibility, and your portal role. If **Tenders** is visible:

1. Open **Vendor Portal / Tenders**.
2. Select the tender and read its documents, requirements, dates, and fee information.
3. Download and review the tender documents.
4. Complete any required tender-fee payment when it is shown. Where a fee applies, document access is limited to 72 hours after the payment provider verifies payment.
5. Request Procurement approval only when the tender page explicitly asks for it, then wait for that decision.
6. Submit clarifications only through the portal and within the allowed period.
7. Upload every required bid section and submit before the closing time.
8. Keep the portal confirmation for your records.

Submit early. A tender cannot be submitted after its configured closing deadline.

## 9. Account help and good practice

- Use **Forgot password** at the sign-in page if you cannot access your account. Use the registered email address.
- Change your password from the signed-in account menu when required.
- Keep document expiry dates current; expired evidence can affect review or eligibility.
- Use accurate legal data. Do not reuse another company’s CR or contact details.
- If you have access to more than one external portal, use the portal switcher in the account menu after signing in.
- For access, review, or tender eligibility questions, contact the Port of Duqm Vendor Administration/Procurement contact published with the service.

## 10. Quick checklist

- [ ] Correct CR and legal company details entered.
- [ ] Owner work email and password recorded securely.
- [ ] Required documents uploaded with correct dates.
- [ ] Profile checklist completed.
- [ ] Profile submitted for review.
- [ ] Contacts and portal roles kept current.
- [ ] Tender deadlines checked before submission.
