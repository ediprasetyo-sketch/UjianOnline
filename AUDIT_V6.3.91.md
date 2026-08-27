# Audit Sistem Ujian Online V6.3.91

Tanggal audit: 2026-08-27

## Status umum

Fitur inti ujian, penyimpanan jawaban, penilaian Essay, gambar soal, hasil attempt, dan autentikasi admin/participant sudah memiliki alur yang jelas. Essay V1.6 menambahkan finalisasi berbasis audit log sehingga tidak memerlukan perubahan struktur tabel produksi.

## Perbaikan pada rilis ini

1. Penilaian Essay dapat difinalisasi setelah seluruh soal yang aktif (`use_answer_key=1` dan `points>0`) selesai dinilai.
2. Setelah finalisasi, form nilai dikunci di UI dan endpoint `save_essay_grade.php` juga menolak perubahan di server.
3. Admin dapat membuka kembali penilaian dengan event `essay_reopened` tanpa menghapus histori event.
4. Finalisasi memperbarui total score attempt dan mencatat metadata pada `audit_logs`.
5. Tombol dan ringkasan status Final / Buka Kembali ditambahkan pada halaman penilaian Essay.
6. Endpoint `phpinfo.php` dan `test.php` publik dihapus karena tidak diperlukan untuk runtime produksi.

## Audit keamanan

### Baik

- Endpoint admin menggunakan `require_login('admin')`.
- Form POST sensitif menggunakan CSRF check.
- Nilai Essay dibatasi 0 sampai poin maksimum di server.
- Upload gambar memeriksa MIME aktual, ekstensi yang ditentukan server, ukuran maksimal 5 MB, dan nama file acak.
- Query utama menggunakan prepared statements.
- Endpoint penyimpanan jawaban peserta memverifikasi bahwa attempt milik participant yang sedang login dan question berasal dari exam attempt tersebut.

### Perlu tindakan manual segera

1. `config.php` pada riwayat repository mengandung kredensial database produksi. Kredensial tersebut harus **dirotasi** dan konfigurasi produksi dipindahkan ke `config.local.php` atau environment variable. Jangan commit kredensial baru ke Git.
2. Pastikan direktori `uploads/` tidak mengizinkan eksekusi script server-side. File upload hanya boleh disajikan sebagai file statis.
3. Setelah rotasi credential, lakukan smoke test koneksi database, login admin, login participant, upload gambar, submit ujian, dan penilaian Essay.

## Audit fungsional

### Admin

- Dashboard / daftar ujian: tersedia.
- CRUD ujian dan soal: tersedia.
- Import peserta/soal: tersedia.
- Upload gambar soal: tersedia dan divalidasi.
- Hasil attempt: tersedia.
- Penilaian Essay manual: tersedia.
- Finalisasi Essay: tersedia pada V1.6.
- Reset attempt: tersedia; reset menghapus audit log attempt sehingga histori attempt memang dianggap dihapus bersama reset.

### Participant

- Verifikasi email dan akses ujian: tersedia.
- Timer/deadline dan auto-finalization: tersedia.
- Penyimpanan jawaban: tersedia.
- Essay disimpan sebagai `essay_answer`.
- Submit manual dan expired: tersedia.

## Temuan teknis lanjutan

1. `admin/results.php` masih memiliki form penilaian Essay lama selain halaman `essay_grading.php`. Endpoint sudah aman karena finalisasi dicek di server, tetapi UI menjadi dua jalur penilaian. Disarankan pada tahap audit berikutnya menjadikan `essay_grading.php` sebagai satu-satunya UI penilaian.
2. `admin/results.php` menggunakan stylesheet tambahan dengan cache-buster versi lama (`results-v6376.css`). Perlu diseragamkan pada audit UI berikutnya.
3. `question_image_url()` menggunakan path relatif untuk gambar lokal. Ini bekerja untuk path `uploads/questions/...`; perlu smoke test untuk URL reverse-proxy/subdirectory agar tidak ada gambar rusak.
4. `save_question.php` memperbolehkan poin Essay aktif bernilai 0, yang kemudian membuat soal tidak masuk antrean penilaian. UI sebaiknya memaksa poin > 0 ketika kunci Essay diaktifkan.
5. Audit log finalisasi sudah digunakan, tetapi belum ada halaman histori perubahan nilai/finalisasi untuk admin. Ini dapat menjadi peningkatan audit trail berikutnya.

## Prioritas perbaikan berikutnya

### P0 — keamanan

- Rotasi password database yang pernah tersimpan di repository.
- Pastikan `config.local.php`/environment dipakai di server.
- Pastikan upload directory tidak mengeksekusi PHP/CGI.

### P1 — konsistensi penilaian

- Satukan penilaian Essay ke `essay_grading.php`.
- Tampilkan status Final pada detail hasil.
- Tambahkan histori finalisasi/reopen dan perubahan nilai.

### P2 — UI & maintenance

- Satukan cache-buster stylesheet.
- Audit semua halaman untuk duplicate CSS/JS dan endpoint lama.
- Tambahkan smoke-test checklist untuk setiap release.

## Kesimpulan

V6.3.91 layak dilanjutkan untuk pengujian produksi terbatas. Fitur Essay V1.6 sudah memiliki penguncian server-side setelah finalisasi. Sebelum deployment produksi penuh, tindakan P0 terkait kredensial database dan keamanan direktori upload harus diselesaikan.
