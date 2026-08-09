# Tender 3-Key Secret Hardening

## Deployment prerequisites

The application requires two independent production secrets:

```text
TENDER_OPENING_CODE_HMAC_KEY=base64:<32-or-more random decoded bytes>
TENDER_OPENING_CODE_ENCRYPTION_KEY=base64:<different 32-or-more random decoded bytes>
```

Generate each value independently:

```powershell
php -r "echo 'base64:' . base64_encode(random_bytes(32)), PHP_EOL;"
```

Provision the resulting values through the production host, container, or
orchestrator secret manager. Never put the real values in the project `.env`,
source control, deployment logs, tickets, database, or a client-side response.
The two decoded values must be different. PHP must provide OpenSSL with
AES-256-GCM support.

The application deliberately has no default or development fallback. Code
generation and confirmation return a generic HTTP 503 response when the vault
is not ready, including in production.

## Protected workflow

- The chairman, secretary, and ITC member each receive a different six-digit
  code.
- A signed HMAC-SHA-256 value is stored for verification. The code itself is
  not stored in plaintext.
- A short-lived AES-256-GCM envelope is stored only because the assigned role
  must retrieve its own code in the authenticated portal. The opening ID,
  tender ID, stage, role, and envelope version are authenticated context.
- A committee member sees and submits only the code assigned to that member's
  active tender role; assignment is revalidated by the model for display and
  confirmation. Submitted plaintext is never written to the confirmation audit
  table.
- Unlock requires one valid confirmation for every required role and three
  distinct user IDs. Generation, confirmation, manual acceptance, and unlock
  use a tender-first database lock order to serialize the ceremony.
- Regeneration is refused after any confirmation attempt or after an opening
  reaches unlocked, signed, or manual-accepted status. This prevents a newer
  session from hiding or resetting an official opening.
- Failed confirmation attempts are limited per user (5) and per source IP (20)
  in a rolling 15-minute window across regenerated sessions for the tender
  ceremony. A limited request receives HTTP 429 and `Retry-After`.
- Hashes and ciphertext are deleted when a session expires, is replaced,
  unlocks, or is accepted through the manual form process.
- Views and code-display responses use `Cache-Control: private, no-store`.

## Upgrade order

1. Back up the database using the approved encrypted backup process.
2. Provision both production environment secrets and verify PHP OpenSSL support.
3. Run migration
   `2026_08_03_110000_tender_opening_secret_hardening.php`, or run
   `app/Database/SQL/tender_opening_secret_hardening_upgrade_pod.sql` for a
   manual `pod_` deployment.
4. Deploy the application code.
5. Configure the signed scheduler to run at least every five minutes. Its
   authenticated Cron path calls
   `Tender_bid_openings_model::expire_old_sessions()` on every successful
   run.
6. Generate a fresh opening session and verify that each assigned role sees
   only its own code.

The upgrade scrubs all legacy plaintext code values and submitted values.
Any old active session that lacks the new protected fields is marked expired;
this is intentional and cannot be reversed by the down migration.

Pre-upgrade backups, replicas, database binary logs, and exported dumps may
still contain historical plaintext. Keep them encrypted and access-controlled,
then expire or securely destroy them under the approved retention policy.

Code use is denied immediately at `expires_at`. Physical removal of the HMAC
and ciphertext happens on the next portal access and through the signed
scheduled cleanup, so the scheduler is required to keep at-rest retention close
to the five-minute lifetime.

The web application performs read-only schema readiness checks. All DDL, index
creation, and one-time legacy scrubbing belong to the migration/manual SQL so
the production application account does not require schema-alter privileges.

## Rotation and incident response

There is no previous-key fallback. Before rotating either key, allow all active
five-minute sessions to expire or explicitly expire and scrub them. Rotate the
two secrets independently, restart the PHP workers, and generate fresh sessions.

If either secret might have been exposed:

1. Disable new 3-key confirmations.
2. Expire and scrub every `codes_generated` session.
3. Rotate both production secrets.
4. Restart the application workers and regenerate required sessions.
5. Review confirmation audit records for unexpected failures, users, source
   addresses, and user agents. Audit records contain metadata only, not entered
   codes.
