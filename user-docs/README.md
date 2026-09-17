# Öffentliche Nutzerhilfe (`docs.ematchef.ch`)

VitePress-Quelle auf Deutsch. Touren bleiben in der App; hier nur nachlesbare Abläufe ohne Org-Daten.

## Lokal

```bash
cd user-docs
npm install
npm run dev
```

Build wie CI:

```bash
bash scripts/build-droplet-docs.sh
# → deploy/droplet/prod/docs/
```

## Betrieb

| | |
|---|---|
| Live | `https://docs.ematchef.ch` |
| Droplet | `ematchef-api-prod` (`178.104.22.40`) |
| Webroot | `/var/www/ematchef-docs-prod` (**nicht** `ematchef-app-prod`) |
| Caddy | `deploy/caddy/Caddyfile.prod-docs.example` |
| Deploy | Workflow `deploy-docs-prod.yml` auf Branch `prod` |
| Secret | `PROD_DOCS_WEBROOT=/var/www/ematchef-docs-prod` (SSH wie Frontend-Prod) |

Weblate: später eigene Component im gleichen Projekt `ematchef`, Dateimaske unter `user-docs/`. Die GitHub-Action **Weblate integrate develop** synct derzeit nur `frontend/src/locales/**` — Docs-Markdown dort ergänzen, sobald die Component existiert.

## Crawler

`public/robots.txt`: Search und AI-Input erlaubt, Training (`GPTBot`, `Google-Extended`, `ClaudeBot`, …) nicht. `llms.txt` listet die öffentlichen Hilfeseiten.
