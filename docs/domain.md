# Domaine métier

## Acteurs

- **Une entreprise** (artisan façadier) : ses coordonnées, logo, mentions légales.
- **Un utilisateur** de connexion (compte unique au MVP).
- **Clients** (particuliers ou pro).
- **Documents** : devis ou factures, avec lignes.

## Entreprise (`company`)

Champs : raison sociale, adresse, téléphone, email, site web, forme juridique (ex. EI), SIRET, n° TVA, logo, signature (image), IBAN/BIC (impression PDF), conditions de paiement (factures), franchise en base TVA (art. 293 B), mentions légales (décennale / assurance pied de page, pénalités, indemnité 40 €).

Réglages devis :

- `quote_deposit_percent` : acompte prérempli sur chaque nouveau devis, en % entier (0–100, défaut **30**).
- `quote_validity_months` : validité des devis en mois (0–99, défaut **2**). Le PDF affiche « Valable N mois » sous le numéro ; 0 = pas de mention. Valeur lue au moment de la génération du PDF.

La signature, si elle est enregistrée dans les réglages, est apposée automatiquement dans le cadre entreprise de la page « Bon pour travaux » des devis. Le cadre client reste vide, à signer à la main.

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

- `object` : titre chantier (ex. « Chantier M. Johan PASCAL à suze la rousse »). Prérempli à la sélection du client (`Chantier {nom} à {ville}`), modifiable ensuite. Imprimé comme objet du document (grand titre italique au-dessus du tableau).
- Adresse du projet : `site_address_line1/2`, `site_postal_code`, `site_city` (distincte de l’adresse client).
- `vat_rate_bp` : un seul taux de TVA pour le document (20 %, 10 %, 5,5 %, 0 %). Franchise en base (`vat_exempt`) force 0 %.
- Devis : `deposit_percent` (entier 0–100). Un nouveau devis sans valeur prend `quote_deposit_percent` des réglages ; l’artisan peut le changer par devis. `deposit_ttc_cents` est **calculé côté serveur** à chaque enregistrement : `round(total_ttc × percent / 100)` (suit les lignes). PDF : « Acompte à la signature de 30 %, soit 4 941,75 €. » 0 % = pas de mention.
- Facture : `deduction_label` + `deduction_ttc_cents` (≤ total TTC). Affiche déduction et « Reste à payer ». 0 = masqué. Une facture ignore `deposit_percent` (toujours 0).

La conversion devis → facture copie les lignes, le chantier et le taux de TVA ; l’acompte du devis devient la déduction de la facture (libellé « Acompte fournitures », montant = `deposit_ttc_cents` du devis), modifiable avant envoi. Sans acompte, pas de déduction.

### Cycle de vie

1. Création en `draft` — pas de numéro.
2. Édition libre à tout moment, y compris après envoi, acceptation ou refus. L’enregistrement conserve le numéro et le statut.
3. **Envoyer** : attribution du numéro (`DEV-YYYY-NNNN` / `FAC-YYYY-NNNN`, 4 chiffres), statut `sent`, PDF téléchargeable. Réservé aux brouillons.
4. Devis `sent` → peut passer `accepted` ou `rejected`.
5. Devis `accepted` → conversion en facture `draft` (copie des lignes + chantier, lien `source_document_id`).

Seul un brouillon peut être supprimé. Un nouveau brouillon peut être créé à partir d’une conversion.

## Lignes

Libellé, quantité, unité au choix parmi `m²`, `ml`, `u` et `forfait` (le menu détaille « ml (mètre linéaire) » et « u (unité) », seule l’abréviation est enregistrée et imprimée ; une ligne neuve est en `m²`), prix unitaire HT (centimes). Le taux de TVA est porté par le document. Quantité, prix et déduction se saisissent avec une virgule ou un point ; l’acompte devis est un pourcentage entier ; seuls des nombres partent vers l’API.

## Catalogue de désignations

Table `line_designations` : libellés réutilisables avec un prix unitaire HT par défaut (centimes).

- À la saisie d’une ligne, le champ Désignation propose les entrées existantes (saisie libre possible). Choisir une entrée préremplit le prix HT de la ligne ; ce prix reste modifiable sans mettre à jour le catalogue.
- À l’enregistrement d’un devis ou d’une facture, chaque libellé encore inconnu est ajouté au catalogue avec le prix de la première ligne qui le porte. Un libellé déjà connu n’est jamais écrasé depuis le document.
- Gestion dans Paramètres : créer, renommer, changer le prix par défaut, supprimer. Renommer ou supprimer n’altère pas les documents déjà enregistrés.
- Unicité du libellé sans distinction de casse (`utf8mb4_unicode_ci`).

## Compteurs

Table `counters` : une ligne par `(type, year)`. Incrément sous verrou (`SELECT … FOR UPDATE`) à l’émission pour une séquence sans trou.

Les réglages entreprise permettent de fixer le dernier numéro émis pour l’année en cours, y compris lorsqu’une séquence a déjà commencé ou que des documents portent déjà un numéro. Le prochain envoi prend le numéro suivant. Les numéros déjà attribués ne sont pas réécrits. Les champs restent toujours saisissables.

## PDF

Mise en page calquée sur les documents de l’ancien logiciel de l’artisan (police Helvetica, gris `#efefef` pour les blocs, `#8f8f8f` pour les bandeaux) :

- En-tête : nom de l’entreprise en grand, adresse et contacts, logo à droite de l’adresse ; client dans un bloc gris à droite.
- Sous l’en-tête : « Devis n° … » / « Facture n° … », « Valable N mois » (devis), « Adresse du projet : » (italique) puis l’adresse dans un bloc gris ; « En date du … » à droite.
- Objet (`object`) en grand titre italique.
- Tableau numéroté : N°, Désignation, Qté (quantité + unité), PU HT, TVA (taux du document répété sur chaque ligne, colonne masquée en franchise de TVA), Total HT. En-tête gris, texte blanc, colonnes séparées par des filets.
- Totaux à droite : Total HT, TVA à x %, bandeau « Total TTC » ; facture : déduction (« - 1 692,90 € ») et bandeau « Reste à payer ». À gauche : mention d’acompte (devis) ou conditions de paiement (facture), puis notes.
- Pied de page centré sous un filet (coordonnées, puis forme juridique, SIRET, assurance, IBAN), avec retour à la ligne ; « Page x / y » à droite ; numéro du document en haut à droite dès la page 2.
- Devis : page signature « Bon pour travaux », cadre gris client à gauche (mention à recopier) et cadre gris entreprise à droite (nom + image de signature si elle est configurée).

## Libellés UI (français)

| Code | Affichage |
|------|-----------|
| draft | Brouillon |
| sent | Envoyé / Envoyée |
| accepted | Accepté |
| rejected | Refusé |
| quote | Devis |
| invoice | Facture |
