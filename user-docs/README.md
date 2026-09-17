# Öffentliche Nutzerhilfe (`docs.ematchef.ch`)

VitePress, drei Sprachordner. Touren bleiben in der App. **Weblate ist nur für die App-UI** (`frontend/src/locales`), nicht für diese Markdown-Hilfe.

```text
user-docs/
  de/     Deutsch (Quelle, zuerst pflegen)
  en/     English (gleiche Seiten parallel)
  fr/     Français (gleiche Seiten parallel)
  index.md   Sprachenwahl unter /
```

Neue Seite: Datei in **allen drei** Ordnern anlegen (sonst fehlt die Sprache im Umschalter-Inhalt) und in `.vitepress/config.ts` in die jeweilige Sidebar eintragen.

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

## Crawler

`public/robots.txt`: Search und AI-Input erlaubt, Training nicht. `llms.txt` listet die öffentlichen Hilfeseiten aller Sprachen.
