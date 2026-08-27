# Audit V6.3.86 Full Repair

## Temuan

1. Halaman Dashboard sudah memakai shell/UI yang lebih baru, tetapi Kelola Soal dan Hasil Ujian masih memuat stylesheet patch lama yang berbeda.
2. Kelola Soal dan Hasil Ujian memiliki cache-buster statis pada stylesheet tambahan sehingga perubahan visual mudah terlihat tidak konsisten.
3. Form simpan soal sudah mendukung essay di backend, tetapi poin essay perlu dipaksa valid agar tidak tersimpan sebagai 0 saat input tidak tersedia.
4. API peserta sudah menyimpan `essay_answer`; halaman hasil sudah membaca jawaban dan `essay_score`; admin sudah memiliki endpoint penilaian manual.

## Perbaikan V6.3.86

- Menambahkan `admin/assets/ui-repair-v6386.css` sebagai stylesheet perbaikan bersama.
- Mengarahkan patch Kelola Soal dan Hasil Ujian lama ke stylesheet baru.
- Merapikan grid daftar soal, panel Tambah Soal, detail attempt, dan form penilaian essay.
- Memperbaiki responsivitas desktop/tablet/mobile.
- Menjaga poin essay minimal 1 dan jawaban acuan tetap tersimpan.
- Menaikkan `VERSION.txt` dan `update-manifest.json` ke 6.3.86.

## Verifikasi setelah instalasi

1. Refresh paksa browser dengan Ctrl+F5.
2. Buka Kelola Soal dan Hasil Ujian, lalu pastikan UI kedua halaman berubah.
3. Buat satu soal essay, isi jawaban acuan, dan simpan.
4. Kerjakan soal sebagai peserta dan kirim.
5. Buka Hasil Ujian > Detail > isi nilai essay > Simpan Nilai.
6. Pastikan nilai attempt dan rata-rata berubah.
