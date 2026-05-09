#!/usr/bin/env bash
#<<<<<<< fix/crowdin-config-prod
# Erzeugt Hostpoint-Artefakte für Produktion:
#   deploy/hostpoint/prod/home  (ematchef.ch)
#   deploy/hostpoint/prod/app   (app.ematchef.ch)
set -euo pipefail

ROOT="${EMATCHEF_REPO_ROOT:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || true
fi
if [[ -z "$ROOT" || ! -d "$ROOT/frontend" ]]; then
  echo "Konnte Repo-Wurzel nicht finden (frontend/ fehlt). Im Repo ausfuehren oder EMATCHEF_REPO_ROOT setzen." >&2
  exit 1
fi
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
#=======
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
#>>>>>>> develop
