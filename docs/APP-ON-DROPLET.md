# Frontend auf dem Droplet (statt Hostpoint-FTP)

## Zielbild

| Was | Host | Deploy |
|-----|------|--------|
| Marketing Prod (`ematchef.ch`) | **Hostpoint** | selten FTP |
| Develop/Staging App + QR + Devices | **Hetzner** (API-Droplet) | CI → rsync, Caddy |
| Prod App + QR + Devices | **Hetzner** (Prod-Droplet) | CI → rsync (später) |
| API | Hetzner Compose | CD-Workflows |

### Hostnamen (Hierarchie)

| Env | App | QR | Devices | API |
|-----|-----|----|---------|-----|
| develop | `app.dev.ematchef.ch` | `qr.dev` | `devices.dev` | `api.dev` |
| staging | `app.staging.ematchef.ch` | `qr.staging` | `devices.staging` | `api.staging` |
| prod | `app.ematchef.ch` | `qr` | `devices` | `api` |

Alte flat-Namen (`app-dev`, …) redirecten kurz per Caddy auf die Hierarchie.

## Develop

```bash
bash scripts/build-droplet-frontend.sh develop
# → deploy/droplet/develop/app/
```

Caddy: [`deploy/caddy/Caddyfile.develop.example`](../deploy/caddy/Caddyfile.develop.example)

Secrets: `DEVELOP_APP_WEBROOT` = `/var/www/ematchef-app-develop` + `DEVELOP_SSH_*`.

### Cloudflare (Develop)

A-Records auf **`94.130.231.112`** (Nur DNS):

- `app.dev` / `qr.dev` / `devices.dev`
- optional Übergang: `app-dev`, `qr-dev`, `devices-dev`, `dev` (Redirects)

Kein neuer Hostpoint-VHost nötig.

## Staging

Gleiches Muster mit `*.staging` + Caddy `basic_auth`. Siehe `Caddyfile.staging.example`.

## Checkliste Cutover Develop

1. [ ] Caddy Hierarchie + Reload  
2. [ ] API `.env` / Compose: `APP_FRONTEND_URL`, `CORS_ALLOW_ORIGIN`  
3. [ ] Workflow **Deploy Frontend Develop** grün  
4. [ ] Cloudflare `app.dev` / `qr.dev` / `devices.dev`  
5. [ ] Smoke Login auf `https://app.dev.ematchef.ch`  
6. [ ] Hostpoint-Dev-Webordner später leeren (Daten/Mail behalten)  
