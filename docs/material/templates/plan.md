# Umbauplan: Vorlagen-Editor & Komponenten-Auflösung

Abarbeitbare Checkliste für den **Vorlagen-Editor-Umbau** und das Folgepaket **Komponenten-Auflösung** (Vorlage → Material). Das **Warum/Zielmodell** steht in [README.md](./README.md). Dieser Plan = **Was & in welcher Reihenfolge**.

**Stand:** Mai 2026 · **Paket 1 + 2 erledigt**. Editor-Umbau + Komponenten-Auflösung (Vorlage → Material).

---

## Leitprinzipien

- **`MaterialWizardSupplierService` wiederverwenden** — keine dritte Lieferanten-Logik; kein `GLOBAL000000`.
- **Address-Scope** — Picker aus `address.scope=global|supplier|department` (siehe [supplier-portal.md §8](../../supplier/supplier-portal.md#81-address-scope-modell-ersetzt-global-suppliers-department)).
- **Nur `material_template`** — kein `supplier_material_template` (Supplier Phase 2) in diesen Paketen.
- **Jedes Paket ist in einem Chat erledigbar** und hinterlässt einen lauffähigen Stand (Build grün).
- **Reihenfolge einhalten** — Paket 2 baut auf Paket 1 auf (Hersteller/Generic-Modell im Editor).
- **Doku mitführen** — bei Modell-/Entscheidungsänderungen [README.md](./README.md) aktualisieren.
- **Übersetzung** — nur `de.json` und `en.json` nachführen.

## Status-Legende

`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt

---

## Übersicht

| # | Paket | Größe | Hängt ab von | Status |
|---|-------|-------|--------------|--------|
| 1 | Vorlagen-Editor-Umbau | M | Supplier Paket 0 (Address-Scope) | [x] |
| 2 | Komponenten-Auflösung (Vorlage → Material) | M–L | 1 | [x] |

> **Nicht in diesen Paketen:** Supplier-Portal Phase 2 (`supplier_material_template_*`); Phoenix-Konfigurator-Inhalte befüllen (Datenaufgabe).

---

## Zentrale Steuerstellen

**Backend**
- `backend/src/Entity/MaterialTemplate.php` — `manufacturer_address_id`, `template_kind`, `template_domain`
- `backend/src/Controller/TemplateController.php` — CRUD, `manufacturer-options`, `createMaterial`, Serialisierung
- `backend/src/Service/TemplateImportExportService.php` — v5 round-trip
- `backend/src/Service/MaterialWizardSupplierService.php` — Picker-Quelle (kein Duplikat)

**Frontend**
- `frontend/src/api/templates.ts` — zentrale TS-Typen (`TemplateKind`, `TemplateDomain`, `NO_MANUFACTURER_KEY`)
- `frontend/src/components/template/TemplateStartWizard.vue` — Start-Assistent (Neue Vorlage)
- `frontend/src/components/template/TemplateEditDialog.vue` — Editor
- `frontend/src/views/settings/TemplatesSettingsView.vue` — Liste, Filter, Gruppierung
- `frontend/src/components/material/MaterialCreateWizard.vue` — Vorlage → Material (Paket 2)

---

## Paket 1 — Vorlagen-Editor-Umbau

**Ziel:** Einzelteil-Vorlagen, Hersteller-Picker (Scope-Modell), bedingter Zelt-Tab, Sortierung „Allgemein / gemischt" am Ende, Start-Assistent, v5 Import/Export.

**Betroffen:**
- Migration `Version20260530240000`
- `MaterialTemplate.php`, `TemplateController.php`, `TemplateImportExportService.php`
- `TemplateStartWizard.vue`, `TemplateEditDialog.vue`, `TemplatesSettingsView.vue`
- `frontend/src/api/templates.ts`, i18n de/en

**Schritte:**
- [x] Migration: `manufacturer_address_id`, `template_kind`, `template_domain`
- [x] API: `GET /api/templates/manufacturer-options` (Reuse `MaterialWizardSupplierService`)
- [x] `TemplateStartWizard`: 3 Schritte vor Editor (Typ, Fachbereich, Hersteller optional)
- [x] Hersteller-Picker statt Freitext; „Kein / gemischt" → Felder null
- [x] Zelt-Tab nur bei `template_domain=tent`; Badge „Einzelteil"
- [x] Optional-Haken nur bei `virtual_combo`; Hinweis Optional ≠ Zubehör
- [x] Liste: Gruppe „Allgemein / gemischt" am Ende; innerhalb Gruppe `name ASC`; Filter + Export
- [x] Import/Export v5: `manufacturer_address_id`, `template_kind`, `template_domain`
- [x] i18n de.json + en.json
- [x] Doku: [combos/README.md](../combos/README.md) Verweis; README + plan

**Definition of Done:** Plane-only-Vorlage mit Picker Zelthangar anlegbar; Küchen-Vorlage ohne Hersteller in „Allgemein / gemischt"; Einzelteil über Assistent; Liste sortiert wie [README §3](./README.md#3-sortierung--gruppierung-templatessettingsview); Import/Export round-trip; `vue-tsc` grün (template-bezogen).

---

## Paket 2 — Komponenten-Auflösung (Vorlage → Material)

**Ziel:** Beim Anlegen aus Vorlage explizite UX für `found` / `missing` / `ambiguous` — kein stilles Anlegen von Duplikat-Artikeln; `expected_name` zentral vom Backend.

Details: [README §5](./README.md#5-komponenten-auflösung-vorlage--material)

**Betroffen:**
- `TemplateController.php` — Hilfsmethode `buildExpectedComponentName`, erweiterte Template-Response oder Endpoint, strikte `createMaterial`-Regeln
- `MaterialCreateWizard.vue` — Zustände pro Komponente, Validierung vor Submit
- `frontend/src/api/templates.ts` / `materials.ts` — Typen für `match_state`, `candidates`
- i18n de/en

**Schritte:**
- [x] Hilfsmethode `buildExpectedComponentName(template, component)` (ein Ort, Backend)
- [x] Endpoint oder erweiterte Template-Response: `expected_name`, `match_state`, `candidates` pro Komponente
- [x] Wizard: Zustände `found` / `missing` / `ambiguous`; Default `existing` bei `missing`
- [x] Submit-Validierung: Pflichtteile ohne Auflösung blockieren (Frontend + Backend)
- [x] `createMaterial`: strikte `mode=existing`-Regeln (kein stilles Neuanlegen bei fehlendem `material_id`)
- [x] Bulk-Auto-Match auf **`expected_name`** umstellen (nicht nur `comp.name`)
- [x] Konfigurator: fehlende Pflicht-`component_type`-Einträge vor Konfigurator-Schritt sichtbar machen
- [x] i18n de/en für Resolve-Meldungen
- [x] Kurzverweis in [combos/README.md](../combos/README.md)

**Definition of Done:** Vorlage mit `is_generic=false` und abweichendem Bestandsnamen → Wizard zeigt Warnung + Picker; nach Wahl Submit erfolgreich **ohne** neuen Duplikat-Artikel; Konfigurator-Basis-Map vollständig wenn alle Pflichtteile zugeordnet; Build grün.
