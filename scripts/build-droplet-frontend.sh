#!/usr/bin/env bash
# Baut Frontend für Droplet (Caddy).
# develop/staging: nur App (kein Marketing) → deploy/droplet/<env>/app/
# prod: home + app → deploy/droplet/prod/{home,app}/
# Usage: bash scripts/build-droplet-frontend.sh [develop|staging|prod]
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
mkdir -p "$OUT_BASE/app"

VITE_APP_VERSION="$(node -p "require('$FRONTEND/package.json').version")"
VITE_APP_GIT_SHA="$(git -C "$ROOT" rev-parse --short=7 HEAD 2>/dev/null || true)"

case "$ENV_NAME" in
  develop)
    # Hierarchie: app.dev / qr.dev / devices.dev — API bleibt api-dev
    API=https://api-dev.ematchef.ch
    MAIN=https://ematchef.ch
    APP=https://app.dev.ematchef.ch
    QR=qr.dev.ematchef.ch
    DEVICES=devices.dev.ematchef.ch
    BANNER=1
    BUILD_HOME=0
    ;;
  staging)
    API=https://api-staging.ematchef.ch
    MAIN=https://ematchef.ch
    APP=https://app.staging.ematchef.ch
    QR=qr.staging.ematchef.ch
    DEVICES=devices.staging.ematchef.ch
    BANNER=1
    BUILD_HOME=0
    ;;
  prod)
    API=https://api.ematchef.ch
    MAIN=https://ematchef.ch
    APP=https://app.ematchef.ch
    QR=qr.ematchef.ch
    DEVICES=devices.ematchef.ch
    BANNER=0
    BUILD_HOME=1
    ;;
esac

build_variant() {
  local variant="$1"
  local out="$2"
  echo "==> Build droplet $variant ($ENV_NAME) → $out"
  mkdir -p "$out"
  VITE_DEPLOY_VARIANT="$variant" \
  VITE_API_BASE="$API" \
  VITE_MAIN_SITE_ORIGIN="$MAIN" \
  VITE_APP_ORIGIN="$APP" \
  VITE_QR_PUBLIC_HOST="$QR" \
  VITE_DEVICES_HOST="$DEVICES" \
  VITE_SHOW_DEV_BANNER="$BANNER" \
  VITE_APP_VERSION="$VITE_APP_VERSION" \
  VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
  npm --prefix "$FRONTEND" run build -- --outDir "$out" --emptyOutDir
  if [[ ! -f "$out/index.html" ]]; then
    echo "Build unvollstaendig: fehlt $out/index.html" >&2
    exit 1
  fi
}

if [[ "$BUILD_HOME" == "1" ]]; then
  mkdir -p "$OUT_BASE/home"
  build_variant home "$OUT_BASE/home"
fi
build_variant app "$OUT_BASE/app"

echo "Fertig: $OUT_BASE/app$([ "$BUILD_HOME" = 1 ] && echo " und $OUT_BASE/home")"
