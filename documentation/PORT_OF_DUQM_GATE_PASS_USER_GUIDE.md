# Port of Duqm e-Services

## Gate Pass Portal — External User Guide

**Audience:** visitors, contractors, suppliers, drivers, and authorised requesters  
**Purpose:** create a Gate Pass portal account, request access for visitors/vehicles, follow the review process, and download an issued pass  
**Guide version:** 19 August 2026

> **Before distribution:** replace `<PORTAL_BASE_URL>` in this guide with the organisation's published e-Services address. Do not give external users the `localhost` links; they are for local testing only.

## 1. What the Gate Pass Portal is for

The Gate Pass Portal is the external workspace for temporary access requests. An authorised requester can create a request, add visitors and vehicles, upload supporting documents, submit the request for review, make a fee payment when required, and download an issued QR pass/PDF.

An account application is not itself a gate pass. Create an account first, then create a separate Gate Pass request for the intended visit.

## 2. Links and how to use them

| Task | Published link | Local test link |
|---|---|---|
| Apply for a Gate Pass account | `<PORTAL_BASE_URL>/index.php/guest_gate_pass` | `http://localhost:8082/index.php/guest_gate_pass` |
| Sign in | `<PORTAL_BASE_URL>/index.php/signin` | `http://localhost:8082/index.php/signin` |
| Open the Gate Pass Portal after sign-in | `<PORTAL_BASE_URL>/index.php/gate_pass_portal` | `http://localhost:8082/index.php/gate_pass_portal` |
| Reset a forgotten password | `<PORTAL_BASE_URL>/index.php/signin/request_reset_password` | `http://localhost:8082/index.php/signin/request_reset_password` |

The Gate Pass Portal link requires a signed-in account with active Gate Pass access.

## 3. Before you start

Prepare the following:

- Your personal first name, surname (if applicable), and individual work email address.
- Mobile number and emergency contact number, including country codes.
- A strong password and password confirmation.
- The company, department, visit purpose, and visit dates.
- Correct identity details and ID document for every visitor.
- Driver licence for each person added as a driver.
- Vehicle registration/Mulkiyah for every vehicle.
- Any visa, photograph, or other evidence required by the screen or operational policy.

Use accurate identity and vehicle data. Incorrect or incomplete information can delay review or stop a request from being submitted.

## 4. Create your Gate Pass account

1. Open **Gate Pass Account Application** using the account-application link.
2. Enter your first name, optional last name, and individual work email.
3. Enter your mobile number and emergency number with the correct country codes.
4. Select the displayed preferred OTP/verification channel (phone or email).
5. Create and confirm a strong password.
6. Complete the anti-bot check and select **Submit Account Application**.
7. When the success message appears, open the sign-in link and use the same email and password. The current page does not sign you in or redirect you automatically.

The current portal activates a successfully created Gate Pass account immediately; it does not have a separate account-approval or email-verification step. Your password must be 10–72 characters and include uppercase, lowercase, a number, and a special character. You may be asked for a verification code at sign-in when multi-factor verification is enabled for the service.

If the portal says that an account already exists for your email, do not apply again. Sign in or use **Forgot password** instead. Do not use another person’s email address or share an account.

## 5. Create a Gate Pass request

After signing in, open **Gate Pass Portal**. In **My Gate Pass Requests**, select **Create Request**.

1. Select the correct **Company**. The **Department** list loads after the company is selected.
2. Select the **Purpose** of the visit.
3. Enter **Visit From** and **Visit To**. A new request must start today or later, and the end date cannot be before the start date.
4. Select the **Visit Type** (for example, Visitor, Supplier, or Agent).
5. Select the **Request Type**:
   - **Person + Vehicle:** add at least one visitor and at least one vehicle before submission.
   - **Person Only:** add at least one visitor before submission.
6. If a vehicle is included, select the applicable vehicle type.
7. Review the shown currency/fee information and add useful notes where needed.
8. Select **Save**. The portal opens the request details page where visitor and vehicle records are added.

You cannot submit directly from the create-request window. Saving creates a draft only; add the people, vehicles, and documents from **Details** before submitting it to reviewers.

## 6. Add visitors and documents

On the request details page, select **Add Visitor** for every person who needs access.

For each visitor, enter:

- First and last name.
- ID type and ID number.
- Nationality, phone number, visitor company, and role.
- **Visitor**, **Driver**, or **Passenger** role; mark the primary visitor where appropriate.
- ID attachment for every visitor.
- Driver licence attachment when the person’s role is **Driver**.
- Visa, photo, or other displayed evidence where applicable.

The portal can warn you when a visitor is blocked. Do not attempt to work around a blocked-visitor warning; contact the appropriate Gate Pass/Port contact to resolve it.

ID files, licences, visas, and other supporting files should be legible JPG/JPEG, PNG, or PDF files no larger than 10 MiB. Use an image file for a visitor photo.

## 7. Add vehicles and Mulkiyah

Select **Add Vehicle** for each required vehicle.

1. Enter the Oman plate prefix and digits, or select the international-plate option and provide the country and plate number.
2. Upload the vehicle registration/Mulkiyah file.
3. Save the vehicle record.

Each vehicle on the request needs a Mulkiyah file before the request can be submitted, including when the form does not mark the upload with an asterisk. Use current, legible JPG/JPEG, PNG, or PDF documents no larger than 10 MiB.

## 8. Submit, pay, and track the request

When all visitors, vehicles, and documents are ready, select **Submit for Department Review**.

The normal requester journey is:

1. **Draft** — you are preparing the request.
2. **Department review** — the department checks the request.
3. **Commercial review/payment** — the request may require a fee payment or a fee-waiver decision.
4. **Security review** — supporting documents and security conditions are checked.
5. **ROP review/issue** — final approval creates the issued pass.

If the page shows **Pay**, follow the checkout instructions when the payment page opens, then return to the request afterwards. Payment is unavailable while a fee-waiver request is pending. Only pay when the portal displays the payment action for that request. If payment is requested but no payment page opens, contact Gate Pass support rather than attempting another payment or submitting a duplicate request.

### If a request is returned

Open the request, read the return reason and comments, correct the requested records/documents, then select **Resubmit to reviewer**. A returned request normally goes back to the same review stage; it does not automatically restart from the beginning.

### If plans change

You can edit only a **Draft** or **Returned** request. There is no requester self-service cancel, delete, or duplicate action in the current portal. For a material change after submission or an issued pass, contact the Port of Duqm Gate Pass contact for direction.

## 9. Understand request status

| Status | What it means | What you should do |
|---|---|---|
| Draft | The request is not yet submitted. | Add all required records/documents and submit. |
| Under Department Review | The department is reviewing the request. | Monitor the request; avoid duplicate submissions. |
| Under Commercial Review | Commercial is considering payment, fee waiver, or the commercial decision. | Pay only if the portal shows **Pay**; otherwise wait for the next stage. |
| Under Security Review | Security is reviewing the request. | Monitor the request. |
| Under ROP Review | ROP is completing the final review. | Monitor the request. |
| Issued | The pass has been issued. | Download the QR code/PDF and use it only during the approved validity period. |
| Returned | A reviewer needs a correction. | Read the comments, correct the request, and resubmit. |
| Rejected | The request is closed. | Follow the stated reason; create a new request only when appropriate. |

## 10. Download and use an issued pass

When the request is issued, the request details page shows the QR pass actions. For each issued pass:

1. Select **Download QR Code** to save the QR image.
2. Select **Download / print PDF** to keep a printable copy.
3. Check the visitor name and valid-from/valid-to dates.
4. Present the issued QR/PDF only for the approved person, vehicle, and dates.

An approved pass does not authorise access outside its validity period or for a different person/vehicle.

## 11. Account help and good practice

- Use **Forgot password** at the sign-in page if necessary.
- Use your individual email and do not share passwords.
- Keep scan-ready identity and vehicle documents legible and current.
- Resolve duplicate/overlapping active-pass warnings before the request moves forward.
- Use the portal status and approval history as the current source of progress.
- For an access restriction, blocked visitor, review decision, or payment question, contact the Port of Duqm Gate Pass contact published with the service.

## 12. Quick checklist

- [ ] Gate Pass account created with a personal work email.
- [ ] Correct company, department, purpose, and dates selected.
- [ ] Every visitor includes correct identity details and ID evidence.
- [ ] Every driver includes a driving licence.
- [ ] Every vehicle includes a Mulkiyah.
- [ ] Request submitted for Department review.
- [ ] Payment completed only when the portal requires it.
- [ ] Issued QR/PDF checked against the approved validity period.
