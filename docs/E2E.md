# Smoke-E2E (Playwright)

Kurze Browser-Tests gegen die **laufende Develop-App** (nicht gegen einen Preview-Host).

## Was wird geprüft?

1. Login-Seite lädt (`/login`, Brand + Felder)
2. Optional: Login mit Test-User → URL verlässt `/login`

## Dedizierter Smoke-User

Nicht `support@…` oder Prod-Accounts verwenden.

1. Auf dem **Develop-API-Droplet** (Repo-Pfad, Backend-Container):

```bash
cd /opt/ematchef/develop   # bzw. DEVELOP_DEPLOY_PATH
# Passwort selbst wählen (stark, nur für E2E), nicht committen:
E2E_PW='…dein-geheimes-passwort…'
docker compose exec -T backend php bin/console app:ensure-e2e-user \
  --email=e2e-smoke@ematchef.ch \
  --password="$E2E_PW"
unset E2E_PW
```

(Befehl legt User an oder aktualisiert: aktiv, E-Mail verifiziert, **standardmäßig ohne Department** — erscheint nicht in der User-Suche. Optional `--department-id=…` für Membership.)

2. GitHub → Settings → Secrets and variables → Actions:

| Secret | Wert |
|--------|------|
| `E2E_BASE_URL` | `https://dev.ematchef.ch` |
| `E2E_USER_EMAIL` | `e2e-smoke@ematchef.ch` |
| `E2E_USER_PASSWORD` | dasselbe Passwort wie oben |

## Lokal

```bash
cd frontend
npm ci
npx playwright install chromium
# WSL/Ubuntu: Systembibliotheken (sonst: libasound.so.2 missing)
sudo npx playwright install-deps chromium
# oder: sudo apt-get install -y libasound2t64
export E2E_BASE_URL=https://dev.ematchef.ch
export E2E_USER_EMAIL='e2e-smoke@ematchef.ch'
export E2E_USER_PASSWORD='…'   # nie in Chat/Git pasten
npm run test:e2e
```

**Sicherheit:** E2E-Passwort nur als Env/GitHub-Secret — nie in Issues, Chat oder Git.

## Demo «Grossanlass Event 2027» (isolierte Umgebung)

`frontend/e2e/ga-event-demo.spec.ts` prüft Anlasszeitraum, Stammdaten-Zeiten, Navigation unter «Beschaffung», Budget Material & Logistik, die Schnellwahl der Demo-Uhr und dass ein Zeitsprung keine Budgetzahlen ändert. Er läuft **nur gegen eine isolierte, frisch mit `app:demo:sync --all` befüllte Umgebung** (nie gegen eine Datenbank mit von Hand veränderten Demo-Daten; die Demo-Uhr ist Datenbankzustand und der Test verstellt sie vorübergehend), aktiv nur mit `E2E_GA_DEMO=1` und dem öffentlichen Demo-Konto `ga-mw`.

Lokal ohne Eingriff in `mvdb`: Wegwerf-PostgreSQL (eigener Container, eigene Datenbank, migriert und befüllt), zweiter Backend-Container desselben Images mit `DATABASE_URL` auf diese Datenbank (Port 8083), Vite mit `VITE_API_PROXY_TARGET=http://127.0.0.1:8083` und den Hosts `app.ematchef.test` auf Port 5199; Chromium bildet die Hosts über `E2E_HOST_RESOLVER='MAP *.ematchef.test 127.0.0.1, MAP ematchef.test 127.0.0.1'` auf 127.0.0.1 ab (Playwright-Config, kein Eintrag in `/etc/hosts` nötig):

```bash
E2E_GA_DEMO=1 E2E_BASE_URL=http://app.ematchef.test:5199 \
E2E_HOST_RESOLVER='MAP *.ematchef.test 127.0.0.1, MAP ematchef.test 127.0.0.1' \
E2E_USER_EMAIL=ga-mw@demo.ematchef.ch E2E_USER_PASSWORD='<Demo-Passwort aus demo-accounts.json>' \
npx playwright test e2e/ga-event-demo.spec.ts
```

**Achtung:** Ein Neustart des Backend-Containers der Entwicklungsumgebung führt `doctrine:migrations:migrate` gegen `mvdb` aus (Entrypoint) und würde damit neue Migrationen anwenden.

## CI

Workflow **E2E Smoke** (`.github/workflows/e2e-smoke.yml`):

- Läuft auf Push/PR zu `develop` / `prod`
- Installiert Chromium und führt `npm run test:e2e` aus
- Fehlt Login-Secret → nur öffentlicher Login-Seiten-Test
- Auf **Staging** und **Prod** ist der Playwright-Job in `ci.yml` **Pflicht** für «CI ok».
- Auf **Develop** ist er nur Signal (`continue-on-error`): ein hängender Droplet darf Merges nicht blockieren. CD aktualisiert Develop unabhängig.

## Preview-Deploy

Pro-PR-URLs (`pr-N.…`) sind **noch nicht** eingerichtet (braucht Host/DNS). Smokes laufen gegen Develop.
