# 02 Systemarchitektur

## Komponenten

| Komponente | Technik (Version laut Konfiguration) | Ort |
| --- | --- | --- |
| SPA | Vue 3, TypeScript, Vite 6, Vue Router 4, Pinia, vue-i18n 11, Vuetify 4, Tailwind 3, Axios (`frontend/package.json`) | `frontend/` |
| API | Symfony 6.4 (`symfony/framework-bundle ^6.4`), PHP 8.4 im Container (`backend/Dockerfile`; `composer.json` verlangt `>=8.1`) | `backend/` |
| Datenbank | PostgreSQL 16 (`docker-compose.yml`, `backend/config/packages/doctrine.yaml`) | Service `db` |
| Reverse Proxy lokal | nginx | `docker/nginx/default.conf` |
| CI | GitHub Actions, PHP 8.4 / Node 22 | `.github/workflows/ci.yml` |

`IMPLEMENTIERT` Backend-Pakete mit Architekturwirkung (`backend/composer.json`): Doctrine ORM 2 + Migrations, `lexik/jwt-authentication-bundle`, `gesdinet/jwt-refresh-token-bundle`, `nelmio/cors-bundle`, `symfony/rate-limiter`, `symfony/mailer` (+ Amazon-Transport), `symfony/http-client`, `spomky-labs/otphp` (TOTP), `dompdf/dompdf`, `endroid/qr-code`, `mikehaertl/php-pdftk`, `api-platform/core`.

`IMPLEMENTIERT` API Platform ist installiert und unter `/api` geroutet (`backend/config/routes.yaml`), die Endpunkte sind aber attributbasierte Controller (`#[Route('/api/…')]`). `ENTSCHIEDEN` (`AGENTS.md`): keine API-Platform-Ressourcen für Funktionen, die Controller nutzen.

## Verzeichnisstruktur (Auszug)

```text
backend/
  src/Controller/      HTTP-Endpunkte (121 Dateien, flach; Unterordner Public/)
  src/Service/         Fachlogik, nach Bereichen: Grossanlass/, Auth/, Accounting/, Display/, Media/, Mail/, Admin/, Security/, Demo/ …
  src/Entity/          Doctrine-Entities (160), Attribute-Mapping
  src/Repository/      24 Repositories (nicht alle Entities haben eines)
  src/Security/        UserProvider, UserChecker, LoginSuccessHandler, Voter/SupplierCompanyVoter
  src/EventSubscriber/ Admin-MFA-Guard, Security-Header, Rate-Limit, JWT-Sitzung, GA-Gates
  src/Command/         Konsolen-Commands (Demo, Bootstrap, Retention, Import/Export)
  src/Enum/            DepartmentRole, AuthMethod
  migrations/          Doctrine-Migrationen (Namensschema VersionYYYYMMDDHHMMSS)
  config/packages/     security.yaml, doctrine.yaml, lexik_jwt…, gesdinet…, nelmio_cors.yaml, mailer.yaml
  tests/               PHPUnit (Command, Controller, Entity, Service, Security, Integration, Wiring …)
frontend/
  src/api/             Axios-Module je Fachbereich, zentral apiClient.ts
  src/router/index.ts  Gesamte Routen und globaler beforeEach-Guard
  src/stores/          Pinia-Stores (auth, permissions, toast, confirm, stepUp, businessClock …)
  src/composables/     Wiederverwendbare Logik (use*)
  src/components/      Nach Bereich; form/base (E*-Basiskomponenten), layout, common
  src/views/           Seiten nach Bereich (grossanlass/, supplier/, devices/, profile/ …)
  src/locales/         i18n-JSON (de, Varianten, en, fr …)
  e2e/                 Playwright
docker/nginx/          lokaler Proxy
deploy/                Server-Overrides und Update-Skripte
docs/                  historische Entwickler-Doku
knowledge/             Wissensbasis (diese Struktur)
```

## Laufzeit lokal (`docker-compose.yml`)

| Service | Port | Rolle |
| --- | --- | --- |
| `db` | intern | PostgreSQL 16, DB `mvdb` |
| `backend` | 8081 | `php -S` mit `public/router.php`; Entrypoint (`backend/docker-entrypoint.sh`): auf DB warten → `composer install` → `doctrine:migrations:migrate` → JWT-Keypair erzeugen |
| `frontend` | 5173 | `npm run dev` (Vite) |
| `nginx` | 80 | `*.ematchef.test`: `/api` und `/media` → backend, alles andere → frontend |
| `adminer` | 8082 | DB-Werkzeug |

`ENTSCHIEDEN` (`AGENTS.md`): Befehle und Tests im vorhandenen Docker-Setup ausführen, kein paralleler lokaler PHP-/Node-/DB-Stack.

`UNKLAR` Produktiver Betrieb (Webserver/Proxy, Prozessmodell): `deploy/` und `docs/APP-ON-DROPLET.md` verweisen auf Caddy vor dem Backend; nicht im Detail geprüft. Der lokale `php -S`-Betrieb gilt nur für Entwicklung.

## Hosts und Kommunikation

```text
Browser (SPA, Origin app.*) ──HTTPS/JSON, Cookies──► /api/* (Symfony) ──Doctrine──► PostgreSQL
        │                                               │
        └─ gleiche SPA, host-spezifisches Routing:      ├─ Mailer (SES/SMTP via MAILER_DSN)
           qr.* (QR-Auflösung /i/…)                     ├─ OAuth-Login: Google, MiData (Hitobito); Gmail/Outlook (GA-Postfach)
           devices.* (Lager-Geräte-UI)                  ├─ Turnstile (Registrierung/Join)
           display.* (Infoscreen-Kiosk)                 └─ Dateien: var/uploads (MediaStorageService)
```

- `IMPLEMENTIERT` Eine einzige SPA bedient alle Hosts; der Router verzweigt per Host (`isDisplayHost()`, `applyQrHostRedirects`, `applyDevicesHostRouting` am Anfang von `router.beforeEach`, `frontend/src/router/index.ts`).
- `IMPLEMENTIERT` Cookies (`BEARER`, Refresh) gelten über `AUTH_COOKIE_DOMAIN` (lokal `.ematchef.test`) für alle Subdomains (`backend/config/packages/lexik_jwt_authentication.yaml`).
- `IMPLEMENTIERT` CORS per Regex aus `CORS_ALLOW_ORIGIN` für `^/api` und `^/media` (`backend/config/packages/nelmio_cors.yaml`).
- `IMPLEMENTIERT` Kein Message-Bus/Queue: keine `symfony/messenger`-Abhängigkeit; periodische Aufgaben sind Konsolen-Commands (`backend/src/Command/`, z. B. `MediaRetentionCommand`, `DisplayDeviceExpiryRemindersCommand`), laut `docs/authentication-security.md` per Host-Cron eingeplant (`UNKLAR`, ob produktiv eingerichtet).

## Qualitätssicherung (`.github/workflows/ci.yml`)

- Frontend: ESLint, Vitest (`npm test`), `npm run build` (inkl. `vue-tsc`).
- Backend: `composer validate`, Migration gegen Test-DB, PHPUnit (`--fail-on-skipped`), PHPStan (`backend/phpstan.neon`).
- E2E: Playwright-Smoke (`frontend/e2e/`).
- Weitere Workflows: Deploy je Umgebung (develop/staging/prod), CodeQL, Locale-Check (`scripts/check-locales.sh`).

## Offen

- `UNKLAR` Produktives Laufzeitmodell des Backends (siehe oben).
