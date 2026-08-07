#!/usr/bin/env bash
# Prüft Locale-JSON: gültiges JSON, erwartete Dateien vorhanden, keine Orphans.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOCALE_DIR="$ROOT/frontend/src/locales"

EXPECTED=(
  de de-pfadi de-cevi de-jubla
  en
  fr fr-pfadi fr-cevi fr-jubla
  it it-pfadi it-cevi it-jubla
  ch-rm
)

fail=0

if [[ ! -d "$LOCALE_DIR" ]]; then
  echo "::error::Locale-Verzeichnis fehlt: $LOCALE_DIR"
  exit 1
fi

for code in "${EXPECTED[@]}"; do
  f="$LOCALE_DIR/${code}.json"
  if [[ ! -f "$f" ]]; then
    echo "::error::Erwartete Locale-Datei fehlt: frontend/src/locales/${code}.json"
    fail=1
    continue
  fi
  if ! python3 -c "import json,sys; json.load(open(sys.argv[1]))" "$f" 2>/dev/null; then
    echo "::error::Ungültiges JSON: frontend/src/locales/${code}.json"
    fail=1
  fi
done

shopt -s nullglob
for f in "$LOCALE_DIR"/*.json; do
  base="$(basename "$f" .json)"
  ok=0
  for code in "${EXPECTED[@]}"; do
    if [[ "$base" == "$code" ]]; then ok=1; break; fi
  done
  if [[ "$ok" -eq 0 ]]; then
    echo "::error::Unerwartete Locale-Datei (Orphan): frontend/src/locales/${base}.json"
    fail=1
  fi
done

# Doppel-Präfix-Müll (historische Weblate-Fehler)
shopt -s nullglob
bad=(
  "$LOCALE_DIR"/en-US.json
  "$LOCALE_DIR"/en-US-*.json
  "$LOCALE_DIR"/fr-FR.json
  "$LOCALE_DIR"/fr-FR-*.json
  "$LOCALE_DIR"/it-IT.json
  "$LOCALE_DIR"/it-IT-*.json
  "$LOCALE_DIR"/de-de-*.json
  "$LOCALE_DIR"/fr-fr-*.json
  "$LOCALE_DIR"/it-it-*.json
)
for f in "${bad[@]}"; do
  [[ -e "$f" ]] || continue
  echo "::error::Verbotene Locale-Datei: ${f#"$ROOT"/}"
  fail=1
done

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "Locales OK (${#EXPECTED[@]} Dateien)."
