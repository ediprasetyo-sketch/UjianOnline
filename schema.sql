CREATE DATABASE IF NOT EXISTS ujian_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ujian_online;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','participant') NOT NULL,
  participant_code VARCHAR(100) UNIQUE NULL,
  full_name VARCHAR(200) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  email VARCHAR(190) NULL,
  email_verified_at DATETIME NULL,
  email_verify_token VARCHAR(128) NULL,
  email_verify_expires_at DATETIME NULL,
  INDEX idx_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exams (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  question_mode ENUM('all','one_by_one') NOT NULL DEFAULT 'all',
  active TINYINT(1) NOT NULL DEFAULT 1,
  access_code VARCHAR(32) NULL UNIQUE,
  randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
  randomize_options TINYINT(1) NOT NULL DEFAULT 0,
  public_token VARCHAR(64) NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_id BIGINT UNSIGNED NOT NULL,
  type ENUM('mcq','essay','matrix_disc') NOT NULL DEFAULT 'mcq',
  question_text TEXT NOT NULL,
  question_image VARCHAR(500) NULL,
  essay_answer_key TEXT NULL,
  option_a TEXT NULL,
  option_b TEXT NULL,
  option_c TEXT NULL,
  option_d TEXT NULL,
  option_e TEXT NULL,
  option_f TEXT NULL,
  option_g TEXT NULL,
  option_h TEXT NULL,
  use_answer_key TINYINT(1) NOT NULL DEFAULT 1,
  correct_option VARCHAR(8) NULL,
  matrix_correct_mirip CHAR(1) NULL,
  matrix_correct_tidak CHAR(1) NULL,
  points DECIMAL(8,2) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  INDEX(exam_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  deadline_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  status ENUM('active','submitted','expired') NOT NULL DEFAULT 'active',
  score DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY one_attempt (exam_id,user_id),
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(status,deadline_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attempt_questions (
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  display_order INT NOT NULL,
  option_map JSON NULL,
  PRIMARY KEY(attempt_id,question_id),
  UNIQUE KEY attempt_display_order (attempt_id,display_order),
  FOREIGN KEY(attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  selected_option CHAR(1) NULL,
  essay_answer TEXT NULL,
  matrix_answer JSON NULL,
  essay_score DECIMAL(8,2) NULL,
  saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY one_answer (attempt_id,question_id),
  FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_participants (
  exam_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(exam_id,user_id),
  FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  exam_id BIGINT UNSIGNED NULL,
  attempt_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  event_data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_at), INDEX(event_type)
) ENGINE=InnoDB;
