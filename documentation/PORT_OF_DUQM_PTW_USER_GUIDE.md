# Port of Duqm e-Services

## Permit to Work (PTW) Portal — Applicant User Guide

**Audience:** approved PTW Applicant users working for an assigned company  
**Purpose:** prepare, submit, revise, track, and download Permit to Work applications  
**Guide version:** 19 August 2026

> **Before distribution:** replace `<PORTAL_BASE_URL>` in this guide with the organisation's published e-Services address. Do not give external users the `localhost` links; they are for local testing only.

## 1. Important access rule

This build has **no public PTW self-registration page**. Before you can create a PTW, an authorised Port/organisation administrator must:

1. Create or approve your individual sign-in account.
2. Assign you as an active **PTW Applicant**.
3. Assign you to the company for which you may create permits.

If **PTW Portal** is not visible after sign-in, or no company appears in the new-application form, ask the authorised PTW administrator to check your account and company assignment. Do not use another person’s account.

## 2. Links and how to use them

| Task | Published link | Local test link |
|---|---|---|
| Sign in | `<PORTAL_BASE_URL>/index.php/signin` | `http://localhost:8082/index.php/signin` |
| Open the PTW Portal after sign-in | `<PORTAL_BASE_URL>/index.php/ptw_portal` | `http://localhost:8082/index.php/ptw_portal` |
| Start a new PTW after sign-in | `<PORTAL_BASE_URL>/index.php/ptw_portal/application_form` | `http://localhost:8082/index.php/ptw_portal/application_form` |
| Reset a forgotten password | `<PORTAL_BASE_URL>/index.php/signin/request_reset_password` | `http://localhost:8082/index.php/signin/request_reset_password` |

After signing in, choose **PTW Portal** from the left navigation. If you have access to more than one external portal, use the portal links shown for your account.

## 3. Before you create a PTW

Prepare the current information for the actual work scope:

- Assigned company.
- Applicant’s full name, position, phone number, and email.
- Clear description of the work, exact location, and planned start/end date and time.
- Work supervisor name and contact details.
- Expected number of workers.
- Current hazard-control, PPE, and work-area-preparation evidence.
- Responsible person’s declaration details and signature.

Upload only current evidence for the work being requested. The application-level upload policy accepts PDF, JPG/JPEG, and PNG files up to 10 MB when an evidence upload is required.

## 4. Create a PTW application

1. Sign in and open **PTW Portal**.
2. Open **My PTW Applications** and select **New PTW** / **Create permit application**.
3. Complete the seven-step form. You may move through the steps using **Next** and **Back** before saving.

### Step 1 — Permit Applicant

Select the assigned **Company Name** and complete the applicant name, position, contact number, and email. Only companies assigned to your applicant account are available.

### Step 2 — Scope of Works

Describe the work clearly and enter the planned start and completion date/time. Complete the exact work location, supervisor details, and total worker count. Use a precise location and scope so reviewers can assess the correct work activity.

### Step 3 — Hazards

Complete each displayed hazard-document requirement. Check the item, provide the required response, and attach evidence when the item requires it. Use the **Other** option only for a genuine additional hazard/control and describe it clearly.

### Step 4 — PPE

Confirm the personal protective equipment required for the work and attach evidence where the checklist requests it.

### Step 5 — Work Area Preparation

Complete each displayed preparation item. Record how the work area is made safe and attach the requested evidence.

### Step 6 — Declaration

Confirm the declaration, enter the responsible person’s name and function, and provide the required signature in the signature area. Ensure the signer is authorised for the company and scope of work.

### Step 7 — Review

Review the displayed summary. Correct any missing or inaccurate details before selecting one of the available actions:

- **Save Draft** — keeps the application editable for later completion.
- **Submit to HSSE** — sends a complete application to the first reviewer stage.

The portal highlights validation errors and returns you to the relevant information if required details, checklist responses, evidence, or signature are missing.

## 5. Key rules while editing

- The selected company becomes an authorisation boundary after the application is created. If the work belongs to another company, create a new PTW rather than changing the company.
- A valid assigned company is required even when saving a Draft.
- Contact numbers must contain 6–20 digits; `+`, spaces, and hyphens may be used.
- Worker count must be a whole number greater than zero, and the completion date/time cannot be before the start date/time.
- A Draft can be edited and completed later.
- A submitted PTW is normally not editable until a reviewer returns it for revision.
- A Rejected PTW is closed. Create a new application only when instructed by the responsible authority.
- Use a new application for a materially different work scope, location, or dates.

## 6. What happens after you submit

The applicant sees the status and stage in **My PTW Applications** and on the application details page. The normal review route is:

| Stage | What happens |
|---|---|
| HSSE | HSSE reviews the safety information and can approve, request revision, or reject. |
| HMO | HMO reviews the application after HSSE approval. |
| Terminal, when required | Terminal completes the final review. |
| Completed / Approved | The permit is issued and the PDF becomes available. |

HSSE can determine that Terminal approval is not required. In that case, HMO approval can complete the permit without a Terminal stage.

## 7. Understand PTW status

| Status | What it means | What you should do |
|---|---|---|
| Draft | The application is still being prepared. | Complete it and save or submit. |
| Submitted | The current reviewer stage has the application. | Monitor the details; wait for a decision. |
| Revise | A reviewer needs changes. | Read the reviewer reason/comments, correct the requested details/evidence, and resubmit. |
| Rejected | The application is closed. | Follow the stated reason and create a new application only when appropriate. |
| Approved / Completed | Final approval is complete. | Download and retain the issued permit PDF. |

When a PTW is returned for revision, the resubmission goes back to the relevant review stage. Correct the actual issue; do not merely resubmit the unchanged application.

## 8. Download the issued permit

After final approval:

1. Open the application from **My PTW Applications**.
2. On the details page, select **Issued Permit**.
3. Download or print the PDF.
4. Check the reference, company, work scope, location, and valid dates before using it at the work site.

The portal history and issued PDF should be retained with the job documentation. An approved permit applies only to the stated scope and validity period.

## 9. Account help and good practice

- Use **Forgot password** at the sign-in page if you cannot access your account.
- Keep your contact details current and use your own individual account.
- Attach legible, current files only; avoid photos that are unreadable or evidence from a previous job.
- Give reviewers enough detail to understand the specific task, location, hazards, controls, supervisor, and dates.
- Check **My PTW Applications**, the application details, and review history regularly after submission; do not rely on an automatic status email.
- For access, company-assignment, safety-requirement, or review questions, contact the Port of Duqm PTW/HSSE contact published with the service.

## 10. Quick checklist

- [ ] My PTW Applicant account is active and assigned to the correct company.
- [ ] Applicant and supervisor details are correct.
- [ ] Scope, location, worker count, and dates describe the actual work.
- [ ] All required hazard, PPE, and preparation items are complete.
- [ ] Required evidence files are attached.
- [ ] Declaration and signature are complete.
- [ ] The PTW is submitted, or intentionally saved as a Draft.
- [ ] The final issued permit is downloaded only after approval.
