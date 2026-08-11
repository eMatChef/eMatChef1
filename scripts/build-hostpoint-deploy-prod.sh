#!/usr/bin/env bash
# Erzeugt Hostpoint-Artefakt nur für Marketing (Prod):
#   deploy/hostpoint/prod/home  (ematchef.ch / www)
# App/QR/Devices liegen auf dem Prod-Droplet (Caddy), nicht mehr per FTP.
set -euo pipefail

ROOT="${EMATCHEF_REPO_ROOT:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || true
fi
if [[ "$(basename "$ROOT")" == frontend && -f "$ROOT/package.json" && ! -d "$ROOT/frontend" ]]; then
  ROOT="$(cd "$ROOT/.." && pwd)"
fi
if [[ -z "$ROOT" || ! -d "$ROOT/frontend" ]]; then
  echo "Konnte Repo-Wurzel nicht finden (frontend/ fehlt). Im Repo ausfuehren oder EMATCHEF_REPO_ROOT setzen." >&2
  exit 1
fi
OUT_BASE="$ROOT/deploy/hostpoint/prod"
FRONTEND="$ROOT/frontend"

mkdir -p "$OUT_BASE/home"

VITE_APP_VERSION="$(node -p "require('$FRONTEND/package.json').version")"
VITE_APP_GIT_SHA="$(git -C "$ROOT" rev-parse --short=7 HEAD 2>/dev/null || true)"
export VITE_APP_VERSION VITE_APP_GIT_SHA

echo "==> Build Hostpoint home (prod) → $OUT_BASE/home"
VITE_DEPLOY_VARIANT=home \
VITE_API_BASE=https://api.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://ematchef.ch \
VITE_APP_ORIGIN=https://app.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr.ematchef.ch \
VITE_DEVICES_HOST=devices.ematchef.ch \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/home" --emptyOutDir

node "$ROOT/scripts/fetch-sitemap.mjs" "$OUT_BASE/home" "https://api.ematchef.ch"

cp "$ROOT/scripts/hostpoint-spa.htaccess" "$OUT_BASE/home/.htaccess"

if [[ ! -f "$OUT_BASE/home/index.html" ]]; then
  echo "Build unvollstaendig: fehlt $OUT_BASE/home/index.html" >&2
  exit 1
fi

echo "Fertig (prod marketing): $OUT_BASE/home"
