<?php
declare(strict_types=1);
session_start();
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/participant_session.php';

$token=trim((string)($_GET['exam']??''));
if($token!==''){
  $stmt=db()->prepare('SELECT * FROM exams WHERE public_token=? AND active=1 LIMIT 1');
  $stmt->execute([$token]);$publicExam=$stmt->fetch();
  if(!$publicExam)exit('Link ujian tidak valid atau ujian sudah tidak aktif.');
  if(participant_session()===null||($_SESSION['public_exam_token']??'')!==$token){header('Location: access.php?exam='.rawurlencode($token));exit;}
  $exams=[$publicExam];
}else{
  if(participant_session()===null){header('Location: '.app_url('login.php'));exit;}
  $exams=db()->query("SELECT * FROM exams WHERE active=1 ORDER BY start_at")->fetchAll();
}
$appTitle='REVOPRINTSHOP';
$participantName=(string)($_SESSION['participant']['full_name']??$_SESSION['participant']['email']??'');
$participantEmail=(string)($_SESSION['participant']['email']??'');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f1f3d">
<title><?=htmlspecialchars($appTitle)?></title>
<style>
:root{--blue:#175cd3;--blue-dark:#0f1f3d;--ink:#17202a;--muted:#667085;--line:#e4e7ec;--bg:#f5f7fb;--green:#027a48;--danger:#b42318}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--ink);font-size:16px;overflow-x:hidden}
button,input,select,textarea{font:inherit}
button{border:0;cursor:pointer;min-height:44px;padding:11px 16px;background:var(--blue);color:#fff;border-radius:10px;font-weight:800;touch-action:manipulation}
button:disabled{opacity:.55;cursor:not-allowed}
.hidden{display:none!important}
.top{position:sticky;top:0;z-index:30;background:rgba(15,31,61,.97);color:#fff;border-bottom:1px solid rgba(255,255,255,.08);backdrop-filter:blur(10px)}
.top-inner{max-width:1100px;margin:auto;min-height:64px;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.brand{font-weight:900;font-size:20px;letter-spacing:-.02em;white-space:nowrap}
.userbox{display:flex;align-items:center;gap:10px;min-width:0}.usertext{min-width:0;text-align:right}.username{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px}.useremail{font-size:12px;color:#b7c5dc;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px}.logout{color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.2);padding:8px 10px;border-radius:9px;font-weight:700;font-size:13px}
.wrap{width:min(1100px,100%);margin:auto;padding:28px 24px 42px}
.page-title{margin:0;font-size:30px;letter-spacing:-.03em}.page-subtitle{color:var(--muted);margin:7px 0 20px}
.card{border:1px solid var(--line);background:#fff;padding:22px;border-radius:16px;margin:14px 0;box-shadow:0 5px 22px rgba(16,24,40,.05)}
.exam-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.exam-card{margin:0;display:flex;flex-direction:column;gap:10px}.exam-card h2{margin:0;font-size:20px}.exam-meta{color:var(--muted);font-size:14px;line-height:1.6}.exam-card button{align-self:flex-start;margin-top:4px}
.exam-shell{padding-bottom:120px}
.exam-toolbar{position:sticky;top:64px;z-index:20;margin:0 0 14px;display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;background:rgba(245,247,251,.94);backdrop-filter:blur(10px);padding:10px 0}
.timer{background:var(--blue-dark);color:#fff;border-radius:14px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:14px;box-shadow:0 6px 18px rgba(15,31,61,.16)}
.timer-label{font-size:12px;color:#b7c5dc;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.timer-value{font-size:24px;font-weight:900;letter-spacing:.02em}.timer.danger{background:var(--danger)}
.exam-info{background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px 14px;min-width:180px}.exam-info-label{font-size:11px;color:var(--muted);font-weight:800;text-transform:uppercase}.exam-info-value{font-weight:900;margin-top:3px}
.exam-card-main{padding:24px}.examhead{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;border-bottom:1px solid var(--line);padding-bottom:16px;margin-bottom:18px}.eyebrow{font-size:11px;font-weight:900;color:var(--blue);letter-spacing:.1em}.examhead h1{margin:5px 0 0;font-size:25px;line-height:1.2}.progress{font-weight:900;background:#eef4ff;color:var(--blue);padding:9px 12px;border-radius:999px;white-space:nowrap}
.q{border:1px solid var(--line);padding:20px;border-radius:14px;background:#fff}.qtop{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.qtext{font-size:18px;line-height:1.6;margin-bottom:14px;min-width:0}.qnum{font-weight:900;margin-right:5px}.points{white-space:nowrap;background:#ecfdf3;color:var(--green);padding:7px 10px;border-radius:9px;font-weight:900;font-size:13px}.qimg{display:block;width:auto;max-width:100%;max-height:420px;margin:14px auto;border-radius:12px;border:1px solid var(--line);object-fit:contain;background:#fff}.imgerr{color:var(--danger);background:#fef3f2;padding:10px;border-radius:9px}.opt{display:flex;align-items:flex-start;gap:11px;margin:9px 0;padding:12px 13px;border:1px solid #e7eaf0;border-radius:11px;cursor:pointer;line-height:1.5;transition:.15s}.opt:hover{border-color:#b7cdf7;background:#f8fbff}.opt input{width:20px;height:20px;flex:0 0 auto;margin:1px 0 0;accent-color:var(--blue)}.opt b{min-width:18px}.answerlabel{display:block;font-weight:900;margin:12px 0 7px}.answerbox{width:100%;min-height:190px;padding:14px;font:17px/1.55 Inter,Arial,sans-serif;border:1px solid #98a2b3;border-radius:11px;resize:vertical}.saved{font-size:13px;color:var(--green);margin-top:7px}.smallsave{min-height:18px}.status{font-size:13px;color:var(--muted)}
.matrix-options-list{margin:12px 0 14px;display:grid;gap:8px}.matrix-option{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:10px;background:#f8fafc;line-height:1.5;font-size:15px}.matrix-letter{font-weight:900;min-width:24px;color:#344054}.matrix-wrap{margin-top:14px;border:1px solid var(--line);border-radius:13px;overflow:auto;-webkit-overflow-scrolling:touch}.matrix-grid{display:grid;grid-template-columns:minmax(150px,1.7fr) repeat(4,minmax(70px,1fr));align-items:center;min-width:500px}.matrix-grid>div,.matrix-choice{min-height:54px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:center}.matrix-col{font-weight:900;font-size:13px;background:#fafbfc}.matrix-row-label{justify-content:flex-start!important;padding:0 14px;font-size:12px;font-weight:900;color:#344054}.matrix-choice{position:relative;cursor:pointer}.matrix-choice input{position:absolute;opacity:0}.matrix-choice span{width:22px;height:22px;border:2px solid #b8c1cc;border-radius:50%;display:block}.matrix-choice input:checked+span{border:6px solid var(--blue)}.matrix-options{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid var(--line)}.matrix-options div{padding:11px 14px;font-size:13px;border-bottom:1px solid #eef0f3}.matrix-options b{color:var(--blue)}
.navrow{display:flex;align-items:center;gap:10px;margin-top:18px}.navrow .saved{flex:1}.secondary{background:#667085}.submit{background:var(--green)}
.mobile-nav{display:none}
@media(max-width:760px){
 body{font-size:16px}.top-inner{min-height:58px;padding:9px 12px}.brand{font-size:17px}.userbox{gap:6px}.usertext{display:none}.logout{font-size:12px;padding:7px 9px}.wrap{padding:14px 10px 90px}.page-title{font-size:25px}.page-subtitle{font-size:14px}.exam-list{grid-template-columns:1fr;gap:10px}.card{padding:16px 13px;border-radius:14px;margin:10px 0}.exam-card h2{font-size:18px}.exam-card button{width:100%}
 .exam-shell{padding-bottom:86px}.exam-toolbar{top:58px;grid-template-columns:1fr;gap:7px;padding:7px 0}.timer{border-radius:12px;padding:10px 12px}.timer-value{font-size:21px}.exam-info{min-width:0;padding:9px 11px;display:flex;justify-content:space-between;align-items:center}.exam-info-label{font-size:10px}.exam-info-value{margin:0;font-size:14px}
 .exam-card-main{padding:14px 11px}.examhead{gap:9px;margin-bottom:12px;padding-bottom:12px;align-items:center}.examhead h1{font-size:19px}.progress{font-size:12px;padding:7px 9px}.q{padding:14px 12px;border-radius:12px}.qtop{gap:8px}.qtext{font-size:16px;line-height:1.62}.points{font-size:11px;padding:6px 8px}.qimg{max-height:300px;margin:11px auto}.opt{padding:13px 11px;margin:7px 0;min-height:48px;align-items:center}.opt input{width:21px;height:21px}.answerbox{min-height:150px;font-size:16px}
 .navrow{display:none}.mobile-nav{position:fixed;display:grid;grid-template-columns:1fr 1.25fr 1fr;gap:7px;left:0;right:0;bottom:0;z-index:50;padding:8px max(8px,env(safe-area-inset-left)) calc(8px + env(safe-area-inset-bottom)) max(8px,env(safe-area-inset-right));background:rgba(255,255,255,.96);border-top:1px solid var(--line);box-shadow:0 -8px 25px rgba(16,24,40,.08);backdrop-filter:blur(12px)}.mobile-nav button{min-height:46px;padding:9px 7px;font-size:13px}.mobile-nav .secondary{background:#667085}.mobile-nav .submit{background:var(--green)}.mobile-nav .next{background:var(--blue)}
 .matrix-grid{min-width:470px;grid-template-columns:110px repeat(4,90px)}.matrix-grid>div,.matrix-choice{min-height:50px}.matrix-options{min-width:470px;grid-template-columns:1fr}.matrix-options div{font-size:12px}.matrix-row-label{padding:0 8px;font-size:10px}.status{font-size:12px}
}
@media(max-width:390px){.brand{font-size:16px}.examhead h1{font-size:18px}.qtext{font-size:15.5px}.mobile-nav button{font-size:12px}.timer-value{font-size:19px}}
</style>
</head>
<body>
<header class="top">
  <div class="top-inner">
    <div class="brand"><?=htmlspecialchars($appTitle)?></div>
    <div class="userbox">
      <div class="usertext"><div class="username"><?=htmlspecialchars($participantName)?></div><div class="useremail"><?=htmlspecialchars($participantEmail)?></div></div>
      <a class="logout" href="logout.php">Keluar</a>
    </div>
  </div>
</header>

<main class="wrap">
  <section id="list">
    <h1 class="page-title">Ujian Online</h1>
    <p class="page-subtitle">Pilih ujian yang tersedia. Jawaban akan tersimpan otomatis.</p>
    <div class="exam-list">
    <?php foreach($exams as $e): ?>
      <article class="card exam-card">
        <h2><?=htmlspecialchars($e['title'])?></h2>
        <div class="exam-meta">Peserta: <b><?=htmlspecialchars($participantName)?></b><br>Durasi <?=floor($e['duration_seconds']/60)?> menit · <?=htmlspecialchars($e['start_at'])?> sampai <?=htmlspecialchars($e['end_at'])?></div>
        <button onclick="startExam(<?=$e['id']?>)">Mulai / Lanjutkan</button>
      </article>
    <?php endforeach; ?>
    </div>
  </section>

  <section id="exam" class="hidden exam-shell">
    <div class="exam-toolbar">
      <div id="timerBox" class="timer"><span class="timer-label">Waktu tersisa</span><span id="timer" class="timer-value">--:--</span></div>
      <div class="exam-info"><span class="exam-info-label">Status</span><span id="saveStatus" class="status">Siap mengerjakan</span></div>
    </div>
    <div class="card exam-card-main">
      <div class="examhead"><div><div class="eyebrow">UJIAN ONLINE</div><h1 id="title"></h1></div><div id="progress" class="progress">Soal 0 / 0</div></div>
      <div id="questions"></div>
      <div class="navrow">
        <button id="prevBtn" class="secondary" onclick="goQuestion(-1)">← Sebelumnya</button>
        <span id="saveStatusDesktop" class="saved"></span>
        <button id="nextBtn" onclick="goQuestion(1)">Berikutnya →</button>
        <button id="submitBtn" class="submit" onclick="submitExam(false)">Kirim Ujian</button>
      </div>
    </div>
  </section>
</main>

<div id="mobileNav" class="mobile-nav hidden">
  <button id="mobilePrev" class="secondary" onclick="goQuestion(-1)">← Kembali</button>
  <button id="mobileNext" class="next" onclick="goQuestion(1)">Berikutnya →</button>
  <button id="mobileSubmit" class="submit" onclick="submitExam(false)">Kirim</button>
</div>

<script>
const csrf=<?=json_encode(csrf_token())?>;
let attempt=null,deadline=0,clockOffset=0,interval=null,examId=null,submitting=false,currentIndex=0,questions=[],questionMode='all';
const pending=new Map(),timers=new Map();
function setStatus(text){const a=document.getElementById('saveStatus'),b=document.getElementById('saveStatusDesktop');if(a)a.textContent=text;if(b)b.textContent=text}
async function api(url,opt={}){opt.headers={...(opt.headers||{}),'X-CSRF-Token':csrf,'Content-Type':'application/json'};const r=await fetch(url,opt);const d=await r.json().catch(()=>({error:'Respons server tidak valid'}));if(!r.ok)throw Error(d.error||'Gagal');return d}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
function imageHtml(path){if(!path)return '';let p=String(path).replace(/\\/g,'/').trim();if(!p)return '';if(!/^https?:\/\//i.test(p)){p=p.replace(/^\/+/, '');p='../'+p}return `<div><img class="qimg" src="${esc(p)}" alt="Gambar soal" loading="eager" onerror="this.style.display='none';this.parentElement.querySelector('.imgerr').style.display='block'"><div class="imgerr" style="display:none">Gambar soal tidak dapat dimuat.</div></div>`}
function syncClock(ms){if(typeof ms==='number')clockOffset=ms-Date.now()}
function nowServerMs(){return Date.now()+clockOffset}
function queueSave(qid,opt,essay,immediate=false){if(timers.has(qid))clearTimeout(timers.get(qid));const run=()=>saveNow(qid,opt,essay);if(immediate)run();else timers.set(qid,setTimeout(run,350))}
function saveNow(qid,opt,essay){const p=(async()=>{try{const d=await api('../api/save_answer.php',{method:'POST',body:JSON.stringify({attempt_id:attempt,question_id:qid,selected_option:opt,essay_answer:essay})});syncClock(d.server_now_ms);deadline=d.deadline_ms;setStatus('Jawaban tersimpan');return d}catch(e){setStatus('Gagal menyimpan');console.warn(e);throw e}})();pending.set(qid,p);p.finally(()=>{if(pending.get(qid)===p)pending.delete(qid)});return p}
async function flushSaves(){for(const t of timers.values())clearTimeout(t);timers.clear();if(pending.size)await Promise.allSettled([...pending.values()])}
function renderSingleQuestion(){const q=questions[currentIndex];if(!q)return;const raw=String(q.question_text||'').trim();const matches=[...raw.matchAll(/(?:^|\s)([A-D])\.\s*/g)];const embedded=q.type==='matrix_disc'&&matches.length>=4;const heading=`<div class="qtop"><div class="qtext"><span class="qnum">${currentIndex+1}.</span>${embedded?'':esc(q.question_text||'')}</div><span class="points">${esc(q.points??0)} poin</span></div>`;let body='';
if(q.type==='essay'){body=`${imageHtml(q.question_image)}<label class="answerlabel">Jawaban Anda</label><textarea class="answerbox" data-q="${q.id}" oninput="questionChanged(this,${q.id})">${esc(q.saved_essay_answer||'')}</textarea><div class="saved smallsave">${q.saved_essay_answer?'Tersimpan':''}</div>`}
else if(q.type==='matrix_disc'){const saved=q.saved_matrix_answer||{};const cols=['A','B','C','D'];const legacy=(()=>{if(matches.length<4)return null;const out={};for(let i=0;i<matches.length;i++){const letter=matches[i][1],start=matches[i].index+matches[i][0].length,end=i+1<matches.length?matches[i+1].index:raw.length;out[letter]=raw.slice(start,end).trim()}return out})();const optionText=k=>String(q['option_'+k.toLowerCase()]||'').trim()||(legacy&&legacy[k])||'';const opts=cols.map(k=>{const text=optionText(k);return text?`<div class="matrix-option"><span class="matrix-letter">${k}.</span><span>${esc(text)}</span></div>`:''}).join('');body=`${imageHtml(q.question_image)}<div class="matrix-options-list">${opts}</div><div class="matrix-wrap"><div class="matrix-grid"><div></div>${cols.map(k=>`<div class="matrix-col">${k}</div>`).join('')}<div class="matrix-row-label">MIRIP</div>${cols.map(k=>`<label class="matrix-choice"><input type="radio" name="matrix_mirip_${q.id}" value="${k}" ${saved.mirip===k?'checked':''} onchange="matrixChanged(${q.id})"><span></span></label>`).join('')}<div class="matrix-row-label">TIDAK MIRIP</div>${cols.map(k=>`<label class="matrix-choice"><input type="radio" name="matrix_tidak_mirip_${q.id}" value="${k}" ${saved.tidak_mirip===k?'checked':''} onchange="matrixChanged(${q.id})"><span></span></label>`).join('')}</div></div><div class="saved smallsave">${saved.mirip&&saved.tidak_mirip?'Tersimpan':'Pilih satu jawaban pada setiap baris'}</div>`}
else{const saved=q.saved_display_option||'';const letters='ABCDEFGH'.split('').filter(k=>String(q['option_'+k.toLowerCase()]||'').trim());body=`${imageHtml(q.question_image)}${letters.map(k=>`<label class="opt"><input type="radio" name="answer_${q.id}" value="${k}" ${saved===k?'checked':''} onchange="selectOption('${k}',${q.id})"><b>${k}.</b><span>${esc(q['option_'+k.toLowerCase()])}</span></label>`).join('')}<div class="saved smallsave">${saved?'Tersimpan':''}</div>`}
document.getElementById('questions').innerHTML=`<div class="q">${heading}${body}</div>`;updateNavigation()}
function renderAllQuestions(){const old=currentIndex,items=[];questions.forEach((_,i)=>{currentIndex=i;renderSingleQuestion();items.push(document.getElementById('questions').innerHTML)});currentIndex=old;document.getElementById('questions').innerHTML=items.join('');document.getElementById('progress').textContent=`Semua soal / ${questions.length}`;document.getElementById('prevBtn').classList.add('hidden');document.getElementById('nextBtn').classList.add('hidden');document.getElementById('submitBtn').classList.remove('hidden');document.getElementById('mobileNav').classList.add('hidden')}
function renderQuestion(){if(questionMode==='all')renderAllQuestions();else renderSingleQuestion()}
function updateNavigation(){const last=currentIndex===questions.length-1;document.getElementById('progress').textContent=`Soal ${currentIndex+1} / ${questions.length}`;document.getElementById('prevBtn').disabled=currentIndex===0;document.getElementById('nextBtn').classList.toggle('hidden',last);document.getElementById('submitBtn').classList.toggle('hidden',!last);const nav=document.getElementById('mobileNav');nav.classList.remove('hidden');document.getElementById('mobilePrev').disabled=currentIndex===0;document.getElementById('mobileNext').classList.toggle('hidden',last);document.getElementById('mobileSubmit').classList.toggle('hidden',!last)}
function currentQ(){return questions[currentIndex]}
function selectOption(k,qid){const q=questions.find(x=>x.id==qid);if(!q)return;q.saved_display_option=k;setStatus('Menyimpan...');queueSave(q.id,k,'',true)}
function questionChanged(el,qid){const q=questions.find(x=>x.id==qid);if(!q)return;q.saved_essay_answer=el.value;setStatus('Menyimpan...');queueSave(q.id,null,el.value,false)}
function matrixChanged(qid){const q=questions.find(x=>x.id==qid);if(!q)return;const get=n=>document.querySelector(`input[name="${n}_${qid}"]:checked`)?.value||'';const ans={mirip:get('matrix_mirip'),tidak_mirip:get('matrix_tidak_mirip')};q.saved_matrix_answer=ans;if(!ans.mirip||!ans.tidak_mirip){setStatus('Lengkapi jawaban Matrix/DISC');return}setStatus('Menyimpan...');const p=(async()=>{try{const d=await api('../api/save_answer.php',{method:'POST',body:JSON.stringify({attempt_id:attempt,question_id:qid,matrix_answer:ans})});syncClock(d.server_now_ms);deadline=d.deadline_ms;setStatus('Jawaban tersimpan');return d}catch(e){setStatus('Gagal menyimpan');throw e}})();pending.set(qid,p);p.finally(()=>{if(pending.get(qid)===p)pending.delete(qid)})}
async function goQuestion(delta){await flushSaves();const next=currentIndex+delta;if(next<0||next>=questions.length)return;currentIndex=next;renderSingleQuestion();window.scrollTo({top:0,behavior:'smooth'});setStatus('Siap melanjutkan')}
async function startExam(id){try{examId=id;setStatus('Memuat ujian...');const d=await api('../api/start.php',{method:'POST',body:JSON.stringify({exam_id:id})});attempt=d.attempt_id;deadline=d.deadline_ms;syncClock(d.server_now_ms);const e=await api('../api/exam.php?id='+encodeURIComponent(id)+'&attempt_id='+encodeURIComponent(attempt));questions=e.questions||[];questionMode=(e.question_mode==='one_by_one'?'one_by_one':'all');document.getElementById('title').textContent=e.title;if(!questions.length){alert('Belum ada soal pada ujian ini.');return}currentIndex=0;document.getElementById('list').classList.add('hidden');document.getElementById('exam').classList.remove('hidden');renderQuestion();setStatus('Siap mengerjakan');tick();clearInterval(interval);interval=setInterval(tick,250);window.scrollTo({top:0,behavior:'smooth'})}catch(e){setStatus('Gagal memuat');alert(e.message)}}
async function tick(){const left=Math.max(0,deadline-nowServerMs()),s=Math.ceil(left/1000),min=Math.floor(s/60);document.getElementById('timer').textContent=String(min).padStart(2,'0')+':'+String(s%60).padStart(2,'0');document.getElementById('timerBox').classList.toggle('danger',s<=60);if(left<=0){clearInterval(interval);await submitExam(true)}}
async function submitExam(auto){if(submitting)return;if(!auto&&!confirm('Yakin ingin mengirim ujian?'))return;submitting=true;document.getElementById('submitBtn').disabled=true;document.getElementById('mobileSubmit').disabled=true;try{await flushSaves();const d=await api('../api/submit.php',{method:'POST',body:JSON.stringify({attempt_id:attempt})});clearInterval(interval);alert(auto?'Waktu habis. Jawaban yang sudah tersimpan dikumpulkan.':'Ujian dikirim. Nilai: '+d.score);location.href='finish.php?attempt='+encodeURIComponent(attempt)+'&auto='+(auto?'1':'0')}catch(e){submitting=false;document.getElementById('submitBtn').disabled=false;document.getElementById('mobileSubmit').disabled=false;alert(e.message)}}
</script>
</body>
</html>
