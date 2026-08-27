<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
if($_SERVER['REQUEST_METHOD']!=='POST')exit('Metode tidak diizinkan.');
check_csrf();
$id=(int)($_POST['id']??0);
if(!$id)exit('ID ujian tidak valid.');
$s=db()->prepare('SELECT id FROM exams WHERE id=? LIMIT 1');
$s->execute([$id]);
if(!$s->fetch())exit('Ujian tidak ditemukan.');

// Do not cascade-delete attempts, answers and audit history. Exams that have
// participant attempts must be retained and can be deactivated instead.
$used=db()->prepare('SELECT COUNT(*) FROM attempts WHERE exam_id=?');
$used->execute([$id]);
if((int)$used->fetchColumn()>0){
  header('Location: index.php?error='.rawurlencode('Ujian tidak dapat dihapus karena sudah memiliki riwayat peserta. Nonaktifkan ujian untuk mempertahankan hasil dan audit.'));
  exit;
}

try{
  db()->prepare('DELETE FROM exams WHERE id=?')->execute([$id]);
}catch(Throwable $e){
  exit('Ujian gagal dihapus. Pastikan relasi database konsisten.');
}
header('Location: index.php?msg='.rawurlencode('Ujian berhasil dihapus.'));exit;
