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

## CI

Workflow **E2E Smoke** (`.github/workflows/e2e-smoke.yml`):

- Läuft auf Push/PR zu `develop` / `prod`
- Installiert Chromium und führt `npm run test:e2e` aus
- Fehlt Login-Secret → nur öffentlicher Login-Seiten-Test
- Job ist vorerst **nicht** Teil von **CI ok** (stabilisieren, dann Pflicht machen)

## Preview-Deploy

Pro-PR-URLs (`pr-N.…`) sind **noch nicht** eingerichtet (braucht Host/DNS). Smokes laufen gegen Develop.
