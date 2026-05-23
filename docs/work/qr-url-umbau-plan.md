# Arbeitspaket: QR-URL-Umbau (Material, Aktivität, Werkstatt)

Plan zur Umstellung der bestehenden QR-Struktur auf das Zielschema aus [docs/qr/link-schema.md](../qr/link-schema.md) und [docs/qr/qr-public-pages.md](../qr/qr-public-pages.md).

**Stand:** Mai 2026  
**Status:** In Arbeit — Phase 1–6 erledigt (Phase 7 Querschnitt/Deploy offen)

---

## Ziel (Kurz)

| Thema | Entscheidung |
|--------|----------------|
| Material-Etikett | Immer `qr.ematchef.ch/i/m/{materialCode}/b/{batchCode}` |
| Scanner (intern) | Kurzform `qr.ematchef.ch/i/b/{batchCode}` — Auflösung gleich |
| Aktivität | `qr.ematchef.ch/i/a/{activityCode}` |
| Werkstatt | `qr.ematchef.ch/i/w/{workshopCode}` |
| `qr.ematchef.ch` | Nur öffentliche Kurzseiten + Kontakt |
| Infoscreen | `app.ematchef.ch/display/{publicId}` (PIN + Cookie, ohne App-Login) |
| Codes | Immer `public_code` (Tabelle `public_code`), keine UUIDs in URLs |
| App-Anzeige | **Nur neue URLs** — kein Anzeigen alter `/i/m/` ohne `/b/` als Druck-URL |
| Alte Aufkleber | Route `/i/b/{code}` bleibt **lesbar** (kein App-Fallback für Druck) |

**Nicht in diesem Paket (später):** `devices.ematchef.ch` (Lager-Packen) — siehe [docs/devices/README.md](../devices/README.md).

---

## Ist-Stand vs. Ziel

| Bereich | Heute (Code) | Ziel |
|--------|----------------|------|
| Material-URL | `…/i/m/{code}` und `…/i/b/{code}` getrennt | Druck: `…/i/m/{mat}/b/{batch}` |
| Massen-Chargen | oft kein Batch-`public_code` | jede relevante Charge eigener Code |
| `ensurePublicCode` | Batch-QR nur bei **serialisiert** | Batch-QR für **alle** aktiven Chargen |
| `material.public_url` | oft nur `/i/m/…` | in UI/Druck nicht mehr; nur Batch-Zeilen |
| Aktivität | kein `public_code`, kein QR in UI | `/i/a/{activityCode}` + Anzeige |
| Werkstatt | kein `public_code`, kein QR in UI | `/i/w/{workshopCode}` + Anzeige |
| Öffentliche Route | `/i/:type/:code` (`m`/`b`) | + hierarchische URL, `/i/a/`, `/i/w/` |
| Display | — | `app.…/display/{publicId}` + Verwaltung in Einstellungen |

### Wichtige Code-Stellen (Ist)

| Bereich | Datei |
|---------|--------|
| URL Builder | `backend/src/Service/Public/PublicCodeService.php` |
| Material QR erzeugen | `backend/src/Controller/MaterialController.php` → `ensurePublicCode` |
| Material serialisieren | `MaterialController::serializeMaterial` → `public_url` pro Material/Batch |
| Öffentliche API | `backend/src/Controller/Public/PublicLookupController.php` |
| Öffentliche Seite | `frontend/src/views/public/PublicMaterialView.vue` |
| Router | `frontend/src/router/index.ts` → `/i/:type/:code` |
| Material QR UI | `frontend/src/components/material/MaterialDetailView.vue` |
| Druckkorb | `PrintTaskItem`, `TasksPrintView`, `frontend/src/api/tasks.ts` |
| QR-Host | `applyQrHostRedirects` in `router/index.ts` |
| QR-Aktions-Modal | `frontend/src/components/common/PublicQrActionModal.vue` |
| Infoscreen | `frontend/src/views/DepartmentDisplayView.vue` |

---

## Abhängigkeiten (Reihenfolge)

```text
Phase 1 ──► Phase 2 ──► Phase 3
   │            │            │
   │            │            ├── Phase 4 (Aktivität)
   │            │            └── Phase 5 (Werkstatt)
   │            │
   │            └── braucht Phase 1 (Builder)
   │
   └── PublicCodeService + Lookup

Phase 6 (Display) nach Phase 4 + 5 (braucht public_url auf Aktivität/Werkstatt)
```

---

## Phase 1 — Backend: URL-Builder & Resolver

**Ziel:** Zentrale URL-Logik und öffentliche Auflösung für neues Schema.

### Aufgaben

- [x] **1.1** `PublicCodeService::buildMaterialBatchPublicUrl($materialCode, $batchCode)`  
  → `{APP_PUBLIC_QR_URL}/i/m/{mat}/b/{batch}` (rawurlencode)

- [x] **1.2** `buildActivityPublicUrl($activityCode)` → `…/i/a/{code}`

- [x] **1.3** `buildWorkshopPublicUrl($workshopCode)` → `…/i/w/{code}`

- [x] **1.4** `ensureActivityPublicCode(Activity)` — `entity_type = activity` in `public_code`

- [x] **1.5** `ensureWorkshopPublicCode(WorkshopTicket)` — `entity_type = workshop`

- [x] **1.6** `resolveActivityByPublicCode`, `resolveWorkshopByPublicCode` (öffentliche Felder + Department)

- [x] **1.7** Lookup erweitern:
  - [x] `GET /api/public/lookup/m/{mat}/b/{batch}` — prüfen: Batch gehört zu Material
  - [x] `GET /api/public/lookup/a/{code}`
  - [x] `GET /api/public/lookup/w/{code}`

- [x] **1.8** Hilfsmethode: aus Material-ID + Batch-ID die kanonische `public_url` bauen (für Serialisierung)

- [x] **1.9** `buildMaterialPublicUrl` / Druck: **nicht mehr** für Etiketten verwenden (deprecated-Kommentar im Service)

**Akzeptanz:** Unit/manuell: zwei Codes → URL `…/i/m/X/b/Y`; Lookup liefert Material + Batch.

---

## Phase 2 — Material: Codes & Migration (neu generieren)

**Ziel:** Jede aktive Charge hat `public_code`; App liefert nur noch kanonische Batch-URLs. **Kein** UI-Fallback auf alte Material-only-URLs.

### Aufgaben

- [x] **2.1** `MaterialController::ensurePublicCode` anpassen:
  - [x] `ensureMaterialPublicCode` (für Segment `{mat}`)
  - [x] Für **jede** relevante Charge (Masse + serialisiert): `ensureBatchPublicCode`
  - [x] Physische Combo / Kiste: bestehende Sonderlogik (nur Batch-QR, kein Material-QR)

- [x] **2.2** Beim **Anlegen** neuer Chargen (`add batch`): immer `ensureBatchPublicCode` (nicht nur bei `serialized`)

- [x] **2.3** `serializeMaterial`:
  - [x] `batches[].public_url` = kanonisch `/i/m/…/b/…` (Fallback `/i/b/…` bei phys. Combo)
  - [x] `material.public_url` = `null` — **kein** `/i/m/` allein für Druck

- [x] **2.4** Console-Command `app:public-code:regenerate-material-batch`:
  - [x] Option `--department-id=` (optional, sonst alle)
  - [x] Option `--dry-run`
  - [x] Pro Material: Material-Code sicherstellen; pro Batch: Batch-Code sicherstellen
  - [x] Report: Anzahl Material/Batch, Fehler (Charge ohne Code)
  - [x] **Keine** `public_code`-Strings löschen — nur fehlende ergänzen

- [x] **2.5** Migration lokal (Docker): Dry-Run → Live — Mai 2026

**Akzeptanz:** API Material-Detail: jede Batch-Zeile hat `public_url` mit `/i/m/…/b/…`; kein Druck-Link nur mit `/i/m/`.

---

## Phase 3 — Material: Frontend & öffentliche Seite

**Ziel:** UI und `qr.` zeigen nur neues Schema; alte `/i/b/` weiter auflösbar.

### Aufgaben

- [x] **3.1** Router:
  - [x] Route `/i/m/:matCode/b/:batchCode` → `PublicMaterialView`
  - [x] `/i/b/:batchCode` → gleiche öffentliche Darstellung (Kurzform)
  - [x] `/i/m/:code` ohne `/b/` → Hinweis „Charge erforderlich“

- [x] **3.2** `PublicMaterialView`: Daten von Lookup `m/…/b/…` oder `b/…` laden

- [x] **3.3** `MaterialDetailView.vue`:
  - [x] Header ohne Material-only-QR; Fokus Chargen (+ QR-Spalte Bestandstabelle)
  - [x] `PublicQrTag` pro Batch mit `batch.public_url`
  - [x] `buildPrintRowsForAllQrs` / Druckkorb: nur Chargen-URLs mit `/b/`
  - [x] QR-Modal: nur Batch / alle Chargen

- [x] **3.4** `PublicQrTag` / Tooltips: URL aus API (`batch.public_url`)

- [x] **3.5** i18n: Texte (de/en) Etikett = Material + Charge

- [x] **3.6** `PublicQrActionModal.vue` (global): Druckkorb, Link öffnen/kopieren, Drucken — genutzt in Material- und Aktivitäts-Detail

**Akzeptanz:** Druck aus Material-Detail zeigt nur URLs mit `/b/` im Pfad; Scan Simulation mit neuer URL öffnet öffentliche Seite.

---

## Phase 4 — Aktivität: `public_code` + QR-Anzeige

**Ziel:** Jede Aktivität kann einen öffentlichen Code; QR in App und auf PDF/Display.

### Aufgaben

- [x] **4.1** Backend `POST /api/activities/{id}/public-code` (analog Material)

- [x] **4.2** Activity-Serialisierung: `public_code`, `public_url` (`buildActivityPublicUrl`)

- [x] **4.3** Nur manuell (kein Auto-ensure bei packing)

- [x] **4.4** `PublicActivityView.vue` + Route `/i/a/:activityCode`

- [x] **4.5** `ActivityDetailView.vue`:
  - [x] `PublicQrTag` im Header wenn `public_url`
  - [x] Button „QR erzeugen“ → API
  - [x] `PublicQrActionModal` (Druckkorb / Link / Druck, `entity_type: activity`)

- [x] **4.6** `frontend/src/api/activities.ts`: `ensureActivityPublicCode(id)`

- [x] **4.7** i18n für Aktivitäts-QR und öffentliche Seite (de/en)

- [x] **4.8** Legacy: Redirect `…/activities/:id/packlist` → `…/packs`

**Akzeptanz:** Aktivität hat QR; Scan `qr…/i/a/…` zeigt öffentliche Kurzseite mit Kontakt.

---

## Phase 5 — Werkstatt-Ticket: `public_code` + QR-Anzeige

**Ziel:** Pro Workshop-Ticket öffentlicher QR; Anzeige in Workshop-UI.

### Aufgaben

- [x] **5.1** Backend `POST /api/workshop/{id}/public-code`

- [x] **5.2** Ticket-Serialisierung: `public_code`, `public_url`

- [x] **5.3** `PublicWorkshopView.vue` + Route `/i/w/:workshopCode`

- [x] **5.4** `WorkshopView.vue`: Ticket-Detail — `PublicQrTag`, „QR erzeugen“, `PublicQrActionModal` (`entity_type: workshop`)

- [x] **5.5** API-Client + i18n (de/en); Kontaktformular `entity_type: workshop`

**Akzeptanz:** Offenes Ticket hat QR; Scan zeigt öffentliche Kurzseite (Materialbezug + Kontakt).

---

## Phase 6 — Abteilungs-Display (Kiosk)

**Ziel:** Infoscreen mit QR auf `qr.` — Zugang per 8-stelligem Code, kein App-Login am Gerät.

### Aufgaben

- [x] **6.1** Route `/display/{publicId}` (öffentlich, PIN + HttpOnly-Cookie)

- [x] **6.2** `DepartmentDisplayView.vue` + `GET /api/public/display/…`

- [x] **6.3** Einstellungen → Infoscreens (nach Join-Code); Sidebar/Dashboard → Einstellungen

- [x] **6.4** Entity `department_display_screen`, Migration, 8-Zeichen-Code

- [x] **6.5** Alte Route `/:departmentId/display` entfernt (kein Redirect)

**Akzeptanz:** Lesezeichen `app.…/display/{publicId}` + PIN; QR scannen → öffentliche Aktivitäts-/Werkstatt-Seite.

---

## Phase 7 — Querschnitt & Deploy

### Aufgaben

- [ ] **7.1** `PrintTaskItem` / `TasksPrintView`: `entity_type` Werte dokumentieren (`material`/`batch`/`activity`/`workshop`); nur gültige `public_url` aus Phase 1–5

- [ ] **7.2** `PublicFoundItemContactService` / Mail-Templates: URLs aus neuem Builder (falls noch `buildMaterialPublicUrl`/`buildBatchPublicUrl` einzeln)

- [ ] **7.3** CORS / `VITE_QR_PUBLIC_HOST`: unverändert prüfen

- [ ] **7.4** Doku aktualisieren: [link-schema.md](../qr/link-schema.md) Status „umgesetzt“ pro Punkt

- [ ] **7.5** Deploy-Reihenfolge:
  1. Backend Phase 1+2 deployen
  2. `app:public-code:regenerate-material-batch` (Prod: Dry-Run zuerst)
  3. Frontend Phase 3–6 deployen

- [ ] **7.6** Kommunikation: **Neue Aufkleber** für Material mit `/i/m/…/b/…`; alte `/i/b/`-Only-Etiketten funktionieren weiter zum Lesen

---

## Was bewusst **nicht** gemacht wird

| Punkt | Grund |
|--------|--------|
| Alte `public_code`-Werte löschen | Codes bleiben stabil; nur URL-Builder ändert sich |
| `devices.ematchef.ch` | eigenes Arbeitspaket |
| Interne Pack-Logik / Scan +1 | nach QR-URLs |
| Material-Etikett nur `/i/m/` | laut Spec ungültig |

---

## Aufwand (grob)

| Phase | Größe | Priorität |
|-------|--------|-----------|
| 1 Backend Builder/Lookup | S | P0 |
| 2 Material Migration | M | P0 |
| 3 Material Frontend + öffentlich | M | P0 |
| 4 Aktivität | M | P1 |
| 5 Werkstatt | M | P1 |
| 6 Display | M | P2 |
| 7 Querschnitt/Deploy | S | laufend |

---

## Checkliste „Release bereit“

- [ ] Alle aktiven Chargen haben `public_code` und kanonische `public_url`
- [ ] Material-Druck nur noch `…/i/m/…/b/…`
- [ ] Aktivität + Werkstatt: QR erzeugen + öffentliche Seite
- [ ] Display zeigt QR für Anlässe und Reparaturen
- [ ] Regenerate-Command auf Prod gelaufen (Log archiviert)
- [ ] Doku `docs/qr/` aktualisiert

---

## Siehe auch

- [docs/qr/link-schema.md](../qr/link-schema.md)
- [docs/qr/qr-public-pages.md](../qr/qr-public-pages.md)
- [docs/devices/README.md](../devices/README.md)
- [deploy/CROSS-SUBDOMAIN-LOGIN.md](../../deploy/CROSS-SUBDOMAIN-LOGIN.md)
