-- REVOPRINTSHOP V6.3.10
-- Public exam link + verified participant email + essay score compatibility.
ALTER TABLE `exams` ADD COLUMN IF NOT EXISTS `public_token` VARCHAR(64) NULL;
UPDATE `exams` SET `public_token` = LOWER(MD5(CONCAT(`id`, '|', `title`, '|', RAND(), '|', NOW(6)))) WHERE `public_token` IS NULL OR `public_token` = '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(190) NULL;
UPDATE `users` SET `email` = `username` WHERE (`email` IS NULL OR `email`='') AND `username` LIKE '%@%';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verify_token` VARCHAR(128) NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verify_expires_at` DATETIME NULL;
ALTER TABLE `answers` ADD COLUMN IF NOT EXISTS `essay_score` DECIMAL(8,2) NULL;
UPDATE `users` SET `email_verified_at`=COALESCE(`email_verified_at`,NOW()) WHERE `email` IS NOT NULL AND `email`<>'';
