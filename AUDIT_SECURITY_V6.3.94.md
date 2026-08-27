# Audit Full V6.3.94 + Security Hardening

Tanggal: 2026-08-27

## Status

Audit dilanjutkan dari V6.3.91–V6.3.93. Versi aplikasi dinaikkan menjadi `6.3.94`.

## Perbaikan diterapkan

### 1. Administrator login rate limiting
- Login administrator sekarang dibatasi berdasarkan source IP dan akun.
- Setelah 5 kegagalan dalam jendela 15 menit, percobaan berikutnya ditahan sampai jendela berakhir.
- Pesan tetap generik sehingga tidak membocorkan apakah username ada.
- Counter akun yang berhasil login dihapus; counter IP tetap dipertahankan untuk mengurangi password spraying.
- Storage rate limit menggunakan tabel InnoDB yang dibuat idempoten saat endpoint login digunakan.

### 2. Local runtime/diagnostic files
- `.user.ini` ditambahkan ke `.gitignore` karena merupakan konfigurasi server lokal.
- `php_check.php` ditambahkan ke `.gitignore` dan tidak boleh menjadi bagian deployment publik.
- `php_check.php` yang sempat dipakai untuk verifikasi PHP menampilkan versi PHP, path konfigurasi, lokasi PHP-FPM, dan limit runtime; file diagnostik tersebut harus dihapus dari webroot setelah pengujian.

### 3. Upload gambar soal
- Endpoint utama dan legacy sudah menggunakan `is_uploaded_file()`, pemeriksaan MIME berbasis `finfo`, batas 5 MB, nama acak, permission 0644, dan pemeriksaan `question_id + exam_id` saat mengganti gambar.

### 4. Session dan request security
- Session participant memuat `config.php` sebelum `session_start()`.
- Cookie session menggunakan cookie-only, strict mode, HttpOnly, SameSite=Lax, dan Secure saat HTTPS.
- CSRF tetap memakai token random + `hash_equals`.

### 5. Data dan scoring
- Payload Essay dibatasi maksimal 20.000 karakter dan harus bertipe string.
- MCQ dan Matrix/DISC divalidasi ulang setelah option mapping.
- Attempt sudah memiliki unique key `(exam_id,user_id)`, sehingga duplicate attempt race ditutup di level database pada schema saat ini.

## Kontrol yang sudah ada

- PDO prepared statements dengan emulated prepares disabled.
- Password `password_hash` / `password_verify`.
- Role check pada endpoint admin.
- API participant session check.
- Attempt terikat ke user dan exam.
- Deadline attempt dibatasi oleh durasi dan `exam.end_at`.
- Finalisasi attempt menggunakan row lock dan status terminal.
- Finalisasi Essay wajib POST + CSRF dengan action allowlist.
- Audit log untuk event penting.
- Security headers dasar dan HSTS saat HTTPS.

## Backlog berikutnya

1. CSP ketat: migrasikan inline CSS/JS ke asset eksternal atau nonce/hash sebelum enforcement penuh.
2. Upload storage di luar webroot + endpoint image serving terkontrol.
3. Backup/restore drill berkala.
4. Security regression test otomatis untuk auth, CSRF, upload, answer validation, finalisasi, dan authorization.
5. Review endpoint admin satu per satu untuk memastikan semua operasi mutasi POST + CSRF dan semua ID terikat resource induknya.

## Deployment check

```text
cd /volume1/web/ujian-online
git pull origin main
git status
```

Setelah pull:
- pastikan `config.local.php` tetap lokal dan tidak ter-track;
- pastikan `.user.ini` tetap lokal dan tidak ter-track;
- hapus `php_check.php` dari webroot setelah verifikasi;
- lakukan smoke test login admin, verifikasi peserta, start, autosave, submit, expired, MCQ, Matrix/DISC, Essay, finalisasi/reopen, hasil, import, dan upload gambar.
