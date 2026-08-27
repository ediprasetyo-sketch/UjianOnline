-- V6.3.7: essay answer key + manual essay grading
ALTER TABLE questions ADD COLUMN IF NOT EXISTS essay_answer_key TEXT NULL AFTER question_image;
ALTER TABLE answers ADD COLUMN IF NOT EXISTS essay_score DECIMAL(8,2) NULL AFTER essay_answer;
