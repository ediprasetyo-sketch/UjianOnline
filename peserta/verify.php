<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/participant_session.php';

$token=trim((string)($_GET['token']??$_POST['token']??''));
$examToken=trim((string)($_GET['exam']??$_POST['exam']??$_SESSION['pending_verify_exam']??''));
if($token===''){
    http_response_code(400);
    exit('Token verifikasi tidak lengkap.');
}

$pdo=db();
$s=$pdo->prepare('SELECT * FROM users WHERE email_verify_token=? AND email_verify_expires_at>=NOW() LIMIT 1');
$s->execute([$token]);
$u=$s->fetch();
if(!$u){
    http_response_code(400);
    exit('Link verifikasi tidak valid atau sudah kedaluwarsa. Silakan minta link baru.');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $fullName=trim((string)($_POST['full_name']??''));
    if(mb_strlen($fullName)<2 || mb_strlen($fullName)>120){
        $error='Masukkan nama peserta minimal 2 karakter dan maksimal 120 karakter.';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users
                SET full_name=?, email_verified_at=COALESCE(email_verified_at,NOW()),
                    email_verify_token=NULL, email_verify_expires_at=NULL
                WHERE id=?')->execute([$fullName,(int)$u['id']]);
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['participant']=[
                'id'=>(int)$u['id'],
                'username'=>$u['username'],
                'email'=>$u['email'],
                'full_name'=>$fullName,
                'role'=>'participant'
            ];
            if($examToken!=='')$_SESSION['public_exam_token']=$examToken;
            unset($_SESSION['pending_verify_email'],$_SESSION['pending_verify_exam']);
            header('Location: index.php?exam='.rawurlencode($examToken));
            exit;
        } catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $error='Nama peserta belum dapat disimpan. Silakan coba lagi.';
        }
    }
}

$prefill=(string)($u['full_name']??'');
if($prefill==='' || strtolower($prefill)===strtolower((string)$u['email']))$prefill='';
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Data Peserta - REVOPRINTSHOP</title>
<style>
body{margin:0;background:#f4f7fb;font-family:Inter,Arial;color:#101828}.wrap{max-width:620px;margin:60px auto;padding:20px}.card{background:#fff;border:1px solid #e4e7ec;border-radius:18px;padding:30px;box-shadow:0 8px 28px #10182812}h1{margin:0 0 8px;font-size:28px}p{color:#667085;line-height:1.6}.email{display:inline-block;background:#f2f4f7;color:#344054;padding:8px 11px;border-radius:8px;font-weight:700}label{font-weight:800;display:block;margin:22px 0 7px}input{width:100%;box-sizing:border-box;padding:13px;border:1px solid #d0d5dd;border-radius:10px;font-size:16px}button{margin-top:18px;background:#175cd3;color:#fff;border:0;border-radius:10px;padding:13px 18px;font-weight:800;cursor:pointer;width:100%}.err{background:#fef3f2;color:#b42318;padding:12px;border-radius:10px;margin-top:16px}

/* V6.3.56 Mobile Participant */

@media(max-width:700px){
  body{font-size:16px}
  .wrap{margin:0 auto;padding:14px;max-width:100%}
  .card{padding:20px 16px;border-radius:16px}
  h1{font-size:26px;line-height:1.2}
  input,textarea,select,button{font-size:16px!important}
}
@media(max-width:480px){
  .wrap{padding:10px}.card{padding:18px 14px;border-radius:14px}
  h1{font-size:23px}.link-help{font-size:13px}
}


/* V6.3.58 responsive participant baseline */
img{max-width:100%;height:auto}
@media (max-width:760px){
  body{font-size:16px!important;overflow-x:hidden}
  .wrap{width:100%;max-width:none;padding:14px!important}
  .card{padding:18px 14px!important;margin:12px 0!important;border-radius:14px!important}
  input,select,textarea,button{font-size:16px!important;max-width:100%}
  input,select,textarea{min-height:44px}
  button,.btn{min-height:44px;touch-action:manipulation}
  table{max-width:100%}
}
@media (max-width:480px){
  .wrap{padding:10px!important}
  .card{padding:16px 12px!important}
}

</style></head><body><main class="wrap"><section class="card">
<h1>Email berhasil diverifikasi</h1>
<p>Email peserta:</p><div class="email"><?=htmlspecialchars((string)$u['email'],ENT_QUOTES,'UTF-8')?></div>
<p>Masukkan nama peserta sebelum melanjutkan ke ujian. Nama ini akan digunakan pada daftar attempt dan hasil ujian.</p>
<?php if(!empty($error)):?><div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<form method="post">
<input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8')?>">
<input type="hidden" name="token" value="<?=htmlspecialchars($token,ENT_QUOTES,'UTF-8')?>">
<input type="hidden" name="exam" value="<?=htmlspecialchars($examToken,ENT_QUOTES,'UTF-8')?>">
<label for="full_name">Nama Peserta</label>
<input id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name" required autofocus placeholder="Contoh: Edi Prasetyo" value="<?=htmlspecialchars($prefill,ENT_QUOTES,'UTF-8')?>">
<button type="submit">Simpan & Lanjutkan Ujian</button>
</form>
</section></main></body></html>