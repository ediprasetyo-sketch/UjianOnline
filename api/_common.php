<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/participant_session.php';
require_once __DIR__ . '/../includes/attempt_sync.php';

function api_auth(): void { require_participant(); }
function participant_id(): int { $p=require_participant(); return (int)$p['id']; }
function body(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }
function check_api_csrf(): void { $token=$_SERVER['HTTP_X_CSRF_TOKEN']??''; if(!hash_equals($_SESSION['csrf']??'',$token)) json_response(['error'=>'CSRF'],419); }
function normalize_attempt_deadline(array $attempt): array {
    $startedTs=strtotime((string)$attempt['started_at']); $storedDeadline=strtotime((string)$attempt['deadline_at']); $examDuration=(int)($attempt['exam_duration_seconds']??0); $examEndTs=strtotime((string)($attempt['exam_end_at']??''));
    if($startedTs===false||$examDuration<1)return $attempt; $calculated=$startedTs+$examDuration; if($examEndTs!==false&&$examEndTs>0)$calculated=min($calculated,$examEndTs);
    if($storedDeadline!==$calculated){$u=db()->prepare("UPDATE attempts SET deadline_at=FROM_UNIXTIME(?) WHERE id=? AND status='active'");$u->execute([$calculated,(int)$attempt['id']]);$attempt['deadline_at']=date('Y-m-d H:i:s',$calculated);} return $attempt;
}
function get_attempt(int $id): array {
    $s=db()->prepare("SELECT a.*,e.duration_seconds AS exam_duration_seconds,e.end_at AS exam_end_at FROM attempts a JOIN exams e ON e.id=a.exam_id WHERE a.id=? AND a.user_id=? LIMIT 1");$s->execute([$id,participant_id()]);$a=$s->fetch();if(!$a)json_response(['error'=>'Attempt tidak ditemukan'],404);
    if($a['status']==='active'){$a=normalize_attempt_deadline($a);if(strtotime($a['deadline_at'])<=time()){expire_attempt($a);$a['status']='expired';}}return $a;
}

/** Score exclusively from the immutable scoring snapshot captured at start. */
function calculate_attempt_score(array $a): float {
    $q=db()->prepare("SELECT aq.scoring_snapshot,ans.selected_option,ans.matrix_answer,ans.essay_score FROM attempt_questions aq LEFT JOIN answers ans ON ans.question_id=aq.question_id AND ans.attempt_id=aq.attempt_id WHERE aq.attempt_id=? ORDER BY aq.display_order");
    $q->execute([(int)$a['id']]); $score=0.0;
    foreach($q as $r){
        $snap=json_decode((string)($r['scoring_snapshot']??''),true);
        if(!is_array($snap)) continue; $type=$snap['type']??''; $points=(float)($snap['points']??0);
        if($type==='mcq' && $r['selected_option']!==null && (string)$r['selected_option']===(string)($snap['correct_option']??'')) $score+=$points;
        elseif($type==='matrix_disc'){
            $ans=json_decode((string)($r['matrix_answer']??''),true)?:[]; $half=$points/2;
            if(($ans['mirip']??null)!==null && (string)$ans['mirip']===(string)($snap['matrix_correct_mirip']??''))$score+=$half;
            if(($ans['tidak_mirip']??null)!==null && (string)$ans['tidak_mirip']===(string)($snap['matrix_correct_tidak']??''))$score+=$half;
        } elseif($type==='essay' && $r['essay_score']!==null) $score+=(float)$r['essay_score'];
    }
    return $score;
}
function auto_grade_essays(int $attemptId,int $examId): void {
    $q=db()->prepare("SELECT aq.question_id,aq.scoring_snapshot,a.essay_answer,a.essay_score FROM attempt_questions aq LEFT JOIN answers a ON a.question_id=aq.question_id AND a.attempt_id=aq.attempt_id WHERE aq.attempt_id=? ORDER BY aq.display_order");$q->execute([$attemptId]);
    $u=db()->prepare("INSERT INTO answers(attempt_id,question_id,essay_score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE essay_score=VALUES(essay_score)");
    foreach($q as $r){$snap=json_decode((string)$r['scoring_snapshot'],true);if(!is_array($snap)||($snap['type']??'')!=='essay'||empty($snap['use_answer_key']))continue;$key=trim((string)($snap['essay_answer_key']??''));$answer=trim((string)($r['essay_answer']??''));if($key===''||$answer==='')continue;$normalize=static function(string $v):string{$v=trim(mb_strtolower($v,'UTF-8'));return preg_replace('/\s+/u',' ',$v)??$v;};if($normalize($answer)===$normalize($key))$u->execute([$attemptId,(int)$r['question_id'],(float)($snap['points']??0)]);}
}
function complete_attempt(array $a,string $status):void{
    if($a['status']!=='active')return;if(!in_array($status,['submitted','expired'],true))throw new InvalidArgumentException('Status attempt tidak valid');$pdo=db();$pdo->beginTransaction();
    try{$lock=$pdo->prepare('SELECT id,exam_id,user_id,status FROM attempts WHERE id=? FOR UPDATE');$lock->execute([(int)$a['id']]);$current=$lock->fetch();if(!$current||$current['status']!=='active'){$pdo->commit();return;}
        auto_grade_essays((int)$current['id'],(int)$current['exam_id']);$score=calculate_attempt_score(['id'=>(int)$current['id'],'exam_id'=>(int)$current['exam_id']]);
        $update=$pdo->prepare("UPDATE attempts SET status=?,submitted_at=NOW(),score=? WHERE id=? AND status='active'");$update->execute([$status,$score,(int)$current['id']]);
        if($update->rowCount()===1)$pdo->prepare("INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type) VALUES(?,?,?,?)")->execute([(int)$current['user_id'],(int)$current['exam_id'],(int)$current['id'],$status==='submitted'?'attempt_submitted':'attempt_expired']);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function submit_attempt(array $a):void{complete_attempt($a,'submitted');}
function expire_attempt(array $a):void{complete_attempt($a,'expired');}
function finalize_attempt(array $a):void{expire_attempt($a);}
