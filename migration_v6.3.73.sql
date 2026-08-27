ALTER TABLE questions ADD COLUMN option_count TINYINT NOT NULL DEFAULT 4;
UPDATE questions SET option_count =
CASE
  WHEN option_h IS NOT NULL AND option_h <> '' THEN 8
  WHEN option_g IS NOT NULL AND option_g <> '' THEN 7
  WHEN option_f IS NOT NULL AND option_f <> '' THEN 6
  WHEN option_e IS NOT NULL AND option_e <> '' THEN 5
  WHEN option_d IS NOT NULL AND option_d <> '' THEN 4
  WHEN option_c IS NOT NULL AND option_c <> '' THEN 3
  ELSE 2
END;
