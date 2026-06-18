#!/bin/sh
set -e
cd /var/www/html

# Composer-Cache/Config: im Container als non-root (HOST_UID) schreibbar halten
# (sonst fällt Composer ggf. auf / und scheitert beim VCS-Clone in ~/.composer)
export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$COMPOSER_HOME/cache}"
mkdir -p "$COMPOSER_HOME" "$COMPOSER_CACHE_DIR"

# Compose „healthy“ reicht bei starkem I/O manchmal nicht: kurz per PDO prüfen, damit Migrationen nicht ins Leere laufen.
echo "[backend] wait for PostgreSQL …"
php docker/wait-for-db.php

# vendor/ liegt in einem benannten Volume (docker-compose): alter Stand bleibt über Rebuilds hinweg.
# Ohne Abgleich mit composer.lock fehlen z. B. symfony/http-client → FcalApiService nicht autowired.
# Langsame Netze / große Pakete: Standard-Composer-Timeout (300s) erhöhen.
export COMPOSER_PROCESS_TIMEOUT="${COMPOSER_PROCESS_TIMEOUT:-1800}"
echo "[backend] composer install …"
composer install --no-interaction --prefer-dist --no-scripts

# Ohne aktuelles Schema schlagen Login und /api/users|profiles mit 500 fehl (Doctrine).
echo "[backend] doctrine:migrations:migrate …"
php bin/console doctrine:migrations:migrate --no-interaction

# Keys sind in .gitignore; fehlende PEM-Dateien → JWT-Erstellung schlägt beim Login fehl.
echo "[backend] lexik:jwt:generate-keypair (skip-if-exists) …"
php bin/console lexik:jwt:generate-keypair --skip-if-exists -n

# Signal für deploy/prod-update.sh: Entrypoint fertig (Composer + Migrationen), bevor exec php -S.
touch /tmp/backend-entrypoint-ready

exec "$@"
