ALTER TABLE questions
  MODIFY type ENUM('mcq','essay','matrix_disc') NOT NULL DEFAULT 'mcq',
  ADD COLUMN matrix_correct_mirip CHAR(1) NULL AFTER correct_option,
  ADD COLUMN matrix_correct_tidak CHAR(1) NULL AFTER matrix_correct_mirip;
ALTER TABLE answers
  ADD COLUMN matrix_answer JSON NULL AFTER essay_answer;
