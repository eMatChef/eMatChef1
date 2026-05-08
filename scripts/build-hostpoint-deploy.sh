#!/usr/bin/env bash
# Legacy entrypoint: behaelt den bisherigen Dateinamen bei und baut PROD.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ROOT/scripts/build-hostpoint-deploy-prod.sh"
