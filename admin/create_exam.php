<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

$title=trim((string)($_POST['title']??''));
$duration=(int)($_POST['duration']??0);
$start=str_replace('T',' ',(string)($_POST['start_at']??''));
$end=str_replace('T',' ',(string)($_POST['end_at']??''));
$mode=$_POST['question_mode']??'all';
$active=(int)($_POST['active']??1);
$rq=(int)($_POST['randomize_questions']??0);
$ro=(int)($_POST['randomize_options']??0);
if($title==='') exit('Judul ujian wajib diisi.');
if($duration<1||$duration>1440) exit('Durasi harus antara 1 sampai 1440 menit.');
if(!in_array($mode,['all','one_by_one'],true))$mode='all';
if(!in_array($active,[0,1],true))$active=1;
if(!in_array($rq,[0,1],true))$rq=0;
if(!in_array($ro,[0,1],true))$ro=0;
$st=strtotime($start);$et=strtotime($end);
if($st===false||$et===false)exit('Tanggal/jam ujian tidak valid.');
if($et<=$st)exit('Tanggal selesai harus lebih besar dari tanggal mulai.');
$token=bin2hex(random_bytes(20));
$stmt=db()->prepare('INSERT INTO exams(title,duration_seconds,start_at,end_at,question_mode,active,access_code,randomize_questions,randomize_options,public_token) VALUES(?,?,?,?,?,?,?,?,?,?)');
$stmt->execute([$title,$duration*60,$start,$end,$mode,$active,null,$rq,$ro,$token]);
$id=(int)db()->lastInsertId();
// Setelah membuat ujian, admin harus mengisi soal terlebih dahulu.
header('Location: questions.php?id='.$id.'&created=1');exit;
