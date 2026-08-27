# Audit Security V6.3.92

Tanggal: 2026-08-27

## Perubahan diterapkan

- Credential database dipindahkan keluar dari `config.php` tracked source. Runtime sekarang membaca `config.local.php` atau `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` dari environment.
- Session diperkeras: cookie-only, strict mode, HttpOnly, SameSite=Lax, dan Secure saat HTTPS.
- Header keamanan dasar ditambahkan: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, dan HSTS saat HTTPS.
- `X-Forwarded-Host` / `X-Forwarded-Proto` tidak lagi dipercaya otomatis untuk link publik. Gunakan `PUBLIC_BASE_URL` pada deployment publik.
- Endpoint finalisasi Essay sekarang wajib POST, CSRF, dan action hanya `finalize` atau `reopen`.
- Seed script berisi default credential demo dihapus.

## Kontrol yang dipertahankan

- Prepared statements PDO dan emulated prepares disabled.
- Password menggunakan `password_hash` / `password_verify`.
- CSRF menggunakan random token dan `hash_equals`.
- Upload gambar membatasi MIME JPG/PNG/WEBP/GIF, ukuran 5 MB, dan nama file acak.
- Endpoint admin menggunakan role check.
- Finalisasi Essay dicatat di `audit_logs` dan endpoint nilai menolak perubahan setelah final.

## Risiko yang masih perlu ditindaklanjuti

1. Credential database lama harus dirotasi karena pernah tersimpan di source repository.
2. Rate limiting login berbasis account/IP belum diterapkan.
3. CSP ketat belum diterapkan karena aplikasi masih menggunakan inline CSS/JS.
4. Upload image idealnya dipisahkan dari lokasi yang dapat mengeksekusi PHP.
5. Backup/restore perlu diuji berkala.

## Deployment

1. Buat `config.local.php` di server dari `config.local.example.php` dan isi kredensial database yang benar.
2. Set `public_base_url` ke domain HTTPS resmi atau gunakan `PUBLIC_BASE_URL` environment variable.
3. Lindungi file konfigurasi dengan permission server yang ketat.
4. Setelah konfigurasi lokal tersedia, lakukan `git pull origin main`.
5. Pastikan `git status` bersih.
6. Uji login admin, login/verifikasi peserta, upload gambar, penilaian Essay, finalisasi/reopen Essay, hasil ujian, import, submit, dan expired attempt.
