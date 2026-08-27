<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/schema_sync.php';
require_login('admin');
check_csrf();
ensure_matrix_disc_schema();

$examId=(int)($_POST['exam_id']??0);
$type=(string)($_POST['type']??'mcq');
if(!in_array($type,['mcq','essay','matrix_disc'],true)) $type='mcq';
$text=trim((string)($_POST['question_text']??''));
if(!$examId || $text==='') exit('Data soal tidak lengkap.');
$requestedPoints=(float)($_POST['points']??1);

if($type==='essay'){
    $useKey=isset($_POST['use_answer_key'])?1:0;
    $points=$useKey?max(0,$requestedPoints):0;
    $essayKey=$useKey?trim((string)($_POST['essay_answer_key']??'')):null;
}elseif($type==='matrix_disc'){
    $useKey=0;$points=0;$essayKey=null;
}else{
    $useKey=isset($_POST['use_answer_key'])?1:0;
    $points=$useKey?max(0,$requestedPoints):0;
    $essayKey=null;
}

$letters=['A','B','C','D','E','F','G','H'];
$opts=[];
foreach($letters as $k) $opts[$k]=trim((string)($_POST[$k]??''));
if($type==='mcq'){
    $opts=array_filter($opts,fn($v)=>$v!=='');
    if(count($opts)<2) exit('Pilihan ganda minimal harus memiliki 2 pilihan.');
}else $opts=[];

$correct=$useKey&&$type==='mcq'?strtoupper(trim((string)($_POST['correct_option']??''))):null;
if($type==='mcq'&&$useKey&&!isset($opts[$correct])) exit('Kunci jawaban harus memilih opsi yang tersedia.');
$check=db()->prepare('SELECT id FROM exams WHERE id=?');$check->execute([$examId]);
if(!$check->fetch()) exit('Ujian tidak ditemukan.');

$imagePath=null;
if(isset($_FILES['question_image'])&&$_FILES['question_image']['error']!==UPLOAD_ERR_NO_FILE){
    $f=$_FILES['question_image'];
    if($f['error']!==UPLOAD_ERR_OK) exit('Upload gambar gagal.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($allowed[$mime])||$f['size']>5*1024*1024) exit('Gambar tidak valid atau terlalu besar.');
    $dir=__DIR__.'/../uploads/questions';if(!is_dir($dir)) mkdir($dir,0755,true);
    $name='q_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) exit('Gagal menyimpan gambar.');
    $imagePath='uploads/questions/'.$name;
}
$o=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM questions WHERE exam_id=?');$o->execute([$examId]);$order=(int)$o->fetchColumn();
$cols='exam_id,type,question_text,question_image,essay_answer_key,option_a,option_b,option_c,option_d,option_e,option_f,option_g,option_h,use_answer_key,correct_option,points,sort_order';
$sql='INSERT INTO questions('.$cols.') VALUES('.implode(',',array_fill(0,17,'?')).')';
$params=[$examId,$type,$text,$imagePath,$essayKey,$opts['A']??null,$opts['B']??null,$opts['C']??null,$opts['D']??null,$opts['E']??null,$opts['F']??null,$opts['G']??null,$opts['H']??null,$useKey,$correct,$points,$order];
db()->prepare($sql)->execute($params);
header('Location: questions.php?id='.$examId.'&updated=1');
exit;
