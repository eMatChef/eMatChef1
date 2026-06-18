# J+S-Katalog ↔ PDF-Formular

## Zwei J+S-Dokumente

| Dokument | Datei | Rolle |
|----------|-------|--------|
| **Gesamtkatalog** | `docs/activities/js-material/250821_JS_Leihmaterial_Katalog_DE.pdf` | Alles J+S-Leihmaterial — **Referenz only** (Link im Modal) |
| **Bestellformular Lagersport/Trekking** | `bestellformular_lagersport_trekking_d.pdf` | **Bestellbar** für Pfadi-Lager/Events dieser Sportart |

Der eMatChef-Katalog `dept_js00000` und das Bestell-Modal filtern per **Kategorie «Lagersport & Trekking»** (`catjslagtr01`) — nicht den vollen Gesamtkatalog.

Superadmin-Pflege: **Verwaltung → J+S-Leihkatalog** (`/admin-dashboard/verwaltung/js-leihkatalog`).

## Manifest

`pdf_catalog_manifest.json` — Bootstrap/Sync für Namen und PDF-Zeilennummer (`material_item.no`). **Dropdown zur Laufzeit: DB + Kategorie**, nicht JSON.

## Sync ausführen

```bash
docker compose exec backend php bin/console app:js-catalog-sync-pdf --dry-run
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:js-catalog-sync-pdf
```

## Pflege

Neue **bestellbare** Zeilen: Manifest + `JsOrderPdfFieldMapper` + `JsDotationRulesService` — immer am **Bestellformular**, nicht am Gesamtkatalog orientieren.
