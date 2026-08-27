<?php
declare(strict_types=1);
require __DIR__.'/../config.php'; require_login('admin'); check_csrf();
if(empty($_FILES['file']['tmp_name']) || ($_FILES['file']['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK){header('Location: participants.php?error='.urlencode('File CSV tidak ditemukan atau gagal diupload.'));exit;}
if(strtolower(pathinfo($_FILES['file']['name']??'',PATHINFO_EXTENSION))!=='csv'){header('Location: participants.php?error='.urlencode('File harus berformat CSV.'));exit;}
$h=fopen($_FILES['file']['tmp_name'],'r'); $head=fgetcsv($h); $pdo=db(); $count=0;
if(!$head){fclose($h);header('Location: participants.php?error='.urlencode('CSV kosong.'));exit;}
$head=array_map(fn($x)=>strtolower(trim((string)$x)),$head);
$required=['full_name','username']; foreach($required as $col){if(!in_array($col,$head,true)){fclose($h);header('Location: participants.php?error='.urlencode('Kolom '.$col.' tidak ditemukan.'));exit;}}
try{$pdo->beginTransaction();
while(($row=fgetcsv($h))!==false){$r=[];foreach($head as $i=>$k)$r[$k]=trim((string)($row[$i]??''));if(($r['username']??'')===''||($r['full_name']??'')==='')continue;
 if(!preg_match('/^[A-Za-z0-9_.-]{3,100}$/',$r['username']))continue;
 $pass=$r['password']??''; $hash=password_hash($pass!==''?$pass:'123456',PASSWORD_DEFAULT);
 $s=$pdo->prepare("INSERT INTO users(username,password_hash,role,participant_code,full_name) VALUES(?,?, 'participant',?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),participant_code=VALUES(participant_code),password_hash=IF(VALUES(password_hash) IS NULL,password_hash,VALUES(password_hash))");
 $s->execute([$r['username'],$hash,($r['participant_code']??'')?:null,$r['full_name']]);$count++;
}
$pdo->commit();fclose($h);header('Location: participants.php?msg='.urlencode("$count peserta berhasil diproses."));exit;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fclose($h);header('Location: participants.php?error='.urlencode('Import gagal. Periksa format CSV dan data peserta.'));exit;}