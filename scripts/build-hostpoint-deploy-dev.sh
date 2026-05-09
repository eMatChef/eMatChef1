#!/usr/bin/env bash
# fix/crowdin-config-prod
# Erzeugt Hostpoint-Artefakte für Development:
#   deploy/hostpoint/dev/home  (dev.ematchef.ch)
#   deploy/hostpoint/dev/app   (app-dev.ematchef.ch)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_BASE="$ROOT/deploy/hostpoint/dev"

cd "$ROOT/frontend"
mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

# Dev-Hauptdomain (dev.ematchef.ch)
#=======
# Erzeugt deploy/hostpoint/dev.ematchef.ch und deploy/hostpoint/app-dev.ematchef.ch
# fuer die Dev-Umgebung (dev/app-dev/qr-dev/api-dev).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"
mkdir -p "$ROOT/deploy/hostpoint"

# Build fuer dev.ematchef.ch (Marketing/Entry auf Dev)
#>>>>>>> develop
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
#<<<<<<< fix/crowdin-config-prod
npm run build -- --outDir "$OUT_BASE/home" --emptyOutDir

# Dev-App-Subdomain (app-dev.ematchef.ch)
#=======
npm run build -- --outDir "$ROOT/deploy/hostpoint/dev.ematchef.ch" --emptyOutDir

# Build fuer app-dev.ematchef.ch (App auf Dev)
#>>>>>>> develop
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
#<<<<<<< fix/crowdin-config-prod
npm run build -- --outDir "$OUT_BASE/app" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/app/.htaccess"

echo "Fertig (dev): $OUT_BASE/home und $OUT_BASE/app"
#=======
npm run build -- --outDir "$ROOT/deploy/hostpoint/app-dev.ematchef.ch" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$ROOT/deploy/hostpoint/dev.ematchef.ch/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$ROOT/deploy/hostpoint/app-dev.ematchef.ch/.htaccess"

echo "Fertig (dev): $ROOT/deploy/hostpoint/dev.ematchef.ch und $ROOT/deploy/hostpoint/app-dev.ematchef.ch"
#>>>>>>> develop
