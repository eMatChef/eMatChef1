#!/usr/bin/env bash
# Baut die öffentliche Nutzerhilfe (VitePress) fürs Prod-Droplet.
# Ausgabe: deploy/droplet/prod/docs/  → rsync nach /var/www/ematchef-docs-prod
set -euo pipefail

ROOT="${EMATCHEF_REPO_ROOT:-}"
if [[ -z "$ROOT" ]]; then
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || true
fi
if [[ -z "$ROOT" || ! -d "$ROOT/user-docs" ]]; then
  echo "Konnte Repo-Wurzel nicht finden (user-docs/ fehlt)." >&2
  exit 1
fi

OUT="$ROOT/deploy/droplet/prod/docs"
DOCS="$ROOT/user-docs"
mkdir -p "$OUT"

echo "==> Build user-docs → $OUT"
npm --prefix "$DOCS" run build -- --outDir "$OUT"

if [[ ! -f "$OUT/index.html" ]]; then
  echo "Build unvollstaendig: fehlt $OUT/index.html" >&2
  exit 1
fi
if [[ ! -f "$OUT/robots.txt" ]]; then
  echo "Build unvollstaendig: fehlt $OUT/robots.txt" >&2
  exit 1
fi

echo "Fertig: $OUT"
