# Tests — obligatoire

Toute évolution de code s’accompagne de tests dans le **même changement**. Pas de feature ou de correctif sans couverture.

## Règle pour les agents LLM

1. Avant de marquer une tâche terminée : les tests concernés passent.
2. Nouveau comportement métier / API / UI → **nouveaux tests** (pas seulement « ça marche à la main »).
3. Bugfix → **test de régression** qui échoue sans le fix.
4. Mettre à jour ce fichier si la stack de test change.

## Backend (PHPUnit 11)

```bash
cd backend && composer install && ./vendor/bin/phpunit
```

| Zone | Fichiers typiques | À tester |
|------|-------------------|----------|
| Domaine | `tests/Domain/*` | enums, transitions de statut autorisées / refusées |
| Calculs | `tests/Services/Totals*` | centimes, arrondi ligne, TVA, franchise, acompte, reste à payer |
| Numérotation | `tests/Services/DocumentNumber*` | séquence sans trou, préfixes DEV/FAC, 4 chiffres, seed compteur |
| Repos | `tests/Repositories/*` | CRUD, send, convert, chantier, déduction, verrous (MySQL) |
| PDF | `tests/Services/PdfTemplate*` | HTML acompte, signature, reste à payer |
| HTTP | `tests/Http/*` | routage, matching des params |
| Auth | `tests/Auth/*` | hash mot de passe, CSRF, setup unique |

Les tests d’intégration MySQL utilisent `config/config.local.php`. S’ils ne peuvent pas se connecter, ils font `markTestSkipped` — les tests unitaires purs restent obligatoires et doivent toujours passer.

## Frontend (Vitest + Testing Library)

```bash
cd frontend && npm install && npm test
```

| Zone | À tester |
|------|----------|
| `src/format.ts` | montants FR, dates, conversion euros ↔ centimes, libellés statut |
| Composants | rendu Chip statut, formulaires critiques |
| Pages / auth | login, setup, appels API mockés (`fetch`) |
| `src/api/client.ts` | CSRF header, gestion d’erreur |

Pas de CSS custom à tester : on vérifie le comportement et le texte FR, pas le style MUI.

## Minimum attendu par type de changement

| Changement | Tests minimum |
|------------|---------------|
| Calcul / totaux / TVA | unit backend |
| Statut / numérotation / conversion | unit + intégration repo si possible |
| Nouvel endpoint API | test route ou repo + cas d’erreur |
| Nouvelle page / flux UI | test composant ou page avec mocks |
| Format affichage (date, €) | test `format.ts` |

## CI locale rapide

```bash
cd backend && ./vendor/bin/phpunit
cd frontend && npm test
```
