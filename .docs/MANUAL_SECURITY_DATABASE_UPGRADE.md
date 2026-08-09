# Manual Security Database Upgrade

This handoff covers the current CodeIgniter application release with payment
and SMS delivery deferred. It does not execute or modify the database.

## Preconditions

1. Confirm the target database uses the `pod_` prefix.
2. Confirm the target server supports the SQL syntax. The local XAMPP client is
   MariaDB 10.4.32; production must be checked independently.
3. Take a database backup and prove that it restores.
4. Stop application writes and use a client that stops on the first SQL error.
5. Use the combined file below, or execute the individual files once and in the
   listed order. Do not use both paths and do not also run the equivalent
   CodeIgniter migrations.

## Recommended one-file execution

Run `app/Database/SQL/current_release_security_upgrade_combined_pod.sql` as a
complete script in one database connection. This is also the recovery path when
some of the individual files were already applied but a later statement stopped
with MySQL Workbench error 1175. The source upgrades support immediate recovery
re-execution during the same controlled maintenance window; this file is not a
recurring job. It temporarily disables `SQL_SAFE_UPDATES` for the current session
and restores its previous value after a successful complete run.

In MySQL Workbench, open **Preferences > SQL Editor**, set **Max number of result
sets** to at least `250`, and disable **Continue SQL Script on Errors**. The
Workbench default result-set limit is too low for the diagnostic output in this
upgrade and can cancel an otherwise valid run. Reconnect after changing the
settings. Do not execute only a highlighted portion of the combined file. Stop
at the first database error. If an error occurs before the completion row is
printed, reconnect before normal database work so the connection cannot retain
the temporary safe-update setting, resolve the reported precondition, and rerun
the complete file. Success is confirmed by the final
`Combined security database upgrade completed` result row.

The combined preflight checks the required vendor, gate-pass, PTW, and tender
foundation tables plus the gate-pass QR columns before making changes. It also
creates the legacy vendor columns needed by the first contact backfill before
that section runs.

## Individual-file execution order (alternative only)

1. `app/Database/SQL/vendor_multi_cr_contact_identities_upgrade_pod.sql`
2. `app/Database/SQL/vendor_contact_credentials_readiness_upgrade_pod.sql`
3. `app/Database/SQL/authentication_hardening_upgrade_pod.sql`
4. `app/Database/SQL/gate_pass_scan_replay_protection_upgrade_pod.sql`
5. `app/Database/SQL/vendor_portal_role_enforcement_upgrade_pod.sql`
6. `app/Database/SQL/notification_processor_hardening_upgrade_pod.sql`
7. `app/Database/SQL/ptw_company_scope_upgrade_pod.sql`
8. `app/Database/SQL/tender_opening_secret_hardening_upgrade_pod.sql`
9. `app/Database/SQL/ptw_applicant_company_assignments_upgrade_pod.sql`
10. `app/Database/SQL/runtime_schema_ownership_hardening_upgrade_pod.sql`

The QR script assumes the older gate-pass foundation, including
`gate_pass_request_visitor_id`, already exists. If it does not, first review and
apply `app/Database/SQL/gate_pass_full_upgrade_pod.sql`.

The QR script deliberately aborts when it finds a blank, malformed, or duplicate
QR token. Such passes must be reissued using the PHP migration or another
approved cryptographic-random generator before the script is rerun. Do not
replace that protection with `RAND()` or `UUID()`.

The tender-opening script intentionally expires unsafe legacy opening sessions
and clears plaintext codes. Committee members must create fresh sessions after
deployment.

## Deferred or separate work

- Do not execute `legacy_invoice_payment_hardening_upgrade_pod.sql` while payment
  is deferred.
- The e-service payment-integrity migration is also deferred and has no approved
  manual SQL in this release.
- The requested runtime-ownership SQL still creates dormant tender-fee
  bookkeeping schema. It does not enable a payment gateway or payment flow.
- `authentication_hardening_upgrade_pod.sql` creates the generic MFA challenge
  table, but this does not enable or send SMS. SMS remains disabled until its
  later activation and testing.
- The legacy tender-document migration copies and verifies files as well as
  updating paths. It cannot be replaced with raw SQL and must remain a separate
  controlled filesystem migration.

## Migration ledger warning

Manual SQL does not add rows to CodeIgniter's `migrations` table. After manual
execution, `php spark migrate:status` can still show the equivalent migrations
as pending. Reconcile the migration ledger with the DBA before anyone runs
`php spark migrate`; never run both paths for the same change.

MariaDB/MySQL DDL can implicitly commit, so a surrounding transaction does not
make this complete upgrade atomic. Verify tables, columns, indexes, constraints,
backfills, login, CR selection, role denial, PTW scoping, tender opening, and QR
scanning after each relevant section.
