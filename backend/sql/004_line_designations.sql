-- Catalogue de désignations de lignes (libellé + prix HT par défaut)
-- À appliquer sur une base déjà initialisée (001 + éventuellement 002/003)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS line_designations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(500) NOT NULL,
  unit_price_ht_cents INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_line_designations_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
