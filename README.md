# eMatChef v4.01

eMatChef ist ein Tool zur Materialverwaltung und Vermietung von Material.
Es unterstützt den Bestand, Bewegungen und Rollenrechte in einem Vue-Frontend mit Symfony-Backend.

## Schnellstart

### Voraussetzungen
- Docker + Docker Compose
- Node.js 18+ (nur fuer lokale Frontend-Entwicklung)

### Start
Damit `backend/vendor` und `frontend/node_modules` auf dem Host **nicht** als `root` angelegt werden (sonst brauchst du später `sudo chown` fuer `composer`/`npm`):

```bash
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose up -d
```

Wenn deine UID/GID dauerhaft 1000 sind (typisch unter WSL/Linux), reicht auch `docker compose up -d` ohne `export`.

**Postgres auf dem Host (DBeaver, psql von außerhalb Docker):** Vorlage `docker-compose.override.local-ports.example.yml` nach `docker-compose.override.yml` kopieren (ist gitignored), dann Compose neu starten – sonst ist die DB nur intern unter Service `db` erreichbar.

### Lokale URLs (App + QR wie in Produktion)

- **App:** http://app.localhost
- **QR (öffentliche Subdomain):** http://qr.localhost

Moderne Browser lösen `*.localhost` auf `127.0.0.1` auf, **ohne** `/etc/hosts`. Lokale Vite-Defaults stehen in `frontend/.env.development`; Production-Builds nutzen `frontend/.env.production` (API/ Domains für Live).

### Frontend lokal entwickeln
```bash
cd frontend
npm install
npm run dev
```

### Backend vorbereiten
```bash
cd backend
composer install
```

## Services
- App (Nginx, empfohlen): http://app.localhost (auch http://localhost oder http://qr.localhost)
- Frontend (Vite direkt): http://localhost:5173
- Backend API: http://localhost:8081 (unter `/api` auch ueber Nginx auf app.localhost / qr.localhost)
- PostgreSQL: nur im Docker-Netz (Host-Zugriff optional per `docker-compose.override.local-ports.example.yml`)
- Adminer: http://localhost:8082

## Wichtige Hinweise
- Keine `.env`-Dateien oder `*.pem`-Keys committen.
- Lokale/Dev-Daten liegen ausserhalb des Repos (z. B. `backend/data/`).

## API-Server (Production, z. B. DigitalOcean)
Vorlagen: `deploy/docker-compose.override.prod.example.yml` → als `docker-compose.override.yml` kopieren; Geheimnisse mit **`bash deploy/init-prod-env.sh`** erzeugen (legt `.env` mit zufälligen Werten an). Manuell: `deploy/docker-compose.prod.env.example`. `docker-compose.override.yml` am Repo-Root ist gitignored.

Auf dem Server (nach SSH ins Repo): **`bash deploy/server-prod-apply.sh`** — holt per `git pull` den neuesten Stand, legt Override/`.env` nur an falls nötig, startet `db`+`backend`.

Falls **`doctrine:migrations:migrate`** auf einer **leeren** DB fehlt (z. B. `relation … does not exist`): einmalig **`bash deploy/bootstrap-empty-postgres.sh`** (löscht die DB-Inhalte und erzeugt das Schema aus den Entities, markiert Migrationen als erledigt). Nur bei **frischem** Volume / ohne wichtige Daten.

## Nützliche Backend-Befehle
```bash
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:migrate
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:status
```

## Org-Subset Seed (gezielte Startdaten)

Fuer den gezielten Start mit nur bestimmten Organisationen (z. B. `org_js000000`, `GLOBALORG001`) gibt es zwei Commands:

```bash
# Export auf Quell-Umgebung
docker exec ematchef_v401-backend-1 php bin/console app:org-subset:export --org=org_js000000 --org=GLOBALORG001 --with-global-templates --output=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json

# Import auf Ziel-Umgebung (inkl. Superadmin)
docker exec ematchef_v401-backend-1 php bin/console app:org-subset:import --file=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json --ensure-superadmin
```

## Crowdin (i18n)

- **Git → Crowdin automatisch:** Bei jedem Push auf **`main`**, der `frontend/src/locales/**` oder `crowdin.yml` ändert, läuft die Action **Translations (Crowdin)** mit `crowdin upload sources` und `crowdin upload translations` (Repo-Stand wird in Crowdin gespiegelt). Kein manueller Upload nötig. Die Action checkt **immer `main` aus** (auch bei manuellem Workflow-Run), damit nicht versehentlich ein anderer Git-Branch nach Crowdin geschoben wird.
- **`crowdin.yml` → `branch: main`:** bezieht sich auf **Crowdin-Versions-/Projekt-Branches**, nicht auf Git. Wenn euer Crowdin-Projekt **keine** Branches nutzt und die CLI damit fehlschlägt, die Zeile `branch: main` in `crowdin.yml` entfernen oder in Crowdin einen Branch `main` anlegen.
- **Crowdin → Git:** wie bisher per Zeitplan oder manuell **download** (PR `chore/crowdin-translations`). `pull_request_title` / `commit_message` stehen in `crowdin.yml` (analog [ecamp/ecamp3 crowdin.yml](https://github.com/ecamp/ecamp3/blob/devel/crowdin.yml); dort ist **en** die Quelle, bei uns bleibt **de** die Quelle, Ziele sind `%locale%.json`).
- In Crowdin: **Settings → Languages → Language mapping** — pro Sprache **Custom code** so setzen, dass die **Dateinamen** zu `frontend/src/locales/` passen, z. B. Englisch → `en` (nicht `en-US`), analog **fr**, **it**. Zusaetzlich ist in `crowdin.yml` unter `languages_mapping.locale` ein Mapping fuer `en-US`/`fr-FR`/`it-IT` → kurze Dateinamen.
- Fuer Ziele wie **Rumantsch** bzw. **de-pfadi** / **de-cevi** dieselben **Codes** wie in `frontend/src/config/languages.ts` waehlen: `ch-rm`, `de-pfadi`, `de-cevi` (nur in Crowdin, sobald die Sprache als Ziel existiert; jeweils **Custom language** bzw. Code-Feld).

Weitere Details: lokal in `docs/` (falls im Arbeitsbaum vorhanden) oder in `deploy/SERVER-UPDATE.md` bei Deployment-Hinweisen.