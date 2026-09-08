# Port of Duqm PHP 8.1.34 compatibility build

Date: 2026-09-08. The production host is fixed at PHP **8.1.34**, per the owner.

This project retains the **CodeIgniter 4.7.4 security baseline** with a small project-maintained compatibility patch. This is **not an upstream-supported PHP 8.1 release of CodeIgniter**. Upstream 4.7 requires PHP 8.2; replacing this patched `system/` directory with a stock 4.7 release will break this host. Do not restore the old 4.6.1 runtime or remove the security baseline guard.

## Patch scope

- Eleven framework `final readonly class` declarations use `final class` with all **28 properties individually readonly**, supported by PHP 8.1. The shared `CodeIgniter\Compatibility\NoDynamicProperties` trait rejects dynamic property writes. Class-level readonly reflection metadata is consequently unavailable, while declared property immutability and finality remain enforced.
- `system/Test/Mock/MockCache.php::clean()` declares `bool` instead of PHP 8.2's standalone `true` return type. Its implementation still always returns `true`.
- Root `index.php` and `spark` require PHP **8.1.34** or later. Both use the same patched framework.
- Production bootstrap sets `zend.exception_ignore_args=1`, since PHP 8.1 does not implement engine-level `SensitiveParameter` redaction. Exception traces omit argument values.
- All other framework files remain byte-identical to the previously bundled 4.7.4 build, including trusted-proxy HTTPS detection, batch deletion escaping, uploaded-file path sanitization, and filename/MIME validation fixes. `CodeIgniter::CI_VERSION` and the production minimum-framework guard are unchanged.

Patch file hashes are recorded in `php81-compatibility-manifest.json`. This identifies the local patch; it is not a claim of upstream support. Future framework upgrades must re-evaluate this patch and repeat PHP 8.1 tests while this hosting constraint remains.

## Validation

- Downloaded the official portable **PHP 8.1.34** runtime into a private temporary folder; XAMPP, Docker containers, and their ports were left unchanged.
- Initial PHP 8.1 lint: 4,524 application/framework/entry-point files checked; exactly the eleven readonly classes and one mock return declaration failed. All twelve corrected files, the new trait, and updated entry points passed PHP 8.1 lint.
- `tests/FrameworkPhp81CompatibilityTest.php`: **62 assertions passed** on both PHP **8.1.34** and **8.2.12**. Tests cover immutable properties, rejected dynamic writes, model casting, URI construction, route readers, filter collection, cache behavior, and secret-free production exception traces.
- 109 standalone test scripts run on PHP 8.1.34: **107 passed**, **2 existing failures**. The same two failures reproduce on XAMPP PHP 8.2.12: `ProductionTransportHardeningTest.php` and `ServerExposureHardeningTest.php` expect local development debug output to be disabled; current `app/Config/Boot/development.php` enables it. This repair does not change those pre-existing local settings. Production uses its separate bootstrap with debug output disabled.
- Three database/live-settings tests were not run: `SmsLoginSettingsDatabaseTest.php`, `SmsWorkflowDatabaseTest.php`, and `VendorLoginMobileDatabaseTest.php`. No production business records, SMS messages, or payment transactions were created during this repair.
- A temporary PHP 8.1.34 web server returned **HTTP 200** for sign-in, vendor registration, and gate-pass registration using the local database.
- After deployment, production `/` returns **302** to sign-in; `/index.php/signin`, `/signin`, `/index.php/guest_vendor`, and `/index.php/guest_gate_pass` all return **200**. The browser rendered the sign-in and vendor form; an existing authenticated admin session also reached the dashboard successfully. The agent did not enter production login credentials or perform an OTP/payment transaction.
- Production `.env`, `error_log`, direct framework source, and both existing public deployment ZIP names return **403**; guest-registration CSS returns **200**. The root PHP error log gained no entries during verification. The application log recorded one CSRF refusal for a background `notifications/count_notifications` POST; no PHP compatibility crash was recorded.

The one existing assertion changed for this patch is in `CliMigrationEntryPointTest.php`: the minimum entry-point PHP version is now `8.1.34` instead of `8.2`, matching the approved hosting constraint. The 4.7.4 security baseline assertions remain unchanged.

## Production repair backups

Before this compatibility patch, the failed deployment contained an old framework and an incompatible new App configuration. Missing framework and routing files were restored first.

- Original framework/root files: `/writable/codex-deploy-20260908-framework474/backup/`.
- Files immediately before the PHP 8.1 compatibility patch: `/writable/codex-deploy-20260908-php8134/backup/`.
- Backups are kept under the site's protected writable directory. Each patched file is staged, downloaded for SHA-256 verification, and activated by rename. The PHP entry-point gate is changed last.
- Production `.env`, existing database credentials, and customer uploads are preserved. The schema migration and integration configuration items in `production-db-review-2026-09-08/PRODUCTION_SETUP.md` still require separate verification.

## Hosting limitation

PHP 8.1 is beyond upstream security support. This patch addresses application compatibility and preserves the bundled framework fixes; it cannot provide PHP engine security updates or upstream framework support for PHP 8.1. Any hosting-vendor extended maintenance must be confirmed with that vendor.

References: [CodeIgniter server requirements](https://codeigniter.com/user_guide/intro/requirements.html), [4.7 upgrade requirements](https://codeigniter.com/user_guide/installation/upgrade_470.html), [4.7.4 security fixes](https://codeigniter.com/user_guide/changelogs/v4.7.4.html), [official PHP Windows archives](https://downloads.php.net/~windows/releases/archives/).
