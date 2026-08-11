# Frontend auf dem Droplet (statt Hostpoint-FTP)

## Zielbild

| Was | Host | Deploy |
|-----|------|--------|
| Marketing (`ematchef.ch`, `dev.`, `staging.`) | **Hetzner** (API-Droplet) | CI → SSH/rsync, Caddy |
| App + QR + Devices (`app*`, `qr*`, `devices*`) | **Hetzner** (gleicher Droplet) | CI → SSH/rsync, Caddy |
| API | Hetzner Compose | CD-Workflows |

Hostpoint-FTP bleibt nur als Notfall (`workflow_dispatch`) bis DNS umgestellt und verifiziert ist.

## Develop (erster Cutover)

### 1. Build

```bash
bash scripts/build-droplet-frontend.sh develop
# → deploy/droplet/develop/{home,app}/
```

### 2. Caddy auf dem Develop-Droplet

Vorlage: [`deploy/caddy/Caddyfile.develop.example`](../deploy/caddy/Caddyfile.develop.example)

```bash
sudo mkdir -p /var/www/ematchef-home-develop /var/www/ematchef-app-develop
sudo chown -R "$(whoami):$(whoami)" /var/www/ematchef-home-develop /var/www/ematchef-app-develop
# Caddyfile anpassen, validate, reload
```

### 3. GitHub Actions / Secrets

Workflow **Deploy Frontend Develop (Droplet)** baut home + app und rsync’t nach:

| Secret | Beispiel |
|--------|----------|
| `DEVELOP_HOME_WEBROOT` | `/var/www/ematchef-home-develop` |
| `DEVELOP_APP_WEBROOT` | `/var/www/ematchef-app-develop` |

Plus bestehende `DEVELOP_SSH_*`.

### 4. DNS-Cutover (Cloudflare)

`dev`, `app-dev`, `qr-dev`, `devices-dev` → **Hetzner-IP** (gleiche wie `api-dev`).

### 5. FTP

Develop/Staging-FTP: nur noch `workflow_dispatch`. Nach stabilem Cutover Workflows entfernen.

## Staging / Prod

| Env | Script-Arg | Home / App Webroot | Hosts |
|-----|------------|--------------------|--------|
| staging | `staging` | `…-home-staging` / `…-app-staging` | `staging`, `app-staging`, … (+ Caddy `basic_auth`) |
| prod | `prod` | `…-home-prod` / `…-app-prod` | `ematchef.ch`, `app`, … |

## Checkliste Cutover Develop

1. [ ] Secrets `DEVELOP_HOME_WEBROOT` + `DEVELOP_APP_WEBROOT` gesetzt  
2. [ ] Caddy-Blöcke aktiv, `caddy validate` + reload  
3. [ ] Workflow **Deploy Frontend Develop** einmal grün  
4. [ ] Cloudflare DNS umstellen  
5. [ ] Landing + Login + SPA-Routen prüfen  
6. [ ] FTP nur noch Notfall / später löschen  

## Warum nicht Docker-Image fürs Frontend?

Optional später. Für den Start reicht **Host-Caddy + rsync**: weniger Moving Parts, gleiches TLS wie `api-dev`.
