# Material-Pipeline (Bestellung & Packliste)

Zwei **getrennte** Status-Ebenen pro Aktivität:

1. **Aktivitäts-Status** — Freigabe, Rollen, Benachrichtigungen → [status.md](./status.md)
2. **Material-Pipeline** — physische Position jeder Position/Menge

**Stand:** Juni 2026 · `quantity_stored` implementiert  
**Architektur:** [newUI/ADR-workflow-layers.md](./newUI/ADR-workflow-layers.md) — Activity-Status inkl. `transport_out`, `transport_back`, `storing`; Material über `quantity_*` (kein Enum pro Zeile)

---

## Warum zwei Ebenen?

| Ebene | Frage | Beispiel |
|-------|-------|----------|
| Aktivität | «In welcher Phase ist der Anlass?» | `returned` = Gruppe hat retourniert |
| Material | «Wo ist Stück X?» | 3× retourniert, 1× noch nicht eingelagert |

Eine Aktivität kann `returned` oder `storing` sein, während einzelne Positionen noch nicht `quantity_stored` sind. **Verfügbarkeit für andere Anlässe** wird pro Position freigegeben, sobald die Menge **eingelagert** ist (`quantity_stored`). Der Aktivitäts-Status `completed` betrifft den **Vorgangsabschluss** (Einlagerung vollständig, Meldungen, Werkstatt, Buchhaltung) — **nicht** mit Material-Stufe `stored` verwechseln.

### Material-Stufen (Naming)

| Material-Stufe | DB-Spalte | Anmerkung |
|----------------|-----------|-----------|
| ordered | `activity_item.quantity` / `quantity_ordered` | Referenz aus Bestellung |
| **packed** | `quantity_packed` | «Gepackt» — nicht `packing` nennen |
| transport_out | `quantity_transport_to` | nur Logistics |
| at_event | `quantity_issued` | bei der Gruppe |
| transport_back | `quantity_transport_back` | nur Logistics |
| returned | `quantity_returned` | abgegeben, noch nicht eingelagert |
| **stored** | `quantity_stored` | physisch im Regal — nicht `completed` |

**Kein** `material_status`-ENUM pro Zeile: Teilmengen = mehrere Spalten > 0 gleichzeitig (z. B. 2× packed, 4× at_event).

---

## Ebene 1: Bestellung (`activity_item`)

Jede Material-Position in der Aktivität.

| Feld / Status | Bedeutung |
|---------------|-----------|
| `ordered` | Gewünschte Menge — von Entwurf bis Freigabe, danach Referenzmenge |
| `quantity` | Bestellte Stückzahl |

### Zuordnung zu Aktivitäts-Status

| Aktivität | `activity_item` |
|-----------|-----------------|
| `draft` | `ordered` — bearbeitbar |
| `submitted` | `ordered` — eingereicht |
| `approved` | `ordered` — freigegeben, noch nicht gepackt |
| `packing` … | `ordered` bleibt Referenz; physische Bewegung über Packliste |

### Quick-Modus: Einreichen = bestätigt

Bei Typ **`activity`** überspringt die Aktivität den Status `submitted`: Einreichen aus dem Entwurf setzt direkt **`approved`**. Der Gruppenchef-Schritt «Bestätigen» entfällt — das Material ist bei Einreichung final. Siehe [status.md](./status.md#quick-modus-typ-activity-vs-lagerevent).

**Legacy:** `activity_item.status = requested` → Ziel: `ordered`. Das Feld wird nicht für Pack-Stufen verwendet.

### Zwei Tabellen — kein «Umzug»

| Tabelle | Wann | Rolle |
|---------|------|--------|
| `activity_item` | ab `draft` | Planung / Bestellung; bleibt als Referenz |
| `activity_pack_item` | ab `packing` (`autoInitPackList`) | Physische Pipeline; eine aggregierte Zeile pro Material |

Bei Freigabe wird Material **nicht** in eine andere Tabelle verschoben — es wird eine **Pack-Zeile angelegt** und `quantity_ordered` aus den Bestellzeilen summiert.

---

## Ebene 2: Pack-Pipeline (`activity_pack_item`)

Pro Material-Position, Mengen als Buckets (Teilmengen möglich). Gleiche `quantity_*` auf `activity_pack_container_item` pro Kisten-Inhalt.

### Volle Pipeline (Logistics / Camp / Event)

```
ordered → packed → transport_out → at_event → transport_back → returned → stored
```

| Material-Stufe | Feld | Bedeutung |
|----------------|------|-----------|
| ordered | `quantity_ordered` / `activity_item.quantity` | Referenz aus Bestellung |
| packed | `quantity_packed` | Im Lager gepackt / in Kiste |
| transport_out | `quantity_transport_to` | Unterwegs zum Anlass |
| at_event | `quantity_issued` | Bei der Gruppe |
| transport_back | `quantity_transport_back` | Unterwegs zurück ins Lager |
| returned | `quantity_returned` | Gruppe hat abgegeben — MW hat noch nicht eingelagert |
| stored | `quantity_stored` | MW hat geprüft und ins Regal gelegt |

### Quick-Profil (Typ `activity`) und External

Transport-Stufen entfallen in der UI **und** im Backend — `quantity_transport_to` / `quantity_transport_back` bleiben **0** (nur Camp/Event schreibt diese Felder):

```
ordered → packed → at_event → returned → stored
```

| Typ | Packliste bearbeiten | Ausgabe «Am Event» | Retour + Ausgepackt |
|-----|----------------------|--------------------|---------------------|
| `activity` | MW + Gruppe/Ersteller ab «gepackt» | Gruppe / Ersteller | Gruppe retourniert → MW lagert ein |
| `external` | **nur MW/DC** | MW (Ausgabe an Mieter) | **MW** retourniert und lagert ein |

Externe Mieter haben **keinen** Packlisten-Zugang — nach Ausgabe übernimmt der Materialwart.

### Logistics-Profil (Typ `camp` / `event`)

Volle Pipeline inkl. Transport — **Gruppe/Ersteller (bis Leader) ab Aktivitäts-Status «gepackt»**, MW packt vorher und lagert nach «Retour» ein:

```
ordered → packed → transport_out → at_event → transport_back → returned → stored
```

| Journey / Activity-Status | Material-Fokus | Wer (Gruppe) | Wer (MW) |
|---------------------------|----------------|--------------|----------|
| `packing` | → `packed` | — (nur Ansicht) | MW packt |
| `packed` / `transport_out` | → `transport_out` | Gruppe / Leader | Notfall |
| `at_event` (Stepper `issue`) | → `at_event` (`quantity_issued`) | Gruppe / Leader | Notfall |
| `transport_back` | → `transport_back` | Gruppe / Leader | Notfall |
| `returned` | → `returned` | Gruppe / Leader | Notfall |
| `storing` (Stepper `store`) | → `stored` | — | MW lagert ein |

Activity-Status und Material sind **unabhängig**: Status kann `at_event` sein, während Mengen noch auf `packed` oder `transport_out` liegen (Teilausgabe).

Gruppe sieht in der Packliste **4 Transport-Tabs** (ohne «Bestätigt → Gepackt» und ohne «Ausgepackt»).

**Rückwärts (symmetrisch):** Pfeil «←» und `moveback` nur auf dem Tab des aktuellen Aktivitäts-Status (MW-Notfall auf älteren Tabs mit Bestätigung). **Kein** Aktivitäts-Status «Am Event»→«Gepackt» bei Camp/Event — Transport-Stufen nur über die jeweiligen Pack-Tabs. «Retour»→«Am Event» (Status zurück) nur auf dem Tab «Transport (zurück)→Retour eingetroffen», analog zur Vorwärts-Regel.

**Verbrauchsmaterial (Camp/Event):** Auf «Am Event → Transport (zurück)» und «Transport (zurück) → Retour» kein Inline-Quick und kein automatisches Verbrauchs-Modal beim Pfeil «→» — Verbrauch und Nachlieferung nur über die Buttons. Inline-+/− nur im Quick-Profil auf «Am Event → Retour».

**Nachlieferung (API `replenishment_pack_stage`):** Der Zuwachs landet auf der Pipeline-Stufe des aktiven Pack-Tabs — z. B. `packed_transport_to` → `quantity_transport_to`, `transport_back_returned` → **`quantity_transport_back`** (links «Transport (zurück)»), nicht `quantity_returned` (rechts «Retour»). Retour-Rechts buchen nur mit Pfeil «→» oder Quick-Profil `at_event_returned`.

**Anzeige «0 Stk.» (Verbrauchsmaterial):** Auf jedem Pack-Tab links, wenn alles verbraucht ist, aber für diesen Schritt noch nicht mit «→» auf die rechte Seite gebucht wurde (nicht im Lager — kann schon beim Packen sein). Nach Verschieben: Zeile verschwindet links und erscheint nur noch rechts in der Ziel-Stufe dieses Tabs.

### UI-Stufen (Quick / External, MW)

| UI-Stufe | Pipeline-Stufe(n) | Wer (`activity`) | Wer (`external`) |
|----------|-------------------|------------------|------------------|
| Bestätigt → Gepackt | `packed` | MW | MW |
| Gepackt → Am Event | `at_event` | Gruppe (MW: Pfeile mit Bestätigung; Nachlieferung: zuerst Tab «Bestätigt → Gepackt» wenn `packed < ordered`) | MW |
| Am Event → Retour | `returned` | Gruppe | MW |
| Retour → Ausgepackt | `quantity_stored` | MW | MW |

### Phys.-Kombi: Set vs. Referenz-Kiste

| Modus | Stammdaten | `quantity_packed` | Pack-Behälter (`activity_pack_container`) |
|-------|------------|-------------------|-------------------------------------------|
| **Set** (z. B. Zelt ohne Charge) | `physical_combo`, Stückliste (BOM), kein `linked_container_batch` | Direkt über Pack-Pipeline (Pfeil «Bestätigt → Gepackt») | **Optional** — nur wenn MW explizit einpackt |
| **Mit Referenz-Kiste** | `linked_container_batch` gesetzt oder Pack-Behälter mit Lager-Charge | Kann mit Anzahl Batch-Behälter synchronisiert werden | Sinnvoll für Plan/Ist und serialisierte Säcke |

**Verpackungseinheit** (`pack_size` / `pack_unit`, z. B. «2 Fackeln pro Sack») ist **unabhängig** von Pack-Behältern und Kombi-Set — nur Bestell-Bündelung.

**Kistencheck** (beim Verschieben): BOM-Abgleich «alles da?» — Etappen `outbound` (bis Event), `return` (Event→Retour bzw. Event→Transport zurück / Transport→Retour), `warehouse_store` (Einlagern). **Pro eingeloggtem Benutzer** je Etappe einmal (History `pack_crate_check` mit `user_id`); Checks anderer Personen gelten nicht. Einlagern (`warehouse_store`) und Rückweg (`return`) jeweils erneut. Rollen, Modi (voll/leicht) und UI-Regeln: **[pack-workflow-rules.md](./pack-workflow-rules.md)**.

---

## Zuordnung: Aktivität ↔ Material

| Aktivitäts-Status | Packliste existiert? | Typischer Material-Fokus (kann Teilmengen haben) |
|-------------------|----------------------|-----------------------------------------------------|
| `draft` … `approved` | Nein | nur `ordered` |
| `packing` | Ja (Init beim Übergang) | `packed` steigt |
| `packed` | Ja | `packed`; ggf. schon Teilmengen weiter |
| `transport_out` | Ja | `transport_out` |
| `at_event` | Ja | `at_event` (`quantity_issued`); Rest evtl. noch `packed` / `transport_out` |
| `transport_back` | Ja | `transport_back` |
| `returned` | Ja | `returned` — **noch nicht** `stored` |
| `storing` | Ja | `stored` steigt |
| `completed` | Ja | alles relevant `stored` (minus Verlust/Verbrauch) |
| `cancelled` | — | keine weiteren Buchungen |

---

## Regeln (Ziel)

1. **Physische Sperre** — `GREATEST(quantity_packed, quantity_returned, quantity_issued) - quantity_stored` ab Status `packing` … `returned`. Bei **Zeitraum-Abfrage** nur wenn Planungszeitraum der blockierenden Aktivität überlappt (z. B. 5 draussen am 06.06. blockieren nicht die Buchung für 20.06., wenn die Planung nicht kollidiert). Ohne Zeitraum: alle offenen Pipeline-Mengen. Eingelagerte Menge ist sofort frei.
2. **Zeitraum-Reservierung** — `activity_item.quantity` nur in `draft`/`submitted`/`approved` bei Overlap mit `planning_start`/`planning_end` (Fallback `usage_*`). Frühes Packen vor `planning_start`: Sperre über `quantity_packed` im überlappenden Zeitraum.
3. **Abschluss** (`storing` → `completed`) — **Aktivitäts-Abschluss** (Ziel): Material muss physisch geklärt sein (eingelagert **oder** Verlust/Reparatur gemeldet / in Werkstatt). **Keine** Blockade durch ausstehende Buchhaltung oder unfertige Werkstatt-*Kosten*. Effektive Kostenverrechnung = separater **Buchhaltungs-Abschluss** ([accounting.md](../accounting.md#zwei-abschlüsse-kernmodell)). *(Ist-Code blockiert noch durch Werkstatt-Tickets + Accounting-Follow-ups — Umbau geplant.)*
4. **`completed` steuert nicht die Verfügbarkeit** — nur Vorgangsabschluss. Kosten: optional Einnahme-Vermerk in der Aktivität; Buchung immer in `/accounting`, sobald Kosten geklärt sind.
5. **Quick / External:** keine Transport-UI; Logistics: volle Pipeline.
6. **Verbrauchsmaterial einlagern:** Maximal `quantity_ordered − Verbrauchsmeldungen − quantity_stored` (auch wenn `quantity_returned` noch 0 ist). Formale Retour-Stücke nutzen weiterhin `maxStored` aus Pack-Feldern.
7. **Mehr Menge als gebucht:** über **Nachlieferung** auf der Aktivität (`activity_item` mit `is_replenishment`), nicht über Einlagern. **Neue Charge/Batch** im Modul Material = physischer Lagerbestand, unabhängig von der Aktivitäts-Buchung.
8. **Einlagerung rückgängig (Tab «Retour → Ausgepackt», rechts):** ←-Pfeil bucht `quantity_stored` zurück nach links; Bestätigungsdialog warnt, dass die Stücke physisch bereits im Lager liegen können.

---

## Code-Referenzen

| Thema | Ort |
|-------|-----|
| Aktivitäts-Status | `backend/src/Entity/Activity.php` |
| Bestellposition | `backend/src/Entity/ActivityItem.php` |
| Pack-Pipeline | `backend/src/Entity/ActivityPackItem.php`, `PackPipelineService.php` |
| Verfügbarkeit / Sperre | `MaterialAvailabilityController.php`, `MaterialAvailabilityReservationQuery.php` |
| UI-Stufen | `frontend/src/components/activities/packStageQuantities.ts` |
| Pack-Init | `ActivityController::autoInitPackList()` bei `packing` |

---

## Implementierung

### Material (`quantity_*`) — erledigt

- [x] `quantity_stored` (+ Backend-Stufe `stored`) in DB und Pipeline
- [x] `returned_unpack` UI an Stufe `stored` gekoppelt (links: Retour offen, rechts: Ausgepackt / eingelagert)
- [x] Verfügbarkeit: Pipeline-Sperre `GREATEST(packed, returned) - stored`; bei Zeitraum-Abfrage mit Planungs-Overlap; Zeitraum-Reservierung bis `approved`; Reparatur-Batches abgezogen (`MaterialAvailabilityReservationQuery`, `MaterialAvailabilityController`)
- [x] Abschluss-Blocker (#7 Phase 1): nur Einlagerung / Disposition (`unstored_pack_items`); Werkstatt & Buchhaltung = Hinweise
- [x] Abschluss-Blocker (Ziel): nur Material-Disposition; Buchhaltung entkoppelt — [accounting.md](../accounting.md#umsetzungsplan-top-10-7)
- [x] `activity_item.status`: Sync aus Pack-Pipeline (`ActivityItemPipelineStatusService`) bei Move, Kisten, Statuswechsel
- [x] Abschluss-Blocker Einlagerung über `PackPipelineService::maxForwardQty(stored)` (+ Verbrauch pro Material)
- [x] UI: Abschluss-Checkliste bei Status `returned` (`ActivityCompletionChecklist`, Blocker aus `GET …/transitions`)

### Überschüssige Esswaren / Verbrauch bei Retour (geplant)

- [x] Schema `activity_surplus_report` + Spec — [surplus-return-food.md](./surplus-return-food.md)
- [x] API surplus-reports (list/create/patch/delete) + Retour-UI Melden
- [ ] Journey Einlagern: Abarbeiten → Suche / Wizard / Charge
- [ ] Material-Detail Tab Ausgabe: Surplus-Chargen anzeigen

### Activity-Status & Journey (geplant)

- [x] Neue Activity-Status: `transport_out`, `transport_back`, `storing` — [ADR](./newUI/ADR-workflow-layers.md)
- [x] `pack_journey_step` entfernt; Stepper = `activity.status`
- [x] Stepper-Zugriff: vergangene Knoten bearbeitbar bei offenen Mengen auf der PackStage

---

## Siehe auch

- [ADR Workflow-Layers](./newUI/ADR-workflow-layers.md)
- [Aktivitäts-Status](./status.md)
- [Pack-Workflow — einheitliche Regeln](./pack-workflow-rules.md)
- [Pack-Step-UI](./pack-step-ui.md)
- [Pack-Workflow Geräte](../devices/pack-workflow.md)
- [Überschuss Retour Esswaren/Verbrauch](./surplus-return-food.md)
