#!/bin/sh
set -e
uid="${HOST_UID:-1000}"
gid="${HOST_GID:-1000}"
# Named Volume node_modules ist oft root vom Image-Build – Vite braucht Schreibrechte (.vite/deps)
if [ "$(id -u)" = 0 ]; then
  if [ -d /app/node_modules ]; then
    chown -R "${uid}:${gid}" /app/node_modules
  fi
  su-exec "${uid}:${gid}" /usr/local/bin/docker-npm-sync.sh
  exec su-exec "${uid}:${gid}" "$@"
fi
/usr/local/bin/docker-npm-sync.sh
exec "$@"
