-- Nuances Facture — schéma initial
-- MySQL / MariaDB, utf8mb4, InnoDB
-- Aucune notion de paiement

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  name VARCHAR(255) NOT NULL DEFAULT '',
  address_line1 VARCHAR(255) NOT NULL DEFAULT '',
  address_line2 VARCHAR(255) NOT NULL DEFAULT '',
  postal_code VARCHAR(20) NOT NULL DEFAULT '',
  city VARCHAR(120) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  siret VARCHAR(20) NOT NULL DEFAULT '',
  vat_number VARCHAR(30) NOT NULL DEFAULT '',
  iban VARCHAR(40) NOT NULL DEFAULT '',
  bic VARCHAR(20) NOT NULL DEFAULT '',
  website VARCHAR(255) NOT NULL DEFAULT '',
  legal_form VARCHAR(40) NOT NULL DEFAULT 'EI',
  payment_terms TEXT NULL,
  logo_path VARCHAR(255) NULL DEFAULT NULL,
  vat_exempt TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Franchise en base art. 293 B',
  legal_decennale TEXT NULL,
  legal_late_penalties TEXT NULL,
  legal_recovery_fee TEXT NULL,
  legal_quote_validity TEXT NULL,
  legal_extra TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_company_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO company (id, name) VALUES (1, '')
  ON DUPLICATE KEY UPDATE id = id;

CREATE TABLE IF NOT EXISTS vat_rates (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rate_bp SMALLINT UNSIGNED NOT NULL COMMENT 'Taux en points de base, ex. 2000 = 20%',
  label VARCHAR(20) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vat_rate (rate_bp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vat_rates (rate_bp, label, is_default) VALUES
  (2000, '20 %', 1),
  (1000, '10 %', 0),
  (550, '5,5 %', 0),
  (0, '0 %', 0)
ON DUPLICATE KEY UPDATE label = VALUES(label);

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL DEFAULT '',
  address_line2 VARCHAR(255) NOT NULL DEFAULT '',
  postal_code VARCHAR(20) NOT NULL DEFAULT '',
  city VARCHAR(120) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  vat_number VARCHAR(30) NOT NULL DEFAULT '',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clients_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS counters (
  doc_type ENUM('quote', 'invoice') NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (doc_type, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type ENUM('quote', 'invoice') NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  number VARCHAR(40) NULL DEFAULT NULL,
  client_id INT UNSIGNED NOT NULL,
  source_document_id INT UNSIGNED NULL DEFAULT NULL,
  issue_date DATE NULL DEFAULT NULL,
  valid_until DATE NULL DEFAULT NULL,
  object VARCHAR(255) NOT NULL DEFAULT '',
  notes TEXT NULL,
  site_address_line1 VARCHAR(255) NOT NULL DEFAULT '',
  site_address_line2 VARCHAR(255) NOT NULL DEFAULT '',
  site_postal_code VARCHAR(20) NOT NULL DEFAULT '',
  site_city VARCHAR(120) NOT NULL DEFAULT '',
  vat_rate_bp SMALLINT UNSIGNED NOT NULL DEFAULT 2000 COMMENT 'Taux TVA du document (basis points)',
  deposit_ttc_cents INT NOT NULL DEFAULT 0 COMMENT 'Devis: acompte TTC en centimes, 0 = masqué',
  deduction_label VARCHAR(255) NOT NULL DEFAULT '',
  deduction_ttc_cents INT NOT NULL DEFAULT 0 COMMENT 'Facture: déduction TTC, 0 = masqué',
  total_ht_cents INT NOT NULL DEFAULT 0,
  total_vat_cents INT NOT NULL DEFAULT 0,
  total_ttc_cents INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  sent_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documents_number (number),
  KEY idx_documents_type_status (doc_type, status),
  KEY idx_documents_client (client_id),
  KEY idx_documents_updated (updated_at),
  CONSTRAINT fk_documents_client FOREIGN KEY (client_id) REFERENCES clients (id),
  CONSTRAINT fk_documents_source FOREIGN KEY (source_document_id) REFERENCES documents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id INT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  label VARCHAR(500) NOT NULL,
  quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
  unit VARCHAR(40) NOT NULL DEFAULT 'u',
  unit_price_ht_cents INT NOT NULL DEFAULT 0,
  vat_rate_bp SMALLINT UNSIGNED NOT NULL DEFAULT 2000,
  line_ht_cents INT NOT NULL DEFAULT 0,
  line_vat_cents INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_lines_document (document_id),
  CONSTRAINT fk_lines_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
