-- Corrects the legacy XAMPP/MySQL users table used by the profile screen.
-- Select the rapport_stage_ia database in phpMyAdmin before importing this file.
-- Run once. Existing nom/prenom values are copied to name before the old
-- columns are made optional, so new accounts can be created with name.

ALTER TABLE `users`
    ADD COLUMN `name` VARCHAR(255) NULL AFTER `id`;

UPDATE `users`
SET `name` = TRIM(CONCAT_WS(
    ' ',
    NULLIF(TRIM(COALESCE(`nom`, '')), ''),
    NULLIF(TRIM(COALESCE(`prenom`, '')), '')
))
WHERE `name` IS NULL OR TRIM(`name`) = '';

ALTER TABLE `users`
    MODIFY COLUMN `nom` VARCHAR(255) NULL,
    MODIFY COLUMN `prenom` VARCHAR(255) NULL;
