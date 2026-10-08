PORT OF DUQM - CURRENT MODIFIED AND UNTRACKED FILES
Prepared: 2026-10-04T21:19:43+04:00 (Asia/Muscat)
Git HEAD at packaging: 84df36e9aa23723ed52d18c9d663bc979566e341

WHAT IS INCLUDED
92 current project files: 43 tracked modified files and 49 untracked/new files.
Original folder paths and file contents are preserved under port_of_duqum/.
Folder totals: app=57, assets=7, tests=11, documentation=17.
FILES_BY_FOLDER.txt lists each path, status, size and SHA256 checksum.
MANIFEST.json provides the same snapshot in machine-readable form.
SHA256SUMS.txt verifies all other files inside this ZIP.

This is a snapshot of the CURRENT Git changes, not a complete application.
Unchanged files and changes already committed to Git are not included.
Git-ignored files are not included: in particular .env, local runtime files,
customer uploads in ignored folders, and previous ZIP archives.
Existing .zip.sha256 files in documentation belong to earlier packages;
use this ZIP's own checksum and SHA256SUMS.txt for this combined package.

HOW THE FOLDERS MAP TO YOUR SERVER
port_of_duqum/app/Controllers/Example.php -> <project root>/app/Controllers/Example.php
port_of_duqum/assets/...                 -> <project root>/assets/...
Keep all folder names, filenames and capitalization exactly as provided.
Do not upload the outer port_of_duqum folder as a nested project directory.

FOR THE ENGINEER
1. Back up the application files being replaced and the destination database.
2. Extract this package outside the public web directory.
   From the extracted directory on Linux, verify:
       sha256sum -c SHA256SUMS.txt
3. Review the two SQL scripts under port_of_duqum/app/Database/SQL/:
       vendor_registration_payment_first_pod.sql
       vendor_code_upgrade_pod.sql
   These are the vendor registration and vendor-code changes. If already
   installed, confirm the destination schema before deciding to run them again.
   If needed, run registration first and vendor code second against the correct
   existing pod_ database using Workbench/phpMyAdmin. No migration command is
   required. This ZIP is not a database dump or a complete schema repair kit.
4. Merge the supplied app and assets files into the EXISTING project root.
   Add new files as well as replacing modified files. SQL files are for manual
   database work; uploading a SQL file does not execute it. Keep them private.
   Preserve the server's .env, database configuration, ownership and permissions.
   Do not use a mirror/delete operation when copying this partial snapshot.
5. tests/ and documentation/ are verification/reference material. They do not
   need to be uploaded to the public website. Keep the package reports private.
   Earlier feature READMEs in documentation describe their own older ZIP layout;
   use the folder mapping in THIS README for the current combined ZIP.
6. Refresh the application cache/OPcache as appropriate for the hosting server,
   then check the affected workflows on the destination after installation.

DELETED PATHS
Git also reports 1561 tracked paths under dd/ as deleted/missing locally.
Their old contents are NOT included. See DELETED_PATHS.txt for the exact list.
This list is for history/reference only, not an instruction to delete server
files. No existing server file or database was changed while making this ZIP.

PACKAGE VALIDATION
The ZIP has been checked for CRC errors, exact path coverage and SHA256 equality
against the selected source files. Packaging does not retest the application
or confirm the state of the destination server. No upload/deployment was done.
