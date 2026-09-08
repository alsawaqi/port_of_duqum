# Production setup — 8 September 2026

This checklist describes the current application code. It does not confirm that the production server has these settings. No production deployment, configuration change, payment, or SMS was performed for this review.

Application-file uploads do not transfer settings saved in the localhost database. Merge the required configuration into the existing production environment and configure the production admin pages.

## 1. Database and existing data

The supplied production export is missing four tables: `pod_eservice_payments`, `pod_eservice_payment_events`, `pod_vendor_fee_requests`, and `pod_sms_outbox`.

1. Run [01_preflight.sql](01_preflight.sql) and review its results. These results have not yet been supplied for review.
2. Back up the production database, uploaded documents, and existing configuration. Pause writes for the cutover.
3. Apply [02_add_missing_tables.sql](02_add_missing_tables.sql) and check [03_verify.sql](03_verify.sql). These scripts target `bedotscpanel_poderp`.
4. Review existing applications and historical payments before enabling the new payment gates. An empty new ledger must not cause people with valid historical payments to be charged again.

Do not import the original schema dump as the upgrade: it contains DROP TABLE statements. Do not replace production with the localhost database or run the entire migration history blindly. See [the database review](README.md) for the tested scope and migration-ledger differences.

## 2. Production .env

Use the actual production domain. If it remains `poderp.bedots.site` and the application is installed at its root, the base URL is `https://poderp.bedots.site/`. It must not include `index.php/signin`, a localhost port, or a page path. Include the application subdirectory only if deployed in one.

The values below are placeholders to merge, not a replacement .env file:

```dotenv
CI_ENVIRONMENT = production
PODC_BASE_URL = "https://YOUR-PRODUCTION-DOMAIN/"

database.default.hostname = "localhost"
database.default.database = "bedotscpanel_poderp"
database.default.username = "YOUR_PRODUCTION_DATABASE_USER"
database.default.password = "YOUR_PRODUCTION_DATABASE_PASSWORD"
database.default.DBDriver = "MySQLi"
database.default.DBPrefix = "pod_"
database.default.port = 3306

SMARTPAY_MERCHANT_ID = "BANK_ISSUED_LIVE_MERCHANT_ID"
SMARTPAY_ACCESS_CODE = "BANK_ISSUED_LIVE_ACCESS_CODE"
SMARTPAY_WORKING_KEY = "BANK_ISSUED_LIVE_WORKING_KEY"
SMARTPAY_GATEWAY_URL = "https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction"

AUTH_SECURITY_MFA_HMAC_KEY = "YOUR_INDEPENDENT_RANDOM_OTP_SECURITY_KEY"
AUTH_SECURITY_MFA_PROVIDER = "ismartsms"
AUTH_SECURITY_MFA_PROVIDER_MAP = ""
AUTH_SECURITY_MFA_USER_TYPES = "*"

TENDER_OPENING_CODE_HMAC_KEY = "YOUR_INDEPENDENT_RANDOM_TENDER_HMAC_KEY"
TENDER_OPENING_CODE_ENCRYPTION_KEY = "YOUR_DIFFERENT_RANDOM_TENDER_ENCRYPTION_KEY"
PODC_CRON_KEY = "YOUR_INDEPENDENT_RANDOM_SCHEDULER_KEY"
```

Use the host, port, username, and database actually assigned by the hosting provider. Production code rejects a root database user or an empty password. For a remote database host, configure verified TLS through `database.default.encrypt` and the hosting provider's certificate settings; the application rejects an unencrypted remote connection.

**Preserve the existing production application encryption key**, whether configured as `PODC_APP_ENCRYPTION_KEY`, `APP_ENCRYPTION_KEY`, or `app.encryption_key`. Do not replace it with the localhost key or generate a new one during upload. Saved encrypted settings depend on it. Preserve existing tender secrets too; replacing them invalidates active tender opening codes.

For a missing OTP, tender, or cron secret, generate an independent random value for each purpose. A 64-character hexadecimal encoding of 32 cryptographically random bytes is accepted by all these fields. These are application security secrets, not values supplied by iSmartSMS or Bank Muscat. The two tender keys must differ. Keep the same established values across workers serving the same application/database. See [tender key setup](../TENDER_3KEY_SECRET_HARDENING.md) for the supported base64 format and rotation rules.

Do not copy local MFA defaults blindly. On an initial setup, leave mandatory OTP off until delivery and account mobiles have been checked. Once the policy has been saved in Settings → SMS, the saved policy overrides `AUTH_SECURITY_MFA_ENABLED`, provider, and user-type defaults in .env.

### Bank Muscat details

- Use the bank's live credentials approved for the final domain; changing the URL does not convert UAT credentials into live credentials. Do not assume test merchant ID `162` is the live ID.
- The current code accepts the live gateway URL shown above and uses `https://smartpayapi.bankmuscat.com/apis/servlet/DoWebTrans` for live status verification. The server needs outbound HTTPS access to the bank.
- With the default public-base configuration, the return address is `https://YOUR-PRODUCTION-DOMAIN/eservice_payment/return_from_bank`. Verify that the bank accepts the final domain/return address and that this URL routes to PHP on the hosting server.
- Check for an old `eservices.payment.provider` override. An explicit `disabled` value overrides the four SMARTPAY entries. Remove a stale override or deliberately configure the intended provider.
- Also remove or correct a stale `eservices.payment.smartpayPublicBaseUrl` that points to localhost.
- Separate `eservices.payment.smartpayApiAccessCode` and `eservices.payment.smartpayApiWorkingKey` are needed only if the bank supplies a separate Status API credential pair. Otherwise the code uses the checkout credentials for that API.
- Production payments require a controlled live validation after deployment. UAT test cards are for UAT.

## 3. Admin settings

| Page | What to configure |
| --- | --- |
| Settings → SMS → iSmartSMS connection | Enter the approved SMS account username, password, and exact sender name, including spaces. The previously tested sender was `Port Duqm`. Enable **Enable the SMS connection** and save. Verify that the account is enabled for HTTP GET API access from the hosting environment. |
| Settings → SMS → workflow options | Enable **Record workflow SMS notifications**, the Vendor, Gate Pass, PTW, and Tender options needed, and **Send real SMS (leave off for previews)**. Save. Preview records are not sent retroactively. |
| Settings → SMS → SMS login verification | Check the production missing-mobile list and enter each person's own Oman mobile in their account. Test the administrator's delivery and sign-in, then enable **Require SMS OTP for all logins** and click **Save login verification**. It applies on the next sign-in; existing sessions remain active. |
| Settings → Email | Select SMTP and set sender name/address, SMTP host, username, password, port, and SSL/TLS type supplied by the mail provider. Use **Send test mail to** and verify receipt. Existing working production SMTP settings can be retained. |
| Settings → Roles | Grant the module-specific accounting permissions to the appropriate users. Verify company scopes and the Accounting only role option where relevant. Production role rows are not transferred by uploading PHP files. |
| Vendor / Gate Pass / Tender masters | Check real registration/renewal fees, validity periods, gate-pass fee/waiver rules, tender fees, reviewer assignments, and committee users. No PTW charge should be assumed merely because an accounting page exists. |
| Settings → Integration → reCAPTCHA, if used | Use matching site/secret keys and protocol for the production hostname. Check that old .env CAPTCHA values do not override these settings. Test the actual guest forms. |

SMS connection values saved in Settings take precedence over `ISMARTSMS_*` .env values. If no SMS settings have been saved, the supported initial environment fields are `ISMARTSMS_ENABLED`, `ISMARTSMS_USER_ID`, `ISMARTSMS_PASSWORD`, and `ISMARTSMS_HEADER`. Prefer one clear place for managing the credentials. Login OTP delivery is immediate and separate from the workflow queue and its real-SMS toggle.

The previously reported missing-mobile account counts describe localhost, not production. A structure-only SQL dump cannot establish production mobile readiness.

## 4. Hosting and scheduled jobs

- This host is fixed at PHP **8.1.34**. Use the complete project compatibility build described in [PHP81_COMPATIBILITY.md](../PHP81_COMPATIBILITY.md), including its patched `system/` files and root entry points. Stock CodeIgniter 4.7 still requires PHP 8.2. Confirm `mysqli`, `curl`, `openssl`, `intl`, `mbstring`, and `fileinfo`; retain GD/ZIP support for the document/image functions used. Web PHP and scheduled-job PHP must load the required extensions and the same production configuration.
- Configure HTTPS and the correct application document root. Preserve the project's access protections for .env, source, SQL, and private uploads. The root .htaccess contains access rules but no general front-controller rewrite; localhost supplies `FallbackResource /index.php` through its Apache virtual host. Configure equivalent routing on production, adjusted for any subdirectory. Bank return URLs must work without `index.php`.
- Give the application account write access to required `writable` session/cache/log/upload directories and preserve existing documents. Check an actual document upload and authorized download.
- Permit outbound HTTPS to iSmartSMS, the bank gateway/Status API, and reCAPTCHA if enabled, plus the configured SMTP connection. Verify server time and scheduled-job logs.
- In the hosting scheduler, run the workflow SMS command every minute from the actual project directory. Example command, with hosting-specific paths:

```sh
cd /ACTUAL/PROJECT/PATH && /ACTUAL/PHP81_34_OR_LATER/PATH spark sms:process
```

The command processes up to 20 queued records per run. Keep job output in a protected log and monitor the queue as usage grows. The local Windows task does not become a production cron job when files are uploaded.

**2026-09-08 repair deployed:** an explicit POST `cron` route now reaches `Cron::index`, using the existing exact `cron` CSRF exception and retaining `PODC_CRON_KEY` authentication. Settings → Cron Job now shows the supported commands instead of the old GET link. A strong scheduler key was added to production `.env`, and its matching curl configuration was installed at `writable/cron-http.conf` with permissions `0600`. Existing integration credentials were preserved.

The recurring jobs are **not installed**. The owner has FileZilla access but no hosting-panel access. See [HOSTING_AND_PROVIDER_ACTIONS.md](HOSTING_AND_PROVIDER_ACTIONS.md) for the exact commands to give the hosting provider. Local route/security tests passed and deployed files were verified over FTP; production cron was not invoked. Automatic approval review rejected the live cron check because it might trigger production work, so verification continued with read-only file checks.

## 5. File scanning and conditional integrations

The upload code exposes `app_filter_secure_upload_malware_scan`, but this repository has no installed scanning engine/adapter. `UPLOAD_MALWARE_SCAN_FAIL_CLOSED=true` rejects uploads when a scanner is unavailable. The sample .env enables that policy; do not copy it and assume scanning is installed. Connect and test a real scanner before enabling enforced scanning. Leaving the option false permits uploads without malware inspection and does not complete the production scanning requirement. This remains a deployment integration item, not an SMS/payment setting.

Additional keys are conditional:

- `PODC_FILE_STREAM_HMAC_KEY`: required for protected Google Drive streaming in production, if that storage is used. Retain the Google Drive integration's own credentials too.
- `PODC_NOTIFICATION_PROCESSOR_KEY`: required for separately signed external HTTP notification-processor calls. Normal internal notifications call the processor directly.
- reCAPTCHA environment keys: configure only when used. `PODC_RECAPTCHA_ENABLED=false` disables validation even if keys exist; `PODC_RECAPTCHA_REQUIRED=true` with a missing secret rejects submissions. Site/secret keys, protocol, and expected hostname must agree.
- Legacy Stripe/GitHub/Bitbucket webhook credentials and a CSP reporting URL are not prerequisites for Bank Muscat or iSmartSMS. Do not populate unused integrations with sample values.

## 6. Go-live checks

After resolving the scheduler and upload-scanning items, verify on the deployed environment:

1. Admin and vendor password + SMS OTP sign-in; multi-CR selection and company isolation.
2. Guest vendor and gate-pass registration, required document uploads, and downloads.
3. Controlled payment return, bank verification, correct workflow continuation, and the correct module accounting record; check failed/cancelled handling too.
4. Vendor, Gate Pass, PTW, and Tender revision/decision SMS to the correct account's mobile.
5. SMTP delivery, accounting-only access, fee values, and scheduled tender transitions/expiry.
6. Reconciled historical payments and in-progress applications, so the cutover does not demand an unsupported second payment.

The database patch previously passed 229 isolated schema/constraint checks. That evidence does not verify production credentials, production data, hosting connectivity, or this deployment. Complete the checks above before opening the updated workflows to all users.
