# Roadmap

## Fait (MVP)

- [x] Installation compte unique
- [x] Fiche entreprise + logo + mentions
- [x] Clients CRUD
- [x] Devis / factures + lignes
- [x] Numérotation à l’envoi
- [x] PDF téléchargeable
- [x] Conversion devis accepté → facture
- [x] UI MUI mobile-first
- [x] Packaging OVH sous-domaine
- [x] Suite de tests PHPUnit + Vitest (voir `docs/testing.md`)

## Suite

- [ ] Envoi email (SMTP OVH) à l’action « Envoyer »
- [ ] Avoirs (credit notes)
- [ ] Situations de travaux / acomptes liés (report devis → facture, historique)
- [ ] Multi-utilisateurs / rôles
- [ ] Catalogue d’articles / ouvrages fréquents
- [ ] Export comptable (CSV)

## Fait (post-MVP)

- [x] Déploiement FTP OVH via GitHub Actions (push sur `main`)
- [x] PDF aligné documents artisan (chantier, pied de page, signature devis)
- [x] Mention acompte devis (%) et déduction / reste à payer facture (sans suivi de règlement)
- [x] Numérotation à 4 chiffres + seed compteur

## Hors périmètre volontaire

- Suivi des paiements / statut « payé »
- Multi-entreprises (SaaS)
