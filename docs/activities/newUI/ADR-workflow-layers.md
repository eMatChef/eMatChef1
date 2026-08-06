# ADR: Activity-Status, Material-Pipeline & Journey-Stepper

**Status:** Entschieden und umgesetzt (Juni 2026)  
**Kontext:** Getrennte APIs (`PATCH /status` vs. `PATCH /pack-journey-step`) und unklare Zuordnung Stepper ↔ Status ↔ Material führten zu widersprüchlicher UX.

---

## Entscheidung

Es gibt **zwei fachliche Ebenen**:

| Ebene | Frage | Speicherort |
|-------|--------|-------------|
| **Activity-Status** | «In welcher Workflow-Phase ist der Anlass?» | `activity.status` (ein Wert pro Aktivität) |
| **Material-Pipeline** | «Wo liegen wie viele Stück?» | `quantity_*` auf `activity_pack_item` / `activity_pack_container_item` |

**Stepper = Activity-Status:** Der Journey-Stepper zeigt dieselbe Workflow-Achse wie `activity.status`. Es gibt **kein** separates persistiertes `pack_journey_step`.

**Material ist unabhängig:** Teilmengen über mehrere `quantity_*`-Spalten gleichzeitig. Ein Activity-Status-Weiter erfordert **nicht**, dass alle Material-Mengen bereits in der nächsten Stufe sind.

---

## Activity-Status (vollständig)

### Freigabe & Storno

`draft` → `submitted` → `approved` → `cancelled` (an mehreren Stellen)

### Pack & Event (Logistics: `camp` / `event`)

```text
packing → packed → transport_out → at_event → transport_back → returned → storing → completed
```

### Pack & Event (Quick: `activity` / `external`)

Transport-Stufen entfallen:

```text
packing → packed → at_event → returned → storing → completed
```

### Neue Status-Keys

| Key | Anzeige (DE) | Stepper-UI |
|-----|--------------|------------|
| `transport_out` | Transport hin | `transport_out` |
| `at_event` | Am Anlass | `issue` *(nur UI-Label)* |
| `transport_back` | Transport zurück | `transport_back` |
| `storing` | Einlagern | `store` |

`packing` mappt auf Stepper `pack`; `packed` ist kurzer Übergang vor Logistics-Transport.

---

## Stepper ↔ Activity-Status

| Stepper (UI) | `activity.status` |
|--------------|-------------------|
| `pack` | `packing` |
| `transport_out` | `transport_out` |
| `issue` | `at_event` |
| `transport_back` | `transport_back` |
| `return` | `returned` |
| `store` | `storing` |

Quick-Profil: Stepper ohne `transport_out` und `transport_back`.

---

## Primary-Buttons (eine Aktion = ein Status-Wechsel)

Panel unten und Header-Button lösen **dieselbe** `PATCH /api/activities/{id}/status`-Transition aus.

| User-Aktion | Logistics | Quick |
|-------------|-----------|-------|
| «Gepackt markieren» | `packing` → `packed` | gleich |
| «Weiter zu Am Anlass» | `transport_out` → `at_event` | `packed` → `at_event` |
| «Transport abgeschlossen» | `at_event` → `transport_back` | — |
| «Weiter zu Retour» / Retour-Handoff | `transport_back` → `returned` | `at_event` → `returned` |
| Einlagern abgeschlossen / Abschluss | `storing` → `completed` | gleich |

«Transport abgeschlossen» ist von **Transport hin** und **Am Anlass** auslösbar (Logistics).

---

## Stepper-Navigation (Ansicht)

| Aktion | Wirkung |
|--------|---------|
| Klick auf Stepper-Knoten | Nur URL/Navigation — **kein** API-Schreiben |
| Vergangener Knoten | **Nicht** pauschal readonly/grau — bearbeitbar, solange auf der zugehörigen **PackStage** noch offene Mengen existieren |
| Zukünftiger Knoten | readonly (noch nicht erreicht) |

---

## Material-Pipeline

### Kein `material_status`-ENUM pro Zeile

Pro Material **eine** `activity_pack_item`-Zeile; **pro Pipeline-Stufe eine Spalte** mit der Menge:

| Material-Stufe | DB-Spalte |
|----------------|-----------|
| ordered | `activity_item.quantity` / `quantity_ordered` |
| packed | `quantity_packed` |
| transport_out | `quantity_transport_to` |
| at_event | `quantity_issued` |
| transport_back | `quantity_transport_back` |
| returned | `quantity_returned` |
| stored | `quantity_stored` |

**Naming:** Stufe `packed` (nicht `packing`); Ende `stored` (nicht `completed` — das ist Activity-Status).

### Teilmengen-Beispiel

6 bestellt → 4 mitgenommen, 2 nicht: `quantity_packed = 2`, `quantity_issued = 4`. Activity kann `at_event` sein.

### Zwei Tabellen (kein «Umzug»)

| Tabelle | Phase |
|---------|--------|
| `activity_item` | Planung (`draft` … `approved`); bleibt als Referenz |
| `activity_pack_item` | ab `packing` (`autoInitPackList`); aggregiert pro Material |

`activity_pack_container_item` spiegelt dieselben `quantity_*` pro Kisten-Inhalt.

---

## Verworfen

| Was | Grund |
|-----|--------|
| `activity.pack_journey_step` | Redundant zu `activity.status` |
| `PATCH …/pack-journey-step` | Ersetzt durch Status-Transitions |
| Getrennte «Weiter»- vs. Header-Aktion | Eine User-Aktion = ein Status |
| Linear `readonly_past` im Stepper | Blockiert Nachbuchung bei Teilmengen |
| Material-`completed` | Verwechslung mit Activity-`completed` |

---

## Datenbank (geplante Migration)

### `activity`

- **Neue Status-Werte:** `transport_out`, `transport_back`, `storing`
- **`STATUS_TRANSITIONS`** und **`TRANSITION_PERMISSIONS`** erweitern (Quick vs. Logistics)
- **Optional:** `transport_out_at`, `transport_back_at`, `storing_at`
- **Entfernen:** Spalte `pack_journey_step` (nach Daten-Migration)

### `activity_pack_item` / `activity_pack_container_item`

- **Keine strukturelle Änderung** — `quantity_*` bleibt

### Daten-Migration `pack_journey_step` → `status` (Beispiel)

| Alt `pack_journey_step` | Alt `status` | Neu `status` |
|-------------------------|--------------|--------------|
| `transport_out` | `packed` | `transport_out` |
| `issue` | `packed` | `at_event` |
| `transport_back` | `at_event` | `transport_back` |
| `return` | `at_event` / `returned` | `returned` |
| `store` | `returned` | `storing` |

---

## Abschluss-Blocker

Bisher `returned` → `completed`. Ziel: **`storing` → `completed`** (Material physisch geklärt: eingelagert oder Verlust/Reparatur gemeldet). **Buchhaltung blockiert nicht** — siehe [accounting.md](../../accounting.md#zwei-abschlüsse-kernmodell).

---

## Implementierungs-Checkliste

- [x] `Activity.php`: Status, Transitions, Permissions, Timestamps
- [x] Migration + Drop `pack_journey_step`
- [x] `ActivityPackJourneyService` entfernt; deprecated `PATCH …/pack-journey-step` → `changeStatus`
- [x] Frontend: Stepper aus `activity.status`; Stepper-Zugriff bei offenen Mengen auf vergangenen Schritten
- [x] i18n + `activity-status.css` für neue Status
- [x] Journey-UI + Kopfzeile auf gemeinsame Status-Transitions (`storing` → `completed`)

---

## Siehe auch

- [status.md](../status.md)
- [material-pipeline.md](../material-pipeline.md)
- [SPEC.md §3](./SPEC.md#3-journey-steps)
- [pack-workflow-rules.md](../pack-workflow-rules.md)
