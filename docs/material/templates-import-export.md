# Material-Vorlagen Import/Export (v5 JSON)

Zentraler Workflow, um Vorlagen lokal zu bearbeiten und reproduzierbar auf Prod zu bringen — analog zum Material-CSV-Import, aber als JSON.

Verwandt:

- [templates/README.md](./templates/README.md) — Vorlagen-Editor, Hersteller-Picker, Start-Assistent, Komponenten-Auflösung (Konzept)
- [templates/plan.md](./templates/plan.md) — Paket 1–2 (Editor erledigt, Auflösung offen)
- [combos/README.md](./combos/README.md) — `option_groups`, Konfigurator, Optional vs. Zubehör

---

| Schicht | Datei |
|---------|--------|
| Service | `backend/src/Service/TemplateImportExportService.php` |
| API | `POST /api/templates/import`, `GET /api/templates/export` |
| CLI | `app:templates:import`, `app:templates:export` |
| UI | `TemplatesSettingsView.vue` (Tabs Import / Export) |

## Workflow lokal → Prod

1. **Lokal:** Vorlage in der UI bearbeiten oder per CLI exportieren:
   ```bash
   php bin/console app:templates:export --global --manufacturer=Zelthangar \
     --output=data/templates/zelthangar-export.json
   ```
2. **Optional:** JSON manuell anpassen (Git-diff-freundlich).
3. **Prod:** Import mit Update (bestehende Vorlagen überschreiben):
   ```bash
   php bin/console app:templates:import --global --file=data/templates/zelthangar-export.json --force
   ```
   Oder in der UI: Tab **Import** → Datei wählen → *Bei Duplikat: Aktualisieren* → Import.

## v5 JSON-Schema

Wurzel:

```json
{
  "format_version": 5,
  "manufacturer": "Zelthangar",
  "templates": [ … ]
}
```

Pro Template (v4-Felder camelCase, Erweiterungen snake_case wie API):

```json
{
  "name": "Zelthangar Phoenix Plane",
  "model": "Phoenix",
  "description": "Nur Außenzelt / Plane",
  "capacity": 16,
  "tentType": "gruppenzelt",
  "materialType": "virtual_combo",
  "isActive": true,
  "source": "zelthangar",
  "components": [
    {
      "type": "aussenzelt",
      "name": "Außenzelt",
      "required": 1,
      "optional": false,
      "tracking": "serialized",
      "component_source": "stock",
      "is_generic": false,
      "repair_types": ["loch", "riss"]
    }
  ],
  "related_accessories": [
    {
      "name": "Transporttasche",
      "component_type": "tasche",
      "is_generic": true,
      "sort_order": 0
    }
  ],
  "option_groups": [
    {
      "id": "g0",
      "name": "Innenzelt",
      "selection_type": "exclusive",
      "min_select": 0,
      "max_select": 1,
      "sort_order": 0
    }
  ],
  "options": [
    {
      "name": "6er Innenzelt",
      "display_mode": "group",
      "option_group_id": "g0",
      "default_selected": true,
      "sort_order": 0,
      "deltas": [
        {
          "component_type": "innenzelt",
          "name": "Innenzelt 6er",
          "qty_delta": 1,
          "tracking": "serialized",
          "component_source": "stock",
          "is_generic": true,
          "sort_order": 0
        }
      ]
    }
  ]
}
```

**Rückwärtskompatibel:** v4-Dateien in `backend/data/templates/` ohne `option_groups` / `related_accessories` importieren weiterhin.

## API

### Import

`POST /api/templates/import`

```json
{
  "scope": "global",
  "department_id": "…",
  "templates_json": { "manufacturer": "…", "templates": [ … ] },
  "duplicate_action": "skip|update|create",
  "dry_run": false,
  "force": false
}
```

Response: `{ success, dry_run, stats: { created, updated, skipped, errors }, rows: [ … ] }`

### Export

`GET /api/templates/export?scope=global&manufacturer=Zelthangar`

Department: `scope=department&department_id=…`

## CLI-Optionen

**Import:** `--file`, `--all`, `--global`, `--force` (= update), `--dry-run`

**Export:** `--global`, `--manufacturer=…`, `--output=path.json`

## Berechtigungen

- **Global (Admin-Dashboard):** Superadmin / Organisationschef / Suborgchef
- **Department:** nur **Materialwart (MW)** — Depchef und andere Rollen sehen Import/Export weder in der UI noch per API
- **Department-Export:** nur eigene Department-Vorlagen (`scope=department`), keine zentralen

Später erweiterbar um `scope=vendor` (Supplier-Portal).

---

## Siehe auch

- [templates/README.md](./templates/README.md) — Felder `manufacturer_address_id`, `template_kind`, `template_domain` (Paket 1, Editor)
- [templates/plan.md](./templates/plan.md) — Checkliste Import/Export v5 round-trip
- [combos/README.md](./combos/README.md) — JSON-Felder `related_accessories`, `option_groups`, `options`
