# Server: aktuellen Stand holen, Cache, Start

Für den **API-Server** (z. B. DigitalOcean), Projektverzeichnis z. B. `/opt/ematchef/prod`.

## Branch- und Domain-Zuordnung

- `prod` -> Produktion (`ematchef.ch`, `app.ematchef.ch`, `qr.ematchef.ch`)
- `develop` -> Entwicklungsumgebung (`dev.ematchef.ch`)

Empfohlene Verzeichnisse auf dem Server:

- `/opt/ematchef/prod` (tracked auf Branch `prod`)
- `/opt/ematchef/develop` (tracked auf Branch `develop`)

**Git auf dem Droplet:** Per SSH einloggen, ins Repo-Verzeichnis wechseln, dort die Befehle unten ausführen (die IDE führt kein Git auf deinem Server aus).

```bash
ssh BENUTZER@DROPLET_HOST_ODER_IP
cd /opt/ematchef/prod   # anpassen, falls dein Pfad anders ist
```

### Schnell: ein Skript statt vieler Zeilen

Im Repo liegt **`deploy/prod-update.sh`**: holt den konfigurierten Ziel-Branch (Standard `origin/prod`, per `EMATCHEF_GIT_BRANCH` ueberschreibbar) und startet **`db` + `backend`** (ohne langsames `docker compose --build`, außer `EMATCHEF_COMPOSE_BUILD=1`).

Beispiel Produktion:

```bash
cd /opt/ematchef/prod
chmod +x deploy/prod-update.sh
EMATCHEF_PROD_ROOT=/opt/ematchef/prod ./deploy/prod-update.sh reset
```

Beispiel Entwicklung (`develop` -> `dev.ematchef.ch`):

```bash
cd /opt/ematchef/develop
chmod +x deploy/prod-update.sh
EMATCHEF_PROD_ROOT=/opt/ematchef/develop \
EMATCHEF_GIT_BRANCH=develop \
COMPOSE_PROJECT_NAME=ematchef-develop \
./deploy/prod-update.sh reset
```

**Develop-API / CORS:** Wenn der Browser von `https://app-dev.ematchef.ch` aus `https://api-dev.ematchef.ch` blockiert („No Access-Control-Allow-Origin“), muss auf dem Develop-Server `CORS_ALLOW_ORIGIN` u. a. **`app-dev.ematchef.ch`** erlauben (siehe `deploy/CROSS-SUBDOMAIN-LOGIN.md`, `deploy/develop-droplet.env.example` und optional `deploy/docker-compose.override.develop.example.yml`).

### Einmalig: Geheimnisse & URLs (überleben `git reset --hard`)

`./deploy/prod-update.sh reset` macht **`git fetch` + `git reset --hard`** auf den Server-Klon. **Nur Dateien, die Git trackt**, werden dabei wieder exakt wie auf GitHub — lokale Änderungen daran sind weg.

**Bleibt in der Regel erhalten** (liegt nicht im Repo bzw. ist gitignored):

| Ort | Zweck |
|-----|--------|
| **Repo-Root `.env`** | Compose-Substitution (`JWT_PASSPHRASE`, `CORS_*`, `APP_*`, …); `init-prod-env.sh` legt sie für Prod an |
| **`docker-compose.override.yml`** im Repo-Root | Prod-Konfiguration (siehe `deploy/docker-compose.override.prod.example.yml`) |
| **`backend/.env.local`** | Symfony-Secrets (Mailer, Turnstile, …); **nicht** versioniert |
| **`config/jwt/*.pem`** unter `backend/` | Lexik-JWT-Schlüssel (gitignored); `--skip-if-exists` im Entrypoint |

**Wird bei jedem Reset überschrieben**, wenn du es nur auf dem Server geändert hast:

| Ort | Konsequenz |
|-----|----------------|
| **`docker-compose.yml`** (getrackt) | u. a. Default-CORS/JWT — deshalb Droplet-Werte in **`.env`** / **Override** |
| **`backend/.env`** (getrackt!) | Server-Anpassungen hier sind **falsch** — Geheimnisse in **`.env.local`** oder Compose-Env |

**JWT:** Die **Passphrase** muss zu den **bestehenden PEM-Dateien** passen. Nach Reset auf einen Default in Compose ohne passende Keys → Login bricht; Lösung: stabile `JWT_PASSPHRASE` in der **Root-`.env`** (Develop) bzw. in der **Prod-`.env`** setzen, die zum Keypair auf dem Volume passt — oder Keys neu erzeugen (bestehende Tokens ungültig).

**SendGrid / Mailer:** In **Produktion** kommt `MAILER_DSN` (und ggf. `MAILER_FROM`) aus der **Server-`.env`** und dem **Override** (`docker-compose.override.prod.example.yml`). Wenn du es nur in einer **getrackten** Datei hattest, war es nach Deploy weg — dauerhaft in **`.env`** (und bei Bedarf `docker-compose.override.yml`) pflegen.

**Develop-Droplet:** Vorlage kopieren und ausfüllen:

```bash
cd /opt/ematchef/develop
cp deploy/develop-droplet.env.example .env
nano .env   # Domains, CORS, JWT_PASSPHRASE, optional Turnstile/Mailer
chmod 600 .env
EMATCHEF_PROD_ROOT=/opt/ematchef/develop EMATCHEF_GIT_BRANCH=develop COMPOSE_PROJECT_NAME=ematchef-develop ./deploy/prod-update.sh up
```

**Prod-Droplet** (Reihenfolge wie in den Kommentaren der Beispieldateien):

```bash
cd /opt/ematchef/prod
cp deploy/docker-compose.override.prod.example.yml docker-compose.override.yml
bash deploy/init-prod-env.sh    # erzeugt .env mit DB-App-JWT-Geheimnissen, sofern noch keine .env
nano .env                       # MAILER_DSN, TURNSTILE_SECRET_KEY, …
chmod 600 .env
EMATCHEF_PROD_ROOT=/opt/ematchef/prod ./deploy/prod-update.sh reset
```

Migration ausführen
```bash
docker compose -p ematchef-prod exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose -p ematchef-prod exec backend php bin/console doctrine:migrations:status
```

`docker compose down -v` (Datenbank leeren) ist **nur** nötig bei kaputtem DB-Stand oder Erstinstallation – **nicht** bei jedem normalen Update.

## 1. Standard: Dienst stoppen → Pull → durchstarten

Container sauber beenden, Repo aktualisieren, `db` + `backend` starten. **Standard ohne `--build`:** Backend-Code liegt per Volume auf dem Host; ein Rebuild ist nur nötig, wenn sich `backend/Dockerfile` oder die PHP-Base-Layers ändern.

```bash
cd /opt/ematchef/prod
docker compose -p ematchef-prod down
git pull
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d db backend
```

Mit Image-Rebuild (langsam, nur bei Dockerfile-/Image-Änderung):

```bash
EMATCHEF_COMPOSE_BUILD=1 docker compose -p ematchef-prod up -d --build db backend
```

Anschließend Symfony-Prod-Cache (Abschnitt 3) nicht vergessen.

**Hinweis:** `down` **ohne** `-v` lässt die PostgreSQL-Daten auf dem Volume bestehen. Nur bei bewusstem Reset der Datenbank: `docker compose -p ematchef-prod down -v` (siehe `deploy/docker-compose.override.prod.example.yml`).

### DB healthy, Migrationen, kein „Hängen“

- **Reihenfolge:** `docker compose … up -d db` abwarten, bis `docker compose … ps` bei `db` **healthy** zeigt (oder Logs ohne Crash), danach `backend` starten — oder beides in einem Befehl: Compose startet `backend` erst, wenn die DB den Healthcheck besteht.
- **Entrypoint:** `backend/docker-entrypoint.sh` ruft vor `composer`/`migrate` **`php docker/wait-for-db.php`** auf (PDO `SELECT 1`, mehrere Versuche). So laufen Migrationen nicht gegen eine noch nicht bereite Postgres-Instanz.
- **Sehr langsames Droplet:** optional `WAIT_FOR_DB_ATTEMPTS=120` `WAIT_FOR_DB_SLEEP=3` in der `backend`-`environment`-Sektion der Compose-Override-Datei setzen.
- **`composer install` dauert lange:** im Entrypoint ist `COMPOSER_PROCESS_TIMEOUT` standardmäßig **1800** Sekunden — bei extrem langsamer Anbindung ggf. erhöhen.
- **Container `db` mit Exit `137`:** oft **OOM** (zu wenig RAM). Prüfen: `free -h`, Swap, `dmesg | tail -30` nach „Out of memory“. Ohne genug RAM/Swap kann Postgres gekillt werden — dann bleibt der Backend-Start beim Warten oder bei Migrationen hängen bzw. scheitern.

### Alternative ohne `down`

Wenn die Container weiterlaufen dürfen und nur Code/Image aktualisiert werden soll:

```bash
cd /opt/ematchef/prod
git pull
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d db backend
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

Bei Konflikten nach `stash pop`: Datei manuell mergen oder deine Produktionswerte aus dem Stash wieder eintragen. Anschließend Deploy wie in Abschnitt 1 (`down` / `up -d` …), falls noch nicht erledigt.

**Alternative:** Kopie sichern, Pull mit Zurücksetzen der Datei, dann Werte aus der Kopie zurücksetzen:

```bash
cp frontend/.env.production /root/frontend.env.production.bak
git checkout -- frontend/.env.production
git pull
# Bei Bedarf: diff zur Backup-Datei und fehlende Keys wieder einfügen
```

Logs Auslessen
```bash
docker compose -p ematchef-prod logs backend --tail 200
```
## 2. Nur neu starten (ohne Rebuild)

Wenn sich am **Backend-Dockerfile** nichts geändert hat:

```bash
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d db backend
```

## 2a. Schritt 1: Security-Basics prüfen (Prod)

Nach dem Start einmal kurz verifizieren:

```bash
cd /opt/ematchef/prod

# Effektive Compose-Konfiguration (APP_ENV/APP_DEBUG, Port-Bindings)
docker compose -p ematchef-prod config | rg "APP_ENV|APP_DEBUG|MAILER_DSN|MAILER_FROM|127.0.0.1:8081|8081:8081"

# Laufende Container/Port-Mappings
docker compose -p ematchef-prod ps

# Offene Host-Ports (sollte 8081 NICHT als 0.0.0.0 zeigen)
ss -tulpen | rg ":80|:443|:8081|:5432"

# Runtime-Check im Backend-Container
docker compose -p ematchef-prod exec backend env | rg "^APP_ENV=|^APP_DEBUG=|^MAILER_DSN=|^MAILER_FROM="
```

Soll-Zustand:
- `APP_ENV=prod` und `APP_DEBUG=0`
- Backend-Port nur lokal (`127.0.0.1:8081:8081`) oder gar nicht direkt veröffentlicht
- Kein öffentlicher Postgres-Port auf dem Host

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

## 3a. E-Mail (Prod, SendGrid-only)

**Transport im Backend:** Ausschliesslich **`MAILER_DSN` aus der Umgebung** (typisch **SendGrid** über `sendgrid+api://...`). Wenn `MAILER_DSN` leer ist oder `null://…` bleibt, **ist kein Versand moeglich** (Registrierung/Passwort-Reset schlagen fehl) — es gibt keinen SMTP-Fallback in `mail_outbound.json` und keinen lokalen Datei-Spool mehr.

**`mail_outbound.json` (optional, `var/app/`):** betrifft nur **Absender-Anzeige** (From-Name/Adresse in der App) und ggf. **Reply-To (Fallback)**, **nicht** den Transport. Vorrang hat weiterhin `MAILER_REPLY_TO` in der **Server-Env** (siehe unten). Der PHP-Prozess im `backend` läuft als `HOST_UID` / `HOST_GID` (Compose-Standard oft **1000:1000**). Schreibt das Host-Repo (z. B. per `root`) nach `git pull` als **`root`**, kann **`Permission denied` bei `var/app/mail_outbound.json`** erscheinen. Auf dem **Host** im Klon, UID/GID an eure `HOST_*` in `.env` bzw. Compose anpassen:

`sudo chown -R 1000:1000 backend/var` (oder: `id -u` / `id -g` statt 1000, so wie der `backend`‑Container tatsächlich startet) — danach Speichern in den E‑Mail-Einstellungen erneut testen; **nicht** dauerhaft 777 nötig.

**`.env` auf dem Server** (Compose/Override lädt die Variablen fürs `backend`; siehe `deploy/docker-compose.override.prod.example.yml` + `deploy/docker-compose.prod.env.example`):

- `MAILER_DSN` — z. B. `sendgrid+api://…@default` (HTTPS-API; umgeht typische SMTP-Port-Sperren).
- `MAILER_FROM` — sichtbare Absenderadresse, z. B. `noreply@ematchef.ch` (muss in SendGrid zu eurer Domain/„Single Sender“-Verifikation passen; siehe [Getting started (SendGrid)](https://docs.sendgrid.com/for-developers/sending-email/getting-started-email-api) und [Domain Authentication](https://docs.sendgrid.com/ui/account-and-settings/how-to-set-up-domain-authentication)).
- `MAILER_REPLY_TO` — optional: **Antwort-Adresse** (Reply-To), z. B. `support@ematchef.ch` (Vorrang vor `reply_to_address` in `mail_outbound.json`).

**Wichtig (Pitfall bei Deployments / Docker):** Wird `MAILER_DSN` in der **Container-Umgebung** noch auf `null://null` gesetzt, gewinnt das **gegen** `backend/.env` / `backend/.env.local` (Prozess-Env > Dotenv-Dateien). Dann muss der Container/Service so neu erstellt werden, dass `MAILER_DSN` wirklich den SendGrid-DSN hat.

### SendGrid (API, empfohlen z. B. bei DigitalOcean / blockiertem SMTP 587)

Das Backend enthält **`symfony/sendgrid-mailer`**. Versand läuft über **HTTPS** zur SendGrid-API — **kein** ausgehendes TCP zu fremdem Port 587 nötig (umgeht typische VPS-SMTP-Sperren).

1. **SendGrid:** Konto anlegen, unter API Keys einen Key mit Berechtigung **Mail Send** erzeugen.
2. **Sender Authentication:** Domain (empfohlen) oder **Single Sender Verification** für die Absenderadresse, die in `MAILER_FROM` steht — siehe [Getting started (SendGrid)](https://docs.sendgrid.com/for-developers/sending-email/getting-started-email-api) und [Domain Authentication](https://docs.sendgrid.com/ui/account-and-settings/how-to-set-up-domain-authentication).
3. **Server-`.env` / Compose-`environment`** fürs `backend` (je nach eurem Deploy-Setup; siehe `deploy/docker-compose.prod.env.example`).

   ```env
   MAILER_FROM="noreply@ematchef.ch"
   MAILER_DSN="sendgrid+api://SG.DEIN_API_KEY_HIER@default"
   ```

   Den kompletten Key **URL-encoden**, falls er Sonderzeichen enthält, die in einer URL stören (`:`, `@`, …).

4. **Backend neu starten**, danach **`cache:clear --env=prod`** (Abschnitt 3).
5. **Test im Container:** `docker compose -p ematchef-prod exec backend php bin/console mailer:test deine@mail.de --env=prod`  
   Oder in der App: Superadmin → E-Mail → Testmail.

Nach jeder Änderung an **Mail-Env** (`MAILER_DSN`, ggf. `MAILER_FROM`/`MAILER_REPLY_TO`) oder outbounds JSON: **`cache:clear --env=prod`** (Abschnitt 3) bzw. Backend neu starten. **API-Check:** `GET /api/mail/settings` — `mailer_transport_mode` ist typischerweise **`env`**; wenn `MAILER_DSN` fehlt, **`env_missing`**. Testmail: Superadmin → E-Mail → Einstellungen, oder `POST /api/mail/test-send` mit `{"to":"…"}` (Superadmin / JWT je nach eurem Setup).

### Testmail / Log: typische Fehlerbilder (SendGrid, HTTPS-API)

Der Versand laeuft **nicht** über klassisches SMTP zum Provider-Mailserver, sondern per **HTTPS** zu SendGrid (siehe DSN `sendgrid+api://...`). Wenn Mails fehlschlagen, lohnt sich fast immer zuerst:

- **SendGrid Activity** (Wurde der Request akzeptiert? Bounce/Block/Spam?)  
- **API-Key** hat **Mail Send** und ist **nicht** deaktiviert/rotiert.  
- **From/Domain** ist in SendGrid wirklich **authentifiziert/verifiziert** (Domain Authentication / Single Sender) und entspricht dem, was `MAILER_FROM` / eure App-Absender-Settings erwarten.  
- **`MAILER_DSN` wirklich gesetzt** (siehe Pitfall oben: alte `MAILER_DSN=null://null` in der **Container-Env** kann Dotenv-Dateien ueberstimmen) und danach **`cache:clear --env=prod`**.

Falls es **gar nicht** am SendGrid-Konto liegt: prüft **ausgehendes HTTPS** vom Server (443) und generelle DNS/Proxy/Firewall-Themen — das ist der relevante Netzpfad (nicht SMTP-Port 587).

## 3b. Hostpoint: Frontend bauen und per FTP hochladen (prod + dev)

Das **API-Backend** läuft z. B. auf dem Droplet; die Websites auf Hostpoint sind in der Regel **statische Dateien**.
Dafuer erzeugt das Repo lokal passende Ordner; du laedst **den Inhalt** dieser Ordner in die passenden **Document Roots** hoch (FTP/SFTP).

### Was du auf Hostpoint hochladen musst (nach dem Build)

Fuer Produktion:

- Script: **`scripts/build-hostpoint-deploy-prod.sh`**
- Legacy-Alias (gleiches Ergebnis): **`scripts/build-hostpoint-deploy.sh`**
- Ausgabeordner:
  - `deploy/hostpoint/prod/home/` (**ematchef.ch**)
  - `deploy/hostpoint/prod/app/` (**app.ematchef.ch**)

Fuer Development:

- Script: **`scripts/build-hostpoint-deploy-dev.sh`**
- Ausgabeordner:
  - `deploy/hostpoint/dev/home/` (**dev.ematchef.ch**)
  - `deploy/hostpoint/dev/app/` (**app-dev.ematchef.ch**)

**GitHub Actions FTP:** Secrets `FTP_PATH_MAIN_*` und `FTP_PATH_APP_*` muessen **zwei verschiedene Document Roots** sein (Hauptdomain vs. App-Subdomain). Wenn `FTP_PATH_APP_*` fehlt, auf die Hauptdomain zeigt oder in dieselbe Verzeichnisstruktur wie MAIN zeigt, wirkt die App-Seite leer oder falsch — trotz erfolgreichem Build von `prod/app` bzw. `dev/app`.

Produktion:

| Lokaler Ordner (nach dem Script) | Typisch ins Hostpoint-Webverzeichnis für |
|----------------------------------|-----------------------------------------|
| **`deploy/hostpoint/prod/home/`** (Inhalt inkl. Unterordner) | die **Hauptdomain** bzw. den VHost fuer **`ematchef.ch`** |
| **`deploy/hostpoint/prod/app/`** (Inhalt inkl. Unterordner) | die **Subdomain** / den VHost fuer **`app.ematchef.ch`** |

Development:

| Lokaler Ordner (nach dem Script) | Typisch ins Hostpoint-Webverzeichnis für |
|----------------------------------|-----------------------------------------|
| **`deploy/hostpoint/dev/home/`** (Inhalt inkl. Unterordner) | die **Dev-Hauptdomain** / den VHost fuer **`dev.ematchef.ch`** |
| **`deploy/hostpoint/dev/app/`** (Inhalt inkl. Unterordner) | die **Dev-App-Subdomain** / den VHost fuer **`app-dev.ematchef.ch`** |

Dazu zählt jeweils **`index.html`**, der Ordner **`assets/`** und die Datei **`.htaccess`** ( Apache-Routing für die SPA). Ohne **`.htaccess` funktionieren direkte URLs** (z. B. Reload auf einer App-Route) **nicht**; beim FTP prüfen, ob versteckte Dateien wirklich mit hochgeladen werden.

### Warum `home` und `app` ‚ähnlich viel‘ an Dateien sind

Beide Zielordner (`home` und `app`) stammen von **derselben** Vue-Codebase (die Scripts fuehren jeweils **zweimal** `npm run build` aus, mit leicht unterschiedlichen Umgebungswerten, u. a. fuer die QR-/App-Domain). Es gibt **kein** separates, kleines „nur Marketing“-Bundle — beide Seiten bekommen die **komplette SPA**; Besucher der Hauptdomain laden trotzdem im Wesentlichen dieselbe App-Struktur (Nutzung/Links entscheidet, was tatsächlich abgerufen wird, nicht fehlende Dateien). Eine wirklich schlankere Hauptdomain braeuchte ein **eigenes, kleines** Frontprojekt oder statische Seiten (bewusst anders im Repo gepflegt).

**Hinweis:** Ein nacktes `npm run build` in `frontend/` legt nur **`frontend/dist/`** an. Fuer den Hostpoint-Upload willst du die Build-Skripte nutzen, weil sie getrennte Ausgabeordner erzeugen und die **`.htaccess` aus `scripts/hostpoint-spa.htaccess`** an die richtige Stelle kopieren.

### Build lokal (Node.js 18+)

1. **`frontend/.env.production` prüfen** (API-URL, Domains, ggf. Turnstile) — alles, was da steht, steckt im gebauten JavaScript. Siehe `deploy/TURNSTILE.md`, wenn der Login mit Cloudflare-Widget hängt.

2. Vom **Repo-Root** aus (Beispielpfad `/opt/ematchef/prod` = oft der Server; **auf deinem PC** den Weg zu deinem Klon nehmen, z. B. `~/projekte/eMatChef`):

   ```bash
   cd /opt/ematchef/prod/frontend
   npm ci
   cd /opt/ematchef/prod
   # Produktion
   bash scripts/build-hostpoint-deploy-prod.sh

   # Development
   bash scripts/build-hostpoint-deploy-dev.sh
   ```

3. Anschließend pro Hostpoint-Account die genannten Ordner **vollständig hoch synchronisieren** (bestehende alte `assets/`-Builds ersetzen). Wenn du nur eins der beiden Produkte änderst, reicht **ein** Zielordner; oft werden aber beide Läufe aus dem Skript frisch gebraucht, damit QR- vs. Main-Site-Build konsistent bleibt.

Nichts davon ersetzt ein Backend-Update auf dem API-Server (Abschnitte 1–3) — **API-Änderung ohne neues Frontend** ist ok, **neues Frontend ohne API** muss in `.env.production` zur API passen, die online ist.

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

## 6. Git per SSH (Deploy-Key, kein Passwort bei jedem `pull`)

So arbeitet der Droplet **ohne** HTTPS-Benutzername/Passwort mit GitHub:

1. **Schlüssel auf dem Server erzeugen** (nur für diesen Server, leer lassen bei „Passphrase“ wenn Skripte ohne Eingabe laufen sollen):

   ```bash
   ssh-keygen -t ed25519 -C "ematchef-api-prod" -f ~/.ssh/ematchef_deploy_ed25519 -N ""
   ```

2. **Öffentlichen Schlüssel anzeigen** und kopieren:

   ```bash
   cat ~/.ssh/ematchef_deploy_ed25519.pub
   ```

3. **Auf GitHub:** Repository → **Settings** → **Deploy keys** → **Add deploy key** → Titel z. B. `DO API prod`, Key einfügen, bei nur Lesen **Allow write access** **nicht** anhaken (reicht für `git pull` / `fetch`).

4. **SSH-Config** (`~/.ssh/config`) — ausführlich

   **Was passiert ohne Config?** Wenn du `git clone git@github.com:…` oder `git fetch` ausführst, startet der SSH-Client eine Verbindung zu **`github.com`**. Er sucht dann nach einem passenden **privaten Schlüssel** (Standard: `~/.ssh/id_rsa`, `id_ed25519`, …). Auf einem Server gibt es oft **keinen** solchen Standard-Key — oder der falsche Key wird zuerst probiert, und GitHub lehnt ab. Mit einer **Host-Sektion** in `~/.ssh/config` sagst du explizit: *Für Verbindungen zu GitHub nimm genau diesen Key.*

   **Datei:** `~/.ssh/config` (pro Benutzer, z. B. `root` oder `deploy`). Wenn die Datei noch nicht existiert, wird sie angelegt. Existiert sie schon, **hänge** die folgenden Zeilen an (oder füge sie manuell mit einem Editor ein) — achte darauf, dass du keine **doppelte** `Host github.com`-Sektion erzeugst; dann gewinnt die **erste** passende Regel.

   **Bedeutung der Zeilen:**

   | Direktive | Erklärung |
   |-----------|-----------|
   | `Host github.com` | Trifft auf alle SSH-Verbindungen zu, bei denen du im Terminal **`github.com`** als Ziel schreibst — also genau bei `git@github.com:USER/REPO.git`. |
   | `HostName github.com` | Echter DNS-Name des Servers (hier gleich `github.com`; nötig, wenn du später z. B. `Host github-ematchef` als Alias nutzen willst). |
   | `User git` | GitHub erwartet den Login-Namen **`git`** für Git-over-SSH (nicht deinen GitHub-Benutzernamen). Ohne diese Zeile geht es oft trotzdem, weil die URL `git@github.com` den User schon setzt — explizit ist aber klar und hilft bei Tests mit `ssh github.com`. |
   | `IdentityFile ~/.ssh/ematchef_deploy_ed25519` | **Genau dieser private Key** wird für diese Verbindung verwendet — dein Deploy-Key. Pfad muss zur **privaten** Datei zeigen (ohne `.pub`). |
   | `IdentitiesOnly yes` | Wichtig auf Rechnern mit **mehreren** Keys: SSH darf **nur** die hier genannten Keys anbieten, nicht vorher alle Standard-Keys durchprobieren. So vermeidest du „zu viele fehlgeschlagene Versuche“ / den falschen Key. |

   **Rechte:** `~/.ssh` sollte für andere nicht schreibbar sein; `config` idealerweise **`chmod 600`** (nur Besitzer lesen/schreiben).

   **Anlegen (Append, wenn noch keine github-Sektion existiert):**

   ```bash
   printf '%s\n' \
     'Host github.com' \
     '  HostName github.com' \
     '  User git' \
     '  IdentityFile ~/.ssh/ematchef_deploy_ed25519' \
     '  IdentitiesOnly yes' \
     >> ~/.ssh/config
   chmod 600 ~/.ssh/config
   ```

   **Alternative:** Datei mit `nano ~/.ssh/config` bearbeiten und den Block von Hand einfügen.

   **Mehrere GitHub-Repos mit verschiedenen Keys:** Dann nicht alle unter `Host github.com` pflegen, sondern **Alias-Hosts**, z. B. `Host github-ematchef` mit `HostName github.com` und eigenem `IdentityFile`; die Git-URL wird dann `git@github-ematchef:USER/REPO.git`. Siehe GitHub-Doku zu mehreren Konten/Keys.

5. **Test:** `ssh -T git@github.com` — Meldung wie „Hi …! You've successfully authenticated“.

   **Falls `Permission denied (publickey)`:**

   1. **Verbose-Log** (zeigt, welcher Key angeboten wird): `ssh -vT git@github.com` — nach `Offering public key` bzw. `identity file` schauen.
   2. **Private Key vorhanden?** `ls -la ~/.ssh/ematchef_deploy_ed25519` — Datei muss existieren; Rechte **`chmod 600`** auf die **private** Datei (ohne `.pub`).
   3. **`~/.ssh/config`:** `grep -A5 'Host github.com' ~/.ssh/config` — `IdentityFile` muss **exakt** zum privaten Key passen (Pfad, Tippfehler). Nach Änderungen: keine doppelten widersprüchlichen `Host github.com`-Blöcke.
   4. **Öffentlichen Key auf GitHub:** Repo → **Settings** → **Deploy keys** — der Key aus **`*.pub`** muss **diesem** Repository zugeordnet sein (Deploy-Keys gelten nicht global). Alternativ: Key unter **GitHub → Settings → SSH and GPG keys** (Konto) — dann gilt er für alle Repos des Kontos.
   5. **Richtiger GitHub-User/Org:** Deploy-Key ist an **ein** Repo gebunden; anderes Repo → neuer Deploy-Key oder Konto-SSH-Key.
   6. **Als root vs. anderer User:** Keys liegen unter **`/root/.ssh`** nur für `root`. Wenn du später als `deploy`-User arbeitest, Key und `config` dort anlegen.

6. **Remote auf SSH umstellen** (im Projektverzeichnis, `USER/REPO` anpassen):

   ```bash
   cd /opt/ematchef/prod
   git remote set-url origin git@github.com:USER/REPO.git
   git remote -v
   git fetch origin
   ```

**Hinweis:** Deploy-Keys gelten **pro Repository**. Mehrere Repos brauchen je einen Key oder ein [SSH-Multiplex](https://docs.github.com/en/authentication/connecting-to-github-with-ssh) mit mehreren `Host`-Aliasen.

---

## Hardening-Checklist (Cross-Subdomain Login)

1. **Security-Header aktiv (Backend)**
   - CSP aktiv (inkl. `frame-ancestors 'none'`, `object-src 'none'`)
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Permissions-Policy` ohne Kamera/Mikro/Geo
   - HSTS nur über HTTPS

2. **Rate-Limits auf Auth-Endpunkten**
   - Login-Throttling auf `/api/auth/login_check`
   - Session-Limit auf `/api/auth/session`
   - Refresh-Limit auf `/api/token/refresh`
   - Bei Überschreitung: `429` + `Retry-After`

3. **Refresh-Token-Rotation**
   - `gesdinet_jwt_refresh_token.single_use: true`
   - Ein Refresh-Token kann nur einmal verwendet werden

4. **Monitoring/Alerting**
   - 401/429 in Backend-/Proxy-Logs überwachen
   - Alert bei ungewöhnlichen Peaks (Brute-Force, kaputte Clients)
   - Metriken: Requests/min, 401-Rate, 429-Rate pro Endpoint

5. **E2E-Smoketest vor Deploy**
   - Auf `app.ematchef.ch` einloggen
   - `ematchef.ch` öffnen → Avatar sichtbar
   - `qr.ematchef.ch/i/m/<code>` öffnen → Avatar sichtbar
   - Auf QR „Zum Material“ klicken → Ziel unter `app.ematchef.ch/...`
   - Logout auf App → Main + QR zeigen nicht eingeloggt

---

**Hinweise**

- **Login über mehrere Subdomains (JWT/Refresh-Cookies, CORS, Cookies):** Abschnitt *Einmalig: Geheimnisse & URLs* oben; Compose-Variablen in `docker-compose.yml` + `deploy/develop-droplet.env.example` / Prod-Override.
- `docker-compose.override.yml` und `.env` mit Geheimnissen liegen **nur auf dem Server**, nicht im Git (siehe `.gitignore`).
- Neue **Console-Commands** erscheinen unter `APP_ENV=prod` erst nach **Cache-Warmup** (siehe Abschnitt 3).
- **Nginx** auf dem Host (443 → 8081) ist unabhängig von Docker; bei reinen PHP-Änderungen meist kein `systemctl reload nginx` nötig.
