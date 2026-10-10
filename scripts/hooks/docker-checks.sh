#!/usr/bin/env bash
# Frontend- und Backend-Prüfungen des Pre-Push-Hooks, in jedem Worktree lauffähig.
#
# Lokale Werkzeuge (frontend/node_modules, backend/vendor) werden genutzt, wenn sie vorhanden sind. Zusätzliche
# Worktrees haben dort oft nur einen leeren Docker-Mount-Stub; dann läuft die Prüfung in einem kurzlebigen Container
# aus den vorhandenen Images (`docker run --rm`):
#   - Quellcode: der Worktree, schreibgeschützt eingehängt und in ein tmpfs kopiert (der Worktree wird nie beschrieben,
#     auch kein Mountpunkt `vendor/` oder `dist/`). Es wird nie ein anderer Worktree geprüft.
#   - Abhängigkeiten: die benannten Volumes des Dev-Stacks (backend/vendor schreibgeschützt; node_modules
#     beschreibbar, weil Vite/Vitest dort nur ihre Caches `.vite*` anlegen, wie es der Dev-Container selbst tut).
#   - Datenbank (nur Backend): eine Wegwerf-PostgreSQL mit Datenbank `val_hook` in einem eigenen Netz; `mvdb` und der
#     Dev-Stack werden nicht berührt. Alles wird beim Beenden entfernt.
# Fehlt ein Werkzeug, ein Image oder ein Volume, bricht der Hook mit einer Meldung ab (nichts wird übersprungen).
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
FE_IMAGE="${EMATCHEF_HOOK_FRONTEND_IMAGE:-ematchef-frontend:latest}"
BE_IMAGE="${EMATCHEF_HOOK_BACKEND_IMAGE:-ematchef-backend:latest}"
PG_IMAGE="${EMATCHEF_HOOK_PG_IMAGE:-postgres:16}"
FE_VOLUME="${EMATCHEF_HOOK_FRONTEND_NODE_MODULES_VOLUME:-ematchef_frontend_node_modules}"
BE_VOLUME="${EMATCHEF_HOOK_BACKEND_VENDOR_VOLUME:-ematchef_backend_vendor}"

die() { echo "pre-push: $*" >&2; exit 1; }

need_docker() {
  command -v docker >/dev/null 2>&1 || die "Werkzeuge fehlen im Worktree und docker ist nicht verfügbar ($1)."
  docker image inspect "$2" >/dev/null 2>&1 || die "Docker-Image $2 fehlt (docker compose build $1)."
  [[ -z "${3:-}" ]] || docker volume inspect "$3" >/dev/null 2>&1 || die "Docker-Volume $3 fehlt (Dev-Stack einmal starten: docker compose up -d $1)."
}

frontend_local() {
  local t
  for t in eslint vitest vue-tsc vite; do [[ -x "$ROOT/frontend/node_modules/.bin/$t" ]] || return 1; done
}

backend_local() {
  [[ -x "$ROOT/backend/vendor/bin/phpunit" && -x "$ROOT/backend/vendor/bin/phpstan" && -n "${EMATCHEF_TEST_DB_URL:-}" ]]
}

# Führt ein Skript im Frontend-Image gegen eine Kopie von frontend/ aus.
frontend_container() {
  docker run --rm --entrypoint sh \
    -v "$ROOT/frontend:/src:ro" -v "$FE_VOLUME:/work/node_modules" \
    --tmpfs /work:exec,size=3g -w /work "$FE_IMAGE" \
    -c "set -e; tar -C /src --exclude=./node_modules --exclude=./dist -cf - . | tar -C /work -xf -; $1"
}

frontend_checks() {
  if frontend_local; then
    echo "pre-push: Frontend-Werkzeuge: lokal"
    (cd "$ROOT/frontend" && echo "pre-push: Frontend ESLint..." && npm run lint:ci \
      && echo "pre-push: Frontend Unit tests..." && npm test \
      && echo "pre-push: Frontend Build (vue-tsc + vite build)..." && npm run build)
    return
  fi
  need_docker frontend "$FE_IMAGE" "$FE_VOLUME"
  echo "pre-push: Frontend-Werkzeuge: Container $FE_IMAGE (Quellen: $ROOT/frontend)"
  frontend_container '
    echo "pre-push: Frontend ESLint..."
    # JSON-Ausgabe: der Standard-Formatter von ESLint 10 braucht ein neueres Node als das Dev-Image.
    set +e; node node_modules/eslint/bin/eslint.js . --max-warnings 2000 -f json -o /tmp/eslint.json; rc=$?; set -e
    node -e "const r=require(\"/tmp/eslint.json\");let e=0,w=0;for(const f of r){e+=f.errorCount;w+=f.warningCount;for(const m of f.messages)if(m.severity===2)console.log(f.filePath+\":\"+m.line+\" \"+m.ruleId+\" \"+m.message)}console.log(\"eslint: \"+e+\" errors, \"+w+\" warnings\")"
    [ "$rc" -eq 0 ] || { echo "pre-push: ESLint fehlgeschlagen (Exit $rc)" >&2; exit "$rc"; }
    echo "pre-push: Frontend Unit tests..."
    node node_modules/vitest/vitest.mjs run
    echo "pre-push: Frontend Build (vue-tsc + vite build)..."
    node --max-old-space-size=4096 node_modules/vue-tsc/bin/vue-tsc.js --noEmit
    node node_modules/vite/bin/vite.js build --outDir /tmp/dist --emptyOutDir
  '
}

backend_checks() {
  echo "pre-push: Backend Composer validate..."
  if command -v composer >/dev/null 2>&1; then
    (cd "$ROOT/backend" && composer validate --strict --no-interaction)
  else
    need_docker backend "$BE_IMAGE"
    docker run --rm --entrypoint composer -v "$ROOT/backend:/src:ro" -w /src "$BE_IMAGE" validate --strict --no-interaction
  fi

  if backend_local; then
    echo "pre-push: Backend-Werkzeuge: lokal (EMATCHEF_TEST_DB_URL gesetzt)"
    (cd "$ROOT/backend" && echo "pre-push: Backend Unit- und Integrationstests..." \
      && ./vendor/bin/phpunit --fail-on-skipped && echo "pre-push: PHPStan..." \
      && ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress)
    return
  fi

  need_docker backend "$BE_IMAGE" "$BE_VOLUME"
  need_docker backend "$PG_IMAGE"
  echo "pre-push: Backend-Werkzeuge: Container $BE_IMAGE (Quellen: $ROOT/backend), Wegwerf-Datenbank $PG_IMAGE"
  # Global, damit der EXIT-Trap die Namen noch kennt.
  local id="$$-$RANDOM" url
  net="ematchef-hook-net-$id"
  pg="ematchef-hook-pg-$id"
  url="postgresql://val:val@$pg:5432/val_hook?serverVersion=16&charset=utf8"
  cleanup() { docker rm -f "${pg:-}" >/dev/null 2>&1 || true; docker network rm "${net:-}" >/dev/null 2>&1 || true; }
  trap cleanup EXIT
  docker network create "$net" >/dev/null
  docker run -d --rm --name "$pg" --network "$net" --tmpfs /var/lib/postgresql/data \
    -e POSTGRES_USER=val -e POSTGRES_PASSWORD=val -e POSTGRES_DB=val_hook "$PG_IMAGE" >/dev/null
  local i
  for i in $(seq 1 30); do
    docker exec "$pg" pg_isready -U val -d val_hook >/dev/null 2>&1 && break
    [[ "$i" -lt 30 ]] || die "Test-Datenbank wurde nicht bereit."
    sleep 1
  done

  docker run --rm --entrypoint sh --network "$net" \
    -v "$ROOT/backend:/src:ro" -v "$BE_VOLUME:/work/vendor:ro" \
    --tmpfs /work:exec,size=2g -w /work \
    -e APP_ENV=test -e DATABASE_URL="$url" -e EMATCHEF_TEST_DB_URL="$url" "$BE_IMAGE" \
    -c '
      set -e
      tar -C /src --exclude=./vendor --exclude=./var -cf - . | tar -C /work -xf -
      mkdir -p var
      echo "pre-push: Migrationen (Test-Datenbank)..."
      php bin/console doctrine:migrations:migrate --no-interaction >/dev/null
      echo "pre-push: Backend Unit- und Integrationstests..."
      php -d memory_limit=-1 vendor/bin/phpunit --fail-on-skipped --cache-directory /tmp/phpunit-cache
      echo "pre-push: PHPStan..."
      php -d memory_limit=-1 vendor/bin/phpstan analyse --memory-limit=1G --no-progress
    '
}

case "${1:-}" in
  frontend) frontend_checks ;;
  backend) backend_checks ;;
  *) die "Aufruf: docker-checks.sh frontend|backend" ;;
esac
