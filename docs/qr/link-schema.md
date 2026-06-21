# QR-Linkschema (`qr.ematchef.ch`)

Zentrale Regeln für öffentliche QR-URLs, `public_code` und Scan-Auflösung. Gilt für Etiketten, PDF, Display-QR und später Scanner (Handheld / Pistole).

**Stand:** Mai 2026 · **Status:** Umgesetzt (Material, Aktivität, Werkstatt, Display)

Verwandte Doku: [Öffentliche QR-Seiten](./qr-public-pages.md) · [Scan- & URL-History](./scan-and-url-history.md) · Lager-Geräte: [../devices/README.md](../devices/README.md)

---

## Grundprinzipien

| Regel | Bedeutung |
|--------|-----------|
| **Immer `public_code`** | In QR-URLs keine internen UUIDs — nur öffentliche Codes aus `public_code`. |
| **Material immer mit Charge** | Etiketten und kanonische URLs enthalten **immer** Batch/Serie (`/b/{batchCode}`). Nur Material ohne Charge ist **ungültig**. |
| **`qr.ematchef.ch` nur öffentlich** | Schlanke Infos + Abteilungs-Kontakt. Kein Packen, kein Editor auf `qr.`. |
| **Keine `/i/b/`-Only-URLs** | Alte Etiketten nur mit `/i/b/{batchCode}` werden **nicht** mehr unterstützt — neue Aufkleber mit `/i/m/…/b/…`. |
| **Interne Tiefe** | Bearbeitung über `app.ematchef.ch` und `devices.ematchef.ch` (nach Login). |

Basis-URL Produktion: `https://qr.ematchef.ch` (lokal z. B. `http://qr.ematchef.test`). Backend: `APP_PUBLIC_QR_URL` → `PublicCodeService`.

---

## URL-Pfade auf `qr.ematchef.ch`

### Material + Charge (Pflicht am Etikett)

**Kanonisch (Druck, Aufkleber, PDF, Display):**

```text
https://qr.ematchef.ch/i/m/{materialCode}/b/{batchCode}
```

| Segment | `public_code` für |
|---------|-------------------|
| `{materialCode}` | `entity_type = material` |
| `{batchCode}` | `entity_type = batch` |

Etiketten nur mit `/i/m/{materialCode}` **ohne** `/b/…` oder nur `/i/b/{batchCode}` → **ungültig** (404 / Hinweis „Charge erforderlich“).

### Aktivität

```text
https://qr.ematchef.ch/i/a/{activityCode}
```

### Werkstatt / Reparatur

```text
https://qr.ematchef.ch/i/w/{workshopCode}
```

### Nicht auf `qr.`

- Abteilungs-Infoscreen (`app.ematchef.ch/display/{publicId}`, PIN + Cookie)
- Packen, Inventur, volle Material-Detailseite
- **Lager-QR** (Standort/Regal/Fach) — nur auf `app.` — siehe [storage-location-qr.md](./storage-location-qr.md)

### Intern: Lager (nur `app.ematchef.ch`)

```text
https://app.ematchef.ch/i/l/{locationCode}     Lagerstandort
https://app.ematchef.ch/i/r/{rackCode}         Regal
https://app.ematchef.ch/i/s/{slotCode}         Fach
```

`is_public = false` in `public_code` — kein öffentlicher Finder.

---

## Serien vs. Masse — ein Schema

| Fall | `{batchCode}` |
|------|---------------|
| Masse, Einkauf März | Charge A |
| Masse, Einkauf November | Charge B |
| Serialisiert, SN-0042 | Charge mit SN |

---

## Auflösung (Resolver)

| API | Route |
|-----|--------|
| Material + Charge | `GET /api/public/lookup/m/{materialCode}/b/{batchCode}` |
| Aktivität | `GET /api/public/lookup/a/{code}` |
| Werkstatt | `GET /api/public/lookup/w/{code}` |

Frontend-Routen: `/i/m/:matCode/b/:batchCode`, `/i/a/:activityCode`, `/i/w/:workshopCode`.

Legacy `/i/m/:code` ohne Batch → Fehlerseite. **`/i/b/:code` entfernt.**

`resolveBatchByPublicCode` bleibt intern (z. B. Finder-Kontakt mit `entity_type=batch`), ohne öffentliche Kurz-URL.

---

## Interne Ziele nach Scan

| Scan | Öffentlich (`qr.`) | Eingeloggt |
|------|-------------------|------------|
| `…/i/m/…/b/…` | Material + Charge + Kontakt | `app.…/materials/…` |
| `…/i/a/…` | Anlass kurz + Kontakt | `app.…/activities/…` |
| `…/i/w/…` | Reparatur kurz + Kontakt | `app.…/workshop` |

---

## Druck & Druckkorb

- **Material:** immer `…/i/m/{mat}/b/{batch}`.
- **Aktivität / Werkstatt:** `…/i/a/…` bzw. `…/i/w/…`.
- **Druckkorb** (`PrintTaskItem` / `TasksPrintView`):
  - `entity_type`: `batch` | `activity` | `workshop`
  - `public_url` muss zum Schema passen (Backend-Validierung)

---

## Code-Referenz

| Bereich | Datei |
|---------|--------|
| URL-Builder | `backend/src/Service/Public/PublicCodeService.php` |
| Öffentliche API | `backend/src/Controller/Public/PublicLookupController.php` |
| Druckkorb | `backend/src/Controller/PrintTaskController.php`, `PrintTaskItem` |
| Frontend-Routen | `frontend/src/router/index.ts` |
| Öffentliche Seiten | `frontend/src/views/public/PublicMaterialView.vue`, `PublicActivityView.vue`, `PublicWorkshopView.vue` |
| Druckkorb UI | `frontend/src/views/TasksPrintView.vue` |
| URL-Hilfen | `frontend/src/utils/publicQrUrl.ts` |

---

## Checkliste (Stand)

- [x] Kanonische URL `…/i/m/{mat}/b/{batch}` im Builder und Druck
- [x] Route + Resolver für Material-Batch-URL
- [x] Batch Pflicht; keine `/i/b/`-Only-Route
- [x] `public_code` für Aktivität und Werkstatt
- [x] Öffentliche Seiten Material / Aktivität / Werkstatt
- [x] `app.…/display/{publicId}` (Kiosk)
- [ ] `devices.ematchef.ch` (Lager) — [docs/devices/rollout-plan.md](../devices/rollout-plan.md)
