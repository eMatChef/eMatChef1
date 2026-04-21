# Server: aktuellen Stand holen, Cache, Start

Für den **API-Server** (z. B. DigitalOcean), Projektverzeichnis z. B. `/opt/ematchef/prod`.

## 1. Neuesten Code von Git holen

```bash
cd /opt/ematchef/prod
git pull
```

Bei HTTPS-Clone ggf. Personal Access Token oder SSH-Deploy-Key nutzen.

## 2. Container bauen und starten

```bash
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d --build db backend
```

Nur neu starten (ohne Rebuild), wenn sich am **Backend-Image** nichts geändert hat:

```bash
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d db backend
```

## 3. Symfony-Prod-Cache leeren und aufwärmen

Nach Code- oder Config-Änderungen am Backend:

```bash
docker compose -p ematchef-prod exec backend rm -rf /var/www/html/var/cache/prod
docker compose -p ematchef-prod exec backend php bin/console cache:warmup --env=prod
```

Alternativ einzeilig:

```bash
docker compose -p ematchef-prod exec backend php bin/console cache:clear --env=prod
```

## 4. Kurz prüfen

```bash
docker compose -p ematchef-prod ps
curl -sI "https://api.ematchef.ch/api/public/site-pages"
```

---

**Hinweise**

- `docker-compose.override.yml` und `.env` mit Geheimnissen liegen **nur auf dem Server**, nicht im Git (siehe `.gitignore`).
- Neue **Console-Commands** erscheinen unter `APP_ENV=prod` erst nach **Cache-Warmup** (siehe Punkt 3).
- **Nginx** auf dem Host (443 → 8081) ist unabhängig von Docker; bei reinen PHP-Änderungen meist kein `systemctl reload nginx` nötig.
