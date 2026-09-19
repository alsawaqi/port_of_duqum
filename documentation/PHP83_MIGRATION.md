# Moving this project to PHP 8.3

Prepared locally on 2026-09-10. Initially kept local as requested; subsequently deployed to **https://poderp.bedots.site** on the same date after the owner requested a FileZilla upload for testing. See the deployment verification below. No upload to the separate `/var/www/html` server has been performed.

The application is validated on **PHP 8.3.33**. It uses standard **CodeIgniter 4.7.4**, whose minimum is PHP 8.2. This copy no longer supports the former PHP 8.1.34 host. XAMPP's existing PHP 8.2.12 installation remains unchanged.

## What changed

- Restored the 12 official framework files previously modified for PHP 8.1; removed the obsolete dynamic-property compatibility trait. The framework security baseline stays at 4.7.4. File hashes are in `php83-framework-manifest.json`.
- Aligned `index.php` and `spark` with the official PHP 8.2 minimum, which permits PHP 8.3.
- Added a startup fallback for failures before CodeIgniter's logger starts. It records the exception type, source file and line in the hosting PHP error log, and shows a short support message. It does not log exception arguments or arbitrary exception messages, which could contain secrets.
- Explicitly enabled production PHP error logging while keeping error details hidden from visitors.
- Added the command-line `server-check.php` requirements checker. HTTP access returns 404. It sends no SMS/email/bank requests and changes no settings or business records. `--database` enables read-only database checks.

## Give these steps to the destination hosting administrator

1. Back up the destination files and database before replacing the application. Upload the complete updated project, especially the whole `system` directory, `app`, `index.php`, `spark`, and `server-check.php`. Avoid a mixture of the former PHP 8.1 files and the restored framework.
2. Select PHP **8.3** for the website. Check the CLI/scheduled jobs use PHP 8.3 too; changing PHP in the hosting panel does not always change the command-line binary.
3. Enable the extensions needed by the portal: `intl`, `mbstring`, `mysqli`, `mysqlnd`, `curl`, `openssl`, `fileinfo`, `gd`, `zip`, `dom`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `iconv`, `ctype`, `bcmath`, `session`, and `json`. Enable `imap` only if email inbox fetching is used. Some are built into PHP. Do not copy Windows DLLs or XAMPP's php.ini to a Linux server.
4. Import the existing complete database. Update the actual case-sensitive file **`app/Config/Database.php`** for the destination database. Production requires a dedicated non-root account with a password. Remote MySQL connections also require the existing TLS configuration; do not disable that guard to bypass an error.
5. Include the hidden **`.env`** file, using the destination's configuration rather than blindly copying localhost values. Set `CI_ENVIRONMENT = production` and `PODC_BASE_URL = https://YOUR-NEW-DOMAIN/`. Preserve the existing application encryption key so stored encrypted settings remain readable. Preserve the intended MFA key/configuration. Keep the correct domain/environment-specific Bank Muscat credentials and public callback URL. The iSmartSMS credentials may be stored in Settings/database; keep those database values when migrating.
6. Let the website PHP user read application files and write to `writable/` and the required `files/` directories, including existing subdirectories. Fix ownership as well as permissions. Do not make the entire project writable to everyone.
7. Keep the project's access-control `.htaccess` files. This root-webroot layout expects Apache-compatible access controls. On Nginx, hosting must configure equivalent routing and denials for `.env`, `app`, `system`, `writable`, backups and other private files. Do not remove protection files to hide an HTTP 500.
8. Restart/reload the destination PHP-FPM service or clear that website's OPcache after replacing PHP files. Do not copy old runtime caches or development sessions as deployment assets. Update the existing scheduled-job executable paths and domain for this server.
9. From the project directory run, using the destination PHP 8.3 executable:

   ```sh
   php -v
   php server-check.php
   php server-check.php --database
   ```

   The second command checks requirements and configuration; the third also uses `SELECT 1` and checks critical table names. It prints no credentials or customer records. Run it as the website user where possible, because permissions can differ for an SSH user. Confirm the same extensions are enabled for the website's PHP-FPM/Apache runtime.
10. Open `/index.php/signin`, `/index.php/guest_vendor`, and `/index.php/guest_gate_pass`. Then test admin login and the new Integration Tests page. Provider access from this new server, SMS sender/IP approval, bank domain registration and bank status verification still need testing on that server. Local compatibility tests do not prove those external approvals.

## If the new server still returns HTTP 500

HTTP 500 alone does not establish that PHP 8.3 is incompatible. The original application already passed its framework checks under PHP 8.3 before the PHP 8.1 workaround was removed.

- Look at the web server/PHP-FPM error log at the exact request time. Apache directives, file access, a missing PHP handler or PHP startup failures can fail before CodeIgniter runs.
- Look at `writable/logs/` for application errors. The new early-startup fallback writes `POD startup failed: <type> at <file>:<line>` to the hosting PHP log. The configuration file and line help identify the failing requirement without recording secrets.
- If the browser shows only the hosting server's generic 500 page and no `POD startup failed` log appears, ask hosting to check its PHP handler and Apache/Nginx error log first.
- Check that hidden `.env` files were transferred; `PODC_BASE_URL` uses HTTPS; encryption keys are present; the database user/credentials meet production requirements; `intl`/`mbstring`/`mysqli` are available; filenames preserve their case; and the PHP user can write to `writable/logs` and cache directories.
- If the log says an `.htaccess` directive is not allowed, hosting must correct the virtual-host/AllowOverride configuration. Do not enable public debugging or remove access controls as a substitute.

**The separate destination server's original HTTP 500 has not been reproduced or diagnosed remotely.** Its URL, PHP error log and server configuration were not provided. Successful testing on poderp.bedots.site does not establish that the unknown server fault has been resolved.

## Local validation

- Official portable PHP 8.3.33 downloaded from PHP's Windows releases; archive SHA-256 matched the official release manifest. XAMPP and Docker were not upgraded or reconfigured.
- Framework compatibility: **73 assertions passed** on PHP 8.3.33 and on existing PHP 8.2.12. Native readonly class behavior, immutable properties, route collection, model casting and exception-argument redaction are covered.
- Standalone application suites: before **112/114 passed**; after **113/115 passed**. The added startup diagnostic suite passed all 11 checks. The same two existing failures remain: `ProductionTransportHardeningTest.php` and `ServerExposureHardeningTest.php` expect local development error display/debug mode to be disabled; this project has them enabled. Production remains separate and keeps error display disabled. Those existing expectations were not weakened.
- Additional real local database suites on PHP 8.3: integration tools **52**, vendor location editing **114**, vendor email/CR sign-in and MFA behavior **38** checks passed. Fixtures were isolated or rolled back; provider delivery was mocked. No SMS, email or bank transaction was sent.
- Application/framework PHP syntax scan passed. A pre-existing syntax error in `tests/Libraries/LeftMenuTest.php:263` reproduces on both PHP 8.2 and PHP 8.3. It is a PHPUnit test fixture, not code loaded by the website; it was not changed in this migration.
- Local PHP 8.3 HTTP checks: root redirects to sign-in; sign-in, vendor guest registration and gate-pass guest registration return **200** with meaningful page bodies; Integration Tests requires authentication; `server-check.php` returns **404** over HTTP. The temporary loopback server was stopped after testing.
- Read-only preflight confirms the local session, user, settings, payment, payment-event, SMS and MFA tables exist. Its production-environment check intentionally fails on localhost's development configuration.
- Previous payment/SMS test-tool work was preserved. No production settings, database values or secrets were changed by this migration.

## FileZilla deployment verification — 2026-09-10

- The saved FileZilla destination is `poderp.bedots.site` on `92.205.177.137`. Its hosting handler had been changed to PHP 8.3. An authenticated temporary check confirmed **PHP 8.3.33, CGI/FastCGI**, and the required core integration extensions. The temporary check was removed immediately.
- Uploaded and verified all **16 runtime files** in the migration. Archived the obsolete PHP 8.1 compatibility trait. Previous files are retained in the protected remote directory `/writable/codex-deploy-20260910-php83/backup/`.
- Compared SHA-256 fingerprints before and after deployment: the server's `.env`, `.htaccess` (including its cPanel PHP 8.3 handler), and `app/Config/Database.php` were preserved exactly. No business database mutation or migration was run.
- All nine live HTTP checks passed: `/` redirects to sign-in; `/index.php/signin`, `/signin`, `/index.php/guest_vendor`, and `/index.php/guest_gate_pass` return 200 and contain the expected page titles and forms; Integration Tests redirects unauthenticated visitors to sign-in; `server-check.php` returns 404 over HTTP; `.env` and the deployment backup return 403.
- This was a deployment smoke check. It did not submit customer forms, send SMS/email, perform an OTP login, or submit a bank transaction.

## References

- [CodeIgniter server requirements](https://codeigniter4.github.io/userguide/intro/requirements.html)
- [Official CodeIgniter 4.7.4 release](https://github.com/codeigniter4/CodeIgniter4/releases/tag/v4.7.4)
- [PHP 8.3 migration guide](https://www.php.net/manual/en/migration83.php)
- [Official PHP Windows release manifest](https://downloads.php.net/~windows/releases/releases.json)

Historical PHP 8.1 deployment details remain in `PHP81_COMPATIBILITY.md`; its old manifest is retained as history, not the current framework file list.
