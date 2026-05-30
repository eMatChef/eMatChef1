# Umbauplan: Medien-Uploads (Fotos)

Abarbeitbare Checkliste für zentrale Foto-Speicherung mit **kontextspezifischen APIs**. Das **Warum/Zielmodell** steht in [README.md](./README.md). Dieser Plan = **Was & in welcher Reihenfolge**.

**Stand:** Mai 2026 · **Erledigt:** Paket 0 + 1 (Foundation + Werkstatt-Migration). **Als Nächstes:** Paket 2 (Schaden melden) — oder Paket 6 vorher, wenn `PhotoUpload` für Paket 2 gewünscht.

---

## Leitprinzipien

- **Ein `MediaStorageService`**, aber **kein** generischer Upload-Endpoint — Routes bleiben pro Domäne (Retention, Rechte, URLs).
- **`var/uploads/`** — nicht in Git; Ordnerstruktur **pro Kontext** (`workshop/`, `issues/`, `material/`).
- **Einheitliches Foto-JSON** in allen Entitäten; Legacy-URL-Strings (`legacy: true`) weiter lesen.
- **Kompression beim Upload** — max. 1920 px, WebP/JPEG ~85 %.
- **Retention nur wo sinnvoll** — abgeschlossene Werkstatt-Tickets nach X Jahren (Default 3).
- **Frontend wiederverwendbar** — `PhotoUpload` + `PhotoGallery`; Eintrag in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md).
- **Jedes Paket ist in einem Chat erledigbar** — Build grün, keine halben Migrationen.
- **Übersetzung** — nur `de.json` und `en.json`.

## Status-Legende

`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt

---

## Übersicht

| # | Paket | Größe | Hängt ab von | Status |
|---|-------|-------|--------------|--------|
| 0 | Foundation: `MediaStorageService` + Kompression | M | – | [x] |
| 1 | Werkstatt: Prototyp migrieren + Pfad `{departmentId}/{ticketId}` | M | 0 | [x] |
| 2 | Schaden melden: Issue-Report-Fotos | M | 0 | [x] |
| 3 | Werkstatt MW: Upload in `WorkshopView` | S–M | 1 | [x] |
| 4 | Material-Abbildung | M | 0 | [ ] |
| 5 | Retention + optional Legacy-Kompression | M | 1 | [ ] |
| 6 | Frontend: `PhotoUpload` / `PhotoGallery` / `useMediaUpload` | M | 1 | [ ] |

> **Reihenfolge:** 0 → 1 → (2 ∥ 3 ∥ 4) → 5; Paket 6 kann parallel zu 2–4 starten, sobald Paket 1 die API stabilisiert.

---

## Zentrale Steuerstellen

**Backend (neu / umbenennen)**
- `backend/src/Service/Media/MediaStorageService.php` — Speichern, Pfade, Kompression, Löschen
- `backend/src/Service/Media/MediaCompressionService.php` — Resize, WebP/JPEG
- `backend/src/Service/Media/MediaRetentionService.php` — Aufräumen abgeschlossener Tickets
- `backend/src/Service/Media/MediaPhotoNormalizer.php` — JSON ein/aus, Legacy-URLs
- `backend/src/Command/MediaRetentionCommand.php` — Cron: `app:media:retention`

**Backend (bestehend → migrieren)**
- `WorkshopPhotoStorageService.php` → delegiert an `MediaStorageService`
- `WorkshopPhotoAccessService.php` → bleibt, ruft ggf. `MediaAccess`-Interface
- `SupplierRepairController.php` — Upload/Download unverändert von aussen, innen neuer Service
- `WorkshopPhotoController.php` — Download MW
- `ActivityWorkflowController.php` — Issue-Create + Foto
- `WorkshopController.php` — PATCH `photos`, Serialisierung
- `MaterialController.php` — Material-Foto (neu)

**Frontend**
- `frontend/src/components/media/PhotoUpload.vue` (neu)
- `frontend/src/components/media/PhotoGallery.vue` (neu)
- `frontend/src/composables/useMediaUpload.ts` (neu)
- `frontend/src/api/media.ts` — Typen `MediaPhoto`, Upload-Helfer
- `DamageReportWizard.vue` — Foto bei Schaden melden
- `WorkshopView.vue` — MW-Fotos am Ticket
- `SupplierRepairsView.vue` — auf Bausteine umstellen
- `MaterialDetailView.vue` — Abbildung uploaden

**Entitäten / Migrationen**
- `WorkshopTicket.photos` — bleibt JSON, Shape vereinheitlichen
- `ActivityIssueReport` — Migration: `photo_url` → `photos` JSON (optional Paket 2)
- `MaterialItem` — Migration: `photos` JSON oder `primary_photo` (Paket 4)

---

## Paket 0 — Foundation: `MediaStorageService` + Kompression

**Ziel:** Gemeinsame Logik extrahieren; noch keine neuen UI-Stellen.

**Schritte:**
- [x] `MediaStorageService`: `store(context, contextId, departmentId, user, file)`, `resolvePath`, `deleteContextFolder`, `buildFilename`
- [x] Kontext-Konstanten: `workshop_ticket`, `issue_report`, `material_item`
- [x] `MediaCompressionService`: max 1920 px, MIME-Whitelist (jpeg/png/webp/gif), max 10 MB roh
- [x] Einheitliches Return-Array (Foto-JSON, siehe [README §2.3](./README.md#23-einheitliches-foto-json-metadaten))
- [x] Unit-Tests: Pfad-Sanitisierung, Kompression Mock (`tests/Service/Media/MediaStorageServiceTest.php`; PHPUnit noch nicht in composer)
- [x] `var/app/media_settings.json` Schema dokumentieren (Retention-Jahre, Kompression an/aus) — `MediaSettingsStore`

**Definition of Done:** Service isoliert testbar; kein Verhalten ändert sich in Prod bis Paket 1.

---

## Paket 1 — Werkstatt: Prototyp migrieren

**Ziel:** `WorkshopPhotoStorageService` durch `MediaStorageService` ersetzen; Pfad `{departmentId}/{ticketId}`.

**Schritte:**
- [x] Pfade: `var/uploads/workshop/{departmentId}/{ticketId}/` (statt `supplier/{companyId}/…`)
- [x] Metadaten: `context`, `context_id`, `department_id`; optional `uploaded_by_supplier_company_id`
- [x] Fallback-Lesen alter Pfade `workshop/supplier/{companyId}/{ticketId}/` für bestehende Uploads
- [x] `WorkshopPhotoAccessService` + Controller anpassen
- [x] `SupplierRepairTicketService` — `addPhoto` nutzt `MediaStorageService`
- [x] Serialisierung: einheitliches Foto-JSON in API-Responses (`MediaPhotoNormalizer` in Supplier + Workshop)
- [ ] Manuell testen: Lieferant upload → MW sieht Foto → Download mit Cookie

**Definition of Done:** Paket-14-Verhalten unverändert; neue Uploads unter department-Pfad; Build grün.

---

## Paket 2 — Schaden melden: Issue-Report-Fotos

**Ziel:** Foto beim Schaden melden; Lieferant sieht «Fotos der Meldung» (supplier-portal.md).

**Schritte:**
- [x] Migration (optional): `activity_issue_report.photos` JSON; `photo_url` deprecate/migrieren (Dual-read)
- [x] `POST /api/activities/{activityId}/issues/{issueId}/photos`
- [x] `GET …/issues/{issueId}/photos/{filename}`
- [x] `ActivityWorkflowController::createIssue` — ohne URL-Pflicht; Upload separat nach Create
- [x] Auto-Workshop-Ticket: `issue_report.photos` in Ticket-Detail und Lieferanten-Reparatur sichtbar
- [x] `DamageReportWizard.vue`: Foto-Upload (max. 3, `<input type="file">` bis Paket 6)
- [x] i18n de/en
- [ ] Manuell testen: Schaden mit Foto → MW-Ticket + Lieferanten-Reparatur

**Definition of Done:** Schaden mit Foto melden → Foto in Werkstatt-Ticket-Detail und Lieferanten-Reparatur sichtbar.

---

## Paket 3 — Werkstatt MW: Upload in `WorkshopView`

**Ziel:** Materialwart kann Fotos am Ticket ergänzen (nicht nur URL-PATCH).

**Schritte:**
- [x] `POST /api/workshop/tickets/{id}/photos` (MW)
- [x] `WorkshopView.vue` — Galerie + Upload im Ticket-Detail
- [x] Berechtigung: Department-Mitglied `mw`/`dc` (+ matwart/depchef, Superadmin)
- [x] i18n de/en
- [ ] Manuell testen: MW upload → Galerie → Download

**Definition of Done:** MW upload ohne Lieferanten-Kontext; gleiches Foto-JSON wie Lieferant.

---

## Paket 4 — Material-Abbildung

**Ziel:** Produktfoto am Material (Sidebar in `MaterialDetailView`).

**Schritte:**
- [ ] Migration: `material_item.photos` JSON oder dediziertes `primary_photo` + Galerie — **Entscheid vor Start:** ein Hauptbild vs. Galerie
- [ ] `POST/GET /api/material/{materialId}/photos/…`
- [ ] `MaterialDetailView.vue`: Upload statt Platzhalter; `image_url` aus erstem Foto ableiten
- [ ] Material löschen → Fotos mitlöschen (`MediaStorageService::deleteContextFolder`)
- [ ] i18n de/en

**Definition of Done:** Abbildung uploaden, in Detail sichtbar, nach Material-Löschung weg.

---

## Paket 5 — Retention + Legacy-Kompression

**Ziel:** Speicherplatz begrenzen; abgeschlossene Reparaturen aufräumen.

**Schritte:**
- [ ] `MediaRetentionService`: Tickets `completed` + `completed_at` älter als X Jahre
- [ ] Command `app:media:retention` (--dry-run, --years=3)
- [ ] Cron-Doku in `deploy/SERVER-UPDATE.md`
- [ ] Optional: `app:media:compress-legacy` für Dateien ohne `bytes`-Metadaten
- [ ] Logging + Zähler (gelöschte Dateien, freigegebene MB)

**Definition of Done:** Dry-run listet betroffene Tickets; Live-Lauf löscht Ordner + leert `photos` JSON.

---

## Paket 6 — Frontend: Wiederverwendbare Bausteine

**Ziel:** Kein copy-paste `<input type="file">` mehr.

**Schritte:**
- [ ] `PhotoUpload.vue` — Props: `uploadUrl`, `accept`, `maxSize`, `disabled`; Events: `@uploaded`, `@error`
- [ ] `PhotoGallery.vue` — Props: `photos: MediaPhoto[]`, `readonly`
- [ ] `useMediaUpload.ts` — FormData, axios ohne JSON-Content-Type
- [ ] `frontend/src/api/media.ts` — Typ `MediaPhoto`
- [ ] `SupplierRepairsView.vue` refactoren
- [ ] Eintrag in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md)
- [ ] i18n: `media.upload`, `media.uploadError`, `media.uploadSuccess`, `media.tooLarge`, …

**Definition of Done:** Mindestens zwei Views nutzen dieselben Komponenten; `vue-tsc` grün.

---

## Offene Entscheidungen (vor dem jeweiligen Paket)

| Thema | Wann klären | Vorschlag |
|-------|-------------|-----------|
| Issue: ein vs. mehrere Fotos | Paket 2 | Max. 3 pro Meldung |
| Material: ein Hauptbild vs. Galerie | Paket 4 | Ein `primary` + optional Galerie später |
| WebP-only vs. JPEG-Fallback | Paket 0 | WebP wenn Imagick/GD; sonst JPEG |
| Retention Jahre global vs. pro Department | Paket 5 | Global 3, später Department-Override |
| Migration `photo_url` → `photos` | Paket 2 | Dual-read eine Phase, dann Spalte droppen |

---

## Siehe auch

- [README.md](./README.md) — Konzept, Ordner, JSON-Shape, Retention
- [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) — Frontend-Bausteine
- [supplier/plan.md](../supplier/plan.md) — Paket 14 Reparaturen
