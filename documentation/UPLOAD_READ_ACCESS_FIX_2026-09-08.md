# Production uploaded-file access correction

8 September 2026. The reported URL was `/files/system/_file762914bcce3fbd904457bc89db635bb6-site-logo.png` on `poderp.bedots.site`.

## Cause and correction

The image existed with mode `0644`. Public profile images and static assets returned 200, but uploaded logos under `files/system` returned 403.

The root `.htaccess` used `RewriteOptions InheritDownBefore`. The uploaded production `files/.htaccess` enabled rewriting, so the inherited root rule saw the child-relative path `system/logo.png` and incorrectly treated it as the protected root framework directory. This matches Apache's documented [rewrite-rule inheritance](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html#rewriteoptions).

Changed the root-directory denial to match the full `REQUEST_URI`, anchored at the application origin root. Applied the same full-URI approach to the executable/configuration denial under `files` and `plugins`, preserving that restriction even when child directories enable rewriting. The app is deployed at the domain root; the local configured virtual hosts also use the project as their document root.

No chmod changes or public access to `writable` were introduced. Private application documents continue to use their existing authorized preview/download controllers.

## Verification

- `tests/ApacheUploadAccessTest.py` starts a separate localhost-only Apache on an available ephemeral port, with synthetic image/document/private-file fixtures and child rewrite rules. It reproduces the old logo 403 and passes 13 post-fix cases: public PNG/PDF reads return 200; root code, configuration, executable uploads, temporary files, tender files and private uploads return 403. Its Apache process stops in `finally`; existing services are unchanged.
- `UploadSecurityHardeningTest.php` and `TenderProtectedStorageSecurityTest.php` passed on PHP 8.1.34.
- `ServerExposureHardeningTest.php` still fails on the previously recorded, unrelated local development debug-toolbar setting. The updated root-URI assertion passes before that existing failure. `git diff --check` passed.
- Production: reported logo, another uploaded logo and a profile image return `200 image/png`. GET of the reported image returns all 3,273 bytes, exactly matching the stored file and its PNG signature.
- Production `.env`, framework code, `writable/uploads/` and `files/temp/` remain 403.
- Normal GET requests to sign-in, guest vendor and guest gate pass return `200 text/html`. A HEAD request to the dynamic sign-in route returns 404 because HEAD is not registered; the normal page works.

## Deployment

Only production `/.htaccess` changed, retaining all unrelated production rules. Backup: `/writable/codex-deploy-20260908-upload-reading/root.htaccess.before`.

Verified deployed SHA-256: `c1e5af8de594d8117d7f49c5b4608f01582896f91754b9a0725d6d6a68429cf3`.

This verifies the reported public-image failure and representative access controls. No production customer document was opened through an authenticated workflow during this repair.
