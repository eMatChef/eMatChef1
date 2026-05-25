#!/usr/bin/env bash
# Extrahiert alle requiredRoles-Zeilen aus dem Vue-Router nach docs/rechte/.
# Voraussetzung: ripgrep (rg) im PATH.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ROUTER="$ROOT/frontend/src/router/index.ts"
OUT="$ROOT/docs/rechte/ROUTEN-requiredRoles.autogen.md"

if [[ ! -f "$ROUTER" ]]; then
  echo "gen-docs-rollen-matrix: Router-Datei fehlt: $ROUTER" >&2
  exit 1
fi

mkdir -p "$(dirname "$OUT")"

if command -v rg >/dev/null 2>&1; then
  SCAN=(rg -n "requiredRoles:" "$ROUTER")
else
  SCAN=(grep -n "requiredRoles:" "$ROUTER")
fi

{
  echo "# Router: \`requiredRoles\` (autogeneriert)"
  echo
  echo "Generiert: $(date -Iseconds 2>/dev/null || date)"
  echo
  echo "Quelle: \`frontend/src/router/index.ts\`"
  echo
  echo "Nur Rohzeilen — Zuordnung zu Routen-Namen siehe \`ROLLEN-MATRIX.md\` oder die Datei im Editor mit Kontext."
  if command -v rg >/dev/null 2>&1; then
    echo "Extraktion: \`rg -n \"requiredRoles:\" …\`"
  else
    echo "Extraktion: \`grep -n \"requiredRoles:\" …\` (ripgrep nicht installiert)"
  fi
  echo
  echo '```text'
  "${SCAN[@]}" || true
  echo '```'
} >"$OUT"

echo "Wrote $OUT"
