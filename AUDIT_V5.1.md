# Audit V5.1 — Source Synchronization

Source of truth: `ujian-online(10).zip`.

## Inventory
- 235 files in the uploaded project.
- Runtime data found in `storage/` and `uploads/`; these must not be committed.
- Release/archive artifacts found in `releases/`; these must not be committed.
- Application source includes `admin/`, `peserta/`, `api/`, `includes/`, `migrations/`, schema files and root entry points.

## Critical finding
The uploaded `config.php` contains migration support but did not contain the public URL helpers required by `peserta/access.php`. The V5.1 baseline restores `public_base_url()` and `public_url()` while retaining migration support.

## Development priorities
1. Complete source synchronization to GitHub, excluding runtime and generated data.
2. Audit the participant verification flow end-to-end.
3. Audit exam attempts, timer expiry and submission synchronization.
4. Standardize admin UI and navigation across Dashboard, Exams, Participants and Question Management.
5. Add repeatable regression checks before each release.

## Repository policy
Never commit database credentials, runtime storage, participant uploads, backups, generated ZIP releases or local secrets.
