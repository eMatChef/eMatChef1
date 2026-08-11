#!/usr/bin/env bash
# Baut nur die App-SPA für Auslieferung auf dem API-Droplet (Caddy), nicht Hostpoint-Marketing.
# Usage: bash scripts/build-droplet-app.sh [develop|staging|prod]
# Output: deploy/droplet/<env>/app/
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

OUT="$ROOT/deploy/droplet/${ENV_NAME}/app"
FRONTEND="$ROOT/frontend"
mkdir -p "$OUT"

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

echo "==> Build droplet app ($ENV_NAME) → $OUT"
VITE_DEPLOY_VARIANT=app \
VITE_API_BASE="$API" \
VITE_MAIN_SITE_ORIGIN="$MAIN" \
VITE_APP_ORIGIN="$APP" \
VITE_QR_PUBLIC_HOST="$QR" \
VITE_DEVICES_HOST="$DEVICES" \
VITE_SHOW_DEV_BANNER="$BANNER" \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT" --emptyOutDir

# Caddy SPA: try_files — keine Apache-.htaccess nötig
if [[ ! -f "$OUT/index.html" ]]; then
  echo "Build unvollstaendig: fehlt $OUT/index.html" >&2
  exit 1
fi

echo "Fertig: $OUT"
