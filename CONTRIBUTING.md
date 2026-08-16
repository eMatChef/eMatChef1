# Contributing Guide

Danke, dass du zu eMatChef beitraegst.
Dieses Dokument beschreibt unseren Git-Workflow mit `develop` als Arbeits-Branch, `staging` als Release-Kandidat und geschuetztem `prod`.

## Branch-Strategie

- `prod`: stabiler Stand, nur fuer Releases und produktionsnahe Deployments
- `staging`: Release-Kandidat (Vollstack hinter Basic Auth), vor Prod
- `develop`: Integrations-Branch fuer laufende Entwicklung
- `feature/*`: neue Funktionen
- `fix/*`: Bugfixes
- `hotfix/*`: dringende Korrekturen fuer produktionsnahe Probleme
- `chore/*`: Wartung, Infrastruktur, Tooling

### Benennung mit Issue-Nummer (empfohlen)

- Feature-Branch: `feature/<issue>-kurze-beschreibung` (z. B. `feature/123-login-banner`)
- Bugfix-Branch: `fix/<issue>-kurze-beschreibung` (z. B. `fix/145-cors-dev`)
- Chore-Branch: `chore/<issue>-kurze-beschreibung` (z. B. `chore/172-update-docs`)
- PR-Titel: `feat: ... (#<issue>)`, `fix: ... (#<issue>)`, `chore: ... (#<issue>)`
- PR-Beschreibung: `Closes #<issue>` (oder `Refs #<issue>`)

## Grundregeln

- Nie direkt auf `staging` oder `prod` committen oder pushen.
- Neue Arbeit immer von `develop` abzweigen.
- Jede Aenderung geht per Pull Request (PR) zurueck nach `develop`.
- Release-Kette: `develop` → `staging` → `prod`, jeweils per Release-PR und Kommentar **`/fast-forward`** (kein normaler Merge-Button auf `staging`/`prod`).
- Kleine, klare PRs bevorzugen (ein Thema pro PR).

## Lokaler Ablauf fuer Features und Fixes

```bash
git checkout develop
git pull origin develop
git checkout -b feature/kurze-beschreibung
```

Dann entwickeln, committen und den Branch pushen:

```bash
git push -u origin feature/kurze-beschreibung
```

Anschliessend auf GitHub einen PR erstellen:

- Basis-Branch: `develop`
- Compare-Branch: dein `feature/*` oder `fix/*`

## Release-Ablauf (Fast-forward)

Wenn ein Entwicklungsstand bereit ist:

1. PR von `develop` nach `staging` erstellen, CI/Review, Kommentar **`/fast-forward`** (nur **Maintain**/**Admin**; siehe `docs/SETUP-GITHUB.md`)
2. Auf Staging pruefen (Basic Auth, dann App-Login) — Hosts siehe `deploy/SERVER-UPDATE.md`
3. PR von `staging` nach `prod` erstellen, CI/Review, erneut **`/fast-forward`**
4. **CD Staging** bzw. **CD Prod** starten nach dem jeweiligen Push; FTP-Deploy bei Frontend-Aenderungen
5. Optional ein Release-Tag setzen (z. B. `v1.0.0`)

## Pull-Request-Richtlinien

- PR-Titel beschreibt den Zweck klar (z. B. `Add rental return validation`).
- Verwende den Prefix `feat:`, `fix:` oder `chore:` und haenge die Issue-Nummer an (`(#123)`).
- Beschreibe kurz, warum die Aenderung noetig ist.
- Fasse zusammen, was getestet wurde.
- Wenn moeglich, verlinke Issues oder Aufgaben.

## Schutz von Branches (GitHub)

### Empfohlen fuer `staging` und `prod`

- Require a pull request before merging
- Require approvals (mindestens 1 bei Teamarbeit)
- Require status checks to pass before merging (`CI ok`)
- Require branches to be up to date before merging
- Restrict who can push to matching branches
- Do not allow bypassing the above settings (ausser Ruleset-Bypass fuer GitHub Actions beim Fast-forward)
- Allow force pushes: OFF
- Allow deletions: OFF

### Optional fuer `develop`

- PR-Pflicht
- Keine Force Pushes
- Keine Branch-Loeschung

## GitHub Actions (CI/CD)

### Aktive Workflows

- `CI` in `.github/workflows/ci.yml`
  - Jobs: Locales, Frontend (ESLint, Vitest, Build), Backend (Composer, PHPUnit, PHPStan), **Playwright smoke** (gegen Develop), Aggregator **CI ok**
  - Trigger: Push/PR auf `develop`, `staging` und `prod`
  - Smoke-Secrets: siehe [docs/E2E.md](docs/E2E.md)
  - Lokal dasselbe (ohne Playwright) via `.githooks/pre-push` — aktivieren mit `./scripts/install-git-hooks.sh`
- `CD Develop` / `CD Staging` / `CD Prod` — Push auf den jeweiligen Branch (SSH); Staging teilt den Develop-Droplet (`/opt/ematchef/staging`)
- `Deploy App Develop (Droplet)` — App/QR/Devices-SPA per rsync auf den Develop-Droplet (Caddy); siehe [docs/APP-ON-DROPLET.md](docs/APP-ON-DROPLET.md)
- `FTP Deploy *` — Hostpoint **Marketing**/Landing (App mittelfristig vom Droplet)
- Dev-Tools-Ideen (Banner-Logins, Demo-Seed): [docs/DEV-TOOLS-BACKLOG.md](docs/DEV-TOOLS-BACKLOG.md)
- `Fast forward` in `.github/workflows/fast-forward.yml`
  - Kommentar **`/fast-forward`**: `develop` → `staging` oder `staging` → `prod`
  - Nur Personen mit **Maintain** oder **Admin**; kein separater Bot-Token (nutzt `GITHUB_TOKEN` + Ruleset-Bypass fuer GitHub Actions)
- Übersetzungen: self-hosted [Weblate](https://translate.ematchef.ch) — Setup und erlaubte Sprachen in [docs/TRANSLATION.md](docs/TRANSLATION.md)


### Required checks fuer Branch Protection

Fuer geschuetzte Branches sollten mindestens gesetzt sein:

- `CI ok`

Hinweis: Ein Check ist erst auswaehlbar, nachdem er mindestens einmal erfolgreich gelaufen ist.

### Benoetigte GitHub Secrets fuer CD

Fuer `CD Develop`:

- `DEVELOP_SSH_HOST`
- `DEVELOP_SSH_USER`
- `DEVELOP_SSH_KEY`
- `DEVELOP_SSH_PORT`
- `DEVELOP_DEPLOY_PATH`
- `DEVELOP_APP_WEBROOT` — Zielordner der App-SPA auf dem Droplet (z. B. `/var/www/ematchef-app-develop`); siehe [docs/APP-ON-DROPLET.md](docs/APP-ON-DROPLET.md)


Fuer `CD Staging` (API auf dem Develop-Droplet):

- `STAGING_DEPLOY_PATH` (z. B. `/opt/ematchef/staging`) — Pflicht
- Optional `STAGING_SSH_*` (sonst Fallback auf `DEVELOP_SSH_*`)
- Hostpoint: `FTP_PATH_MAIN_STAGING`, `FTP_PATH_APP_STAGING`
- Basic Auth: `STAGING_BASIC_AUTH_USER`, `STAGING_BASIC_AUTH_PASSWORD`
- Optional absolute `.htpasswd`-Pfade: `STAGING_BASIC_AUTH_HTPASSWD_PATH_HOME`, `STAGING_BASIC_AUTH_HTPASSWD_PATH_APP`

Fuer `CD Prod`:

- `PROD_SSH_HOST`
- `PROD_SSH_USER`
- `PROD_SSH_KEY`
- `PROD_SSH_PORT`
- `PROD_DEPLOY_PATH`

## Commit-Empfehlungen

- Kleine, in sich geschlossene Commits
- Imperativ im Commit-Titel (z. B. `Add ...`, `Fix ...`, `Refactor ...`)
- Keine Geheimnisse committen (`.env`, Schluessel, Zugangsdaten)
- Author = dein GitHub-Account (`git config user.name` / `user.email`), nicht Bots

### Keine Bot-Contributors (github-actions / cursoragent)

GitHub listet jeden **Commit-Author** unter Contributors. Deshalb:

1. **Weblate-Sync** commitet als Maintainer (`Matthias Ruffieux`), nicht als `github-actions[bot]`.
2. **Cursor IDE:** Settings → Agents → **Attribution** ausschalten (entfernt „Made with Cursor“ lokal).
3. **Cursor Cloud Agents** committen serverseitig oft als `cursoragent` — dafür gibt es keinen zuverlässigen Opt-out. Workaround: lokal committen/pushen, oder PR squash-mergen und Author prüfen.
4. Repo-Hook `.githooks/prepare-commit-msg` streicht `Co-authored-by: Cursor` / `cursoragent@…` aus der Message. Aktivieren mit:

```bash
./scripts/install-git-hooks.sh
```

Bestehende Bot-Commits in der History bleiben sichtbar, bis die History umgeschrieben wird (force-push, meist unnötig).

## Fragen und Abstimmung

Wenn du bei einer groesseren Aenderung unsicher bist (Architektur, Datenmodell, Workflow),
bitte zuerst kurz im Team abstimmen, bevor du mit der Implementierung startest.
