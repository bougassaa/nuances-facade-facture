-- Image de signature entreprise, apposée sur les devis
-- À appliquer sur une base déjà initialisée (001 sans cette colonne)

SET NAMES utf8mb4;

ALTER TABLE company
  ADD COLUMN signature_path VARCHAR(255) NULL DEFAULT NULL AFTER logo_path;
