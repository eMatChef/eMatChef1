#!/usr/bin/env bash
# Auf dem API-Server im Repo-Root ausführen (nach git clone):
#   bash deploy/server-prod-apply.sh
#
# Reihenfolge: git pull → Prod-Override → .env (nur wenn fehlend) → docker compose db+backend
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

echo "==> 1/5 git pull"
git pull

echo "==> 2/5 docker-compose.override.yml"
if [[ -f docker-compose.override.yml ]]; then
  echo "    (existiert bereits — unverändert)"
else
  cp deploy/docker-compose.override.prod.example.yml docker-compose.override.yml
  echo "    aus deploy/docker-compose.override.prod.example.yml kopiert"
fi

echo "==> 3/5 .env mit Geheimnissen"
if [[ -f .env ]]; then
  echo "    .env existiert bereits — init-prod-env.sh wird übersprungen."
  echo "    Neu erzeugen (überschreibt Geheimnisse, DB-Passwort ändert sich!): bash deploy/init-prod-env.sh --force"
else
  bash deploy/init-prod-env.sh
fi

echo "==> 4/5 docker compose (db + backend)"
export HOST_UID="$(id -u)" HOST_GID="$(id -g)"
docker compose -p ematchef-prod up -d --build db backend

echo "==> 5/5 Status"
docker compose -p ematchef-prod ps -a
echo ""
echo "Lokal testen: curl -sS -o /dev/null -w '%{http_code}\\n' http://127.0.0.1:8081/"
