#!/usr/bin/env bash
# .git/objects oder .git/logs gehören oft root (sudo git, alter Deploy) → git fetch scheitert.
# Nutzung: EMATCHEF_PROD_ROOT=/opt/ematchef/prod ./deploy/fix-git-permissions.sh

set -euo pipefail

ROOT="${1:-${EMATCHEF_PROD_ROOT:-.}}"
GIT_DIR="${ROOT}/.git"

if [[ ! -d "${GIT_DIR}/objects" ]]; then
  echo "Fehler: kein Git-Repo unter ${ROOT}" >&2
  exit 1
fi

uid="$(id -u)"
gid="$(id -g)"

# Verzeichnis kann -w sein, Unterordner in objects/ trotzdem root — echter Schreibtest.
if touch "${GIT_DIR}/objects/.deploy-write-test" 2>/dev/null; then
  rm -f "${GIT_DIR}/objects/.deploy-write-test"
  exit 0
fi

echo "==> .git/objects nicht beschreibbar — Rechte auf ${uid}:${gid} setzen …"

if chown -R "${uid}:${gid}" "${GIT_DIR}" 2>/dev/null; then
  chmod -R u+rwX "${GIT_DIR}" 2>/dev/null || true
else
  echo "==> chown ohne sudo fehlgeschlagen — Docker (root) auf .git …"
  docker run --rm -u 0 \
    -v "${GIT_DIR}:/git" \
    alpine:3.20 \
    sh -c "chown -R ${uid}:${gid} /git && chmod -R u+rwX /git"
fi

touch "${GIT_DIR}/objects/.deploy-write-test" 2>/dev/null || {
  echo "Fehler: .git/objects nach Rechte-Fix weiterhin nicht beschreibbar." >&2
  exit 1
}
rm -f "${GIT_DIR}/objects/.deploy-write-test"
