# App-Frontend auf dem Droplet (statt Hostpoint-FTP)

## Zielbild

| Was | Host | Deploy |
|-----|------|--------|
| Marketing / Landing (`ematchef.ch`, `dev.ematchef.ch`, `staging.ematchef.ch`) | **Hostpoint** | FTP (selten) |
| App + QR + Devices (`app*.ematchef.ch`, `qr*`, `devices*`) | **Hetzner** (gleicher API-Droplet) | CI → SSH/rsync, Caddy `file_server` |

Hostpoint kann **keine** Docker-Images wie ein Cluster. Stattdessen: statischen Vite-Build auf dem Droplet ausliefern (Caddy) — schneller und robuster als FTP mit tausenden Einzeldateien.

## Develop (erster Cutover)

### 1. Build lokal / CI

```bash
bash scripts/build-droplet-app.sh develop
# → deploy/droplet/develop/app/ (index.html + assets/)
```

### 2. Caddy auf dem Develop-Droplet

Vorlage: [`deploy/caddy/Caddyfile.develop.example`](../deploy/caddy/Caddyfile.develop.example)

Typisch:

- `api-dev.ematchef.ch` → `127.0.0.1:8081` (wie bisher)
- `app-dev.ematchef.ch`, `qr-dev.ematchef.ch`, `devices-dev.ematchef.ch` → `root` auf z. B. `/var/www/ematchef-app-develop`

```bash
sudo mkdir -p /var/www/ematchef-app-develop
sudo chown -R "$(whoami):$(whoami)" /var/www/ematchef-app-develop
# Caddyfile anpassen, validate, reload
```

### 3. GitHub Actions

Workflow **Deploy App Develop (Droplet)**: baut nur die App-Variante und sync’t per SSH nach `DEVELOP_APP_WEBROOT` (Secret, z. B. `/var/www/ematchef-app-develop`).

Secrets: bestehende `DEVELOP_SSH_*` + neu `DEVELOP_APP_WEBROOT`.

### 4. DNS-Cutover (Cloudflare)

`app-dev`, `qr-dev`, `devices-dev` von Hostpoint-IP → **Hetzner-IP** (`api-dev`).  
Marketing `dev.ematchef.ch` bleibt auf Hostpoint.

### 5. FTP danach

Nur noch **home** (Marketing) per FTP; App-Upload aus `ftp-deploy-develop.yml` entfernen (nach erfolgreichem Cutover).

Bis zum Cutover: Droplet-Deploy und Hostpoint-App dürfen parallel existieren — DNS entscheidet, welcher Host ausgeliefert wird.

## Staging / Prod

Gleiches Muster:

| Env | Script-Arg | Webroot-Beispiel | Hosts |
|-----|------------|------------------|--------|
| staging | `staging` | `/var/www/ematchef-app-staging` | `app-staging`, `qr-staging`, `devices-staging` (+ Caddy `basic_auth`) |
| prod | `prod` | `/var/www/ematchef-app-prod` | `app`, `qr`, `devices` |

Marketing `staging.ematchef.ch` / `ematchef.ch` weiter Hostpoint.

## Checkliste Cutover Develop

1. [ ] Secret `DEVELOP_APP_WEBROOT` gesetzt  
2. [ ] Caddy-Blöcke für App/QR/Devices aktiv, `caddy validate` + reload  
3. [ ] Workflow **Deploy App Develop** einmal grün  
4. [ ] `curl -sI https://app-dev.ematchef.ch` gegen Droplet-IP testen (Hosts-Datei oder nach DNS)  
5. [ ] Cloudflare DNS umstellen  
6. [ ] Login + SPA-Routen prüfen  
7. [ ] FTP-Workflow auf nur-Marketing reduzieren  

## Warum nicht Docker-Image fürs Frontend?

Optional später (Nginx/Caddy-Container im Compose). Für den Start reicht **Host-Caddy + rsync**: weniger Moving Parts, gleiches TLS wie `api-dev`, kein Registry nötig.
