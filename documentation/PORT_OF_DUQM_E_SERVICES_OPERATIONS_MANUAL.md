# Port of Duqm e-Services Operations Manual

**Modules:** Vendoring, Permit to Work (PTW), Gate Pass, and Tender & Procurement  
**Version:** 1.0  
**Date:** 11 August 2026  
**Audience:** System administrators, operational reviewers, internal requesters, vendors, contractors, and security personnel.

## Purpose and scope

This manual explains how to operate the four business workflows in the Port of Duqm e-Services system:

1. Vendoring and the Vendor Portal
2. Permit to Work (PTW)
3. Gate Pass
4. Tender and Procurement

It is written as a practical operating guide. Screen and menu visibility depends on the modules enabled for the organisation and on the signed-in user's assigned role and operational membership.

## Contents

1. Shared access and administration
2. Vendoring
3. Permit to Work
4. Gate Pass
5. Tender and Procurement
6. Operating controls and support

---

# 1. Shared access and administration

## 1.1 How access works

The system uses more than a menu permission. A person may need all of the following before they can complete an action:

- A valid, active user account.
- The right system role or module permission.
- An active operational assignment, such as reviewer, department user, evaluator, or portal contact.
- Access to the relevant company, department, vendor registration, or tender.

Assigning a role alone does not automatically make a user a reviewer or approver. Operational assignments control who can act on live workflow records.

### Access levels

| Access level | Practical purpose |
|---|---|
| System Administrator | Full system authority. Use only for trusted platform administrators. |
| Settings Administrator | Manages the available system settings. This is still a high-trust role because it can affect global configuration and integrations. |
| Role/Permission Manager | Creates and maintains internal roles and their permissions, where separately authorised. |
| Module Administrator | Maintains master data and operational assignments for a specific module. |
| Workflow Reviewer | Processes records only for the assigned company, department, or tender role. |
| External Portal User | Uses the Vendor Portal, PTW Portal, or Gate Pass Portal only within their active membership. |

## 1.2 New internal user setup

Use this sequence whenever a new employee must access an internal workflow.

1. Confirm that the person already has, or needs, an active staff account.
2. Assign the least-privileged internal role needed for their work.
3. Assign the user to the required company and, where needed, department.
4. Create the operational membership required by the module. Examples include:
   - Gate Pass Department Reviewer
   - PTW HSSE Reviewer
   - Tender Procurement User
   - Tender Technical Evaluator
5. Confirm the user sees the expected menu and only the expected records.
6. Record the business owner who approved the access.

> **Important:** Use a System Administrator account only when full cross-company authority is genuinely needed. A Settings Administrator or Module Administrator should not be assumed to have the authority to approve workflow records.

## 1.3 Role and permission administration

Use **Settings / Roles and Permissions** to create or amend internal roles.

1. Select an existing role to review it, or create a new business role.
2. Grant only the required View, Create, Update, Delete, or workflow permissions.
3. For Tender, Gate Pass, PTW, Vendor, Companies, and Departments, select permissions area by area.
4. Save the role.
5. Assign the role to eligible internal staff.
6. Complete a least-privilege check using a non-administrator account where practical.

When changing a role used by many people, first identify the affected users. A role change can broaden or remove access for every assigned user.

## 1.4 Global settings administration

Settings administrators may manage items such as:

- Application details, branding, localisation, modules, menu visibility, and notifications.
- Email sending settings, email templates, and integrations.
- Client portal configuration and sign-in behaviour.
- IP restriction and other security-adjacent settings.
- Module-specific master data and operational assignment screens.

Use a controlled change process for credentials, SMTP settings, integrations, payment-related configuration, and portal access rules. Test changes with a limited account before relying on them in a live workflow.

## 1.5 Shared master data

**Companies** and **Departments** are shared records used by several modules. Create and activate them before assigning users or starting company-specific work.

Maintain these records carefully:

- Use a clear, stable company name and code.
- Create departments only when there is a business owner.
- Deactivate obsolete records rather than reusing them for a different business unit.
- Review assigned users before deactivating a company or department.

---

# 2. Vendoring

## 2.1 Purpose

The Vendoring module manages vendor registration, qualification information, vendor users, supporting documents, vendor updates, and access to eligible tenders.

## 2.2 Roles in the process

| Role | Main responsibility |
|---|---|
| Guest Vendor | Starts a new vendor registration. |
| Vendor Owner | Completes the portal profile and manages vendor contacts. |
| Vendor Editor | Maintains permitted vendor information and participates in tender activity. |
| Vendor Bidder | Views the vendor profile and participates in tender activity. |
| Vendor Viewer | Read-only portal access. |
| Vendor Administrator / Procurement | Maintains vendor master data, reviews vendors, manages status, and processes vendor updates. |

Vendor portal roles are assigned for a specific vendor registration (CR). The same person can have different roles for different registrations.

## 2.3 Administrator setup before vendor onboarding

Complete the following setup before inviting or approving vendors.

1. Maintain **Vendor Categories** and **Subcategories** for the services or goods that vendors may provide.
2. Maintain **Vendor Groups** and define any group-specific registration requirements.
3. Maintain **Vendor Grades** if grades affect qualification or tender eligibility.
4. Maintain **Group Fees** where registration or qualification fees apply.
5. Maintain **Document Types**, including required documents and validity requirements.
6. Assign internal users the required vendor permissions:
   - Vendor master access
   - Vendor Update Request access
   - Vendor specialties access
   - Relevant master-data access
7. Confirm procurement/vendor reviewers are active and understand status decision rules.

Before changing a required document type or group rule, consider the impact on active registrations. Do not remove a requirement without confirming how existing vendor records should be handled.

## 2.4 Guest vendor registration

### Before starting

The guest should have:

- Registered legal company name and CR information.
- Main company address and contact details.
- Primary owner contact email and mobile number.
- Required initial registration documents.
- A strong password for the owner contact when activation is requested.

### Steps

1. Open **Guest Vendor Registration**.
2. Enter the company legal details and registration information.
3. Enter the primary owner/contact details carefully. This contact becomes the initial portal contact.
4. Upload the requested registration documents.
5. Review the information and submit the registration.
6. Follow the activation invitation sent to the owner contact, if prompted.
7. Sign in to the **Vendor Portal** to complete the registration profile.

The initial vendor record is normally created in **New** status. The vendor must complete the required profile information and submit it for internal review.

## 2.5 Vendor Portal: completing the profile

Use the Vendor Portal as the vendor's working area. The exact menu labels may differ slightly by portal role, but the normal profile areas are:

- **Overview** - review registration completeness and current status.
- **Company Profile** - maintain core company details.
- **Contacts** - add or maintain authorised users.
- **Bank Accounts** - provide banking details and supporting bank documents.
- **Specialties** - select the relevant approved business categories and subcategories.
- **Branches** - maintain branch or location records.
- **Credentials** - record licences, certificates, and expiry dates.
- **Documents** - upload required compliance documents.
- **Tenders** - view eligible tenders, respond to clarifications, pay applicable fees, and submit bids.

### Profile completion procedure

1. Sign in to the Vendor Portal and select the correct vendor registration if more than one is available.
2. Review the **Overview** or completeness indicator.
3. Complete the Company Profile using the company's legal and current information.
4. Add at least one valid contact and confirm the Owner contact is correct.
5. Add bank account details and the required supporting evidence.
6. Select appropriate specialties that reflect the vendor's actual services.
7. Add branches and credentials where required.
8. Upload all mandatory document types, including any documents required for the vendor's group.
9. Review expiry dates and replace expired documents.
10. Select **Submit for Review** when all required sections are complete.

Do not submit duplicate records for the same bank account, document, specialty, or credential while a related update is still pending review.

## 2.6 Managing vendor contacts

The Vendor Owner should manage portal contacts using the least privilege needed.

| Portal role | Use it when the person needs to... |
|---|---|
| Owner | Manage contacts, profile details, and tender participation. |
| Editor | Maintain profile information and support tender participation. |
| Bidder | Participate in tenders without contact administration. |
| Viewer | View information only. |

Steps:

1. Open **Vendor Portal / Contacts**.
2. Add or select the contact.
3. Confirm their email and mobile details.
4. Assign the appropriate role for this vendor registration.
5. Send or resend the access invitation as required.
6. Remove or deactivate access promptly when the person no longer represents the vendor.

Never share a portal account. Each person must use their own account so the system audit trail remains accurate.

## 2.7 Vendor submission and status guidance

| Status | Meaning | Vendor action |
|---|---|---|
| New | Registration is being prepared. | Complete the profile and submit. |
| Pending Payment | A required payment is outstanding. | Complete the required payment and check status again. |
| Submitted | Internal review is in progress. | Monitor comments; avoid duplicate updates. |
| Revise | Internal review requires correction. | Read the remarks, correct only the requested items, and resubmit. |
| Approved | Registration is approved for normal operation. | Keep documents, contacts, and credentials current. |
| Rejected | Registration was declined. | Follow the instruction from vendor administration before restarting. |
| Suspended | Use or access is restricted. | Contact vendor administration or procurement. |
| Expired | Registration or required records have expired. | Renew the expired requirements. |

## 2.8 Internal vendor review and update requests

Internal Vendor Administrators and Procurement users should use **Vendors** and **Vendor Update Requests** as the controlled internal work areas.

### Reviewing an initial vendor or major change

1. Open the vendor record or update request.
2. Confirm the vendor's legal details, category/group/grade, contacts, bank information, specialties, credentials, and required documents.
3. Review document validity, expiry dates, and all reviewer remarks.
4. Choose the appropriate decision:
   - Approve
   - Revise
   - Reject
   - Suspend or block only when supported by an approved business decision
5. Enter a specific, factual reason for material decisions.
6. Save the decision.
7. Confirm the vendor receives the expected status and portal guidance.

Use **Revise** when a correctable item is missing or incorrect. Use **Reject** only for a final decline under the relevant business policy. Use **Suspend** or blocking controls only through the organisation's approved vendor governance process.

## 2.9 Vendor participation in tenders

Vendor access to a tender is controlled by tender state, invitation/targeting, eligibility, and the vendor's portal membership. A vendor should:

1. Open **Vendor Portal / Tenders**.
2. Select an available tender and review the RFQ, required documents, fee, dates, and eligibility terms.
3. Pay any required tender fee through the provided process.
4. Use the portal clarification process within the permitted period.
5. Upload the complete bid before the closing time.
6. Submit the bid and retain the submission confirmation.

Always submit early. The system does not accept a bid after the tender deadline.

---

# 3. Permit to Work (PTW)

## 3.1 Purpose

PTW manages safety and operational approval for work activities. A permit moves from the applicant through HSSE, HMO, and, where required, Terminal review before it is issued.

## 3.2 PTW administrator setup

Before applicants submit PTWs, configure the following under the PTW administration/master-data screens.

1. Ensure all required Companies are active.
2. Assign **Applicant Users** to the companies for which they may create permits.
3. Assign **HSSE Users** to the companies they may review.
4. Assign **HMO Users** to the companies they may review.
5. Assign **Terminal Users** to the companies they may review.
6. Maintain checklist requirements:
   - Hazard Documents
   - PPE
   - Work Area Preparation
7. For each checklist item, confirm label, required flag, evidence requirement, sort order, and active status.
8. Maintain standard **Revise** and **Reject** reasons by reviewer stage.
9. Grant Request List access only to users who need cross-record reporting or monitoring.

Keep company assignments current. A reviewer should not receive access to a company unless they are authorised to review that company's work.

## 3.3 Applicant procedure

### Start a PTW application

1. Open **PTW Portal / My PTW Applications**.
2. Select **New PTW**.
3. Select the company to which the work belongs.
4. Enter applicant and contact information.
5. Enter work scope, location, work area, supervisor, expected worker count, and planned work dates.
6. Complete each required Hazard Document, PPE, and Work Area Preparation item.
7. Upload supporting evidence where the checklist requires it.
8. Confirm the declaration and provide the required signature.
9. Select:
   - **Save Draft** to continue later, or
   - **Submit** to send the PTW to HSSE review.

The selected company cannot normally be changed after creation. Create a new PTW if the work must be associated with another company.

### Checklist and attachment guidance

- Complete every mandatory checkbox, text response, and evidence requirement.
- Use clear, current evidence files. Supported document formats normally include JPG, JPEG, PNG, and PDF.
- Use a meaningful description for non-standard or "other" safety requirements.
- Check that start and end dates are logical and match the planned work.
- Do not use an old PTW as evidence that a new work scope is safe; submit current documents for the current job.

## 3.4 PTW workflow

| Stage | Responsible user | Decision and outcome |
|---|---|---|
| Draft | Applicant | Save, edit, or submit. |
| HSSE | HSSE Reviewer | Approve to HMO, send for revision, or reject. |
| HMO | HMO Reviewer | Approve to Terminal or complete the PTW when Terminal approval is not required; send for revision; or reject. |
| Terminal | Terminal Reviewer, when required | Approve to issue the permit; send for revision; or reject. |
| Completed | System | Status becomes Approved and the permit PDF is available. |

HSSE may mark Terminal approval as not required while approving the PTW. In that case, HMO approval completes the permit directly.

### PTW status guidance

| Status | What the user should do |
|---|---|
| Draft | Applicant completes or corrects the application. |
| Submitted | Wait for the current reviewer stage. |
| Revise | Applicant reads the reason, corrects the PTW, and resubmits. |
| Rejected | The application is closed. Create a new application only when instructed. |
| Approved / Completed | Download and retain the issued permit PDF. |

## 3.5 Reviewer procedure

1. Open the appropriate inbox:
   - **PTW HSSE Inbox**
   - **PTW HMO Inbox**
   - **PTW Terminal Inbox**
2. Open the PTW and confirm the company, work scope, location, dates, declaration, signature, and checklist.
3. Review attached evidence and the full decision history.
4. Select **Approve**, **Revise**, or **Reject**.
5. For Revise or Reject, select a suitable reason and enter clear remarks.
6. If you are HSSE and approving, confirm whether Terminal approval is required.
7. Save the decision and confirm the next stage.

Reviewers must not approve work outside their company assignment or technical authority. Use Revision for correctable safety gaps and Rejection for a final decision under the relevant safety policy.

## 3.6 Issued permit and records

After final approval:

1. The applicant opens the completed PTW from **My PTW Applications**.
2. Select the permit/PDF action.
3. Download or print the permit as required for the work site.
4. Retain the permit with the relevant job documentation.

The portal record and its audit history are the official workflow record. Keep reviewer comments factual and specific.

---

# 4. Gate Pass

## 4.1 Purpose

Gate Pass manages temporary access for visitors and vehicles. It supports request creation, document checks, fee/payment processing, multi-stage review, QR issuance, and entry/exit scanning.

## 4.2 Gate Pass administrator setup

Before processing requests:

1. Maintain active **Companies** and **Departments**.
2. Maintain **Purposes** and approved return/rejection reasons.
3. Configure **Fee Rules** by duration, rate type, currency, amount, and waiver eligibility.
4. Assign active operational users:
   - Requesters / Department Users
   - Department Reviewers
   - Commercial Reviewers
   - Security Reviewers
   - ROP Approvers
5. Maintain the **Blocked Visitor** register.
6. Grant only the required Gate Pass permissions for visitors, purposes, reviewers, fee rules, request lists, and activity logs.

Department Reviewers are normally limited by company and department. Commercial and Security reviewers are normally company-scoped. ROP approval is a high-trust function and should be assigned only to authorised personnel.

## 4.3 Requester procedure

### Create a request

1. Open **Gate Pass Portal**.
2. Select **New Gate Pass Request**.
3. Select the correct company, department, purpose, request type, and validity period.
4. Add all visitors and, where applicable, all vehicles.
5. Upload required documents:
   - Visitor identity document
   - Driver licence, where applicable
   - Vehicle registration/Mulkiyah
   - Valid plate details and any other required evidence
6. Save as Draft while information is incomplete.
7. Submit the request to Department review.

A request can include people, vehicles, or both. Add all required people and vehicle details before submission to prevent delays.

### After submission

1. Monitor the request status in the portal.
2. If the request is **Returned**, read the comments, correct the record, and resubmit to the indicated stage.
3. If Commercial review requires payment, complete the payment before expecting the request to progress.
4. When the request becomes **Issued**, download or print the QR pass.
5. Cancel or replace the request if the visit dates, people, or vehicles materially change.

## 4.4 Gate Pass reviewer procedure

| Reviewer | Main actions | Approval result |
|---|---|---|
| Department | Approve, return, reject, request fee waiver | Moves to Commercial when approved. |
| Commercial | Confirm/change fee, decide waiver, confirm payment | Moves to Security when eligible. |
| Security | Approve, return, reject, manage blocked visitors | Moves to ROP when approved. |
| ROP | Approve, return, reject, issue pass | Creates the issued QR pass. |

### Reviewer steps

1. Open the appropriate Gate Pass inbox.
2. Confirm the request falls within your assigned scope.
3. Review purpose, dates, company/department, visitor and vehicle details, and supporting evidence.
4. Check whether the visitor is blocked, the pass overlaps an active pass, or payment/waiver conditions remain unresolved.
5. Select the appropriate decision.
6. Enter a clear reason for a return, rejection, waiver, or material change.
7. Save the decision and confirm the next stage.

Do not approve a pass merely to correct a data issue. Return it to the requester with an explanation.

## 4.5 Gate Pass statuses

| Status | Meaning and expected action |
|---|---|
| Draft | Requester is still preparing the record. |
| Submitted | Awaiting Department review. |
| Department Approved | Awaiting Commercial decision, payment, or waiver outcome. |
| Commercial Approved | Awaiting Security review. |
| Security Approved | Awaiting ROP approval. |
| ROP Approved / Issued | The pass is active and may be printed or scanned. |
| Returned | Requester corrects and resubmits to the appropriate stage. |
| Rejected | Request is closed. |
| Cancelled / Expired | The pass is no longer valid for access. |

## 4.6 QR issuance, entry, exit, and checks

Only issued passes should be printed or scanned.

### Security scan procedure

1. Scan the visitor's issued QR code.
2. Confirm the visitor identity, validity period, company/access context, and block status.
3. Record the correct event:
   - Entry
   - Exit
   - Check
4. Refuse entry for blocked visitors, expired or invalid passes, invalid QR codes, or incomplete required information.
5. Record Exit only after a valid Entry.

Do not share QR passes or printouts outside the authorised visitor or vehicle use. QR activity is part of the security audit trail.

---

# 5. Tender and Procurement

## 5.1 Purpose

Tender and Procurement manages the sourcing cycle from the internal tender request through approval, tender setup, vendor bidding, secure bid opening, technical/commercial evaluation, and award.

## 5.2 Tender administrator setup

Before raising a tender request:

1. Confirm Companies and Departments are active.
2. Maintain Tender roles and permissions for:
   - Department Users / Requesters
   - Department Managers
   - Finance Users
   - Procurement Users
   - Procurement Managers
   - Tender Committee and ITC members
   - Technical Evaluators
   - Commercial Evaluators
   - Reports and Vendor Portal access
3. Assign each user to the correct company. Assign requesters to the relevant department where required.
4. Ensure vendor master data is current, including vendor specialties, groups, grades, documents, and portal access.
5. Confirm each tender team can include:
   - A Department Manager
   - Technical evaluators
   - Commercial evaluators
   - One Chairman
   - One Secretary
   - At least one ITC member
6. Grant the separate three-key bid-opening permission only to approved opening participants.

The tender team must be complete before a request can move into the procurement process.

## 5.3 Internal requester procedure

### Create and submit a Tender Request

1. Open **Tender Requests**.
2. Select **New Tender Request**.
3. Select the company and department.
4. Enter the scope, budget, schedule, tender type, and evaluation method.
5. Set Technical and Commercial evaluation weights. The total must equal 100%.
6. Assign the required Department Manager, Technical and Commercial evaluators, Chairman, Secretary, and ITC member(s).
7. For a closed tender, select the intended invited vendors.
8. Save as Draft while preparation is incomplete.
9. Submit to the Department Manager.

If the request is rejected, read the decision remarks, update the Draft/Rejected request, and submit it again when complete.

## 5.4 Tender request approvals

| Request status | Responsible role | Required action |
|---|---|---|
| Draft | Requester | Prepare, edit, and submit. |
| Submitted | Department Manager | Approve or reject. |
| Manager Approved | Finance | Verify or reject the financial basis. |
| Finance Verified | Tender Committee | Approve or reject. |
| Committee Approved | Procurement | Create and configure the tender. |
| Rejected | Requester | Correct and resubmit where appropriate. |

Each reviewer should inspect the request within their responsibility. Approval at one stage does not replace the required approval at later stages.

## 5.5 Procurement setup and publication

After a Tender Request is Committee Approved:

1. Open the approved request in the Procurement work area.
2. Create the Tender record.
3. Configure:
   - Title and reference
   - Open or closed tender type
   - Evaluation method and weights
   - Release, closing, site-visit, clarification, technical, and commercial dates
   - Required bid documents
   - RFQ details/items where used
   - Vendor targeting rules or invited vendors
   - Tender team and committee membership
4. Review all dates, team members, documents, and eligibility conditions.
5. Submit the configured Tender to the Procurement Manager when approval is required.
6. Publish only after the required approval and validation are complete.

Once published, do not manage a material change informally. Use the controlled update, revision, cancellation, or retender process.

## 5.6 Vendor tender procedure

1. Sign in to **Vendor Portal / Tenders**.
2. Open an available tender and confirm the vendor is eligible/invited.
3. Read the RFQ, bid documents, dates, fee, and submission requirements.
4. Complete any required tender fee payment.
5. Use the clarification process only during the permitted clarification window.
6. Upload the complete technical/commercial bid and all required evidence.
7. Submit before the closing deadline.
8. Retain the submission confirmation and monitor portal messages.

Vendors should not rely on informal email or verbal updates for tender changes. The portal record and issued tender documents are the official source.

## 5.7 Secure three-key bid opening

When a closed tender moves to **Technical 3-Key**:

1. Confirm that the Chairman, Secretary, and ITC member are correctly assigned to that tender.
2. Each participant receives or confirms their own opening code.
3. Each participant enters their own code; never share a code.
4. All three confirmations are required to unlock the bid package.
5. Complete and sign the opening form as required.
6. Procurement confirms the completed opening record before starting Technical Evaluation.

Protect opening codes and opening records. They are part of the tender control environment.

## 5.8 Technical evaluation, commercial evaluation, and award

### Technical Evaluation

1. Technical Evaluators open the assigned tender.
2. Review each submitted bid against the technical criteria.
3. Record acceptance/rejection rationale and technical score.
4. Save the evaluation within the designated evaluation period.

### Commercial Evaluation

1. Commercial Evaluators review bids that have progressed to Commercial Evaluation.
2. Record commercial decisions, rationale, and scores.
3. Complete the evaluation within the designated period.

### Award

1. Procurement confirms the Tender is at Award Decision.
2. Confirm that one commercially approved bid is selected.
3. Record the award decision and required letter/award information.
4. Use formal cancellation or retender controls if the sourcing requirement changes.
5. Retain the tender audit trail, opening form, evaluation records, clarifications, and award decision.

## 5.9 Tender lifecycle guidance

| Tender status/stage | Meaning |
|---|---|
| Draft / Bidding | Procurement is preparing the tender or awaiting approval. |
| Published / Bidding | Eligible vendors may submit while the window is open. |
| Closed / Technical 3-Key | Deadline has passed; secure bid opening is required. |
| Closed / Technical | Technical evaluation is in progress. |
| Closed / Commercial | Commercial evaluation is in progress. |
| Closed / Award Decision | Evaluations are complete; Procurement selects the award. |
| Awarded | Award decision has been recorded. |
| Cancelled | Tender has been formally cancelled. |
| Retendered | A replacement tender has been created. |

---

# 6. Operating controls and support

## 6.1 Daily operating checklist

### Administrators

- Review newly created accounts, pending invitations, deactivated accounts, and operational assignments.
- Check that company and department master data remains current.
- Review system settings changes, especially integrations, email, payment, and portal-access configuration.
- Monitor failed scheduled jobs or notification failures that could affect Tender deadlines or workflow progress.

### Workflow reviewers

- Review assigned inboxes at the agreed service frequency.
- Check status, evidence, and prior remarks before taking a decision.
- Use clear decision comments that explain the next action.
- Do not work outside the assigned company, department, vendor, or tender scope.

### Portal users

- Keep company/contact details, licences, documents, and expiry dates current.
- Use one personal account per individual; never share credentials.
- Check portal notifications and status changes frequently.
- Submit records early enough to allow review before a business deadline.

## 6.2 Common support questions

### I cannot see a menu or record

Check the following in order:

1. The account is active.
2. The correct internal role or external portal membership is assigned.
3. The required company, department, vendor CR, or tender membership is active.
4. The record is at a workflow stage visible to that role.
5. The module is enabled and the user is using the correct portal.

### A request is not moving forward

1. Check its current status and workflow stage.
2. Read the latest reviewer remarks or payment requirements.
3. Confirm required documents, team assignments, or fee conditions are complete.
4. Confirm the next reviewer has an active assignment.
5. Escalate to the relevant module administrator with the reference number and a screenshot of the status where needed.

### A user left the organisation

1. Deactivate or remove the user's portal membership and internal account access promptly.
2. Reassign any operational responsibilities before removing a reviewer.
3. Verify outstanding PTWs, Gate Passes, vendor updates, and tender assignments.
4. Preserve the historical audit trail; do not reuse the departed user's account.

## 6.3 Audit and recordkeeping

The system records workflow actions, reviewer decisions, and related activity. To maintain a reliable audit trail:

- Use individual accounts only.
- Enter factual, professional comments.
- Do not share QR passes, tender opening codes, or passwords.
- Avoid changing master data during an active approval cycle unless the business owner has approved the impact.
- Retain downloaded final permits, issued passes, tender evaluations, and award records according to organisational policy.

## 6.4 Escalation guide

| Issue type | Escalate to |
|---|---|
| Account, role, or settings issue | System or Settings Administrator |
| Vendor registration, documents, or eligibility | Vendor Administration / Procurement |
| PTW safety decision | HSSE, HMO, or Terminal authority |
| Gate access, blocked visitor, QR scan, or security issue | Security or ROP authority |
| Tender request, bid, evaluation, opening, or award issue | Procurement / Procurement Manager |

---

# Appendix: Quick reference

## Key operating principles

1. Use the least privilege needed for every account and operational membership.
2. Keep Companies, Departments, vendor data, and reviewer assignments current.
3. Complete required information before submitting; do not use approval stages to correct basic data errors.
4. Use Revise for correctable issues and provide clear comments.
5. Respect deadlines. Gate Pass validity, PTW work dates, vendor document validity, and Tender closing dates are operational controls.
6. Treat portal status, history, and formal workflow comments as the source of truth.
7. Escalate access or configuration changes through the designated administrator rather than sharing an account.
