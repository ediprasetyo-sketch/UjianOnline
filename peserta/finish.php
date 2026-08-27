<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/participant_session.php';

$attemptId=(int)($_GET['attempt']??0);
$auto=(int)($_GET['auto']??0)===1;
$completion=$_SESSION['participant_completion']??null;

if($attemptId>0 && participant_session()!==null){
    $p=participant_session();
    $q=db()->prepare('SELECT a.id,a.status,a.score,e.title,u.full_name,u.email FROM attempts a JOIN exams e ON e.id=a.exam_id JOIN users u ON u.id=a.user_id WHERE a.id=? AND a.user_id=? LIMIT 1');
    $q->execute([$attemptId,(int)$p['id']]);
    $row=$q->fetch();
    if(!$row){ http_response_code(404); exit('Data ujian tidak ditemukan.'); }
    if(!in_array((string)$row['status'],['submitted','expired'],true)){
        http_response_code(409);
        exit('Ujian belum selesai. Selesaikan atau kirim ujian terlebih dahulu.');
    }
    $auto=((string)$row['status']==='expired');
    $completion=[
        'attempt_id'=>(int)$row['id'],
        'title'=>(string)$row['title'],
        'name'=>(string)($row['full_name']?:$p['full_name']??''),
        'email'=>(string)$row['email'],
        'score'=>$row['score'],
        'auto'=>$auto,
    ];
    $_SESSION['participant_completion']=$completion;
    unset($_SESSION['participant'],$_SESSION['public_exam_token'],$_SESSION['pending_verify_email'],$_SESSION['pending_verify_exam']);
    session_regenerate_id(true);
}elseif(!is_array($completion)){
    http_response_code(400); exit('Sesi penyelesaian ujian sudah berakhir.');
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ujian Selesai</title>
<style>
:root{--blue:#175cd3;--green:#027a48;--ink:#17202a;--muted:#667085;--line:#e4e7ec}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f7fb;font-family:Inter,Arial;color:var(--ink)}.card{width:min(620px,100%);background:#fff;border:1px solid var(--line);border-radius:20px;padding:38px;text-align:center;box-shadow:0 12px 34px #10182812}.check{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;margin:0 auto 18px;background:#ecfdf3;color:var(--green);font-size:34px;font-weight:900}h1{margin:0 0 10px;font-size:30px}p{color:var(--muted);line-height:1.65}.info{margin:24px 0;text-align:left;background:#f8fafc;border:1px solid var(--line);border-radius:14px;padding:16px}.row{padding:8px 0;border-bottom:1px solid var(--line)}.row:last-child{border:0}.label{display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#667085;margin-bottom:3px}.btn{display:inline-block;margin-top:8px;padding:12px 20px;border-radius:10px;background:var(--blue);color:#fff;text-decoration:none;font-weight:800}.note{font-size:13px;margin-top:18px}@media(max-width:700px){body{font-size:16px;padding:14px}.card{padding:20px 16px;border-radius:16px}h1{font-size:26px;line-height:1.2}.btn{min-height:44px}}@media(max-width:480px){.card{padding:18px 14px;border-radius:14px}h1{font-size:23px}}
</style></head><body><main class="card"><div class="check">✓</div><h1><?= $completion['auto']?'Waktu Ujian Berakhir':'Ujian Berhasil Dikirim' ?></h1><p><?= $completion['auto']?'Waktu ujian telah habis. Jawaban yang berhasil tersimpan telah dikumpulkan.':'Jawaban Anda telah berhasil disimpan dan dikirim.' ?></p><section class="info"><div class="row"><span class="label">Ujian</span><b><?=htmlspecialchars($completion['title'],ENT_QUOTES,'UTF-8')?></b></div><div class="row"><span class="label">Peserta</span><b><?=htmlspecialchars($completion['name'],ENT_QUOTES,'UTF-8')?></b><br><small><?=htmlspecialchars($completion['email'],ENT_QUOTES,'UTF-8')?></small></div><div class="row"><span class="label">Status</span><b>SELESAI</b></div></section><p class="note">Anda telah otomatis keluar dari sesi ujian. Halaman ini tidak akan membawa Anda ke Dashboard Admin.</p><a class="btn" href="../login.php">Selesai</a></main></body></html>
