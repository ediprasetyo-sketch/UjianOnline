# Audit Full Ujian Online V6.3.98

Tanggal: 2026-08-27
Branch audit: `audit-fix-6.3.98`
Base: `main`

## Ringkasan

Audit source repository dilakukan terhadap struktur aplikasi, authentication/session, API participant, attempt/timer, scoring, upload/update flow, migration runner, security headers, dan CI gate.

### Hasil

- **Status source:** sehat secara struktur dan sudah memiliki hardening yang cukup matang.
- **Temuan yang diperbaiki:** response aplikasi belum secara eksplisit melarang caching browser/proxy. Untuk sistem ujian, ini berisiko meninggalkan halaman soal, jawaban, hasil, atau data peserta pada cache lokal/proxy.
- **Perbaikan V6.3.98:** `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` dan `Pragma: no-cache` ditambahkan di `config.php`, sehingga endpoint/API dan halaman yang memuat config.php tidak boleh disimpan oleh cache.

## Area yang diperiksa

1. **Repository & release**
   - `VERSION.txt` dan `update-manifest.json` harus sinkron.
   - Release gate sudah memeriksa file wajib, syntax PHP, hard-coded production path, dan duplikasi `session_start()`.
   - Versi dinaikkan dari 6.3.97 ke 6.3.98 untuk patch ini.

2. **Authentication & session**
   - Session menggunakan cookie-only, strict mode, HttpOnly, SameSite=Lax, dan Secure saat HTTPS.
   - Login admin melakukan `session_regenerate_id(true)` setelah autentikasi.
   - Participant juga melakukan regenerasi session setelah verifikasi email.
   - CSRF menggunakan token random dan `hash_equals`.

3. **Participant API**
   - API mewajibkan participant session.
   - API mutation mewajibkan CSRF.
   - Attempt dibatasi berdasarkan `attempt_id + user_id`.
   - Soal dan option mapping diverifikasi kembali terhadap attempt/exam.

4. **Timer & finalisasi**
   - Deadline attempt dibatasi oleh durasi ujian dan `end_at`.
   - Autosave hanya diberi grace period 2 detik untuk request yang sedang berjalan; bukan perpanjangan waktu normal.
   - Finalisasi memakai row lock sehingga submit manual dan auto-expire tidak boleh menyelesaikan attempt dua kali.

5. **Scoring**
   - MCQ, Matrix/DISC, dan Essay diproses server-side.
   - Essay dibatasi 20.000 karakter.
   - Essay dengan answer key dapat auto-grade; essay lain tetap membutuhkan penilaian sesuai flow aplikasi.

6. **Upload/update**
   - Upload gambar menggunakan validasi file/MIME dan batas ukuran.
   - Paket update ZIP memiliki validasi path, jumlah file, ukuran hasil extract, VERSION, manifest, backup, dan CSRF.

7. **Database/migration**
   - PDO prepared statements dengan emulated prepares disabled.
   - Migration memiliki checksum sehingga perubahan terhadap migration yang sudah diterapkan dapat terdeteksi.

8. **CI**
   - GitHub Actions melakukan PHP lint seluruh source, version/manifest check, required-file check, hard-coded path check, dan secret/config guard.

## Temuan dan keputusan

### FIXED — Cache sensitive exam responses

Sebelumnya `config.php` menetapkan security headers tetapi tidak menetapkan kebijakan cache eksplisit. Browser, reverse proxy, atau cache layer tertentu dapat memiliki perilaku caching yang tidak diinginkan.

**Perbaikan:**

```text
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
```

Ditempatkan terpusat di `config.php` agar berlaku konsisten pada halaman dan API yang memuat konfigurasi aplikasi.

### BACKLOG — CSP enforcement penuh

Belum diaktifkan sebagai CSP blocking karena source masih menggunakan inline CSS/JS pada sejumlah halaman. Tahap aman berikutnya adalah memindahkan inline asset ke file eksternal atau menggunakan nonce/hash, lalu mengaktifkan CSP secara bertahap.

### BACKLOG — Upload storage di luar webroot

Upload soal sudah divalidasi, tetapi arsitektur paling aman tetap menyimpan file upload di luar webroot dan menyajikannya melalui endpoint terkontrol.

### BACKLOG — Automated security regression

Disarankan menambahkan test otomatis untuk auth, CSRF, authorization antar-attempt, timer expiry, duplicate submit, upload validation, dan finalization race.

### BACKLOG — Admin mutation review

Endpoint admin perlu direview satu per satu untuk memastikan setiap mutasi memakai POST + CSRF dan setiap ID selalu terikat resource induknya.

## Validasi setelah patch

Wajib dijalankan pada branch/release:

- `php -l` untuk seluruh file PHP.
- Validasi `VERSION.txt == update-manifest.json`.
- Smoke test login admin.
- Smoke test link peserta + verifikasi email.
- Start attempt.
- Autosave MCQ/Matrix/Essay.
- Submit manual.
- Auto-expire.
- Re-open/finish sesuai flow aplikasi.
- Hasil dan scoring.
- Upload gambar soal.
- Update ZIP.
- Pastikan response ujian memiliki `Cache-Control: no-store`.

## Kesimpulan

Tidak ditemukan indikasi kerusakan arsitektur besar pada source yang dapat dibuktikan hanya dari static audit. Fondasi keamanan V6.3.97 sudah cukup baik. Patch V6.3.98 menutup satu hardening gap yang relevan untuk sistem ujian: pencegahan caching data sensitif.
