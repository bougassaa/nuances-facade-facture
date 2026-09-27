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

- Stockage en **centimes** (entiers) pour prix unitaires HT, totaux HT/TVA/TTC, déduction facture.
- Quantités en décimal (4 décimales max).
- Arrondi à la ligne : `round(qty * unit_ht_cents)` puis somme ; TVA par taux regroupé.
- Acompte devis : `round(total_ttc_cents * deposit_percent / 100)`.
- Reste à payer facture : `total_ttc_cents - deduction_ttc_cents` (affichage).

## PDF

- Template HTML PHP → dompdf 3.x, pied de page via callback canvas (pagination + mentions légales).
- Généré à la demande (téléchargement) et après action « Envoyer ».
- Schéma SQL : `001_init.sql` (install) ; migration `002_document_layout.sql` (bases déjà en prod).

## Sécurité

- Mot de passe : `password_hash` / `password_verify` (bcrypt).
- CSRF : token session pour mutations (header `X-CSRF-Token`).
- Upload logo : types image limités, hors web, servi via endpoint authentifié si besoin ; sur PDF, chemin fichier local.

## Tests

Obligatoires à chaque changement — détail dans [`docs/testing.md`](testing.md).
Backend : PHPUnit. Frontend : Vitest + Testing Library.
