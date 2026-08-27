<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/schema_sync.php';
require_login('admin');
check_csrf();

$examId=(int)($_POST['exam_id']??0);
$questionId=(int)($_POST['question_id']??0);
if($examId<1)exit('Ujian tidak valid.');

if(isset($_FILES['image']) && is_array($_FILES['image'])){
    $f=$_FILES['image'];
    if($f['error']!==UPLOAD_ERR_OK)exit('Upload gambar gagal.');
    if(!is_uploaded_file($f['tmp_name']))exit('File upload tidak valid.');
    if((int)$f['size']<1 || (int)$f['size']>5*1024*1024)exit('Ukuran gambar maksimal 5 MB.');

    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if(!isset($allowed[$mime]))exit('Format gambar harus JPG, PNG, WEBP, atau GIF.');

    // If this endpoint updates an existing question, require the question to
    // belong to the selected exam. This prevents cross-exam image replacement.
    $oldPath=null;
    if($questionId>0){
        $q=db()->prepare('SELECT question_image FROM questions WHERE id=? AND exam_id=? LIMIT 1');
        $q->execute([$questionId,$examId]);
        $row=$q->fetch();
        if(!$row)exit('Soal tidak ditemukan.');
        $oldPath=$row['question_image']??null;
    }

    $dir=__DIR__.'/../uploads/questions';
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir))exit('Folder upload tidak dapat dibuat.');
    $name='q_'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($f['tmp_name'],$target))exit('Gagal menyimpan gambar.');
    @chmod($target,0644);
    $imagePath='uploads/questions/'.$name;

    if($questionId>0){
        $ok=db()->prepare('UPDATE questions SET question_image=? WHERE id=? AND exam_id=?')->execute([$imagePath,$questionId,$examId]);
        if(!$ok){@unlink($target);exit('Gagal mengaitkan gambar ke soal.');}
        if($oldPath && preg_match('~^uploads/questions/q_[a-f0-9]+\.(?:jpg|png|webp|gif)$~i',(string)$oldPath) && is_file(__DIR__.'/../'.$oldPath))@unlink(__DIR__.'/../'.$oldPath);
        header('Location: questions.php?id='.$examId.'&image=1'); exit;
    }

    header('Location: questions.php?id='.$examId.'&image_path='.rawurlencode($imagePath)); exit;
}

exit('Gambar tidak ditemukan.');
