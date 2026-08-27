<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__.'/config.php';
$pdo = db();

$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users(username,password_hash,role,full_name)
VALUES('admin',?,'admin','Administrator')
ON DUPLICATE KEY UPDATE username=username");
$stmt->execute([$hash]);

$hash = password_hash('peserta123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users(username,password_hash,role,participant_code,full_name)
VALUES('peserta',?,'participant','P001','Peserta Demo')
ON DUPLICATE KEY UPDATE username=username");
$stmt->execute([$hash]);

echo "Seed selesai. Admin: admin/admin123 | Peserta: peserta/peserta123\n";
