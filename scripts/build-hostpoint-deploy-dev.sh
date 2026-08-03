#!/usr/bin/env bash
# Erzeugt Hostpoint-Artefakte für Development:
#   deploy/hostpoint/dev/home  (dev.ematchef.ch)
#   deploy/hostpoint/dev/app   (app-dev.ematchef.ch, qr-dev.*, devices-dev.* — gleicher Document Root)
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
OUT_BASE="$ROOT/deploy/hostpoint/dev"
FRONTEND="$ROOT/frontend"

mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

VITE_APP_VERSION="$(node -p "require('$FRONTEND/package.json').version")"
VITE_APP_GIT_SHA="$(git -C "$ROOT" rev-parse --short=7 HEAD 2>/dev/null || true)"
export VITE_APP_VERSION VITE_APP_GIT_SHA

# Dev-Hauptdomain (dev.ematchef.ch) — npm --prefix: kein cd (CI-sicher)
VITE_DEPLOY_VARIANT=home \
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/home" --emptyOutDir

# Dev nicht indexieren — keine Sitemap von der API holen
VITE_DEPLOY_VARIANT=app \
VITE_API_BASE=https://api-dev.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://dev.ematchef.ch \
VITE_APP_ORIGIN=https://app-dev.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr-dev.ematchef.ch \
VITE_DEVICES_HOST=devices-dev.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/app" --emptyOutDir

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"
cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/app/.htaccess"

for d in home app; do
  if [[ ! -f "$OUT_BASE/$d/index.html" ]]; then
    echo "Build unvollstaendig: fehlt $OUT_BASE/$d/index.html" >&2
    exit 1
  fi
done

echo "Fertig (dev): $OUT_BASE/home und $OUT_BASE/app"
