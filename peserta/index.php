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
  if(participant_session()===null||($_SESSION['public_exam_token']??'')!==$token){
    header('Location: access.php?exam='.rawurlencode($token));exit;
  }
  $exams=[$publicExam];
}else{
  if(participant_session()===null){header('Location: ' . app_url('login.php'));exit;}
  $exams=db()->query("SELECT * FROM exams WHERE active=1 ORDER BY start_at")->fetchAll();
}
$appTitle='REVOPRINTSHOP';
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($appTitle)?></title>
<style>
:root{--blue:#175cd3;--ink:#17202a;--muted:#667085;--line:#e4e7ec}*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial;background:#f5f7fb;color:var(--ink);font-size:16px}.top{background:#101828;color:#fff;padding:18px}.wrap{max-width:1000px;margin:auto;padding:24px}.brand{font-weight:800;font-size:20px}.top a{color:#fff}.card{border:1px solid var(--line);background:#fff;padding:22px;border-radius:14px;margin:14px 0;box-shadow:0 4px 16px #10182808}.timer{position:sticky;top:8px;background:#17202a;color:#fff;padding:16px;border-radius:10px;font-size:26px;font-weight:800;display:flex;justify-content:space-between;z-index:5}.q{border:1px solid #ddd;padding:18px;margin:14px 0;border-radius:10px}.opt{display:block;margin:12px 0;font-size:17px}.opt input{transform:scale(1.15);margin-right:8px}.hidden{display:none}.danger{background:#b42318!important}.qimg{display:block;width:auto;max-width:100%;max-height:420px;margin:14px 0;border-radius:12px;border:1px solid #e4e7ec;object-fit:contain;background:#fff}.imgerr{color:#b42318;background:#fef3f2;padding:10px;border-radius:8px}.qtext{margin-bottom:12px;font-size:18px;line-height:1.55}.qnum{font-weight:800;margin-right:4px}textarea.answerbox{width:100%;min-height:190px;padding:14px;font:17px/1.55 Inter,Arial,sans-serif;border:1px solid #98a2b3;border-radius:10px;resize:vertical}button{padding:11px 16px;background:#175cd3;color:#fff;border:0;border-radius:8px;font-size:16px;font-weight:800;cursor:pointer}button:disabled{opacity:.6;cursor:not-allowed}.saved{font-size:13px;color:#027a48;margin-top:6px}.loading{opacity:.7}

.examhead{display:flex;justify-content:space-between;gap:15px;align-items:flex-start;border-bottom:1px solid #e4e7ec;padding-bottom:14px;margin-bottom:14px}.eyebrow{font-size:12px;font-weight:800;color:#667085;letter-spacing:.08em}.progress{font-weight:800;background:#eef4ff;color:#175cd3;padding:9px 12px;border-radius:999px}.qtop{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.points{white-space:nowrap;background:#ecfdf3;color:#027a48;padding:7px 10px;border-radius:8px;font-weight:800;font-size:14px}.answerlabel{display:block;font-weight:800;margin:10px 0 7px}.navrow{display:flex;align-items:center;gap:10px;margin-top:18px}.navrow .saved{flex:1}.secondary{background:#667085}.submit{background:#027a48}.smallsave{min-height:18px}@media(max-width:650px){.examhead,.qtop{flex-direction:column}.navrow{flex-wrap:wrap}.navrow .saved{flex-basis:100%;order:-1}.points{align-self:flex-start}}
.qtext:has(.qnum:only-child){min-height:20px}.matrix-options-list{margin:12px 0 14px;display:grid;gap:8px}.matrix-option{display:flex;gap:12px;align-items:flex-start;padding:9px 10px;border-radius:9px;background:#fff;line-height:1.5;font-size:17px}.matrix-letter{font-weight:800;min-width:24px;color:#344054}
.matrix-wrap{margin-top:14px;border:1px solid #e4e7ec;border-radius:14px;overflow:hidden}.matrix-title{padding:11px 14px;background:#f8fafc;font-size:12px;font-weight:800;color:#475467}.matrix-grid{display:grid;grid-template-columns:minmax(120px,1.7fr) repeat(4,minmax(62px,1fr));align-items:center}.matrix-grid>div,.matrix-choice{min-height:52px;border-bottom:1px solid #e4e7ec;display:flex;align-items:center;justify-content:center}.matrix-col{font-weight:800;font-size:13px;background:#fafbfc}.matrix-row-label{justify-content:flex-start!important;padding:0 14px;font-size:12px;font-weight:900;color:#344054}.matrix-choice input{position:absolute;opacity:0}.matrix-choice span{width:21px;height:21px;border:2px solid #b8c1cc;border-radius:50%;display:block}.matrix-choice input:checked+span{border:6px solid #175cd3}.matrix-options{display:grid;grid-template-columns:1fr 1fr;gap:0;border-top:1px solid #e4e7ec}.matrix-options div{padding:11px 14px;font-size:13px;border-bottom:1px solid #eef0f3}.matrix-options b{color:#175cd3}@media(max-width:600px){.matrix-grid{grid-template-columns:100px repeat(4,1fr)}.matrix-options{grid-template-columns:1fr}.matrix-row-label{padding:0 8px;font-size:10px}.matrix-choice{min-height:48px}.matrix-options div{font-size:12px}}
/* V6.3.56 Mobile-first exam layout */
@media(max-width:700px){
  .top{padding:13px 14px}.brand{font-size:17px}.wrap{padding:12px}
  .card{padding:16px 13px;border-radius:14px;margin:10px 0}
  .timer{position:sticky;top:4px;padding:12px;font-size:21px;border-radius:12px}
  .q{padding:14px 12px;margin:10px 0}.qtext{font-size:16px;line-height:1.55}
  .opt{padding:10px 8px;margin:7px 0;border:1px solid #eef0f3;border-radius:10px}
  .opt input{transform:scale(1.2);margin-right:10px}
  .qimg{max-height:300px;margin:10px 0}
  textarea.answerbox{min-height:140px;font-size:16px}
  button{min-height:44px;padding:11px 14px}
  .navrow{position:sticky;bottom:0;background:#fff;padding:10px 0 2px;margin-top:12px;z-index:4}
  .navrow button{flex:1}
  .progress{font-size:13px;padding:8px 10px}
  .matrix-wrap{border-radius:10px;overflow:auto}
  .matrix-grid{min-width:440px;grid-template-columns:120px repeat(4,80px)}
  .matrix-grid>div,.matrix-choice{min-height:50px}
  .matrix-title{font-size:12px}
  .matrix-options{min-width:440px}
}
@media(max-width:390px){
  .timer{font-size:18px}.brand{font-size:16px}
  .q{padding:12px 10px}.opt{font-size:15px}
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


/* V6.3.58 exam usability */
@media(max-width:760px){
  .top{padding:14px!important}.brand{font-size:18px!important}
  .timer{top:6px!important;font-size:21px!important;padding:12px!important}
  .q{padding:14px 12px!important;margin:12px 0!important}
  .qtext{font-size:16px!important;line-height:1.6!important}
  .opt{padding:10px 8px!important;margin:7px 0!important;border:1px solid #e7eaf0;border-radius:10px}
  .opt input{transform:scale(1.25)!important;margin-right:12px!important}
  textarea.answerbox{min-height:150px!important}
  .navrow{display:flex!important;gap:8px!important;position:sticky!important;bottom:0!important;background:#f5f7fb!important;padding:10px 0!important;z-index:8}
  .navrow button{flex:1}
  .matrix-wrap{overflow-x:auto!important;-webkit-overflow-scrolling:touch}
}

</style></head><body>
<div class="top"><div class="wrap" style="padding:0"><span class="brand"><?=htmlspecialchars($appTitle)?></span><span style="float:right"><?=htmlspecialchars(($_SESSION['participant']['full_name']??$_SESSION['participant']['email']??''))?> · <a href="logout.php">Keluar Peserta</a></span></div></div>
<div class="wrap"><div id="list"><h1>Ujian Online</h1><?php foreach($exams as $e): ?><div class="card"><h2><?=htmlspecialchars($e['title'])?></h2><p>Peserta: <b><?=htmlspecialchars(($_SESSION['participant']['full_name']??''))?></b><br><span style="color:#667085;font-size:14px"><?=htmlspecialchars(($_SESSION['participant']['email']??''))?></span></p><p>Durasi <?=floor($e['duration_seconds']/60)?> menit · <?=htmlspecialchars($e['start_at'])?> sampai <?=htmlspecialchars($e['end_at'])?></p><button onclick="startExam(<?=$e['id']?>)">Mulai / Lanjutkan</button></div><?php endforeach; ?></div>
<div id="exam" class="hidden">
  <div id="timerBox" class="timer"><span>Waktu tersisa</span><span id="timer">--:--</span></div>
  <div class="card">
    <div class="examhead"><div><div class="eyebrow">UJIAN ONLINE</div><h1 id="title"></h1></div><div id="progress" class="progress">Soal 0 / 0</div></div>
    <div id="questions"></div>
    <div class="navrow">
      <button id="prevBtn" class="secondary" onclick="goQuestion(-1)">← Sebelumnya</button>
      <span id="saveStatus" class="saved"></span>
      <button id="nextBtn" onclick="goQuestion(1)">Berikutnya →</button>
      <button id="submitBtn" class="submit" onclick="submitExam(false)">Kirim Ujian</button>
    </div>
  </div>
</div></div>
<script>
const csrf=<?=json_encode(csrf_token())?>;
let attempt=null,deadline=0,clockOffset=0,interval=null,examId=null,submitting=false,currentIndex=0,questions=[],questionMode='all';
const pending=new Map(),timers=new Map();
async function api(url,opt={}){opt.headers={...(opt.headers||{}),'X-CSRF-Token':csrf,'Content-Type':'application/json'};const r=await fetch(url,opt);const d=await r.json().catch(()=>({error:'Respons server tidak valid'}));if(!r.ok)throw Error(d.error||'Gagal');return d}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
function imageHtml(path){if(!path)return '';let p=String(path).replace(/\\/g,'/').trim();if(!p)return '';if(!/^https?:\/\//i.test(p)){p=p.replace(/^\/+/, '');p='../'+p}return `<div><img class="qimg" src="${esc(p)}" alt="Gambar soal" loading="eager" onerror="this.style.display='none';this.parentElement.querySelector('.imgerr').style.display='block'"><div class="imgerr" style="display:none">Gambar soal tidak dapat dimuat.</div></div>`}
function syncClock(ms){if(typeof ms==='number')clockOffset=ms-Date.now()}
function nowServerMs(){return Date.now()+clockOffset}
function queueSave(qid,opt,essay,immediate=false){if(timers.has(qid))clearTimeout(timers.get(qid));const run=()=>saveNow(qid,opt,essay);if(immediate)run();else timers.set(qid,setTimeout(run,350))}
function saveNow(qid,opt,essay){const p=(async()=>{try{const d=await api('../api/save_answer.php',{method:'POST',body:JSON.stringify({attempt_id:attempt,question_id:qid,selected_option:opt,essay_answer:essay})});syncClock(d.server_now_ms);deadline=d.deadline_ms;document.getElementById('saveStatus').textContent='Jawaban tersimpan';return d}catch(e){document.getElementById('saveStatus').textContent='Gagal menyimpan';console.warn(e);throw e}})();pending.set(qid,p);p.finally(()=>{if(pending.get(qid)===p)pending.delete(qid)});return p}
async function flushSaves(){for(const t of timers.values())clearTimeout(t);timers.clear();if(pending.size)await Promise.allSettled([...pending.values()])}
function renderSingleQuestion(){const q=questions[currentIndex];if(!q)return;const matrixLegacyRaw=String(q.question_text||'').trim();const matrixLegacyMatches=[...matrixLegacyRaw.matchAll(/(?:^|\s)([A-D])\.\s*/g)];const matrixQuestionHasEmbeddedOptions=q.type==='matrix_disc'&&matrixLegacyMatches.length>=4;const heading=`<div class="qtop"><div class="qtext"><span class="qnum">${currentIndex+1}.</span>${matrixQuestionHasEmbeddedOptions?'':esc(q.question_text||'')}</div><span class="points">${esc(q.points??0)} poin</span></div>`;let body='';
if(q.type==='essay'){body=`${imageHtml(q.question_image)}<label class="answerlabel">Jawaban Anda</label><textarea class="answerbox" data-q="${q.id}" oninput="questionChanged(this,${q.id})">${esc(q.saved_essay_answer||'')}</textarea><div class="saved smallsave">${q.saved_essay_answer?'Tersimpan':''}</div>`}
else if(q.type==='matrix_disc'){const saved=q.saved_matrix_answer||{};const cols=['A','B','C','D'];const legacyMatrixOptions=(()=>{const raw=matrixLegacyRaw;const re=/(?:^|\s)([A-D])\.\s*/g;const hits=[...raw.matchAll(re)];if(hits.length<4)return null;const out={};for(let i=0;i<hits.length;i++){const letter=hits[i][1];const start=hits[i].index+hits[i][0].length;const end=i+1<hits.length?hits[i+1].index:raw.length;out[letter]=raw.slice(start,end).trim();}return out;})();const optionText=k=>{const direct=String(q['option_'+k.toLowerCase()]||'').trim();return direct||(legacyMatrixOptions&&legacyMatrixOptions[k])||''};const matrixOptions=cols.map(k=>{const text=optionText(k);return text?`<div class="matrix-option"><span class="matrix-letter">${k}.</span><span>${esc(text)}</span></div>`:''}).join('');body=`${imageHtml(q.question_image)}<div class="matrix-options-list">${matrixOptions}</div><div class="matrix-wrap"><div class="matrix-grid"><div class="matrix-corner"></div>${cols.map(k=>`<div class="matrix-col">${k}</div>`).join('')}${[['mirip','MIRIP'],['tidak_mirip','TIDAK MIRIP']].map(([key,label])=>`<div class="matrix-row-label">${label}</div>${cols.map(k=>`<label class="matrix-choice"><input type="radio" name="matrix_${key}_${q.id}" value="${k}" ${saved[key]===k?'checked':''} onchange="matrixChanged(${q.id})"><span></span></label>`).join('')}`).join('')}</div></div><div class="saved smallsave">${saved.mirip&&saved.tidak_mirip?'Tersimpan':'Pilih MIRIP dan TIDAK MIRIP'}</div>`}
else{const saved=q.saved_display_option||'';body=`${imageHtml(q.question_image)}${'ABCDEFGH'.split('').filter(k=>String(q['option_'+k.toLowerCase()]||'').trim()).map(k=>`<label class="opt"><input type="radio" name="answer_${q.id}" value="${k}" ${saved===k?'checked':''} onchange="selectOption('${k}',${q.id})"><b>${k}.</b> ${esc(q['option_'+k.toLowerCase()])}</label>`).join('')}<div class="saved smallsave">${saved?'Tersimpan':''}</div>`}
document.getElementById('questions').innerHTML=`<div class="q">${heading}${body}</div>`;document.getElementById('progress').textContent=`Soal ${currentIndex+1} / ${questions.length}`;document.getElementById('prevBtn').disabled=currentIndex===0;document.getElementById('nextBtn').classList.toggle('hidden',currentIndex===questions.length-1);document.getElementById('submitBtn').classList.toggle('hidden',currentIndex!==questions.length-1);}
function renderAllQuestions(){
  const old=currentIndex, items=[];
  questions.forEach((_,i)=>{currentIndex=i; renderSingleQuestion(); items.push(document.getElementById('questions').innerHTML);});
  currentIndex=old;
  document.getElementById('questions').innerHTML=items.join('');
  document.getElementById('progress').textContent=`Semua soal / ${questions.length}`;
  document.getElementById('prevBtn').classList.add('hidden');
  document.getElementById('nextBtn').classList.add('hidden');
  document.getElementById('submitBtn').classList.remove('hidden');
}
function renderQuestion(){ if(questionMode==='all') renderAllQuestions(); else renderSingleQuestion(); }
function currentQ(){return questions[currentIndex]}
function selectOption(k,qid=null){const q=(qid?questions.find(x=>x.id==qid):currentQ());if(!q)return;q.saved_display_option=k;document.getElementById('saveStatus').textContent='Menyimpan...';queueSave(q.id,k,'',true)}
function questionChanged(el,qid=null){const q=(qid?questions.find(x=>x.id==qid):currentQ());if(!q)return;q.saved_essay_answer=el.value;document.getElementById('saveStatus').textContent='Menyimpan...';queueSave(q.id,null,el.value,false)}function matrixChanged(qid=null){const q=(qid?questions.find(x=>x.id==qid):currentQ());if(!q)return;const prefix=qid?`_${qid}`:'';const get=n=>document.querySelector(`input[name="${n}${prefix}"]:checked`)?.value||'';const ans={mirip:get('matrix_mirip'),tidak_mirip:get('matrix_tidak_mirip')};q.saved_matrix_answer=ans;if(!ans.mirip||!ans.tidak_mirip){document.getElementById('saveStatus').textContent='Pilih satu jawaban pada setiap baris';return}document.getElementById('saveStatus').textContent='Menyimpan...';queueMatrixSave(q.id,ans)}
function queueMatrixSave(qid,ans){const p=(async()=>{try{const d=await api('../api/save_answer.php',{method:'POST',body:JSON.stringify({attempt_id:attempt,question_id:qid,matrix_answer:ans})});syncClock(d.server_now_ms);deadline=d.deadline_ms;document.getElementById('saveStatus').textContent='Jawaban tersimpan';return d}catch(e){document.getElementById('saveStatus').textContent='Gagal menyimpan';throw e}})();pending.set(qid,p);p.finally(()=>{if(pending.get(qid)===p)pending.delete(qid)});return p}
async function goQuestion(delta){await flushSaves();const next=currentIndex+delta;if(next<0||next>=questions.length)return;currentIndex=next;renderQuestion();window.scrollTo({top:0,behavior:'smooth'})}
async function startExam(id){try{examId=id;const d=await api('../api/start.php',{method:'POST',body:JSON.stringify({exam_id:id})});attempt=d.attempt_id;deadline=d.deadline_ms;syncClock(d.server_now_ms);const e=await api('../api/exam.php?id='+encodeURIComponent(id)+'&attempt_id='+encodeURIComponent(attempt));questions=e.questions||[];questionMode=(e.question_mode==='one_by_one'?'one_by_one':'all');document.getElementById('title').textContent=e.title;if(!questions.length){alert('Belum ada soal pada ujian ini.');return}currentIndex=0;renderQuestion();document.getElementById('list').classList.add('hidden');document.getElementById('exam').classList.remove('hidden');tick();clearInterval(interval);interval=setInterval(tick,250)}catch(e){alert(e.message)}}
async function tick(){const left=Math.max(0,deadline-nowServerMs());const s=Math.ceil(left/1000),min=Math.floor(s/60);document.getElementById('timer').textContent=String(min).padStart(2,'0')+':'+String(s%60).padStart(2,'0');if(s<=60)document.getElementById('timerBox').classList.add('danger');if(left<=0){clearInterval(interval);await submitExam(true)}}
async function submitExam(auto){if(submitting)return;submitting=true;document.getElementById('submitBtn').disabled=true;try{await flushSaves();const d=await api('../api/submit.php',{method:'POST',body:JSON.stringify({attempt_id:attempt})});clearInterval(interval);alert(auto?'Waktu habis. Jawaban yang sudah tersimpan dikumpulkan.':'Ujian dikirim. Nilai: '+d.score);location.href='finish.php?attempt='+encodeURIComponent(attempt)+'&auto='+(auto?'1':'0')}catch(e){submitting=false;document.getElementById('submitBtn').disabled=false;alert(e.message)}}
</script></body></html>
