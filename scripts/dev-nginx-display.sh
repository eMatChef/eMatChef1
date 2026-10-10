#!/usr/bin/env bash
# Lokal: Nginx mit zusätzlichem Virtual Host für display.ematchef.test neu anlegen.
# Nötig, wenn der laufende Nginx die Konfiguration eines Worktrees mountet, der den Host noch nicht kennt
# (sonst landet display.* im Default-Virtual-Host). Ändert nichts im anderen Worktree; es werden nur dessen
# Compose-Dateien gelesen und docker-compose.display.local.yml dieses Worktrees ergänzt.
#
#   scripts/dev-nginx-display.sh [<worktree-mit-aktivem-stack>]
#
# Voraussetzung: docker/nginx/display.local.conf und docker-compose.display.local.yml (lokal, nicht eingecheckt;
# siehe docs/devices/infoscreen.md). Volumes und Datenbank bleiben unberührt, nur der nginx-Container wird neu angelegt.
set -euo pipefail

HERE="$(cd "$(dirname "$0")/.." && pwd)"
STACK="${1:-$(docker inspect ematchef-nginx-1 --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}')}"
OVERRIDE="$HERE/docker-compose.display.local.yml"

[[ -f "$OVERRIDE" && -f "$HERE/docker/nginx/display.local.conf" ]] || { echo "Lokale Dateien fehlen (siehe Kopfkommentar)."; exit 1; }

FILES=(-f "$STACK/docker-compose.yml")
[[ -f "$STACK/docker-compose.override.yml" ]] && FILES+=(-f "$STACK/docker-compose.override.yml")
FILES+=(-f "$OVERRIDE")

docker run --rm -v "$HERE/docker/nginx/display.local.conf:/etc/nginx/conf.d/display.conf:ro" --entrypoint true nginx:alpine >/dev/null
docker compose -p ematchef "${FILES[@]}" up -d --no-deps nginx
docker exec ematchef-nginx-1 nginx -t
