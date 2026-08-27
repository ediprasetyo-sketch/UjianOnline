REVOPRINTSHOP V6.3.10

Cara update:
1. Backup database dan folder C:\xampp\htdocs\ujian-online-v6.2.
2. Buka Admin > Update Sistem.
3. Upload ZIP V6.3.10 ini.
4. Klik Backup & Update Sekarang.
5. Restart Apache.
6. Logout/login admin dan peserta.

Perubahan utama:
- Jawaban yang sudah tersimpan dipertahankan saat waktu habis; auto-submit menunggu autosave yang sedang berjalan.
- Autosave essay memakai antrean dan debounce.
- Kolom jawaban essay diperbesar dan font diperjelas.
- Nilai PG dihitung otomatis; essay tetap menunggu penilaian admin.
- Edit soal dapat mengganti/menghapus gambar dan ada tombol Batal.
- Setelah membuat ujian, admin masuk ke Kelola Soal dulu. Link publik baru bisa dibuka setelah minimal 1 soal.
- Peserta masuk melalui link publik dengan email yang diverifikasi melalui link email.
- Pada localhost, jika PHP mail() belum aktif, halaman verifikasi menampilkan link verifikasi lokal untuk pengujian.
- Email peserta otomatis dicatat pada tabel users.

Catatan email:
Untuk hosting publik, aktifkan layanan pengiriman email/PHP mail atau sesuaikan fungsi send_verification() dengan SMTP provider.
