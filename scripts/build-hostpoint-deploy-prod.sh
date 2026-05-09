#!/usr/bin/env bash
# Erzeugt Hostpoint-Artefakte für Produktion:
#   deploy/hostpoint/prod/home  (ematchef.ch)
#   deploy/hostpoint/prod/app   (app.ematchef.ch)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_BASE="$ROOT/deploy/hostpoint/prod"

cd "$ROOT/frontend"
mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

# Hauptdomain (ematchef.ch)
npm run build -- --outDir "$OUT_BASE/home" --emptyOutDir

# App-Subdomain (app.ematchef.ch), inkl. QR-Host
VITE_QR_PUBLIC_HOST=qr.ematchef.ch \
npm run build -- --outDir "$OUT_BASE/app" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/app/.htaccess"

echo "Fertig (prod): $OUT_BASE/home und $OUT_BASE/app"
