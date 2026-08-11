#!/usr/bin/env bash
# Kompatibilitaet: baut home + app (siehe build-droplet-frontend.sh).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec bash "$ROOT/scripts/build-droplet-frontend.sh" "$@"
