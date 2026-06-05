# Umsetzungsplan: Materialwart-Workflow 2026

Abarbeitbare Checkliste für den Werkstatt-Umbau. Das **Zielmodell** steht in [materialwart-workflow2026.md](./materialwart-workflow2026.md). Dieser Plan = **Was, in welcher Reihenfolge, wann**.

**Stand:** Juni 2026 · **Status:** Planung — noch nicht gestartet

---

## Leitprinzipien

- **Spezifikation zuerst** — Änderungen am Zielmodell in `materialwart-workflow2026.md` mitführen.
- **Inkrementell liefern** — jedes Paket hinterlässt einen nutzbaren Stand; alter Workflow bleibt bis Paket 21 parallel lauffähig.
- **Eine Zeltblatt-UI** — `RepairSheetEditor` für MW, Schadensmeldung und Supplier; nur Preisquelle wechselt.
- **Bestehende Patterns** — `department_setting` für Skalare; Supplier-Portal-Patterns für externe Vorlagen.
- **Kein Big-Bang** — `strategy`/`phase` neben altem `status` bis Migration abgeschlossen.
- **Übersetzung** — nur `de.json` und `en.json` nachführen.

## Status-Legende

`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt

---

## Zeitplan (Wellen)

| Welle | Fokus | Pakete | Ziel für MW |
|-------|--------|--------|-------------|
| **W1** | Fundament | 1–3 | Settings + neues Statusmodell in API |
| **W2** | Templates | 4–6 | Zeltblatt-Vorlagen importieren & pflegen |
| **W3** | Kern-UI | 7–9 | Triage + Zeltblatt im Ticket |
| **W4** | Intern | 10–12 | Stückliste, Lager, Einkauf |
| **W5** | Frühe Erfassung | 13 | Diagramm schon bei Schadensmeldung |
| **W6** | Extern | 14–16 | Supplier-Zeltblatt + sauberer Abschluss |
| **W7** | Kosten & Sonderfälle | 17–18 | Zeit/Pauschale/Material; Reinigung extern |
| **W8** | Inventur | 19–20 | Tab unter `/tasks/` |
| **W9** | Abschluss | 21–22 | Alte Status-UI weg; Plattform-Stamm Zelte |

> Wellen sind logische Reihenfolgen, keine fixen Kalenderwochen. **W1–W3** = MVP (Triage + Zeltblatt intern). **W4–W6** = produktionsreif für Reparatur-Alltag. **W7–W9** = Vollausbau.

---

## Übersicht Pakete

| # | Paket | Welle | Größe | Hängt ab von | Status |
|---|-------|-------|-------|--------------|--------|
| 1 | Workshop `department_setting` (Backend) | W1 | S | – | [ ] |
| 2 | Workshop Settings-UI (MW) | W1 | S | 1 | [ ] |
| 3 | `strategy` + `phase` am Ticket | W1 | M | – | [ ] |
| 4 | `repair_template` (Plattform-Stamm) | W2 | M | – | [ ] |
| 5 | `department_repair_template` + API | W2 | M | 4 | [ ] |
| 6 | Settings: Vorlagen importieren & Preise | W2 | M | 2, 5 | [ ] |
| 7 | `RepairSheetEditor` (Komponente) | W3 | L | 5 | [ ] |
| 8 | `WorkshopTriageDialog` | W3 | M | 3 | [ ] |
| 9 | WorkshopView: Triage + Zeltblatt integrieren | W3 | M | 7, 8 | [ ] |
| 10 | `RepairPartsList` (Nicht-Zelt) | W4 | M | 2, 3 | [ ] |
| 11 | Lagerentnahme bei Abschluss | W4 | M | 10 | [ ] |
| 12 | Einkauf-Flow (Quittung, Lager, Reste, Erinnerung) | W4 | L | 10, 1 | [ ] |
| 13 | Schadensmeldung: Zelt-Diagramm | W5 | M | 7 | [ ] |
| 14 | `supplier_repair_template` + Supplier-UI | W6 | L | 7 | [ ] |
| 15 | „An Lieferant senden" + Portal-Ansicht | W6 | M | 14, 9 | [ ] |
| 16 | `WorkshopTicketCompletionService` (Supplier-Fix) | W6 | M | 15 | [ ] |
| 17 | `WorkshopCostSummary` (Zeit/Pauschale/Material) | W7 | M | 1, 10, 7 | [ ] |
| 18 | Reinigung extern (Supplier-Dienste) | W7 | M | 14, 8 | [ ] |
| 19 | `inventory_task` + API | W8 | M | 3 | [ ] |
| 20 | Tab Inventur unter `/tasks/` | W8 | L | 19, 9 | [ ] |
| 21 | Status-Migration + alte UI entfernen | W9 | M | 9–18 | [ ] |
| 22 | Plattform-Seed: Spatz, Phönix, Hajk, Wico | W2/W9 | M | 4 | [ ] |

**Grössen:** S ≈ 1 Chat · M ≈ 1–2 Chats · L ≈ 2–3 Chats · XL = mehrere Chats

---

## Zentrale Steuerstellen

**Backend**
- `backend/src/Entity/DepartmentSetting.php` — `getWorkshopDefaults()`
- `backend/src/Entity/WorkshopTicket.php` — `strategy`, `phase`, `repair_checklist`
- `backend/src/Controller/WorkshopController.php` — Triage, Abschluss, Stückliste
- `backend/src/Controller/DepartmentSettingController.php` — Settings-API
- `backend/src/Service/Supplier/SupplierRepairTicketService.php` — Abschluss-Lücke (Paket 16)

**Frontend**
- `frontend/src/views/WorkshopView.vue` — Haupt-Werkstatt
- `frontend/src/views/settings/MyDepartmentSettingsView.vue` — oder `MyDepartmentWorkshopSettingsView.vue`
- `frontend/src/components/DamageReportWizard.vue` — Zelt-Diagramm (Paket 13)
- `frontend/src/views/TasksShellView.vue` — Tab Inventur (Paket 20)
- `frontend/src/views/supplier/SupplierRepairsView.vue` — erweitern (Paket 15)

**Doku**
- [materialwart-workflow2026.md](./materialwart-workflow2026.md) — Spezifikation
- [README.md](./README.md) — Ist-Zustand

---

## W1 — Fundament

### Paket 1 — Workshop `department_setting` (Backend) · S

**Ziel:** Skalare MW-Einstellungen in DB und API.

**Keys (neu):**
- `workshop.hourly_rate_chf` (Default `45.00`)
- `workshop.order_reminder_days` (Default `7`)
- `workshop.order_reminder_mode` (`days` \| `document_date`, Default `days`)
- `workshop.spare_parts_category_id` (leer)

**Schritte:**
- [ ] `DepartmentSetting::getWorkshopDefaults()` ergänzen
- [ ] `DepartmentSettingController` — Gruppe `workshop` in GET/PATCH
- [ ] Validierung: `spare_parts_category_id` existiert und gehört zum Department
- [ ] PHPUnit: Defaults + PATCH roundtrip

**DoD:** API liefert/setzt alle vier Keys; Migration nicht nötig (Key/Value-Tabelle).

---

### Paket 2 — Workshop Settings-UI (MW) · S

**Ziel:** MW kann Einstellungen pflegen.

**Schritte:**
- [ ] Abschnitt „Werkstatt" in Department-Settings (Stundensatz, Erinnerung, Modus)
- [ ] Kategorie-Picker „Ersatzteile" (Dropdown aus `category` des Departments)
- [ ] `frontend/src/api/departmentSettings.ts` — Typen + Mapper für `workshop.*`
- [ ] i18n `de.json` / `en.json`
- [ ] Hinweistext: Ersatzteile-Kategorie in Kategorien anlegen, falls leer

**DoD:** MW speichert Settings; Werkstatt-API kann `spare_parts_category_id` lesen.

**Hängt ab von:** Paket 1

---

### Paket 3 — `strategy` + `phase` am Ticket · M

**Ziel:** Neues Workflow-Modell parallel zum alten `status`.

**DB:**
- [ ] Migration: `workshop_ticket.strategy` VARCHAR(30) DEFAULT `triage`
- [ ] Migration: `workshop_ticket.phase` VARCHAR(30) NULL
- [ ] Migration: `workshop_ticket.repair_checklist` JSON NULL

**Backend:**
- [ ] Konstanten in `WorkshopTicket` (`STRATEGY_*`, `PHASE_*`)
- [ ] Serializer: `strategy`, `phase`, `repair_checklist` in API
- [ ] Neuer Endpoint `POST /api/workshop/{id}/triage` — setzt `strategy`, initial `phase`
- [ ] Bestehende Tickets: Migrationsskript `open` → `strategy=triage`, `phase=null`
- [ ] `allowed_transitions` vorerst aus altem `status` (Kompatibilität)

**Frontend:**
- [ ] Typen in `frontend/src/api/workshop.ts`

**DoD:** Tickets haben strategy/phase in API; alte UI funktioniert unverändert.

---

## W2 — Templates

### Paket 4 — `repair_template` (Plattform-Stamm) · M

**Ziel:** Zentrale Zeltblatt-Struktur ohne Department-Preise.

**DB:**
- [ ] Tabelle `repair_template`: `id`, `template_key`, `name`, `material_class` (`tent`), `structure_json`, `diagram_json`, `is_active`, Timestamps

**`structure_json` (Skizze):**
```json
{
  "sections": [
    { "key": "aussenzelt", "label": "Aussenzelt", "items": [
      { "key": "firstring", "label": "Firstring / Öse", "diagram_marker": "ridge_green" }
    ]}
  ],
  "whole_unit_option": true
}
```

**Schritte:**
- [ ] Entity + Repository
- [ ] Admin-API (ROLE_SUPERADMIN): CRUD — oder Seed-only in Paket 22
- [ ] `MaterialItem.repair_template_key` VARCHAR(50) NULL + Migration

**DoD:** Mindestens ein Template per Seed oder Admin anlegbar; Material verknüpfbar.

---

### Paket 5 — `department_repair_template` + API · M

**Ziel:** Department kopiert Plattform-Template und setzt Preise.

**DB:**
- [ ] Tabelle `department_repair_template`: `department_id`, `template_key`, `prices_json`, `flat_rate_chf`, `is_active`, UNIQUE `(department_id, template_key)`

**API:**
- [ ] `GET /api/departments/{id}/repair-templates` — Liste (merged: Struktur + Preise)
- [ ] `POST …/repair-templates/import` — Body `{ template_key }` — kopiert von Plattform
- [ ] `PATCH …/repair-templates/{key}` — Preise, Pauschale, Position aktiv/inaktiv

**DoD:** MW kann Template importieren und Preise speichern; API liefert merged Blatt für Editor.

**Hängt ab von:** Paket 4

---

### Paket 6 — Settings: Vorlagen importieren & Preise · M

**Ziel:** UI für Paket 5.

**Schritte:**
- [ ] Settings → Werkstatt → „Reparatur-Vorlagen"
- [ ] Liste importierter Templates; Button „Von Plattform importieren" (Spatz, Phönix, …)
- [ ] Editor: Preis pro Position, Pauschale, Position ein/aus
- [ ] Vorschau mit `RepairSheetEditor` (readonly) — optional, sonst Paket 7

**DoD:** MW importiert Spatz-Template und trägt CHF-Preise ein.

**Hängt ab von:** Paket 2, 5

---

### Paket 22 — Plattform-Seed: Spatz, Phönix, Hajk, Wico · M

**Ziel:** Erste produktive Zeltblatt-Stämme (kann parallel zu W3 starten, wenn Paket 4 fertig).

**Schritte:**
- [ ] Seed/Migration: `repair_template` für `spatz`, `phoenix`, `hajk`, `wico`
- [ ] `structure_json` aus OMC-Zeltblatt (Screenshots) — Aussenzelt, Innenzelt, Vordach, Apsis, Sonderposten
- [ ] `diagram_json`: Marker-Koordinaten (grün/blau/orange …)
- [ ] Doku: welche Materialien `repair_template_key` erhalten

**DoD:** Department kann alle vier Typen importieren.

**Hängt ab von:** Paket 4 · **Kann in W2 oder W9** nachgezogen werden, wenn Struktur aus OMC noch aufwendig ist.

---

## W3 — Kern-UI (MVP)

### Paket 7 — `RepairSheetEditor` · L

**Ziel:** Eine wiederverwendbare Zeltblatt-Komponente.

**Pfad:** `frontend/src/components/workshop/RepairSheetEditor.vue`

**Props:**
- `modelValue` — ausgefülltes `repair_checklist`
- `template` — merged Struktur + Preise (Department oder Supplier)
- `mode` — `edit` \| `readonly` \| `supplier`
- `priceSource` — `department` \| `supplier` (nur Label/Debug)

**Features:**
- [ ] Accordion-Sektionen (Aussenzelt, Innenzelt, …)
- [ ] Pro Position: `-` Menge `+`, CHF (auto = Menge × Einzelpreis)
- [ ] Diagramm mit Markern (SVG/Canvas); Klick setzt aktive Sektion
- [ ] Radio: ganzes Zelt / nur defekte Teile
- [ ] Bemerkungen-Feld
- [ ] Summenzeile + Pauschale

**DoD:** Komponente isoliert in Storybook/Workshop-Dev-View testbar; speichert `repair_checklist` JSON.

**Hängt ab von:** Paket 5

---

### Paket 8 — `WorkshopTriageDialog` · M

**Ziel:** Entscheidung am Ticket-Anfang.

**Schritte:**
- [ ] Kontext-Karte: Material, Herkunft, Fotos, Aktivität, Priorität, „externe Vermietung"
- [ ] Buttons je Ticket-Typ (siehe Spezifikation §4)
- [ ] Zelt: zeigt `RepairSheetEditor` (readonly, aus Meldung) **oberhalb** der Buttons
- [ ] API: `POST /api/workshop/{id}/triage` mit `{ strategy }`
- [ ] Extern: nach Klick → Supplier-Pflicht-Subdialog (bestehende Repair-Companies-Liste)
- [ ] Abschreiben: direkt Writeoff-Subflow (bestehende Logik, umgebaut)

**DoD:** Neues Ticket öffnet Triage zuerst; Entscheidung persistiert in `strategy`.

**Hängt ab von:** Paket 3

---

### Paket 9 — WorkshopView: Triage + Zeltblatt · M

**Ziel:** MW-Alltag in der Werkstatt-Ansicht.

**Schritte:**
- [ ] Ticket-Detail: wenn `strategy=triage` → Triage-Dialog automatisch
- [ ] Nach Intern: `RepairSheetEditor` im Detail (edit), Department-Preise
- [ ] Nach Extern: Editor mit Supplier-Preisen (nach Lieferantenwahl, Paket 15)
- [ ] `repair_checklist` laden/speichern (`PATCH` Ticket)
- [ ] Kanban-Karten: Badge mit `strategy`/`phase` (MW-Labels)
- [ ] Abschreiben: Editor ausgeblendet

**DoD:** MW durchläuft Triage → Intern → Zeltblatt bearbeiten → speichern.

**Hängt ab von:** Paket 7, 8

**→ MVP Ende W3:** Triage + Zeltblatt intern für Zelte mit importiertem Template.

---

## W4 — Interne Reparatur (Nicht-Zelt + Beschaffung)

### Paket 10 — `RepairPartsList` · M

**Ziel:** Stückliste für Nicht-Zelt-Material.

**Schritte:**
- [ ] Komponente `RepairPartsList.vue`
- [ ] Materialsuche nur `category_id = workshop.spare_parts_category_id`
- [ ] Zeile: Material, Menge, `source` (stock/purchase), `status`, Einkaufspreis (vom Materialstamm)
- [ ] API: `parts_used` Schema dokumentieren + validieren in `WorkshopController::update`
- [ ] Bestandsanzeige pro Zeile (grün/rot)

**DoD:** MW pflegt Stückliste am Ticket; Daten in `parts_used`.

**Hängt ab von:** Paket 2, 3

---

### Paket 11 — Lagerentnahme bei Abschluss · M

**Ziel:** `source=stock` Zeilen verbuchen; Hauptmaterial vs. Ersatzteile trennen.

**Schritte:**
- [ ] `WorkshopTicketCompletionService` extrahieren (aus `WorkshopController::transition`)
- [ ] Bei `resolution=repaired`: `parts_used` mit `status=consumed` → Batch `-qty` oder Verbrauchsbuchung
- [ ] Kosten: Summe × Einkaufspreis
- [ ] UI-Warnung vor Abschluss (Hauptmaterial wird OK, Ersatzteile abgebucht)
- [ ] Bei `writeoff`: Stückliste ignorieren / sperren

**DoD:** Interne Reparatur mit Stückliste reduziert Lager korrekt; kein Writeoff am Hauptmaterial.

**Hängt ab von:** Paket 10

---

### Paket 12 — Einkauf-Flow · L

**Ziel:** Neu kaufen → Lager → Entnahme → Reste → Erinnerung.

**Schritte:**
- [ ] `PurchaseLineDialog`: Ort/Lieferant, Preis, Quittung (Media-Upload)
- [ ] Bei Quittung: optional OCR/Datum für `order_reminder_mode=document_date` (manuell reicht v1)
- [ ] Einbuchen: neuer `MaterialBatch` +qty am Ersatzteil-Material
- [ ] Zeile `status: ordered` → `received` → `consumed`
- [ ] Abschluss-Dialog: „Übrig geblieben?" → Rest zurück (+Batch)
- [ ] `phase=ordered` am Ticket wenn offene Bestellungen
- [ ] Inbox/Cron: Erinnerung nach `workshop.order_reminder_days` oder Dokument-Datum

**DoD:** Einkaufszeile durchgängig; Erinnerung erscheint in Inbox.

**Hängt ab von:** Paket 10, 1 · [docs/media/README.md](../media/README.md) für Quittungs-Upload

---

## W5 — Frühe Erfassung

### Paket 13 — Schadensmeldung: Zelt-Diagramm · M

**Ziel:** `repair_checklist` schon bei Meldung teilfüllen.

**Schritte:**
- [ ] `DamageReportWizard`: wenn Material `repair_template_key` gesetzt → `RepairSheetEditor` (nur Diagramm + Sektionen, keine Preise)
- [ ] `createActivityIssue` / Auto-Ticket: `repair_checklist` vom Report auf Ticket kopieren
- [ ] Manueller Wizard ohne Aktivität: gleiches Diagramm für Zelt-Material

**DoD:** Ticket aus Schadensmeldung hat vorausgefülltes Zeltblatt.

**Hängt ab von:** Paket 7

---

## W6 — Extern

### Paket 14 — `supplier_repair_template` + Supplier-UI · L

**Ziel:** Lieferant pflegt eigenes Zeltblatt mit Preisen.

**DB:**
- [ ] `supplier_repair_template` + `services_json` (Reinigung/Reparatur-Dienste)

**API + Portal:**
- [ ] CRUD unter `/api/supplier/{companyId}/repair-templates`
- [ ] UI in Supplier-Portal (analog Paket 6, mit `RepairSheetEditor`)
- [ ] Import von Plattform-Struktur + eigene Preise

**DoD:** Supplier hat Spatz-Template mit Preisen; MW kann es nach Lieferantenwahl laden.

**Hängt ab von:** Paket 7

---

### Paket 15 — „An Lieferant senden" · M

**Ziel:** MW schickt ausgefülltes Blatt an Supplier-Portal.

**Schritte:**
- [ ] Button im Ticket-Detail (nur `strategy=external_repair`, Lieferant gesetzt)
- [ ] Ticket-Status/phase → `awaiting_quote` oder `in_progress` beim Supplier
- [ ] Supplier sieht in `SupplierRepairsView` dasselbe `RepairSheetEditor` (readonly + Offerte-Felder)
- [ ] `estimated_cost` aus Blatt-Summe oder manuell
- [ ] Optional v1: kein PDF; Portal reicht

**DoD:** MW sendet; Supplier sieht Blatt + Fotos + kann Offerte bestätigen.

**Hängt ab von:** Paket 14, 9

---

### Paket 16 — `WorkshopTicketCompletionService` (Supplier) · M

**Ziel:** Supplier-Abschluss = gleiche Seiteneffekte wie intern.

**Schritte:**
- [ ] Logik aus `WorkshopController::transition` (completed) in Service
- [ ] `SupplierRepairTicketService::transitionTicket` ruft Service auf
- [ ] Material-Zustand, Writeoff-Batch, IssueReport resolved, `enqueueFromWorkshopTicket`
- [ ] Tests: Supplier complete → Material `ok`, Buchhaltung pending

**DoD:** Kein Unterschied intern/extern bei Abschluss-Seiteneffekten.

**Hängt ab von:** Paket 15

---

## W7 — Kosten & Reinigung

### Paket 17 — `WorkshopCostSummary` · M

**Ziel:** Drei Kostenbausteine am Abschluss.

**Schritte:**
- [ ] Komponente: Checkboxen Arbeitszeit / Pauschale / Material
- [ ] Arbeitszeit: Stunden × `workshop.hourly_rate_chf`
- [ ] Pauschale: aus Template oder manuell
- [ ] Material: Summe `repair_checklist` + `parts_used`
- [ ] `actual_cost` berechnet und speicherbar; Aufschlüsselung in History

**DoD:** MW sieht und speichert Kostenbreakdown am Abschluss.

**Hängt ab von:** Paket 1, 10, 7

---

### Paket 18 — Reinigung extern · M

**Ziel:** Triage „Extern reinigen" mit Supplier-Dienstliste.

**Schritte:**
- [ ] `strategy=external_cleaning` in Triage
- [ ] Dienst aus `supplier_repair_template.services_json` wählen
- [ ] Flow analog externe Reparatur (ohne volles Zeltblatt, wenn kein Zelt)
- [ ] Zelt-Reinigung: Position „waschen & imprägnieren" aus Zeltblatt + Supplier-Dienst

**DoD:** Reinigung extern durchgängig buchbar.

**Hängt ab von:** Paket 14, 8

---

## W8 — Inventur

### Paket 19 — `inventory_task` + API · M

**Ziel:** Reguläre Inventur neben Inspektions-Tickets.

**DB:**
- [ ] `inventory_task`: `department_id`, `title`, `status`, `lines_json`, `workshop_ticket_id` NULL, Timestamps

**API:**
- [ ] CRUD `/api/inventory-tasks`
- [ ] Inspektions-Ticket abschliessen kann `inventory_task` verknüpfen

**DoD:** MW kann Inventur-Aufgabe anlegen; API testbar.

**Hängt ab von:** Paket 3

---

### Paket 20 — Tab Inventur unter `/tasks/` · L

**Ziel:** Zentraler Ort für Mini-Inventur.

**Schritte:**
- [ ] `TasksShellView`: Tab „Inventur" (`TasksInventoryView`)
- [ ] Router: `/{departmentId}/tasks/inventory`
- [ ] Liste: offene Inspektions-Tickets (`strategy=inspection`) + `inventory_task`
- [ ] UI: Zähl-Workflow (Ist vs. Soll) — Wiederverwendung aus Activity `shellForwardInventory` wo möglich
- [ ] Abschluss aktualisiert Ticket / Task

**DoD:** MW findet offene Inventur unter `/tasks/` → Inventur.

**Hängt ab von:** Paket 19, 9

---

## W9 — Abschluss

### Paket 21 — Status-Migration + alte UI entfernen · M

**Ziel:** Nur noch `strategy`/`phase` in der MW-UI.

**Schritte:**
- [ ] Datenmigration: alle offenen Tickets `status` → `strategy`/`phase`
- [ ] Kanban-Spalten nach `phase` umbauen (MW-Labels)
- [ ] Entfernen: „Arbeit starten", „Wartet auf Teile" Buttons
- [ ] API: `status` deprecated oder computed aus phase
- [ ] Dashboard-Stats auf neues Modell
- [ ] Department-Display-Screens: `workshop_statuses` Mapping
- [ ] Supplier-Portal Status-Labels

**DoD:** Keine alten Status-Labels mehr sichtbar; Regression Werkstatt + Aktivitäts-Blocker.

**Hängt ab von:** Paket 9–18

---

## Abhängigkeitsgraph (vereinfacht)

```
1 → 2 → 6 → 9
4 → 5 → 7 → 9, 13, 14
3 → 8 → 9
2, 3 → 10 → 11, 12
14 → 15 → 16
1, 7, 10 → 17
14, 8 → 18
3 → 19 → 20
9–18 → 21
4 → 22
```

---

## MVP-Definition (lieferbar nach W3)

Minimum für ersten Produktivtest:

- [x] Spezifikation (`materialwart-workflow2026.md`)
- [ ] Paket 1–3, 4–7, 8–9, 22 (mindestens ein Zelt-Template, z. B. Spatz)

**MW kann dann:** Settings setzen → Template importieren → Ticket triagieren → Intern → Zeltblatt ausfüllen → speichern.

**Noch nicht im MVP:** Einkauf-Flow, Supplier-Senden, Inventur-Tab, Kostenbreakdown, Schadens-Diagramm.

---

## Nächster Schritt

**Paket 1 starten:** `DepartmentSetting::getWorkshopDefaults()` + API-Gruppe `workshop`.

Bei Beginn eines Pakets: Status in dieser Datei auf `[~]` setzen, bei Abschluss `[x]`.
