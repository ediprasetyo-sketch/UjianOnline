-- V6.3.6 idempotent migration: public exam links + participant email registration
ALTER TABLE exams ADD COLUMN IF NOT EXISTS public_token VARCHAR(64) NULL;
UPDATE exams SET public_token = LOWER(MD5(CONCAT(id, '|', title, '|', RAND(), '|', NOW(6)))) WHERE public_token IS NULL OR public_token = '';

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'exams' AND index_name = 'idx_exams_public_token'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE UNIQUE INDEX idx_exams_public_token ON exams(public_token)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL;

SET @email_idx_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_email'
);
SET @email_sql := IF(@email_idx_exists = 0,
  'CREATE INDEX idx_users_email ON users(email)',
  'SELECT 1');
PREPARE email_stmt FROM @email_sql;
EXECUTE email_stmt;
DEALLOCATE PREPARE email_stmt;

UPDATE users SET email = username WHERE (email IS NULL OR email='') AND username LIKE '%@%';
