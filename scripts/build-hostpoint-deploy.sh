#!/usr/bin/env bash
# Legacy-Alias: Standard bleibt Produktion.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
exec "$ROOT/scripts/build-hostpoint-deploy-prod.sh"
