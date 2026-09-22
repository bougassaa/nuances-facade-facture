# Déploiement OVH — facture.nuances-facade.fr

## Prérequis

1. Hébergement web OVH (offre classique) avec PHP 8.4.
2. Domaine `nuances-facade.fr` déjà sur l’hébergement (`www/`).
3. Multisite : ajouter le sous-domaine `facture.nuances-facade.fr` avec racine `facture/` et SSL ([guide multisite](https://docs.ovhcloud.com/en/guides/web-cloud/web-hosting/multisites-configure-multisite.md)).
4. Base MySQL créée dans l’espace client ; importer `backend/sql/001_init.sql`.

## Arborescence FTP / SFTP

```
/home/user/
├── www/                 # site principal — ne pas modifier
├── facture/             # racine web du sous-domaine
│   ├── index.html
│   ├── assets/
│   ├── api/index.php
│   ├── .htaccess
│   └── .ovhconfig
└── facture_app/         # hors de toute racine web
    ├── bootstrap.php
    ├── src/
    ├── vendor/
    ├── config/
    │   ├── config.example.php
    │   └── config.local.php   # secrets, ne pas versionner
    ├── storage/logos/
    └── templates/pdf/
```

`facture/api/index.php` fait `require` de `../../facture_app/bootstrap.php` (chemin relatif au FTP).

## Packaging local

```bash
./scripts/package-ovh.sh
```

Génère `dist-ovh/facture/` et `dist-ovh/facture_app/`.
Copier sur le serveur (SFTP), puis créer `facture_app/config/config.local.php` à partir de l’exemple.

## Configuration PHP

Fichier `facture/.ovhconfig` :

```
app.engine=php
app.engine.version=8.4
http.firewall=none
environment=production
```

## Session

Cookie limité à l’hôte `facture.nuances-facade.fr` (pas de `Domain=.nuances-facade.fr`).

## Première connexion

Ouvrir `https://facture.nuances-facade.fr` → écran d’installation (création du compte admin) si aucun utilisateur n’existe.
