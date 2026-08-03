#!/usr/bin/env bash
# Erzeugt im Repo-Root eine .env mit zufälligen Geheimnissen für deploy/docker-compose.override.prod.example.yml
# Aufruf (auf dem Server im geklonten Repo):  bash deploy/init-prod-env.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/.env"

if [[ -f "$ENV_FILE" && "${1:-}" != "--force" ]]; then
  echo "Hinweis: $ENV_FILE existiert bereits."
  echo "Zum Neuerzeugen (alte Werte werden ersetzt): bash deploy/init-prod-env.sh --force"
  exit 1
fi

# PostgreSQL: 32 Bytes Zufall (256 Bit) als Hex — nur [0-9a-f], problemlos in DATABASE_URL
POSTGRES_PASSWORD="$(openssl rand -hex 32)"
APP_SECRET="$(openssl rand -hex 32)"
JWT_PASSPHRASE="$(openssl rand -hex 48)"

cat >"$ENV_FILE" <<EOF
# Automatisch erzeugt von deploy/init-prod-env.sh — nicht committen.
# POSTGRES_PASSWORD: stark zufällig (openssl rand -hex 32), User mvuser in Compose
POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
APP_SECRET=${APP_SECRET}
JWT_PASSPHRASE=${JWT_PASSPHRASE}

# Optional (von Hand; siehe deploy/docker-compose.prod.env.example):
# MAILER_DSN="ses+api://ACCESS_KEY:SECRET@default?region=eu-central-1"
# MAILER_FROM="noreply@ematchef.ch"
# TURNSTILE_SECRET_KEY="..."
EOF

chmod 600 "$ENV_FILE"
echo "Geschrieben: $ENV_FILE (Modus 600) — POSTGRES_PASSWORD, APP_SECRET, JWT_PASSPHRASE jeweils kryptografisch zufällig."
echo "Nächster Schritt: cp deploy/docker-compose.override.prod.example.yml docker-compose.override.yml (falls noch nicht)"
echo "Dann: docker compose -p ematchef-prod up -d --build db backend"
