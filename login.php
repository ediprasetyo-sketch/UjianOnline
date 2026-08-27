<?php
declare(strict_types=1);
require __DIR__.'/config.php';

$adminCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if (!empty($_SESSION['user']) && $adminCount > 0) { header('Location: admin/index.php'); exit; }

$error='';
$setupMode = ($adminCount === 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();

    if ($setupMode && isset($_POST['setup_admin'])) {
        $username=trim((string)($_POST['username']??''));
        $fullName=trim((string)($_POST['full_name']??'Administrator'));
        $password=(string)($_POST['password']??'');
        $confirm=(string)($_POST['password_confirm']??'');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/',$username)) {
            $error='Username minimal 3 karakter dan hanya boleh huruf, angka, titik, garis bawah, atau strip.';
        } elseif (strlen($password) < 8) {
            $error='Password minimal 8 karakter.';
        } elseif ($password !== $confirm) {
            $error='Konfirmasi password tidak sama.';
        } else {
            try {
                $stmt=db()->prepare("INSERT INTO users(username,password_hash,role,full_name) VALUES(?,?, 'admin', ?)");
                $stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT),$fullName ?: 'Administrator']);
                session_regenerate_id(true);
                $_SESSION['user']=['id'=>(int)db()->lastInsertId(),'role'=>'admin','full_name'=>$fullName ?: $username];
                header('Location: admin/index.php'); exit;
            } catch (Throwable $e) {
                $error='Username administrator sudah digunakan atau akun tidak dapat dibuat.';
            }
        }
    } elseif (!$setupMode) {
        $loginName=trim((string)($_POST['login']??''));
        $password=(string)($_POST['password']??'');
        $s=db()->prepare("SELECT * FROM users WHERE username=? AND role='admin' LIMIT 1");
        $s->execute([$loginName]); $u=$s->fetch();
        if ($u && password_verify($password,(string)$u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user']=['id'=>(int)$u['id'],'role'=>'admin','full_name'=>$u['full_name']??$u['username']??'Administrator'];
            header('Location: admin/index.php'); exit;
        }
        $error='Username atau password administrator salah.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administrator · REVOPRINTSHOP</title>
<style>
:root{--navy:#101828;--blue:#175cd3;--blue2:#0b4abf;--bg:#f3f6fb;--card:#fff;--ink:#101828;--muted:#667085;--line:#e4e7ec;--danger:#b42318}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 15% 10%,#e9f1ff 0,transparent 32%),var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink);display:flex;align-items:center;justify-content:center;padding:28px}.shell{width:min(980px,100%);display:grid;grid-template-columns:1.05fr .95fr;background:var(--card);border:1px solid var(--line);border-radius:24px;overflow:hidden;box-shadow:0 24px 70px #10182818}.brandpanel{background:linear-gradient(145deg,#101828,#172b4d);color:#fff;padding:52px 46px;position:relative}.brandpanel:after{content:"";position:absolute;width:220px;height:220px;border:1px solid #ffffff20;border-radius:50%;right:-80px;bottom:-70px}.logo{font-size:28px;font-weight:900;letter-spacing:-.04em}.badge{display:inline-block;margin-top:24px;padding:7px 11px;border-radius:99px;background:#ffffff14;border:1px solid #ffffff24;font-size:12px;font-weight:800}.brandpanel h1{font-size:40px;line-height:1.08;margin:62px 0 14px;letter-spacing:-.04em}.brandpanel p{color:#cbd5e1;line-height:1.65;max-width:390px}.features{margin-top:28px;display:grid;gap:10px}.feature{display:flex;gap:10px;align-items:center;color:#e2e8f0;font-size:14px}.check{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:#175cd3;font-weight:900}.formpanel{padding:52px 44px;display:flex;align-items:center}.inner{width:100%;max-width:390px;margin:auto}.eyebrow{color:var(--blue);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:.08em}.inner h2{font-size:30px;margin:9px 0 8px;letter-spacing:-.03em}.sub{color:var(--muted);margin:0 0 28px;line-height:1.5}label{display:block;font-size:13px;font-weight:800;margin:17px 0 7px}input{width:100%;padding:13px 14px;border:1px solid #d0d5dd;border-radius:10px;font:inherit;outline:none}input:focus{border-color:var(--blue);box-shadow:0 0 0 4px #175cd31a}button{width:100%;padding:13px 16px;margin-top:22px;border:0;border-radius:10px;background:var(--blue);color:#fff;font-size:15px;font-weight:800;cursor:pointer}button:hover{background:var(--blue2)}.err{padding:11px 13px;border-radius:10px;background:#fef3f2;color:var(--danger);font-size:14px;margin-bottom:14px}.foot{margin-top:18px;text-align:center;color:var(--muted);font-size:12px}@media(max-width:760px){.shell{grid-template-columns:1fr}.brandpanel{padding:32px}.brandpanel h1{font-size:30px;margin-top:35px}.formpanel{padding:34px 26px}}
</style></head><body><main class="shell"><section class="brandpanel"><div class="logo">REVOPRINTSHOP</div><div class="badge">ADMINISTRATOR PORTAL</div><h1>Kelola ujian dengan lebih mudah.</h1><p>Dashboard administrator untuk membuat ujian, mengatur soal, memantau hasil, dan membagikan link peserta dari satu tempat.</p><div class="features"><div class="feature"><span class="check">✓</span> Kelola ujian dan soal</div><div class="feature"><span class="check">✓</span> Pantau hasil peserta</div><div class="feature"><span class="check">✓</span> Bagikan link ujian publik</div></div></section><section class="formpanel"><div class="inner"><div class="eyebrow">Administrator</div><h2>Selamat datang</h2><p class="sub"><?php if($setupMode): ?>Buat akun administrator pertama untuk mulai menggunakan aplikasi.<?php else: ?>Masuk menggunakan akun administrator Anda.<?php endif; ?></p><?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?><?php if($setupMode): ?>
<div class="err" style="background:#eef7ff;color:#175cd3">Belum ada akun administrator. Silakan buat akun administrator pertama.</div>
<form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="setup_admin" value="1">
<label>Nama lengkap</label><input name="full_name" placeholder="Nama administrator" required>
<label>Username administrator</label><input name="username" autocomplete="username" placeholder="Minimal 3 karakter" required>
<label>Password</label><input name="password" type="password" autocomplete="new-password" placeholder="Minimal 8 karakter" required>
<label>Konfirmasi password</label><input name="password_confirm" type="password" autocomplete="new-password" placeholder="Ulangi password" required>
<button type="submit">Buat Akun Administrator</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><label>Username administrator</label><input name="login" autocomplete="username" placeholder="Masukkan username" required><label>Password</label><input name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required><button type="submit">Masuk ke Dashboard</button></form>
<?php endif; ?><div class="foot">REVOPRINTSHOP · Administrator</div></div></section></main></body></html>