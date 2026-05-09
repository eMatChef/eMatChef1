#!/usr/bin/env bash
#<<<<<<< fix/crowdin-config-prod
# Legacy-Alias: Standard bleibt Produktion.
#=======
# Legacy entrypoint: behaelt den bisherigen Dateinamen bei und baut PROD.
#>>>>>>> develop
set -euo pipefail

ROOT="${EMATCHEF_REPO_ROOT:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || true
fi
if [[ -z "$ROOT" || ! -d "$ROOT/scripts" ]]; then
  echo "Konnte Repo-Wurzel nicht finden. Im Repo ausfuehren oder EMATCHEF_REPO_ROOT setzen." >&2
  exit 1
fi
exec "$ROOT/scripts/build-hostpoint-deploy-prod.sh"
