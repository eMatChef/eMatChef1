# Server: aktuellen Stand holen, Cache, Start

Für den **API-Server** (z. B. DigitalOcean), Projektverzeichnis z. B. `/opt/ematchef/prod`.

## 1. Standard: Dienst stoppen → Pull → durchstarten

Container sauber beenden, Repo aktualisieren, Images neu bauen und `db` + `backend` starten:

```bash
cd /opt/ematchef/prod
docker compose -p ematchef-prod down
git pull
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d --build db backend
```

Anschließend Symfony-Prod-Cache (Abschnitt 3) nicht vergessen.

**Hinweis:** `down` **ohne** `-v` lässt die PostgreSQL-Daten auf dem Volume bestehen. Nur bei bewusstem Reset der Datenbank: `docker compose -p ematchef-prod down -v` (siehe `deploy/docker-compose.override.prod.example.yml`).

### Alternative ohne `down`

Wenn die Container weiterlaufen dürfen und nur Code/Image aktualisiert werden soll:

```bash
cd /opt/ematchef/prod
git pull
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d --build db backend
```

Bei HTTPS-Clone ggf. Personal Access Token oder SSH-Deploy-Key nutzen.

### Wenn `git pull` wegen `frontend/.env.production` abbricht

Diese Datei ist im Repo versioniert; **lokale Anpassungen auf dem Server** (z. B. Turnstile-Key) blockieren den Merge. Vor dem Pull wegstellen und danach wiederherstellen:

```bash
cd /opt/ematchef/prod
git stash push -m "prod frontend env" -- frontend/.env.production
git pull
git stash pop
```

Bei Konflikten nach `stash pop`: Datei manuell mergen oder deine Produktionswerte aus dem Stash wieder eintragen. Anschließend Deploy wie in Abschnitt 1 (`down` / `up --build` …), falls noch nicht erledigt.

**Alternative:** Kopie sichern, Pull mit Zurücksetzen der Datei, dann Werte aus der Kopie zurücksetzen:

```bash
cp frontend/.env.production /root/frontend.env.production.bak
git checkout -- frontend/.env.production
git pull
# Bei Bedarf: diff zur Backup-Datei und fehlende Keys wieder einfügen
```

## 2. Nur neu starten (ohne Rebuild)

Wenn sich am **Backend-Dockerfile** nichts geändert hat:

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

## 5. Superadmin anlegen (erstes Setup / fehlender Admin)

Legt bei Bedarf die globale System-Organisation (`GLOBALORG001`) und ein Department an und erstellt bzw. aktualisiert den Superadmin-User.

**Interaktiv** (Passwort wird verdeckt abgefragt; `-it` nötig):

```bash
docker compose -p ematchef-prod exec -it backend php bin/console app:ensure-superadmin deine@email.de --env=prod
```

**Nicht-interaktiv** (Passwort landet ggf. in der Shell-Historie):

```bash
docker compose -p ematchef-prod exec backend php bin/console app:ensure-superadmin deine@email.de --password='…' --env=prod
```

Bei **neuer, leerer Datenbank** zuerst Migrationen ausführen, dann dieses Command:

```bash
docker compose -p ematchef-prod exec backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

---

**Hinweise**

- `docker-compose.override.yml` und `.env` mit Geheimnissen liegen **nur auf dem Server**, nicht im Git (siehe `.gitignore`).
- Neue **Console-Commands** erscheinen unter `APP_ENV=prod` erst nach **Cache-Warmup** (siehe Abschnitt 3).
- **Nginx** auf dem Host (443 → 8081) ist unabhängig von Docker; bei reinen PHP-Änderungen meist kein `systemctl reload nginx` nötig.
