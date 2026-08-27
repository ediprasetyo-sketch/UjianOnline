<?php
declare(strict_types=1);
require __DIR__.'/../config.php'; require_login('admin'); if($_SERVER['REQUEST_METHOD']!=='POST')exit('Metode tidak diizinkan.'); check_csrf();$id=(int)($_POST['id']??0);if(!$id)exit('ID ujian tidak valid.');
$s=db()->prepare('SELECT id FROM exams WHERE id=? LIMIT 1');$s->execute([$id]);if(!$s->fetch())exit('Ujian tidak ditemukan.');
try{db()->prepare('DELETE FROM exams WHERE id=?')->execute([$id]);}catch(Throwable $e){exit('Ujian gagal dihapus. Pastikan relasi database menggunakan ON DELETE CASCADE.');}
header('Location: index.php?msg='.rawurlencode('Ujian berhasil dihapus.'));exit;
