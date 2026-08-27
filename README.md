# V6.3.6.2

Hotfix: peserta melalui public link tidak lagi diminta kode ujian. `api/start.php` mengabaikan `access_code` dan assignment untuk mode link publik, tetapi tetap memverifikasi `public_token` yang disimpan di session.
