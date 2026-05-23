# Rollout-Plan: `devices.ematchef.ch`

Phasenplan für Umsetzung und Einführung. Baut auf abgeschlossenem [QR-URL-Umbau](../work/qr-url-umbau-plan.md) (Phase 1–7) auf.

**Stand:** Mai 2026

---

## Voraussetzungen (erledigt / vor Go-Live)

| # | Punkt | Status |
|---|--------|--------|
| V1 | Kanonische Material-QR: `/i/m/{mat}/b/{batch}` | erledigt |
| V2 | Aktivitäts-QR: `/i/a/{code}` | erledigt |
| V3 | Öffentliche Seiten auf `qr.` | erledigt |
| V4 | Infoscreen `app.…/display/{publicId}` | erledigt |
| V5 | Prod: `app:public-code:regenerate-material-batch` (Dry-Run → Live) | beim Deploy |
| V6 | Neue Etiketten drucken (keine `/i/b/`-Only mehr) | Kommunikation Abteilung |

---

## Architektur-Entscheidungen (fix)

| Thema | Entscheidung |
|--------|----------------|
| Frontend | **Gleiche SPA** wie App/`qr.` — Host `devices.ematchef.ch`, Router-Subset |
| Auth | HttpOnly-Cookies, `AUTH_COOKIE_DOMAIN=.ematchef.ch` (inkl. `devices.`) |
| API | Bestehende Activity-Pack-Endpoints — kein zweites Backend |
| UI | Zwei Modi: **Handheld** (TC700H) vs. **Desktop Lager** (PC + PowerScan) |
| Abteilung | **Ein Gerät = eine Abteilung**, Login zu Schichtbeginn |
| Offline v1 | **Keine Queue** — klare Fehlermeldung, Scan wiederholen |
| `qr.` beim Scan | **Nie** Navigation zur QR-URL — nur Parser + API |

---

## Phasen

### D0 — Doku & Hardware-Vorbereitung

**Ziel:** Team und Geräte vorbereiten, ohne Code.

| Aufgabe | Artefakt |
|---------|----------|
| Konzept abstimmen | [concept.md](./concept.md) |
| Pack-Ablauf | [pack-workflow.md](./pack-workflow.md) |
| Zebra TC700H | [zebra-tc700h.md](./zebra-tc700h.md) |
| PowerScan am PC | [datalogic-powerscan.md](./datalogic-powerscan.md) |
| Lokal testen | [local-dev-handheld.md](./local-dev-handheld.md) |
| WLAN/DNS für `*.ematchef.test` im Lager-Testnetz | IT-Checkliste |

**Akzeptanz:** MW versteht Hin/Retour; Test-URLs auf Etiketten zeigen auf kanonische QR.

---

### D1 — Subdomain & Shell

**Status:** umgesetzt (Mai 2026)

**Ziel:** `devices.ematchef.ch` lädt Login und leere Lager-Shell.

| Aufgabe | Technik |
|---------|---------|
| `VITE_DEVICES_HOST` | `devices.ematchef.test` lokal |
| `allowedHosts` in `vite.config.ts` | + `devices.ematchef.test` |
| CORS `CORS_ALLOW_ORIGIN` | + `devices.ematchef.ch` / `.test` |
| `AUTH_COOKIE_DOMAIN` | `.ematchef.ch` deckt `devices.` ab |
| Router: Login, `/{dept}/`, Guards | nur MW-Rollen |
| Abteilung pinnen | localStorage / User-Preference |
| Scan-Input-Testseite | Parser-Stub, Log der letzten Scans |

**Akzeptanz:** Login auf `devices.` funktioniert; Session wie auf `app.`; Scan-String wird geloggt ohne Navigation.

---

### D2 — Aktivität & Session (readonly)

**Status:** umgesetzt (Mai 2026)

**Ziel:** Aktivitäts-QR → Pack-Session, Liste + Live-Fortschritt (readonly).

| Aufgabe | |
|---------|--|
| Route `/{dept}/pack/{activityId}` | |
| Parser: `/i/a/{code}` | |
| `pack-items` + `pack-progress` laden | |
| Polling 3–5 s | |
| Handheld-Layout: Kopf + Fortschrittsbalken + Liste | |
| Desktop-Layout: Tabelle, Filter „offen“ | |
| Fallback-Liste „packing“ | |

**Akzeptanz:** Scan Anlass-QR öffnet Session; Fortschritt aktualisiert sich live (Polling).

---

### D3 — Flow Hin (Material scannen)

**Status:** umgesetzt (Mai 2026)

**Ziel:** Erster produktiver Pfad Lager → Aktivität.

| Aufgabe | |
|---------|--|
| Modus **Hin** (Default) | |
| Parser: `/i/m/…/b/…` | |
| Zeile finden, `move` mit passender `stage` | |
| Ein Workflow-Profil (z. B. `quick` MW) | |
| Erfolg/Fehler-UI | |
| Optional: eine Ziel-Kiste wählen (wenn nicht nur lose) | |

**Akzeptanz:** MW packt 10 Positionen per Scan am TC70; Stand in `app.` Packliste identisch.

---

### D4 — Flow Retour

**Ziel:** Rückweg Aktivität → Lager.

| Aufgabe | |
|---------|--|
| Umschalter **Retour** / Query `flow=return` | |
| `move` / `moveback` mit Retour-Stufen | |
| Hinweis wenn Position nicht retournierbar | |

**Akzeptanz:** Retour-Scan erhöht `quantity_returned` bzw. passende Stufe — nicht „nochmal raus“.

---

### D5 — Desktop Lager + PowerScan

**Ziel:** PC mit Pistole — volleres UI, Wedge-Betrieb.

| Aufgabe | |
|---------|--|
| UI-Modus Desktop (automatisch am PC) | |
| Sichtbares + verstecktes Scan-Feld, Enter-Handler | |
| Letzte N Scans, Fehlerlog | |
| Mehr Spalten (Kiste, Stufe, Benutzer) | |
| Link „In App öffnen“ bei Bedarf | |

**Akzeptanz:** PowerScan tippt URL; bleibt auf `devices.`; gleiche Session wie TC70.

---

### D6 — Presence & Mehrbenutzer

**Ziel:** „Wer ist an was dran?“

| Aufgabe | |
|---------|--|
| Heartbeat `PATCH …/pack-session/presence` (neu) oder leichtgewichtig über bestehende API | |
| Anzeige in Session: Initialen + Kiste/Zeile | |
| Timeout ~60 s | |

**Akzeptanz:** Zwei MW sehen gegenseitig Aktivität am gleichen Anlass.

---

### D7 — Smartphone (optional)

**Ziel:** Handy im Prozess ohne PowerScan.

| Aufgabe | |
|---------|--|
| Responsives Layout zwischen Handheld und Desktop | |
| Readonly-Dashboard für Leitung | |
| Optional Kamera-QR (Browser) | |

**Akzeptanz:** Handy zeigt Fortschritt; Packen optional eingeschränkt.

---

### D8 — Offline-Queue (optional, später)

**Ziel:** Kurze Funklöcher überbrücken.

| Aufgabe | |
|---------|--|
| IndexedDB-Queue für `move` | |
| Sync bei `online` | |
| Konflikt-UI | |

**Akzeptanz:** Scans offline werden nachgereicht oder mit Fehler gemeldet.

---

## Deploy-Reihenfolge (Produktion)

```text
1. Backend (falls D6 neue Endpoints)
2. Frontend SPA (devices-Routen + Env)
3. Nginx/ DNS: devices.ematchef.ch → gleicher Build wie app.
4. CORS + AUTH_COOKIE_DOMAIN prüfen
5. CORS_ALLOW_ORIGIN: devices.ematchef.ch
6. Kommunikation Abteilung: TC70 Start-URL bookmarken
7. Schulung: Hin vs. Retour, neue Material-Etiketten
```

### Nginx / Hosting (Richtlinie)

- `devices.ematchef.ch` → **dieselbe** statische SPA wie `app.` (ein Build, mehrere `server_name`).
- API bleibt `api.ematchef.ch` (oder Proxy `/api` in Dev).

### Lokale Entwicklung

Siehe [local-dev-handheld.md](./local-dev-handheld.md).

---

## MVP-Definition (Release 1)

**Enthalten:** D0 + D1 + D2 + D3 + D4 + D5 (ohne D6–D8)

**Nicht enthalten:**

- Inventur
- Offline-Queue
- Volle Kisten-Parität mit App
- Smartphone-Packen

---

## Risiken & Mitigation

| Risiko | Mitigation |
|--------|------------|
| Scanner öffnet `qr.` statt `devices.` | Start-URL + DataWedge-Profil; Parser auf beiden Hosts, aber Lager nur `devices.` bookmarken |
| WLAN-Löcher | Klare Offline-Meldung; D8 später |
| Zu komplexe Pack-Stufen im MVP | Ein Profil (`quick` MW) zuerst |
| Cookie-Login auf falschem Host lokal | DNS `*.ematchef.test`, nicht rohe IP |

---

## Checkliste „Devices live“

- [ ] `devices.ematchef.ch` erreichbar (HTTPS)
- [ ] Login + Abteilung pinnen getestet (TC70 + PC)
- [ ] Aktivitäts-QR → Session
- [ ] Material-QR Hin + Retour
- [ ] Fortschritt sichtbar für zweiten User (Polling)
- [ ] PowerScan am PC ohne Tab-Wechsel
- [ ] Doku an MW ausgehändigt
- [ ] Etiketten nur noch `/i/m/…/b/…`

---

## Siehe auch

- [concept.md](./concept.md)
- [../work/qr-url-umbau-plan.md](../work/qr-url-umbau-plan.md)
