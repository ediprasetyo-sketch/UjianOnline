Ujian Online V6.3.17 — ZIP Update
- Admin dapat upload paket ZIP langsung dari halaman Update Sistem.
- Validasi CSRF, MIME/extension, ZIP path traversal, jumlah file, dan ukuran extract.
- Backup otomatis sebelum instalasi.
- Config database, storage, uploads, schema.sql tidak ditimpa.
- Paket wajib memiliki VERSION.txt dengan versi lebih baru.
Catatan: paket ZIP update sebaiknya dibuat dari release bersih tanpa storage/backups dan upload data pengguna.
