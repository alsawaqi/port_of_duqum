# Tender Three-Key Opening Secret Hardening

## Purpose

Tender opening assigns a short-lived six-digit code to each required committee role: chairman, secretary, and ITC member. The application protects those role codes with two independent deployment secrets:

- TENDER_OPENING_CODE_HMAC_KEY authenticates a role code with HMAC-SHA256.
- TENDER_OPENING_CODE_ENCRYPTION_KEY encrypts the recoverable, short-lived display value with AES-256-GCM.

The role is the third part of the “three-key” control; it is not a third environment encryption key. Each code is bound to the opening ID, tender ID, opening stage, and committee role. The application revalidates the active committee assignment before revealing or accepting a role code.

## Required deployment properties

1. Generate two different secrets with a cryptographically secure random-number generator.
2. Each decoded secret must contain at least 32 random bytes.
3. The recommended representation is base64: followed by the Base64 value.
4. The HMAC and encryption values must not be identical or derived from one another.
5. Placeholder, missing, malformed, or short values cause the vault to fail closed.
6. The production PHP/OpenSSL runtime must support AES-256-GCM.

The checked-in .env.example contains intentionally invalid CHANGE_ME placeholders. Never put the real values in .env.example, source code, SQL, documentation, issue trackers, chat, or Git.

An operator may generate one value with PHP's cryptographically secure random_bytes function:

    php -r "echo 'base64:', base64_encode(random_bytes(32)), PHP_EOL;"

Run the command separately twice and capture each output directly into the approved secret store. Do not paste the generated output into a terminal transcript, support ticket, build log, or this document.

## Safe deployment

1. Create the two secrets in an approved password vault, deployment secret store, or protected production environment file outside the repository.
2. Assign one independently generated value to TENDER_OPENING_CODE_HMAC_KEY and the other to TENDER_OPENING_CODE_ENCRYPTION_KEY.
3. Restrict read access to the operating-system account that runs the application and to explicitly authorized operators.
4. Deploy both values atomically to every application worker. Restart long-running PHP workers if the hosting model caches environment values.
5. Run the local contract/unit test before release:

       php tests/TenderOpeningSecretHardeningTest.php

6. In staging, create a new opening session and verify that only the active assigned role can display and confirm its own code, incorrect attempts are limited, the response is not cached, and the secrets are scrubbed after expiry or completion.
7. Never print either environment value during a health check. A readiness check should report only ready or not ready.

Do not reuse the application encryption key, cron key, database password, API key, or one tender key as the other tender key.

## Runtime protections

- Codes contain exactly six digits and are created with a cryptographically secure generator.
- HMAC comparison is timing-safe.
- AES-256-GCM protects confidentiality and integrity of the recoverable value.
- Authenticated context prevents a code sealed for one opening, tender, stage, or role from being reused in another.
- General opening reads exclude protected secret columns.
- Confirmation locks the opening row and requires distinct active committee users in the required roles.
- Failed confirmations are limited per user and per IP over the configured failure window.
- A confirmation attempt prevents unsafe regeneration of the active session.
- Expiry, unlock, signing, and other terminal transitions null the protected hashes and ciphertext.
- The signed/CLI cron cleanup calls expire_old_sessions() so expired values are physically scrubbed.
- Role-code responses are private and no-store.

## Rotation

There is no previous-key fallback. Replacing either secret immediately makes existing active ciphertext unreadable or existing HMAC values unverifiable. Rotation must therefore be coordinated; do not silently rotate keys while an opening session is active.

Safe rotation procedure:

1. Schedule a maintenance window and prevent creation or confirmation of tender-opening sessions.
2. Identify every active codes-generated opening session through an authorized administrative process.
3. Use the application's expiry/cleanup path to expire and scrub active role-code hashes and ciphertext. Confirm that no active session retains protected code material.
4. Follow the operations backup policy before deployment, while keeping secret values out of database backups and backup notes.
5. Generate two new, independent secrets and update both environment variables atomically on every worker.
6. Restart affected workers and run the local test plus a staging role-bound opening test.
7. Resume tender opening only after all instances report ready.
8. Create fresh opening sessions and distribute new role codes through the authorized application views.
9. Retire the old values from the secret store according to the approved retention policy. Do not restore or commit them as a rollback shortcut.

If an application rollback is required, coordinate it separately from key rollback. Old active codes should remain expired; generate a fresh session under the current approved keys.

## Key loss or suspected disclosure

Key loss means active role codes cannot be recovered safely. Pause opening activity, expire and scrub affected active sessions, deploy a new independent key pair, and create fresh opening sessions. Do not attempt to recover codes from logs, user devices, or database exports.

For suspected disclosure:

1. restrict tender-opening activity and preserve security logs without copying secrets;
2. determine affected environments and time range;
3. expire and scrub active sessions;
4. rotate both tender keys using the safe procedure above;
5. review role-code display and confirmation audit events for misuse;
6. document the incident and remediation without recording key material.

## Deployment checklist

- [ ] Both variables are supplied outside Git.
- [ ] Each decoded value has at least 32 random bytes.
- [ ] Values are independently generated and different.
- [ ] File/secret-store permissions are restricted.
- [ ] No value appears in source, example files, SQL, logs, tickets, chat, or documentation.
- [ ] PHP/OpenSSL supports AES-256-GCM.
- [ ] TenderOpeningSecretHardeningTest.php passes.
- [ ] Staging role, rate-limit, expiry, and no-cache behavior is verified.
- [ ] Rotation owner and maintenance procedure are recorded.
- [ ] Cron expiry cleanup is scheduled and monitored by operations.

