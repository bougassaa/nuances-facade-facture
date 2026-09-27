# Nuances Facture — guide pour agents LLM

## Produit

Application de devis et factures pour un artisan façadier (entreprise unique).
Hôte public : `https://facture.nuances-facade.fr`.
Elle permet de créer, modifier et envoyer des devis et factures (PDF).
Elle ne suit pas les règlements : aucun statut « payé ». Les mentions d’acompte
(pourcentage sur devis, déduction et « reste à payer » sur facture) sont autorisées
comme libellés d’impression uniquement.

## Stack

| Couche | Techno |
|--------|--------|
| Front | React + TypeScript + Vite + MUI (`@mui/material`) |
| Back | PHP 8.4 (local possible 8.2+), PDO, sans framework |
| PDF | dompdf 3.x |
| Base | MySQL / MariaDB, charset `utf8mb4` |
| Hébergement | OVH Web classique, multisite, dossier `facture/` |

## Règles non négociables

1. **Lire la doc** avant de modifier le domaine métier ou le déploiement : `docs/domain.md`, `docs/architecture.md`, `docs/deployment-ovh.md`, `docs/testing.md`.
2. **Mettre à jour la doc** dans le même changement que le code concerné.
3. **MUI uniquement** pour boutons, champs, Select, Autocomplete, Dialog, Menu, Chip, Card, etc. Pas de CSS custom pour ces composants. Mise en page via `Stack` / `Container` / thème MUI uniquement.
4. **Pas de suivi des règlements** : aucun statut « payé ». Mentions d’acompte / reste à payer autorisées sur le PDF et en saisie documentaire uniquement.
5. **Totaux en centimes**, calculés côté serveur, arrondi à la ligne.
6. **Numéro de document** attribué uniquement au passage en statut « envoyé », séquence sans trou, verrou SQL.
7. Code PHP privé hors web (`facture_app/`). Secrets dans `config.local.php` (gitignored).
8. Interface en français. Montants `1 234,56 €`. Dates `jj/mm/aaaa`.
9. **Tests obligatoires** (front + back) pour tout nouveau comportement ou correctif — voir `docs/testing.md`. Ne pas livrer sans `./vendor/bin/phpunit` et `npm test` verts sur la zone touchée.

## Structure

```
AGENTS.md
docs/                 # architecture, domaine, déploiement OVH, roadmap, testing
backend/              # API PHP + SQL + PDF
frontend/             # React + MUI
scripts/package-ovh.sh
```

## Statuts documents

- Devis : `draft` | `sent` | `accepted` | `rejected`
- Facture : `draft` | `sent`

## Développement local

```bash
# Backend
cd backend && composer install
cp config/config.example.php config/config.local.php  # puis éditer MySQL
php -S 127.0.0.1:8080 -t public public/router.php
./vendor/bin/phpunit

# Frontend
cd frontend && npm install && npm run dev
npm test
```

Le proxy Vite envoie `/api` vers `http://127.0.0.1:8080`.

## Packaging OVH

```bash
./scripts/package-ovh.sh
```

Produit `dist-ovh/facture/` (public) et `dist-ovh/facture_app/` (privé).
Un push sur `main` déclenche `.github/workflows/deploy.yml` : tests, build, envoi FTP de ces deux dossiers.
