# Dev-Tools Backlog (Banner-Logins & Demo-Seed)

Ziel: Develop/Staging schneller testen — **nicht** für Produktion.

Verwandt: gelber Banner (`frontend/src/utils/devEnvironmentBanner.ts`, `DevEnvironmentBanner.vue`), `EMATCHEF_DEV_TOOLS` (Backend), Org-Subset-Seeds (`backend/data/seeds/`, `app:org-subset:*`), `app:ensure-e2e-user`.

---

## A — Demo-Logins im gelben Banner

**Idee:** Wenn `shouldShowDevEnvironmentBanner()` wahr ist und der User **nicht** eingeloggt ist: im Banner aufklappen „Demo-Accounts“ mit festen Seed-Usern (Label + Rolle). Klick füllt Login vor oder ruft `login_check` auf.

**Regeln:**
- Nur Dev/Staging-Hosts (`VITE_SHOW_DEV_BANNER`, `*-dev.*`, später `*-staging.*` in der Host-Liste)
- Nur bekannte **Seed-Passwörter** (nie Produktiv-Credentials)
- API optional: `GET /api/dev/demo-logins` nur wenn `EMATCHEF_DEV_TOOLS=1` (sonst hardcodierte Liste im Frontend)

**Nicht:** Banner auf `app.ematchef.ch` / Prod.

**Status:** geplant (noch nicht umgesetzt)

---

## B — Fixe Dev-Vorlage (DB-Seed)

**Idee:** Ein reproduzierbarer Datenstand für develop/staging:

- feste User (MW, Mitleiter, …) mit bekannten Passwörtern (für A)
- Material aller relevanten Typen (inkl. Combos/Verbrauch/Food wo sinnvoll)
- Aktivitäten verschiedener Typen
- Packstadien / Journey-Daten
- Werkstatt-Tickets (Beispiele)

**Ansatz:**
1. Einmalig guten Stand auf develop pflegen
2. `app:org-subset:export` (oder neues `app:dev-demo:export`) → JSON im Repo unter z. B. `backend/data/seeds/dev-demo/`
3. `app:dev-demo:reset` = DB leeren/migrieren + Import (nur wenn `EMATCHEF_DEV_TOOLS=1`)
4. Optional UI-Button neben dem bestehenden DB-Reset in den Abteilungseinstellungen

**Status:** geplant (Bausteine Export/Import + E2E-User existieren bereits)

---

## Abgrenzung zu C (App auf Droplet)

Marketing-Domains (`ematchef.ch`, `dev.ematchef.ch`, `staging.ematchef.ch`) bleiben auf **Hostpoint** (selten Änderungen).  
App/QR/Devices (`app*.ematchef.ch`, …) → Deploy auf **Hetzner** — siehe [APP-ON-DROPLET.md](APP-ON-DROPLET.md).
