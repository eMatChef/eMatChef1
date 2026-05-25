# Material-Pipeline (Bestellung & Packliste)

Zwei **getrennte** Status-Ebenen pro Aktivität:

1. **Aktivitäts-Status** — Freigabe, Rollen, Benachrichtigungen → [status.md](./status.md)
2. **Material-Pipeline** — physische Position jeder Position/Menge

**Stand:** Mai 2026 · `quantity_stored` implementiert

---

## Warum zwei Ebenen?

| Ebene | Frage | Beispiel |
|-------|-------|----------|
| Aktivität | «In welcher Phase ist der Anlass?» | `returned` = Gruppe hat retourniert |
| Material | «Wo ist Stück X?» | 3× retourniert, 1× noch nicht eingelagert |

Eine Aktivität kann `returned` sein, während einzelne Positionen noch nicht `quantity_stored` sind. **Verfügbarkeit für andere Anlässe** wird pro Position freigegeben, sobald die Menge **eingelagert** ist (`quantity_stored`). Der Aktivitäts-Status `completed` betrifft den **Vorgangsabschluss** (Einlagerung vollständig, Meldungen, Werkstatt, Buchhaltung).

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

---

## Ebene 2: Pack-Pipeline (`activity_pack_item`)

Pro Material-Position, Mengen als Buckets (Teilmengen möglich).

### Volle Pipeline (Logistics / Camp / Event)

```
ordered → packed → transport_to → at_event → transport_back → returned → quantity_stored
```

| Stufe | Feld | Bedeutung |
|-------|------|-----------|
| Bestellt | *(activity_item.quantity)* | Referenz aus Bestellung |
| Gepackt | `quantity_packed` | Im Lager gepackt / in Kiste |
| Transport hin | `quantity_transport_to` | Unterwegs zum Event |
| Am Event | `quantity_issued` | Bei der Gruppe |
| Transport zurück | `quantity_transport_back` | Unterwegs zurück ins Lager |
| Retour | `quantity_returned` | Gruppe hat abgegeben — MW hat noch nicht eingelagert |
| Eingelagert | `quantity_stored` | MW hat geprüft und ins Regal gelegt |

### Quick-Profil (Typ `activity`) und External

Transport-Stufen entfallen in der UI **und** im Backend — `quantity_transport_to` / `quantity_transport_back` bleiben **0** (nur Camp/Event schreibt diese Felder):

```
ordered → packed → at_event → returned → quantity_stored
```

| Typ | Packliste bearbeiten | Ausgabe «Am Event» | Retour + Ausgepackt |
|-----|----------------------|--------------------|---------------------|
| `activity` | MW + Gruppe/Ersteller ab «gepackt» | Gruppe / Ersteller | Gruppe retourniert → MW lagert ein |
| `external` | **nur MW/DC** | MW (Ausgabe an Mieter) | **MW** retourniert und lagert ein |

Externe Mieter haben **keinen** Packlisten-Zugang — nach Ausgabe übernimmt der Materialwart.

### Logistics-Profil (Typ `camp` / `event`)

Volle Pipeline inkl. Transport — **Gruppe/Ersteller (bis Leader) ab Aktivitäts-Status «gepackt»**, MW packt vorher und lagert nach «Retour» ein:

```
ordered → packed → transport_to → at_event → transport_back → returned → quantity_stored
```

| UI-Stufe (MW) | Pipeline | Wer (Gruppe) | Wer (MW) |
|---------------|----------|--------------|----------|
| Bestätigt → Gepackt | `packed` | — (nur Ansicht) | MW packt |
| Gepackt → Transport hin | `transport_to` | Gruppe / Leader | Notfall |
| Transport hin → Am Event | `at_event` | Gruppe / Leader | Notfall |
| Am Event → Transport zurück | `transport_back` | Gruppe / Leader | Notfall |
| Transport zurück → Retour | `returned` | Gruppe / Leader | Notfall |
| Retour → Ausgepackt | `quantity_stored` | — | MW lagert ein |

Gruppe sieht in der Packliste **4 Transport-Tabs** (ohne «Bestätigt → Gepackt» und ohne «Ausgepackt»).

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

**Kistencheck** (beim Verschieben): BOM-Abgleich «alles da?» — Etappen `outbound` (bis Event), `return` (Event→Retour bzw. Event→Transport zurück / Transport→Retour), `warehouse_store` (Einlagern). Gruppe/Leiter (u, L1–L3): je Etappe einmal, inkl. Rückweg. MW/DC: Hinweg (`outbound`) einmal pro Aktivität; **Rückweg (`return`) erneut** (auch Phys.-Kombi); Einlagern (`warehouse_store`) erneut.

---

## Zuordnung: Aktivität ↔ Material

| Aktivitäts-Status | Packliste existiert? | Typische Material-Lage |
|-------------------|----------------------|-------------------------|
| `draft` | Nein | nur `ordered` |
| `submitted` | Nein | nur `ordered` |
| `approved` | Nein | nur `ordered` |
| `packing` | Ja (Init beim Übergang) | `packed` steigt |
| `packed` | Ja | alles `packed`, noch nicht `at_event` |
| `at_event` | Ja | `quantity_issued` |
| `returned` | Ja | `quantity_returned` — **noch nicht** `quantity_stored` |
| `completed` | Ja | `quantity_stored` vollständig (minus Verlust/Verbrauch) |
| `cancelled` | — | keine weiteren Buchungen |

---

## Regeln (Ziel)

1. **Physische Sperre** — `GREATEST(quantity_packed, quantity_returned) - quantity_stored` ab Status `packing` … `returned`, **ohne** Zeitraum-Overlap. Eingelagerte Menge ist sofort für andere Anlässe frei.
2. **Zeitraum-Reservierung** — `activity_item.quantity` nur in `draft`/`submitted`/`approved` bei Overlap mit `planning_start`/`planning_end` (Fallback `usage_*`). Frühes Packen vor `planning_start`: Sperre über `quantity_packed`. Nach `planning_end`: Sperre über offene Pipeline-Menge bis `stored`.
3. **Abschluss** (`returned` → `completed`) blockiert bei: offenem Einlagern (`packed`/`returned` > `stored`), offenen Issue-Meldungen (Verlust/Reparatur/Schaden), offenen Werkstatt-Tickets, **allen** ausstehenden Buchhaltungs-Aufträgen der Aktivität (mehrere `activity_*`-Follow-ups, kein `activity_final`).
4. **`completed` steuert nicht die Verfügbarkeit** — nur Vorgangsabschluss; Kosten laufen über die einzelnen Buchhaltungs-Aufträge ab Retour.
5. **Quick / External:** keine Transport-UI; Logistics: volle Pipeline.

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

- [x] `quantity_stored` (+ Backend-Stufe `stored`) in DB und Pipeline
- [x] `returned_unpack` UI an Stufe `stored` gekoppelt (links: Retour offen, rechts: Ausgepackt / eingelagert)
- [x] Verfügbarkeit: Pipeline-Sperre `GREATEST(packed, returned) - stored`; Zeitraum-Reservierung bis `approved` (`MaterialAvailabilityReservationQuery`)
- [x] Abschluss-Blocker: Einlagerung, Issues, Werkstatt, Buchhaltung (`ActivityController::getCompletionBlockers`)
- [x] `activity_item.status`: Sync aus Pack-Pipeline (`ActivityItemPipelineStatusService`) bei Move, Kisten, Statuswechsel
- [x] Abschluss blockiert, solange `quantity_returned > quantity_stored`
- [x] UI: Abschluss-Checkliste bei Status `returned` (`ActivityCompletionChecklist`, Blocker aus `GET …/transitions`)

---

## Siehe auch

- [Aktivitäts-Status](./status.md)
- [Pack-Workflow Geräte](../devices/pack-workflow.md)
