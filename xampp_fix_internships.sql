-- Aligns the legacy XAMPP internships table with the current Laravel application.
-- Select rapport_stage_ia in phpMyAdmin before importing this file.
-- Uses the legacy columns reported by SHOW COLUMNS FROM internships.
-- Run once. Existing rows and legacy columns are preserved.

ALTER TABLE `internships`
    ADD COLUMN `titre` VARCHAR(180) NULL AFTER `user_id`,
    ADD COLUMN `poste` VARCHAR(180) NULL,
    ADD COLUMN `encadrant_nom` VARCHAR(180) NULL,
    ADD COLUMN `encadrant_email` VARCHAR(255) NULL,
    ADD COLUMN `date_debut` DATE NULL,
    ADD COLUMN `date_fin` DATE NULL,
    ADD COLUMN `theme` TEXT NULL,
    ADD COLUMN `problematique` TEXT NULL,
    MODIFY COLUMN `theme_stage` VARCHAR(255) NULL;

UPDATE `internships`
SET
    `titre` = COALESCE(NULLIF(TRIM(`titre`), ''), CONCAT('Stage - ', `entreprise`)),
    `poste` = COALESCE(`poste`, `poste_occupe`),
    `encadrant_nom` = COALESCE(`encadrant_nom`, `tuteur_entreprise`),
    `date_debut` = COALESCE(`date_debut`, `periode_debut`),
    `date_fin` = COALESCE(`date_fin`, `periode_fin`),
    `theme` = COALESCE(`theme`, `theme_stage`);

ALTER TABLE `internships`
    MODIFY COLUMN `titre` VARCHAR(180) NOT NULL;
