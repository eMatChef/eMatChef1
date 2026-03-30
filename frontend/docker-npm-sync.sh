#!/bin/sh
# Stimmt node_modules (Named Volume) mit package-lock.json ab — verhindert fehlende Pakete nach Dependency-Updates.
set -e
[ -f /app/package-lock.json ] || exit 0
WANT=$(md5sum /app/package-lock.json | awk '{print $1}')
GOT=""
[ -f /app/node_modules/.deps-hash ] && GOT=$(cat /app/node_modules/.deps-hash)
[ "$WANT" = "$GOT" ] && exit 0
echo "docker-entrypoint: package-lock.json changed, running npm ci..."
cd /app
npm ci --no-audit --no-fund
mkdir -p /app/node_modules
echo "$WANT" > /app/node_modules/.deps-hash
