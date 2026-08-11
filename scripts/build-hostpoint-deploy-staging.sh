#!/usr/bin/env bash
# Erzeugt Hostpoint-Artefakte für Staging (Notfall-FTP, Basic Auth):
#   deploy/hostpoint/staging/home  (optional)
#   deploy/hostpoint/staging/app   (app.staging / qr.staging / devices.staging — gleicher Document Root)
#
# Auth (Hostpoint):
#   STAGING_BASIC_AUTH_HTPASSWD_PATH_HOME / _APP = AuthUserFile (z. B. /home/…/.htpasswds/htpasswd.… aus dem Panel)
#   User/Pass im Hostpoint-Passwortschutz pflegen — CI schreibt keine DocRoot-.htpasswd, wenn Pfad unter .htpasswds liegt.
#   Fallback: relative ".htpasswd" + STAGING_BASIC_AUTH_USER/PASSWORD → Apache-MD5 (.htpasswd im Upload)
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
OUT_BASE="$ROOT/deploy/hostpoint/staging"
FRONTEND="$ROOT/frontend"
HTACCESS_TEMPLATE="$ROOT/scripts/hostpoint-staging.htaccess"

mkdir -p "$OUT_BASE/home" "$OUT_BASE/app"

VITE_APP_VERSION="$(node -p "require('$FRONTEND/package.json').version")"
VITE_APP_GIT_SHA="$(git -C "$ROOT" rev-parse --short=7 HEAD 2>/dev/null || true)"
export VITE_APP_VERSION VITE_APP_GIT_SHA

# Staging-Hauptdomain (staging.ematchef.ch)
VITE_DEPLOY_VARIANT=home \
VITE_API_BASE=https://api.staging.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://ematchef.ch \
VITE_APP_ORIGIN=https://app.staging.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr.staging.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/home" --emptyOutDir

# Staging-App (app-staging), inkl. qr-staging / devices-staging
VITE_DEPLOY_VARIANT=app \
VITE_API_BASE=https://api.staging.ematchef.ch \
VITE_MAIN_SITE_ORIGIN=https://ematchef.ch \
VITE_APP_ORIGIN=https://app.staging.ematchef.ch \
VITE_QR_PUBLIC_HOST=qr.staging.ematchef.ch \
VITE_DEVICES_HOST=devices.staging.ematchef.ch \
VITE_SHOW_DEV_BANNER=1 \
VITE_APP_VERSION="$VITE_APP_VERSION" \
VITE_APP_GIT_SHA="$VITE_APP_GIT_SHA" \
npm --prefix "$FRONTEND" run build -- --outDir "$OUT_BASE/app" --emptyOutDir

write_htaccess() {
  local dest="$1"
  local auth_file="$2"
  sed "s|__AUTH_USER_FILE__|${auth_file}|g" "$HTACCESS_TEMPLATE" >"$dest"
}

is_hostpoint_htpasswds() {
  [[ "$1" == *".htpasswds"* ]]
}

AUTH_HOME="${STAGING_BASIC_AUTH_HTPASSWD_PATH_HOME:-.htpasswd}"
AUTH_APP="${STAGING_BASIC_AUTH_HTPASSWD_PATH_APP:-.htpasswd}"
write_htaccess "$OUT_BASE/home/.htaccess" "$AUTH_HOME"
write_htaccess "$OUT_BASE/app/.htaccess" "$AUTH_APP"

# Hostpoint verwaltet User unter .htpasswds — keine DocRoot-.htpasswd erzeugen/hochladen
if is_hostpoint_htpasswds "$AUTH_HOME" && is_hostpoint_htpasswds "$AUTH_APP"; then
  echo "Basic Auth: Hostpoint-.htpasswds Pfade — keine DocRoot-.htpasswd (Panel-User)."
elif [[ -n "${STAGING_BASIC_AUTH_USER:-}" && -n "${STAGING_BASIC_AUTH_PASSWORD:-}" ]]; then
  if ! command -v htpasswd >/dev/null 2>&1; then
    echo "htpasswd fehlt (apache2-utils / httpd-tools). Fuer Basic Auth installieren oder in CI bereitstellen." >&2
    exit 1
  fi
  # -nbm: Apache MD5 (apr1) — bcrypt (-B) liefert auf Hostpoint oft HTTP 500
  HASH_LINE="$(htpasswd -nbm "$STAGING_BASIC_AUTH_USER" "$STAGING_BASIC_AUTH_PASSWORD")"
  if ! is_hostpoint_htpasswds "$AUTH_HOME"; then
    printf '%s\n' "$HASH_LINE" >"$OUT_BASE/home/.htpasswd"
    chmod 644 "$OUT_BASE/home/.htpasswd"
  fi
  if ! is_hostpoint_htpasswds "$AUTH_APP"; then
    printf '%s\n' "$HASH_LINE" >"$OUT_BASE/app/.htpasswd"
    chmod 644 "$OUT_BASE/app/.htpasswd"
  fi
  echo "Basic Auth: DocRoot-.htpasswd erzeugt (user=${STAGING_BASIC_AUTH_USER}, Apache-MD5)"
else
  echo "Hinweis: Keine Hostpoint-.htpasswds-Pfade und keine STAGING_BASIC_AUTH_USER/PASSWORD — Auth ggf. unvollstaendig." >&2
fi

for d in home app; do
  if [[ ! -f "$OUT_BASE/$d/index.html" ]]; then
    echo "Build unvollstaendig: fehlt $OUT_BASE/$d/index.html" >&2
    exit 1
  fi
  if [[ ! -f "$OUT_BASE/$d/.htaccess" ]]; then
    echo "Build unvollstaendig: fehlt $OUT_BASE/$d/.htaccess" >&2
    exit 1
  fi
done

echo "Fertig (staging): $OUT_BASE/home und $OUT_BASE/app"
