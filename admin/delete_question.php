<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
if($_SERVER['REQUEST_METHOD']!=='POST') exit('Metode tidak diizinkan.');
check_csrf();
$id=(int)($_POST['id']??0);
$stmt=db()->prepare('SELECT exam_id, question_image FROM questions WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$q=$stmt->fetch();
if(!$q) exit('Soal tidak ditemukan.');
$examId=(int)$q['exam_id'];

// Never delete a question that is already part of an attempt. Past attempts
// must remain reproducible and their answers/results must not be destroyed.
$used=db()->prepare('SELECT COUNT(*) FROM attempt_questions WHERE question_id=?');
$used->execute([$id]);
if((int)$used->fetchColumn()>0){
  header('Location: questions.php?id='.$examId.'&error='.rawurlencode('Soal tidak dapat dihapus karena sudah digunakan dalam ujian peserta. Nonaktifkan/arsipkan soal tersebut agar riwayat hasil tetap aman.'));
  exit;
}

db()->beginTransaction();
try{
  db()->prepare('DELETE FROM questions WHERE id=? AND exam_id=?')->execute([$id,$examId]);
  if(db()->inTransaction()) db()->commit();
}catch(Throwable $e){
  if(db()->inTransaction()) db()->rollBack();
  exit('Gagal menghapus soal.');
}
if(!empty($q['question_image']) && is_file(__DIR__.'/../'.$q['question_image'])) @unlink(__DIR__.'/../'.$q['question_image']);
header('Location: questions.php?id='.$examId.'&deleted=1'); exit;
