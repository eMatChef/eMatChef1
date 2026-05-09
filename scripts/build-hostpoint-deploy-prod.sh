#!/usr/bin/env bash
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
FRONTEND="$ROOT/frontend"

mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

# Hauptdomain (ematchef.ch)
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/home" --emptyOutDir

# App-Subdomain (app.ematchef.ch), inkl. QR-Host
VITE_QR_PUBLIC_HOST=qr.ematchef.ch \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/app" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/app/.htaccess"

echo "Fertig (prod): $OUT_BASE/home und $OUT_BASE/app"
