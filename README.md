# Nuances Facture

Outil de devis et factures pour Nuances Façade.
Production : [https://facture.nuances-facade.fr](https://facture.nuances-facade.fr)

Voir [AGENTS.md](AGENTS.md) et [docs/](docs/) pour le contexte projet (obligatoire pour les agents LLM).

## Démarrage local

1. Créer une base MySQL et importer `backend/sql/001_init.sql`.
2. `cp backend/config/config.example.php backend/config/config.local.php` puis renseigner MySQL.
3. Backend : `cd backend && composer install && php -S 127.0.0.1:8080 -t public public/router.php`
4. Frontend : `cd frontend && npm install && npm run dev`
5. Ouvrir http://127.0.0.1:5173

## Tests (obligatoires)

Voir [docs/testing.md](docs/testing.md) — toute évolution de code doit être couverte.

```bash
cd backend && ./vendor/bin/phpunit   # 35+ tests
cd frontend && npm test             # Vitest
```

## Packaging OVH

```bash
./scripts/package-ovh.sh
```

Déployer `dist-ovh/facture/` et `dist-ovh/facture_app/` (voir [docs/deployment-ovh.md](docs/deployment-ovh.md)).
