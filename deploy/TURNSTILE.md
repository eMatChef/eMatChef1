# Cloudflare Turnstile einbinden

Turnstile schützt in eMatChef die **öffentliche Registrierung** (`POST /api/auth/register`). **Login** nutzt kein Turnstile.

## 1. Widget in Cloudflare anlegen

1. [Cloudflare Dashboard](https://dash.cloudflare.com/) → **Turnstile** → **Add widget**.
2. **Hostname(s):** z. B. `app.ematchef.ch` (und ggf. `localhost` für lokale Tests mit eigenem Hosts-Eintrag).
3. Notieren:
   - **Site Key** (öffentlich, kommt ins Frontend)
   - **Secret Key** (nur Backend, geheim)

## 2. Frontend (Hostpoint-Build)

In `frontend/.env.production`:

```env
VITE_TURNSTILE_SITE_KEY=<Site Key aus Cloudflare>
```

Leer lassen = kein Widget (nur sinnvoll, wenn Backend ebenfalls **kein** Secret prüft).

Dann neu bauen und deployen:

```bash
./scripts/build-hostpoint-deploy.sh
```

Upload des Ordners `deploy/hostpoint/app.ematchef.ch/` wie gewohnt.

**Lokal ohne Widget testen:** in `frontend/.env.development` kann `VITE_TURNSTILE_SKIP=1` gesetzt werden (zusammen mit Backend `TURNSTILE_SKIP_VERIFY=1`).

## 3. Backend (API-Droplet)

Secret **nicht** ins Git; auf dem Server in **`/opt/ematchef/prod/.env`** (oder wie bei euch üblich):

```env
TURNSTILE_SECRET_KEY=<Secret Key aus Cloudflare>
TURNSTILE_SKIP_VERIFY=0
```

In **`docker-compose.override.yml`** müssen die Variablen durchgereicht werden (siehe `deploy/docker-compose.override.prod.example.yml`):

```yaml
TURNSTILE_SECRET_KEY: "${TURNSTILE_SECRET_KEY:-}"
TURNSTILE_SKIP_VERIFY: "${TURNSTILE_SKIP_VERIFY:-0}"
```

Backend neu starten:

```bash
cd /opt/ematchef/prod
docker compose -p ematchef-prod up -d backend
docker compose -p ematchef-prod exec backend php bin/console cache:warmup --env=prod
```

## 4. Verhalten

| Konfiguration | Ergebnis |
|---------------|----------|
| Secret gesetzt, `SKIP_VERIFY=0` | Registrierung nur mit gültigem Turnstile-Token |
| Secret leer | Backend prüft **nicht** (nur für lokale Entwicklung sinnvoll) |
| `TURNSTILE_SKIP_VERIFY=1` | Prüfung aus (nur Test; **Produktion nie**) |

## 5. Fehlersuche

- **„Captcha-Verifikation fehlgeschlagen“:** Site-Key/Secret-Key passen nicht zusammen, falsche Domain im Widget, oder Token abgelaufen → Turnstile neu lösen.
- **Kein Widget sichtbar:** `VITE_TURNSTILE_SITE_KEY` fehlt im **Build** (nach Änderung immer `npm run build` / Deploy-Script).
- API muss **HTTPS** erreichbar sein; Cloudflare verifiziert serverseitig gegen `siteverify`.
