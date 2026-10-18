#!/usr/bin/env bash
# GA-Logistik Ende-zu-Ende gegen eine reproduzierbare Wegwerf-Umgebung (docs/E2E.md):
# frische Datenbank -> Migrationen -> app:demo:sync -> zweites Backend (:8083) -> Vite (:5199) -> Playwright -> Aufräumen.
# Rührt mvdb und die Dev-Container nicht an (nur lesender Zugriff auf deren Image/Volumes).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DB_NAME="${E2E_DB_NAME:-val_e2e_ga_flow}"
DB_URL="postgresql://mvuser:mvpass@db:5432/${DB_NAME}?serverVersion=16&charset=utf8"
BE=be_e2e_flow
VITE_PID=""
cleanup() {
  # npx startet Vite als Kindprozess: den Listener auf dem Port beenden (kein pkill, das würde fremde Prozesse treffen)
  for pid in $(ss -ltnp "sport = :5199" 2>/dev/null | grep -o 'pid=[0-9]*' | cut -d= -f2); do kill "$pid" 2>/dev/null || true; done
  docker rm -f "$BE" >/dev/null 2>&1 || true
  docker exec ematchef-db-1 psql -U mvuser -d postgres -c "drop database if exists ${DB_NAME}" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker exec ematchef-db-1 psql -U mvuser -d postgres -c "drop database if exists ${DB_NAME}" -c "create database ${DB_NAME}" >/dev/null
run() { docker exec -e DATABASE_URL="$DB_URL" -w /var/www/html ematchef-backend-1 "$@"; }
run php bin/console doctrine:migrations:migrate -n >/dev/null
run php -d memory_limit=2G bin/console app:demo:sync --all >/dev/null
DEPT="$(docker exec ematchef-db-1 psql -U mvuser -d "$DB_NAME" -tA -c "select id from department where name='Demo Grossanlass Event'")"
PASSWORD="$(run php -r 'require "vendor/autoload.php"; echo App\Util\DemoAccounts::password();')"
docker rm -f "$BE" >/dev/null 2>&1 || true
docker run -d --name "$BE" --network ematchef_default -p 8083:8081 -v "$ROOT/backend:/var/www/html" -v ematchef_backend_vendor:/var/www/html/vendor \
  -e DATABASE_URL="$DB_URL" -e AUTH_COOKIE_SECURE=false -e AUTH_COOKIE_DOMAIN=.ematchef.test -e APP_ENV=dev -e TZ=UTC \
  -e JWT_PASSPHRASE=your-secret-passphrase-change-in-production \
  -e 'CORS_ALLOW_ORIGIN=^https?://(ematchef\.test|app\.ematchef\.test|localhost|127\.0\.0\.1)(:[0-9]+)?$' \
  -e APP_FRONTEND_URL=http://app.ematchef.test:5199 ematchef-backend >/dev/null
cd "$ROOT/frontend"
VITE_API_PROXY_TARGET=http://127.0.0.1:8083 npx vite --port 5199 --host 0.0.0.0 >/dev/null 2>&1 &
VITE_PID=$!
for _ in $(seq 1 60); do curl -s -o /dev/null http://127.0.0.1:5199/ && curl -s -o /dev/null http://127.0.0.1:8083/api/health && break; sleep 2; done
# Vite beim ersten Aufruf kompilieren lassen, bevor der Test den Login lädt
curl -s -o /dev/null --max-time 180 -H 'Host: app.ematchef.test' http://127.0.0.1:5199/login || true
E2E_GA_FLOW=1 E2E_BASE_URL=http://app.ematchef.test:5199 \
  E2E_HOST_RESOLVER='MAP *.ematchef.test 127.0.0.1, MAP ematchef.test 127.0.0.1' \
  E2E_DEPARTMENT_ID="$DEPT" E2E_USER_EMAIL=ga-mw@demo.ematchef.ch E2E_DRIVER_EMAIL=ga-lw@demo.ematchef.ch E2E_USER_PASSWORD="$PASSWORD" \
  npx playwright test e2e/ga-logistics-flow.spec.ts --reporter=line --workers=1 --output=/tmp/e2e-ga-logistics-results "$@"
