# Konzept: `devices.ematchef.ch` (Lager & Packen)

**Stand:** Mai 2026  
**Status:** Spezifikation — Umsetzung folgt nach QR-Umbau (Phase 1–7 abgeschlossen)

Verwandte Doku:

- [QR-Linkschema](../qr/link-schema.md) — Etiketten-URLs, `public_code`
- [Öffentliche QR-Seiten](../qr/qr-public-pages.md) — was auf `qr.` passiert
- [Rollout-Plan](./rollout-plan.md) — Phasen und Deploy
- [Lokales Testen am Handheld](./local-dev-handheld.md)

---

## 1. Zielbild

**Nutzer:** Materiallager / MW mit:

- **Zebra TC700H** (Handheld, Browser, Touch)
- **Datalogic PowerScan 8530** (Pistole, Bluetooth HID, USB-Dock = Tastatur-Wedge) — **nur am Laptop/PC**

**Zweck:** Reduzierte Web-Oberfläche zum **Packen einer Aktivität** (Hin- und Retour-Flow), nicht die volle `app.ematchef.ch` mit Sidebar und allen Tabs.

**Nicht in v1:** Inventur, Material-Neuerfassung, Werkstatt-Bearbeitung, öffentliche Finder-Seiten auf `devices.`.

**Technik:** Gleiche **Vue-SPA** und **Symfony-API** wie die App; eigene Subdomain und schlankes Routing. Login über **HttpOnly-Cookies** domain-weit (siehe [deploy/CROSS-SUBDOMAIN-LOGIN.md](../../deploy/CROSS-SUBDOMAIN-LOGIN.md)).

---

## 2. Drei Subdomains

| Host | Rolle | Login |
|------|--------|--------|
| `qr.ematchef.ch` | Öffentlich: Material/Anlass/Werkstatt kurz + Kontakt | optional |
| `app.ematchef.ch` | Volle Verwaltung, Packliste im Aktivitäts-Tab | User-Session |
| **`devices.ematchef.ch`** | **Lager:** Scan → Packen, grosser Touch / Desktop-Lager | User-Session |

**Infoscreen** bleibt auf `app.…/display/{publicId}` (PIN, kein User-Login) — kein Lager-Gerät.

---

## 3. Zwei UI-Modi (eine SPA)

Gleiche Codebasis; Darstellung je nach Gerät:

| Modus | Gerät | UI |
|--------|--------|-----|
| **Handheld** | Zebra TC700H | Stark reduziert: Vollbild, grosse Buttons, wenig Text, eine Hauptaktion pro Screen |
| **Desktop Lager** | Laptop/PC + PowerScan | Schmaler als volle App, aber **mehr** als Handheld: Tabelle, Filter, Pack-Stufen, Kisten sichtbar |

Erkennung z. B. über Viewport/Touch, User-Agent oder Query `?mode=handheld|desktop`. TC70 startet standardmässig **Handheld**; PC mit Pistole **Desktop Lager**.

**PowerScan:** Verhält sich wie Tastatur (Zeichen + Enter). **Kein** `window.location` zur gescannten URL — zentraler **Scan-Parser** im Hintergrund.

---

## 4. QR = URL → Entität

Etiketten und PDF-QR enthalten **URLs** (z. B. `https://qr.ematchef.ch/i/m/{mat}/b/{batch}`). Auf `devices.`:

1. Scanner tippt URL (+ Enter) in verstecktes Scan-Feld.
2. Parser extrahiert Typ und Codes.
3. App ruft **interne APIs** auf — **kein** Tab-Wechsel zu `qr.ematchef.ch`.

| Scan-Inhalt | Bedeutung |
|-------------|-----------|
| `…/i/a/{activityCode}` | Aktivität öffnen / Pack-Session |
| `…/i/m/{mat}/b/{batch}` | Material+Charge in aktiver Session verbuchen |
| Nur alphanumerischer Code (selten) | Lookup-API, dann wie oben |

Alte `/i/b/{batch}`-Only-URLs werden **nicht** unterstützt (QR-Umbau Phase 7). Neue Etiketten immer kanonisch `/i/m/…/b/…`.

---

## 5. Zwei Pack-Flows (Hin + Retour)

Entspricht den bestehenden **Pack-Stufen** in der App (`packStageQuantities.ts`, `ActivityWorkflowController` — `move`, `pack-progress`).

### Flow A — Materiallager → Aktivität (Hin)

Material verlässt das Lager Richtung Anlass (Stufen je nach Aktivitäts-Profil `logistics` / `external` / `quick`), z. B.:

- `confirmed_packed` → Transport / Event (`packed_at_event`, `issued`, …)

**Start:** Aktivitäts-QR scannen → Session im Modus **„Hin“**.

### Flow B — Aktivität → Materiallager (Retour)

Material kommt zurück (z. B. `transport_back_returned`, `at_event_returned`, `returned_unpack`).

**Gleiche Session**, expliziter Modus **„Retour“** (Umschalter oder `?flow=return`), damit nicht erneut „raus“ gebucht wird.

---

## 6. Routing (Ziel)

```text
devices.ematchef.ch/login
devices.ematchef.ch/{deptId}/              → Abteilung (gepinnt), „Aktivität scannen“, Liste „packing“
devices.ematchef.ch/{deptId}/pack/{activityId}  → Pack-Session (flow=out|return)
```

Optional: Link aus `app.` — „Im Lager öffnen“.

Konfiguration (geplant): `VITE_DEVICES_HOST`, `isDevicesHost()`, Router-Subset, Redirects analog `VITE_QR_PUBLIC_HOST`.

---

## 7. Organisatorische Regeln

| Regel | Umsetzung |
|--------|-----------|
| **Ein Gerät = eine Abteilung** | Nach Login Abteilung wählen/pinnen (`activeDepartment`); Startscreen ohne erneute Wahl |
| **Login zu Schichtbeginn** | Einmal auf `devices.`; Cookie `.ematchef.ch` / lokal `.ematchef.test` |
| **Eine Pack-Session pro Gerät** | Eine Aktivität aktiv; Wechsel per Aktivitäts-QR |
| **Minimal Design** | Keine Sidebar, klare Farben (grün/rot/gelb), wenig Dekoration |

---

## 8. Live-Status (was gepackt ist, wer dran ist)

**Ist im Code:** `GET /api/activities/{id}/pack-progress`, Pack-Items mit Mengen/Stufen — **kein** WebSocket/Mercure.

**Geplant:**

| Feature | v1 | später |
|---------|-----|--------|
| Fortschritt (offen/erledigt) | Polling 3–5 s, Fortschrittsbalken | — |
| Letzter Scan / Feedback | gross auf Handheld | Sound/Vibration optional |
| **Wer packt gerade** | — | Presence (Heartbeat, Timeout 60 s) |

Handheld: wenige Infos pro Zeile (Material, Soll/Ist, Stufe). Desktop: Tabelle + Filter „nur offen“.

---

## 9. Smartphones

**PowerScan nur am PC/Laptop** — kein HID-Wedge am TC70.

| Rolle | Einsatz | UI |
|--------|---------|-----|
| MW Lager | TC70 oder PC | volles Packen |
| Leitung / Übersicht | Handy | readonly Fortschritt (optional) |
| Helfer am Event | eher `app.` Quick-Flow | nicht primär `devices.` |
| Ad-hoc | Handy mit Kamera-QR | responsives Layout, kein Wedge |

**Empfehlung:** v1 = TC70 + PC; Smartphone zuerst **Zuschauen** oder mittleres responsives Layout, volles Packen am Handy erst bei Bedarf.

---

## 10. Backend / Frontend-Wiederverwendung

**Backend (vorhanden):**

- `ActivityWorkflowController` — `pack-items`, `pack-progress`, `move`, `moveback`, …
- `ActivityPackContainerController` — Kisten/Container

**Frontend (vorhanden, komplex):**

- `ActivityDetailView`, `ActivityPackListTab`, viele `Pack*.vue`-Komponenten

**Strategie:** Eigenes schlankes UI auf `devices.`, **dieselben API-Calls**. Mittelfristig gemeinsames Composable `useActivityPackSession()` extrahieren (nicht iframe der vollen App).

---

## 11. Offline

**Heute (ohne Devices-Feature):** Kein Service Worker, keine Offline-Queue. Request scheitert ohne Netz → **nichts serverseitig gespeichert**; kein automatisches Nachreichen.

**v1 Devices:** Klare UI-Meldung „Kein Netz — Scan nicht gespeichert“; Scan bei WLAN wiederholen.

**Später (optional):** IndexedDB-Warteschlange, Sync bei `online`, Konflikt-Anzeige — separates Arbeitspaket.

Details: [local-dev-handheld.md](./local-dev-handheld.md#offline).

---

## 12. Scan-Parser (Spezifikation)

Zentrale Funktion `parseScanInput(raw: string)`:

```ts
// Beispiel-Rückgabetypen
type ScanParseResult =
  | { type: 'activity'; activityCode: string }
  | { type: 'material_batch'; materialCode: string; batchCode: string }
  | { type: 'unknown'; raw: string }
```

Regeln:

- URLs von `qr.` und `devices.` gleich behandeln (nur Pfad relevant).
- Bei Material-Scan: nur in **offener** Pack-Session mit passendem Flow (Hin/Retour).
- Fehler: unbekannter Code, Zeile nicht auf Packliste, Menge bereits voll — **gross und eindeutig** (Handheld).

---

## 13. Offene Produktentscheidungen

Vor MVP mit Fachbereich klären:

1. Welches **Aktivitäts-Profil** zuerst (`quick` MW vs. volle `logistics` mit Kisten)?
2. Packen vor allem **lose** oder fast immer in **Kisten/Container**?
3. Sollen **Smartphones** im Lager mitpacken oder nur Fortschritt zeigen?
4. Presence („wer dran“) in v1 oder v1.5?

---

## 14. Siehe auch

- [pack-workflow.md](./pack-workflow.md) — Ablauf Hin/Retour im Detail
- [rollout-plan.md](./rollout-plan.md) — Phasen D0–D8
- [zebra-tc700h.md](./zebra-tc700h.md) — Handheld
- [datalogic-powerscan.md](./datalogic-powerscan.md) — Pistole am PC
