# Architektur

Stand: Code im Repository. Betriebliche Details: [CONTRIBUTING.md](../CONTRIBUTING.md), [APP-ON-DROPLET.md](./APP-ON-DROPLET.md), [deploy/SERVER-UPDATE.md](../deploy/SERVER-UPDATE.md).

## Laufzeit

| Teil | Technik | Ort |
| --- | --- | --- |
| SPA | Vue 3, Vite, Vue Router, Pinia, Vuetify, vue-i18n, Axios | `frontend/` |
| API | Symfony 6.4, PHP ≥ 8.1, Doctrine ORM | `backend/` |
| DB | PostgreSQL 16 | Service `db` in `docker-compose.yml` |
| Lokal | Docker Compose: `db`, `backend` (:8081), `frontend` (:5173), `nginx` (:80), Adminer (:8082) | Repo-Root |
| Nutzerhilfe | VitePress | `user-docs/` |

Die HTTP-API sind Symfony-Controller mit `#[Route('/api/…')]` (`backend/config/routes.yaml`). `api-platform/core` ist installiert und unter `/api` geroutet; Entities haben keine `ApiResource`-Attribute. Neue Endpunkte als Controller anlegen, nicht als zweite API-Platform-Schicht.

## Frontend

| Bereich | Pfad |
| --- | --- |
| Routen | `frontend/src/router/index.ts` |
| Seiten | `frontend/src/views/` |
| UI | `frontend/src/components/` |
| HTTP | `frontend/src/api/` (ein Modul pro Ressource, `apiClient.ts`) |
| Zustand | `frontend/src/stores/` (Auth, Permissions, …) |
| Texte | `frontend/src/locales/*.json`, Einstieg `frontend/src/i18n.ts` |

Department-App hängt unter `/:departmentId/…` (Aktivitäten, Material, Accounting, Werkstatt, Einstellungen). Weitere Flächen: `/admin-dashboard`, `/supplier/:companyId`, öffentliche Seiten (`/`, `/login`, `/i/…` QR), `/display`.

## Backend

| Bereich | Pfad |
| --- | --- |
| HTTP | `backend/src/Controller/` |
| Fachlogik | `backend/src/Service/` (u. a. `Accounting/`, `Grossanlass/`, `Activity/`, `Material/`) |
| Persistenz | `backend/src/Entity/`, `backend/src/Repository/` |
| Schema | `backend/migrations/` |
| Security | `backend/config/packages/security.yaml`, `backend/src/Security/` |

## Authentifizierung

Stateless JWT (Lexik) plus Refresh-Token (`gesdinet/jwt-refresh-token-bundle`).

- Login: `POST /api/auth/login_check` (E-Mail + Passwort), Throttling.
- Refresh: `/api/token/refresh`.
- Öffentlich ohne JWT: Registrierung, Verify, Passwort-Reset und OAuth-Login/-Callback unter `/api/auth/`, `/api/public`, `/api/health` und `/api/token/refresh`. MiData-Verknüpfung (`/api/auth/link/midata`) erfordert einen eingeloggten User.
- Übrige `/api`- und `/media`-Routen: `ROLE_USER`.
- Google, MiData und Microsoft haben eigene Firewalls (`/api/auth/google`, `/api/auth/midata`, `/api/auth/microsoft`). MiData verwendet OIDC-Discovery und speichert nur `(provider, sub)` als ExternalIdentity; Provider-Tokens bleiben transient.
- Die MiData-OAuth-Anforderung umfasst `openid email with_roles groups people`. `with_roles` liefert Rollen mit Gruppen-ID, Typ und Berechtigungen; Aktivitätsdaten wie `start_on`/`end_on` kommen aus der read-only Hitobito JSON:API. Der kurzlebige OAuth-Access-Token bleibt im Callback-Speicher und wird nicht persistiert.
- `HitobitoApiClient` verwendet Symfony HttpClient und provider-keyed Issuer-Konfiguration. `GET /api/groups/{id}` und `GET /api/roles?filter[person_id]=…` werden als JSON:API gelesen; Rollenlisten folgen sicheren same-origin-Paginierungslinks. Die Parent-Chain-Prüfung vergleicht nur externe IDs, stoppt nach höchstens 64 Ebenen und bestätigt bei Lücken, Zyklen oder unbekannten Gruppen keine Zugehörigkeit. API-/Berechtigungsfehler bleiben explizite Fehler.
- `PbsGroupTypeClassifier` klassifiziert bekannte PBS-Gruppentypen nur als Organisations-, Department- oder Group-Kandidaten. Er erzeugt keine eMatChef-Strukturen und ändert keine Mitgliedschaften oder Rollen. Der Login zeigt CeviDB und JublaDB als „demnächst“; für sie sind keine OAuth-Flows eingerichtet. Join-Code/MiData-Verifikation ist noch nicht implementiert.
- Passwort-Hashing: Symfony `auto` auf `User`.

Department-Rechte kommen nicht aus dem JWT allein, sondern aus `Membership.role` im angefragten Department. Katalog: `DepartmentRole`, Zuweisungslogik `MembershipRoleCatalog`.

## Hosts (lokal / produktiv)

Lokal über Nginx und `*.ematchef.test` (`APP_FRONTEND_URL`, `APP_PUBLIC_QR_URL`, `VITE_DEVICES_HOST`). Produktiv getrennte Flächen, u. a. App, QR (`qr.ematchef.ch`), Geräte (`devices.ematchef.ch`), Nutzerhilfe (`docs.ematchef.ch`), Weblate (`translate.ematchef.ch`). Marketing-Site und App-Deploy sind getrennte Workflows — siehe CONTRIBUTING, nicht hier nachbauen.

## Externe Dienste (im Code/Config belegt)

| Dienst | Verwendung |
| --- | --- |
| Amazon SES über Symfony Mailer (`MAILER_DSN`) | Transaktionsmail — [mail/README.md](./mail/README.md) |
| Weblate | App-UI-Locales — [TRANSLATION.md](./TRANSLATION.md) |
| Google OAuth | Login |
| Microsoft OAuth | Login |
| Cloudflare Turnstile | Registrierung (`TURNSTILE_SKIP_VERIFY` nur lokal) |
| Gmail- und Outlook-OAuth-Callback-Controller | Grossanlass-Postfach (`GrossanlassGmail*`, `GrossanlassOutlook*`) |
| `GET /api/health` | Uptime ohne JWT |

Keine Secrets in Docs oder Compose committen. Droplet-Env liegt ausserhalb von `docker-compose.yml` (Kommentar dort und `deploy/`).
