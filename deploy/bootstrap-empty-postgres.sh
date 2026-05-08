#!/usr/bin/env bash
# Einmalig auf einer LEEREN Postgres-DB (frisches Volume), wenn doctrine:migrations:migrate
# scheitert (fehlende Kern-Tabellen / historische Migrationen).
#
# WARNUNG: Löscht alle Tabellen in der konfigurierten DB (doctrine:schema:drop).
#
# Aufruf vom Repo-Root auf dem Server:
#   bash deploy/bootstrap-empty-postgres.sh
# oder mit Pfad:
#   bash deploy/bootstrap-empty-postgres.sh /opt/ematchef/prod
#
# Danach: docker compose -p ematchef-prod up -d --build backend
set -euo pipefail

ROOT="${1:-$(pwd)}"
COMPOSE_PROJECT="${COMPOSE_PROJECT:-ematchef-prod}"
cd "$ROOT"

echo "==> Starte db …"
docker compose -p "$COMPOSE_PROJECT" up -d db
echo "==> Warte auf Healthcheck (max. 60s) …"
for i in $(seq 1 30); do
  if docker compose -p "$COMPOSE_PROJECT" exec -T db pg_isready -U mvuser -d mvdb >/dev/null 2>&1; then
    echo "    Postgres bereit."
    break
  fi
  sleep 2
  if [[ "$i" -eq 30 ]]; then
    echo "Fehler: Postgres wurde nicht rechtzeitig bereit."
    exit 1
  fi
done

echo "==> Schema aus Entities erzeugen + Migrationen als erledigt markieren …"
docker compose -p "$COMPOSE_PROJECT" run --rm --entrypoint "" backend sh -eu -c '
cd /var/www/html
composer install --no-interaction --prefer-dist --no-scripts
php bin/console doctrine:schema:drop --force --full-database || true
php bin/console doctrine:schema:create --no-interaction
php bin/console doctrine:migrations:sync-metadata-storage --no-interaction || true
php bin/console doctrine:migrations:version --add --all --no-interaction
'

echo "==> Backend starten (Entrypoint: migrate sollte nichts mehr offen haben) …"
export HOST_UID="$(id -u)" HOST_GID="$(id -g)"
docker compose -p "$COMPOSE_PROJECT" up -d --build backend

echo "==> Fertig. Test: curl -sS -o /dev/null -w '%{http_code}\\n' http://127.0.0.1:8081/"
