-- Acompte devis en pourcentage du TTC (défaut entreprise 30 %)
-- et validité des devis en nombre de mois (défaut 2)
-- À appliquer sur une base déjà initialisée (001 à 005)

SET NAMES utf8mb4;

ALTER TABLE company
  ADD COLUMN quote_deposit_percent TINYINT UNSIGNED NOT NULL DEFAULT 30
    COMMENT 'Acompte prérempli sur les nouveaux devis (%)' AFTER payment_terms,
  ADD COLUMN quote_validity_months TINYINT UNSIGNED NOT NULL DEFAULT 2
    COMMENT 'Validité des devis en mois, 0 = pas de mention' AFTER quote_deposit_percent;

-- L’ancien texte libre (« Valable 3 mois ») est remplacé par quote_validity_months.
ALTER TABLE company
  DROP COLUMN legal_quote_validity;

ALTER TABLE documents
  ADD COLUMN deposit_percent TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Devis: acompte en % du TTC, 0 = masqué' AFTER vat_rate_bp;

-- Devis existants : le montant saisi devient un pourcentage arrondi…
UPDATE documents
SET deposit_percent = LEAST(100, ROUND(deposit_ttc_cents * 100 / total_ttc_cents))
WHERE doc_type = 'quote'
  AND deposit_ttc_cents > 0
  AND total_ttc_cents > 0;

-- … et le montant est recalculé depuis ce pourcentage (même règle que l’API).
UPDATE documents
SET deposit_ttc_cents = CAST(ROUND(total_ttc_cents * deposit_percent / 100) AS SIGNED);
