# Hetzner-Cutover — Chat-Zusammenfassung

Stand: 2026-08-15 · aus dem Cursor-Chat **„Hetzner Cutover Start“**  
Detaillierte Zielbild-/Deploy-Technik: [`APP-ON-DROPLET.md`](APP-ON-DROPLET.md) · Server-Ops: `deploy/SERVER-UPDATE.md`

Dieses Dokument hält fest, **was im Cutover-Chat passiert ist**, was **jetzt gilt**, und was **noch offen** ist. Es ist keine Schritt-für-Schritt-Anleitung zum Nachbauen.

---

## Zielbild (Ergebnis)

| Was | Wo | Deploy |
|-----|-----|--------|
| Marketing Prod (`ematchef.ch`) | **Hostpoint** | FTP nur noch `home` (#196) |
| App Develop | **Hetzner** develop-Droplet `94.130.231.112` | CD Develop + Deploy Frontend Develop |
| App Staging | gleicher Develop-Droplet (eigener Compose-Pfad) | CD Staging + Frontend analog |
| App Prod | **Hetzner** Prod-Droplet `178.104.22.40` | CD Prod + Frontend |
| API (dev/staging/prod) | Hetzner Compose hinter Caddy | CD-Workflows |
| DNS | **Cloudflare** (Hostpoint-DNS irrelevant) | A → Droplets |
| Übersetzungen | Weblate-Droplet `translate.ematchef.ch` | Branch `develop` / Push `weblate` |

### Hostnamen (aktuell)

| Env | App-Einstieg (Login) | API | QR / Devices |
|-----|----------------------|-----|--------------|
| develop | `https://dev.ematchef.ch` | `api.dev.ematchef.ch` | `qr.dev` / `devices.dev` |
| staging | `https://staging.ematchef.ch` | `api.staging.ematchef.ch` | `qr.staging` / `devices.staging` |
| prod | `https://app.ematchef.ch` | `api.ematchef.ch` | `qr` / `devices` |

- Develop/Staging: **kein Marketing** — bare Host = App/Login.
- Prod: Marketing ≠ App (`ematchef.ch` vs. `app.ematchef.ch`).
- `app.dev` / `app.staging` waren Übergangsnamen; Canonical ist der bare Host. Ohne Cloudflare-A für `app.dev` → **NXDOMAIN** (alte Bookmarks / gecachte 301er beachten).

Release-Kette: `develop` → `staging` → `prod` per **`/fast-forward`** (kein Squash auf diesen PRs).

---

## Chronik — Wichtigstes aus dem Chat

### 1. Weblate / Translate-Droplet (Anfang des Chats)

- Component repariert (kaputter Git-Clone, fehlende GitHub-`known_hosts`, Format `json-nested`).
- Orphan-Locales entfernt; Org-Varianten-Matrix `de|fr|it` × `pfadi|cevi|jubla`.
- **Falle:** Dateimaske `de-*.json` + Code `de-jubla` → Datei `de-de-jubla.json` — Fix Maske `*.json` (#153).
- Weblate von **`prod`** auf **`develop`** umgestellt (#158); Action **Weblate integrate develop** synct nur `frontend/src/locales/**`.
- Skripte: `docs/weblate-setup-org-variants.sh`, `docs/weblate-switch-to-develop.sh`, Doku `docs/TRANSLATION.md`.

### 2. Staging auf dem Develop-Droplet

- Staging-API/App als eigener Ordner unter `/opt/ematchef/staging` auf dem Develop-Host (nicht Hostpoint-www).
- Caddy bedient Staging-Hosts; früher Staging-Marketing/Basic-Auth auf Hostpoint abgelöst.
- Entscheidung: Staging/Dev brauchen **keine** Marketing-Site.

### 3. Frontend vom Hostpoint-FTP aufs Droplet (#189 ff.)

- App-Builds → rsync/Caddy auf Hetzner (`scripts/build-droplet-frontend.sh`).
- Hostnamen-Hierarchie `app.dev` / `api.dev` … (#190), danach Korrektur: **Einstieg = `dev.` / `staging.`** ohne Zwang zu `app.dev` (#191/#192).
- Cloudflare: A-Records auf Droplet; alte flat-Namen (`api-dev`) zeigten zeitweise auf Hostpoint → Login kaputt — auf `api.dev` → Hetzner korrigiert.
- Prod-App HTTPS auf Prod-Droplet ergänzt (vorher oft nur API ok).

### 4. Marketing Hostpoint härten / FTP schlank

- FTP Prod nur noch Marketing/`home` (#196).
- HTTPS erzwingen + HSTS (#193); FTP-Timeouts in CI waren flaky — manuell nachgezogen.
- Browser „Nicht sicher“: serverseitig oft schon ok; lokale Ursachen: Chrome-Cache, altes HTTP-Bookmark, seltener AV/Uhrzeit. Chrome-Update bzw. Cache-Löschung half.

### 5. Dev-Tools A+B + Demo-User (#199–#204)

- Gelber Banner → **Demo-Logins** (nur ausgeloggt), Mails `@ematchef.ch`.
- `app:dev-demo:reset` / Rollen-User; E2E-User ohne Department, ausgeblendet aus Suche.
- Demo-Passwort final: **`test!ematchef`** (#204).
- Release #199 bis **staging/prod** via #200/#201 (`/fast-forward`).
- **CD Prod** rot: Workflow suchte falschen Key-Pfad; echter Key `/root/.ssh/ematchef_deploy_prod_ed25519` — Symlink + Workflow-Fix (#202).

### 6. Develop-Einstieg & Chrome-301-Cache

- Alter Caddy-`redir … permanent` `dev.` → `app.dev` war im Chrome gecacht, obwohl Server längst 200 auf `dev.` liefert.
- Fix lokal: Cache / Site-Daten für `ematchef.ch` (Variante B), Inkognito bestätigte Server ok.
- Optional: Cloudflare A `app.dev` → develop-Droplet für alte Bookmarks.

### 7. Weitere Develop-Merges (noch nicht überall released)

Auf **`develop`** (Stand nach Chat): u. a. #202–#206 inkl. Hilfe-FAB/Touren (#205), Nass/feucht-Retour & Esswaren-Ablaufdatum (#206).  
**Staging/Prod** standen am Chat-Ende noch bei #199 — Release per FF bei Bedarf.

### 8. Nebenbei im selben Chat (nicht Cutover-Kern)

- CI: Vitest, Path-Filter, Playwright-Smoke als Pflicht, E2E-Docs.
- Viele Git-Worktrees/`*-wt` und zwei Cursor-Workspaces (Ordnergebunden) — Code ist eine Repo; Aufräumen = Worktrees entfernen, nur `eMatChef` öffnen.

---

## Wichtige PRs (Cutover / Hosting / Demo)

| PR | Thema |
|----|--------|
| #189 | Full-Site Frontend auf Droplet + FF/FTP-Cleanup-Start |
| #190 | Host-Hierarchie `app.dev` / `api.dev` … |
| #191 / #192 | Einstieg `dev.` / `staging.` = App (kein Marketing-Umweg) |
| #193 | Hostpoint HTTPS + HSTS |
| #196 | FTP Prod nur Marketing (`home`) |
| #199 | Banner-Demo-Logins + Dev-Demo-Reset |
| #200 / #201 | FF develop→staging→prod (#199) |
| #202 | CD Prod Deploy-Key-Pfad |
| #203 | Doppel-Dropdown Benutzer hinzufügen |
| #204 | Demo-Passwort `test!ematchef` |
| #205 / #206 | Hilfe-FAB; Nass/feucht (develop) |

Frühere Chat-PRs zu Weblate/i18n: #151–#158 u. a. — siehe `docs/TRANSLATION.md`.

---

## Learnings (kurz)

1. **DNS = Cloudflare**, nicht Hostpoint-Subdomain-Panel.
2. Develop/Staging App auf bare Host; Prod Marketing bleibt Hostpoint.
3. **Permanente 301** in Caddy sparsam — Browser cachen hart.
4. CD Prod: Deploy-Key-Pfad auf dem Server dokumentieren/mehrere Kandidaten im Workflow (#202).
5. Weblate-Dateimaske: `*.json` + Sprachfilter, nie `de-*.json` bei Codes `de-…`.
6. Fast-forward mit `GITHUB_TOKEN` startet Push-Workflows oft nicht zuverlässig — bei Bedarf manuell dispatchen.

---

## Offen / Folgearbeit

Siehe auch `docs/work/wip-gha-deploy-cleanup.md` (teilweise überholt):

- [ ] FTP Develop/Staging-Workflows entfernen oder nur `workflow_dispatch`
- [ ] Staging/Prod Frontend-Deploy-Workflows & Secrets analog Develop prüfen
- [ ] Optional: Caddy Basic Auth Staging wieder aktivieren
- [ ] Optional: Cloudflare A für `app.dev` (Legacy-Bookmarks)
- [ ] `#202`–`#206` nach Staging/Prod freigeben, wenn gewünscht (`/fast-forward`)
- [ ] Alte Git-Worktrees/`*-wt` und ungenutzte Cursor-Workspaces aufräumen
- [ ] Untracked Notizen: `docs/work/wip-gha-deploy-cleanup.md` nach Erledigung löschen

---

## Schnell-Checks

```bash
# Develop App
curl -sI https://dev.ematchef.ch/login | head -5

# Marketing
curl -sI https://ematchef.ch | head -10

# Prod App
curl -sI https://app.ematchef.ch/login | head -5
```

Demo auf Develop (ausgeloggt): Banner → Demo-Logins → z. B. `superadmin@ematchef.ch` / `test!ematchef`.
