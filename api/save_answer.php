<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
api_auth();
check_api_csrf();

$b=body();
$attemptId=(int)($b['attempt_id']??0);
$qid=(int)($b['question_id']??0);
if($attemptId<1||$qid<1)json_response(['error'=>'Data jawaban tidak lengkap'],400);

// Jawaban yang sedang dalam perjalanan boleh disimpan maksimal 2 detik setelah
// deadline agar jawaban yang sudah diketik tidak hilang saat auto-submit berjalan.
// Ini bukan perpanjangan waktu: submit/finalisasi tetap dikunci pada deadline.
$s=db()->prepare("SELECT a.*,e.duration_seconds AS exam_duration_seconds,e.end_at AS exam_end_at
                  FROM attempts a JOIN exams e ON e.id=a.exam_id
                  WHERE a.id=? AND a.user_id=? LIMIT 1");
$s->execute([$attemptId,participant_id()]);
$attempt=$s->fetch();
if(!$attempt)json_response(['error'=>'Attempt tidak ditemukan'],404);
if($attempt['status']!=='active')json_response(['error'=>'Ujian sudah terkunci'],409);
$attempt=normalize_attempt_deadline($attempt);
$deadline=strtotime($attempt['deadline_at']);
if(time()>$deadline+2){
    finalize_attempt($attempt);
    json_response(['error'=>'Waktu habis/ujian terkunci'],409);
}

$q=db()->prepare("SELECT q.id,q.type,aq.option_map FROM questions q JOIN attempt_questions aq ON aq.question_id=q.id AND aq.attempt_id=? WHERE q.id=? AND q.exam_id=? LIMIT 1");
$q->execute([$attemptId,$qid,$attempt['exam_id']]);
$question=$q->fetch();
if(!$question)json_response(['error'=>'Soal tidak valid'],400);

$selected=$b['selected_option']??null;
$matrixAnswer=null;
$map=json_decode($question['option_map']?:'{}',true)?:[];
if($question['type']==='mcq'){
    if(!in_array($selected,['A','B','C','D','E','F','G','H'],true))json_response(['error'=>'Pilihan tidak valid'],400);
    $selected=$map[$selected]??$selected;
}elseif($question['type']==='matrix_disc'){
    $input=$b['matrix_answer']??null;
    if(!is_array($input))json_response(['error'=>'Jawaban matriks tidak valid'],400);
    $matrixAnswer=[];
    foreach(['mirip','tidak_mirip'] as $key){
        $choice=(string)($input[$key]??'');
        if(!in_array($choice,['A','B','C','D','E','F','G','H'],true))json_response(['error'=>'Setiap baris matriks wajib dipilih'],400);
        $matrixAnswer[$key]=$map[$choice]??$choice;
    }
    if($matrixAnswer['mirip']===$matrixAnswer['tidak_mirip']){
        json_response(['error'=>'Pilihan MIRIP dan TIDAK MIRIP harus berbeda'],400);
    }
    $selected=null;
}else{$selected=null;}

$save=db()->prepare("INSERT INTO answers(attempt_id,question_id,selected_option,essay_answer,matrix_answer) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE selected_option=VALUES(selected_option),essay_answer=VALUES(essay_answer),matrix_answer=VALUES(matrix_answer)");
$save->execute([$attemptId,$qid,$selected,$question['type']==='essay'?($b['essay_answer']??''):null,$matrixAnswer?json_encode($matrixAnswer):null]);

json_response(['ok'=>true,'server_now_ms'=>time()*1000,'deadline_ms'=>$deadline*1000]);
