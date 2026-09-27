# Domaine métier

## Acteurs

- **Une entreprise** (artisan façadier) : ses coordonnées, logo, mentions légales.
- **Un utilisateur** de connexion (compte unique au MVP).
- **Clients** (particuliers ou pro).
- **Documents** : devis ou factures, avec lignes.

## Entreprise (`company`)

Champs : raison sociale, adresse, téléphone, email, site web, forme juridique (ex. EI), SIRET, n° TVA, logo, IBAN/BIC (impression PDF), conditions de paiement (factures), franchise en base TVA (art. 293 B), mentions légales (décennale / assurance pied de page, pénalités, indemnité 40 €, validité devis).

Taux de TVA disponibles : 20 %, 10 %, 5,5 %, 0 %.

## Clients

Nom / raison sociale, adresse, email, téléphone, n° TVA (optionnel), notes.

## Documents

Types : `quote` (devis), `invoice` (facture).

| Type | Statuts |
|------|---------|
| Devis | `draft`, `sent`, `accepted`, `rejected` |
| Facture | `draft`, `sent` |

**Aucun statut lié au règlement** (pas de « payé »). Les mentions d’acompte et le « reste à payer » sont purement documentaires.

### Champs chantier, TVA et acompte

- `object` : titre chantier (ex. « Chantier M. Johan PASCAL à suze la rousse »). Prérempli à la sélection du client (`Chantier {nom} à {ville}`), modifiable ensuite.
- Adresse du projet : `site_address_line1/2`, `site_postal_code`, `site_city` (distincte de l’adresse client).
- `vat_rate_bp` : un seul taux de TVA pour le document (20 %, 10 %, 5,5 %, 0 %). Franchise en base (`vat_exempt`) force 0 %.
- Devis : `deposit_ttc_cents` (montant TTC en centimes, ≤ total TTC). 0 = pas de mention.
- Facture : `deduction_label` + `deduction_ttc_cents` (≤ total TTC). Affiche déduction et « Reste à payer ». 0 = masqué.

La conversion devis → facture copie les lignes, le chantier et le taux de TVA, **pas** l’acompte.

### Cycle de vie

1. Création en `draft` — pas de numéro.
2. Édition libre tant que `draft`.
3. **Envoyer** : attribution du numéro (`DEV-YYYY-NNNN` / `FAC-YYYY-NNNN`, 4 chiffres), statut `sent`, PDF téléchargeable.
4. Devis `sent` → peut passer `accepted` ou `rejected`.
5. Devis `accepted` → conversion en facture `draft` (copie des lignes + chantier, lien `source_document_id`).

Les documents `sent` (et suivants) ne sont plus modifiables (lignes figées). Un nouveau brouillon peut être créé à partir d’une conversion.

## Lignes

Libellé, quantité, unité (ex. m², u, forfait), prix unitaire HT (centimes). Le taux de TVA est porté par le document.

## Compteurs

Table `counters` : une ligne par `(type, year)`. Incrément sous verrou (`SELECT … FOR UPDATE`) à l’émission pour une séquence sans trou.

Au démarrage, les réglages entreprise peuvent initialiser le dernier numéro déjà émis (année en cours) **uniquement** si aucun document de ce type n’a encore de numéro pour l’année.

## PDF

Mise en page calquée sur les documents artisan : en-tête entreprise / client, chantier, tableau numéroté (sans TVA par ligne), TVA unique au total, acompte ou reste à payer, pied de page (forme juridique, SIRET, assurance, IBAN, pagination). Devis : page signature « Bon pour travaux ».

## Libellés UI (français)

| Code | Affichage |
|------|-----------|
| draft | Brouillon |
| sent | Envoyé / Envoyée |
| accepted | Accepté |
| rejected | Refusé |
| quote | Devis |
| invoice | Facture |
