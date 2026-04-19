#!/bin/sh
set -e
cd /var/www/html

# vendor/ liegt in einem benannten Volume (docker-compose): alter Stand bleibt über Rebuilds hinweg.
# Ohne Abgleich mit composer.lock fehlen z. B. symfony/http-client → FcalApiService nicht autowired.
echo "[backend] composer install …"
composer install --no-interaction --prefer-dist --no-scripts

# Ohne aktuelles Schema schlagen Login und /api/users|profiles mit 500 fehl (Doctrine).
echo "[backend] doctrine:migrations:migrate …"
php bin/console doctrine:migrations:migrate --no-interaction

# Keys sind in .gitignore; fehlende PEM-Dateien → JWT-Erstellung schlägt beim Login fehl.
echo "[backend] lexik:jwt:generate-keypair (skip-if-exists) …"
php bin/console lexik:jwt:generate-keypair --skip-if-exists -n

exec "$@"
