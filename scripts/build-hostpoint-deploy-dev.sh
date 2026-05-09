#!/usr/bin/env bash
# Erzeugt Hostpoint-Artefakte für Development:
#   deploy/hostpoint/dev/home  (dev.ematchef.ch)
#   deploy/hostpoint/dev/app   (app-dev.ematchef.ch)
set -euo pipefail

# Unabhaengig vom aktuellen Arbeitsverzeichnis (z. B. CI mit working-directory: frontend)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
OUT_BASE="$ROOT/deploy/hostpoint/dev"

cd "$ROOT/frontend"
mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

# Dev-Hauptdomain (dev.ematchef.ch)
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
npm run build -- --outDir "$OUT_BASE/home" --emptyOutDir

# Dev-App-Subdomain (app-dev.ematchef.ch)
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
npm run build -- --outDir "$OUT_BASE/app" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/app/.htaccess"

echo "Fertig (dev): $OUT_BASE/home und $OUT_BASE/app"
