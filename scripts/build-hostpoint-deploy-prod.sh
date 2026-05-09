#!/usr/bin/env bash
# Erzeugt deploy/hostpoint/ematchef.ch und deploy/hostpoint/app.ematchef.ch
# (Hauptdomain vs. App+QR gemäß frontend/.env.production).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"
mkdir -p "$ROOT/deploy/hostpoint"

# Build fuer Hauptdomain
npm run build -- --outDir "$ROOT/deploy/hostpoint/ematchef.ch" --emptyOutDir

# Build fuer App-Subdomain (mit expliziter QR-Subdomain)
VITE_QR_PUBLIC_HOST=qr.ematchef.ch \
npm run build -- --outDir "$ROOT/deploy/hostpoint/app.ematchef.ch" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$ROOT/deploy/hostpoint/ematchef.ch/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$ROOT/deploy/hostpoint/app.ematchef.ch/.htaccess"

echo "Fertig (prod): $ROOT/deploy/hostpoint/ematchef.ch und $ROOT/deploy/hostpoint/app.ematchef.ch"
