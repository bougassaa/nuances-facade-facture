#!/usr/bin/env bash
# Produit dist-ovh/facture/ (public) et dist-ovh/facture_app/ (privé)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist-ovh"
PUBLIC_OUT="$OUT/facture"
APP_OUT="$OUT/facture_app"

echo "==> Build frontend"
cd "$ROOT/frontend"
npm install
npm run build

echo "==> Composer (prod)"
cd "$ROOT/backend"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Assemble package"
rm -rf "$OUT"
mkdir -p "$PUBLIC_OUT/api" "$APP_OUT/public" "$APP_OUT/storage/logos"

# SPA
cp -R "$ROOT/frontend/dist/." "$PUBLIC_OUT/"

# API entry → code privé hors web
cat > "$PUBLIC_OUT/api/index.php" <<'PHP'
<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/facture_app/public/index.php';
PHP

# App privée (même structure que backend/)
cp -R "$ROOT/backend/src" "$APP_OUT/src"
cp -R "$ROOT/backend/templates" "$APP_OUT/templates"
cp -R "$ROOT/backend/vendor" "$APP_OUT/vendor"
mkdir -p "$APP_OUT/config"
cp "$ROOT/backend/config/config.example.php" "$APP_OUT/config/config.example.php"
cp "$ROOT/backend/bootstrap.php" "$APP_OUT/bootstrap.php"
cp "$ROOT/backend/public/index.php" "$APP_OUT/public/index.php"
cp "$ROOT/backend/storage/logos/.gitkeep" "$APP_OUT/storage/logos/.gitkeep"
cp "$ROOT/backend/sql/001_init.sql" "$APP_OUT/001_init.sql"
cp "$ROOT/backend/sql/002_document_layout.sql" "$APP_OUT/002_document_layout.sql"

# Config prod (exemple) — à copier en config.local.php sur le serveur
cat > "$APP_OUT/config/config.example.php" <<'PHP'
<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => 'votre-host.mysql.db',
        'port' => 3306,
        'name' => 'votre_base',
        'user' => 'votre_user',
        'pass' => 'votre_mot_de_passe',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'host' => 'facture.nuances-facade.fr',
        'session_name' => 'NFSESSID',
        'secure_cookie' => true,
        'debug' => false,
    ],
    'paths' => [
        'logos' => __DIR__ . '/../storage/logos',
    ],
];
PHP

cat > "$PUBLIC_OUT/.htaccess" <<'HTA'
Options -Indexes
RewriteEngine On

RewriteRule ^api(?:/(.*))?$ api/index.php [QSA,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

RewriteRule ^ index.html [L]
HTA

cat > "$PUBLIC_OUT/.ovhconfig" <<'OVH'
app.engine=php
app.engine.version=8.4
http.firewall=none
environment=production
OVH

chmod +x "$ROOT/scripts/package-ovh.sh" 2>/dev/null || true

echo "==> OK : $OUT"
echo "1. Copier facture/ et facture_app/ à la racine FTP (à côté de www/)."
echo "2. Créer facture_app/config/config.local.php depuis l'exemple."
echo "3. Importer facture_app/001_init.sql dans MySQL (ou 002_document_layout.sql si base déjà créée)."

# En local, restaure les deps de dev. Inutile en CI (le workspace est jeté).
if [[ "${CI:-}" != "true" ]]; then
  cd "$ROOT/backend"
  composer install --no-interaction >/dev/null
fi
