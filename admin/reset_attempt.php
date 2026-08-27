<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

$attemptId=(int)($_POST['attempt_id']??0);
if(!$attemptId) exit('Attempt tidak valid.');

$stmt=db()->prepare("SELECT id,exam_id,user_id FROM attempts WHERE id=? LIMIT 1");
$stmt->execute([$attemptId]);
$a=$stmt->fetch();
if(!$a) exit('Attempt tidak ditemukan.');

db()->beginTransaction();
try{
  db()->prepare("DELETE FROM answers WHERE attempt_id=?")->execute([$attemptId]);
  db()->prepare("DELETE FROM attempt_questions WHERE attempt_id=?")->execute([$attemptId]);
  db()->prepare("DELETE FROM audit_logs WHERE attempt_id=?")->execute([$attemptId]);
  db()->prepare("DELETE FROM attempts WHERE id=?")->execute([$attemptId]);
  db()->commit();
}catch(Throwable $e){
  db()->rollBack();
  exit('Gagal mereset attempt.');
}
header('Location: results.php?id='.(int)$a['exam_id']);
exit;
