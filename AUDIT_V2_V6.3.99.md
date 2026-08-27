# Audit V3 — Exam Integrity Hardening V6.3.99

Tanggal: 2026-08-27
Branch: `audit-fix-6.3.98`

## Implemented

### Immutable scoring snapshot
Saat attempt dibuat, setiap `attempt_questions` menyimpan `scoring_snapshot` berisi tipe soal, poin, kunci MCQ/Matrix, kunci essay, mode answer-key, option keys, dan option map. Final scoring dan auto-grade essay menggunakan snapshot tersebut, bukan definisi soal yang dapat berubah setelah peserta mulai.

### Concurrent start protection
`api/start.php` menggunakan MySQL advisory lock dengan key per `exam_id + participant_id`. Request start paralel diproses serial dan tidak dapat membuat dua attempt baru untuk peserta/ujian yang sama selama seluruh worker memakai database yang sama.

### Historical data protection
Soal yang sudah masuk `attempt_questions` tidak dapat dihapus. Ujian yang sudah memiliki attempt tidak dapat dihapus.

### Deadline enforcement
API paket soal memeriksa deadline server-side sebelum mengembalikan soal untuk attempt aktif.

## Database change

Migration `migrations/6.3.99.sql` menambahkan `attempt_questions.scoring_snapshot LONGTEXT NULL`.

## Test plan

- Start paralel dari dua browser/session peserta yang sama.
- Autosave paralel pada question yang sama.
- Submit ganda bersamaan.
- Submit tepat pada deadline.
- Expiry bersamaan dengan autosave.
- Ubah kunci/poin soal setelah attempt dibuat dan pastikan skor tetap memakai snapshot.
- IDOR: attempt A meminta/menulis data attempt B.
- CSRF untuk seluruh mutation.
- Restore hasil setelah soal diubah/dinonaktifkan.

## Remaining hardening

1. Automated integration/security tests untuk skenario di atas.
2. Unique database constraint `(exam_id,user_id)` setelah existing data diperiksa bebas duplikasi.
3. Upload di luar webroot.
4. Refactor inline JS/CSS lalu aktifkan CSP enforcement.

## Status

**V6.3.99 sudah mencakup hardening integritas inti: snapshot scoring, serialisasi start attempt, proteksi histori, dan deadline server-side.**
