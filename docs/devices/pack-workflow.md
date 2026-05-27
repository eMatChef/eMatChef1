# Pack-Workflow auf `devices.ematchef.ch`

Ablauf vom Scan bis zur Buchung — Hin (Lager → Aktivität) und Retour (Aktivität → Lager).

**Stand:** Mai 2026 · Spezifikation

Siehe auch: [concept.md](./concept.md) · [rollout-plan.md](./rollout-plan.md)

---

## Übersicht

```text
                    ┌─────────────────┐
                    │ Login devices.  │
                    │ Abteilung pin   │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Aktivität scannen │
                    │  /i/a/{code}    │
                    └────────┬────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
     ┌────────▼────────┐           ┌────────▼────────┐
     │ Modus: HIN      │           │ Modus: RETOUR   │
     │ (Lager → Event) │           │ (Event → Lager) │
     └────────┬────────┘           └────────┬────────┘
              │                             │
              └──────────────┬──────────────┘
                             │
                    ┌────────▼────────┐
                    │ Material scannen │
                    │ /i/m/…/b/…      │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ API: move /      │
                    │ pack-items       │
                    └─────────────────┘
```

---

## Schritt 0 — Gerät vorbereiten

1. Browser auf TC70 (Enterprise Browser / Chrome) oder PC öffnen: `https://devices.ematchef.ch`
2. **Einmal pro Schicht einloggen** (gleicher Account wie App).
3. **Abteilung wählen** — wird am Gerät gespeichert (ein Gerät = eine Abteilung).

---

## Schritt 1 — Aktivität wählen

| Methode | Aktion |
|---------|--------|
| **QR** | Anlass-QR scannen (`qr…/i/a/{activityCode}`) |
| **Liste** | Fallback: Aktivitäten mit Status `packing` (und ggf. `packed` für Retour) |

Ergebnis: Pack-Session `/{deptId}/pack/{activityId}`.

---

## Schritt 2 — Flow wählen (Hin oder Retour)

| Modus | Wann | UI |
|--------|------|-----|
| **Hin** | Material geht zum Anlass | Standard nach Aktivitäts-Scan |
| **Retour** | Material kommt zurück | Grosser Umschalter „Retour“ — verhindert falsche Richtung |

Die App mappt den Modus auf die passenden **`stage`**-Werte beim API-`move` (siehe `ActivityWorkflowController`).

### Typische Stufen (vereinfacht)

**Profil `logistics` (Camp/Event):**

| Richtung | Stufen (Beispiele) |
|----------|-------------------|
| Hin | `confirmed_packed` → `packed_transport_to` → `transport_to_at_event` → … |
| Retour | `at_event_transport_back` → `transport_back_returned` |

**Profil `quick` / `external` (MW):**

| Richtung | Stufen (Beispiele) |
|----------|-------------------|
| Hin | `confirmed_packed` → `packed_at_event` |
| Retour | `at_event_returned` → `returned_unpack` |

Exakte Stufen pro Abteilung/Aktivitätstyp kommen aus `packWorkflowProfile` — MVP startet mit **einem** Profil.

---

## Schritt 3 — Material scannen

1. Scanner tippt URL (z. B. `https://qr.ematchef.ch/i/m/ABC/b/XYZ`) + Enter.
2. Parser liefert `materialCode` + `batchCode`.
3. Backend/Frontend findet **Pack-Item** zur Aktivität + Batch.
4. Je nach Modus Hin/Retour: `POST …/pack-items/{id}/move` mit passender `stage` und `quantity`.

### Erfolg / Fehler (UX)

| Ergebnis | Handheld | Desktop |
|----------|----------|---------|
| OK | Grüner Banner, optional Ton | Zeile aktualisiert, Log-Eintrag |
| Nicht auf Liste | Rot: „Nicht auf Packliste“ | gleich + Link „In App öffnen“ |
| Falsche Aktivität | Rot: „Andere Aktivität wählen“ | — |
| Netz weg | Gelb/Rot: „Offline — nicht gespeichert“ | — |

---

## Schritt 4 — Live-Fortschritt

Während der Session (Polling):

- **X / Y** Positionen erledigt
- Liste: offen vs. erledigt (Handheld: kompakte Zeilen)
- Später: **„Max M. · Kiste 2“** (Presence)

Mehrere MW am gleichen Anlass: alle sehen denselben Stand (Server ist Source of Truth).

---

## Kisten / Container

Die volle App (`ActivityPackListTab`) unterstützt Kisten, Shells, Issue/Return pro Container.

**MVP-Option A:** Nur **lose** Pack-Positionen (einfachster Scan → eine Zeile).

**MVP-Option B:** Feste **Ziel-Kiste** vor dem Scannen wählen, dann Material zuordnen (wie „Material in Kiste 3“).

**Später:** Parität mit Desktop-Packliste (Container-Cards) im **Desktop-Lager**-Modus; Handheld bleibt reduziert.

Entscheidung im [rollout-plan.md](./rollout-plan.md) Phase D3/D4.

---

## API-Referenz (Ist)

| Aktion | Endpoint (Auszug) |
|--------|-------------------|
| Packliste | `GET /api/activities/{id}/pack-items` |
| Fortschritt | `GET /api/activities/{id}/pack-progress` |
| Bewegen | `POST /api/activities/{id}/pack-items/{packItemId}/move` |
| Zurück | `POST …/moveback` |
| Container | `ActivityPackContainerController` unter `/pack-containers` |

Devices ruft dieselben Endpoints auf — keine parallele „Devices-API“, ausser optional später `GET /api/devices/resolve-scan` als Komfort.

---

## Abgrenzung zu `qr.` und `app.`

| Aktion | Wo |
|--------|-----|
| Öffentlich Material anzeigen, Finder-Kontakt | `qr.` |
| Packliste bearbeiten, Kisten-Modals, Statuswechsel Aktivität | `app.` |
| **Schnell packen per Scan im Lager** | **`devices.`** |
