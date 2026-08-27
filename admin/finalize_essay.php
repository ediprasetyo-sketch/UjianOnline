<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

$attemptId=(int)($_POST['attempt_id']??0);
$action=trim((string)($_POST['action']??'finalize'));
if($attemptId<1) exit('Attempt tidak valid.');

$st=db()->prepare("SELECT id,exam_id,user_id FROM attempts WHERE id=? LIMIT 1");
$st->execute([$attemptId]);
$attempt=$st->fetch();
if(!$attempt) exit('Attempt tidak ditemukan.');

$latest=db()->prepare("SELECT event_type FROM audit_logs WHERE attempt_id=? AND event_type IN ('essay_finalized','essay_reopened') ORDER BY id DESC LIMIT 1");
$latest->execute([$attemptId]);
$latestEvent=(string)($latest->fetchColumn()?:'');

if($action==='reopen'){
    if($latestEvent!=='essay_finalized'){
        header('Location: essay_grading.php?attempt_id='.$attemptId);
        exit;
    }
    $payload=json_encode(['action'=>'reopen','reason'=>'admin_reopen'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    db()->prepare("INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type,event_data) VALUES(?,?,?,?,?)")->execute([(int)($_SESSION['user']['id']??0),(int)$attempt['exam_id'],$attemptId,'essay_reopened',$payload]);
    header('Location: essay_grading.php?attempt_id='.$attemptId.'&reopened=1');
    exit;
}

if($latestEvent==='essay_finalized'){
    header('Location: essay_grading.php?attempt_id='.$attemptId.'&finalized=1');
    exit;
}

$check=db()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN a.essay_score IS NOT NULL THEN 1 ELSE 0 END) AS graded FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=? AND q.type='essay' AND q.use_answer_key=1 AND q.points>0");
$check->execute([$attemptId,(int)$attempt['exam_id']]);
$progress=$check->fetch();
$total=(int)($progress['total']??0);
$graded=(int)($progress['graded']??0);
if($graded<$total){
    header('Location: essay_grading.php?attempt_id='.$attemptId.'&incomplete=1');
    exit;
}

$sum=db()->prepare("SELECT COALESCE(SUM(CASE WHEN q.type='mcq' AND q.use_answer_key=1 AND a.selected_option=q.correct_option THEN q.points ELSE 0 END),0)+COALESCE(SUM(CASE WHEN q.type='essay' AND q.use_answer_key=1 THEN LEAST(GREATEST(COALESCE(a.essay_score,0),0),q.points) ELSE 0 END),0) AS total FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=?");
$sum->execute([$attemptId,(int)$attempt['exam_id']]);
$totalScore=(float)$sum->fetchColumn();

$payload=json_encode(['action'=>'finalize','graded'=>$graded,'total'=>$total,'essay_score'=>$totalScore,'finalized_by'=>(int)($_SESSION['user']['id']??0)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
db()->beginTransaction();
try{
    db()->prepare("UPDATE attempts SET score=? WHERE id=?")->execute([$totalScore,$attemptId]);
    db()->prepare("INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type,event_data) VALUES(?,?,?,?,?)")->execute([(int)($_SESSION['user']['id']??0),(int)$attempt['exam_id'],$attemptId,'essay_finalized',$payload]);
    db()->commit();
}catch(Throwable $e){
    if(db()->inTransaction()) db()->rollBack();
    exit('Gagal memfinalisasi penilaian Essay.');
}

header('Location: essay_grading.php?attempt_id='.$attemptId.'&finalized=1');
exit;
