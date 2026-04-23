# Server: aktuellen Stand holen, Cache, Start

Für den **API-Server** (z. B. DigitalOcean), Projektverzeichnis z. B. `/opt/ematchef/prod`.

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

## 3a. E-Mail / SMTP (Prod)

**Reihenfolge im Backend:** Zuerst **`MAILER_DSN`** aus der Umgebung (wenn nicht `null://…`), sonst vollständiges SMTP aus **`var/app/mail_outbound.json`** (Superadmin → Mail-Einstellungen in der App), sonst **Datei-Spool** unter `var/app/mail_spool`.

**`.env` auf dem Server** (Compose lädt sie für `backend`):

- `MAILER_FROM` — sichtbare Absenderadresse, z. B. `noreply@ematchef.ch`.
- `MAILER_REPLY_TO` — optional: **Antwort-Adresse** (Reply-To), z. B. `support@ematchef.ch`. Hostpoint kann das nicht „global“ für alle Mails setzen — das macht die App pro Versand. Ohne Env: optional `reply_to_address` in `mail_outbound.json` (Superadmin → Mail-Einstellungen, PATCH `reply_to_address`).
- `MAILER_DSN` — Symfony-Mail-DSN, z. B. Hostpoint STARTTLS:  
  `smtp://MAILBOX%40ematchef.ch:PASSWORT@smtp.mail.hostpoint.ch:587?encryption=tls`  
  (Zeichen in User/Pass **URL-encoden**, z. B. `@` → `%40`.) Beispiele: `deploy/docker-compose.prod.env.example`.

**Fallback JSON:** Nur wenn `MAILER_DSN` leer oder `null://null` ist, greift SMTP aus `mail_outbound.json`. Voraussetzung: schreibbares `var/app/` im Container.

Nach Änderung an `.env` oder `mail_outbound.json`: **`cache:clear --env=prod`** (siehe Abschnitt 3) bzw. Backend neu starten.

**Test:** Als Superadmin `POST /api/mail/test-send` mit Body `{"to":"deine@mail.de"}` (oder gleichwertig aus dem Admin-UI, falls vorhanden). Modus prüfen: `GET /api/mail/settings` (`mailer_transport_mode`: `env`, `smtp_json` oder `file_spool`).

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
