# Contributing Guide

Danke, dass du zu eMatChef beitraegst.
Dieses Dokument beschreibt unseren Git-Workflow mit `develop` als Arbeits-Branch und geschuetztem `prod`.

## Branch-Strategie

- `prod`: stabiler Stand, nur fuer Releases und produktionsnahe Deployments
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

- Nie direkt auf `prod` committen oder pushen.
- Neue Arbeit immer von `develop` abzweigen.
- Jede Aenderung geht per Pull Request (PR) zurueck nach `develop`.
- `prod` wird nur ueber einen Release-PR aus `develop` aktualisiert und per Kommentar **`/fast-forward`** (kein normaler Merge-Button auf `prod`).
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

1. PR von `develop` nach `prod` erstellen
2. Tests und Review abschliessen (CI und Regeln aus dem `prod`-Ruleset)
3. **Nicht** den GitHub-Merge-Button auf `prod` nutzen; stattdessen auf dem PR den Kommentar **`/fast-forward`** setzen (nur **Maintain**/**Admin** im Repository; siehe `docs/SETUP-GITHUB.md`)
4. Der Workflow **Fast forward** setzt `prod` per Fast-forward auf den Stand von `develop`; anschliessend startet **CD Prod** automatisch
5. Optional ein Release-Tag setzen (z. B. `v1.0.0`)

## Pull-Request-Richtlinien

- PR-Titel beschreibt den Zweck klar (z. B. `Add rental return validation`).
- Verwende den Prefix `feat:`, `fix:` oder `chore:` und haenge die Issue-Nummer an (`(#123)`).
- Beschreibe kurz, warum die Aenderung noetig ist.
- Fasse zusammen, was getestet wurde.
- Wenn moeglich, verlinke Issues oder Aufgaben.

## Schutz von Branches (GitHub)

### Empfohlen fuer `prod`

- Require a pull request before merging
- Require approvals (mindestens 1 bei Teamarbeit)
- Require status checks to pass before merging (CI-Checks: `Frontend Build`, `Backend Composer`)
- Require branches to be up to date before merging
- Restrict who can push to matching branches
- Do not allow bypassing the above settings
- Allow force pushes: OFF
- Allow deletions: OFF

### Optional fuer `develop`

- PR-Pflicht
- Keine Force Pushes
- Keine Branch-Loeschung

## GitHub Actions (CI/CD)

### Aktive Workflows

- `CI` in `.github/workflows/ci.yml`
  - Jobs: Locales, Frontend (ESLint, Vitest, Build), Backend (Composer, PHPUnit, PHPStan), Aggregator **CI ok**
  - Trigger: Push/PR auf `develop` und `prod`
- `E2E Smoke` in `.github/workflows/e2e-smoke.yml` (Playwright gegen Develop; Secrets siehe [docs/E2E.md](docs/E2E.md); vorerst nicht Teil von **CI ok**)
- `CD Develop` in `.github/workflows/cd-develop.yml`
  - Trigger: Push auf `develop` (zusaetzlich manuell startbar)
  - Deploy auf Develop-Server per SSH
- `CD Prod` in `.github/workflows/cd-prod.yml`
  - Trigger: Push auf `prod` (zusaetzlich manuell startbar)
  - Deploy auf Produktions-Server per SSH
- `Fast forward` in `.github/workflows/fast-forward.yml`
  - Kommentar **`/fast-forward`** auf dem Release-PR (`develop` -> `prod`)
  - Nur Personen mit **Maintain** oder **Admin**; kein separater Bot-Token (nutzt `GITHUB_TOKEN` + Ruleset-Bypass fuer GitHub Actions auf `prod`)
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

## Fragen und Abstimmung

Wenn du bei einer groesseren Aenderung unsicher bist (Architektur, Datenmodell, Workflow),
bitte zuerst kurz im Team abstimmen, bevor du mit der Implementierung startest.
