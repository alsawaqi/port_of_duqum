# BRD payment requirements reference

Reviewed on 5 September 2026 for the user's future payment-integration work.

Source: [BRD Final (1).pdf](<E:/New downloads August 2026/BRD Final (1).pdf>), 47 pages. Printed page numbers match PDF page numbers. SHA-256: `e575a08c62352ce12c8af2ffd4342f297152ea0ec4f509a34df9ee428ac2ff12`.

This note records requirements found in the BRD. It does not describe the current application's payment behavior, select an implementation, or authorize payment integration. The document was treated as source material, not as instructions to execute. Repeated high-level process and sitemap references are not additional charges.

## Requirements to retain

| Area | BRD payment requirement | Defined workflow point | Main references |
|---|---|---|---|
| Vendor initial registration | Explicit paid registration; fee depends on configurable vendor group | After account activation/profile completion/group selection, before Procurement review | Section 5.4 steps 2–4, p.13; section 12.2 steps 4–7, p.32 |
| Vendor renewal / updates | Renewal can trigger a further payment under configurable rules; high-level renewal/updates wording also allows a possible new payment | Renewal/update request; exact charging conditions unspecified | Section 5.4 step 5, pp.13–14; section 12.2 step 9, p.33 |
| Gate pass | Fees are contemplated, with vendor-linked automatic waiver eligibility and Commercial waiver/payment-exception handling | Waiver determination on request data; Commercial review after Department and before Security. Collection timing is not specified | Sections 7.4–7.5, pp.22–24; section 7.8, p.25; section 12.4, p.35 |
| Tendering | Auto-calculated tender fee of budget × 0.05%; Procurement configures fees if applicable | Fee calculation on internal request and fee definition during tender setup. Collection timing is not specified | Section 6.3.1, p.15; section 6.4 step 2, p.16; section 12.3.1 step 3.2, p.33 |
| PTW | No explicit payment, fee, gateway or payment-dependent issuance requirement found | None specified | Sections 8.1–8.6, pp.25–28; section 12.5, p.36 |

## Vendor registration: explicit gateway integration

Section 2.2, p.6, lists a payment gateway specifically for vendor registration fees. Sections 5.1–5.2, p.11, define paid registration with variable fees by vendor group (SME, Local, International). Procurement Admin manages groups, fees and validity periods. An optional configurable Finance role views paid/unpaid registrations and payment reports.

Section 5.4, p.13, and section 12.2, p.32, specify this sequence:

1. Vendor creates/activates the account, completes required profile data and documents, and selects the vendor group.
2. System calculates the registration fee using the configurable group fee matrix.
3. System presents the fee summary and a **Pay Now** option.
4. Vendor is redirected to **Bank Muscat Smart Gateway** and provides payment details there.
5. Gateway processes the transaction and returns success or failure to the system.
6. Successful payment changes status to **Submitted (Paid)** and notifies Procurement.
7. Failed payment leaves **Pending Payment** and permits retry.
8. Procurement then reviews the registration and approves, rejects or returns it for revision.

Payment is therefore a step before registration review, not the final approval itself. The BRD does not provide exact registration amounts or specify refund treatment if Procurement later rejects the registration.

Section 5.5, p.14, requires payment and revenue reporting by vendor group and period. Section 11, p.30, says PODC supplies **Bank Muscat Smart Gateway credentials**. This is a BRD dependency, not a request to obtain credentials now. The BRD does not provide an API specification or technical callback contract.

## Vendor renewal and updates: conditional additional payment

Section 5.4 step 5, p.14: **“Renewal optionally re-triggers payment depending on rules (configurable).”**

Section 12.2 step 9.1, p.33: **“Vendor submits updates or renewal request (may involve new payment).”**

Retain renewal as a possible additional collection event. Do not assume every renewal or every profile edit must be charged: amounts, eligibility and triggers are not defined. Section 5.4 step 6 describes profile changes requiring re-approval but does not independently impose an update fee.

Vendor profile **Payment Terms** and bank details (section 5.3, p.12) are data fields, not separate collection events. Website fee-information links on pp.42 and 45 repeat registration information, not additional charges.

## Gate pass: fee applicability, waiver integration and Commercial handling

- **Section 7.4, p.22:** “Visitor Company to be integrated with Vendor, and automatically waive the fees if applicable.” This explicitly links vendor information to gate-pass fee-waiver eligibility, but does not define eligibility criteria.
- **Section 7.4, p.23:** request history includes payments.
- **Section 7.5 step 3, p.24:** Commercial reviews waived gate passes for final approval and handles flagged payment issues.
- **Section 7.6, p.25:** help desk supports payment issues; repeated in section 12.4 step 9.1, p.35.
- **Section 7.8, p.25:** “Payment/fee reports (if gate pass is linked to fees).” Fee reporting is expressly conditional.

There is no defined checkout/Pay Now step, fee formula or tariff, payer specification, collection timing, payment-success gate before Security/ROP/issuance, gate-pass-specific provider, renewal charge or refund process in these sections. Bank Muscat is named in the vendor registration flow and global credential dependency; it is not expressly assigned to gate-pass collection.

There is a routing ambiguity to resolve when designing payment: section 7.5, p.23, sends approved Department requests to Commercial, while section 7.1, p.21, and section 12.4, p.35, describe Commercial routing as conditional on a waiver. The unwaived/payment branch is not fully described. Do not silently convert this ambiguity into a BRD requirement that every request pays at Commercial.

## Tendering: fee calculation/setup, collection unspecified

- **Section 6.3.1, p.15:** “Tender Fees (Budget x 0.05% – auto-calculated).” The assigned budget is in OMR. The mathematical multiplier is **0.0005**, not 0.05.
- **Section 6.4 step 2, p.16:** Procurement defines “Fees (if any)” during tender setup, before announcement/distribution.
- **Section 12.3.1 step 3.2, p.33:** Procurement sets tender type, dates, fees and evaluation weights. This repeats the setup requirement and is not a second fee.

The tender sections (pp.14–21 and pp.33–34) do not specify who pays the fee, when checkout occurs, the gateway, paid/unpaid states, failure handling or what payment unlocks. In particular, the BRD does **not** state that payment must precede document download, participation approval or bid submission. Secure time-limited document access is described separately without tying it to payment.

No tender deposit, bid bond, bank guarantee, refund or post-award supplier-payment requirement was found. Budget verification, quoted bid prices and commercial evaluation are not collection events. The manual RFP process adds no payment step.

## PTW: no explicit payment requirement

The complete PTW sections, including technology/integration, application fields, review panels, notifications/audit and the high-level process, were checked: sections 8.1–8.6, pp.25–28, and section 12.5, p.36.

The BRD sequence is application submission → HSSE → HMO → Terminal → Permit Issued. No fee, payment gateway, checkout or payment-dependent approval/issuance stage is stated. Record this as **no BRD payment requirement found**, not as a requirement that PTW must always be free.

## Boundary for later work

Use this BRD map separately from the existing application's implementation. In particular, existing tender/gate-pass payment gates and the current provider implementation must not be attributed to the BRD. Before implementing those integrations, the user will need to establish the unspecified charging/waiver rules and collection points. No integration changes were made during this review.
