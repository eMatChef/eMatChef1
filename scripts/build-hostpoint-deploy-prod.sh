#!/usr/bin/env bash
# Erzeugt Hostpoint-Artefakte für Produktion:
#   deploy/hostpoint/prod/home  (ematchef.ch)
#   deploy/hostpoint/prod/app   (app.ematchef.ch, qr.ematchef.ch, devices.ematchef.ch — gleicher Document Root)
set -euo pipefail

ROOT="${EMATCHEF_REPO_ROOT:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || true
fi
# CI-Falle: ROOT zeigt auf .../frontend → sonst entsteht .../frontend/frontend.
if [[ "$(basename "$ROOT")" == frontend && -f "$ROOT/package.json" && ! -d "$ROOT/frontend" ]]; then
  ROOT="$(cd "$ROOT/.." && pwd)"
fi
if [[ -z "$ROOT" || ! -d "$ROOT/frontend" ]]; then
  echo "Konnte Repo-Wurzel nicht finden (frontend/ fehlt). Im Repo ausfuehren oder EMATCHEF_REPO_ROOT setzen." >&2
  exit 1
fi
OUT_BASE="$ROOT/deploy/hostpoint/prod"
FRONTEND="$ROOT/frontend"

mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

# Hauptdomain (ematchef.ch)
VITE_DEPLOY_VARIANT=home \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/home" --emptyOutDir

node "$ROOT/scripts/fetch-sitemap.mjs" "$OUT_BASE/home" "https://api.ematchef.ch"

# App-Subdomain (app.ematchef.ch), inkl. qr.* und devices.* (gleicher Hostpoint-Ordner)
VITE_DEPLOY_VARIANT=app \
VITE_QR_PUBLIC_HOST=qr.ematchef.ch \
VITE_DEVICES_HOST=devices.ematchef.ch \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/app" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/app/.htaccess"

for d in home app; do
  if [[ ! -f "$OUT_BASE/$d/index.html" ]]; then
    echo "Build unvollstaendig: fehlt $OUT_BASE/$d/index.html" >&2
    exit 1
  fi
done

echo "Fertig (prod): $OUT_BASE/home und $OUT_BASE/app"
