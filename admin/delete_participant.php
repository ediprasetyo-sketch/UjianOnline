<?php
declare(strict_types=1);
require __DIR__.'/../config.php'; require_login('admin'); check_csrf();
$id=(int)($_POST['id']??0);
if($id<=0){header('Location: participants.php?error='.urlencode('Peserta tidak valid.'));exit;}
$s=db()->prepare("DELETE FROM users WHERE id=? AND role='participant'");
$s->execute([$id]);
header('Location: participants.php?msg='.urlencode($s->rowCount()?'Peserta berhasil dihapus.':'Peserta tidak ditemukan.'));exit;