# Audit Full V6.3.93 + Security Hardening

Tanggal: 2026-08-27

## Status

Audit dilanjutkan dari baseline V6.3.91/V6.3.92. Repository saat audit berada pada `6.3.92`; hasil hardening ini menaikkan versi aplikasi menjadi `6.3.93`.

## Temuan dan perbaikan diterapkan

### 1. Session participant verification
- `peserta/verify.php` sebelumnya memanggil `session_start()` sebelum `config.php`, sehingga pengaturan cookie keamanan dari `config.php` belum tentu berlaku pada request tersebut.
- Perbaikan: seluruh request sekarang memuat `config.php` terlebih dahulu sehingga session menggunakan cookie-only, strict mode, HttpOnly, SameSite, dan Secure saat HTTPS.
- Update token verifikasi dibuat lebih ketat dengan mencocokkan `id` dan token saat UPDATE.

### 2. API penyimpanan jawaban
- Payload Essay sebelumnya tidak memiliki batas panjang aplikasi dan dapat menerima tipe data yang tidak sesuai.
- Perbaikan: jawaban Essay harus string dan dibatasi maksimal 20.000 karakter.
- Pilihan MCQ dan hasil mapping sekarang divalidasi ulang setelah option map diterapkan.
- Jawaban matriks divalidasi ulang setelah mapping dan harus tetap berada pada pilihan A-H.

### 3. Upload gambar soal
- Endpoint upload gambar lama hanya memvalidasi MIME dan ukuran.
- Perbaikan: `is_uploaded_file()` diwajibkan, ukuran harus >0 dan <=5 MB, nama file acak diperpanjang, folder gagal dibuat ditangani, file hasil diberi permission 0644, dan penggantian gambar harus cocok dengan `question_id + exam_id`.
- Upload gambar utama pada `save_question.php` dan `edit_question.php` tetap dibatasi JPG/PNG/WEBP/GIF dan 5 MB.
- Folder `uploads/` tetap di-ignore Git.

### 4. Perlindungan data dan deployment
- Credential database tetap berada di `config.local.php`/environment, bukan source tracked.
- `.gitignore` melindungi `config.local.php`, `.env*`, `uploads/`, `storage/`, backup, dan archive.
- Upload PHP-FPM untuk website telah diverifikasi di server melalui `php_check.php`: `upload_max_filesize=1024M`, `post_max_size=1024M`, `memory_limit=256M`, `max_file_uploads=50`.

## Kontrol yang sudah ada

- Prepared statements PDO dengan emulated prepares disabled.
- Password `password_hash` / `password_verify`.
- CSRF token random + `hash_equals`.
- Role check pada endpoint admin.
- API participant session check.
- Attempt terikat ke `user_id` dan `exam_id`.
- Deadline attempt dinormalisasi dari `started_at + duration` dan dibatasi `exam.end_at`.
- Finalisasi attempt menggunakan row lock dan status terminal.
- Finalisasi Essay wajib POST + CSRF dan action allowlist `finalize/reopen`.
- Audit log untuk start, submit/expired, finalisasi, dan reopen Essay.
- Header keamanan dasar dan HSTS saat HTTPS.

## Risiko tersisa / backlog

1. Rate limiting login berbasis IP/account belum diterapkan.
2. CSP ketat belum diterapkan karena halaman masih menggunakan inline CSS/JS.
3. Upload gambar masih berada di webroot; ekstensi file acak non-PHP menurunkan risiko, tetapi pemisahan storage di luar webroot + image-serving endpoint akan lebih kuat.
4. Mekanisme backup/restore perlu diuji berkala.
5. Race condition pembuatan attempt sebaiknya ditutup dengan constraint database atau lock yang lebih kuat setelah data produksi diperiksa untuk duplikasi historis.
6. Pengujian runtime tetap diperlukan setelah deployment: login admin, verifikasi peserta, start, autosave, submit, expired, MCQ, Matrix/DISC, Essay, finalisasi/reopen, hasil, import, dan upload gambar.

## Rekomendasi deployment

```text
cd /volume1/web/ujian-online
git pull origin main
git status
```

Pastikan `config.local.php` tetap berada di server dan tidak pernah di-commit. Setelah pull, buka `php_check.php` dan halaman admin untuk smoke test.
