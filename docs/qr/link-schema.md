# QR-Linkschema (`qr.ematchef.ch`)

Zentrale Regeln für öffentliche QR-URLs, `public_code` und kurze Scan-Formen. Gilt für Etiketten, PDF, Display-QR und Scanner (Handheld / Pistole).

**Stand:** Mai 2026 · **Status:** Ziel-Spezifikation (Umsetzung im Code schrittweise)

Verwandte Doku: [Öffentliche QR-Seiten](./qr-public-pages.md) · Lager-Geräte: [../devices/README.md](../devices/README.md)

---

## Grundprinzipien

| Regel | Bedeutung |
|--------|-----------|
| **Immer `public_code`** | In QR-URLs keine internen UUIDs — nur öffentliche Codes aus der Tabelle `public_code` (bzw. künftig gleiches Muster für Aktivität/Werkstatt). |
| **Material immer mit Charge** | Physische Material-Etiketten und kanonische URLs enthalten **immer** Batch/Serie (`/b/{batchCode}`). Nur Material ohne Charge ist **ungültig** für Scan/Druck. |
| **`qr.ematchef.ch` nur öffentlich** | Subdomain zeigt schlanke Infos + Abteilungs-Kontakt. Kein Packen, keine Packliste, kein Workshop-Editor auf `qr.`. |
| **Kurz-URL für Scanner** | `/i/b/{batchCode}` — schneller für Pistole/Handheld; liefert dieselbe Auflösung wie die lange Form. |
| **Interne Tiefe** | Bearbeitung über `app.ematchef.ch` und `devices.ematchef.ch` (nach Login), nicht über erweiterte „interne“ Seiten auf `qr.`. |

Basis-URL Produktion: `https://qr.ematchef.ch` (lokal z. B. `http://qr.ematchef.test`). Konfiguration Backend: `APP_PUBLIC_QR_URL` → `PublicCodeService`.

---

## URL-Pfade auf `qr.ematchef.ch`

### Material + Charge (Pflicht am Etikett)

**Kanonisch (Druck, Aufkleber, PDF):**

```text
https://qr.ematchef.ch/i/m/{materialCode}/b/{batchCode}
```

| Segment | `public_code` für |
|---------|-------------------|
| `{materialCode}` | `entity_type = material` |
| `{batchCode}` | `entity_type = batch` (Masse-Einkauf **oder** serialisierte Einheit mit Seriennummer) |

**Kurzform (Scanner, intern schneller):**

```text
https://qr.ematchef.ch/i/b/{batchCode}
```

`{batchCode}` ist systemweit eindeutig → Resolver liefert Material + Charge. Parser (Wedge, `devices.`) akzeptiert **beide** Formen.

**Legacy:** Bestehende Etiketten nur mit `/i/b/{batchCode}` bleiben gültig. Etiketten nur mit `/i/m/{materialCode}` **ohne** `/b/…` sollen perspektivisch nicht mehr neu gedruckt werden.

### Aktivität

```text
https://qr.ematchef.ch/i/a/{activityCode}
```

Ein Code pro Anlass (Camp, Event, …). Für Packplan-PDF und QR auf dem Abteilungs-Display.

### Werkstatt / Reparatur

```text
https://qr.ematchef.ch/i/w/{workshopCode}
```

Ein Code pro Werkstatt-Ticket. Für Reparaturlisten-PDF und Display.

### Nicht auf `qr.`

- Abteilungs-Infoscreen (`app.ematchef.ch/display/{publicId}`, PIN + Cookie)
- Packen, Inventur, volle Material-Detailseite
- Login-pflichtige Workflows

---

## Serien vs. Masse — ein Schema

Im Datenmodell ist eine **Seriennummer eine Charge** (`material_batch` mit `serial_number`).

| Fall | `{batchCode}` | Beispiel-URL |
|------|---------------|--------------|
| Masse, Einkauf März | Charge A | `…/i/m/{mat}/b/{codeA}` |
| Masse, Einkauf November | Charge B | `…/i/m/{mat}/b/{codeB}` |
| Serialisiert, SN-0042 | Charge mit SN | `…/i/m/{mat}/b/{codeSN42}` |

**Regel:** Trennung der Einkäufe oder Einheiten = **eigener** `{batchCode}`, gemeinsamer `{materialCode}` im langen Pfad.

---

## Auflösung (Resolver)

### Material / Batch

Heute implementiert über `PublicCodeService`:

- `resolveMaterialByPublicCode` → `/api/public/lookup/m/{code}`
- `resolveBatchByPublicCode` → `/api/public/lookup/b/{code}`

**Ziel (Scan-Pipeline):**

1. Scan-String parsen (URL oder Rohcode).
2. Wenn `/i/m/…/b/…` → beide Codes validieren (Batch gehört zum Material).
3. Wenn nur `/i/b/…` → Batch inkl. Material laden.
4. Wenn nur Material-Teil ohne Batch → **Fehler** („Charge/Serie erforderlich“).

Geplant: einheitlicher Endpoint z. B. `GET /api/public/lookup/resolve?raw=…` für Wedge und `devices.`.

### Aktivität / Werkstatt

**Ziel:** `public_code` pro Aktivität bzw. Workshop-Ticket (analog `PublicCode`), URLs wie oben. Noch nicht im Stand Mai 2026 — siehe [qr-public-pages.md](./qr-public-pages.md).

---

## Interne Ziele nach Scan

| Scan (öffentliche URL) | Öffentlich (`qr.`) | Eingeloggt / Lager |
|------------------------|-------------------|---------------------|
| `…/i/m/…/b/…` oder `…/i/b/…` | Material + Charge kurz + Kontakt | `app.…/materials/…` · Packen auf `devices.…` |
| `…/i/a/…` | Anlass kurz + Kontakt | `app.…/activities/…` · `devices.…/pack/…` |
| `…/i/w/…` | Reparatur kurz + Kontakt | `app.…/workshop` (Ticket fokussieren) |

Optional auf der öffentlichen Seite: Links „In App öffnen“ / „Lager (devices)“ — ohne sensible Daten auf `qr.`.

---

## `devices.ematchef.ch`

Eigene Subdomain für Lager (Handheld, Pistolen-Scanner). **Keine** eigenen Material-QR auf Etiketten — Scanner tippt URLs von `qr.…`.

- Parser: dieselben `/i/…`-Pfade und `public_code`.
- Fokus: verstecktes Scan-Input, Pack-Session, keine Navigation zur öffentlichen Seite bei Wedge-Scan.
- Details: [../devices/README.md](../devices/README.md)

---

## `app.ematchef.ch/display/{publicId}`

**Kiosk-Infoscreen** (Zugangscode, kein User-Login). Zeigt anstehende Anlässe, Werkstatt, etc. **mit QR**, die auf `qr.ematchef.ch` zeigen — Nutzer scannen mit dem Handy und sehen die **öffentliche** Kurzseite.

```text
Display (app, PIN)               Handy-Scan
─────────────────────────          ───────────
Sommerlager 2026  [QR]      →      qr…/i/a/{activityCode}
Reparatur Zelt    [QR]      →      qr…/i/w/{workshopCode}
```

---

## Druck & PDF

- **Material-Etikett:** immer lange Form `…/i/m/{mat}/b/{batch}`.
- **Aktivität / Werkstatt-PDF:** `…/i/a/…` bzw. `…/i/w/…`.
- **Druckkorb:** `PrintTaskItem` mit `public_url` + `public_code` (`TasksPrintView`) — `entity_type` frei wählbar, URL muss zum Schema passen.

---

## Code-Referenz (Ist-Stand)

| Bereich | Datei |
|---------|--------|
| URL Material/Batch (aktuell getrennt) | `backend/src/Service/Public/PublicCodeService.php` — `buildMaterialPublicUrl`, `buildBatchPublicUrl` |
| Öffentliche API | `backend/src/Controller/Public/PublicLookupController.php` |
| Frontend Route `/i/:type/:code` | `frontend/src/router/index.ts` |
| Öffentliche Material-Seite | `frontend/src/views/public/PublicMaterialView.vue` |
| QR-Host-Redirects | `frontend/src/router/index.ts` — `applyQrHostRedirects` |

**Geplante Anpassungen:** hierarchische URL `buildMaterialBatchPublicUrl`, Batch-QR auch für Massen-Chargen, Routen `/i/m/:mat/b/:batch`, Aktivität/Werkstatt-Codes, Redirect `packlist` → `/i/a/…`.

---

## Kurz-Checkliste Implementierung

- [ ] `ensureBatchPublicCode` für alle relevanten Massen-Chargen (Einkauf = eigener Code)
- [ ] Kanonische URL `…/i/m/{mat}/b/{batch}` im URL-Builder und Druck
- [ ] Route + Resolver für lange Material-Batch-URL
- [ ] Scan-Parser: Batch Pflicht; Kurzform `/i/b/`
- [ ] `public_code` für Aktivität (`/i/a/`) und Werkstatt (`/i/w/`)
- [ ] Öffentliche Seiten laut [qr-public-pages.md](./qr-public-pages.md)
- [x] `app.…/display/{publicId}` (Kiosk)
- [ ] `devices.ematchef.ch` (Lager)
