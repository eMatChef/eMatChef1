# Entwicklung

Git, Branches, PR, CI/CD: [CONTRIBUTING.md](../CONTRIBUTING.md). Übersetzungen: [TRANSLATION.md](./TRANSLATION.md). Diese Datei nur für den lokalen Arbeitsablauf. Öffentliche Benutzer- und Entwickleranleitung (Testumgebung, Demo-Konten): [docs.ematchef.ch](https://docs.ematchef.ch), Quelle im Repository `eMatChef/documentation`.

## Struktur

```text
frontend/     Vue-SPA
backend/      Symfony-API, Entities, Migrationen, PHPUnit
docs/         Entwicklerdoku (dieses Verzeichnis)
deploy/       Droplet, Caddy, Server-Updates
docker/       Nginx, lokale Certs
scripts/      Locales, Migrationstest, Deploy-Builds, Git-Hooks
.github/      CI/CD
```

Lokal: `docker compose up` im Repo-Root (Postgres, Backend, Frontend, Nginx). Host-UID setzen, damit Volumes nicht root gehören — Kommentar in `docker-compose.yml`.

## Wo eine Änderung hingehört

| Änderung | Zuerst suchen, dann erweitern |
| --- | --- |
| HTTP-Endpunkt | bestehender Controller unter `backend/src/Controller/`, Logik in `backend/src/Service/` |
| Tabelle / Beziehung | Entity + Doctrine-Migration in `backend/migrations/` |
| API-Aufruf im Client | `frontend/src/api/<ressource>.ts` |
| Seite | `frontend/src/views/…` und Route in `frontend/src/router/index.ts` |
| Wiederverwendbare UI | `frontend/src/components/`, Konventionen [ui/vuetify-standards.md](./ui/vuetify-standards.md) |
| Sichtbarer Text | zuerst `frontend/src/locales/de.json` |
| Department-Rolle | `DepartmentRole`, `MembershipRoleCatalog`, Router-Guards in `useDepartmentMemberRole.ts` |

Kein paralleles Modul, wenn Controller, Service oder View für dasselbe Thema schon existieren.

## Commands

Backend im Compose-Container (`backend` ist der Service-Name):

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend ./vendor/bin/phpunit
docker compose exec backend ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Migrationen gegen eine Wegwerf-DB, ohne die lokale `mvdb` zu verändern:

```bash
./scripts/test-migrations.sh
```

Frontend (auf dem Host in `frontend/`, oder im Container wenn der Stack läuft):

```bash
npm run test          # Vitest
npm run lint
npm run typecheck
npm run build
```

Locales (CI-Job „Locales“):

```bash
bash scripts/check-locales.sh
```

Playwright gegen die laufende Develop-App, nicht gegen localhost: [E2E.md](./E2E.md).

Git-Hooks (Pre-Push ohne Playwright, Prepare-Commit-Msg): `./scripts/install-git-hooks.sh`.

## Lokalen Docker-Stack auf einen Worktree umstellen

Der lokale Stack (`https://app.ematchef.test`) bindet `./backend` und `./frontend` per Bind-Mount ein; Datenbank (`ematchef_postgres_data`), `backend_vendor` und `frontend_node_modules` sind benannte Volumes und bleiben erhalten. Um einen anderen Worktree zu zeigen, im Worktree ausführen (Projektname kommt aus `COMPOSE_PROJECT_NAME` der `.env`):

```bash
cp -a <haupt>/.env <haupt>/docker-compose.override.yml .
cp -a <haupt>/backend/.env.local backend/
mkdir -p backend/config/jwt docker/certs && cp -a <haupt>/backend/config/jwt/. backend/config/jwt/ && cp -a <haupt>/docker/certs/. docker/certs/
docker compose -p ematchef up -d --no-deps --force-recreate backend frontend
docker exec ematchef-nginx-1 nginx -s reload   # Upstream-IPs neu auflösen, sonst 502
```

- Der Entrypoint des Backends führt beim Start `doctrine:migrations:migrate` gegen die gemeinsame Datenbank aus. Kennt der Worktree Migrationen nicht, die bereits angewendet sind (anderer Branch), meldet Doctrine nur eine Warnung.
- Zurück zum Hauptworktree: derselbe `up`-Befehl im Hauptverzeichnis.
- Kein `node_modules`-Symlink im Worktree anlegen (liegt im Volume); Tests im Container ausführen: `docker exec ematchef-frontend-1 sh -c "cd /app && npx vitest run"`.

## Tests

| Suite | Ort | CI |
| --- | --- | --- |
| PHPUnit | `backend/tests/` | Job Backend in `.github/workflows/ci.yml` |
| PHPStan | `backend/` | derselbe Job |
| Vitest | `frontend/src/**/*.spec.ts` | Job Frontend (`npm test`) |
| Playwright-Smoke | `frontend/e2e/` | `ci.yml` und `e2e-smoke.yml`, siehe [E2E.md](./E2E.md) |

CI läuft auf Push/PR gegen `develop`, `staging`, `prod` und filtert Frontend/Backend/Locales.

## Migrationen

Neue Schemaänderung nur als Doctrine-Migration unter `backend/migrations/`. Der Backend-Container führt Migrationen beim Start aus (`backend/docker-entrypoint.sh`). Nicht an der laufenden Dev-DB „vorbeifixen“, ohne eine Migration einzuchecken.

## Übersetzungen

Neue UI-Keys in `frontend/src/locales/de.json`. Weitere Sprachen und Org-Deltas kommen über Weblate, nicht als freie neue Locale-Dateien. Erlaubte Codes: [TRANSLATION.md](./TRANSLATION.md).
