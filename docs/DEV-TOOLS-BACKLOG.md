# Dev-Tools Backlog (Banner-Logins & Demo-Seed)

Ziel: Develop/Staging schneller testen — **nicht** für Produktion.

Verwandt: gelber Banner (`frontend/src/utils/devEnvironmentBanner.ts`, `DevEnvironmentBanner.vue`, `frontend/src/utils/demoLogins.ts`), `EMATCHEF_DEV_TOOLS` (Backend), Org-Subset-Seeds (`backend/data/seeds/`, `app:org-subset:*`), `app:ensure-e2e-user`, `app:dev-demo:reset`.

---

## A — Demo-Logins im gelben Banner

**Idee:** Wenn `shouldShowDevEnvironmentBanner()` wahr ist und der User **nicht** eingeloggt ist: im Banner aufklappen „Demo-Accounts“ mit festen Seed-Usern (Label + Rolle). Klick füllt Login vor.

**Regeln:**
- Nur Dev/Staging-Hosts (`VITE_SHOW_DEV_BANNER`, Host-Liste in `devEnvironmentBanner.ts`)
- Nur bekannte **Seed-Passwörter** (`test` für `*@ematchef.ch`) — nie Produktiv-Credentials
- Frontend-Liste in `demoLogins.ts` (optional später `GET /api/dev/demo-logins` wenn `EMATCHEF_DEV_TOOLS=1`)

**Nicht:** Banner auf `app.ematchef.ch` / Prod-Marketing.

**Status:** umgesetzt (Banner + Login-Prefill via `sessionStorage`)

---

## B — Fixe Dev-Vorlage (DB-Seed)

**Idee:** Ein reproduzierbarer Datenstand für develop/staging:

- feste User (MW, Mitleiter, …) mit bekannten Passwörtern (für A) → `app:create-role-users` / `app:dev-demo:reset`
- Material aller relevanten Typen (inkl. Combos/Verbrauch/Food wo sinnvoll) — noch auszubauen
- Aktivitäten verschiedener Typen — noch auszubauen
- Packstadien / Journey-Daten — noch auszubauen
- Werkstatt-Tickets (Beispiele) — noch auszubauen

**Aktuell:**
```bash
# Auf Develop-API (EMATCHEF_DEV_TOOLS=1):
php bin/console app:dev-demo:reset --e2e-password="$E2E_PW"
# oder nur Rollen-User:
php bin/console app:create-role-users
```

**Weiter:**
1. Einmalig guten Stand auf develop pflegen
2. `app:org-subset:export` → JSON unter `backend/data/seeds/dev-demo/`
3. `app:dev-demo:reset` um Import erweitern
4. Optional UI-Button neben dem bestehenden DB-Reset in den Abteilungseinstellungen

**Status:** teilweise (Rollen-User + E2E ohne Dept; Material/Aktivitäten-Seed noch offen)

---

## E2E-Smoke-User

- Email: `e2e-smoke@ematchef.ch`
- Standard **ohne** Department; nicht in User-Suche / Admin-Liste / Auto-Join
- Optional `--department-id=…` nur wenn bewusst Mitgliedschaft nötig
- Siehe [E2E.md](E2E.md)

---

## Abgrenzung zu C (App auf Droplet)

Marketing nur noch **Prod** `ematchef.ch` auf Hostpoint. Develop/Staging App-Einstieg (`dev.` / `staging.`) und App/QR/Devices → **Hetzner** — siehe [APP-ON-DROPLET.md](APP-ON-DROPLET.md).
