# Statische Dateien (`public/`)

Alles in diesem Ordner wird von **Vite** beim Build **unverändert** ins Wurzelverzeichnis der Site kopiert und ist unter **`/`** erreichbar.

| Datei | URL im Browser | Hinweis |
|--------|----------------|--------|
| `favicon.svg` | `/favicon.svg` | Tab-Icon (EMC grün `#10b981`) – Farben parallel zu `src/styles/ui/brand-tokens.css` |
| `og-image.png` | `/og-image.png` | Open-Graph-Vorschaubild (1200×630) für Link-Shares — exportiert aus `og-image.svg` |
| `og-image.svg` | `/og-image.svg` | Quelldatei zum Bearbeiten/Vorschau des OG-Bilds |
| `favicon.ico` (optional) | `/favicon.ico` | Klassisches ICO; wenn vorhanden, in `index.html` zusätzlich eintragen |

**SEO (Marketing-Build):** `robots.txt`, `sitemap.xml` und `llms.txt` werden beim Build durch `vite-plugin-site-seo.ts` erzeugt (nur `VITE_DEPLOY_VARIANT=home`). App-/QR-Build setzt `noindex` und `Disallow: /`.

**Favicon tauschen:** Datei hier ersetzen oder neue Datei ablegen und `frontend/index.html` (`<link rel="icon" …>`) anpassen.
