#!/bin/sh
# Stimmt node_modules (Named Volume) mit package-lock.json ab — verhindert fehlende Pakete nach Dependency-Updates.
set -e
[ -f /app/package-lock.json ] || exit 0
cd /app

WANT=$(md5sum package-lock.json | awk '{print $1}')
GOT=""
[ -f node_modules/.deps-hash ] && GOT=$(cat node_modules/.deps-hash)

NEED_CI=0
if [ "$WANT" != "$GOT" ]; then
  NEED_CI=1
  echo "docker-entrypoint: package-lock.json changed, running npm ci..."
elif ! npm ls --depth=0 >/dev/null 2>&1; then
  # z. B. Named Volume veraltet: Hash passt noch, Pakete fehlen (neues Dependency im Repo)
  NEED_CI=1
  echo "docker-entrypoint: node_modules incomplete (npm ls failed), running npm ci..."
fi

[ "$NEED_CI" = 0 ] && exit 0

npm ci --no-audit --no-fund
mkdir -p node_modules
echo "$WANT" > node_modules/.deps-hash
