# J+S-Katalog ↔ PDF-Formular

## Zwei J+S-Dokumente

| Dokument | Datei | Rolle |
|----------|-------|--------|
| **Gesamtkatalog** | `docs/activities/js-material/250821_JS_Leihmaterial_Katalog_DE.pdf` | Alles J+S-Leihmaterial — **Referenz only** (Link im Modal) |
| **Bestellformular Lagersport/Trekking** | `bestellformular_lagersport_trekking_d.pdf` | **Bestellbar** für Pfadi-Lager/Events dieser Sportart |

Der eMatChef-Katalog `dept_js00000` und das Bestell-Modal enthalten **nur** die Zeilen des **Bestellformulars** — nicht den vollen Gesamtkatalog.

## Manifest

`pdf_catalog_manifest.json` — kanonische Liste (28 Positionen = Formularzeilen inkl. 6 Schwimmwesten-Grössen).

## Sync ausführen

```bash
docker compose exec backend php bin/console app:js-catalog-sync-pdf --dry-run
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:js-catalog-sync-pdf
```

## Pflege

Neue **bestellbare** Zeilen: Manifest + `JsOrderPdfFieldMapper` + `JsDotationRulesService` — immer am **Bestellformular**, nicht am Gesamtkatalog orientieren.
