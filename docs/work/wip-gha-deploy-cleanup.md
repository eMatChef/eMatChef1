# WIP: GitHub Actions / Deploy-Aufräumen

Stand: 2026-08-16 — Staging/Prod App-Deploy per CI ergänzt (`deploy-frontend-staging.yml` / `deploy-frontend-prod.yml`).

## Erledigt

- Droplet-Deploy Staging + Prod (rsync App), Secrets `STAGING_APP_WEBROOT` / `PROD_APP_WEBROOT`
- Docs: `docs/APP-ON-DROPLET.md`

## Optional noch offen

1. FTP Develop/Staging auf `workflow_dispatch`-only oder entfernen
2. FTP Prod: nur Marketing (bereits so)
3. Caddy Staging: optional `basic_auth` wieder aktivieren

Diese Datei kann nach dem nächsten Cleanup-PR gelöscht werden.
