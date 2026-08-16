#!/usr/bin/env bash
# Point this repo at the version-controlled hooks in .githooks/
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

git config core.hooksPath .githooks
chmod +x .githooks/pre-push .githooks/prepare-commit-msg

echo "Git hooks installed (core.hooksPath=.githooks)"
echo "  pre-push → CI-Checks lokal (ESLint, Vitest, Build, Locales, PHPUnit, PHPStan; kein Playwright)"
echo "  prepare-commit-msg → Cursor/cursoragent Co-Author-Zeilen entfernen"
