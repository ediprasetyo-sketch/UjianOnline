<?php
require __DIR__.'/../config.php'; require_login('admin'); check_csrf();
$exam=(int)$_POST['exam_id'];
if(empty($_FILES['file']['tmp_name'])) exit('File tidak ditemukan.');
$file=$_FILES['file']['tmp_name']; $name=$_FILES['file']['name'];
$pdo=db(); $count=0;

function add_question($pdo,$exam,$r){
  $text=trim($r['question']??$r['pertanyaan']??'');
  if($text==='') return false;
  $type=strtolower(trim($r['type']??$r['tipe']??'mcq'));
  if(in_array($type,['pg','pilihan ganda','pilihanganda'],true)) $type='mcq';
  if($type!=='essay') $type='mcq';
  $correct=strtoupper(trim($r['correct_option']??$r['kunci']??$r['jawaban']??'')); $correct=in_array($correct,['A','B','C','D'],true)?$correct:null;
  $stmt=$pdo->prepare("INSERT INTO questions(exam_id,type,question_text,option_a,option_b,option_c,option_d,correct_option,points,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)");
  static $order=0;
  $stmt->execute([$exam,$type,$text,$r['A']??$r['a']??null,$r['B']??$r['b']??null,$r['C']??$r['c']??null,$r['D']??$r['d']??null,$correct,(float)($r['points']??$r['poin']??1),++$order]);
  return true;
}
$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
if(in_array($ext,['csv','txt'],true)){
  $h=fopen($file,'r'); $headers=fgetcsv($h);
  while(($row=fgetcsv($h))!==false){$r=[];foreach($headers as $i=>$k)$r[trim($k)]=$row[$i]??'';if(add_question($pdo,$exam,$r))$count++;}
  fclose($h);
}elseif(in_array($ext,['xlsx','xls'],true)){
  $autoload=__DIR__.'/../vendor/autoload.php';
  if(!file_exists($autoload)) exit('Untuk import XLSX/XLS, jalankan composer require phpoffice/phpspreadsheet terlebih dahulu.');
  require $autoload;
  $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);$sheet=$reader->load($file)->getActiveSheet();
  $rows=$sheet->toArray(null,true,true,true);$headers=array_map(fn($v)=>trim((string)$v),array_shift($rows));
  foreach($rows as $row){$r=[];foreach($headers as $col=>$k)$r[$k]=$row[$col]??'';if(add_question($pdo,$exam,$r))$count++;}
}else exit('Format harus CSV, XLS, atau XLSX.');
header('Location: questions.php?id='.$exam.'&imported='.$count);exit;
