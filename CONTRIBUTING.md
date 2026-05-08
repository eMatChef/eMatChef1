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

## Grundregeln

- Nie direkt auf `prod` committen oder pushen.
- Neue Arbeit immer von `develop` abzweigen.
- Jede Aenderung geht per Pull Request (PR) zurueck nach `develop`.
- `prod` wird nur ueber PR aus `develop` aktualisiert.
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

## Release-Ablauf

Wenn ein Entwicklungsstand bereit ist:

1. PR von `develop` nach `prod` erstellen
2. Tests und Review abschliessen
3. PR mergen
4. Optional ein Release-Tag setzen (z. B. `v1.0.0`)

## Pull-Request-Richtlinien

- PR-Titel beschreibt den Zweck klar (z. B. `Add rental return validation`).
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
  - Check `Frontend Build`
  - Check `Backend Composer`
  - Trigger: Push/PR auf `develop` und `prod`
- `CD Develop` in `.github/workflows/cd-develop.yml`
  - Trigger: Push auf `develop` (zusaetzlich manuell startbar)
  - Deploy auf Develop-Server per SSH
- `CD Prod` in `.github/workflows/cd-prod.yml`
  - Trigger: Push auf `prod` (zusaetzlich manuell startbar)
  - Deploy auf Produktions-Server per SSH (Environment `production`)
- `Translations (Crowdin)` in `.github/workflows/translations.yml`
  - Trigger: relevante Pushes auf `prod`, Zeitplan oder manueller Run

### Required checks fuer Branch Protection

Fuer den geschuetzten Branch `prod` sollten mindestens diese Checks als verpflichtend gesetzt sein:

- `Frontend Build`
- `Backend Composer`

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
