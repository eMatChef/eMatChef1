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
- Sitzungen: Jeder Login (Passwort, Google, MiData) erzeugt genau eine serverseitige `UserSession`; deren UUID steht als Claim `sid` im JWT (`JwtSessionSubscriber`, `JWT_CREATED`). Refresh-Tokens (`refresh_tokens.session_id`) gehören zur Sitzung; ein Refresh (Gesdinet `single_use`) behält die `sid`. Jeder JWT-Request prüft in `JWT_DECODED`, dass die Sitzung existiert, dem User gehört und nicht widerrufen ist (sonst 401); zusätzlich prüft `App\Security\UserChecker` auf Login, Refresh und API den Account-State `active`. Logout widerruft die aktuelle Sitzung, Passwortänderung alle anderen, Passwort-Reset und Deaktivierung alle (jeweils samt Refresh-Tokens, über `UserSessionManager`/`RefreshTokenRevoker`). Ein neuer Login erzeugt eine weitere Sitzung, ohne andere zu widerrufen; MiData-Verknüpfen stellt keine neuen Tokens aus. `last_seen_at` wird höchstens alle 5 Minuten geschrieben.
- Übergang (befristet, `LegacySessionCutoff`): JWTs ohne `sid` werden nur akzeptiert, wenn sie vor der Ausführung der Migration `Version20261005120000` ausgestellt wurden; Refresh-Tokens ohne Sitzung nur, wenn sie davor erstellt wurden — sie erhalten beim Refresh eine Sitzung mit `auth_method = legacy`. Nach Ablauf der Refresh-TTL (30 Tage) entfernen und `session_id` auf `NOT NULL` setzen.
- Öffentlich ohne JWT: Registrierung, Verify, Passwort-Reset und OAuth-Login/-Callback unter `/api/auth/`, `/api/public`, `/api/health` und `/api/token/refresh`. MiData-Verknüpfung (`/api/auth/link/midata`) erfordert einen eingeloggten User.
- Übrige `/api`- und `/media`-Routen: `ROLE_USER`.
- Google, MiData und Microsoft haben eigene Firewalls (`/api/auth/google`, `/api/auth/midata`, `/api/auth/microsoft`). MiData verwendet OIDC-Discovery und speichert nur `(provider, sub)` als ExternalIdentity; Provider-Tokens bleiben transient. Der normale MiData-Login (`/api/auth/midata`) sendet `prompt=login`: Hitobito meldet eine bestehende MiData-Browser-Session ab und verlangt eine erneute Anmeldung, damit keine fremde Session unbemerkt übernommen wird. Link- und Onboarding-Flow (`/api/auth/link/midata`) senden kein `prompt`, weil der Callback dort die MiData-Person gegen die verknüpfte Identität prüft. Ein eMatChef-Logout beendet die MiData-Session nicht; ein Remote-Logout über den `end_session_endpoint` ist bewusst nicht umgesetzt (er bräuchte ein gespeichertes ID-Token).
- Die MiData-OAuth-Anforderung umfasst `openid email with_roles groups people`. Die `/oauth/userinfo`-Antwort wird im Callback zu einem typisierten DTO normalisiert. `with_roles` liefert unter anderem `primary_group_id` und Rollen mit `group_id`, `group_name`, `role`, `role_class`, `role_name` und `permissions`; sie enthält keine verlässlichen `start_on`/`end_on`-Aktivitätsdaten. Der kurzlebige OAuth-Access-Token bleibt im Callback-Speicher und wird nicht persistiert.
- `HitobitoApiClient` verwendet Symfony HttpClient und provider-keyed Issuer-Konfiguration. `GET /api/groups/{id}` und `GET /api/roles?filter[person_id]=…` werden als JSON:API gelesen; Gruppen-Parent- und Layer-IDs werden aus expliziten Attribut-IDs oder, falls diese fehlen, aus Relationship-`data.id` gelesen. Eine vorhandene Relationship ohne `data`-Key (z. B. nur mit `meta`) liefert keine zusätzliche ID und widerspricht einer vorhandenen Attribut-ID nicht. Rollenlisten folgen sicheren same-origin-Paginierungslinks. Die Rollen-JSON:API liefert detailliertere Role-Ressourcen inklusive `start_on`/`end_on` und wird für zeitbezogene Prüfungen wie Phase-5-Join-Verifikation verwendet, nicht zusätzlich für die primäre Rollen-Erkennung beim normalen Onboarding. Die Parent-Chain-Prüfung vergleicht nur externe IDs, stoppt nach höchstens 64 Ebenen und bestätigt bei Lücken, Zyklen oder unbekannten Gruppen keine Zugehörigkeit. API-/Berechtigungsfehler bleiben explizite Fehler.
- `PbsGroupTypeClassifier` klassifiziert bekannte PBS-Gruppentypen nur als ignoriert (Root), Organisations- (Bund), Department- (Kantonalverband, Region, Abteilung) oder Group-Kandidaten (Stufen). Er erzeugt keine eMatChef-Strukturen und ändert keine Mitgliedschaften oder Rollen. Der Login zeigt CeviDB und JublaDB als „demnächst“; für sie sind keine OAuth-Flows eingerichtet.
- Ein Department-Join-Code identifiziert nur das Department. Beim MiData-OAuth-Callback wird ein aus dem HMAC-signierten OAuth-State wiederhergestellter Join-Code serverseitig erneut aufgelöst und mit dem kurzlebigen Access-Token geprüft: Aktive MiData-Rollen müssen exakt der gemappten MiData-Abteilung entsprechen oder nachweisbar darunter liegen. Bei Bestätigung wird eine fehlende Membership mit Rolle `u` erstellt; vorhandene Membership-Rollen bleiben unverändert. Ein vorhandener offener JoinRequest wird ohne erfundenen menschlichen Reviewer abgeschlossen. Die Phase-5-Prüfung gibt ihre aktive, auf diese Abteilung begrenzte Rollenliste an `MiDataGroupMembershipSynchronizer` weiter. Dieser legt GroupMemberships additiv und ausschließlich für bereits auf bestehende eMatChef Groups gemappte direkte externe Gruppenrollen desselben Departments an. Unmapped-Gruppen werden protokolliert; Cross-Department-Mappings werden als Konflikt protokolliert und nicht übernommen. Bestehende GroupMembership-Eigenschaften bleiben unverändert; Standardzuordnungen erhalten nur `member`, `canProcure=false` und `isPrimary=false`. Es gibt keine Ancestor-Zuordnung, keine Löschung, keinen Strukturimport und kein automatisches Department-Rollenmapping. Ohne MiData-Identität oder Department-Mapping sowie bei erfolgreich geprüften Rollen ohne Treffer legt der Callback keine manuelle Anfrage an; diese wird erst durch die erneute Formularübermittlung mit der bestehenden Turnstile-Prüfung erstellt. Technische Verifikationsfehler erzeugen weder Membership noch JoinRequest und liefern eine generische temporäre Fehlerantwort mit MiData-Wiederholungsmöglichkeit. OAuth-Tokens bleiben transient und werden weder gespeichert noch an das Frontend gegeben.
- MiData-Materialwart-Onboarding (nur PBS): PBS-Strukturmodell: `Group::Root` wird ignoriert und nie einem eMatChef-Objekt zugeordnet; `Group::Bund` entspricht einer eMatChef-`Organisation`; `Group::Kantonalverband`, `Group::Region` und `Group::Abteilung` entsprechen Departments (Kantonalverband ohne Parent, Regionen darunter, Abteilung unter der letzten Region bzw. direkt unter dem Kantonalverband); PBS-Untergruppen sind Groups und nicht Teil dieses Flows. Die Bund-Zuordnung wird ausschliesslich administrativ über `app:external-structure:map` hergestellt; das Onboarding erzeugt die Bund-Organisation nie. Meldet sich ein MiData-User ohne eMatChef-Membership an, nominieren die userinfo-Rollen nur Kandidaten. `MiDataMaterialwartVerifier` bestätigt über `GET /api/roles` eine aktive direkte Rolle `Group::Abteilung::Materialwart` (`start_on`/`end_on` inklusive) exakt in der Gruppe und verlangt die Kette Abteilung → Region* → Kantonalverband → Bund (→ Root). Rollen- und Gruppennamen sowie Browserdaten sind nie Autorisierungsmerkmale. Ein `MiDataDepartmentOnboarding`-Angebot (User, Provider, externe Person, externe Abteilung, Rollenklasse, Anzeigenamen, Ablauf nach 24 h; keine Tokens) entsteht nur, wenn der Bund bereits auf eine Organisation gemappt ist; im Callback wird keine Struktur angelegt. `/pending-assignment` zeigt es über `GET /api/join-requests/midata-onboarding`. „Abteilung einrichten“ startet den MiData-Link-Flow mit der Angebots-ID im HMAC-signierten State; erst dieser Callback prüft mit frischem Token Angebotsbesitz, Ablauf, verknüpfte Identität, aktive Rolle, Struktur und Bund-Mapping erneut. Verknüpft wird ausschliesslich über `ExternalStructureIdentity(provider, external_group_id)`; bestehende Mappings werden nur bei passendem Zieltyp, passender Organisation und passendem Parent wiederverwendet, sonst Konflikt ohne Reparatur oder Verschiebung. Namen verknüpfen nie; ein gleicher oder sehr ähnlicher Name unter demselben erwarteten Parent (bzw. auf Root-Ebene derselben Organisation) stoppt als Konflikt, unter anderen Parents nicht. Die Anlage (Departments, Mappings, Membership `mw`) läuft in einer Doctrine-Transaktion unter einem PostgreSQL-Advisory-Lock pro Kantonalverband; der Unique-Index auf `(provider, external_group_id)` bleibt Rückfallschutz. Eine bestehende Membership wird nie verändert. Mehrere Zugehörigkeiten: Pro MiData-Abteilung entsteht höchstens ein Angebot, auch wenn der User schon Memberships hat (Abteilungen, in deren gemapptem Department er bereits Mitglied ist, entfallen). Bis zu 10 relevante Abteilungen werden beim Login vollständig geprüft; `/api/roles` wird dafür einmal geladen und `HitobitoApiClient` lädt jede Gruppe pro Request höchstens einmal. Bei mehr als 10 speichert der Callback nur ungeprüfte Kandidaten (`MiDataMembershipCandidate`: User, Provider, externe Gruppe, Rollenklasse, Anzeigename, Ablauf; keine Tokens oder Hierarchie) und `/pending-assignment` bzw. das Profil zeigen eine Suche nur über diese eigenen Kandidaten (`GET /api/join-requests/midata-onboarding/candidates`). Ein Suchtreffer autorisiert nichts: Die Auswahl startet den Link-Flow mit der Kandidaten-ID im signierten State; erst dieser Callback prüft mit frischem Token vollständig, legt das Angebot an und richtet es ein. Ungeprüfte Kandidaten zählen nicht als verifizierte Alternative für die Support-Queue. Weitere Memberships sind nicht primär; offene Angebote bleiben nach einer Einrichtung bestehen und sind für User mit Membership im Profil unter „Weitere MiData-Zugehörigkeiten“ erreichbar. Ein offenes Angebot ersetzt den generischen Prozess „Benutzer ohne Zuordnung“ (`MiDataOnboardingQueueExclusion`); nach erfolgreicher Einrichtung wird eine noch offene System-Anfrage des Users ohne Reviewer auf `assigned` mit dem eingerichteten Department gesetzt (Event `assigned`, Quelle `midata_onboarding`).
- Passwort-Hashing: Symfony `auto` auf `User`.

Department-Rechte kommen nicht aus dem JWT allein, sondern aus `Membership.role` im angefragten Department. Katalog: `DepartmentRole`, Zuweisungslogik `MembershipRoleCatalog`.

Support-Anfragen (`AdminJoinRequest`): Department-Manager (`mw`/`dc` ohne globale Admin-Rolle) sehen offene Anfragen und Verlauf, lehnen ab und weisen zu (Zuweisung nur `mw`) ausschliesslich für Anfragen ihrer Organisation, deren gewünschtes Parent-Department ihr Department oder ein Unter-Department ist (`AdminJoinRequestManagerScope`). Anfragen ohne Organisation oder ohne Parent-Department bleiben globalen Admins vorbehalten. Zuweisungen durch Manager müssen im eigenen Teilbaum liegen und `MembershipRoleCatalog::isAllowed`/`canAssign` erfüllen; der Fallback „Ziel ohne mw/dc → zugewiesener User wird mw“ gilt nur für globale Admins. Org-/Sub-Org-Admins lehnen nur Anfragen ihrer zugänglichen Organisationen oder ohne Organisation ab; Superadmins bleiben global.

Die Support-Liste `GET /api/join-requests/admin-request/pending` ist rein lesend. Globale Admins sehen dort zusätzlich berechnete Einträge „Benutzer ohne Zuordnung“ (`request_kind: unassigned_user`, ohne Anfrage-ID; `UnassignedUserSupportQueue`): aktive User ohne Membership, ohne offene JoinRequest, ohne eigene offene AdminJoinRequest, ohne verifizierte Alternative und ohne ausgeblendete System-Anfrage; Superadmin-, E2E-Smoke- und reine Lieferantenkonten sind ausgenommen. Gespeichert wird erst bei einer expliziten Admin-Aktion (`POST /api/join-requests/unassigned-users/{userId}/assign` bzw. `/dismiss`) als AdminJoinRequest mit `assigned` bzw. `rejected`; eine abgelehnte System-Anfrage blendet den User dauerhaft aus. System-Anfragen (Altbestand `auto_created`, Queue-Aktion `queue_created`) werden über ihre Events erkannt, nicht über den Text. Verifizierte Alternativen (z. B. ein offenes MiData-Angebot) melden sich über `UnassignedUserQueueExclusion`; solange sie bestehen, erscheinen offene System-Anfragen des Users weder in der Support-Liste noch in `/mine`. Eigene Anfragen bleiben bei der Registrierung bzw. auf `/pending-assignment` explizite Aktionen des Users.

## Hosts (lokal / produktiv)

Lokal über Nginx und `*.ematchef.test` (`APP_FRONTEND_URL`, `APP_PUBLIC_QR_URL`, `VITE_DEVICES_HOST`). Produktiv getrennte Flächen, u. a. App, QR (`qr.ematchef.ch`), Geräte (`devices.ematchef.ch`), Nutzerhilfe (`docs.ematchef.ch`), Weblate (`translate.ematchef.ch`). Marketing-Site und App-Deploy sind getrennte Workflows — siehe CONTRIBUTING, nicht hier nachbauen.

## Externe Dienste (im Code/Config belegt)

| Dienst | Verwendung |
| --- | --- |
| Amazon SES über Symfony Mailer (`MAILER_DSN`) | Transaktionsmail — [mail/README.md](./mail/README.md) |
| Weblate | App-UI-Locales — [TRANSLATION.md](./TRANSLATION.md) |
| Google OAuth | Login |
| Microsoft OAuth | Login |
| Cloudflare Turnstile | Registrierung und Join-Anfragen (`TURNSTILE_SKIP_VERIFY` nur lokal) |
| Gmail- und Outlook-OAuth-Callback-Controller | Grossanlass-Postfach (`GrossanlassGmail*`, `GrossanlassOutlook*`) |
| `GET /api/health` | Uptime ohne JWT |

Keine Secrets in Docs oder Compose committen. Droplet-Env liegt ausserhalb von `docker-compose.yml` (Kommentar dort und `deploy/`).
