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

fix_git_dir_permissions() {
  if [[ -x "${ROOT}/deploy/fix-git-permissions.sh" ]]; then
    EMATCHEF_PROD_ROOT="$ROOT" "${ROOT}/deploy/fix-git-permissions.sh"
    return
  fi
  local git_dir="${ROOT}/.git"
  [[ -d "${git_dir}/objects" ]] || return 0
  [[ -w "${git_dir}/objects" && -w "${git_dir}/logs" ]] && return 0
  local uid gid
  uid="$(id -u)"
  gid="$(id -g)"
  echo "==> .git Rechte reparieren (${uid}:${gid}) …"
  chown -R "${uid}:${gid}" "${git_dir}" 2>/dev/null && chmod -R u+rwX "${git_dir}" 2>/dev/null && return 0
  docker run --rm -u 0 -v "${git_dir}:/git" alpine:3.20 \
    sh -c "chown -R ${uid}:${gid} /git && chmod -R u+rwX /git"
}

git_fetch_deploy_branch() {
  # Expliziter Refspec — aktualisiert nur origin/<branch>, nie andere Remote-Refs.
  local refspec="+refs/heads/${BRANCH}:refs/remotes/origin/${BRANCH}"
  fix_git_dir_permissions
  if git fetch --no-tags origin "${refspec}"; then
    return 0
  fi
  echo "git fetch erneut nach Rechte-Fix …"
  fix_git_dir_permissions
  if git fetch --no-tags origin "${refspec}"; then
    return 0
  fi
  echo "git fetch --no-tags origin ${refspec} fehlgeschlagen." >&2
  echo "Manuell: sudo chown -R $(whoami):$(whoami) \"${ROOT}/.git\"" >&2
  return 1
}

case "$MODE" in
  reset)
    git_fetch_deploy_branch
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

# vendor/ liegt in benanntem Compose-Volume — nach root-Runs oder neuen composer.lock-Paketen
# schlägt composer install sonst fehl („vendor/mikehaertl does not exist and could not be created“).
fix_backend_vendor_volume_permissions() {
  local vendor_volume="${PROJECT}_backend_vendor"
  if ! docker volume inspect "$vendor_volume" >/dev/null 2>&1; then
    return 0
  fi
  echo "==> backend/vendor-Volume (${vendor_volume}): Rechte auf ${HOST_UID}:${HOST_GID} …"
  docker run --rm -u 0 \
    -v "${vendor_volume}:/vendor" \
    alpine:3.20 \
    sh -c "chown -R ${HOST_UID}:${HOST_GID} /vendor && chmod -R u+rwX /vendor"
}

# Develop/Prod-Override setzen oft security_opt: no-new-privileges — exec -u 0 scheitert dann.
# Cache daher bevorzugt auf dem Host (bind mount), nicht per root im laufenden Container.
clear_symfony_prod_cache_dir() {
  local cache_dir="${ROOT}/backend/var/cache/prod"
  echo "==> Symfony prod cache leeren …"
  fix_backend_var_permissions
  if [[ -e "$cache_dir" ]]; then
    if ! rm -rf "$cache_dir" 2>/dev/null; then
      echo "==> var/cache/prod: rm per Docker (root) auf dem Host-Volume …"
      docker run --rm -u 0 \
        -v "${ROOT}/backend/var:/var" \
        alpine:3.20 \
        sh -c "rm -rf /var/cache/prod"
      fix_backend_var_permissions
    fi
  fi
}

wait_for_backend_ready() {
  local waited=0
  local max_wait="${EMATCHEF_BACKEND_READY_TIMEOUT:-300}"
  echo "==> Warten auf Backend (Composer/Migrationen im Entrypoint) …"
  while (( waited < max_wait )); do
    if docker compose -p "$PROJECT" ps --status running --services backend 2>/dev/null | grep -qx backend; then
      if docker compose -p "$PROJECT" exec -T backend php bin/console about --env=prod >/dev/null 2>&1; then
        echo "==> Backend bereit (${waited}s)."
        return 0
      fi
    fi
    sleep 2
    waited=$((waited + 2))
  done
  echo "Fehler: Backend nach ${max_wait}s nicht bereit (Entrypoint/Composer?)." >&2
  docker compose -p "$PROJECT" logs backend --tail 80 >&2 || true
  return 1
}

reset_symfony_prod_cache() {
  clear_symfony_prod_cache_dir
  wait_for_backend_ready
  docker compose -p "$PROJECT" exec -T backend php bin/console cache:warmup --env=prod
}

fix_backend_var_permissions
fix_backend_vendor_volume_permissions

docker compose -p "$PROJECT" stop backend 2>/dev/null || true
docker compose -p "$PROJECT" rm -f backend 2>/dev/null || true

compose_up=(docker compose -p "$PROJECT" up -d)
if [[ "${EMATCHEF_COMPOSE_BUILD:-}" == "1" ]]; then
  compose_up+=(--build)
fi
"${compose_up[@]}" db backend

fix_backend_var_permissions

# Nach git reset: auf Entrypoint warten, dann Migrationen + Prod-Cache
if wait_for_backend_ready; then
  echo "==> Doctrine-Migrationen …"
  docker compose -p "$PROJECT" exec -T backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod
  reset_symfony_prod_cache
else
  exit 1
fi

echo ""
echo "OK: ${PROJECT} db + backend gestartet."
if [[ "${EMATCHEF_COMPOSE_BUILD:-}" != "1" ]]; then
  echo "(Ohne Image-Rebuild. Bei Dockerfile-/Base-Image-Änderung: EMATCHEF_COMPOSE_BUILD=1 $0 ${MODE})"
fi
echo "Logs:"
echo "  docker compose -p ${PROJECT} logs backend --tail 60"
