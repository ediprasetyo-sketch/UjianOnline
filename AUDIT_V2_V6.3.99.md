# Audit V2 — Ujian Online V6.3.99

Tanggal: 2026-08-27
Branch: `audit-fix-6.3.98`

## Scope

Audit lanjutan difokuskan pada authorization antar-resource, integritas attempt/answer/result, timer server-side, mutation admin yang destruktif, randomisasi soal/pilihan, session/CSRF, upload, dan konsistensi data historis.

## Temuan

### HIGH — Penghapusan soal dapat merusak riwayat attempt

`admin/delete_question.php` sebelumnya menghapus `answers` dan `attempt_questions` berdasarkan `question_id`, lalu menghapus soal. Akibatnya hasil/jejak attempt peserta yang sudah selesai dapat kehilangan data.

**Status: FIXED V6.3.99.** Penghapusan ditolak jika soal sudah pernah masuk `attempt_questions`. Admin diarahkan untuk mempertahankan/menonaktifkan soal.

### HIGH — Penghapusan ujian dapat merusak riwayat peserta

`admin/delete_exam.php` sebelumnya mencoba menghapus ujian secara langsung dan bergantung pada cascade database. Bila cascade aktif, attempt, jawaban, dan data audit berpotensi ikut hilang.

**Status: FIXED V6.3.99.** Ujian yang sudah memiliki attempt tidak boleh dihapus; admin harus menonaktifkannya.

### MEDIUM — API paket soal masih dapat dipanggil setelah deadline

`api/exam.php` sebelumnya hanya memeriksa `status='active'`. Attempt yang sudah melewati deadline tetapi belum terkena request autosave/submit masih dapat meminta paket soal aktif.

**Status: FIXED V6.3.99.** Deadline dihitung dari server dan attempt otomatis di-expire sebelum paket soal dikembalikan.

### MEDIUM — Risiko perubahan soal setelah attempt

Model scoring saat ini mengambil `correct_option`, `points`, dan kunci Matrix dari tabel `questions` saat finalisasi. Karena itu perubahan soal/kunci setelah peserta mulai mengerjakan dapat mempengaruhi scoring attempt lama.

**Status: IDENTIFIED / NEXT HARDENING.** Rekomendasi implementasi berikutnya adalah snapshot metadata scoring ke `attempt_questions` saat attempt dibuat, lalu scoring selalu menggunakan snapshot tersebut.

### MEDIUM — Race saat membuat attempt

Flow `start.php` melakukan SELECT existing attempt lalu INSERT attempt baru. Tanpa unique constraint atau lock database yang sesuai, dua request paralel secara teoritis dapat membuat dua attempt untuk peserta/ujian yang sama.

**Status: IDENTIFIED / NEXT HARDENING.** Rekomendasi: unique constraint `(exam_id,user_id)` bila aturan produk memang satu attempt per ujian, atau transactional named lock bila aturan bisnis berubah di masa depan.

### LOW — CSP belum enforcement

Inline CSS/JS masih digunakan sehingga CSP blocking penuh belum aman diterapkan tanpa refactor asset.

**Status: BACKLOG.**

### LOW — Upload masih berada di bawah webroot

Validasi MIME/ukuran sudah ada, tetapi penyimpanan di luar webroot dengan endpoint terkontrol tetap lebih aman.

**Status: BACKLOG.**

## Yang sudah diverifikasi

- Participant API mengikat `attempt_id` ke `user_id`.
- Save answer mengikat question ke `attempt_id` dan exam.
- CSRF mutation participant dan admin sudah diwajibkan.
- Finalisasi attempt memakai row lock dan terminal status.
- Deadline dihitung server-side dan dibatasi oleh durasi serta `exam.end_at`.
- Randomisasi pilihan disimpan sebagai `option_map` per attempt.
- Prepared statements menggunakan PDO dan emulated prepares dimatikan.
- Sensitive responses sekarang no-store/no-cache sejak V6.3.98.

## Prioritas V3

1. Snapshot scoring/question metadata ke attempt.
2. Atomic one-attempt-per-exam enforcement.
3. Security regression test otomatis untuk IDOR, CSRF, timer, duplicate submit/start, dan historical-result integrity.
4. CSP enforcement.
5. Upload storage di luar webroot.

## Kesimpulan

V6.3.99 menutup dua risiko integritas data tingkat tinggi pada mutation admin dan satu celah timing pada API soal. Dua isu berikutnya yang paling penting adalah **snapshot scoring** dan **atomic attempt creation**, karena keduanya menyangkut keabsahan nilai dan konsistensi attempt ketika aplikasi digunakan bersamaan oleh banyak peserta.
