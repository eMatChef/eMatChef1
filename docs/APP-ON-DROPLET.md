# Frontend auf dem Droplet (statt Hostpoint-FTP)

## Zielbild

| Was | Host | Deploy |
|-----|------|--------|
| Marketing Prod (`ematchef.ch`) | **Hostpoint** | selten FTP |
| Marketing lokal | Docker (`ematchef.test`) | lokal |
| Develop/Staging App | **Hetzner** | CI → rsync, Caddy |
| Prod App | **Hetzner** Prod-Droplet | rsync / später CI |
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

Secret: `DEVELOP_APP_WEBROOT` = `/var/www/ematchef-app-develop`

### Cloudflare

A → `94.130.231.112` (Nur DNS): `dev`, `api.dev`, `qr.dev`, `devices.dev` (+ optional `app.dev`).

## Checkliste

1. [ ] Caddy inkl. `dev.ematchef.ch` → App-Webroot  
2. [ ] `APP_FRONTEND_URL=https://dev.ematchef.ch`, CORS inkl. `dev.`  
3. [ ] Deploy Frontend Develop  
4. [ ] Smoke: `https://dev.ematchef.ch/login` bleibt auf `dev.`  
