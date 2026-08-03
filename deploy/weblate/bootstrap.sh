#!/usr/bin/env bash
# Einmalig auf dem Droplet ematchef-translate ausführen (als root oder mit sudo).
# Läuft im Verzeichnis der Skript-Datei (egal ob /opt/weblate oder /opt/weblate/weblate).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="${INSTALL_DIR:-$SCRIPT_DIR}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Bitte als root ausführen: sudo bash bootstrap.sh"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ca-certificates curl git ufw

if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi

systemctl enable --now docker

# Firewall: SSH + HTTP/HTTPS
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable || true

cd "$INSTALL_DIR"

if [[ ! -f docker-compose.yml ]]; then
  echo "docker-compose.yml fehlt in $INSTALL_DIR"
  echo "Erwartet: Dateien aus dem Repo deploy/weblate/"
  exit 1
fi

if [[ ! -f environment ]]; then
  cp environment.example environment
  echo "→ environment angelegt — Passwörter setzen: nano $INSTALL_DIR/environment"
fi

if [[ ! -f docker-compose.override.yml ]]; then
  cp docker-compose.override.example.yml docker-compose.override.yml
  echo "→ override angelegt — Domain prüfen: nano $INSTALL_DIR/docker-compose.override.yml"
fi

echo
echo "Docker/Firewall ok. Nächste Schritte:"
echo "  1. DNS A-Record: translate.ematchef.ch → diese Droplet-IP"
echo "  2. nano $INSTALL_DIR/environment"
echo "  3. nano $INSTALL_DIR/docker-compose.override.yml"
echo "  4. cd $INSTALL_DIR && docker compose up -d"
echo "  5. https://translate.ematchef.ch  (Admin-Login aus environment)"
