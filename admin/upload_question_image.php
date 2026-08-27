<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

if (empty($_FILES['image']['tmp_name'])) exit('Gambar tidak ditemukan.');

$f=$_FILES['image'];
if ($f['error'] !== UPLOAD_ERR_OK) exit('Upload gambar gagal.');

$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if (!isset($allowed[$mime])) exit('Format gambar harus JPG, PNG, WEBP, atau GIF.');

if ($f['size'] > 5*1024*1024) exit('Ukuran gambar maksimal 5 MB.');

$dir=__DIR__.'/../uploads/questions';
if (!is_dir($dir)) mkdir($dir,0755,true);

$name='q_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
if (!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) exit('Gagal menyimpan gambar.');

$examId=(int)($_POST['exam_id']??0);
$questionId=(int)($_POST['question_id']??0);
if ($questionId>0) {
  db()->prepare("UPDATE questions SET question_image=? WHERE id=? AND exam_id=?")
    ->execute(['uploads/questions/'.$name,$questionId,$examId]);
  header('Location: questions.php?id='.$examId.'&image=1'); exit;
}
header('Location: questions.php?id='.$examId.'&image_path='.rawurlencode('uploads/questions/'.$name)); exit;
