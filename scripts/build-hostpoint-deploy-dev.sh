#!/usr/bin/env bash
# Erzeugt deploy/hostpoint/dev.ematchef.ch und deploy/hostpoint/app-dev.ematchef.ch
# fuer die Dev-Umgebung (dev/app-dev/qr-dev/api-dev).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"
mkdir -p "$ROOT/deploy/hostpoint"

# Build fuer dev.ematchef.ch (Marketing/Entry auf Dev)
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
npm run build -- --outDir "$ROOT/deploy/hostpoint/dev.ematchef.ch" --emptyOutDir

# Build fuer app-dev.ematchef.ch (App auf Dev)
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
npm run build -- --outDir "$ROOT/deploy/hostpoint/app-dev.ematchef.ch" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$ROOT/deploy/hostpoint/dev.ematchef.ch/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$ROOT/deploy/hostpoint/app-dev.ematchef.ch/.htaccess"

echo "Fertig (dev): $ROOT/deploy/hostpoint/dev.ematchef.ch und $ROOT/deploy/hostpoint/app-dev.ematchef.ch"
