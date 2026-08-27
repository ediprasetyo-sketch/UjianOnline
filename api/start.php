<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';

api_auth();
check_api_csrf();

$b = body();
$examId = (int)($b['exam_id'] ?? 0);

$stmt = db()->prepare("SELECT * FROM exams WHERE id=? AND active=1 LIMIT 1");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    json_response(['error' => 'Ujian tidak tersedia'], 404);
}

if (participant_session() === null) {
    json_response(['error' => 'Akses peserta tidak valid'], 403);
}

if (
    empty($exam['public_token']) ||
    empty($_SESSION['public_exam_token']) ||
    !hash_equals((string)$exam['public_token'], (string)$_SESSION['public_exam_token'])
) {
    json_response(['error' => 'Gunakan link ujian peserta yang dibagikan admin'], 403);
}

$now = time();
$startTs = strtotime($exam['start_at']);
$endTs = strtotime($exam['end_at']);

if ($now < $startTs || $now > $endTs) {
    json_response(['error' => 'Di luar jadwal ujian'], 409);
}

$existing = db()->prepare(
    "SELECT a.*, e.duration_seconds AS exam_duration_seconds, e.end_at AS exam_end_at
     FROM attempts a
     JOIN exams e ON e.id=a.exam_id
     WHERE a.exam_id=? AND a.user_id=? LIMIT 1"
);
$existing->execute([$examId, participant_id()]);
$attempt = $existing->fetch();

if ($attempt) {
    if ($attempt['status'] !== 'active') {
        json_response(['error' => 'Ujian sudah selesai dan tidak dapat diulang'], 409);
    }

    // Repair an old/wrong deadline using the original started_at + current
    // exam duration. This is the important V6.3.8 fix.
    $attempt = normalize_attempt_deadline($attempt);

    $deadline = strtotime($attempt['deadline_at']);
    if ($deadline <= $now) {
        finalize_attempt($attempt);
        json_response(['error' => 'Waktu ujian sudah habis'], 409);
    }

    json_response([
        'attempt_id' => (int)$attempt['id'],
        'deadline_ms' => $deadline * 1000,
        'server_now_ms' => time() * 1000,
        'duration_seconds' => (int)$exam['duration_seconds']
    ]);
}

$deadline = min($now + (int)$exam['duration_seconds'], $endTs);
$pdo = db();

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        "INSERT INTO attempts(exam_id,user_id,started_at,deadline_at)
         VALUES(?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?))"
    );
    $insert->execute([
        $examId,
        participant_id(),
        $now,
        $deadline
    ]);

    $attemptId = (int)$pdo->lastInsertId();

    $q = $pdo->prepare(
        "SELECT id,type,option_a,option_b,option_c,option_d,option_e,option_f,option_g,option_h FROM questions
         WHERE exam_id=?
         ORDER BY sort_order,id"
    );
    $q->execute([$examId]);
    $questions = $q->fetchAll();

    if ((int)$exam['randomize_questions'] === 1) {
        shuffle($questions);
    }

    $mapStmt = $pdo->prepare(
        "INSERT INTO attempt_questions(attempt_id,question_id,display_order,option_map)
         VALUES(?,?,?,?)"
    );

    foreach ($questions as $i => $question) {
        $keys=[]; foreach(['A','B','C','D','E','F','G','H'] as $k){if(trim((string)($question['option_'.strtolower($k)]??''))!=='')$keys[]=$k;} $optionMap=array_combine($keys,$keys);

        if (
            (int)$exam['randomize_options'] === 1 &&
            in_array($question['type'], ['mcq','matrix_disc'], true)
        ) {
            $original=$keys; shuffle($original); $optionMap=array_combine($keys,$original);
        }

        $mapStmt->execute([
            $attemptId,
            (int)$question['id'],
            $i + 1,
            json_encode($optionMap)
        ]);
    }

    $audit = $pdo->prepare(
        "INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type,event_data)
         VALUES(?,?,?,?,?)"
    );
    $audit->execute([
        participant_id(),
        $examId,
        $attemptId,
        'attempt_started',
        json_encode(['ip'=>$_SERVER['REMOTE_ADDR'] ?? null])
    ]);

    $pdo->commit();

    json_response([
        'attempt_id' => $attemptId,
        'deadline_ms' => $deadline * 1000,
        'server_now_ms' => time() * 1000,
        'duration_seconds' => (int)$exam['duration_seconds']
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['error' => 'Gagal membuat sesi ujian'], 500);
}
