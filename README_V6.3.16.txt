UJIAN ONLINE V6.3.16 — STABILIZATION & SECURITY

Changes:
- VERSION.txt, updater, and update-manifest synchronized to 6.3.16.
- schema.sql updated for public_token, participant email verification, essay answer keys, and essay scores.
- Added migration 6.3.16 as release baseline.
- Admin updater now enforces CSRF on all POST actions.
- Update manifest accepts sha256 or legacy package_sha256, but SHA-256 is mandatory and must be 64 hex characters.
- seed.php is CLI-only to prevent web execution.

Before deployment:
1. Back up database and application.
2. Existing installations should run pending migrations in order.
3. New installations may use the synchronized schema.sql.
