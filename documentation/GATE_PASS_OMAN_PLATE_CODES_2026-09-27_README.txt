PORT OF DUQM - OMAN VEHICLE PLATE CODE UPDATE
27 September 2026

WHAT CHANGED
The customer Gate Pass vehicle form and the Security vehicle form share their
plate-code list. Both now include all 97 codes published in the ROP English and
Arabic vehicle registration renewal dropdowns checked on 27 September 2026.
Added 78 missing Latin options and 86 missing Arabic options.
Examples: AA, AB, BB, HH, KK, SS, TT, WW and YY.
Existing options were retained for older records and compatibility. Therefore the
full application dropdown has 119 Latin and 116 Arabic options; not all legacy
options are claimed to be in the current ROP renewal list.

Codes display with the same letter spacing as the ROP lists. Compact keys are
stored using the existing format, for example SS 00123. Printed/spaced forms are
accepted and reopen correctly. Leading zeroes in the numeric part are retained.
Saved codes from the other interface language remain selected when editing.
International plate handling remains available through its existing checkbox.

RUNTIME FILE TO UPLOAD (relative to the application root)
  app/Helpers/general_helper.php

INSTALLATION
1. If the FOUR_MODULE_STABILITY_FIX_2026-09-27_R2.zip update is still pending,
   install that update first following its README and SQL instructions.
2. Back up app/Helpers/general_helper.php on the destination server.
3. Extract this plate-code ZIP in the same application root, preserving paths,
   or upload the included app/Helpers/general_helper.php over the same file.
   For the Linux installation this is normally:
     /var/www/html/app/Helpers/general_helper.php
4. Clear/reload PHP OPcache using the server's usual deployment process if it
   does not automatically detect updated files. Refresh the application.
5. Open Add Vehicle in the Gate Pass portal and in Security. Check SS / YY in
   English and their Arabic equivalents with the Arabic interface. On a test
   request, select a code, enter the digits, attach the Mulkiyah and save/reopen.

No SQL or migration is required for this plate-code update. No database rows,
existing plates, settings, credentials or scheduled jobs are changed.
Do not reinstall the older R2 helper over this new file afterward.
This is an additional plate-code patch, not a replacement for the full R2 update.
Nothing was uploaded to FileZilla or production during this change.

SOURCE VERIFICATION
Official ROP public vehicle registration renewal forms:
https://www.rop.gov.om/OnlineServices/eTraffic/english/RenewVehicleRegistration.aspx
https://www.rop.gov.om/OnlineServices/eTraffic/arabic/RenewVehicleRegistration.aspx
The dropdown values were read directly without submitting any vehicle details.
The English/Arabic sets match by their letter equivalents. The accompanying CSV
lists the 97 published codes in both scripts. The application does not contact
ROP at runtime and does not verify plate ownership or whether a particular
number has been issued. Future ROP code additions may require another list update.

VALIDATION
- New regression: 2,427 checks covering both independent ROP list snapshots,
  both actual form views, all published code save/edit round trips, legacy
  options, leading zeroes, malformed codes and the international plate path.
- Eight related PHP scripts passed on PHP 8.3.33 (plate display, international
  plates, workflow helpers, parent/role boundaries, Security history, QR/PDF
  and calendar display, payment clearance, tender dates).
- The new regression also passed on PHP 8.2.12. Syntax checks passed.
- No database save or production/provider call was performed for this update;
  round-trip verification exercises the shared server-side payload/parsing code
  used by both save controllers and the rendered add/edit forms.
