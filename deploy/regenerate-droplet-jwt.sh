#!/usr/bin/env bash
# Droplet (Repo-Root): neue JWT_PASSPHRASE (openssl) + Lexik-Keypair neu erzeugen.
# Alle bestehenden JWT/Refresh-Tokens für dieses Compose-Projekt werden ungültig.
#
# Develop (Standard):
#   cd /opt/ematchef/develop && bash deploy/regenerate-droplet-jwt.sh --yes
#
# Anderes Compose-Projekt:
#   COMPOSE_PROJECT_NAME=ematchef-prod bash deploy/regenerate-droplet-jwt.sh --yes
#
# Ohne --yes: nur Hinweis, kein Schreiben (kein versehentliches Ausführen).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PROJECT="${COMPOSE_PROJECT_NAME:-ematchef-develop}"
ENV_FILE="$ROOT/.env"

if [[ "${1:-}" != "--yes" ]]; then
  echo "Aufruf mit --yes bestätigen (löscht gültige Tokens auf diesem Server für Projekt ${PROJECT})." >&2
  echo "Beispiel: cd $ROOT && bash deploy/regenerate-droplet-jwt.sh --yes" >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Fehler: $ENV_FILE fehlt. Erst z. B. cp deploy/develop-droplet.env.example .env anlegen." >&2
  exit 1
fi

PHRASE="$(openssl rand -hex 32)"
tmp="$(mktemp)"
grep -v '^JWT_PASSPHRASE=' "$ENV_FILE" >"$tmp" || true
printf '%s\n' "JWT_PASSPHRASE=${PHRASE}" >>"$tmp"
mv "$tmp" "$ENV_FILE"
chmod 600 "$ENV_FILE"

echo "==> JWT_PASSPHRASE in .env gesetzt (${#PHRASE} Zeichen Hex). Wert wird nicht nochmal ausgegeben."

echo "==> Backend starten (lädt neue Env) …"
docker compose -p "$PROJECT" up -d backend

echo "==> Lexik Keypair (--overwrite) …"
docker compose -p "$PROJECT" exec -T backend php bin/console lexik:jwt:generate-keypair --overwrite -n

echo "==> Fertig. Neu einloggen; alte Tokens für ${PROJECT} sind ungültig."
