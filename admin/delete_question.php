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
// Remove dependent attempt/question records first, then answers, to keep old attempts consistent.
db()->beginTransaction();
try{
  db()->prepare('DELETE FROM answers WHERE question_id=?')->execute([$id]);
  db()->prepare('DELETE FROM attempt_questions WHERE question_id=?')->execute([$id]);
  db()->prepare('DELETE FROM questions WHERE id=?')->execute([$id]);
  db()->commit();
}catch(Throwable $e){db()->rollBack();exit('Gagal menghapus soal.');}
if(!empty($q['question_image']) && is_file(__DIR__.'/../'.$q['question_image'])) @unlink(__DIR__.'/../'.$q['question_image']);
header('Location: questions.php?id='.$examId.'&deleted=1'); exit;
