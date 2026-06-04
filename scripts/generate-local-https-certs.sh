#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CERT_DIR="$ROOT/docker/certs"
CERT_FILE="$CERT_DIR/ematchef.test.pem"
KEY_FILE="$CERT_DIR/ematchef.test-key.pem"

if ! command -v mkcert >/dev/null 2>&1; then
  echo "mkcert fehlt. Installation:"
  echo "  WSL/Ubuntu: sudo apt install mkcert libnss3-tools && mkcert -install"
  echo "  Windows:    choco install mkcert  (oder https://github.com/FiloSottile/mkcert)"
  exit 1
fi

mkdir -p "$CERT_DIR"
mkcert -install
mkcert -cert-file "$CERT_FILE" -key-file "$KEY_FILE" \
  "ematchef.test" "*.ematchef.test" \
  "app.ematchef.test" "qr.ematchef.test" "devices.ematchef.test" \
  "localhost" "127.0.0.1"

echo
echo "Zertifikate erstellt:"
echo "  $CERT_FILE"
echo "  $KEY_FILE"
echo
echo "Nächste Schritte:"
echo "  cp docker-compose.override.https.example.yml docker-compose.override.yml"
echo "  docker compose up -d nginx"
echo "  Browser: https://app.ematchef.test"
