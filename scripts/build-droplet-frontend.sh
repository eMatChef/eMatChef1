#!/usr/bin/env bash
# Baut Marketing (home) + App-SPA für Auslieferung auf dem API-Droplet (Caddy).
# Usage: bash scripts/build-droplet-frontend.sh [develop|staging|prod]
# Output: deploy/droplet/<env>/{home,app}/
set -euo pipefail

ENV_NAME="${1:-develop}"
case "$ENV_NAME" in
  develop|staging|prod) ;;
  *)
    echo "Usage: $0 [develop|staging|prod]" >&2
    exit 1
    ;;
esac

ROOT="${EMATCHEF_REPO_ROOT:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || true
fi
if [[ "$(basename "$ROOT")" == frontend && -f "$ROOT/package.json" && ! -d "$ROOT/frontend" ]]; then
  ROOT="$(cd "$ROOT/.." && pwd)"
fi
if [[ -z "$ROOT" || ! -d "$ROOT/frontend" ]]; then
  echo "Konnte Repo-Wurzel nicht finden (frontend/ fehlt)." >&2
  exit 1
fi

OUT_BASE="$ROOT/deploy/droplet/${ENV_NAME}"
FRONTEND="$ROOT/frontend"
mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

VITE_APP_VERSION="$(node -p "require('$FRONTEND/package.json').version")"
VITE_APP_GIT_SHA="$(git -C "$ROOT" rev-parse --short=7 HEAD 2>/dev/null || true)"

case "$ENV_NAME" in
  develop)
    API=https://api-dev.ematchef.ch
    MAIN=https://dev.ematchef.ch
    APP=https://app-dev.ematchef.ch
    QR=qr-dev.ematchef.ch
    DEVICES=devices-dev.ematchef.ch
    BANNER=1
    ;;
  staging)
    API=https://api-staging.ematchef.ch
    MAIN=https://staging.ematchef.ch
    APP=https://app-staging.ematchef.ch
    QR=qr-staging.ematchef.ch
    DEVICES=devices-staging.ematchef.ch
    BANNER=1
    ;;
  prod)
    API=https://api.ematchef.ch
    MAIN=https://ematchef.ch
    APP=https://app.ematchef.ch
    QR=qr.ematchef.ch
    DEVICES=devices.ematchef.ch
    BANNER=0
    ;;
esac

echo "==> Build droplet home ($ENV_NAME) → $OUT_BASE/home"
VITE_DEPLOY_VARIANT=home \
VITE_API_BASE="$API" \
VITE_MAIN_SITE_ORIGIN="$MAIN" \
VITE_APP_ORIGIN="$APP" \
VITE_QR_PUBLIC_HOST="$QR" \
VITE_DEVICES_HOST="$DEVICES" \
VITE_SHOW_DEV_BANNER="$BANNER" \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/home" --emptyOutDir

echo "==> Build droplet app ($ENV_NAME) → $OUT_BASE/app"
VITE_DEPLOY_VARIANT=app \
VITE_API_BASE="$API" \
VITE_MAIN_SITE_ORIGIN="$MAIN" \
VITE_APP_ORIGIN="$APP" \
VITE_QR_PUBLIC_HOST="$QR" \
VITE_DEVICES_HOST="$DEVICES" \
VITE_SHOW_DEV_BANNER="$BANNER" \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/app" --emptyOutDir

for d in home app; do
  if [[ ! -f "$OUT_BASE/$d/index.html" ]]; then
    echo "Build unvollstaendig: fehlt $OUT_BASE/$d/index.html" >&2
    exit 1
  fi
done

echo "Fertig: $OUT_BASE/home und $OUT_BASE/app"
