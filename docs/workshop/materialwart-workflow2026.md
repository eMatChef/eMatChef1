# Materialwart-Workflow 2026

Zielbild für den **verbesserten Werkstatt-Workflow** aus Sicht des Materialwarts (MW). Ersetzt schrittweise den generischen Status-Flow (`open` → `in_progress` → `waiting_parts`).

**Stand:** Juni 2026 · **Status:** Spezifikation (noch nicht implementiert)

**Verwandt:** [README.md](./README.md) (Ist-Zustand) · **[plan.md](./plan.md)** (Umsetzungsplan) · [docs/accounting.md](../accounting.md) · [docs/supplier/supplier-portal.md](../supplier/supplier-portal.md)

---

## 1. Ziele

| Ziel | Beschreibung |
|------|--------------|
| **Entscheidung zuerst** | MW triagiert jedes neue Ticket, bevor Detailarbeit beginnt |
| **Eine Zeltblatt-UI** | Gleiche Oberfläche für MW — unabhängig davon, ob Template von Plattform, Department oder Supplier stammt |
| **Strukturierte Reparatur** | Zelt: Kategorien + Diagramm; sonst: Stückliste aus Ersatzteile-Kategorie |
| **Korrekter Bestand** | Hauptmaterial vs. Ersatzteile strikt trennen; Einkauf immer über Lager |
| **Kosten transparent** | Arbeitszeit, Pauschale und Materialkosten (und/oder) |
| **Extern sauber** | Supplier wählen, Zeltblatt mit Preisen senden, Offerte/Ist-Kosten |

---

## 2. Kernprinzip: Eine UI für alle

Der **Materialwart sieht immer dieselbe Zeltblatt-Komponente** (`RepairSheetEditor`):

- Kategorien (Aussenzelt, Innenzelt, Vordach, Apsis, Sonderposten)
- Positionen mit Mengen und CHF
- Diagramm mit Markierungen
- Bemerkungen

**Was sich unterscheidet**, ist nur die **Preisquelle** im Hintergrund:

| Template-Quelle | Preise | Wann geladen |
|-----------------|--------|--------------|
| Plattform-Typ (Spatz, Phönix, Hajk, Wico …) | Department hat importiert + eigene Preise gesetzt | Interne Reparatur |
| Department-Override | MW in Settings gepflegt | Interne Reparatur |
| Supplier-Template | Supplier hat Preise + Pauschale gesetzt | Externe Reparatur (nach Lieferantenwahl) |

Der MW **entscheidet nach dem Öffnen des Tickets**:

```
┌─────────────────────────────────────────────────────────┐
│  Zeltblatt (vorausgefüllt aus Schadensmeldung)          │
│  … Diagramm, Positionen, Fotos …                        │
├─────────────────────────────────────────────────────────┤
│  Wie reparieren?                                        │
│  [ 🔧 Intern ]    [ 🏭 Extern ]    [ 🗑️ Abschreiben ]   │
└─────────────────────────────────────────────────────────┘
```

- **Intern** → Department-/Plattform-Preise im Blatt
- **Extern** → Lieferant **muss** gewählt werden → Blatt wechselt auf Supplier-Preise → „An Lieferant senden"
- **Abschreiben** → Zeltblatt/Stückliste gesperrt, nur Menge + Kosten

---

## 3. Ticket-Eingänge

Unverändert zum [Ist-Zustand](./README.md#1-ticket-erstellung-5-wege):

| Quelle | Auto-Ticket |
|--------|-------------|
| Schadensmeldung (damage/repair/loss) | Ja |
| Rückgabe defekt/beschädigt | Ja |
| Kistencheck Überschuss / not_taken | Inspektion |
| Manuell / Wizard ohne Aktivität | Ja |

**Neu bei Zelten:** Schadensmeldung zeigt bereits das **Diagramm** (wo defekt?) — Daten fliessen ins Ticket-Zeltblatt.

---

## 4. Triage-Matrix (Entscheidungen pro Typ)

| Ticket-Herkunft / Typ | Entscheidungen |
|----------------------|----------------|
| Schaden / Reparatur | Intern · Extern · Abschreiben |
| Verlust | Abschreiben (prominent) · ggf. Intern |
| Inspektion (Schaden) | In Ordnung · Reparatur nötig · Abschreiben |
| Inspektion (Überschuss/Kistencheck) | Mini-Inventur → Einlagern / Klären |
| Reinigung | Intern reinigen · **Extern reinigen** · Abschreiben |

Nach „Reparatur nötig" bei Inspektion → normaler Reparatur-Pfad (inkl. Zeltblatt bei Zelten).

---

## 5. Zeltblatt & Reparatur-Templates

### 5.1 Drei Ebenen

```
Plattform (Stamm)          Department (Import + Preise)     Supplier (externe Preise)
─────────────────         ────────────────────────────     ─────────────────────────
repair_template           department_repair_template       supplier_repair_template
  key: spatz                department_id + template_key     supplier_company_id
  key: phoenix              + Preise, Pauschale              + Preise, Pauschale
  key: hajk …               + aktiv/inaktiv pro Position     + Dienstleistungen
```

- **Plattform:** Struktur (Kategorien, Positionen, Diagramm-Koordinaten), Preise optional leer
- **Department:** Import aus Plattform, MW trägt CHF-Preise und Pauschale ein (Settings)
- **Supplier:** Gleiche Struktur, eigene Preise; kann an MW zurückgesendet werden

### 5.2 Verknüpfung am Material

```
MaterialItem (Zelt Spatz 8er)
  repair_template_key: "spatz"   // oder "spatz_8er" bei Varianten
```

Bei Ticket-Erstellung: System ermittelt Template über `repair_template_key` + Department-Override.

### 5.3 Pflege durch MW (Settings)

```
Settings → Mein Department → Werkstatt
  ├── Reparatur-Vorlagen
  │     Import «Spatz» von Plattform
  │     Preise pro Position, Pauschale «ganzes Zelt»
  └── (siehe Abschnitt 8)
```

Plattform-Stamm: Superadmin / zentrale Bibliothek — Vereine kopieren, nicht neu definieren.

### 5.4 Schadensmeldung (früh)

Bei Meldung für Zelt-Material:

1. Diagramm: Klick auf defekte Stellen
2. Kategorie vorausgewählt (Aussenzelt / Innenzelt …)
3. Optional Fotos

→ Workshop-Ticket enthält `repair_checklist` bereits teilweise ausgefüllt.

---

## 6. Pfad: Interne Reparatur

### 6.1 Ablauf

1. Triage → **Intern**
2. Zeltblatt vervollständigen (oder Stückliste bei Nicht-Zelt)
3. **Stückliste** (Nicht-Zelt): Positionen nur aus **Ersatzteile-Kategorie** (Settings)
4. Pro Position: **Aus Lager** oder **Neu kaufen**
5. Kostenübersicht → Phase `planning` / `ordered` / `ready`
6. Reparatur → Abschluss

### 6.2 Stückliste (Nicht-Zelt)

| Feld | Beschreibung |
|------|--------------|
| `material_item_id` | Aus Kategorie „Ersatzteile" |
| `quantity` | Menge |
| `source` | `stock` \| `purchase` |
| `status` | `planned` → `ordered` → `received` → `consumed` |
| `unit_cost` | Einkaufspreis |

Gespeichert in `workshop_ticket.parts_used` (JSON, Schema erweitern) oder eigenes Feld `repair_parts`.

### 6.3 Beschaffung

| Weg | Ablauf |
|-----|--------|
| **Aus Lager** | Bestand prüfen; Kosten = **Einkaufspreis**; bei Abschluss `consumed` |
| **Neu kaufen** | Lieferant/Ort, Preis, **Quittung hochladen** → **ins Lager** (+Batch) → entnehmen → bei Abschluss: **„Übrig geblieben?"** → Rest zurück ins Lager |

**Erinnerung** (Inbox): „Bestellt am … — angekommen?" — siehe Settings.

### 6.4 Hauptmaterial vs. Ersatzteile (Abschreibungs-Falle)

| | Hauptmaterial (Ticket) | Ersatzteil (Stückliste) |
|--|------------------------|-------------------------|
| Reparatur OK | `condition → ok` | Bestand `-N` |
| Abschreiben | Writeoff-Batch `-qty` | **Keine** Ersatzteile verbuchen |

UI-Warnung bei Intern + Stückliste: *Ersatzteile werden vom Lager abgebucht; das Hauptmaterial wird repariert, nicht abgeschrieben.*

Bei Triage **Abschreiben**: Zeltblatt/Stückliste deaktiviert.

### 6.5 Kosten am Abschluss (3 Bausteine, und/oder)

```
actual_cost = Arbeitszeit (h × Satz)  +  Pauschale  +  Materialkosten (Summe Stückliste)
```

MW aktiviert pro Ticket, was zutrifft (Checkboxen).

---

## 7. Pfad: Externe Reparatur / Reinigung

### 7.1 Reparatur extern

1. Triage → **Extern**
2. **Lieferant wählen** (Pflicht) — `assigned_to_supplier_company_id`
3. Zeltblatt lädt **Supplier-Template** (gleiche UI, Supplier-Preise)
4. MW prüft/ergänzt vorausgefülltes Blatt
5. **„An Lieferant senden"** — Ticket + Blatt + Fotos im Supplier-Portal
6. Supplier: Offerte bestätigen / anpassen → `estimated_cost`
7. Abschluss: `actual_cost` → Material-Zustand, Buchhaltung, IssueReport resolved

Reinigung extern: gleiches Modell über **Supplier-Dienstleistungsliste** (`type: cleaning`).

### 7.2 Supplier-Dienste

Supplier pflegt im Portal:

- Reparatur-Vorlagen (Zeltblatt + Preise)
- Dienstleistungen (waschen, imprägnieren, …) mit Einzelpreis
- Pauschale pro Vorlage

Capability: `repairs` (bestehend), ggf. `cleaning` erweitern.

---

## 8. Department-Settings — Speicherort

### 8.1 Einfache Werte → `department_setting` (bestehend)

Tabelle `department_setting` (`DepartmentSetting`), Key/Value pro Department — gleiches Muster wie `activity.*`, `rental.amortization_*`.

| `setting_key` | `setting_value` | Default | UI |
|---------------|-----------------|---------|-----|
| `workshop.hourly_rate_chf` | Dezimal string | `45.00` | Settings → Werkstatt |
| `workshop.order_reminder_days` | Integer string | `7` | Settings → Werkstatt |
| `workshop.order_reminder_mode` | `days` \| `document_date` | `days` | Settings → Werkstatt |
| `workshop.spare_parts_category_id` | `category.id` (12 Zeichen) | leer | Settings → Werkstatt |

API: bestehend `GET/PATCH /api/departments/{id}/settings` — Gruppe `workshop` in `DepartmentSetting::getWorkshopDefaults()` ergänzen.

Frontend: neuer Abschnitt in `MyDepartmentSettingsView` oder eigene `MyDepartmentWorkshopSettingsView`.

### 8.2 Ersatzteile-Kategorie

- MW legt in **Kategorien** eine Hauptkategorie „Ersatzteile" an (oder nutzt bestehende)
- ID wird in `workshop.spare_parts_category_id` gespeichert
- Stücklisten-Suche filtert `material.category_id = spare_parts_category_id`

Kein separates Flag am Material nötig — Zugehörigkeit über Kategorie.

### 8.3 Reparatur-Vorlagen → eigene Tabellen (nicht `department_setting`)

Zeltblatt-Struktur ist zu gross für Key/Value. Geplante Entities:

| Tabelle | Zweck |
|---------|--------|
| `repair_template` | Plattform-Stamm (`key`, `name`, `structure_json`, `diagram_json`) |
| `department_repair_template` | `department_id`, `template_key`, `prices_json`, `flat_rate_chf`, `is_active` |
| `supplier_repair_template` | `supplier_company_id`, `template_key`, `prices_json`, `flat_rate_chf`, `services_json` |

Ticket-Daten (ausgefülltes Blatt): `workshop_ticket.repair_checklist` (JSON, neu) — Snapshot pro Ticket, unabhängig vom Template.

### 8.4 Übersicht Speicherorte

```
department_setting          → Skalare MW-Einstellungen (Stundensatz, Erinnerung, Ersatzteile-Category-ID)
category                    → „Ersatzteile" als normale Materialkategorie
repair_template             → Plattform-Stamm (Struktur)
department_repair_template  → Department-Preise + Pauschale
supplier_repair_template    → Supplier-Preise + Dienste
workshop_ticket.repair_checklist  → Ausgefülltes Zeltblatt am Ticket
workshop_ticket.parts_used        → Stückliste Nicht-Zelt
workshop_ticket.strategy / .phase → Neuer Workflow-Status (siehe §9)
```

---

## 9. Status-Modell 2026

Ersetzt MW-sichtbare Labels „Reparatur starten" / „Wartet auf Teile".

### 9.1 `strategy` (Entscheidung)

| Wert | Bedeutung |
|------|-----------|
| `triage` | Noch nicht entschieden |
| `internal_repair` | Intern |
| `external_repair` | Extern (Lieferant gesetzt) |
| `external_cleaning` | Externe Reinigung |
| `writeoff` | Abschreiben |
| `inspection` | Inspektion / Mini-Inventur |

### 9.2 `phase` (Fortschritt)

| Wert | MW-Anzeige |
|------|------------|
| `planning` | Planung (Blatt / Stückliste) |
| `ordered` | Bestellt — wartet auf Ankunft |
| `ready` | Bereit zur Ausführung |
| `in_progress` | In Arbeit |
| `awaiting_quote` | Offerte ausstehend (extern) |
| `completed` | Abgeschlossen |
| `cancelled` | Storniert |

Technisch: neue Spalten am `workshop_ticket` oder Mapping von altem `status` während Migration.

### 9.3 Mapping Alt → Neu (Übergang)

| Alt | Neu (grob) |
|-----|----------------|
| `open` | `strategy=triage` |
| `in_progress` | `phase=in_progress` |
| `waiting_parts` | `phase=ordered` oder `awaiting_quote` |
| `completed` | `phase=completed` |

---

## 10. Inspektion & Inventur (Zukunft)

### 10.1 Inspektions-Ticket = Mini-Inventur

Überschuss/Kistencheck → Ticket `type=inspection`, `strategy=inspection` → Abschluss = dokumentierte Klärung (Einlagern, Umbuchen, Verlust).

### 10.2 Tab „Inventur" unter Aufgaben

Route bestehend: `/{departmentId}/tasks/` (`TasksShellView` — Tabs: Allgemein, Druck).

**Geplant:** weiterer Tab **Inventur** (`TasksInventoryView`):

```
/tasks/…/inventory   (Zukunft)
├── Aus Inspektions-Tickets (offen)
├── Reguläre Inventur-Aufgaben (MW erstellt, z. B. Jahresinventur)
└── Erledigt
```

Gemeinsame Zähl-UI (Ist vs. Soll); Herkunft unterschiedlich. Reguläre Aufgaben: neues Objekt `inventory_task` (später spezifizieren).

---

## 11. Buchhaltung

| Ereignis | Follow-up / Buchung |
|----------|---------------------|
| Ticket abgeschlossen, `actual_cost` > 0, Aktivität | `activity_workshop` (bestehend) |
| Einkauf mit Quittung | `purchase` + Verknüpfung Ticket |
| Teile aus Lager | Kosten = Einkaufspreis in Materialkosten-Summe |
| Extern | Ist-Preis vom Supplier |

Kostenbasis Ersatzteile: **Einkaufspreis** (nicht Verkaufspreis).

---

## 12. UI-Komponenten (geplant)

| Komponente | Verwendung |
|------------|------------|
| `RepairSheetEditor` | Zeltblatt — MW, Schadensmeldung, Supplier-Portal |
| `WorkshopTriageDialog` | 3–4 Entscheidungsbuttons + Kontext-Karte |
| `RepairPartsList` | Stückliste Nicht-Zelt (Ersatzteile-Kategorie) |
| `WorkshopCostSummary` | Zeit + Pauschale + Material |
| `PurchaseLineDialog` | Kaufen + Quittung + Erinnerung |

---

## 13. Umsetzungsplan

**Ausführlich:** [plan.md](./plan.md) — 22 Pakete in 9 Wellen, Checklisten, Abhängigkeiten, MVP nach W3.

| Welle | Fokus | Pakete |
|-------|--------|--------|
| W1 | Fundament (Settings, strategy/phase) | 1–3 |
| W2 | Reparatur-Templates | 4–6, 22 |
| W3 | **MVP:** Triage + Zeltblatt | 7–9 |
| W4 | Stückliste, Lager, Einkauf | 10–12 |
| W5 | Schadensmeldung Diagramm | 13 |
| W6 | Supplier extern | 14–16 |
| W7 | Kosten, Reinigung extern | 17–18 |
| W8 | Inventur-Tab `/tasks/` | 19–20 |
| W9 | Migration, alte UI weg | 21 |

**Nächster Schritt:** Paket 1 — `workshop.*` in `department_setting`.

---

## 14. Offene Punkte (klein)

| # | Thema | Vorschlag |
|---|--------|-----------|
| 1 | `repair_checklist` vs. `parts_used` | Zelt → `repair_checklist`; sonst `parts_used` |
| 2 | Capability `cleaning` | Eigen oder unter `repairs` |
| 3 | PDF-Export Zeltblatt für Supplier | Phase 7, optional |
| 4 | Varianten Spatz 6er/8er | `repair_template_key` verfeinern |

---

## 15. Kurzreferenz Entscheidungen (Workshop 2026)

| Thema | Entscheidung |
|-------|-------------|
| Zeltblatt-UI | **Eine UI für MW** — Quelle nur Preise/Template |
| Intern vs. extern | **MW entscheidet**; extern → Lieferant Pflicht |
| Ersatzteile | Fixe **Kategorie** pro Department (`workshop.spare_parts_category_id`) |
| Settings DB | **`department_setting`** für Skalare; **eigene Tabellen** für Templates |
| Zeltblatt-Templates | Plattform → Department-Import; Supplier für extern |
| Diagramm | Bereits bei **Schadensmeldung** |
| Pauschale intern | Department-Template |
| Pauschale extern | Supplier-Template |
| Reinigung extern | Supplier-Dienstleistungsliste |
| Überschuss | Inspektions-Ticket → später Tab **Inventur** unter `/tasks/` |
| Lagerpreis | Einkaufspreis |
| Einkauf | Immer Lager; Reste abfragen |
| Erinnerung | Settings, Default 7 Tage oder PDF-Datum |
| Kosten | Zeit ∧/∨ Pauschale ∧/∨ Material |
| Arbeitssatz | `workshop.hourly_rate_chf` in Department-Settings |
