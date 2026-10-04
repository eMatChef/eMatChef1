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
- Release-Kette: `develop` → `staging` → `prod`. Der Release-PR dient Review und CI. Abgeschlossen wird er nur mit dem Kommentar **`/fast-forward`**. Squash, Merge-Commit und Rebase sind auf diesen PRs keine Promotion.
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

Feature, Fix und Chore:

1. Branch von `develop`
2. Pull Request nach `develop`
3. Normaler Merge gemaess den Regeln fuer `develop` (Merge, Squash oder Rebase)

Release nach Staging:

1. Pull Request `develop` → `staging` (nur Review und CI)
2. Check `CI ok` muss am Head-Commit erfolgreich sein
3. Abschluss ausschliesslich durch Kommentar **`/fast-forward`** (Maintain oder Admin)
4. Der Workflow prueft, dass `staging` ein echter Vorfahre von `develop` ist, und setzt `staging` per Fast-Forward auf genau diesen Commit
5. Der Push startet CD Staging und das Frontend-Deploy. Auf Staging pruefen (Basic Auth, dann App-Login) — Hosts siehe `deploy/SERVER-UPDATE.md`

Release nach Produktion:

1. Pull Request `staging` → `prod` (nur Review und CI)
2. Erneut nur **`/fast-forward`**, mit denselben Pruefungen
3. Der Push startet CD Prod und das Frontend-Deploy. FTP fuer das Prod-Marketing bleibt manuell

Squash, Merge-Commit und Rebase schliessen einen Release-PR nicht ab. Ein Force-Push ist keine Promotion.

Die Rulesets `protect-staging` und `protect-prod` muessen dazu passen: kein Squash, kein Merge, kein Rebase als Button, und der Workflow darf den Fast-Forward-Push ausfuehren. Diese Ruleset-Anpassung ist ein eigener Schritt und ist mit dieser Workflow-Aenderung noch nicht erledigt.

Die bestehende History-Abweichung von `staging` und `prod` wird separat repariert. Bis dahin ist ein Fast-Forward fachlich noch nicht moeglich, auch wenn der Workflow die Pruefung schon durchfuehrt.

Optional danach ein Release-Tag setzen (z. B. `v1.0.0`).

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
- Auf `staging` und `prod` keinen Abschluss per Squash, Merge-Commit oder Rebase. Promotion nur durch `/fast-forward`
- Der Fast-Forward-Workflow braucht einen Ruleset-Bypass fuer GitHub Actions, sonst kann er den Ref nicht setzen. Der Bypass ist noch nicht gesetzt
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
- `FTP Deploy Prod Marketing` — Hostpoint **ematchef.ch**, nur manuell (`workflow_dispatch`); App auf Prod läuft über `Deploy Frontend Prod` (Droplet)
- Dev-Tools-Ideen (Banner-Logins, Demo-Seed): [docs/DEV-TOOLS-BACKLOG.md](docs/DEV-TOOLS-BACKLOG.md)
- `Fast forward` in `.github/workflows/fast-forward.yml`
  - Kommentar **`/fast-forward`**: nur `develop` → `staging` oder `staging` → `prod`
  - Nur Personen mit **Maintain** oder **Admin**
  - Pflicht: offener PR, kein Draft, Check **CI ok** erfolgreich, Ziel-SHA ist Vorfahre des Head-SHA, Head unveraendert bis zum Push
  - Push ohne `--force`: `<head-sha>:refs/heads/<ziel>`, authentifiziert mit dem kurzlebigen Token der Release-App (`RELEASE_APP_ID`, `RELEASE_APP_PRIVATE_KEY`)
- `Release PR guard` in `.github/workflows/release-pr-guard.yml`
  - Kommentar auf Release-PRs. Merged nicht, deployed nicht, ist kein Required Check
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
- `DEVELOP_APP_WEBROOT` — Zielordner der App-SPA auf dem Develop-Droplet (z. B. `/var/www/ematchef-app-develop`); siehe [docs/APP-ON-DROPLET.md](docs/APP-ON-DROPLET.md)
- `STAGING_APP_WEBROOT` / `PROD_APP_WEBROOT` — analog für Staging/Prod (`deploy-frontend-staging.yml` / `deploy-frontend-prod.yml`)


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
- `PROD_DOCS_WEBROOT` — statische Nutzerhilfe (`docs.ematchef.ch`), z. B. `/var/www/ematchef-docs-prod`; nicht der App-Webroot. Siehe [user-docs/README.md](user-docs/README.md).

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
