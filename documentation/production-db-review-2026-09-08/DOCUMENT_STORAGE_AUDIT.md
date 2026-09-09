# Production document storage audit

Verified on 8 September 2026, PHP 8.1.34, after the public-image access-rule correction.

## Result

Audited 136 nonempty file references across vendor, gate pass, PTW and tender tables. After repair, 131 references resolve to nonempty files that production PHP can open and read. Five vendor references point to missing originals. These are reference counts, not distinct physical-file counts: PTW requirements and attachments can refer to the same file.

| Section | References | Readable | Missing |
| --- | ---: | ---: | ---: |
| Vendor documents and bank letters | 18 | 13 | 5 |
| Gate pass visitor and vehicle attachments | 72 | 72 | 0 |
| PTW attachments, signatures and requirement attachments | 34 | 34 | 0 |
| Tender documents, bid documents and evaluation attachments | 12 | 12 | 0 |

No existing file failed the PHP read-permission check, and no checked reference remains rejected by the expected protected-storage path resolver. Sources with no saved attachments were also checked but contribute zero references. Dynamically generated permits and gate-pass PDFs are outside this stored-file audit.

## Tender repair completed

Tender document IDs **11** (tender **10**) and **12** (tender **11**) still referenced `files/tender_files/...`. Their originals existed, but current application download controllers require `writable/uploads/tender_documents/...`.

Validated the original JPEG and PDF, backed up both source files and database rows, copied them into the protected per-tender directories, compared SHA-256 hashes, and updated only those two document rows in a transaction. Updated fields were path, MIME, size and timestamp. No migration ledger, user, workflow, payment or SMS setting was changed.

Both repaired paths passed a subsequent independent read-only audit. Original files remain in the blocked legacy folder. Additional private backup: `writable/codex-deploy-20260908-document-storage/` on production.

## Five original vendor files still required

See [MISSING_VENDOR_FILES.csv](MISSING_VENDOR_FILES.csv) for exact storage paths.

| Vendor ID | Table / record ID | Missing file |
| --- | --- | --- |
| 19 | vendor_documents / 7 | PDF document |
| 21 | vendor_documents / 8 | PDF document |
| 18 | vendor_bank_accounts / 10 | PDF bank letter |
| 18 | vendor_bank_accounts / 11 | PDF bank letter |
| 19 | vendor_bank_accounts / 12 | PDF bank letter |

All three vendor records are approved and not deleted. The missing files were absent from their production paths, the local project's upload directories, and the directory listings of both existing production deployment ZIP archives (`poderp.zip`, `poderpv2.zip`). Another backup or the original documents is needed. No substitute files were created and their database records were retained.

## Verification scope

The production checks used expiring, bearer-protected fixed-purpose endpoints, a read-only database transaction for each audit, canonical path resolution and PHP file-open checks. The tender repair endpoint accepted no SQL, arbitrary path or arbitrary action input. Every temporary endpoint was removed after its run. Document contents were not returned by the audit.

Seven existing document/upload/access suites passed locally on PHP 8.1.34: TenderDocumentPreviewAccess, GatePassStageScopedDocumentAuthorization, TenderProtectedStorageSecurity, TenderClarificationScopeAttachment, TenderReportGroupUpdateAttachment, PtwApplicantAuthorization and UploadSecurityHardening.

This verifies storage and source-level authorization contracts. It does not claim every production role's browser preview/download workflow was exercised, or that every stored document is semantically valid.
