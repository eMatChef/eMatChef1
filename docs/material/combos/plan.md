# Umbauplan: Kombos & Konfigurator

Abarbeitbare Checkliste für den Umbau auf das bereinigte Combo-Modell. Das **Warum/Zielmodell** steht im [README.md](./README.md) (insb. Abschnitt 0 + 6). Dieser Plan = **Was & in welcher Reihenfolge**.

**Stand:** Mai 2026 · Pakete 0–7, von klein → groß. **Erledigt: 0–4** (Mini-Fixes; `combo_status` draft/ready inkl. Migration `Version20260529120000`; `reservation_mode` end-to-end entfernt inkl. Drop-Migration `Version20260529130000`; drei Typen im Wizard mit Klartext-Karten/Badges + Hülle-Hinweis; verwandtes Zubehör als separate Empfehlung inkl. Entities/Migration `Version20260529140000`/API, Detail-Tab, Vorlage + Aktivitäts-Vorschlag, „fertigstellen" für physische Kombo). **Nächstes: Paket 5.**

---

## Leitprinzipien

- **Kein Fallback / keine Abwärtskompatibilität** — nichts ist produktiv. Migrationen dürfen Spalten **droppen/umbenennen**, Seed-Daten direkt anpassen, keine Kompatibilitäts-Shims.
- **Jedes Paket ist in einem Chat erledigbar** und hinterlässt einen lauffähigen Stand (Build grün).
- **Reihenfolge einhalten** — spätere Pakete bauen auf früheren auf (Spalte „Hängt ab von").
- **Doku mitführen** — bei Modell-/Entscheidungsänderungen `README.md` aktualisieren.
- **Übersetzung** — nur ihn de.json und en.json nachführen.

## Status-Legende

`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt

---

## Übersicht

| # | Paket | Größe | Hängt ab von | Status |
|---|-------|-------|--------------|--------|
| 0 | Mini-Fixes | XS | – | [x] |
| 1 | DB-Fundament: Entwurfs-Flag | S | – | [x] |
| 2 | `reservation_mode` entfernen (end-to-end) | S–M | – | [x] |
| 3 | Drei Typen im Wizard | M | 1, 2 | [x] |
| 4 | Physische Kombo finalisieren | M | 3 | [x] |
| 5 | Virtuelle Kombo + Options-/Delta-Fundament | XL | 4 | [ ] |
| 6 | Konfigurator-UI (Auswahl-Gruppen) | L | 5 | [ ] |
| 7 | Komfort / Cross-Cutting | M | 5, 6 | [ ] |

> **Weg B (vereinheitlicht):** Alles Wählbare ist eine **Option mit Delta-Liste**; `is_optional` ist nur Anzeige-Flag „Toggle". Darum baut **Paket 5 das Options-/Delta-Schema gleich mit** (auch der einfache Ja/Nein-Fall nutzt es). Paket 6 setzt nur die **Gruppen-/Auswahl-UI** obendrauf, kein neues Fundament. Es gibt **keinen** billigen Bool-Zwischenschritt.

---

## Zentrale Steuerstellen (bei jedem Paket berücksichtigen)

Diese Stellen steuern Typ-/Status-/Verfügbarkeitslogik zentral — Änderungen am Combo-Modell strahlen hierhin aus:

**Backend**
- `backend/src/Entity/MaterialItem.php` — zentrale Typ-/Status-Definition + Helfer `isCombo()` / `isComboDraft()`.
- `backend/src/Service/MaterialAvailabilityReservationQuery.php` — **die** Reservierungs-SQL (summiert `activity_item.quantity` pro `material_item_id`; Basis für Zeilenmodell B in Paket 5).
- `backend/src/Controller/MaterialAvailabilityController.php` — Verfügbarkeit + Draft-Ausschluss (`combo_status <> 'draft'`).
- `backend/src/Controller/MaterialController.php` / `TemplateController.php` — CRUD, Serialisierung, `finalize-combo`.

**Frontend**
- `frontend/src/api/materials.ts` + `api/templates.ts` — **zentrale TS-Typen** (jede Feld-Änderung zuerst hier).
- `composables/useActivityCreateWizard.ts` — Aktivitäts-Materialauswahl.
- `components/activities/shared/activityAvailabilityMaterial.ts`, `packMaterialDisplay.ts`, `packShellCrateHelpers.ts` — Verfügbarkeits-/Pack-Anzeige, verzweigt auf `material_type`.
- Wizard: `wizard/CreationModeStep.vue`, `MaterialTypeToggles.vue`, `SelectedModeBanner.vue` (Paket 3).
- ⚠️ **Kein** zentraler Frontend-Helfer für „ist Kombo": `isComboMaterial` ist **dupliziert** (`MaterialsView.vue` + `MaterialDetailView.vue`). Bei Typ-Logik-Änderungen beide Stellen anfassen — oder vorher in einen gemeinsamen Helfer ziehen.

---

## Paket 0 — Mini-Fixes

**Ziel:** Offensichtliche Inkonsistenzen weg, ohne Modelländerung.

**Betroffen:**
- `frontend/src/components/material/MaterialDetailView.vue`
- `frontend/src/components/material/MaterialCreateWizard.vue`

**Schritte:**
- [x] `is_optional`-Haken + Badge im Zusammensetzungs-Tab nur bei `virtual_combo` zeigen (bei `physical_combo` ausblenden, erzwungen `false`).
- [x] Analog im Wizard: optional-Toggle der Komponenten nur bei virtueller Kombo.

**Definition of Done:** Physische Kombo zeigt nirgends „optional"; Build + Lint grün.

---

## Paket 1 — DB-Fundament: Entwurfs-Flag (`draft/ready`)

**Ziel:** Kombos/Konfiguratoren werden als „Hülle" angelegt und erst im Detail fertiggestellt; halbfertige sind nicht buchbar.

**Betroffen:**
- `backend/src/Entity/MaterialItem.php` (neues Feld)
- `backend/migrations/` (neue Migration)
- `backend/src/Controller/MaterialController.php` (set bei Create, serialize)
- `backend/src/Controller/MaterialAvailabilityController.php` (draft ausschließen)
- `frontend/src/components/material/MaterialDetailView.vue` („fertigstellen"-Aktion)
- `frontend/src/components/material/MaterialCreateWizard.vue` (Create → draft)
- `frontend/src/views/MaterialsView.vue` (Badge „in Bearbeitung")
- `frontend/src/api/materials.ts` (Feld im Typ)

**Schritte:**
- [x] Feld `combo_status` (`draft` | `ready`) auf `MaterialItem` (nur relevant für Combo-Typen; Einzelartikel default `ready`).
- [x] Migration (Default für bestehende Combos sinnvoll setzen, z. B. `ready`).
- [x] Create einer Kombo/Konfigurator → `draft`.
- [x] Detail-Tab: Aktion „Kombo fertigstellen" → `ready` (mit Mindest-Validierung: ≥1 Pflichtteil).
- [x] Verfügbarkeits-/Lookup-Abfrage: `draft` ausschließen (nicht buchbar in Aktivitäten).
- [x] Badge „in Bearbeitung" in Materialliste + Detail.

**Definition of Done:** Neue Kombo ist `draft`, taucht nicht im Aktivitäts-Materiallookup auf, lässt sich im Detail auf `ready` setzen und ist dann buchbar.

---

## Paket 2 — `reservation_mode` entfernen (end-to-end)

**Ziel:** Das wirkungslose, verwirrende Feld komplett raus (Verhalten leitet sich aus Typ + Zubehör ab, siehe README Abschnitt 0).

**Betroffen** (vollständige Trefferliste aus `grep reservation_mode`):
- `backend/src/Entity/MaterialItem.php`, `backend/src/Entity/MaterialTemplate.php`
- `backend/migrations/` (neue Migration: Spalten droppen) — bestehende Migration `Version20260210130000.php` legte u. a. `reservation_mode` an (nur als Referenz, nicht editieren).
- `backend/src/Controller/MaterialController.php`, `TemplateController.php`, `ImportTemplatesCommand.php`
- `frontend/src/components/material/MaterialCreateWizard.vue` (Reservations-Radios raus)
- `frontend/src/components/material/MaterialDetailView.vue` (Dropdown + `formatReservationModeLabel` raus)
- `frontend/src/components/template/TemplateEditDialog.vue`
- `frontend/src/api/materials.ts`, `templates.ts` (Feld raus)
- `frontend/src/locales/de.json`, `en.json` (Keys aufräumen — laut Leitprinzip nur diese zwei; `reservation_mode`-Keys liegen zusätzlich noch in `en-US.json`, `fr-FR.json`, `it-IT.json` und können bei Bedarf mit raus)
- Vorlagen-Daten: `backend/data/templates/{zelthangar,tortuga,wico,hajk,spatz}.json`
- Seeds: `backend/data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json`

**Schritte:**
- [x] Alle Lesezugriffe/Setter/Serialisierungen von `reservation_mode` entfernen (Entity-Properties + Getter/Setter, Controller-Mapping, `serializeMaterial`/Template-Serialisierung).
- [x] UI-Elemente (Radios, Dropdown, Hints, `formatReservationModeLabel`) entfernen.
- [x] Zentrale TS-Typen (`api/materials.ts`, `templates.ts`) bereinigen — zuerst, damit Build-Fehler die restlichen Fundstellen zeigen.
- [x] DB-Spalten in `material_item` und `material_template` droppen (neue Migration).
- [x] Vorlagen-JSON + Seeds bereinigen; `ImportTemplatesCommand.php` darf `reservation_mode` nicht mehr lesen.
- [x] Ungenutzte i18n-Keys (`resOnlyComplete`, `tentRes*`, `labelReservationMode*` …) entfernen.

**Definition of Done:** Kein `reservation_mode` mehr im Code/DB; Build + Lint grün; Material/Vorlage anlegen+bearbeiten funktioniert.

---

## Paket 3 — Drei Typen im Wizard (Hülle anlegen)

**Ziel:** Erstell-Wizard zeigt klar **drei** Typen (Einzelartikel · Physische Kombo · Virtuelle Kombo) und erfasst nur die „Hülle". **Konfigurator ist kein eigener Typ** — er ergibt sich später aus der Zusammensetzung (Paket 6).

**Betroffen:**
- `frontend/src/components/material/wizard/CreationModeStep.vue`
- `frontend/src/components/material/wizard/SelectedModeBanner.vue`
- `frontend/src/components/material/MaterialCreateWizard.vue`
- `frontend/src/locales/de.json`, `en.json`

**Schritte:**
- [x] Karten bleiben 3, mit Klartext-Beschreibungen (wann nehme ich was?) + konsistente Badges/Icons.
- [x] Kein neuer `material_type`, kein `is_configurable` bei Erstellung — „Konfigurator-Eigenschaft" wird in Paket 6 aus den Options-Gruppen abgeleitet.
- [x] Wizard erfasst nur Typ/Name/Kategorie/Basisteile → Material als `draft` (Paket 1).
- [x] Hinweis „Stückliste/Optionen im Detail fertigstellen".

**Definition of Done:** Drei Typen anlegbar als `draft`; klare Typ-Auswahl; Build grün.

---

## Paket 4 — Physische Kombo finalisieren

**Ziel:** Physische Kombo (Kochkiste, Zelt im Sack) sauber in Wizard + Detail + Vorlagen; verwandtes Zubehör als separate Empfehlung.

**Betroffen:**
- `backend/src/Entity/` (neue Relation „verwandtes Zubehör": z. B. `MaterialRelatedAccessory` oder Feld-Liste)
- `backend/migrations/`
- `backend/src/Controller/MaterialController.php`
- `frontend/src/components/material/MaterialDetailView.vue` (Zubehör-Verwaltung im Zusammensetzungs-Tab)
- `frontend/src/components/material/MaterialCreateWizard.vue`
- `frontend/src/components/template/TemplateEditDialog.vue`
- `frontend/src/components/activities/ActivityMaterialAvailabilityLookup.vue` (Vorschlag „Zubehör dazu?")

**Schritte:**
- [x] Relation „verwandtes Zubehör" (Material → Materialien) – Entity + Migration + API. **Für alle Typen nutzbar** (physisch und virtuell), nicht nur physische Kombo. (`MaterialRelatedAccessory` + `MaterialTemplateRelatedAccessory`, Migration `Version20260529140000`, CRUD `/materials/{id}/related-accessories`.)
- [x] Detail-Tab: Zubehör-Liste verwalten (getrennt von Stückliste/Optionen). (Eigene Karte „Verwandtes Zubehör" im Zusammensetzungs-Tab.)
- [x] Vorlage: Zubehör mitführen, beim Erstellen übertragen. (Tab „Zubehör" im `TemplateEditDialog`; Auflösung zu konkreten Materialien beim „Vorlage → Material".)
- [x] Aktivitäts-Flow: nach Hinzufügen einer Kombo verwandtes Zubehör als **eigene Positionen** vorschlagen (nur wenn verfügbar). (Vorschlag-Panel in `ActivityMaterialAvailabilityLookup`, Zeitraum-Verfügbarkeit über `available-for-period`.)
- [x] „fertigstellen" (Paket 1) auch für physische Kombo. (Bereits via `isComboDraft`/`finalize-combo` für beide Typen; verifiziert.)

**Definition of Done:** Physische Kombo komplett anlegbar/bearbeitbar inkl. Zubehör-Vorschlag im Buchungs-Flow.

---

## Paket 5 — Virtuelle Kombo + Options-/Delta-Fundament

**Ziel:** Virtuelle Kombo end-to-end inkl. **vereinheitlichtem Options-/Delta-Modell (Weg B)**: Basis-Stückliste + Optionen (vorerst nur Anzeige-Modus `toggle` = Ja/Nein), Verfügbarkeit, Buchung, Packen. Das DB-Fundament wird hier so gebaut, dass Paket 6 (Gruppen) nur die UI ergänzt.

**Betroffen:**
- `backend/src/Entity/` (neu: `MaterialComboOptionGroup`, `MaterialComboOption`, `MaterialComboOptionDelta`; analog Vorlage `MaterialTemplateOption*` — Schema deckt von Anfang an Gruppen/Auswahl-Modus mit ab, auch wenn UI erst Toggle nutzt)
- `backend/src/Entity/ActivityItem.php` (+ `parent_activity_item_id`, `config_snapshot`)
- `backend/src/Entity/MaterialComboComponent.php` (+ `component_source`)
- `backend/migrations/`
- `frontend/src/components/material/MaterialDetailView.vue`, `MaterialCreateWizard.vue`, `TemplateEditDialog.vue`
- `backend/src/Controller/MaterialController.php`, `TemplateController.php`, `MaterialAvailabilityController.php`, `backend/src/Service/MaterialAvailabilityReservationQuery.php`
- `backend/src/Controller/ActivityController.php`, `ActivityWorkflowController.php`, `backend/src/Service/PackPipelineService.php`
- `frontend/src/composables/useActivityCreateWizard.ts`, `frontend/src/components/activities/ActivityMaterialAvailabilityLookup.vue`

**Schritte:**
- [ ] **Options-/Delta-Schema** (Kombo **und** Vorlage) nach **README Abschnitt 6 „Datenbank-Schema (Detail)"**: `material_combo_option_group` / `material_combo_option` (`display_mode` toggle|group) / `material_combo_option_delta` (`qty_delta` signed) + `component_source` auf `material_combo_component`; gespiegelt als `material_template_*`. `is_optional` wird zum abgeleiteten Anzeige-Flag „Toggle" (kein separater Mechanismus).
- [ ] **Komponenten-Quelle** `component_source` (`stock` | `self_provided`) auf Basis- **und** Delta-Zeilen. `self_provided` (z. B. Mast): **nicht** reserviert/im Flaschenhals, nur Checklisten-/Hinweis-Posten. **Validierung: ≥ 1 `stock`-Teil je Kombo.**
- [ ] Detail/Wizard/Vorlage: Basis-Stückliste + Ja/Nein-Optionen (Toggle), `component_source` wählbar. (Gruppen-UI erst Paket 6.)
- [ ] **Backend Verfügbarkeit:** virtuelle Kombo in Komponenten auflösen → Flaschenhals `min(floor(frei/menge))` nur über `stock`-Teile; Delta-Klemmung auf **≥ 0** (README Abschnitt 6/7).
- [ ] **`ready`-Validierung = Gesamtmenge:** Fertigstellen/Buchen prüft Verfügbarkeit der gesamthaft benötigten `stock`-Pflichtteile (Flaschenhals × Stückzahl) im Zeitraum.
- [ ] Aktivitäts-Lookup zeigt „X× verfügbar"; Toggle-Optionen an/aus; **Hartsperre auch für Toggles**, die nicht verfügbare `stock`-Teile hinzufügen; `self_provided`-Teile als Hinweis gelistet.
- [ ] **Zeilenmodell B (hybrid):** `activity_item` um `parent_activity_item_id` (NULL = Eltern/normal) + `config_snapshot` (JSON, an Eltern-Zeile) erweitern. Buchung erzeugt Eltern-Zeile (Kombo) + Kind-Zeilen pro `stock`-Teil → bestehende Reservierungs-SQL zählt die Kinder automatisch. `self_provided` ohne Kind-Zeile (nur Hinweis).
- [ ] Packen (MW): Pick-/Scan-Liste je `on_issue`-Komponente → `component_batch_id` setzen; Pack-/Sperr-Positionen pro Komponente (da Kombo keine Batches hat).

**Definition of Done:** Virtuelle Kombo mit Basis + Ja/Nein-Optionen (auch abziehende) buchbar; korrekte Flaschenhals-Verfügbarkeit inkl. Hartsperre; `self_provided` als Hinweis; MW kann beim Packen Seriennummern zuweisen.

---

## Paket 6 — Konfigurator-UI (Auswahl-Gruppen)

**Ziel:** Auf dem Fundament aus Paket 5 die **Gruppen-/Auswahl-Fälle** ergänzen: Entweder-Oder, „1 oder 2", Mengen-Wahl. Kein neues Datenfundament.

**Betroffen:**
- `backend/src/Controller/MaterialAvailabilityController.php` (Verfügbarkeit pro Option/Gruppe)
- `frontend/src/components/material/MaterialDetailView.vue` (Gruppen-/Auswahl-Editor, Button „Delta/Optionen")
- `frontend/src/components/template/TemplateEditDialog.vue`
- Aktivitäts-Zusammenstell-Schritt (neu) + `useActivityCreateWizard.ts` (Zeilenmodell mit gewählter Konfiguration)

**Schritte:**
- [ ] „Konfigurator-Eigenschaft" **abgeleitet**: virtuelle Kombo mit ≥ 1 Options-Gruppe (Anzeige-Modus `group`) verhält sich als Konfigurator (Badge/Buchungs-UI), kein eigener `material_type`/Flag.
- [ ] Button „Delta/Optionen" im Zusammensetzungs-Tab klappt die Gruppen-Maschinerie auf (Progressive Disclosure).
- [ ] Detail-/Vorlagen-Editor: Gruppen (Auswahltyp exklusiv/Menge, min/max) + Optionen mit ±Delta (Vorzeichen sichtbar, Live-Vorschau der Endmenge).
- [ ] Verfügbarkeit **pro Option/Gruppe** (Flaschenhals, Zeitraum, nur `stock`-Teile) → nicht verfügbare Option **hart gesperrt**, exklusive Gruppe lenkt zur Alternative.
- [ ] Aktivitäts-Zusammenstell-Schritt (Gruppen wählen → Endmenge berechnen × Bestellmenge).
- [ ] Gewählte Konfiguration via **Zeilenmodell B** ablegen: `config_snapshot` an der Eltern-Zeile, Kind-Zeilen aus der aufgelösten Endmenge (Struktur steht aus Paket 5).

**Definition of Done:** Konfigurierbares Zelt/Blachenburg anlegbar (inkl. Entweder-Oder + „1 oder 2" + ±Delta), in der Aktivität zusammenstellbar mit korrekter Verfügbarkeit.

---

## Paket 7 — Komfort / Cross-Cutting

**Ziel:** UX-Feinschliff über alle Typen.

**Betroffen:**
- `frontend/src/components/activities/*` (Lookup, Detail, Pack)
- ggf. Backend (Überlapp-Erkennung)

**Schritte:**
- [ ] „Kombinieren?"-Dialog: Überlapp einer Kombo-Option mit vorhandener Aktivitäts-Position erkennen → fragen statt doppelt reservieren (README Abschnitt 7).
- [ ] Set-Anzeige der gebuchten virtuellen Kombo „wie Kiste" (Hülle + Inhalt).
- [ ] Badges überall konsistent (📦 / 🟦 phys / 🟪 virt / 🧩 konfigurierbar).

**Definition of Done:** Buchung von Kombos fühlt sich rund an; keine Doppelreservierung innerhalb einer Aktivität.

---

## Offene Punkte (vor dem jeweiligen Paket entscheiden)

Alle Grundsatzentscheidungen sind getroffen. Es bleiben nur **Implementierungs-Detailfragen** (im jeweiligen Schritt zu klären): Index-/Kaskaden-Details der neuen Tabellen, genaues JSON-Format des Konfig-Snapshots.

> **Entschieden:**
> - **Konfigurator = kein eigener Typ** — abgeleitet aus der Zusammensetzung (Options-Gruppe vorhanden). Kein neuer `material_type`, kein `is_configurable`.
> - **Ein vereinheitlichtes Options-Modell (Weg B):** `is_optional` = nur Anzeige-Flag „Toggle"; Anzeige-Modus **entkoppelt** von den Deltas. Ein Code-Pfad, Schema früh (Paket 5).
> - **#1 Direktbuchungs-Schutz = A (kein Schutz, Default):** Kombo = nur Buchungs-Gruppierung; Bestandskonflikt schon über Verfügbarkeits-SQL gelöst. Optionales Schutz-Flag (B) ggf. später.
> - **#2 DB-Schema = definiert** (README Abschnitt 6 „Datenbank-Schema (Detail)"): `material_combo_option_group` / `…_option` / `…_option_delta` + `component_source` auf `material_combo_component`; gespiegelt als `material_template_*`.
> - **#3 Zeilenmodell Aktivität = B (hybrid):** Eltern-`activity_item` (Kombo + `config_snapshot`) + Kind-Zeilen pro `stock`-Teil (`parent_activity_item_id`); nutzt die bestehende Reservierungs-SQL.
> - **Verwandtes Zubehör für alle Typen**; **≥ 1 `stock`-Teil je Kombo**; **`ready`-Validierung = Gesamtmenge** der `stock`-Pflichtteile verfügbar.

---

## Siehe auch

- [README.md](./README.md) — Konzept & Zielmodell
- [../../activities/material-pipeline.md](../../activities/material-pipeline.md) — Bestellung/Pack-Pipeline
