# V6.3.6

Perubahan:
- Judul aplikasi peserta menjadi **Test Psikotest Revo Print Shop**.
- Peserta dapat mengakses ujian melalui link publik.
- Tidak ada kode ujian yang harus diketik peserta.
- Peserta cukup memasukkan email.
- Jika email belum ada, peserta otomatis dibuat pada tabel users dengan role participant.
- Hasil attempt tetap terikat pada user/email tersebut.
- Admin dapat membuka `admin/exam_link.php?id=ID_UJIAN` untuk mendapatkan link publik.

Catatan: migration menambah `exams.public_token` dan `users.email`. Backup database wajib dilakukan sebelum update.
