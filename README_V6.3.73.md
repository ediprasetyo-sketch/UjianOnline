# WebTestOnline Update V6.3.73

Hotfix pilihan ganda dinamis.

Perbaikan:
- Tombol Kurangi Pilihan sekarang harus mengirim jumlah pilihan aktif (`option_count`).
- Saat soal disimpan, semua option di atas jumlah aktif dibersihkan menjadi NULL.
- Jika pilihan dikurangi dari 4 ke 3, option_d dihapus.
- Jika dikurangi sampai 2, option_c sampai option_h dihapus.
- Kunci jawaban yang menunjuk opsi yang sudah dihapus ikut dibersihkan.
- Tampilan daftar, preview, ujian, dan hasil harus membaca `option_count`.

Integrasi:
1. Tambahkan kolom `option_count` jika belum ada.
2. Setelah INSERT/UPDATE soal, panggil:
   normalize_question_options($pdo, $questionId, (int)$_POST['option_count']);
3. Pastikan form menyimpan hidden input `option_count`.
