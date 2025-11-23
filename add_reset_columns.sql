-- Add reset token columns to users table
ALTER TABLE `users` 
ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL AFTER `email`,
ADD COLUMN `reset_expiry` DATETIME DEFAULT NULL AFTER `reset_token`;
