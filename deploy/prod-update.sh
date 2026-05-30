#!/usr/bin/env bash
# Ein Befehl auf dem API-Server: Code aktualisieren + db/backend starten (Rebuild nur mit EMATCHEF_COMPOSE_BUILD=1).
#
# Nutzung (z. B. /opt/ematchef/prod):
#   chmod +x deploy/prod-update.sh
#   EMATCHEF_PROD_ROOT=/opt/ematchef/prod ./deploy/prod-update.sh reset
#
# Modi:
#   reset  — git fetch + reset --hard origin/<branch> (Server = exakt wie Remote, typisch für Droplet)
#   pull   — git pull (wenn du lokal committest und mergen willst)
#   up     — nur Docker (kein Git), z. B. nach manuellem git
#
# Umgebungsvariablen (optional):
#   EMATCHEF_PROD_ROOT   Standard: /opt/ematchef/prod
#   EMATCHEF_GIT_BRANCH  Standard: prod
#   COMPOSE_PROJECT_NAME Standard: ematchef-prod
#   EMATCHEF_COMPOSE_BUILD=1  — docker compose … --build (langsam; nur bei Dockerfile/PHP-Base-Änderung)
#   EMATCHEF_GIT_SSH_IDENTITY — privater Deploy-Key für git@github.com (Pfad, ~ erlaubt). Z. B. für GitHub
#     Actions (non-interactive SSH): sonst „Permission denied (publickey)“ bei git fetch.

set -euo pipefail

ROOT="${EMATCHEF_PROD_ROOT:-/opt/ematchef/prod}"
BRANCH="${EMATCHEF_GIT_BRANCH:-prod}"
PROJECT="${COMPOSE_PROJECT_NAME:-ematchef-prod}"
MODE="${1:-reset}"

cd "$ROOT"

if [[ -n "${EMATCHEF_GIT_SSH_IDENTITY:-}" ]]; then
  _git_ssh_id="${EMATCHEF_GIT_SSH_IDENTITY/#\~/$HOME}"
  if [[ ! -f "$_git_ssh_id" ]]; then
    echo "Fehler: EMATCHEF_GIT_SSH_IDENTITY gesetzt, aber Datei fehlt: ${_git_ssh_id}" >&2
    exit 1
  fi
  export GIT_SSH_COMMAND="ssh -i ${_git_ssh_id} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
fi

case "$MODE" in
  reset)
    git fetch origin
    git reset --hard "origin/${BRANCH}"
    # Skript neu starten: git reset überschreibt diese Datei, laufendes Bash behält sonst alte Version (offene Inode).
    if [[ -z "${EMATCHEF_DEPLOY_REEXEC:-}" ]]; then
      export EMATCHEF_DEPLOY_REEXEC=1
      exec "$0" "$@"
    fi
    ;;
  pull)
    git pull origin "$BRANCH"
    if [[ -z "${EMATCHEF_DEPLOY_REEXEC:-}" ]]; then
      export EMATCHEF_DEPLOY_REEXEC=1
      exec "$0" "$@"
    fi
    ;;
  up) ;;
  *)
    echo "Usage: $0 [reset|pull|up]" >&2
    exit 1
    ;;
esac

if [[ -f .env ]] && [[ ! -r .env ]]; then
  echo "Fehler: .env ist für Benutzer $(whoami) nicht lesbar (häufig: als root mit chmod 600 angelegt)." >&2
  echo "Fix auf dem Server: sudo chown $(whoami):$(whoami) \"$ROOT/.env\" && chmod 600 \"$ROOT/.env\"" >&2
  exit 1
fi

# HOST_UID/GID: Compose-.env auf dem Server hat Vorrang (oft 1000:1000), sonst Deploy-User
if [[ -f .env ]]; then
  # shellcheck disable=SC1091
  set -a && source .env && set +a
fi
export HOST_UID="${HOST_UID:-$(id -u)}"
export HOST_GID="${HOST_GID:-$(id -g)}"

# backend/var muss dem Container-USER gehören (kein sudo — CI/SSH ist non-interactive)
fix_backend_var_permissions() {
  [[ -d backend/var ]] || return 0
  if chown -R "${HOST_UID}:${HOST_GID}" backend/var 2>/dev/null; then
    chmod -R u+rwX backend/var 2>/dev/null || true
    return 0
  fi
  echo "==> backend/var: Rechte per Docker (root) auf ${HOST_UID}:${HOST_GID} …"
  docker run --rm -u 0 \
    -v "${ROOT}/backend/var:/var" \
    alpine:3.20 \
    sh -c "chown -R ${HOST_UID}:${HOST_GID} /var && chmod -R u+rwX /var"
}

reset_symfony_prod_cache() {
  echo "==> Symfony prod cache leeren …"
  if docker compose -p "$PROJECT" exec -T backend rm -rf var/cache/prod 2>/dev/null; then
    :
  else
    echo "==> var/cache/prod: rm im Container als root …"
    docker compose -p "$PROJECT" exec -T -u 0 backend sh -ec "
      rm -rf var/cache/prod
      mkdir -p var/cache var/log var/app
      chown -R ${HOST_UID}:${HOST_GID} var
      chmod -R u+rwX var
    "
  fi
  docker compose -p "$PROJECT" exec -T backend php bin/console cache:warmup --env=prod
}

fix_backend_var_permissions

# Backend zuerst stoppen — vermeidet „address already in use“ beim Recreate
docker compose -p "$PROJECT" stop backend 2>/dev/null || true
docker compose -p "$PROJECT" rm -f backend 2>/dev/null || true

compose_up=(docker compose -p "$PROJECT" up -d)
if [[ "${EMATCHEF_COMPOSE_BUILD:-}" == "1" ]]; then
  compose_up+=(--build)
fi
"${compose_up[@]}" db backend

fix_backend_var_permissions

# Nach git reset: Migrationen + DI-Container neu bauen
if docker compose -p "$PROJECT" ps --status running backend 2>/dev/null | grep -q backend; then
  echo "==> Doctrine-Migrationen …"
  docker compose -p "$PROJECT" exec -T backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod
  reset_symfony_prod_cache
fi

echo ""
echo "OK: ${PROJECT} db + backend gestartet."
if [[ "${EMATCHEF_COMPOSE_BUILD:-}" != "1" ]]; then
  echo "(Ohne Image-Rebuild. Bei Dockerfile-/Base-Image-Änderung: EMATCHEF_COMPOSE_BUILD=1 $0 ${MODE})"
fi
echo "Logs:"
echo "  docker compose -p ${PROJECT} logs backend --tail 60"
