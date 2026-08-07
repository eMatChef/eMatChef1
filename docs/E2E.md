# Smoke-E2E (Playwright)

Kurze Browser-Tests gegen die **laufende Develop-App** (nicht gegen einen Preview-Host).

## Was wird geprüft?

1. Login-Seite lädt (`/login`, Brand + Felder)
2. Optional: Login mit Test-User → URL verlässt `/login`

## Lokal

```bash
cd frontend
npm ci
npx playwright install chromium
export E2E_BASE_URL=https://app-dev.ematchef.ch
# optional für Login-Smoke:
export E2E_USER_EMAIL='…'
export E2E_USER_PASSWORD='…'
npm run test:e2e
```

## GitHub Secrets (Repository)

| Secret | Beispiel | Pflicht |
|--------|----------|---------|
| `E2E_BASE_URL` | `https://app-dev.ematchef.ch` | ja (sonst Default im Config) |
| `E2E_USER_EMAIL` | dedizierter Smoke-User | für Login-Test |
| `E2E_USER_PASSWORD` | … | für Login-Test |

Anlegen: GitHub → Settings → Secrets and variables → Actions.

**Test-User:** eigener Account auf Develop, kein Prod-Admin. Ideal: nur Leserechte / eine Test-Abteilung.

## CI

Workflow **E2E Smoke** (`.github/workflows/e2e-smoke.yml`):

- Läuft auf Push/PR zu `develop` / `prod`
- Installiert Chromium und führt `npm run test:e2e` aus
- Fehlt Login-Secret → nur öffentlicher Login-Seiten-Test
- Job ist vorerst **nicht** Teil von **CI ok** (stabilisieren, dann Pflicht machen)

## Preview-Deploy

Pro-PR-URLs (`pr-N.…`) sind **noch nicht** eingerichtet (braucht Host/DNS). Smokes laufen gegen Develop.
