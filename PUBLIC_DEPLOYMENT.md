# Publikasi REVOPRINTSHOP

Aplikasi dapat dipublikasikan ke internet pada hosting yang mendukung PHP 8.2+ dan MySQL/MariaDB.

## Kebutuhan
- PHP 8.2 atau lebih baru
- MySQL 8 / MariaDB yang kompatibel
- PDO MySQL
- ZipArchive aktif untuk Auto Update
- HTTPS/SSL wajib untuk keamanan login dan data peserta

## Langkah ringkas
1. Buat database MySQL di hosting.
2. Import schema database dari instalasi yang sudah ada atau pindahkan database lama dengan backup.
3. Upload seluruh folder aplikasi ke `public_html` atau subfolder domain.
4. Sesuaikan `config.php` dengan host, nama database, user, dan password hosting.
5. Pastikan folder `uploads/questions` dapat ditulis PHP.
6. Aktifkan HTTPS.
7. Login admin dan uji link publik dari jaringan berbeda.
8. Bagikan URL ujian publik kepada peserta.

## Catatan keamanan
- Jangan menggunakan password database kosong di hosting.
- Jangan membuka phpMyAdmin untuk publik tanpa perlindungan tambahan.
- Gunakan HTTPS.
- Backup database sebelum update.
- Jangan menaruh file backup database di folder web publik.
