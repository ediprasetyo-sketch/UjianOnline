<?php
declare(strict_types=1);
require __DIR__.'/../config.php'; require_login('admin'); check_csrf();

$pdo=db();
$id=(int)($_POST['id']??0);
$full=trim((string)($_POST['full_name']??''));
$username=trim((string)($_POST['username']??''));
$code=trim((string)($_POST['participant_code']??''));
$password=(string)($_POST['password']??'');

if($full==='' || $username===''){header('Location: participants.php?error='.urlencode('Nama dan username wajib diisi.'));exit;}
if(!preg_match('/^[A-Za-z0-9_.-]{3,100}$/',$username)){header('Location: participants.php?error='.urlencode('Username minimal 3 karakter dan hanya boleh huruf, angka, titik, garis bawah, atau strip.'));exit;}
try{
 if($id>0){
   if($password!==''){
     if(strlen($password)<6) throw new RuntimeException('Password minimal 6 karakter.');
     $s=$pdo->prepare("UPDATE users SET full_name=?,username=?,participant_code=?,password_hash=? WHERE id=? AND role='participant'");
     $s->execute([$full,$username,$code?:null,password_hash($password,PASSWORD_DEFAULT),$id]);
   }else{
     $s=$pdo->prepare("UPDATE users SET full_name=?,username=?,participant_code=? WHERE id=? AND role='participant'");
     $s->execute([$full,$username,$code?:null,$id]);
   }
   header('Location: participants.php?msg='.urlencode('Data peserta berhasil diperbarui.'));exit;
 }
 if(strlen($password)<6) throw new RuntimeException('Password minimal 6 karakter.');
 $s=$pdo->prepare("INSERT INTO users(username,password_hash,role,participant_code,full_name) VALUES(?,?, 'participant',?,?)");
 $s->execute([$username,password_hash($password,PASSWORD_DEFAULT),$code?:null,$full]);
 header('Location: participants.php?msg='.urlencode('Peserta berhasil ditambahkan.'));exit;
}catch(Throwable $e){
 $msg=$e instanceof RuntimeException?$e->getMessage():'Username sudah digunakan atau data tidak dapat disimpan.';
 header('Location: participants.php?error='.urlencode($msg));exit;
}