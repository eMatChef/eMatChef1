# Cross-Subdomain-Login (gemeinsame Session)

Kurzdoku für **eine gemeinsame Anmeldung** über mehrere Frontends (z. B. `app.*`, `qr.*`, Marketing-`ematchef.ch`) mit derselben API (`api.*`). Zum **Einfügen in einen neuen Chat** siehe Abschnitt [Kontext für neuen Cursor-Chat](#kontext-für-neuen-cursor-chat).

## Problem

- `localStorage` ist **origin-gebunden**: Ein Login auf `app.ematchef.ch` ist für `qr.ematchef.ch` oder `ematchef.ch` **nicht** sichtbar.
- Öffentliche Seiten brauchen trotzdem einen **sicheren** Hinweis „eingeloggt“ (Avatar, „Zur App“), ohne Secrets im JS.

## Lösung (Stand im Repo)

1. **JWT und Refresh-Token als HttpOnly-Cookies** (Lexik + Gesdinet), Domain-weit setzbar.
2. **API-Calls mit Credentials:** Axios `withCredentials: true`, damit der Browser Cookies mitschickt.
3. **Session-Abfrage:** `GET /api/auth/session` liefert `user`, `profile`, `departments` wenn ein gültiges JWT-Cookie da ist (sonst 401).
4. **Öffentliche Frontends** rufen beim Mount `loadUserSessionFromCookie()` im Auth-Store auf (kein Token in `localStorage` nötig für „eingeloggt erkannt“).

## Lokal: immer `*.localhost` nutzen

Die Cookie-Domain (z. B. `.localhost`) muss ein **Suffix des Hosts** sein, der die `Set-Cookie`-Antwort auslöst. Deshalb:

| Aufruf | Cross-Subdomain-Cookies mit `AUTH_COOKIE_DOMAIN=.localhost` |
|--------|----------------------------------------------------------------|
| `http://app.localhost:5173`, `http://qr.localhost:…` | **Ja** — so ist das Projekt gedacht (`vite.config.ts` `allowedHosts`, `docker-compose` `APP_*_URL`). |
| `http://localhost:5173` oder `http://127.0.0.1:5173` | **Nein** — Host ist nicht `*.localhost`; Cookies für `Domain=.localhost` werden nicht korrekt gesetzt bzw. nicht mit allen Frontends geteilt. |

**CORS ≠ Cookies:** In `CORS_ALLOW_ORIGIN` dürfen `localhost` und `127.0.0.1` vorkommen (z. B. für Tools), aber der **gemeinsame Login über Subdomains** funktioniert nur, wenn du die App im Browser unter **`app.localhost` / `qr.localhost`** öffnest. Optional in `/etc/hosts`: `127.0.0.1 app.localhost qr.localhost`.

## Umgebungsvariablen (Backend)

In `backend/.env` (lokal) bzw. **Server-Umgebung / Docker-Override** (Produktion und Develop-Umgebung):

| Variable | Lokal (typisch) | Produktion (WWW) |
|----------|-----------------|-------------------|
| `AUTH_COOKIE_DOMAIN` | `.localhost` (nur sinnvoll bei Hosts `app.localhost`, `qr.localhost`, …) | `.ematchef.ch` |
| `AUTH_COOKIE_SECURE` | `0` (HTTP) | **`1` (HTTPS)** — Pflicht sobald die API über `https://` erreichbar ist |

Fuer die **öffentliche Develop-Umgebung** (`dev.ematchef.ch`, `app-dev.ematchef.ch`, `qr-dev.ematchef.ch`, API `api-dev.ematchef.ch`):

- `AUTH_COOKIE_SECURE=1`
- `AUTH_COOKIE_DOMAIN=.ematchef.ch` — damit gelten HttpOnly-Cookies für **alle** diese Subdomains (nicht nur `dev.ematchef.ch`; sonst fehlen Cookies auf `app-dev.*`).
- `CORS_ALLOW_ORIGIN` muss **jeden** Browser-Origin abdecken, von dem die SPA die API aufruft, z. B. Regex:

  `^https://(dev\.ematchef\.ch|app-dev\.ematchef\.ch|qr-dev\.ematchef\.ch)$`

  Steht dort nur `dev.ematchef.ch`, schlägt der Login von **`https://app-dev.ematchef.ch`** mit CORS-Fehlern fehl („No Access-Control-Allow-Origin“).

Beispiel-Override: `deploy/docker-compose.override.develop.example.yml`

Lexik/Gesdinet lesen diese Werte in:

- `backend/config/packages/lexik_jwt_authentication.yaml` (`set_cookies`, Cookie-Extractor `BEARER`)
- `backend/config/packages/gesdinet_jwt_refresh_token.yaml` (`cookie:`)

Nach Änderung: **Backend neu starten** und ggf. `php bin/console cache:clear --env=prod`.

**Wichtig in Produktion:** `AUTH_COOKIE_SECURE=1` und ausschließlich **HTTPS** für API und alle Frontends. Ohne HTTPS lehnt der Browser `Secure`-Cookies ab oder verhält sich inkonsistent.

## Sicherheit im öffentlichen Internet

- **Kein `http://` in Prod** für Seiten, die Login/Cookies setzen; Reverse-Proxy (Nginx) TLS terminieren, HSTS auf den Hosts erwägen.
- **`AUTH_COOKIE_SECURE=1`** in Prod (im Prod-Compose-Beispiel gesetzt), damit Cookies nur über TLS gesendet werden.
- **CORS eng halten:** `CORS_ALLOW_ORIGIN` nur als Regex auf **eure echten HTTPS-Origins** (z. B. `app`, `qr`, Apex, `www`) — nicht `*` bei `allow_credentials: true` (Nelmio: `origin_regex: true`, explizite Origins).
- **SameSite `lax`** (aktuell in Lexik/Gesdinet) ist für **alle Frontends unter derselben registrable Domain** (`*.ematchef.ch`) und Aufrufe zur API unter derselben Site üblich und passend. Wenn API und SPA je **auf komplett anderen Registrable Domains** lägen, müsste man `SameSite=None` + `Secure` prüfen — hier nicht der Fall, solange alles unter `ematchef.ch` bleibt.
- **Geheimnisse** nur in Server-`.env` / Secrets, nie im Frontend-Repo hardcoden.

## CORS

`nelmio_cors` hat `allow_credentials: true`. Die erlaubten Origins (`CORS_ALLOW_ORIGIN`) müssen **alle** betroffenen **HTTPS-**Frontends in Produktion abdecken, z. B. `app.ematchef.ch`, **`qr.ematchef.ch`**, `ematchef.ch`, `www.ematchef.ch` (siehe Prod-Compose-Beispiel).

## Wichtige Dateien (Überblick)

| Bereich | Datei |
|---------|--------|
| Cookies + JWT aus Cookie | `backend/config/packages/lexik_jwt_authentication.yaml` |
| Refresh-Cookie | `backend/config/packages/gesdinet_jwt_refresh_token.yaml` |
| Session-JSON | `backend/src/Controller/AuthController.php` (`/api/auth/session`) |
| Axios Credentials | `frontend/src/api/apiClient.ts` |
| Session laden | `frontend/src/api/auth.ts` (`loadSessionFromServer`), `frontend/src/stores/auth.ts` (`loadUserSessionFromCookie`) |
| Public UI | `frontend/src/components/layout/PublicSiteLayout.vue`, `frontend/src/views/public/LandingHomeView.vue`, `frontend/src/views/public/PublicMaterialView.vue` |
| Prod-Defaults (Compose) | `deploy/docker-compose.override.prod.example.yml` (`CORS_*`, `AUTH_COOKIE_*`) |
| Develop-Defaults (Compose) | `deploy/docker-compose.override.develop.example.yml` |

## Manuelle Checks (Browser)

1. Auf **App-Origin** einloggen (lokal: **`http://app.localhost:…`**, nicht nur `localhost`).
2. DevTools → **Network** → `login_check` → Response **Set-Cookie**: `BEARER` (JWT), ggf. `refresh_token` — **Domain** muss zur Tabelle oben passen; in Prod **Secure** gesetzt.
3. Auf **anderem Subdomain-Frontend** `GET /api/auth/session`: **200** + JSON, wenn Cookies mitgeschickt werden; **401** wenn keine gültige Session.

## Kontext für neuen Cursor-Chat

Untenstehenden Block in einen **neuen Chat** kopieren, wenn jemand nur an der Login-Cross-Subdomain-Thematik weiterarbeiten soll:

```
Kontext: eMatChef – gemeinsame Login-Session über Subdomains

Ziel: Eingeloggt auf app.* soll auf qr.* und Marketing-Domain sichtbar sein (UI: Avatar, „Zur App“), ohne localStorage-Token auf jeder Origin.

Umsetzung im Repo:
- JWT HttpOnly-Cookie (Lexik, Name BEARER) + Cookie-Extractor; Domain/Secure über AUTH_COOKIE_DOMAIN und AUTH_COOKIE_SECURE.
- Refresh-Token HttpOnly-Cookie (Gesdinet), gleiche Env-Variablen.
- Frontend: axios withCredentials; GET /api/auth/session für Public-Seiten; Auth-Store loadUserSessionFromCookie().

Lokal: Browser-URL app.localhost / qr.localhost (nicht localhost/127.0.0.1 allein für Cookie-Sharing).

Relevante Pfade:
- backend/config/packages/lexik_jwt_authentication.yaml
- backend/config/packages/gesdinet_jwt_refresh_token.yaml
- backend/src/Controller/AuthController.php (Route /api/auth/session)
- frontend/src/api/apiClient.ts
- frontend/src/api/auth.ts
- frontend/src/stores/auth.ts
- frontend/src/components/layout/PublicSiteLayout.vue
- frontend/src/views/public/LandingHomeView.vue
- frontend/src/views/public/PublicMaterialView.vue
- deploy/docker-compose.override.prod.example.yml

Prod: AUTH_COOKIE_DOMAIN=.ematchef.ch, AUTH_COOKIE_SECURE=1; HTTPS; CORS_ALLOW_ORIGIN alle HTTPS-Frontends inkl. qr.*; nach Deploy cache:clear.
```
