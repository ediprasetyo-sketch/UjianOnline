<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';

api_auth();

$examId = (int)($_GET['id'] ?? 0);
$attemptId = (int)($_GET['attempt_id'] ?? 0);

$examStmt = db()->prepare("SELECT * FROM exams WHERE id=? AND active=1 LIMIT 1");
$examStmt->execute([$examId]);
$exam = $examStmt->fetch();
if (!$exam) json_response(['error'=>'Ujian tidak ditemukan'],404);

if ($attemptId > 0) {
    $attemptStmt = db()->prepare(
        "SELECT a.*, e.duration_seconds AS exam_duration_seconds, e.end_at AS exam_end_at
         FROM attempts a JOIN exams e ON e.id=a.exam_id
         WHERE a.id=? AND a.exam_id=? AND a.user_id=? LIMIT 1"
    );
    $attemptStmt->execute([$attemptId, $examId, participant_id()]);
    $attempt = $attemptStmt->fetch();
    if (!$attempt) json_response(['error'=>'Sesi ujian tidak valid'],403);
    if ($attempt['status'] !== 'active') json_response(['error'=>'Sesi ujian sudah terkunci'],409);

    // Do not expose an active question set after the server-side deadline.
    // The authoritative timer is the attempt deadline, not the browser clock.
    $attempt = normalize_attempt_deadline($attempt);
    $deadlineTs = strtotime((string)$attempt['deadline_at']);
    if ($deadlineTs === false || $deadlineTs <= time()) {
        expire_attempt($attempt);
        json_response(['error'=>'Waktu ujian sudah habis'],409);
    }
}

if ($attemptId > 0) {
    $sql = "SELECT q.*, aq.display_order, aq.option_map,
                    ans.selected_option AS saved_selected_option,
                    ans.essay_answer AS saved_essay_answer, ans.matrix_answer AS saved_matrix_answer
             FROM attempt_questions aq
             JOIN questions q ON q.id=aq.question_id
             LEFT JOIN answers ans ON ans.attempt_id=aq.attempt_id AND ans.question_id=q.id
             WHERE aq.attempt_id=?
             ORDER BY aq.display_order";
    $stmt = db()->prepare($sql);
    $stmt->execute([$attemptId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $map = json_decode($row['option_map'] ?: '{}', true) ?: ['A'=>'A','B'=>'B','C'=>'C','D'=>'D'];
        $original = [
            'A'=>$row['option_a'], 'B'=>$row['option_b'],
            'C'=>$row['option_c'], 'D'=>$row['option_d'], 'E'=>$row['option_e']??null, 'F'=>$row['option_f']??null, 'G'=>$row['option_g']??null, 'H'=>$row['option_h']??null
        ];
        $row['option_a'] = $original[$map['A'] ?? 'A'] ?? null;
        $row['option_b'] = $original[$map['B'] ?? 'B'] ?? null;
        $row['option_c'] = $original[$map['C'] ?? 'C'] ?? null;
        $row['option_d'] = $original[$map['D'] ?? 'D'] ?? null;
        foreach(['E','F','G','H'] as $k){ $row['option_'.strtolower($k)]=$original[$map[$k]??$k]??null; }
        $savedOriginal = $row['saved_selected_option'] ?? null;
        $row['saved_display_option'] = null;
        if ($savedOriginal) {
            foreach ($map as $display => $originalKey) {
                if ($originalKey === $savedOriginal) {
                    $row['saved_display_option'] = $display;
                    break;
                }
            }
        }
        if ($row['type'] === 'matrix_disc') {
            $savedMatrix = json_decode($row['saved_matrix_answer'] ?? '{}', true) ?: [];
            $displayMatrix = [];
            foreach (['mirip','tidak_mirip'] as $rowKey) {
                if (!empty($savedMatrix[$rowKey])) {
                    foreach ($map as $display => $originalKey) {
                        if ($originalKey === $savedMatrix[$rowKey]) { $displayMatrix[$rowKey] = $display; break; }
                    }
                }
            }
            $row['saved_matrix_answer'] = $displayMatrix;
        }
        unset($row['option_map']);
    }
} else {
    $stmt = db()->prepare(
        "SELECT id,type,question_text,question_image,option_a,option_b,option_c,option_d,option_e,option_f,option_g,option_h,use_answer_key,points
         FROM questions WHERE exam_id=? ORDER BY sort_order,id"
    );
    $stmt->execute([$examId]);
    $rows = $stmt->fetchAll();
}

json_response([
    'id'=>(int)$exam['id'],
    'title'=>$exam['title'],
    'question_mode'=>($exam['question_mode'] ?? 'all'),
    'questions'=>$rows,
    'server_now_ms'=>time()*1000,
    'deadline_ms'=>$attemptId > 0 ? strtotime($attempt['deadline_at'])*1000 : null
]);
