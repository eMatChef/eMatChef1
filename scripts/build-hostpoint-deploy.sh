#!/usr/bin/env bash
#<<<<<<< fix/crowdin-config-prod
# Legacy-Alias: Standard bleibt Produktion.
#=======
# Legacy entrypoint: behaelt den bisherigen Dateinamen bei und baut PROD.
#>>>>>>> develop
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
exec "$ROOT/scripts/build-hostpoint-deploy-prod.sh"
