-- V6.3.6.3: public-link/email schema. No PREPARE/EXECUTE/DEALLOCATE.
ALTER TABLE `exams` ADD COLUMN IF NOT EXISTS `public_token` VARCHAR(64) NULL;
UPDATE `exams` SET `public_token` = LOWER(MD5(CONCAT(`id`, '|', `title`, '|', RAND(), '|', NOW(6)))) WHERE `public_token` IS NULL OR `public_token` = '';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(190) NULL;
UPDATE `users` SET `email` = `username` WHERE (`email` IS NULL OR `email`='') AND `username` LIKE '%@%';
