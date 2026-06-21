# Lager-QR (Standort, Regal, Fach)

Interne QR-Etiketten für Lagerstandorte, Regale und Fächer — **nicht** auf `qr.ematchef.ch`. Scan-Auflösung nur für eingeloggte Nutzer (App / später `devices.`).

**Stand:** Juni 2026 · **Status:** Umgesetzt (Schema, Druckkorb, Settings-Druck); Packflow/Inventur/Suche separat

Verwandt: [link-schema.md](./link-schema.md) · [qr-public-pages.md](./qr-public-pages.md) · [scan-and-url-history.md](./scan-and-url-history.md)

---

## Scope

| In Scope | Out of Scope (eigene Specs) |
|----------|----------------------------|
| URL-Schema + `public_code` (`is_public = false`) | Pack-Journey: Regal-Scan → Filter |
| Druckkorb + Aufgaben-Druck (Sammel / einzeln) | Inventur-Modus |
| Settings: Druck bei Anlage + Bulk-Aktionen | Globale Suche per Regal-Scan |
| Interne Lookup-API + `scanParser` | `devices.ematchef.ch`-UI |

---

## Grundprinzipien

| Regel | Bedeutung |
|-------|-----------|
| **Basis-URL: `app.ematchef.ch`** | Login-Pflicht; Pistole parsed URL im Hintergrund |
| **`public_code` in Tabelle `public_code`** | Wie Material/Aktivität |
| **`is_public = false`** | Kein öffentlicher Finder auf `qr.` |
| **Code bleibt bei Umbenennung** | Etikett am Regal bleibt gültig; nur `label` im Druckjob neu |
| **Code wird bei Löschung revoked** | `is_active = false`, `revoked_at` |

---

## URL-Schema (intern)

Parallele Pfade zu `/i/m/`, `/i/a/`, `/i/w/`:

```text
https://app.ematchef.ch/i/l/{locationCode}     Lagerstandort (Address type=storage)
https://app.ematchef.ch/i/r/{rackCode}         Regal (StorageRack)
https://app.ematchef.ch/i/s/{slotCode}         Fach (StorageSlot)
```

| Segment | `entity_type` in `public_code` | Entity |
|---------|----------------------------------|--------|
| `{locationCode}` | `storage_address` | `Address` (type `storage`) |
| `{rackCode}` | `storage_rack` | `StorageRack` |
| `{slotCode}` | `storage_slot` | `StorageSlot` |

`public_code` ist global eindeutig — flache URLs, kein verschachteltes `/i/l/…/r/…`.

### Etikett-Text (`label` im Druckkorb)

| Typ | Beispiel |
|-----|----------|
| Standort | `Magazin Pfadihaus` |
| Regal | `Magazin · Regal B3` |
| Fach | `Magazin · B3 · Fach A` |

---

## API

### Code sicherstellen

```text
POST /api/storage-qr/addresses/{id}/ensure
POST /api/storage-qr/racks/{id}/ensure
POST /api/storage-qr/slots/{id}/ensure
```

Antwort: `{ public_code, public_url, label, entity_type, entity_id }`

### Druckkorb befüllen

```text
POST /api/storage-qr/queue-print
```

Body:

```json
{
  "department_id": "…",
  "scope": "all | address | rack | slot",
  "address_id": "…",
  "rack_id": "…",
  "slot_id": "…"
}
```

| `scope` | Druckjobs für |
|---------|---------------|
| `all` | alle Standorte + Regale + Fächer |
| `address` | Standort + Regale/Fächer darunter |
| `rack` | Regal + alle Fächer |
| `slot` | ein Fach |

Deduplizierung: pending-Einträge pro `(entity_type, entity_id)` werden übersprungen.

### PDF pro Standort (mit Auswahl)

```text
POST /api/storage-qr/pdf
```

Body:

```json
{
  "department_id": "…",
  "address_id": "…",
  "selections": [
    { "entity_type": "storage_address", "entity_id": "…" },
    { "entity_type": "storage_rack", "entity_id": "…" },
    { "entity_type": "storage_slot", "entity_id": "…" }
  ]
}
```

Antwort: `application/pdf` — A4-Raster 3×4 (12 Etiketten/Seite), gleiche Engine wie Material-QR-PDF.

UI: **Settings → Regale & Fächer** → Standort → Icon «PDF mit Auswahl…» → Baum mit Checkboxen.

Gleicher Dialog unter **Aufgaben → Drucken** → «PDF: Lager-QR…» (dort mit Standort-Dropdown).

---

```text
GET /api/storage-qr/lookup/l/{code}
GET /api/storage-qr/lookup/r/{code}
GET /api/storage-qr/lookup/s/{code}
```

Nur für eingeloggte Department-Mitglieder. Packflow/Inventur/Suche nutzen diese API später.

---

## Druckkorb (`PrintTaskItem`)

Neue `entity_type`-Werte:

| `entity_type` | `public_url`-Pattern |
|---------------|---------------------|
| `storage_address` | `^/i/l/[^/]+/?$` |
| `storage_rack` | `^/i/r/[^/]+/?$` |
| `storage_slot` | `^/i/s/[^/]+/?$` |

---

## UI (Settings → Regale & Fächer)

| Aktion | Ort |
|--------|-----|
| **Alle QR drucken** | Seiten-Header |
| **Standort-QR** | Location-Header |
| **Regal + Fächer** | Regal-Aktionen |
| **Einzelnes Fach** | Fach-Zeile |
| **Beim Anlegen in Druckkorb** | Checkbox (Default an) |

Nach Umbenennung: manuell erneut drucken (gleicher Code, neues Label).

---

## Scan-Parser (`scanParser.ts`)

Zusätzliche Typen:

- `storage_address` — Pfad `/i/l/{code}`
- `storage_rack` — `/i/r/{code}`
- `storage_slot` — `/i/s/{code}`

---

## Implementierungsphasen

| Phase | Deliverable | Status |
|-------|-------------|--------|
| **LQ1** | `PublicCodeService`, ensure-Endpoints, URL-Builder | ✓ |
| **LQ2** | `PrintTaskController`, `TasksPrintView`, i18n | ✓ |
| **LQ3** | StorageSettings: Bulk-Druck + Checkbox bei Anlage | ✓ |
| **LQ4** | Onboarding-Wizard Auto-Druckjob | offen |
| **LQ5** | Lookup-API + Parser (ohne Pack-UI) | ✓ |
| **LQ6** | PDF-Export «Lager-Etiketten» pro Standort mit Auswahl | ✓ |

---

## Code-Referenz

| Bereich | Datei |
|---------|--------|
| Service | `backend/src/Service/Storage/StorageQrService.php` |
| Controller | `backend/src/Controller/StorageQrController.php` |
| URL-Builder | `PublicCodeService.php` (interne Lager-URLs) |
| Druck-Validierung | `PrintTaskController.php` |
| Frontend API | `frontend/src/api/storageQr.ts` |
| URL-Hilfen | `frontend/src/utils/internalStorageQrUrl.ts` |
| Parser | `frontend/src/utils/scanParser.ts` |
| Settings-UI | `StorageSettingsView.vue` |
