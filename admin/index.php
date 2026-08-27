<?php
declare(strict_types=1);
require __DIR__.'/../config.php';

/* Defensive compatibility fallback for older config.php installations. */
if (!function_exists('public_url')) {
    function public_url(string $path=''): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = '';
        foreach (['/admin/', '/peserta/'] as $needle) {
            $pos = strpos($script, $needle);
            if ($pos !== false) {
                $base = substr($script, 0, $pos);
                break;
            }
        }
        return $scheme . '://' . $host . rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}


if (!function_exists('participant_display_name')) {
    function participant_display_name(array $row): string {
        $name = trim((string)($row['full_name'] ?? ''));
        $email = trim((string)($row['email'] ?? $row['username'] ?? ''));
        if ($name === '' || ($email !== '' && strcasecmp($name, $email) === 0)) {
            return '';
        }
        return $name;
    }
}
if (!function_exists('participant_display_email')) {
    function participant_display_email(array $row): string {
        return trim((string)($row['email'] ?? $row['username'] ?? ''));
    }
}

require_once __DIR__.'/../includes/attempt_sync.php';
require_login('admin');

$pdo=db();
sync_attempt_statuses($pdo);
$totalExams=(int)$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$activeExams=(int)$pdo->query("SELECT COUNT(*) FROM exams WHERE active=1")->fetchColumn();
$totalParticipants=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='participant'")->fetchColumn();
$totalQuestions=(int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$todayAttempts=(int)$pdo->query("SELECT COUNT(*) FROM attempts WHERE DATE(started_at)=CURDATE()")->fetchColumn();
$runningAttempts=(int)$pdo->query("SELECT COUNT(*) FROM attempts WHERE status='active' AND started_at IS NOT NULL")->fetchColumn();
$completedAttempts=(int)$pdo->query("SELECT COUNT(*) FROM attempts WHERE status IN ('submitted','finished','expired')")->fetchColumn();
$scheduledExams=(int)$pdo->query("SELECT COUNT(*) FROM exams WHERE active=1 AND start_at>NOW()")->fetchColumn();
$liveExams=$pdo->query("SELECT e.*, (SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id AND a.status='active') running_count, (SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id AND a.status IN ('submitted','finished','expired')) completed_count FROM exams e WHERE e.active=1 AND e.start_at<=NOW() AND e.end_at>=NOW() ORDER BY e.end_at ASC LIMIT 6")->fetchAll();
$watchAttempts=$pdo->query("SELECT a.id,a.exam_id,a.status,a.started_at,u.full_name,u.email,e.title,e.end_at FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.status='active' ORDER BY a.started_at DESC LIMIT 8")->fetchAll();

$recentExams=$pdo->query("SELECT e.*, (SELECT COUNT(*) FROM questions q WHERE q.exam_id=e.id) question_count,
 (SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id) attempt_count
 FROM exams e ORDER BY e.created_at DESC, e.id DESC LIMIT 8")->fetchAll();
$publicBase=rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])),'/\\');
$publicScheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$publicHost=(string)($_SERVER['HTTP_HOST']??'localhost');
foreach($recentExams as &$recentExam){
  if(empty($recentExam['public_token'])){
    $recentExam['public_token']=bin2hex(random_bytes(20));
    $pdo->prepare('UPDATE exams SET public_token=? WHERE id=?')->execute([$recentExam['public_token'],$recentExam['id']]);
  }
  $recentExam['public_link']=public_url('peserta/access.php?exam='.rawurlencode((string)$recentExam['public_token']));
}
unset($recentExam);

$recentActivity=$pdo->query("SELECT a.id,a.exam_id,a.status,a.started_at,a.submitted_at,u.full_name,u.email,e.title
 FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id
 ORDER BY COALESCE(a.submitted_at,a.started_at) DESC LIMIT 8")->fetchAll();

$days=[];
$createdSeries=[];
$attemptSeries=[];
for($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $days[]=$d;
    $s=$pdo->prepare("SELECT COUNT(*) FROM exams WHERE DATE(created_at)=?");
    $s->execute([$d]); $createdSeries[]=(int)$s->fetchColumn();
    $s=$pdo->prepare("SELECT COUNT(*) FROM attempts WHERE DATE(started_at)=?");
    $s->execute([$d]); $attemptSeries[]=(int)$s->fetchColumn();
}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function status_label(array $e):string{
    if(!(int)$e['active']) return 'Nonaktif';
    $now=time(); $st=strtotime($e['start_at']); $en=strtotime($e['end_at']);
    if($now<$st) return 'Terjadwal';
    if($now>$en) return 'Selesai';
    return 'Aktif';
}
?><!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Admin — Ujian Online</title>
<link rel="stylesheet" href="assets/admin-ui.css?v=<?=urlencode(app_version())?>">
<link rel="stylesheet" href="assets/monitoring-ui.css?v=<?=urlencode(app_version())?>">
</head><body>
<div class="admin-layout">
<aside class="admin-sidebar">
  <div class="admin-brand"><span class="mark">✓</span> Ujian Online</div>
  <div class="admin-section">MENU UTAMA</div>
  <nav class="admin-nav">
    <a class="active" href="index.php"><span class="ico">⌂</span>Dashboard</a>
    <a href="#ujian-list"><span class="ico">▣</span>Ujian</a>
    <a href="participants.php"><span class="ico">♟</span>Peserta</a>
    <a href="templates.php"><span class="ico">⇩</span>Template Import Soal</a>
  </nav>
  <div class="admin-section">SISTEM</div>
  <nav class="admin-nav"><a href="update.php"><span class="ico">↻</span>Update Sistem</a></nav>
  <div class="system-box"><h4>Informasi Sistem</h4>
    <div class="system-row"><span>Versi Aplikasi</span><span class="version-chip"><?=h(app_version())?></span></div>
    <div class="system-row"><span>PHP Version</span><b><?=h(PHP_VERSION)?></b></div>
    <div class="system-row"><span>Environment</span><span class="prod-chip">Production</span></div>
  </div>
</aside>

<main class="admin-main">
<header class="admin-topbar">
  <div class="top-left"><button class="hamb menu-toggle" type="button" aria-label="Buka menu">☰</button><h1>Dashboard Admin</h1></div>
  <div class="profile"><div class="avatar"><?=h(strtoupper(substr((string)($_SESSION['user']['full_name']??'A'),0,1)))?></div><div><b><?=h($_SESSION['user']['full_name']??'Administrator')?></b><div class="ui-sub">Administrator</div></div><a class="logout-link" href="../logout.php">Keluar</a></div>
</header>

<div class="admin-content">
<?php if(!empty($_GET['msg'])): ?><div class="flash success"><?=h($_GET['msg'])?></div><?php endif; ?>
<div class="page-head"><div><h2 class="page-title">Dashboard Administrator</h2><p class="page-subtitle">Kelola ujian online, peserta, soal, dan hasil dalam satu dashboard.</p></div><div class="crumb">⌂ Beranda / Dashboard</div></div>

<section class="ui-grid stats-grid">
  <a class="ui-card stat-card stat-click" href="#ujian-list"><div class="stat-body"><div class="stat-icon">▣</div><div><div class="ui-label">TOTAL UJIAN</div><div class="ui-value"><?=$totalExams?></div><div class="ui-sub"><?=$activeExams?> ujian aktif</div></div></div><div class="stat-link">Lihat semua ujian →</div></a>
  <a class="ui-card stat-card green stat-click" href="participants.php"><div class="stat-body"><div class="stat-icon">♟</div><div><div class="ui-label">PESERTA TERDAFTAR</div><div class="ui-value"><?=$totalParticipants?></div><div class="ui-sub">Total peserta terdaftar</div></div></div><div class="stat-link">Kelola peserta →</div></a>
  <a class="ui-card stat-card orange stat-click" href="#ujian-list"><div class="stat-body"><div class="stat-icon">▤</div><div><div class="ui-label">TOTAL SOAL</div><div class="ui-value"><?=$totalQuestions?></div><div class="ui-sub">Semua soal tersedia</div></div></div><div class="stat-link">Kelola soal →</div></a>
  <a class="ui-card stat-card purple stat-click" href="#aktivitas-list"><div class="stat-body"><div class="stat-icon">◔</div><div><div class="ui-label">ATTEMPT HARI INI</div><div class="ui-value"><?=$todayAttempts?></div><div class="ui-sub">Percobaan hari ini</div></div></div><div class="stat-link">Lihat aktivitas →</div></a>
</section>

<section class="ui-grid content-grid">
  <div class="ui-card card-pad">
    <h3 class="card-title">Ringkasan Aktivitas <select id="chartRange" aria-label="Rentang grafik"><option value="7">7 Hari Terakhir</option></select></h3>
    <div class="legend"><span><i class="dot blue-dot"></i>Ujian Dibuat</span><span><i class="dot green-dot"></i>Percobaan</span></div>
    <div class="chart"><canvas id="activityChart"></canvas></div>
  </div>
  <div class="ui-card card-pad"><h3 class="card-title">Akses Cepat</h3>
    <div class="quick-grid">
      <button class="quick" type="button" data-modal="examModal"><span class="qicon blue">⊕</span>Buat Ujian Baru</button>
      <a class="quick" href="participants.php"><span class="qicon green-t">♟</span>Kelola Peserta</a>
      <a class="quick" href="#ujian-list"><span class="qicon orange-t">▤</span>Kelola Soal</a>
      <a class="quick" href="#aktivitas-list"><span class="qicon purple-t">◔</span>Lihat Hasil</a>
      <a class="quick" href="templates.php"><span class="qicon blue">⇩</span>Import Soal</a>
      <a class="quick" href="update.php"><span class="qicon danger-t">↻</span>Update Sistem</a>
    </div>
  </div>
</section>

<section id="ujian-list" class="ui-card card-pad data-section exam-list-section">
  <div class="section-heading exam-list-head">
    <div><span class="eyebrow">MANAJEMEN UJIAN</span><h3 class="card-title">Ujian Terbaru</h3><p class="ui-sub">Kelola soal, jadwal, hasil, link peserta, dan status ujian dari satu tempat.</p></div>
    <button class="ui-btn" type="button" data-modal="examModal">+ Buat Ujian</button>
  </div>
  <?php if($recentExams): ?>
  <div class="table-wrap exam-table-wrap">
    <table class="ui-table exam-table">
      <thead><tr><th>Ujian</th><th>Jadwal Ujian</th><th>Soal</th><th>Attempt</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach($recentExams as $e): ?>
      <tr class="exam-row">
        <td data-label="Ujian" class="exam-name-cell">
          <b class="exam-title"><?=h($e['title'])?></b>
          <div class="exam-meta"><span>◷ <?=h($e['duration_seconds']/60)?> menit</span><span>#<?=h($e['id'])?></span></div>
        </td>
        <td data-label="Jadwal Ujian" class="exam-schedule">
          <b><?=h(date('d M Y · H:i',strtotime($e['start_at'])))?></b>
          <span>sampai <?=h(date('d M Y · H:i',strtotime($e['end_at'])))?></span>
        </td>
        <td data-label="Soal"><span class="metric-badge"><?=h($e['question_count'])?><small>soal</small></span></td>
        <td data-label="Attempt"><span class="metric-badge"><?=h($e['attempt_count'])?><small>attempt</small></span></td>
        <td data-label="Status"><span class="status <?=strtolower(status_label($e))?>"><?=h(status_label($e))?></span></td>
        <td data-label="Aksi">
          <div class="row-actions exam-actions">
            <a class="action-btn" href="questions.php?id=<?=$e['id']?>">Soal</a>
            <a class="action-btn" href="edit_exam.php?id=<?=$e['id']?>">Edit</a>
            <a class="action-btn" href="results.php?id=<?=$e['id']?>">Hasil</a>
            <button class="action-btn primary copy-exam-link" type="button" data-link="<?=h($e['public_link'])?>" data-title="<?=h($e['title'])?>">Salin Link</button>
            <a class="action-btn" href="<?=$e['public_link']?>" target="_blank" rel="noopener">Buka</a>
            <a class="action-btn subtle-link" href="exam_link.php?id=<?=$e['id']?>">Detail Link</a>
            <form method="post" action="delete_exam.php" class="inline-form" onsubmit="return confirm('Hapus ujian ini beserta data terkait?')">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="id" value="<?=$e['id']?>">
              <button class="action-btn danger" type="submit">Hapus</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?><div class="empty"><span class="info-i">i</span><div><b>Belum ada data ujian.</b><br><span class="ui-sub">Silakan buat ujian baru untuk memulai.</span></div></div><?php endif; ?>
</section>

<section class="monitor-strip">
<div class="monitor-title"><div><h3 class="card-title">Monitoring Ujian</h3><p class="ui-sub">Pantau kondisi ujian dan peserta yang sedang mengerjakan.</p></div><div class="monitor-tools"><span class="monitor-status"><i></i><span id="monitorLastUpdate">Monitoring aktif</span></span><button type="button" class="ui-btn secondary" onclick="window.refreshMonitoring()">↻ Refresh</button></div></div>
<div class="monitor-grid">
<div class="ui-card monitor-card live"><span>Ujian Sedang Berlangsung</span><strong id="liveExamCount"><?=count($liveExams)?></strong><small><?=$scheduledExams?> ujian terjadwal berikutnya</small></div>
<div class="ui-card monitor-card"><span>Peserta Sedang Mengerjakan</span><strong id="runningAttemptCount"><?=$runningAttempts?></strong><small>Attempt yang belum selesai</small></div>
<div class="ui-card monitor-card success"><span>Total Attempt Selesai</span><strong id="completedAttemptCount"><?=$completedAttempts?></strong><small>Akumulasi seluruh ujian</small></div>
</div>
</section>
<section class="ui-card card-pad data-section live-table">
<div class="section-heading"><div><h3 class="card-title">Ujian Sedang Berlangsung</h3><p class="ui-sub">Status dihitung dari jadwal aktif saat halaman dibuka.</p></div></div>
<?php if($liveExams):?><div class="live-list" id="liveExamList"><?php foreach($liveExams as $e):?><div class="live-exam-row"><div class="live-dot"></div><div class="live-exam-main"><b><?=h($e['title'])?></b><span>Berakhir <?=h(date('d M Y H:i',strtotime($e['end_at'])))?></span></div><div class="live-count"><b><?=h($e['running_count'])?></b><span>sedang</span></div><div class="live-count"><b><?=h($e['completed_count'])?></b><span>selesai</span></div><div class="row-actions"><a class="action-btn" href="results.php?id=<?=$e['id']?>">Monitor Hasil</a><a class="action-btn primary" href="questions.php?id=<?=$e['id']?>">Soal</a></div></div><?php endforeach;?></div><?php else:?><div class="empty"><span class="info-i">i</span><div><b>Tidak ada ujian yang sedang berlangsung.</b><br><span class="ui-sub">Ujian aktif akan muncul otomatis sesuai jadwal.</span></div></div><?php endif;?>
</section>
<section class="ui-card card-pad data-section">
<div class="section-heading"><div><h3 class="card-title">Peserta Sedang Mengerjakan</h3><p class="ui-sub">Daftar attempt yang belum selesai.</p></div></div>
<?php if($watchAttempts):?><div class="table-wrap" id="watchAttemptTable"><table class="ui-table"><thead><tr><th>Peserta</th><th>Ujian</th><th>Mulai</th><th>Status</th><th>Monitoring</th></tr></thead><tbody><?php foreach($watchAttempts as $a):?><tr><td><div class="participant-cell"><b><?=h(participant_display_name($a)?:'Peserta')?></b><small><?=h(participant_display_email($a))?></small></div></td><td><?=h($a['title'])?></td><td><?=h($a['started_at'])?></td><td><span class="status aktif">Sedang Mengerjakan</span></td><td><a class="action-btn primary" href="results.php?id=<?=$a['exam_id']?>&attempt_id=<?=$a['id']?>">Lihat Attempt</a></td></tr><?php endforeach;?></tbody></table></div><?php else:?><div class="empty"><span class="info-i">i</span><div><b>Tidak ada peserta yang sedang mengerjakan.</b></div></div><?php endif;?>
</section>
<section id="aktivitas-list" class="ui-card card-pad data-section">
  <div class="section-heading"><div><h3 class="card-title">Aktivitas & Attempt Terbaru</h3><p class="ui-sub">Aktivitas peserta terbaru akan tampil otomatis.</p></div></div>
  <?php if($recentActivity): ?><div class="table-wrap"><table class="ui-table"><thead><tr><th>Peserta</th><th>Ujian</th><th>Mulai</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
  <?php foreach($recentActivity as $a): ?><?php $statusText=in_array($a['status'],['submitted','finished'],true)?'Selesai':($a['status']==='expired'?'Waktu Habis':'Sedang Mengerjakan'); $statusClass=in_array($a['status'],['submitted','finished'],true)?'selesai':($a['status']==='expired'?'expired':'aktif'); ?><tr><td><div class="participant-cell"><b><?=h(participant_display_name($a)?:'Peserta')?></b><small><?=h(participant_display_email($a))?></small></div></td><td><?=h($a['title'])?></td><td><?=h(date('d M Y H:i',strtotime($a['started_at'])))?></td><td><span class="status <?=$statusClass?>"><?=h($statusText)?></span></td><td><a class="action-btn primary" href="results.php?id=<?=$a['exam_id']?>&attempt_id=<?=$a['id']?>">Lihat Jawaban</a></td></tr><?php endforeach; ?></tbody></table></div>
  <?php else: ?><div class="empty"><span class="info-i">i</span><div><b>Belum ada aktivitas.</b><br><span class="ui-sub">Aktivitas peserta akan muncul di sini.</span></div></div><?php endif; ?>
</section>
</div>

<footer class="admin-footer"><span>© <?=date('Y')?> Ujian Online. All rights reserved.</span><span>Versi <?=h(app_version())?></span></footer>
</main></div>

<div class="modal" id="examModal" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="examModalTitle">
  <button class="modal-close" type="button" data-close-modal>×</button><h2 id="examModalTitle">Buat Ujian Baru</h2><p class="ui-sub">Setelah disimpan Anda langsung diarahkan ke halaman Kelola Soal.</p>
  <form method="post" action="create_exam.php" class="exam-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div><label>Judul ujian</label><input name="title" required placeholder="Contoh: Ujian Matematika"></div>
    <div class="form-grid"><div><label>Durasi (menit)</label><input type="number" name="duration" min="1" max="1440" value="30" required></div><div><label>Tampilan soal</label><select name="question_mode"><option value="all">Semua sekaligus</option><option value="one_by_one">Satu per satu</option></select></div></div>
    <div class="form-grid"><div><label>Mulai</label><input type="datetime-local" name="start_at" value="<?=date('Y-m-d\TH:i')?>" required></div><div><label>Selesai</label><input type="datetime-local" name="end_at" value="<?=date('Y-m-d\TH:i',strtotime('+1 day'))?>" required></div></div>
    <div class="form-grid"><div><label>Status</label><select name="active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div><div><label>Randomisasi</label><select name="randomize_questions"><option value="0">Soal normal</option><option value="1">Acak soal</option></select></div></div>
    <input type="hidden" name="randomize_options" value="0"><div class="modal-actions"><button type="button" class="ui-btn secondary" data-close-modal>Batal</button><button type="submit" class="ui-btn">Buat & Kelola Soal</button></div>
  </form>
</div></div>

<script>
const chartData={labels:<?=json_encode(array_map(fn($d)=>date('D',strtotime($d)),$days))?>,created:<?=json_encode($createdSeries)?>,attempts:<?=json_encode($attemptSeries)?>};
const c=document.getElementById('activityChart'),ctx=c.getContext('2d');
function drawChart(){const r=c.getBoundingClientRect(),w=Math.max(300,r.width),h=240,dpr=window.devicePixelRatio||1;c.width=w*dpr;c.height=h*dpr;ctx.setTransform(dpr,0,0,dpr,0,0);ctx.clearRect(0,0,w,h);const max=Math.max(1,...chartData.created,...chartData.attempts),pad={l:34,r:16,t:18,b:30};for(let i=0;i<=4;i++){let y=pad.t+(h-pad.t-pad.b)*i/4;ctx.strokeStyle='#e9eef5';ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(w-pad.r,y);ctx.stroke();ctx.fillStyle='#8491a3';ctx.font='11px Arial';ctx.fillText(String(Math.round(max*(4-i)/4)),4,y+4)}function line(arr,color){ctx.strokeStyle=color;ctx.lineWidth=2;ctx.beginPath();arr.forEach((v,i)=>{let x=pad.l+(w-pad.l-pad.r)*(arr.length===1?0:i/(arr.length-1));let y=pad.t+(h-pad.t-pad.b)*(1-v/max);i?ctx.lineTo(x,y):ctx.moveTo(x,y);ctx.fillStyle=color;ctx.beginPath();ctx.arc(x,y,3,0,Math.PI*2);ctx.fill();if(i===0){ctx.beginPath();ctx.moveTo(x,y)} });ctx.stroke();}line(chartData.created,'#2f66c9');line(chartData.attempts,'#1d9b62');chartData.labels.forEach((l,i)=>{let x=pad.l+(w-pad.l-pad.r)*(chartData.labels.length===1?0:i/(chartData.labels.length-1));ctx.fillStyle='#718096';ctx.font='11px Arial';ctx.textAlign='center';ctx.fillText(l,x,h-8)});ctx.textAlign='start'}drawChart();addEventListener('resize',drawChart);
document.querySelectorAll('[data-modal]').forEach(b=>b.addEventListener('click',()=>{let m=document.getElementById(b.dataset.modal);m.classList.add('show');m.setAttribute('aria-hidden','false')}));
document.querySelectorAll('[data-close-modal]').forEach(b=>b.addEventListener('click',()=>{let m=b.closest('.modal');m.classList.remove('show');m.setAttribute('aria-hidden','true')}));
document.querySelector('.menu-toggle').addEventListener('click',()=>document.body.classList.toggle('sidebar-open'));
</script>
<script>
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
let previousCompleted=<?=json_encode($completedAttempts)?>;
let notified=false;
function fmtDate(v){if(!v)return'—';const d=new Date(v.replace(' ','T'));return isNaN(d)?v:d.toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'});}
function countdown(end){const s=Math.max(0,Math.floor((new Date(end.replace(' ','T'))-Date.now())/1000));const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sec=s%60;return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;}
function renderLive(list){const el=document.getElementById('liveExamList');if(!el)return;if(!list.length){el.innerHTML='<div class="empty"><span class="info-i">i</span><div><b>Tidak ada ujian yang sedang berlangsung.</b><br><span class="ui-sub">Ujian aktif akan muncul otomatis sesuai jadwal.</span></div></div>';return;}el.innerHTML=list.map(e=>`<div class="live-exam-row" data-end="${esc(e.end_at)}"><div class="live-dot"></div><div class="live-exam-main"><b>${esc(e.title)}</b><span>Berakhir ${fmtDate(e.end_at)} · <b class="countdown">${countdown(e.end_at)}</b></span></div><div class="live-count"><b>${esc(e.running_count)}</b><span>sedang</span></div><div class="live-count"><b>${esc(e.completed_count)}</b><span>selesai</span></div><div class="row-actions"><a class="action-btn" href="results.php?id=${encodeURIComponent(e.id)}">Monitor Hasil</a><a class="action-btn primary" href="questions.php?id=${encodeURIComponent(e.id)}">Soal</a></div></div>`).join('');}
function participantName(a){const n=String(a.full_name||'').trim(),e=String(a.email||a.username||'').trim();return !n||(e&&n.toLowerCase()===e.toLowerCase())?'Peserta':n;}function participantEmail(a){return String(a.email||a.username||'').trim();}
function renderAttempts(rows){const box=document.getElementById('watchAttemptTable');if(!box)return;const body=rows.length?rows.map(a=>`<tr><td><div class="participant-cell"><b>${esc(participantName(a))}</b><small>${esc(participantEmail(a))}</small></div></td><td>${esc(a.title)}</td><td>${fmtDate(a.started_at)}</td><td><span class="status aktif">Sedang Mengerjakan</span></td><td><a class="action-btn primary" href="results.php?id=${encodeURIComponent(a.exam_id)}&attempt_id=${encodeURIComponent(a.id)}">Lihat Attempt</a></td></tr>`).join(''):'<tr><td colspan="5" class="table-empty">Tidak ada peserta yang sedang mengerjakan.</td></tr>';box.querySelector('tbody').innerHTML=body;}
function toast(msg){let x=document.getElementById('monitorToast');if(!x){x=document.createElement('div');x.id='monitorToast';x.className='monitor-toast';document.body.appendChild(x)}x.textContent=msg;x.classList.add('show');setTimeout(()=>x.classList.remove('show'),4500);}
async function refreshMonitoring(){try{const r=await fetch('monitoring.php',{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});if(!r.ok)throw new Error();const d=await r.json();if(!d.ok)throw new Error();document.getElementById('liveExamCount').textContent=d.live.length;document.getElementById('runningAttemptCount').textContent=d.running;document.getElementById('completedAttemptCount').textContent=d.completed;renderLive(d.live);renderAttempts(d.attempts);if(notified&&d.completed>previousCompleted)toast(`${d.completed-previousCompleted} attempt baru saja difinalisasi.`);previousCompleted=d.completed;notified=true;document.getElementById('monitorLastUpdate').textContent='Diperbarui '+new Date().toLocaleTimeString('id-ID');}catch(e){document.getElementById('monitorLastUpdate').textContent='Monitoring gagal, mencoba lagi...';}}
function tickCountdown(){document.querySelectorAll('[data-end]').forEach(e=>{const x=e.querySelector('.countdown');if(x)x.textContent=countdown(e.dataset.end);});}
window.refreshMonitoring=refreshMonitoring;refreshMonitoring();setInterval(refreshMonitoring,15000);setInterval(tickCountdown,1000);
</script>
<script>
document.querySelectorAll('.copy-exam-link').forEach(function(button){
  button.addEventListener('click',async function(){
    const link=this.dataset.link, original=this.textContent;
    try{
      if(navigator.clipboard && window.isSecureContext){
        await navigator.clipboard.writeText(link);
      }else{
        const ta=document.createElement('textarea');
        ta.value=link; ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
      }
      this.textContent='Tersalin ✓';
      this.classList.add('copied');
      setTimeout(()=>{this.textContent=original;this.classList.remove('copied')},1800);
    }catch(e){ window.prompt('Salin link ujian:',link); }
  });
});
</script>

</body></html>