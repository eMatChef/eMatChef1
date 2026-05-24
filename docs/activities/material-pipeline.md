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

Eine Aktivität kann `returned` sein, während einzelne Positionen noch nicht `quantity_stored` sind. Das ist gewollt — sonst müsste MW die Aktivität offen halten. **Verfügbarkeit im Lager** wird trotzdem erst wieder freigegeben, wenn die Aktivität **`completed`** ist (Einlagerung allein reicht nicht).

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
| Gepackt → Am Event | `at_event` | MW / Gruppe | MW |
| Am Event → Retour | `returned` | Gruppe | MW |
| Retour → Ausgepackt | `quantity_stored` | MW | MW |

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

1. **Erst bei `completed` wieder verfügbar** — Material bleibt gesperrt ab «Wird gepackt» bis zur Aktivitäts-Abschluss, auch wenn schon retourniert/eingelagert oder das Rückgabedatum vorbei ist.
2. **Ab «Wird gepackt» gesperrt** — `GREATEST(quantity_packed, quantity_returned)` blockiert die Verfügbarkeit **ohne** Zeitraum-Overlap (kein Abzug durch `quantity_stored` vor `completed`).
3. **Bestell-Reservierung** — `activity_item.quantity` mit Zeitraum-Overlap nur in `draft`/`submitted`/`approved`.
4. **Abschluss** (`returned` → `completed`) erst wenn alle relevanten Positionen eingelagert (oder als Verlust/Verbrauch gebucht).
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
- [x] Verfügbarkeit: gepacktes / retourniertes Material blockiert «frei» bis **`completed`** (`MaterialAvailabilityReservationQuery`)
- [x] `activity_item.status`: Sync aus Pack-Pipeline (`ActivityItemPipelineStatusService`) bei Move, Kisten, Statuswechsel
- [x] Abschluss blockiert, solange `quantity_returned > quantity_stored`

---

## Siehe auch

- [Aktivitäts-Status](./status.md)
- [Pack-Workflow Geräte](../devices/pack-workflow.md)
