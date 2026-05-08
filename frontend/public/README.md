# Statische Dateien (`public/`)

Alles in diesem Ordner wird von **Vite** beim Build **unverändert** ins Wurzelverzeichnis der Site kopiert und ist unter **`/`** erreichbar.

| Datei | URL im Browser | Hinweis |
|--------|----------------|--------|
| `favicon.svg` | `/favicon.svg` | Tab-Icon (EMC grün `#10b981`) – Farben parallel zu `src/styles/ui/brand-tokens.css` |
| `favicon.ico` (optional) | `/favicon.ico` | Klassisches ICO; wenn vorhanden, in `index.html` zusätzlich eintragen |
| `robots.txt` (optional) | `/robots.txt` | Crawler |

**Favicon tauschen:** Datei hier ersetzen oder neue Datei ablegen und `frontend/index.html` (`<link rel="icon" …>`) anpassen.
