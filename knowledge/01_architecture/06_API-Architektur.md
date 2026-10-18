# 06 API-Architektur

## Struktur

- `IMPLEMENTIERT` REST-artige JSON-API unter `/api/*`, ausschliesslich als Symfony-Controller mit `#[Route(...)]`-Attributen (`backend/src/Controller/`, geladen über `backend/config/routes.yaml`). Dateien liegen flach; pro Fachbereich ein oder mehrere Controller (`Category`, `Activity*`, `Accounting*`, `Grossanlass*`, `Supplier*`, `Display*`, `Profile*`, `Auth`, `Mfa`, `StepUp` …). Öffentliche Endpunkte: `backend/src/Controller/Public/`.
- `IMPLEMENTIERT` Pfade sind ressourcenorientiert mit Department im Pfad (z. B. `/api/departments/{id}/grossanlass/…`) oder als Query-Parameter (`/api/categories?department_id=…`, `CategoryController`). Kein einheitliches Schema; Muster im Nachbar-Controller übernehmen.
- `IMPLEMENTIERT` Frontend-URLs und API-Pfade sind unabhängig voneinander (`docs/ARCHITECTURE.md`).
- `IMPLEMENTIERT` Auch `/media/*` (Dateiauslieferung) liegt hinter derselben Firewall (`backend/config/packages/security.yaml`, Muster `^/(api|media)`).
- `IMPLEMENTIERT` OpenAPI/Swagger-Beschreibung aus den Routen, nur mit `API_DOCS_ENABLED=1` (Standard aus): `backend/src/Controller/ApiDocsController.php`, Pfad `/api/doc`.
- `IMPLEMENTIERT` `GET /api/health` ohne Authentifizierung; `GET /` und `/robots.txt` über `ApiDiscoveryController`.
- `ENTSCHIEDEN` (`AGENTS.md`) Keine API-Platform-Ressourcen; neue Endpunkte als Controller, Logik im Service.

## Authentifizierung der Requests

Details: [07](./07_Sicherheit-und-Berechtigungen.md).

- `IMPLEMENTIERT` Die API-Firewall (`^/(api|media)`) ist `stateless` mit `jwt: ~`; Standard-Zugriff `ROLE_USER` (`access_control` Letzter Eintrag `.*`). Öffentlich sind nur explizit gelistete Pfade (Login, Register, Verify, Password-Reset, MFA, OAuth, Token-Refresh, `/api/public`, `/api/health`, API-Doku, CORS-`OPTIONS`).
- `IMPLEMENTIERT` Das JWT liegt im HttpOnly-Cookie `BEARER`; der Refresh-Token im Cookie des Gesdinet-Bundles. Der Server akzeptiert zusätzlich einen `Authorization: Bearer`-Header (Extractor aktiv, `lexik_jwt_authentication.yaml`), der Frontend-Client sendet ihn jedoch nie.
- `IMPLEMENTIERT` CORS mit Credentials (`nelmio_cors.yaml`), Antwort-Header (CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`) setzt `EventSubscriber/SecurityHeadersSubscriber.php`.
- `IMPLEMENTIERT` Rate-Limit für `GET /api/auth/session` und `POST /api/token/refresh`: `EventSubscriber/ApiAuthRateLimitSubscriber.php`; Login-Throttling 5 Versuche / 15 min in der Firewall `login`.

## Fehlerbehandlung

- `IMPLEMENTIERT` Controller geben `JsonResponse` mit `['error' => '<Text oder Code>']` und HTTP-Status zurück (im Backend rund 2000 Vorkommen `'error' =>` in `Controller/`); maschinenlesbare Codes stehen ebenfalls in `error` (`mfa_required`, `step_up_required`, `mfa_setup_required`, `reauth_required` …). Zusatzfelder `message`/`code` kommen vereinzelt vor. Ein einheitliches, erzwungenes Fehlerschema existiert nicht (`UNKLAR`, ob es Ausnahmen gibt).
- `IMPLEMENTIERT` Zugriffsverweigerung auf Department-Ebene: 401 «Nicht authentifiziert» bzw. 403 aus `DepartmentAccessGuard::deny` / `denyManage`.
- `IMPLEMENTIERT` Frontend-Anzeige: `frontend/src/utils/apiErrorMessage.ts` liest `error`, `detail`, `message`, `title` und liefert Fallbacks für Timeout/Netzwerkfehler.

## Frontend: `apiClient` (verbindlich)

`ENTSCHIEDEN` (`AGENTS.md`): Alle HTTP-Aufrufe über den Axios-Client in `frontend/src/api/apiClient.ts`; Fachfunktionen in `frontend/src/api/<bereich>.ts` (120 Dateien, z. B. `categories.ts`, `grossanlassProcurement.ts`, `auth.ts`). Kein `fetch`, keine zweite `axios.create()`-Instanz, keine eigenen Auth-/401-/Refresh-Pfade.

`IMPLEMENTIERT` Verhalten des Clients:

| Aspekt | Verhalten |
| --- | --- |
| Basis-URL | `VITE_RELATIVE_API=true` → relativ (lokal über nginx/Vite-Proxy); sonst `VITE_API_BASE`, Fallback `http://localhost:8081` |
| Cookies | `withCredentials: true`, Timeout 30 s, JSON-Header; `FormData` ohne erzwungenen Content-Type |
| Authorization-Header | wird in jedem Request entfernt (`stripAuthorizationHeader`); Auth nur per Cookie |
| 401 | genau ein Refresh (`refreshSessionCookie`, Mutex gegen parallele Refreshes), dann Wiederholung; scheitert er → `sessionExpiredHandler` (in `main.ts`: Toast, Logout, Login-Weiterleitung mit Rücksprung) |
| 403 `mfa_required` / `step_up_required` | Step-up-Dialog (`stores/stepUp.ts`), danach genau eine Wiederholung; `mfa_setup_required` → Hinweis/Profil-Sicherheit |
| Proaktiver Refresh | höchstens alle 25 min nach erfolgreichen Aufrufen (`setApiSuccessRefreshCallback`) |
| Ausgenommen von der 401-Behandlung | Login, Register, Verify, Password-Reset, `/api/public/*`, `/api/auth/session` (Session-Probe) |
| Test-Header | `X-Onboarding-Tour: 1` während der Onboarding-Tour |

`IMPLEMENTIERT` Dokumentierte Ausnahmen vom Muster (keine Backend-Aufrufe): direkte `fetch`-Aufrufe an externe Geodienste in `components/AddressModal.vue`, `components/MapView.vue`, `components/contacts/ContactDetailView.vue`, `utils/geoSearch.ts` sowie `fetch(dataUrl)` in `components/common/PublicQrTag.vue`. `frontend/src/api/media.ts` importiert Axios nur als Typ. Es gibt keine ESLint-Regel, die dies erzwingt.

## Neue Endpunkte: Checkliste (aus Konvention abgeleitet)

1. Controller im passenden Bereich erweitern oder neu, `#[Route('/api/…')]`, `#[IsGranted('ROLE_USER')]` wie im Nachbar-Controller (727 Vorkommen).
2. Department-Zugriff und ggf. Rolle serverseitig prüfen (siehe [04](./04_Mandantenstruktur.md)).
3. Logik in Service; Persistenz über Doctrine.
4. Frontend: Funktion in `frontend/src/api/<bereich>.ts` über `apiClient`.
5. Bei Vertragsänderung: Tests (`backend/tests/Controller`, Frontend-`*.spec.ts`) und `docs/` anpassen.

## Offen

- `UNKLAR` Einheitliches Fehler-/Validierungsformat (Feldfehler) über alle Controller.
- `UNKLAR` Versionierung der API: keine Versionspräfixe in Routen gefunden (`/api/…` ohne `v1`).
