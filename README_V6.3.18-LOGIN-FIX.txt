Ujian Online V6.3.18 — Login & Admin Fix
Perbaikan:
- Instalasi baru tanpa akun admin sekarang menampilkan setup akun administrator pertama di login.php.
- Tidak ada lagi ketergantungan pada seed.php untuk bisa masuk pertama kali.
- Validasi username dan password minimum 8 karakter.
- admin/update.php diperbaiki agar menggunakan config.php dan require_login('admin').
- Menghapus referensi config/auth.php dan config/csrf.php yang tidak ada.
- PHP syntax lint dijalankan setelah patch.
