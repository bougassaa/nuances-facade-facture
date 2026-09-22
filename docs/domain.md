# Domaine métier

## Acteurs

- **Une entreprise** (artisan façadier) : ses coordonnées, logo, mentions légales.
- **Un utilisateur** de connexion (compte unique au MVP).
- **Clients** (particuliers ou pro).
- **Documents** : devis ou factures, avec lignes.

## Entreprise (`company`)

Champs : raison sociale, adresse, téléphone, email, SIRET, n° TVA, logo, IBAN/BIC (optionnels, impression PDF uniquement), franchise en base TVA (art. 293 B), mentions légales (décennale, pénalités, indemnité 40 €, validité devis).

Taux de TVA disponibles : 20 %, 10 %, 5,5 %, 0 %.

## Clients

Nom / raison sociale, adresse, email, téléphone, n° TVA (optionnel), notes.

## Documents

Types : `quote` (devis), `invoice` (facture).

| Type | Statuts |
|------|---------|
| Devis | `draft`, `sent`, `accepted`, `rejected` |
| Facture | `draft`, `sent` |

**Aucun statut lié au paiement.**

### Cycle de vie

1. Création en `draft` — pas de numéro.
2. Édition libre tant que `draft`.
3. **Envoyer** : attribution du numéro (`DEV-YYYY-NNN` / `FAC-YYYY-NNN`), statut `sent`, PDF téléchargeable.
4. Devis `sent` → peut passer `accepted` ou `rejected`.
5. Devis `accepted` → conversion en facture `draft` (copie des lignes, lien `source_document_id`).

Les documents `sent` (et suivants) ne sont plus modifiables (lignes figées). Un nouveau brouillon peut être créé à partir d’une conversion.

## Lignes

Libellé, quantité, unité (ex. m², u, forfait), prix unitaire HT (centimes), taux de TVA.

## Compteurs

Table `counters` : une ligne par `(type, year)`. Incrément sous verrou (`SELECT … FOR UPDATE`) à l’émission pour une séquence sans trou.

## Libellés UI (français)

| Code | Affichage |
|------|-----------|
| draft | Brouillon |
| sent | Envoyé / Envoyée |
| accepted | Accepté |
| rejected | Refusé |
| quote | Devis |
| invoice | Facture |
