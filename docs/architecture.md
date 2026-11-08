# Architecture

## Vue d’ensemble

```
facture.nuances-facade.fr
├── facture/                 # racine web (multisite OVH)
│   ├── index.html           # SPA React
│   ├── assets/              # JS/CSS build Vite
│   ├── api/index.php        # front controller (inclut facture_app)
│   ├── .htaccess
│   └── .ovhconfig           # PHP 8.4
└── facture_app/             # hors web
    ├── src/
    ├── vendor/
    ├── config/
    ├── storage/logos/
    ├── storage/signatures/
    ├── storage/sessions/    # fichiers de session PHP (1 an)
    └── templates/pdf/
```

En développement, `backend/public/` joue le rôle de `facture/api/` + front controller, et Vite sert le SPA.

## Front

- Vite + React + TypeScript + React Router.
- Design system : MUI. Proxy `/api` → PHP en local.
- Auth : cookie de session PHP (même origine en prod).

## Back

- Front controller unique : toutes les routes `/api/*`.
- Routeur minimal (méthode + chemin).
- PDO MySQL, transactions pour émission et conversion.
- Session PHP : cookie persistant 1 an (`HttpOnly`, `Secure` en HTTPS, `SameSite=Lax`), fichiers dans `storage/sessions/` (hors purge OVH). Hôte `facture.nuances-facade.fr` uniquement.

## Calculs monétaires

- Stockage en **centimes** (entiers) pour prix unitaires HT, totaux HT/TVA/TTC, acompte devis, déduction facture.
- Quantités en décimal (4 décimales max).
- Saisie : clavier décimal pour quantité et montants, clavier numérique pour compteurs, code postal et SIRET, clavier téléphone pour les numéros. Virgule et point sont acceptés comme séparateur ; l’API et MySQL reçoivent des nombres (centimes entiers, quantité décimale), jamais une chaîne localisée.
- Arrondi HT à la ligne : `round(qty * unit_ht_cents)` puis somme ; TVA une seule fois : `round(total_ht_cents * vat_rate_bp / 10000)`.
- Acompte devis : pourcentage saisi (`deposit_percent`, prérempli depuis `company.quote_deposit_percent`), montant `deposit_ttc_cents = round(total_ttc_cents × percent / 100)` recalculé par le serveur à chaque enregistrement.
- Reste à payer facture : `total_ttc_cents - deduction_ttc_cents` (affichage).

## PDF

- Template HTML PHP → dompdf 3.x, police Helvetica (police standard PDF) avec repli automatique DejaVu Sans caractère par caractère pour les glyphes absents ; pied de page via callback canvas (texte centré avec retour à la ligne, pagination, numéro en haut à droite dès la page 2).
- Généré à la demande (téléchargement) et après action « Envoyer ».
- Schéma SQL : `001_init.sql` (install) ; migrations `002_document_layout.sql`, `003_document_vat_deposit.sql`, `004_line_designations.sql`, `005_company_signature.sql`, `006_quote_deposit_validity.sql` (bases déjà en prod).

## Sécurité

- Mot de passe : `password_hash` / `password_verify` (bcrypt).
- CSRF : token session pour mutations (header `X-CSRF-Token`).
- Upload logo et signature : types image limités (PNG, JPEG, WebP, GIF), hors web, servis via endpoint authentifié ; sur PDF, image embarquée. La signature est recadrée (marges blanches) à l’enregistrement.

## Tests

Obligatoires à chaque changement — détail dans [`docs/testing.md`](testing.md).
Backend : PHPUnit. Frontend : Vitest + Testing Library.
