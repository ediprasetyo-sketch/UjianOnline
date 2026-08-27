# Audit Project V6.3.48

## Critical findings repaired
1. `ensure_matrix_disc_schema()` was called but not defined in the active project.
2. Previous update architecture preserved `config.php`, while previous hotfixes incorrectly depended on adding helpers there.
3. Matrix/DISC schema synchronization was therefore broken after partial ZIP updates.
4. `update-manifest.json` was stale at 6.3.16 while `VERSION.txt` was 6.3.47.
5. Edit Question did not support the Matrix/DISC type.
6. Question list did not present Matrix/DISC keys consistently.

## Repairs in this package
- New update-safe helper: `includes/schema_sync.php`.
- `admin/questions.php` and `admin/save_question.php` explicitly load that helper.
- Matrix/DISC schema checks are idempotent.
- Edit Question supports Matrix/DISC.
- VERSION and manifest synchronized to 6.3.48.
- Environment-specific `config.php` remains preserved by the updater.

## Validation
- All PHP files passed `php -l` syntax validation.
- Package excludes runtime backups, staged update archives, and user-uploaded question images from the release payload.
