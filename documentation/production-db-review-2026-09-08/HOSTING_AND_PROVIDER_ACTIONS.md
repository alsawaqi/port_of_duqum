# Production integration checks and remaining provider actions

Verified against `https://poderp.bedots.site/` on 2026-09-08, running PHP 8.1.34.

## Confirmed

- All required PHP extensions are loaded: mysqli, curl, openssl, intl, mbstring, fileinfo, GD, ZIP.
- All four previously missing payment/SMS tables are present. Their 84 columns match the prepared schema patch: `pod_eservice_payments` (35), `pod_eservice_payment_events` (12), `pod_vendor_fee_requests` (18), and `pod_sms_outbox` (19). No further table creation was necessary. This comparison checked column names, not every index/type definition.
- The OTP challenge/login-security tables and HMAC key are present.
- Production's outward-facing IP was independently checked from the server: **92.205.177.137**.
- One SMS connectivity attempt to the owner's previously approved test number is recorded in `pod_sms_outbox` as a system connection test. No customer workflow or payment record was created.

## iSmartSMS: provider action required

The production server received response **20**. The supplied iSmartSMS PDF, page 7, defines this as **Client IP Address has been blocked**.

Give iSmartSMS support this request:

> Please allow our production server's outgoing IP **92.205.177.137** for our iSmartSMS HTTP GET account and approved sender. Our portal is **https://poderp.bedots.site/** and the endpoint is **https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx**. The production test returned code **20** on 8 September 2026. Please confirm when the IP restriction is resolved.

No account password should be included in that message. No repeated sends were attempted after the explicit refusal.

The SMS connection, live workflow SMS, per-module notification settings, and mandatory login OTP remain disabled on production. Enable and retest after the provider allows this IP. The portal currently has **38 active login accounts without valid Oman mobile numbers**. Each account needs its owner's real mobile before mandatory OTP can be enabled; do not fill customer accounts with the test number. The production admin account does not currently match the previously approved test mobile, so confirm/update that account's own number before an OTP login test.

## Hosting scheduler: install these two jobs

FileZilla uploads files but cannot install recurring jobs. The host must configure them under the `bedotscpanel` account. These executable paths were checked on the production server.

**Every minute — queued workflow SMS:**

```sh
cd '/home/bedotscpanel/public_html/poderp.bedots.site/' && /opt/cpanel/ea-php81/root/usr/bin/php spark sms:process >> '/home/bedotscpanel/public_html/poderp.bedots.site/writable/logs/sms-cron.log' 2>&1
```

**Every five minutes — general application jobs and scheduled tender work:**

```sh
/usr/bin/curl --config '/home/bedotscpanel/public_html/poderp.bedots.site/writable/cron-http.conf' 2>> '/home/bedotscpanel/public_html/poderp.bedots.site/writable/logs/general-cron.log'
```

The private curl configuration already contains the HTTPS URL, POST method, and authentication header. It has permissions **0600**. The matching generated `PODC_CRON_KEY` is already stored in production `.env`. Keep both private. The key is not included in this document or the visible scheduler commands.

Cron has two roles: dispatch queued workflow notifications, and perform timed application work such as tender workflow progression and expired opening-session cleanup. Login OTP delivery is immediate and does not depend on cron. The general scheduler can also perform other configured RISE tasks, so activate it as part of the production cutover with the application's normal operational configuration.

The explicit POST route and corrected Settings → Cron Job page have been deployed. Backups are under `/writable/codex-deploy-20260908-integration-setup/backup/`. Local `CronRoutingTest`, `CronEndpointHardeningTest`, `TenderWorkflowSchedulerSecurityTest`, and `SafeHttpMutationCoverageTest` passed on PHP 8.1.34. Uploaded file hashes, the controller's authentication guard, and the private configuration permissions were checked over FTP.

**The recurring jobs have not been installed or executed.** The owner cannot open the hosting panel. Automatic approval review rejected the live cron invocation check because it could run production jobs; the check was not retried, and read-only file verification was used instead.

## Bank Muscat: domain credentials and network access required

The saved Bank Muscat configuration is complete in format and still selects **UAT/test**. Its callback base is correctly `https://poderp.bedots.site`.

From the production server:

| Endpoint | Result |
|---|---|
| UAT checkout: `spayuattrns.bmtest.om` | Connection timeout, curl error 28 |
| UAT Order Status API: `spayuatapi.bmtest.om` | Connection timeout, curl error 28 |
| Live checkout: `smartpaytrns.bankmuscat.com` | Certificate-verified connection; HTTP 200 |
| Live Order Status API: `smartpayapi.bankmuscat.com` | Certificate-verified connection; HTTP 403 for an unauthenticated HEAD request |

The live results establish network reachability only, not credential acceptance. The UAT timeout does not by itself identify whether the bank, hosting firewall, or network path is responsible. Ask the bank/host to allow HTTPS from **92.205.177.137** to both UAT hosts (resolved to `134.0.202.117` during the check) if UAT testing will continue here.

For real payments, obtain Bank Muscat's live merchant/access/working-key values for **https://poderp.bedots.site/**, register the callback **https://poderp.bedots.site/eservice_payment/return_from_bank**, and confirm this server IP is allowed for the Order Status API. Do not simply switch a test key to the live endpoint. No gateway mode change or payment was made during these checks. A complete hosted payment, authenticated callback, Status API verification, and accounting-record check remain pending.

## SMTP: deferred by owner

The configured SMTP service accepted a connection on `10.157.25.12:25` but rejected authentication with **535**. No email was sent. The owner subsequently requested that SMTP be left for later; its settings were not changed and no further SMTP attempts will be made without renewed instruction.

## Diagnostic cleanup

All temporary diagnostic endpoints required an expiring random bearer token and returned only targeted non-secret results. They were removed immediately after use. The final FTP inventory confirmed no temporary diagnostic endpoint remains. No production login credentials were entered, no OTP policy was bypassed, and no customer mobile numbers were changed.
