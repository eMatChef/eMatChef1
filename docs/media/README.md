# Medien-Uploads (Fotos)

Zentrales Modell für Bild-Uploads in eMatChef: **ein gemeinsamer Storage-Service**, **kontextspezifische API-Routes** und **wiederverwendbare Frontend-Bausteine**.

**Stand:** Mai 2026 · **Ist-Zustand:** nur Lieferanten-Reparatur (`WorkshopPhotoStorageService`, Paket 14 Supplier-Portal). Schaden melden, Werkstatt-MW und Material-Abbildung sind vorbereitet (URL-Felder), aber ohne echten Datei-Upload.

**Abarbeitung:** [plan.md](./plan.md)

---

## 1. Problem

Heute existieren **fragmentierte** Foto-Stellen:

| Kontext | DB-Feld | Upload | UI |
|---------|---------|--------|-----|
| Werkstatt-Ticket | `workshop_ticket.photos` (JSON) | Lieferant ✅, MW nur URL-PATCH | Supplier-Portal |
| Schadenmeldung | `activity_issue_report.photo_url` (String) | ❌ | `DamageReportWizard` ohne Foto |
| Material-Abbildung | — (Frontend `image_url` ohne Backend) | ❌ | Platzhalter in `MaterialDetailView` |

Das führt zu doppelter Logik, uneinheitlichen Metadaten (wer/wann) und erschwert **Retention** (alte Reparatur-Fotos löschen) sowie **Kompression**.

---

## 2. Zielmodell

### 2.1 Ein Service, viele Kontexte

```
MediaStorageService          ← Speichern, Pfad, Kompression, Löschen
MediaAccessService           ← Berechtigung je Kontext (delegiert)
MediaRetentionService        ← Aufräumen abgeschlossener Tickets (Cron/Command)
```

**API-Routes bleiben kontextspezifisch** — nicht ein generischer `/api/media/upload`:

| Kontext | Upload | Download |
|---------|--------|----------|
| Werkstatt-Ticket (MW) | `POST /api/workshop/tickets/{id}/photos` | `GET /media/{deptId}/photos/workshop/{ticketId}/{filename}` |
| Werkstatt-Ticket (Lieferant) | `POST /api/supplier-companies/{companyId}/repairs/{ticketId}/photos` | derselbe `/media/…`-Pfad |
| Schadenmeldung | `POST /api/activities/{activityId}/issues/{issueId}/photos` | `GET /media/{deptId}/photos/issues/{issueId}/{filename}` |
| Material | `POST /api/materials/{materialId}/photos` | `GET /media/{deptId}/photos/material/{materialId}/{filename}` |

Vorteil kontextspezifischer Routes: klare Berechtigung, klare Retention-Regeln pro Domäne, keine «God-Endpoint»-Parameter.

### 2.2 Dateisystem (unter `var/uploads/`, gitignored)

Basis wie heute bei Config: `%kernel.project_dir%/var/uploads/` — in Docker über `./backend`-Mount persistent.

```
var/uploads/{departmentId}/
  photos/
    material/{materialId}/
    workshop/{ticketId}/
    issues/{issueId}/
  documents/
    accounting/{bookingId}/          ← Bilder + PDFs derselben Buchung
    accounting-followup/{followUpId}/
    activity-js-order/{orderId}/
    grossanlass-procurement-quote/{quoteId}/
```

**Dateiname (überall gleich):** `{YmdHis}_{userId}_{random8}.{ext}`

- Sortierbar nach Zeit
- Uploader im Dateinamen (zusätzlich Metadaten in DB)
- Keine Sonderzeichen / kein Originalname im Pfad (Originalname nur in JSON)

**Lieferanten-Reparatur:** Fotos liegen unter dem **Ticket-Ordner** (`workshop/{departmentId}/{ticketId}/`), nicht unter `supplier/{companyId}/`. Die Firma steht in den Metadaten (`uploaded_by_supplier_company_id` optional). Retention hängt am Ticket-Lebenszyklus, nicht an der Firma.

> **Migration:** Bestehende Pfade `workshop/supplier/{companyId}/{ticketId}/` (Paket 14) bei Paket 1 auf `workshop/{departmentId}/{ticketId}/` umziehen oder per Fallback lesen.

### 2.3 Einheitliches Foto-JSON (Metadaten)

Überall dasselbe Objekt in JSON-Arrays; einzelne Legacy-URL-Strings weiter unterstützen (`legacy: true`):

```json
{
  "id": "a1b2c3d4e5f67890",
  "filename": "20260530143022_usr_456_a1b2c3d4.jpg",
  "url": "/media/dept_abc/photos/workshop/wt_xyz/20260530143022_usr_456_a1b2c3d4.webp",
  "uploaded_at": "2026-05-30T14:30:22+02:00",
  "uploaded_by_id": "usr_456",
  "uploaded_by_name": "Max Muster",
  "original_filename": "IMG_1234.jpg",
  "context": "workshop_ticket",
  "context_id": "wt_xyz789",
  "bytes": 245760,
  "width": 1920,
  "height": 1080,
  "mime": "image/jpeg"
}
```

`activity_issue_report.photo_url` wird perspektivisch zu **`photos` JSON** (Migration) oder bleibt ein Einzel-Foto mit gleichem Objekt-Shape in einem Array der Länge 1.

### 2.4 Kompression

**Beim Upload (Standard):**

- Max. Kantenlänge 1920 px (Seitenverhältnis beibehalten)
- JPEG Qualität 85 oder WebP 85 (WebP bevorzugt wenn GD/Imagick verfügbar)
- Max. Upload 10 MB roh; nach Kompression typisch 200 KB–1 MB
- GIF: nur speichern wenn Animation, sonst erstes Frame → JPEG

**Optional später (Paket 5):** Console-Command `app:media:compress-legacy` für bestehende Dateien.

### 2.5 Retention (Datenmenge)

| Kontext | Regel (Vorschlag) | Auslöser |
|---------|-------------------|----------|
| `workshop_ticket` | Fotos löschen **X Jahre nach `completed_at`** (Default: 3) | Cron + `MediaRetentionService` |
| `issue_report` | Fotos löschen wenn verknüpftes Ticket + Retention abgelaufen, oder Issue `resolved` + X Jahre | gleicher Job |
| `material_item` | Solange Material existiert; bei Löschung Material → Fotos mitlöschen | Material-Delete-Hook |

Konfiguration: `var/app/media_settings.json` (Superadmin) oder Department-Setting — analog `integration_settings.json`.

Retention-Job:

1. Tickets mit `status=completed` und `completed_at < now - retention`
2. Ordner `workshop/{departmentId}/{ticketId}/` rekursiv löschen
3. `photos` JSON auf `[]` setzen (oder Einträge mit `deleted_at` markieren)
4. Log in `var/log/media_retention.log`

---

## 3. Berechtigungen (Kurz)

| Kontext | Lesen | Schreiben |
|---------|-------|-----------|
| Workshop-Ticket | Department-Mitglied, zugewiesener Lieferant (`repairs`) | MW (`mw`/`dc`), Lieferant (nur zugewiesene Tickets) |
| Issue-Report | Activity-Zugriff, MW | Reporter + MW |
| Material | Department-Mitglied | `canManageMaterials` |

Implementierung: je Kontext ein schlanker `*MediaAccess`-Delegat auf gemeinsame Membership-/Supplier-Checks.

---

## 4. Frontend-Bausteine

Siehe [wiederverwendbare-komponenten.md § Medien/Fotos](../wiederverwendbare-komponenten.md#medien--fotos).

- **`PhotoUpload`** — Datei wählen, Vorschau, Upload, Fehler
- **`PhotoGallery`** — Liste mit Uploader/Zeitstempel, optional Löschen
- **`useMediaUpload`** — FormData, Fortschritt, kontextspezifische URL aus Props

---

## 5. Ist-Zustand (Referenz)

| Datei | Rolle |
|-------|-------|
| `WorkshopPhotoStorageService.php` | Prototyp — wird zu `MediaStorageService` |
| `WorkshopPhotoAccessService.php` | Werkstatt-Berechtigung |
| `SupplierRepairController.php` | Upload/Download Lieferant |
| `WorkshopPhotoController.php` | Download MW |
| `SupplierRepairsView.vue` | Erstes UI mit `<input type="file">` |

---

## 6. Siehe auch

- [mediathek-zukunft.md](./mediathek-zukunft.md) — optionale Department-Mediathek (Phase 2)
- [supplier/plan.md Paket 14](../supplier/plan.md) — Reparaturen Lieferant (Fotos)
- [supplier-portal.md § Datenschutz](../supplier/supplier-portal.md) — Lieferant sieht Schadenfotos der Meldung
- [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) — Frontend-Bausteine
