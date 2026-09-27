-- TVA au niveau document + acompte devis en euros TTC
-- À appliquer sur une base déjà initialisée (001 + éventuellement 002)

SET NAMES utf8mb4;

ALTER TABLE documents
  ADD COLUMN vat_rate_bp SMALLINT UNSIGNED NOT NULL DEFAULT 2000
    COMMENT 'Taux TVA du document (basis points)' AFTER site_city,
  ADD COLUMN deposit_ttc_cents INT NOT NULL DEFAULT 0
    COMMENT 'Devis: acompte TTC en centimes, 0 = masqué' AFTER vat_rate_bp;

UPDATE documents d
SET d.vat_rate_bp = COALESCE(
  (
    SELECT dl.vat_rate_bp
    FROM document_lines dl
    WHERE dl.document_id = d.id
    ORDER BY dl.position ASC, dl.id ASC
    LIMIT 1
  ),
  2000
);

UPDATE documents
SET deposit_ttc_cents = CAST(ROUND(total_ttc_cents * deposit_percent / 100) AS SIGNED)
WHERE deposit_percent > 0;

ALTER TABLE documents
  DROP COLUMN deposit_percent;

UPDATE document_lines dl
INNER JOIN documents d ON d.id = dl.document_id
SET dl.vat_rate_bp = d.vat_rate_bp,
    dl.line_vat_cents = 0;
