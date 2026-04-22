#!/usr/bin/env bash
# Ein Befehl auf dem API-Server: Code aktualisieren + db/backend neu bauen/starten.
#
# Nutzung (z. B. /opt/ematchef/prod):
#   chmod +x deploy/prod-update.sh
#   EMATCHEF_PROD_ROOT=/opt/ematchef/prod ./deploy/prod-update.sh reset
#
# Modi:
#   reset  — git fetch + reset --hard origin/<branch> (Server = exakt wie Remote, typisch für Droplet)
#   pull   — git pull (wenn du lokal committest und mergen willst)
#   up     — nur Docker (kein Git), z. B. nach manuellem git
#
# Umgebungsvariablen (optional):
#   EMATCHEF_PROD_ROOT   Standard: /opt/ematchef/prod
#   EMATCHEF_GIT_BRANCH  Standard: main
#   COMPOSE_PROJECT_NAME Standard: ematchef-prod

set -euo pipefail

ROOT="${EMATCHEF_PROD_ROOT:-/opt/ematchef/prod}"
BRANCH="${EMATCHEF_GIT_BRANCH:-main}"
PROJECT="${COMPOSE_PROJECT_NAME:-ematchef-prod}"
MODE="${1:-reset}"

cd "$ROOT"

case "$MODE" in
  reset)
    git fetch origin
    git reset --hard "origin/${BRANCH}"
    ;;
  pull)
    git pull origin "$BRANCH"
    ;;
  up) ;;
  *)
    echo "Usage: $0 [reset|pull|up]" >&2
    exit 1
    ;;
esac

export HOST_UID="$(id -u)" HOST_GID="$(id -g)"
docker compose -p "$PROJECT" up -d --build db backend

echo ""
echo "OK: ${PROJECT} db + backend gestartet."
echo "Prod-Cache (bei Code-/Config-Änderungen):"
echo "  docker compose -p ${PROJECT} exec backend php bin/console cache:clear --env=prod"
echo "Logs:"
echo "  docker compose -p ${PROJECT} logs backend --tail 60"
