# Tender Document Storage Migration

## Required end state

All tender source documents must be stored below:

`WRITEPATH/uploads/tender_documents/tender_{tender_id}/`

The database stores only a protected relative path such as:

`tender_documents/tender_17/td_<random>.pdf`

Documents are served only through an authenticated controller after tender/company/role authorization and canonical path-containment checks. There is **no public compatibility bridge** for `files/tender_files/`. Both the root web rules and `files/tender_files/.htaccess` deny the legacy URL.

New documents are written to protected storage by the procurement workflow. Vendor, Procurement, Procurement Manager, Technical, Commercial and Report controllers resolve files through `Upload_security::resolveStoredFile()`; they do not fall back to the public legacy directory.

## Security properties

- Accepted legacy migration types are PDF, JPEG and PNG after server-side `fileinfo` detection; PDFs must contain a valid EOF marker.
- The legacy path must match the row's exact tender directory and must remain beneath the canonical legacy root. Null bytes, traversal components, missing files and unsupported types fail the migration.
- Target directories and files request modes 0750 and 0640.
- Target names use 24 cryptographically random bytes and do not reuse client filenames.
- Source and destination size and SHA-256 digest are compared before the database path changes.
- Database path updates are conditional on the original row value and occur in one transaction.
- If copying or validation fails, the transaction rolls back and newly created targets are removed.
- After a successful commit, the migration removes legacy source files. A removal failure is logged as critical; the static URL remains denied.
- The migration `down()` deliberately does not copy protected files back into a public path.

## Production prerequisites

1. Complete and sign a full database/upload backup and isolated restore drill.
2. Place the application in a maintenance state so no tender document row/file changes during migration.
3. Confirm `CI_ENVIRONMENT=production`, an explicit HTTPS base URL, protected `WRITEPATH`, and a DML-only application identity after migrations.
4. Enable PHP `fileinfo` and OpenSSL; enable only other modules required by reviewed features.
5. Make the release tree read-only. Grant the migration process write access to the protected target and legacy source only for the approved window.
6. Configure the real malware-scanner hook and retain `UPLOAD_MALWARE_SCAN_FAIL_CLOSED=true`. Before migration, scan every legacy source with the approved engine and record only engine/version, verdict, time and document ID in the restricted manifest. The filesystem migration validates type and integrity but is not itself a substitute for a malware verdict.
7. Align WAF/proxy, Apache and PHP request limits with application upload limits.
8. Confirm `.gitignore` excludes runtime documents and that the release artifact contains no customer/tender files.
9. Purge historical customer documents from all Git refs/caches and protect or expire approved historical backups separately; an index deletion is not a history purge.

## Preflight inventory

Run the following against the correct database and replace `pod_` only if the deployed prefix differs:

```sql
SELECT id, tender_id, path
FROM pod_tender_documents
WHERE deleted = 0
  AND path LIKE 'files/tender_files/%'
ORDER BY id;
```

For each row, build a restricted migration manifest containing document ID, tender ID, legacy relative path, byte count and SHA-256 checksum. Protect the manifest as sensitive metadata. Do not include file content in tickets or logs.

Before the change window, investigate and resolve any row that has:

- a missing or non-file source;
- a path outside `files/tender_files/{tender_id}/`;
- a traversal/null-byte component;
- a MIME other than PDF/JPEG/PNG;
- a malformed PDF;
- a missing, failed, timed-out or non-clean malware scan verdict;
- a duplicate, orphaned or incorrect tender relationship.

Do not weaken the migration to skip an unsafe row silently. Quarantine/convert an unsupported business document through an approved offline process, update the source and manifest, then restart the clean migration.

## Migration procedure

The reviewed migration is:

`app/Database/Migrations/2026_08_03_140000_migrate_legacy_tender_documents.php`

1. Record the exact release commit and migration checksum.
2. Stop application writes/background jobs and take the final coordinated database/file backup.
3. Run the CodeIgniter migration through the approved migration identity, normally:

   ```text
   php spark migrate --all
   ```

   If PODC uses reviewed manual SQL for other schema changes, do not execute equivalent migration and SQL twice. This document migration is filesystem-aware and must use the reviewed migration or an independently reviewed migration utility with identical guarantees.
4. Capture sanitized migration output and the resulting migration ledger.
5. Confirm there are no active database rows with a public legacy path:

   ```sql
   SELECT COUNT(*) AS legacy_rows
   FROM pod_tender_documents
   WHERE deleted = 0
     AND path LIKE 'files/tender_files/%';
   ```

   Acceptance value: `0`.
6. Rebuild the protected manifest from database rows and disk. Reconcile row count, byte count, source/target SHA-256 mapping and tender ownership against the preflight inventory.
7. Verify the legacy directory contains no required source. Any copy left because deletion failed must be handled under the approved secure-deletion/retention procedure; public access remains denied.
8. Restore the web process to its final least-privilege permissions and revoke the migration identity's temporary access.

## Authorization and direct-access acceptance tests

Use non-sensitive test records and at least two companies/vendors.

1. An authorized Procurement user can view/download its company's tender document.
2. Procurement Manager, Technical, Commercial and Report users can access only the parent tender and workflow stages allowed to their assignments.
3. An eligible vendor can access only the selected CR's tender after required payment/eligibility checks and only within the 72-hour paid window where applicable.
4. A different company/vendor, revoked assignment, forged document ID, mismatched tender/document pair and traversal path receive 403/404 without revealing paths.
5. The public legacy URL `/files/tender_files/<any-object>` returns 403/404 and never file content.
6. `/writable/uploads/tender_documents/<any-object>` is not publicly reachable.
7. Responses use safe content type, `X-Content-Type-Options: nosniff`, private/no-store caching for protected material, and safe filename disposition.
8. Download/audit records identify the authorized actor, vendor/company/tender/document, action and time without recording content or credentials.

Relevant regression contracts are `tests/TenderProtectedStorageSecurityTest.php`, `tests/TenderDocumentPreviewAccessTest.php`, `tests/TenderCompanyAuthorizationHardeningTest.php`, and `tests/UploadSecurityHardeningTest.php`. Run them as part of the release, but also execute the HTTP and database tests above.

## Failure and rollback

- Before commit, the migration rolls back database changes and removes target copies it created.
- After commit, source removal is not transactionally reversible and `down()` does not republish documents. Rollback therefore requires the coordinated pre-migration database and file backup.
- Never restore only the database or only the files; their paths and contents form one consistency boundary.
- Never restore legacy files to a publicly readable route. During rollback, keep the deny rules active and serve any required restored document through the authorized application path.
- Preserve sanitized logs and evidence, record the failed document ID/reason, remediate offline, restore the coordinated state if necessary, and rerun from a clean checkpoint.

## Post-go-live controls

- Alert if any active `tender_documents.path` begins with `files/`, contains traversal, or does not begin with `tender_documents/`.
- Periodically reconcile database rows, protected objects and checksums; investigate orphans in either direction.
- Back up protected documents with encryption, access control and recurring restore tests.
- Keep legacy URL denial, directory listing denial and script execution denial in external security scans.
- Include protected download authorization, payment-window boundaries and storage migration rules in every regression and penetration test.
