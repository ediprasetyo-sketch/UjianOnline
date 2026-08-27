<?php
/*
V6.3.73 hotfix helper.
Call normalize_question_options($pdo, $questionId, $optionCount)
after saving a Pilihan Ganda question.
*/
function normalize_question_options(PDO $pdo, int $questionId, int $optionCount): void {
    $optionCount = max(2, min(8, $optionCount));
    $letters = ['a','b','c','d','e','f','g','h'];
    $sets = ['option_count = :option_count'];
    $params = [':option_count' => $optionCount, ':id' => $questionId];

    for ($i = $optionCount; $i < count($letters); $i++) {
        $sets[] = 'option_'.$letters[$i].' = NULL';
    }

    // Prevent an answer key from pointing to a removed option.
    $allowed = $letters[$optionCount - 1];
    $sets[] = "answer_key = CASE
        WHEN answer_key IS NOT NULL
         AND ASCII(UPPER(answer_key)) > ASCII('{$allowed}')
        THEN NULL ELSE answer_key END";

    $sql = 'UPDATE questions SET '.implode(', ', $sets).' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
