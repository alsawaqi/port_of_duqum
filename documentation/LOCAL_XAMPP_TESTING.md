# Local XAMPP testing alongside Docker

Open **http://www.localhost:1044/**. The user confirmed this exact hostname and port for the Bank Muscat test credentials. XAMPP now has a dedicated Apache virtual host for `C:/xampp/htdocs/port_of_duqum`, bound to **127.0.0.1:1044** with `ServerName www.localhost`. Browser sign-in has been verified at this origin. The earlier **http://localhost:8082/** virtual host remains available, but use the confirmed 1044 origin for current payment tests.

The virtual host is in `C:/xampp/apache/conf/extra/httpd-vhosts.conf`. It has `DirectoryIndex index.php`, `FallbackResource /index.php`, and `AllowOverride All`, so the application's existing routing and protected-directory rules apply. It is a loopback-only listener. Do not remove the existing Apache ports 80/443, stop containers, or change Docker port mappings to run this project.

Verified on 2026-09-05:

| Component | Local setting |
| --- | --- |
| Application | `http://www.localhost:1044/` |
| Apache | XAMPP 2.4.58; loopback port 1044; earlier loopback port 8082 retained |
| PHP | XAMPP PHP 8.2.12 |
| Required extensions | `openssl`, `curl`, `mysqli`, `intl`, `mbstring`, `fileinfo` enabled |
| Database | Existing XAMPP MySQL/MariaDB listener on 3306; use the project's configured database |
| Environment | `CI_ENVIRONMENT = development` locally |

Docker has separate active listeners, including 8080, 8085-8088, and other ports. Only the verified XAMPP Apache process was restarted to load the added 1044 virtual host; Docker containers and their port mappings were unchanged. Ports can change after a restart; check ownership before reassigning one.

phpMyAdmin is verified at **http://www.localhost:1044/phpmyadmin/** through XAMPP's inherited alias. Its browser page confirms local MariaDB 10.4.32 on 127.0.0.1. The earlier `http://localhost:8082/phpmyadmin/` address remains available.

## Environment settings

Keep the existing `.env` and its application encryption key. Merge settings into that file instead of replacing it with the production `.env.example`:

```dotenv
CI_ENVIRONMENT = development
PODC_BASE_URL = "http://www.localhost:1044/"
```

Use **`www.localhost:1044`** consistently when signing in and testing checkout. Changing the hostname or port changes the browser origin and may select a different session. `PODC_BASE_URL` supplies the origin for application links and payment return URLs. The four `SMARTPAY_*` settings remain the merchant ID, access code, working key and official UAT gateway URL; no additional payment key is required merely to change the local address. Production needs its own HTTPS origin and bank-issued credentials. Refer to `BANK_MUSCAT_SMARTPAY_SETUP.md` for the exact four settings.

Keep the local database hostname, name, prefix and credentials matched to this installation. The application supports `database.default.*` overrides in `.env`; configuring localhost does not require changing MySQL ports or importing/deleting a database.

## Repeat the read-only checks

From PowerShell in the project directory:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\documentation\tools\Check-LocalXampp.ps1
```

The checker defaults to port **1044** and hostname **www.localhost**. It resolves that hostname directly to **127.0.0.1** for its HTTP checks and bypasses proxies, preserving Apache's virtual-host routing without depending on Windows DNS. It reports the loopback listener, PHP extensions, database connectivity and payment/migration table names, and expects sign-in HTTP 200 and protected configuration/source/tool paths HTTP 403. It does not print credentials, customer rows, response bodies or session cookies. It does not start or stop services, write business records, or run migrations. HTTP requests can create normal local session/log files. The checker was updated for the new origin; its earlier successful HTTP checks were performed at port 8082.

To inspect the retained older virtual host explicitly, pass `-Port 8082 -LocalHostName localhost`. Do not use that older origin to initiate payments with credentials issued for `www.localhost:1044`.

For a read-only inventory of payment table columns, record counts, currencies and migration ledger entries, run `C:\xampp\php\php.exe .\documentation\tools\check_local_database.php --schema`. The ledger inventory is limited to the latest 60 entries per ledger table.

If Apache or MySQL is stopped, inspect the XAMPP control panel and port ownership before starting the existing service. The checker deliberately does not terminate a process merely because it occupies a port.

## Bank-hosted payment testing

Use **http://www.localhost:1044/** for local pages, accounting and bank checkout. The bank return route is **http://www.localhost:1044/eservice_payment/return_from_bank**. The earlier registration attempt from port 8082 reached the UAT gateway but received `10002 / Merchant Authentication failed`; that is historical evidence from the previous origin, not a result for the corrected address. The repeat bank-hosted outcome at the corrected origin is still being tested.

A complete bank-hosted checkout requires credentials and return URLs enabled by the bank for the intended test environment. This integration receives the bank's return through the customer's browser and verifies the order through an outbound Status API request. A separate server-to-server notification cannot reach the developer's loopback address from the bank. If the bank requires a public HTTPS endpoint, use a bank-approved HTTPS staging deployment and matching credentials, then set that origin in the environment.

Never mark an attempted payment successful because a local checkout cannot contact the bank. Use the gateway response and the application's verification/recording flow.

## Local SMS delivery

The Windows task `PortDuqm-SMS-Outbox-Local` processes the SMS outbox every minute while the Admin Windows account is signed in. It runs `scripts/process-local-sms.ps1`, which starts XAMPP PHP with `spark sms:process` in a hidden window. No additional listener or port is used. Overlapping worker runs are disabled. Inspect or disable this task in Windows Task Scheduler when changing the local installation.

Delivery settings are under **Settings → SMS**. Both the connection and real workflow delivery are enabled locally. Old preview records are never converted into live sends. The worker's first live test was accepted by iSmartSMS on 7 September 2026; each notification's result is visible on the SMS settings page.

Vendor registration now requests a separate personal Oman mobile for the login account. New contacts also need their own mobile. Adding another CR or editing a contact preserves an established global login number. Approval can fill a blank external vendor login number only from one unambiguous, approved personal contact mobile; existing nonblank numbers and internal/admin accounts require explicit account editing.

The live registration → contact approval → SMS OTP → CR selection → vendor portal test passed. Mandatory login OTP remains disabled until all active accounts have valid personal mobiles. As of 7 September 2026, 36 accounts still need correction; **Settings → SMS → Show accounts to update** displays their names, emails, and account-edit links. Do not put one shared test number on customer accounts merely to clear this list.

After correcting the accounts and confirming delivery, enable **Settings → SMS → SMS login verification → Require SMS OTP for all logins** and click **Save login verification**. Only administrators can change this policy. Enabling checks the saved SMS connection, verification security key, and active account mobiles. It applies to every login type from the next sign-in; existing sessions are not ended. The SMS connection cannot be disabled while SMS login is required.

The policy is stored in the existing settings table under `sms_private` as `sms_login_otp_required`. Once saved, it overrides the MFA enabled flag, provider and scope from `.env`, selecting `ismartsms` for `*` without per-type exceptions. Before the first save, the environment remains the fallback. The HMAC security key stays in `.env`. OTP is sent during sign-in and does not wait for the background outbox worker. Disabling real workflow delivery does not disable OTP; use the separate login verification switch. Policy saves are recorded in the authentication audit log.
