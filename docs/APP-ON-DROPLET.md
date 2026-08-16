# Frontend auf dem Droplet (statt Hostpoint-FTP)

Chat-Chronik / Cutover-Stand: [`HETZNER-CUTOVER.md`](HETZNER-CUTOVER.md)

## Zielbild

| Was | Host | Deploy |
|-----|------|--------|
| Marketing Prod (`ematchef.ch`) | **Hostpoint** | selten FTP |
| Marketing lokal | Docker (`ematchef.test`) | lokal |
| Develop/Staging App | **Hetzner** | CI → rsync, Caddy |
| Prod App | **Hetzner** Prod-Droplet | CI → rsync, Caddy |
| API | Hetzner Compose | CD-Workflows |

### Hostnamen

| Env | App-Einstieg (Login) | QR / Devices | API |
|-----|----------------------|--------------|-----|
| develop | `dev.ematchef.ch` | `qr.dev` / `devices.dev` | `api.dev` |
| staging | `staging.ematchef.ch` | `qr.staging` / `devices.staging` | `api.staging` |
| prod | `app.ematchef.ch` | `qr` / `devices` | `api` |

`app.dev` / `app.staging` können parallel denselben Webroot bedienen (Bookmarks), Canonical ist der bare Host.

## Develop

```bash
bash scripts/build-droplet-frontend.sh develop
# → deploy/droplet/develop/app/  (VITE_APP_ORIGIN=https://dev.ematchef.ch)
```

Caddy: [`deploy/caddy/Caddyfile.develop.example`](../deploy/caddy/Caddyfile.develop.example)

### CI-Workflows & Secrets

| Env | Workflow | Webroot-Secret (Beispiel) | SSH |
|-----|----------|---------------------------|-----|
| develop | `deploy-frontend-develop.yml` | `DEVELOP_APP_WEBROOT` = `/var/www/ematchef-app-develop` | `DEVELOP_SSH_*` |
| staging | `deploy-frontend-staging.yml` | `STAGING_APP_WEBROOT` = `/var/www/ematchef-app-staging` | `DEVELOP_SSH_*` (gleicher Droplet) |
| prod | `deploy-frontend-prod.yml` | `PROD_APP_WEBROOT` = `/var/www/ematchef-app-prod` | `PROD_SSH_*` |

Die Versionszeile im Profil-Menü (`v4.0.1 · <sha>`) kommt aus dem **Frontend-Build** (`VITE_APP_GIT_SHA`). Sie entspricht dem Commit, der zuletzt per rsync ausgerollt wurde — nicht dem API-`git pull` allein.

### Cloudflare

A → `94.130.231.112` (Nur DNS): `dev`, `api.dev`, `qr.dev`, `devices.dev` (+ optional `app.dev`).

## Checkliste

1. [ ] Caddy inkl. `dev.ematchef.ch` / `staging.ematchef.ch` / `app.ematchef.ch` → jeweiliger App-Webroot  
2. [ ] `APP_FRONTEND_URL` + CORS passend zur Env  
3. [ ] Deploy Frontend Develop / Staging / Prod Workflows + Secrets  
4. [ ] Smoke: Login-Host bleibt auf der Env-Domain; Profil-Nav zeigt aktuellen Git-SHA  
