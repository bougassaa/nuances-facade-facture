-- Alignement PDF artisan : chantier, acompte, pied de page entreprise
-- À appliquer sur une base déjà initialisée avec 001_init.sql

SET NAMES utf8mb4;

ALTER TABLE company
  ADD COLUMN website VARCHAR(255) NOT NULL DEFAULT '' AFTER bic,
  ADD COLUMN legal_form VARCHAR(40) NOT NULL DEFAULT 'EI' AFTER website,
  ADD COLUMN payment_terms TEXT NULL AFTER legal_form;

ALTER TABLE documents
  ADD COLUMN site_address_line1 VARCHAR(255) NOT NULL DEFAULT '' AFTER notes,
  ADD COLUMN site_address_line2 VARCHAR(255) NOT NULL DEFAULT '' AFTER site_address_line1,
  ADD COLUMN site_postal_code VARCHAR(20) NOT NULL DEFAULT '' AFTER site_address_line2,
  ADD COLUMN site_city VARCHAR(120) NOT NULL DEFAULT '' AFTER site_postal_code,
  ADD COLUMN deposit_percent DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Devis: % du TTC, 0 = masqué' AFTER site_city,
  ADD COLUMN deduction_label VARCHAR(255) NOT NULL DEFAULT '' AFTER deposit_percent,
  ADD COLUMN deduction_ttc_cents INT NOT NULL DEFAULT 0 COMMENT 'Facture: déduction TTC, 0 = masqué' AFTER deduction_label;
