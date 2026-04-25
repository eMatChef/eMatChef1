# Server: aktuellen Stand holen, Cache, Start

Für den **API-Server** (z. B. DigitalOcean), Projektverzeichnis z. B. `/opt/ematchef/prod`.

**Git auf dem Droplet:** Per SSH einloggen, ins Repo-Verzeichnis wechseln, dort die Befehle unten ausführen (die IDE führt kein Git auf deinem Server aus).

```bash
ssh BENUTZER@DROPLET_HOST_ODER_IP
cd /opt/ematchef/prod   # anpassen, falls dein Pfad anders ist
```

### Schnell: ein Skript statt vieler Zeilen

Im Repo liegt **`deploy/prod-update.sh`**: holt `origin/main` (per `reset` oder `pull`) und startet **`db` + `backend`** (ohne langsames `docker compose --build`, außer `EMATCHEF_COMPOSE_BUILD=1`). Beispiel auf dem Droplet:

```bash
cd /opt/ematchef/prod
chmod +x deploy/prod-update.sh
EMATCHEF_PROD_ROOT=/opt/ematchef/prod ./deploy/prod-update.sh reset
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

## 3b. Hostpoint: Frontend bauen und per FTP hochladen

Das **API-Backend** läuft z. B. auf dem Droplet; die **Websites** `ematchef.ch` (Marketing/Landing) und `app.ematchef.ch` (App inkl. QR) sind in der Regel **statische Dateien auf Hostpoint**. Dafür erzeugt das Repo lokal passende Ordner; du lädst **den Inhalt** dieser Ordner in die passenden **Document Roots** hoch (FTP/SFTP).

### Was du auf Hostpoint hochladen musst (nach dem Build)

Zuerst lokal bauen (siehe unten) — Script **`scripts/build-hostpoint-deploy.sh`**. Dann entstehen zwei Ordner:

| Lokaler Ordner (nach dem Script) | Typisch ins Hostpoint-Webverzeichnis für |
|----------------------------------|-----------------------------------------|
| **`deploy/hostpoint/ematchef.ch/`** (alle Dateien inkl. Unterordner) | die **Hauptdomain** bzw. den VHost für **`ematchef.ch`** (manchmal `public_html`, `htdocs` oder `www` — je nach Hostpoint-Menü) |
| **`deploy/hostpoint/app.ematchef.ch/`** (alle Dateien inkl. Unterordner) | die **Subdomain** / den VHost für **`app.ematchef.ch`** |

Dazu zählt jeweils **`index.html`**, der Ordner **`assets/`** und die Datei **`.htaccess`** ( Apache-Routing für die SPA). Ohne **`.htaccess` funktionieren direkte URLs** (z. B. Reload auf einer App-Route) **nicht**; beim FTP prüfen, ob versteckte Dateien wirklich mit hochgeladen werden.

### Warum `ematchef.ch` und `app.ematchef.ch` ‚ähnlich viel‘ an Dateien sind

Beide Zielordner stammen von **derselben** Vue-Codebase (`scripts/build-hostpoint-deploy.sh` führt **zweimal** `npm run build` aus, mit leicht unterschiedlichen Umgebungswerten, u. a. **QR-Subdomain** im zweiten Lauf). Es gibt **kein** separates, kleines „nur Marketing“-Bundle — beide Seiten bekommen die **komplette SPA**; Besucher von `ematchef.ch` laden trotzdem im Wesentlichen dieselbe App-Struktur (Nutzung/Links entscheidet, was tatsächlich abgerufen wird, nicht fehlende Dateien). Eine wirklich schlankere Hauptdomain bräuchte ein **eigenes, kleines** Frontprojekt oder statische Seiten (bewusst anders im Repo gepflegt).

**Hinweis:** Ein nacktes `npm run build` in `frontend/` legt nur **`frontend/dist/`** an. Für den Hostpoint-Upload willst du das **Build-Skript** nutzen, weil es **zwei** getrennte Ausgabeordner und die **`.htaccess` aus `scripts/hostpoint-spa.htaccess`** an die richtige Stelle kopiert.

### Build lokal (Node.js 18+)

1. **`frontend/.env.production` prüfen** (API-URL, Domains, ggf. Turnstile) — alles, was da steht, steckt im gebauten JavaScript. Siehe `deploy/TURNSTILE.md`, wenn der Login mit Cloudflare-Widget hängt.

2. Vom **Repo-Root** aus (Beispielpfad `/opt/ematchef/prod` = oft der Server; **auf deinem PC** den Weg zu deinem Klon nehmen, z. B. `~/projekte/eMatChef`):

   ```bash
   cd /opt/ematchef/prod/frontend
   npm ci
   cd /opt/ematchef/prod
   bash scripts/build-hostpoint-deploy.sh
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

**Hinweise**

- `docker-compose.override.yml` und `.env` mit Geheimnissen liegen **nur auf dem Server**, nicht im Git (siehe `.gitignore`).
- Neue **Console-Commands** erscheinen unter `APP_ENV=prod` erst nach **Cache-Warmup** (siehe Abschnitt 3).
- **Nginx** auf dem Host (443 → 8081) ist unabhängig von Docker; bei reinen PHP-Änderungen meist kein `systemctl reload nginx` nötig.
