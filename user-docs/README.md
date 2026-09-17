# Öffentliche Nutzerhilfe (`docs.ematchef.ch`)

VitePress mit Sprachordnern. **Einstieg ist Deutsch** (`/` leitet nach `/de/`). Touren bleiben in der App. **Weblate ist nur für die App-UI**, nicht für diese Markdown-Hilfe.

```text
user-docs/
  de/     Deutsch
  en/     English
  fr/     Français
  it/     Italiano
```

Neue Seite: Datei in **allen vier** Ordnern anlegen und in `.vitepress/config.ts` in die jeweilige Sidebar eintragen (Links mit Sprachpräfix, z. B. `/de/hilfe/...`).

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
| Live | `https://docs.ematchef.ch` → `/de/` |
| Droplet | `ematchef-api-prod` (`178.104.22.40`) |
| Webroot | `/var/www/ematchef-docs-prod` |
| Caddy | `deploy/caddy/Caddyfile.prod-docs.example` |
| Deploy | Workflow `deploy-docs-prod.yml` |

## Crawler

`public/robots.txt`: Search und AI-Input erlaubt, Training nicht. `llms.txt` listet die öffentlichen Hilfeseiten aller Sprachen.
