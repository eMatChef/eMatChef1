#!/usr/bin/env bash
# Migrationen gegen eine leere DB testen — deine lokale mvdb bleibt unangetastet.
#
# Voraussetzung: Im Projektroot, Docker, Stack mit Service „db“ (wie docker-compose.yml).
#
# Nutzung:
#   ./scripts/test-migrations.sh           # Standard: temporäre DB im laufenden Postgres-Container
#   ./scripts/test-migrations.sh isolated  # eigenes Compose-Projekt + eigenes Volume (komplett getrennt)
#
# Optional:
#   MIGRATION_TMP_DBNAME=mvdb_mytest ./scripts/test-migrations.sh
#   COMPOSE_PROJECT_NAME=foo ./scripts/test-migrations.sh   # wenn du nicht den Default-Projektnamen nutzt

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export HOST_UID="${HOST_UID:-$(id -u)}" HOST_GID="${HOST_GID:-$(id -g)}"

compose() {
  docker compose "$@"
}

wait_db() {
  local i
  for i in $(seq 1 45); do
    if compose exec -T db pg_isready -U mvuser -d mvdb >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "Postgres (db) wird nicht healthy — bitte zuerst: docker compose up -d db" >&2
  return 1
}

tmp_db() {
  local tmpdb="${MIGRATION_TMP_DBNAME:-mvdb_migtest_tmp}"
  echo "==> Modus tmp_db: lege temporäre Datenbank „${tmpdb}“ an (mvdb bleibt unverändert)."
  wait_db
  compose exec -T db psql -U mvuser -d postgres -v ON_ERROR_STOP=1 -c \
    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${tmpdb}' AND pid <> pg_backend_pid();" \
    2>/dev/null || true
  compose exec -T db psql -U mvuser -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"${tmpdb}\";"
  compose exec -T db psql -U mvuser -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"${tmpdb}\";"

  echo "==> composer + doctrine:migrations:migrate gegen ${tmpdb} …"
  # Einmal-Container: schreibbarer COMPOSER_HOME; root vermeidet Permission-Konflikte auf vendor-Volume.
  compose run --rm --user 0:0 \
    -e COMPOSER_HOME=/tmp/composer-cache \
    -e DATABASE_URL="postgresql://mvuser:mvpass@db:5432/${tmpdb}?serverVersion=16&charset=utf8" \
    --entrypoint bash \
    backend -lc 'cd /var/www/html && composer install --no-interaction --prefer-dist --no-scripts && php bin/console doctrine:migrations:migrate --no-interaction'

  echo "==> Aufräumen: DROP DATABASE ${tmpdb}"
  compose exec -T db psql -U mvuser -d postgres -v ON_ERROR_STOP=1 -c \
    "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${tmpdb}' AND pid <> pg_backend_pid();" \
    2>/dev/null || true
  compose exec -T db psql -U mvuser -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"${tmpdb}\";"
  echo "==> Fertig."
}

isolated() {
  local p="${MIGRATION_ISOLATED_PROJECT:-ematchef-migtest}"
  echo "==> Modus isolated: Compose-Projekt „${p}“ (eigenes Volume, dein Standard-Stack bleibt unberührt)."
  compose -p "$p" up -d db
  local i
  for i in $(seq 1 45); do
    if compose -p "$p" exec -T db pg_isready -U mvuser -d mvdb >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
  compose -p "$p" run --rm --user 0:0 \
    -e COMPOSER_HOME=/tmp/composer-cache \
    --entrypoint bash \
    backend -lc 'cd /var/www/html && composer install --no-interaction --prefer-dist --no-scripts && php bin/console doctrine:migrations:migrate --no-interaction'
  echo "==> OK. Abmelden + Volume löschen:"
  echo "    docker compose -p \"${p}\" down -v"
}

case "${1:-tmp_db}" in
  tmp_db) tmp_db ;;
  isolated) isolated ;;
  *)
    echo "Usage: $0 [tmp_db|isolated]" >&2
    exit 1
    ;;
esac
