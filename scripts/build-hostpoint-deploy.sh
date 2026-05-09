#!/usr/bin/env bash
#<<<<<<< fix/crowdin-config-prod
# Legacy-Alias: Standard bleibt Produktion.
#=======
# Legacy entrypoint: behaelt den bisherigen Dateinamen bei und baut PROD.
#>>>>>>> develop
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ROOT/scripts/build-hostpoint-deploy-prod.sh"
