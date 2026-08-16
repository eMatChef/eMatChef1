# WIP: GitHub Actions / Deploy-Aufräumen (morgen)

Stand: 2026-08-11 · nach Hetzner-Cutover Develop/Staging + Prod-App auf Droplet  
Marketing Prod bleibt Hostpoint (FTP nur `home`, #196).

## Ziel morgen

1. **FTP Develop/Staging löschen oder nur Notfall** (`workflow_dispatch`)
   - `.github/workflows/ftp-deploy-develop.yml`
   - `.github/workflows/ftp-deploy-staging.yml`
   - zugehörige Scripts optional behalten (`scripts/build-hostpoint-deploy-dev.sh` / `-staging.sh`) nur falls Notfall

2. **Droplet-Deploy für Staging + Prod ergänzen** (analog `deploy-frontend-develop.yml`)
   - Secrets: `STAGING_APP_WEBROOT`, `PROD_APP_WEBROOT` (+ SSH: Staging = Develop-Droplet, Prod = Prod-SSH)
   - Trigger: Push `staging` / `prod` auf `frontend/**` + Build-Script
   - Nur **App**-Build (`build-droplet-frontend.sh staging|prod`), kein Home auf Staging/Dev

3. **FTP Prod** prüfen: läuft nur Marketing; bei Timeout Hostpoint/FTP-Erreichbarkeit aus CI

4. **Caddy Staging** optional: `basic_auth` wieder aktivieren

5. **Docs** kurz: `docs/APP-ON-DROPLET.md` + ggf. SERVER-UPDATE FTP-Abschnitte streichen

## Schon erledigt (Kontext)

| Was | Wo |
|-----|-----|
| Develop App-Einstieg | `https://dev.ematchef.ch` |
| Staging App-Einstieg | `https://staging.ematchef.ch` |
| Prod App | `https://app.ematchef.ch` (Droplet) |
| Prod Marketing | `https://ematchef.ch` (Hostpoint) |
| FTP Prod nur home | PR #196 auf develop (noch Release nach staging/prod falls offen) |
| Legacy WIP anderer Chat | Branch `wip/legacy-a-nass-feucht` — nicht vermischen |

## Nicht anfassen

- Branch `wip/legacy-a-nass-feucht`
- Weblate / CD API-Workflows (behalten)

## Reihenfolge morgen

1. PR: FTP-Cleanup + Deploy Staging/Prod Workflows von `origin/develop`  
2. Secrets setzen, einmal manuell dispatchen  
3. FF `develop` → `staging` → `prod`  
4. Diese Datei löschen  

Nach Cutover-Abschluss: diese WIP-MD entfernen.
