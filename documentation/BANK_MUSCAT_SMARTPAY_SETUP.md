# Bank Muscat SmartPay integration

Implemented and locally checked on 5 September 2026. This is the implementation reference for subsequent payment work. The earlier `BRD_PAYMENT_REQUIREMENTS_REFERENCE.md` remains the source-of-requirements map; this guide describes the approved implementation.

## Payment points

| Payment | Where the customer starts | Effect of verified payment |
| --- | --- | --- |
| Vendor registration | Vendor Portal → Overview → complete profile → Pay registration fee | Records the group fee and submits the vendor for Procurement review. Payment does not itself approve registration. |
| Vendor renewal | Vendor Portal → Overview → Pay renewal fee | Records a separate renewal period and submits it for review. Approval extends the existing future expiry, or starts from today if expired. |
| Gate pass | Request details → Pay, after Department approval, for a chargeable request | Records payment and advances Commercial clearance to Security. Security approval and ROP issuance also enforce financial clearance. |
| Tender fee | Vendor tender details → Pay Tender Fee | Records payment for that vendor and tender; satisfies the existing document/participation/bid payment gates. |

PTW has no payment requirement in the reviewed BRD, so this change does not introduce a PTW charge. General RISE invoice/subscription payments are outside these four e-service payment points.

Amounts come from existing fee configuration. Vendor registration and renewal require active group fee rows and a positive configured validity period. A missing fee is not treated as free. Explicit zero vendor fees submit without creating a fictitious bank transaction. Gate-pass zero fees and documented Commercial waivers remain supported. Historic bypass flags alone do not satisfy a new paid-fee check. Existing issued gate passes are not revoked by this upgrade.

This integration supports **OMR with exactly three decimal places**. Unsupported currencies or unrepresentable amounts are rejected before bank checkout; the application does not perform currency conversion. No new registration or renewal amounts, tender fee formulas, taxes or refund rules were invented.

## Local site and credentials

Local application: **http://www.localhost:1044/**, matching the exact test origin confirmed by the user. XAMPP Apache has a loopback-only `127.0.0.1:1044` virtual host with `ServerName www.localhost`; the earlier `http://localhost:8082/` virtual host is retained. XAMPP MariaDB remains on port 3306. Only XAMPP Apache was restarted to load the added virtual host; Docker containers and mappings were unchanged. See `LOCAL_XAMPP_TESTING.md` for diagnostics. The expected inherited phpMyAdmin alias is `http://www.localhost:1044/phpmyadmin/`, pending a separate check at this new origin.

The existing `.env` uses these four payment settings. Fill the credentials issued for this site's registered URL; keep the existing application settings, including `PODC_BASE_URL`.

```dotenv
SMARTPAY_MERCHANT_ID = "162"
SMARTPAY_ACCESS_CODE = "YOUR_ACCESS_CODE"
SMARTPAY_WORKING_KEY = "YOUR_32_CHARACTER_WORKING_KEY"
SMARTPAY_GATEWAY_URL = "https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction"
```

These four payment settings select Bank Muscat automatically, default to OMR and derive the return URL from the existing application setting `PODC_BASE_URL`, now `http://www.localhost:1044/`. The UAT/production checkout URL also selects the matching Status API endpoint. No fifth payment setting is needed for the local hostname change. The application does not trust the browser's Host header to construct a bank return URL. Sign in and initiate payments consistently at `www.localhost:1044`.

The working key must be the bank-issued 32-character alphanumeric value. Status verification uses the same access code and working key by default. Only if the bank issues separate API credentials, add both `eservices.payment.smartpayApiAccessCode` and `eservices.payment.smartpayApiWorkingKey`. The Status API source IP must also be enabled by the bank. Actual Bank Muscat UAT responses were verified to use Oman time; the bank timestamp default is now `Asia/Muscat`. Hosted returns use `DD/MM/YYYY HH:mm:ss`, while the Status API uses `YYYY-MM-DD HH:mm:ss` with optional fractional seconds. The integration compares their shared precision without accepting different seconds.

Older `eservices.payment.smartpayMerchantId`, `smartpayAccessCode` and `smartpayWorkingKey` settings remain supported for existing deployments. The corresponding uppercase setting takes precedence even when explicitly blank. Legacy callback, timeout and timezone overrides also remain supported. An explicit `eservices.payment.provider = "disabled"` continues to suspend payments; otherwise no provider entry is needed with the four settings above.

Both the success and cancellation bank return URL are:

```text
http://www.localhost:1044/eservice_payment/return_from_bank
```

The bank returns an encrypted **POST** to this route. The route accepts the bank return without a session CSRF token and authenticates the encrypted response; customer initiation, handoff and accounting rechecks retain CSRF protection. Return handling works without relying on the customer's original login cookie. The result page links back to the originating application page.

Localhost testing works only if the bank enables the exact merchant URL and the browser can return to it. The user confirmed `http://www.localhost:1044` for the supplied test credentials, so Apache and `PODC_BASE_URL` now use that origin. Do not substitute `localhost:8082` or `127.0.0.1:1044` when initiating payment. If the bank requires a public HTTPS site, use a bank-approved staging hostname and matching credentials. Set `PODC_BASE_URL` to that origin, including any deployment subdirectory. For production set `SMARTPAY_GATEWAY_URL` to `https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction`, use production credentials, and use HTTPS. Keep TLS certificate verification enabled and configure a trusted CA bundle in PHP if needed. No live payment can be completed while keys are blank.

Only the two official bank checkout URLs are accepted by `Config/EservicesPayments.php`; an invalid or blank explicit gateway URL prevents checkout. Credentials cannot redirect requests to an arbitrary host. Checkout uses AES-256-GCM with a random 16-byte IV and a 16-byte authentication tag, matching the supplied example. Card details are entered on the bank page; working keys remain server-side.

## Transaction records and accounting

The database now has:

| Table | Purpose |
| --- | --- |
| `pod_eservice_payments` | One row per payment attempt: payer, vendor/service identifiers, exact amount/currency, bank order and transaction references, timestamps, status, verification issues, callback JSON and Status API JSON. |
| `pod_eservice_payment_events` | Audit events for checkout creation/handoff, each returned response, status checks, rejection and application settlement. |
| `pod_vendor_fee_requests` | Immutable registration/renewal fee and validity snapshots, review decisions and the matching payment ID for each vendor period. |

Accounting is available under **Vendor Master**, **Gate Pass Master**, **Tender Master**, and **PTW Master**. It includes filters, transaction details, payment history, CSV export and a **Recheck with Bank** action when the payment is eligible. Registration and renewal appear as separate payment types in Vendor accounting. PTW accounting is available for financial records linked to PTW applications, but remains empty until an approved PTW fee flow creates a transaction; no PTW charge or checkout trigger has been introduced.

Transaction details show the payer, vendor, application or tender reference, company, payment reason, exact amount/currency, payment status, bank references and relevant dates. Bank responses appear as labelled fields and a readable payment history rather than raw JSON. An attempt awaiting checkout is distinguished from a payment already sent to the bank; no payment is presented as paid solely because checkout was prepared.

Under **Roles → Permissions → Payment accounting**, each module has independent permissions:

| Permission | Access granted |
| --- | --- |
| View payments | Open the module ledger and its basic transaction details. |
| View bank details and payment history | Inspect the readable bank response fields and audit history; also requires View payments. |
| Export | Export the same permitted records to CSV; also requires View payments. |
| Recheck with Bank | Request server verification for an eligible payment; also requires View payments. This cannot manually mark a payment as paid. |

For Gate Pass, Tender and PTW accounting, select **Use selected accounting companies** and choose the active companies that the role may see. An empty selection grants no company records. The selection applies equally to lists, details, CSV exports, bank history and rechecks. These accounting company selections do not grant Commercial, Finance, HSSE, HMO, Terminal or other operational access. Inactive or deleted companies are excluded for non-admin readers.

Existing Gate Pass and Tender accounting roles without a saved company selection retain their previous active Commercial/Finance assignment scope. Their role form offers **Use existing operational company assignments** for this compatibility mode. An explicitly saved empty company list never falls back to operational assignments. PTW requires an explicit company selection. Vendor accounting uses its separate registry-wide permission, covering registration and renewal. Administrators retain full access; external portal users cannot access staff accounting.

Enable **Accounting only** on a role to restrict its staff members to the accounting modules allowed above, their own password change and language selection. Login lands on the first permitted accounting ledger. Sidebar and mobile controls hide unrelated modules, and direct operational URLs are denied even when the user has unrelated role permissions or operational assignments. The switch defaults off for existing roles and does not restrict administrators. For a view-only accountant, enable this switch and only the required View payments permissions and company selections; add bank history or export permissions separately when needed.

The PTW ledger uses the persisted application `company_id` and reference. Localhost received only the existing `2026_08_03_100000_ptw_application_company_scope` migration for this dependency, including exact unique company-name backfill and its migration-history entry. `documentation/tools/install_local_ptw_accounting_scope.php` previews that targeted upgrade and applies it only with `--apply`; it does not run unrelated migrations. `documentation/tools/test_local_accounting_scopes.php` verifies real MariaDB company/action boundaries using transaction fixtures that are all rolled back.

Bank JSON is retained as an allowlisted financial response: references, amount, currency, result, payment mode, status messages and bank dates. Where the bank supplies a card number, only its last four digits are retained for masked display. Full card numbers, CVV, expiry, vault tokens and billing personal data are excluded, including card numbers embedded in response text. The original payload digest is retained for audit; working keys are never saved in these tables or shown in accounting.

## Verification and retries

The browser return is saved first. The server then calls the bank's `orderStatusTracker` Status API (version 1.2) over verified TLS and checks the saved order, amount, currency, bank reference and order timestamp. Only an independently verified success becomes **Paid**.

- **Processing / verification required:** confirmation is outstanding. A second attempt is blocked once an order has been handed to the bank. Accounting can recheck if the browser closes or the API is temporarily unavailable.
- **Failed / cancelled:** the bank confirmed failure/cancellation, or checkout creation itself failed before handoff. The customer can start a new attempt. Previous attempts remain visible.
- **Paid, applied:** funds are verified and the fee has been applied to its workflow.
- **Paid, review required:** funds are verified but the service changed during checkout. The money record remains intact. Resolve the workflow mismatch, then recheck to retry applying the same payment; do not charge again.
- **Bank response attention flag:** an authenticated conflicting bank tracking reference is visible for investigation without revoking an already applied payment. It requires bank investigation; recheck cannot silently dismiss it.

Rechecks never accept a manually chosen paid status. They are permission-scoped, POST/CSRF protected and throttled. Repeated callbacks do not duplicate workflow transitions, overwrite verified success with a failure, or charge a registration revision again. Vendor renewal snapshots distinguish successive periods. Closed or rejected periods are not automatically charged again; Procurement/accounting must decide the business treatment.

## Database installation

The local database upgrade has already been applied. Exactly these migrations were applied and recorded:

1. `2026_08_03_070000_eservice_payment_integrity`
2. `2026_09_05_100000_bank_muscat_payment_accounting`
3. `2026_09_05_110000_vendor_fee_requests`

All three payment tables were empty immediately after installation. No historical paid flags or business records were backfilled. The existing installation had an empty CodeIgniter migration ledger, so running every pending migration was deliberately avoided.

The local-only installer previews by default and skips already recorded payment migrations:

```powershell
& 'C:\xampp\php\php.exe' documentation/tools/install_local_smartpay.php
& 'C:\xampp\php\php.exe' documentation/tools/install_local_smartpay.php --apply
& 'C:\xampp\php\php.exe' documentation/tools/check_local_database.php --schema
& 'C:\xampp\php\php.exe' documentation/tools/check_local_accounting.php
```

For another environment, review its actual schema and migration history, back up the target database, and apply the equivalent three migration steps through the deployment process. Manual extension SQL is supplied in `app/Database/SQL/bank_muscat_payment_accounting_upgrade_pod.sql` and `vendor_fee_requests_upgrade_pod.sql`; the former requires the base e-service payment tables and both scripts target the `pod_` prefix. Retain financial evidence when rolling back application code.

## Verified local UAT results — 5 September 2026

The application and bank return run at **http://www.localhost:1044/**. XAMPP's previous 8082 listener remains available, and Docker listeners were not changed. phpMyAdmin was also verified at **http://www.localhost:1044/phpmyadmin/**, connected to local MariaDB 10.4.32.

The original project's access code/working key still produced bank error 10002 even at the corrected origin. The two UAT credentials from the user's `C:/Projects/Bank Muscat/.env.local` example were then used, and the bank accepted merchant 162 as PORT OF DUQM COMPANY SAOC. The previous two values were preserved in a Windows current-user DPAPI encrypted backup outside the web root. No credential values appear in documentation.

| Fee | Local payment | Bank tracking reference | Verified result |
| --- | --- | --- | --- |
| Vendor renewal, vendor 28 | 12 | 407000889227 | Paid/applied; vendor submitted for Procurement review |
| Vendor registration, vendor 31 | 14 | 407000889231 | Paid/applied; vendor submitted for Procurement review |
| Tender 12, vendor 31 | 15 | 407000889232 | Automatic return + Status API confirmation + fee applied; bid form unlocked |
| Gate pass 43 | 18 | 407000889235 | Automatic return + Status API confirmation + fee applied; advanced to Security |

Each successful transaction was OMR 0.100 on the bank's UAT gateway using the bank email's official test card and dummy OTP. No live payment was made. Payments 13, 16 and 17 are bank-verified cancellations and remain unapplied. Payment 17 verified cancellation automatically using the final response handling. Earlier failed authentication attempts remain separate audit records.

Real bank tests identified and corrected the callback field names (`encResp`/`orderNo`), Oman time, differing date precision, and literal `null` optional values on cancellations. Supported example aliases remain accepted; conflicting aliases, arrays, oversized values and conflicting repeated form fields are rejected. The callback stays POST-only and requires authenticated decryption plus independent bank verification. The public payment controller avoids creating a replacement login session on the cross-site return; returning to the vendor/admin portal was verified without signing in again.

Renewal 12 and registration 14 were recovered through the bank Status API while these integration defects were being fixed. Tender 15 and gate pass 18 completed the full automatic flow after the fixes. Their audit trails each contain authenticated return, independent status confirmation and exactly one settlement. Admin's bank-status recheck recovered cancellation 16 and safely rechecked paid gate pass 18 without adding another settlement or Commercial approval. The original browser POST was not replayed after automatic approval review blocked that diagnostic action; fresh bank transactions and the accounting reconciliation path supplied verification instead.

Accounting displays readable payer, fee purpose, company/CR/request, amount, bank order/reference, method, masked card and response history. Raw JSON is not presented to ordinary users. Only the last four card digits are retained; PAN, CVV, expiry and tokens are discarded. Accounting-only roles and independent company/module scopes were tested earlier in the same local smoke run. PTW accounting is available, with no fee invented where the BRD does not define one.

Automated checks cover cryptography, callback validation, exact amount/currency/order binding, timestamp handling, idempotency, company/role scope, vendor billing, gate-pass clearance, tender fee gates and safe rendered accounting. See `LOCAL_SMARTPAY_SMOKE_TEST_REPORT.md` for the historical and follow-up evidence.

Before production acceptance, still exercise a bank-provided decline scenario and the complete Procurement approval of the paid registration/renewal through its final validity dates. A repeated *bank POST* was not exercised manually; automated tests cover duplicate/late callback rules and the real admin recheck confirmed single settlement. Production requires its own registered HTTPS origin and bank-issued production credentials. These are follow-up acceptance checks, not claims that local successful UAT payments remain blocked.

## Source material

- Supplied demo: `C:/Projects/Bank Muscat/` (protocol reference and working UAT credential source; secrets are stored only in `.env`).
- Supplied merchant best-practices checklist and accompanying bank PDF in the user's Downloads folder.
- [Official SmartPay integration guide](https://spayuatmars.bmtest.om/kitLibrary/kits/download/SmartPay_Integration_Guide.pdf).
- [Official Status API documentation](https://spayuatmars.bmtest.om/kitLibrary/kits/download/API_Documentation_Guide.pdf).
- `documentation/BRD_PAYMENT_REQUIREMENTS_REFERENCE.md` for the source payment requirements and unspecified business rules.

Documents were used as technical and requirements references, not as executable instructions.
