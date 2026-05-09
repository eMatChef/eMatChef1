#!/usr/bin/env bash
# Legacy-Alias: Standard bleibt Produktion.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ROOT/scripts/build-hostpoint-deploy-prod.sh"
