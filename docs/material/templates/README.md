# Material-Vorlagen (Editor & Hersteller)

Konzept-Dokumentation zum **Vorlagen-Editor** (`TemplateEditDialog`, `TemplatesSettingsView`, `TemplateStartWizard`): generische Rezepte (nicht nur Zelte), Hersteller-Picker an das **Address-Scope-Modell**, Start-Assistent, Sortierung/Gruppierung — unabhängig vom Combo-Umbau ([combos/README.md](../combos/README.md), Pakete 0–7 erledigt).

**Stand:** Mai 2026 · **Paket 1 (Editor-Umbau) implementiert** · Paket 2 (Komponenten-Auflösung) offen — siehe [plan.md](./plan.md)

Verwandt:

- [plan.md](./plan.md) — Implementierungs-Checkliste (Pakete 1–2)
- [../templates-import-export.md](../templates-import-export.md) — v5 JSON, lokal → Prod
- [../combos/README.md](../combos/README.md) — Optional vs. Zubehör, Konfigurator
- [../../supplier/supplier-portal.md](../../supplier/supplier-portal.md) — Address-Scope, `SupplierCompany`, `MaterialWizardSupplierService`

---

## 0. Zielmodell — verbindlich

Vorlagen sind **generische Rezepte** — Küche, Apotheke, Werkstatt, Zelte. Hersteller ist **optional** und kommt aus der **gleichen Lieferanten-Quelle** wie Material-Wizard und Import (`MaterialWizardSupplierService`), nicht aus Freitext oder `GLOBAL000000`.

```text
Neu → Start-Assistent (3 Schritte)
        ↓
Editor (passende Tabs)
        ↓
Liste: Hersteller-Gruppen A–Z + „Allgemein / gemischt" am Ende
        ↓
Material-Wizard: Modus Einzelartikel | physische | virtuelle Kombo (bestehend)
```

### Was sich mit Paket 1 geändert hat

| Thema | Vorher | Jetzt |
|-------|--------|-------|
| **Einzelteil** | 1-Komponenten-Vorlage wirkte wie Combo | Badge „Einzelteil"; Assistent + `template_kind=single_part` |
| **Hersteller** | Freitext `manufacturer` | Picker → `manufacturer_address_id` (+ Anzeige-Cache `manufacturer`) |
| **Zelt-Tab** | immer sichtbar | nur bei `template_domain=tent` |
| **Optional (Komponente)** | Haken überall | nur bei `virtual_combo`; Hinweis ≠ Tab Zubehör |
| **Liste** | „Ohne Hersteller" alphabetisch mitten drin | Gruppe **„Allgemein / gemischt"** immer am Ende |
| **Lieferanten** | Scope-Migration (Supplier Paket 0), Editor nutzte sie nicht | `GET /api/templates/manufacturer-options` via `MaterialWizardSupplierService` |

---

## 1. Start-Assistent („Neue Vorlage")

Beim Klick **Neue Vorlage** zuerst `TemplateStartWizard`, dann `TemplateEditDialog`.

### Schritt 1 — Was modellieren?

| Wahl | Bedeutung | Editor-Voreinstellung |
|------|-----------|------------------------|
| **Einzelteil** | 1 Komponente → Wizard „Einzelartikel" | 1 leere Komponente; Hinweis „Keine Combo-Hülle" |
| **Combo / Set** | physische oder virtuelle Kombo | Typ wählbar im Editor |
| **Konfigurator** | virtuelle Kombo + Options-Gruppen | `material_type=virtual_combo`; Tab Konfigurator sichtbar |

> **Einzelteil ≠ eigener DB-Typ:** Weiterhin `material_template` mit **genau einer** `material_template_component`. Der Assistent setzt `template_kind=single_part` oder leitet UI ab (`components.length === 1`).

### Schritt 2 — Fachbereich

| Wahl | UI |
|------|-----|
| **Zelt / Unterkunft** | Tab **Zelt-Details** (Typ, Kapazität) |
| **Küche / Werkstatt / Erste Hilfe / …** | Zelt-Tab **ausblenden** |
| **Allgemein** | nur Name/Beschreibung/Komponenten |

Speicherung: `template_domain` (`tent` \| `kitchen` \| `workshop` \| `first_aid` \| `general` \| …) — nullable.

### Schritt 3 — Hersteller-Kontext (optional)

| Wahl | Speicherung |
|------|-------------|
| **Ein Hersteller** | Picker → `manufacturer_address_id` (+ denormalisiert `manufacturer`) |
| **Kein / gemischt** | alle Hersteller-Felder `null`; Komponenten standardmäßig **`is_generic=true`** (überschreibbar) |

Picker-Quelle: **`MaterialWizardSupplierService::listCatalogSuppliers()`** (Admin/global) bzw. **`listForDepartment($id)`** (Department-Vorlagen).

**Nicht:** Freitext, **nicht** `department_id=GLOBAL000000`.

---

## 2. Hersteller & Address-Scope-Modell

### Drei Ebenen (nicht verwechseln)

| Ebene | Feld / Tabelle | Bedeutung |
|-------|----------------|-----------|
| **Adresse** | `address.scope` = `global` \| `supplier` \| `department` | Wer ist Lieferant/Hersteller (Stammdaten) |
| **Vorlage** | `material_template.scope` = `global` \| `department` | Wer besitzt das Rezept (Plattform vs. Verein) |
| **Vorlage-Hersteller** | `manufacturer_address_id` + `manufacturer` (Anzeige) | Wofür steht **dieses Rezept als Ganzes** (optional) |

### Picker-Datenquellen (wiederverwenden)

```text
MaterialWizardSupplierService
  ├── loadActiveSupplierCompanyAddresses()   → address.scope=supplier
  ├── loadGlobalLegacySuppliers()            → address.scope=global, type=supplier
  └── loadDepartmentSuppliers(deptId)        → address.scope=department (Dep-Vorlagen)
```

Referenz: `backend/src/Service/MaterialWizardSupplierService.php`, Migration `Version20260530120000` (Address-Scope).

### DB-Felder (Paket 1)

| Spalte | Typ | Bedeutung |
|--------|-----|-----------|
| `manufacturer_address_id` | CHAR(12) NULL FK → `address` | Stabile Referenz (Picker) |
| `template_kind` | VARCHAR(20) NULL | `single_part` \| `combo` \| `configurator` |
| `template_domain` | VARCHAR(40) NULL | `tent` \| `kitchen` \| `general` \| … |

`manufacturer` (VARCHAR) bleibt als **Anzeige-Cache** für Liste/Export/JSON — bei Picker-Änderung mitsyncen.

Migration: `Version20260530240000`.

### Vorlage → Material (Namenslogik)

- `is_generic=false` + Hersteller gesetzt → Komponentenname + Modell + Hersteller
- Hersteller **null** / gemischt → Komponenten **`is_generic=true`** → „Heringe", „Kocher" ohne Marke
- Küchenbox / Apotheke: **kein** Vorlagen-Hersteller, generische Komponenten

> **Auflösung gegen Bestand** (Treffer, „nicht gefunden", manuelle Wahl): Paket 2 — [Abschnitt 5](#5-komponenten-auflösung-vorlage--material).

---

## 3. Sortierung & Gruppierung (TemplatesSettingsView)

### Gruppen (Ebene 1)

1. **Bekannte Hersteller** — sortiert **A–Z** nach Anzeigename (`manufacturer`)
2. **„Allgemein / gemischt"** — `manufacturer` und `manufacturer_address_id` beide null — **immer am Ende**

Interner Key: `__NO_MANUFACTURER__`; Label i18n: **`settings.templates.generalMixed`**.

### Innerhalb der Gruppe (Ebene 2)

- Sortierung: **`template.name` ASC**

### Filter & Export

Filter-Dropdown inkl. „Allgemein / gemischt" (`filterManufacturer = __NO_MANUFACTURER__`). Export-Tab analog.

### Admin vs. Department-Liste

| Kontext | Darstellung |
|---------|-------------|
| **Global-Admin** | nur `scope=global`; Gruppierung wie oben |
| **Department-Settings** | eigene + globale Vorlagen |

Backend-Liste: `scope ASC, manufacturer ASC NULLS LAST, name ASC`.

---

## 4. Editor-Regeln

| Mechanismus | Bedeutung | Wann im Editor |
|-------------|-----------|----------------|
| **`is_optional` (Komponente)** | Teil der **Stückliste**, beim Anlegen/Buchen weglassbar | nur **`virtual_combo`** |
| **Tab Zubehör** | **Empfehlung**, kein BOM-Teil | alle Typen |
| **Physische Kombo** | kein Optional-Haken | Zubehör nur über Tab Zubehör |

Hinweis-Texte im Editor (i18n de/en): Optional ≠ Zubehör.

### Einzelteil (ohne neuen `material_type`)

| Aspekt | Regel |
|--------|--------|
| Definition | **Einzelteil** = genau **1** Komponente |
| Assistent | „Einzelteil" → eine Komponenten-Zeile |
| Wizard | Nutzer wählt **Einzelartikel** + Vorlage |
| UX | Badge **„Einzelteil"** wenn `template_kind=single_part` oder `components.length===1` |

### Nicht-Zelt-Vorlagen

- Tab **Zelt-Details** nur wenn `template_domain=tent`.
- Hersteller **nicht** Pflicht — Küche/Apotheke: Schritt 3 „Kein / gemischt".

---

## 5. Komponenten-Auflösung (Vorlage → Material)

**Stand:** erledigt — siehe [plan.md Paket 2](./plan.md) (Backend `expected_name`/`match_state`, Material-Wizard UX)

Beim Anlegen eines Materials aus einer Vorlage muss jede Stücklisten-Zeile auf ein **bestehendes** `material_item` verweisen oder **bewusst neu** angelegt werden. Heute fehlt die explizite UX, wenn der erwartete Name im Bestand **nicht** existiert oder **anders** heißt.

### 5.1 Problem (Ist)

| Ebene | Verhalten heute | Folge |
|-------|-----------------|-------|
| **Backend** (`createMaterial`) | Nach `material_id` → sonst **exakter Name** → sonst **still neues Material** | Duplikate z. B. „Außenzelt" vs. „Außenzelt Phoenix Zelthangar" |
| **Frontend** (Wizard) | Auto-Treffer nur **Bulk**, nur gegen **`comp.name`** | Serialisierte Teile starten immer mit mode **`new`** |
| **Konfigurator** | Fehlendes Material → Option entfällt, kein Wizard-Hinweis | Unvollständige Basis-Map |

### 5.2 Erwarteter Name (`expected_name`)

```text
is_generic = true   → expected_name = component.name
is_generic = false  → component.name + Modell + Hersteller (wenn nicht schon enthalten)
```

API-Vorschlag pro Komponente: `expected_name`, `match_state` (`found` \| `missing` \| `ambiguous`), `matched_material_id`, `candidates`.

Frontend darf die Namenslogik **nicht** duplizieren — `expected_name` kommt vom Server.

### 5.3 UI-Zustände im Material-Wizard (Soll)

| Zustand | Anzeige | Default-Modus |
|---------|---------|---------------|
| **`found`** | ✓ „Verknüpft mit: *{material.name}*" | `existing` + `material_id` |
| **`missing`** | ⚠ „Material **{expected_name}** nicht im Bestand — **bitte Artikel wählen**" | `existing`, Suche vorausgefüllt |
| **`ambiguous`** | „Mehrere Treffer — bitte eines wählen" | `existing`, Auswahl erzwingen |
| **Neu anlegen** | Toggle **„Neu kaufen"** | `new` |

Pflicht-Komponente ohne Auflösung → Submit blockieren.

### 5.4 Backend-Regeln (Soll)

| Regel | Begründung |
|-------|------------|
| `mode=existing` + `material_id` → nur dieses Material | Explizite Nutzerwahl |
| `mode=existing` ohne `material_id` → **422** | Kein stilles Neuanlegen |
| `mode=new` → neues Material mit `expected_name` | Bewusstes Anlegen |

### 5.5 Modellierungstipps (Vorlagen-Editor)

| Ziel | Empfehlung |
|------|------------|
| Teilbare Standardartikel | **`is_generic=true`** |
| Herstellerspezifisches Teil | **`is_generic=false`** + Vorlagen-Hersteller/Modell |
| Abweichende Department-Namen | **`missing`-Flow** im Wizard |

---

## 6. Code-Referenzen

| Thema | Ort |
|-------|-----|
| Vorlagen-Entity | `backend/src/Entity/MaterialTemplate.php` |
| Vorlagen-API | `backend/src/Controller/TemplateController.php` |
| Import/Export v5 | `backend/src/Service/TemplateImportExportService.php` |
| Lieferanten (Scope) | `backend/src/Service/MaterialWizardSupplierService.php` |
| Address-Scope | `backend/src/Entity/Address.php`, Migration `Version20260530120000` |
| Editor-Migration | Migration `Version20260530240000` |
| Start-Assistent | `frontend/src/components/template/TemplateStartWizard.vue` |
| Editor | `frontend/src/components/template/TemplateEditDialog.vue` |
| Liste | `frontend/src/views/settings/TemplatesSettingsView.vue` |
| Material-Wizard | `frontend/src/components/material/MaterialCreateWizard.vue` |
| Komponenten-Auflösung (Backend) | `TemplateController::createMaterial`, `resolveComponentMaterial` |

---

## Siehe auch

- [plan.md](./plan.md) — Pakete 1–2
- [../templates-import-export.md](../templates-import-export.md)
- [../combos/plan.md](../combos/plan.md) — Combo-Pakete 0–7 (erledigt)
- [../../supplier/supplier-portal.md](../../supplier/supplier-portal.md) — Abschnitt 8 Address-Scope
