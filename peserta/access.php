<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/participant_session.php';

$token=trim((string)($_GET['exam']??$_POST['exam']??''));
if($token==='' && !empty($_SESSION['public_exam_token'])){
    $token=(string)$_SESSION['public_exam_token'];
}
if($_SERVER['REQUEST_METHOD']==='GET' && $token!=='' && ($_SESSION['public_exam_token']??'')!==$token){
    unset($_SESSION['participant'],$_SESSION['public_exam_token'],$_SESSION['pending_verify_email'],$_SESSION['pending_verify_exam']);
}

if($token===''){
    http_response_code(400);
    $title='Link Ujian Tidak Ditemukan';
    $message='Link ujian belum lengkap atau sesi ujian sudah berakhir. Silakan buka kembali link ujian yang diberikan oleh administrator.';
    require __DIR__.'/link_error.php';
    exit;
}

$stmt=db()->prepare('SELECT * FROM exams WHERE public_token=? AND active=1 LIMIT 1');
$stmt->execute([$token]);
$exam=$stmt->fetch();
if(!$exam){
    http_response_code(404);
    $title='Link Ujian Tidak Valid';
    $message='Link ujian tidak valid atau ujian sudah tidak aktif. Silakan minta link baru kepada administrator.';
    require __DIR__.'/link_error.php';
    exit;
}

function verification_url(string $verifyToken,string $examToken): string {
    return public_url('peserta/verify.php?token='.rawurlencode($verifyToken).'&exam='.rawurlencode($examToken));
}

function send_verification(string $email,string $verifyUrl): bool {
    $subject='Verifikasi email - REVOPRINTSHOP';
    $body="Halo,\n\nKlik link berikut untuk memverifikasi email dan masuk ke ujian:\n\n{$verifyUrl}\n\nLink berlaku 30 menit.\n\nREVOPRINTSHOP";
    $host=preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??'localhost'));
    $headers="From: REVOPRINTSHOP <no-reply@{$host}>\r\n";
    return @mail($email,$subject,$body,$headers);
}

$email=strtolower(trim((string)($_POST['email']??'')));
$successMessage='';
$error='';
$fallbackUrl='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();

    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $error='Masukkan alamat email yang valid.';
    } else {
        $pdo=db();
        $u=$pdo->prepare('SELECT * FROM users WHERE email=? OR username=? LIMIT 1');
        $u->execute([$email,$email]);
        $user=$u->fetch();

        if($user && ($user['role']??'participant')!=='participant'){
            $error='Email ini terdaftar sebagai akun admin.';
        } else {
            try {
                if(!$user){
                    $username=$email;
                    $hash=password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT);
                    $ins=$pdo->prepare('INSERT INTO users(username,email,password_hash,full_name,role,email_verified_at) VALUES(?,?,?,?,?,NULL)');
                    $ins->execute([$username,$email,$hash,$email,'participant']);
                    $uid=(int)$pdo->lastInsertId();
                } else {
                    $uid=(int)$user['id'];
                }

                $verifyToken=bin2hex(random_bytes(32));
                $upd=$pdo->prepare('UPDATE users SET email=?,email_verify_token=?,email_verify_expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE) WHERE id=?');
                $upd->execute([$email,$verifyToken,$uid]);

                $verifyUrl=verification_url($verifyToken,$token);
                $sent=send_verification($email,$verifyUrl);

                $_SESSION['pending_verify_email']=$email;
                $_SESSION['pending_verify_exam']=$token;

                if($sent){
                    $successMessage='Link verifikasi berhasil dikirim ke '.$email.'. Silakan cek Inbox atau folder Spam.';
                } else {
                    // Do not claim success. Keep the generated one-time URL available so
                    // public-link testing can continue even when SMTP/mail() is unavailable.
                    $error='Email verifikasi belum dapat dikirim dari server ini.';
                    $fallbackUrl=$verifyUrl;
                }
             } catch(Throwable $e){
                error_log('Verification process failed: '.$e->getMessage());
                $error='Proses verifikasi belum dapat diproses. Silakan coba lagi.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verifikasi Email - REVOPRINTSHOP</title>
<style>
:root{--blue:#175cd3;--ink:#101828;--muted:#667085;--line:#e4e7ec;--ok:#027a48;--warn:#b54708}
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;font-family:Inter,Arial;color:var(--ink)}
.wrap{max-width:680px;margin:56px auto;padding:20px}.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:32px;box-shadow:0 10px 30px #10182812}
h1{margin:0 0 10px;font-size:30px}p{color:var(--muted);line-height:1.6}label{font-weight:800;display:block;margin:22px 0 7px}
input{width:100%;padding:13px;border:1px solid #d0d5dd;border-radius:10px;font-size:16px}
button,.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;background:var(--blue);color:#fff;border:0;border-radius:10px;padding:12px 18px;font-weight:800;cursor:pointer;text-decoration:none}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.secondary{background:#fff;color:var(--blue);border:1px solid #b2ccff}
.alert{padding:14px 15px;border-radius:12px;margin:18px 0}.err{background:#fff4ed;color:var(--warn);border:1px solid #fed7aa}.ok{background:#ecfdf3;color:var(--ok);border:1px solid #abefc6}
.fallback{background:#f8fafc;border:1px solid var(--line);padding:16px;border-radius:14px;margin-top:16px}.url{word-break:break-all;background:#fff;border:1px solid var(--line);padding:10px;border-radius:8px;color:#344054;font-size:13px}
.help{font-size:14px}
@media(max-width:700px){.wrap{margin:0 auto;padding:14px}.card{padding:22px 16px;border-radius:16px;margin-top:18px}h1{font-size:25px}input,button,.btn{font-size:16px!important;min-height:46px}.actions>*{width:100%}}
</style></head>
<body><main class="wrap"><section class="card">
<h1>Test Psikotest Revo Print Shop</h1>
<p><b><?=htmlspecialchars((string)$exam['title'],ENT_QUOTES,'UTF-8')?></b></p>

<?php if($successMessage!==''): ?>
  <div class="alert ok">✓ <?=htmlspecialchars($successMessage,ENT_QUOTES,'UTF-8')?></div>
  <p class="help">Setelah email diverifikasi, peserta dapat langsung melanjutkan ke ujian.</p>
<?php elseif($fallbackUrl!==''): ?>
  <div class="alert err">⚠ <?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div>
  <div class="fallback">
    <b>Link verifikasi tersedia untuk melanjutkan testing.</b>
    <p class="help">Gunakan link ini jika pengiriman email/SMTP belum dikonfigurasi.</p>
    <div id="verifyUrl" class="url"><?=htmlspecialchars($fallbackUrl,ENT_QUOTES,'UTF-8')?></div>
    <div class="actions">
      <a class="btn" href="<?=htmlspecialchars($fallbackUrl,ENT_QUOTES,'UTF-8')?>">Buka Link Verifikasi</a>
      <button type="button" class="btn secondary" onclick="copyVerifyLink()">Salin Link</button>
    </div>
  </div>
<?php else: ?>
  <?php if($error!==''): ?><div class="alert err">⚠ <?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
  <p>Masukkan email peserta. Identitas peserta diverifikasi khusus untuk ujian ini.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8')?>">
    <input type="hidden" name="exam" value="<?=htmlspecialchars($token,ENT_QUOTES,'UTF-8')?>">
    <label for="email">Email peserta</label>
    <input id="email" type="email" name="email" required autocomplete="email" placeholder="nama@contoh.com" value="<?=htmlspecialchars($email,ENT_QUOTES,'UTF-8')?>">
    <button type="submit">Kirim Verifikasi</button>
  </form>
<?php endif; ?>
</section></main>
<script>
function copyVerifyLink(){
  const text=document.getElementById('verifyUrl')?.textContent||'';
  if(!text)return;
  navigator.clipboard?.writeText(text).then(()=>alert('Link verifikasi berhasil disalin.')).catch(()=>{
    const ta=document.createElement('textarea');ta.value=text;document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove();alert('Link verifikasi berhasil disalin.');
  });
}
</script>
</body></html>
