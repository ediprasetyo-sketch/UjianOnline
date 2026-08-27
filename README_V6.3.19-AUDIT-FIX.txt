Ujian Online V6.3.19 — Admin Flow Audit Fix

Audit findings fixed:
1. require_login() previously redirected to /login.php, which breaks installations in a subfolder.
2. logout and participant redirects are now base-path aware.
3. Update System visual menu contained links to pages that do not exist in this project.
4. Broken menu entries were replaced with routes that exist.
5. Login/admin pages and all PHP files were syntax checked after the patch.

Audit scope:
- Login and first-admin setup
- Session/role protection
- Admin route targets
- Admin POST CSRF handlers
- PHP include/require paths
- PHP syntax validation
