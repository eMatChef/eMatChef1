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
| Transport hin → Am Event | `at_event` | Gruppe / Leader | Notfall — **Aktivitäts-Status «Am Event»** nur von diesem Tab (nicht schon bei «Transport hin») |
| Am Event → Transport zurück | `transport_back` | Gruppe / Leader | Notfall |
| Transport zurück → Retour | `returned` | Gruppe / Leader | Notfall — **Aktivitäts-Status «Retour»** nur von diesem Tab (nicht schon bei «Transport zurück») |
| Retour → Ausgepackt | `quantity_stored` | — | MW lagert ein |

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

**Kistencheck** (beim Verschieben): BOM-Abgleich «alles da?» — Etappen `outbound` (bis Event), `return` (Event→Retour bzw. Event→Transport zurück / Transport→Retour), `warehouse_store` (Einlagern). **Pro eingeloggtem Benutzer** je Etappe einmal (History `pack_crate_check` mit `user_id`); Checks anderer Personen gelten nicht. Einlagern (`warehouse_store`) und Rückweg (`return`) jeweils erneut.

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

1. **Physische Sperre** — `GREATEST(quantity_packed, quantity_returned, quantity_issued) - quantity_stored` ab Status `packing` … `returned`. Bei **Zeitraum-Abfrage** nur wenn Planungszeitraum der blockierenden Aktivität überlappt (z. B. 5 draussen am 06.06. blockieren nicht die Buchung für 20.06., wenn die Planung nicht kollidiert). Ohne Zeitraum: alle offenen Pipeline-Mengen. Eingelagerte Menge ist sofort frei.
2. **Zeitraum-Reservierung** — `activity_item.quantity` nur in `draft`/`submitted`/`approved` bei Overlap mit `planning_start`/`planning_end` (Fallback `usage_*`). Frühes Packen vor `planning_start`: Sperre über `quantity_packed` im überlappenden Zeitraum.
3. **Abschluss** (`returned` → `completed`) blockiert bei: offenem Einlagern (gleiche Logik wie `maxForwardQty` für Stufe `stored`, inkl. Verbrauchsmaterial: `ordered − Verbrauch − stored`), offenen Issue-Meldungen (Verlust/Reparatur/Schaden), offenen Werkstatt-Tickets, **allen** ausstehenden Buchhaltungs-Aufträgen der Aktivität (mehrere `activity_*`-Follow-ups, kein `activity_final`).
4. **`completed` steuert nicht die Verfügbarkeit** — nur Vorgangsabschluss; Kosten laufen über die einzelnen Buchhaltungs-Aufträge ab Retour.
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

- [x] `quantity_stored` (+ Backend-Stufe `stored`) in DB und Pipeline
- [x] `returned_unpack` UI an Stufe `stored` gekoppelt (links: Retour offen, rechts: Ausgepackt / eingelagert)
- [x] Verfügbarkeit: Pipeline-Sperre `GREATEST(packed, returned) - stored`; bei Zeitraum-Abfrage mit Planungs-Overlap; Zeitraum-Reservierung bis `approved`; Reparatur-Batches abgezogen (`MaterialAvailabilityReservationQuery`, `MaterialAvailabilityController`)
- [x] Abschluss-Blocker: Einlagerung, Issues, Werkstatt, Buchhaltung (`ActivityController::getCompletionBlockers`)
- [x] `activity_item.status`: Sync aus Pack-Pipeline (`ActivityItemPipelineStatusService`) bei Move, Kisten, Statuswechsel
- [x] Abschluss-Blocker Einlagerung über `PackPipelineService::maxForwardQty(stored)` (+ Verbrauch pro Material)
- [x] UI: Abschluss-Checkliste bei Status `returned` (`ActivityCompletionChecklist`, Blocker aus `GET …/transitions`)

---

## Siehe auch

- [Aktivitäts-Status](./status.md)
- [Pack-Workflow Geräte](../devices/pack-workflow.md)
