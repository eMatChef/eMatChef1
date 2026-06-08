# Etiketten- & Plaketten-Fulfillment (Zukunft)

**Status:** Idee / Zukunft — **nicht** im aktuellen MVP.  
**Stand:** Juni 2026

Departments ohne Drucker oder Metall-Laser können QR-Etiketten und Plaketten über ein **Hub-Department** derselben Organisation beziehen. Kein Blanko-QR-Pool — immer **Material + Charge zuerst**, dann Sammelbestellung mit **Druckspezifikation** (Menge, Träger, Format).

**Verwandt:** [QR-Linkschema](../qr/link-schema.md) · [Öffentliche QR-Seiten](../qr/qr-public-pages.md) · Ist: `PrintTaskItem`, `TasksPrintView`, `PrintTaskController`

---

## 1. Problem

| Situation | Heute | Lücke |
|-----------|--------|-------|
| Department mit Etikettendrucker | Druckkorb → Browser-Druck | ✅ |
| Department mit eigenem Laser | Export manuell / extern | ⚠️ kein Profi-Export |
| Department ohne Infrastruktur | — | ❌ |

Viele Pfadi-Strukturen: **Kantonal-/Zentrums-Department** hat Laser, **Gruppen-Departments** nicht.

Der heutige Druckkorb kennt weder **Stückzahl**, noch **Träger** (Etikett vs. Metall), noch **Format/Grösse** — für einen Hub unzureichend.

---

## 2. Zielbild: Hub & Client

```
┌─────────────────────┐         ┌─────────────────────┐
│  Client-Department   │         │   Hub-Department     │
│  (kleine Abteilung)  │         │  (MW-Zentrum, Laser) │
├─────────────────────┤         ├─────────────────────┤
│ Material + Charge   │         │ ☑ Fulfillment-Hub   │
│ QR automatisch      │  Order  │ Format-Katalog      │
│ Druckkorb füllen    │ ──────► │ Inbox: Aufträge     │
│ Spezifikation:      │         │ Sammel-PDF/SVG      │
│ Menge, Format,      │         │ Lasern + versenden  │
│ Metall/Etikett      │         │                     │
└─────────────────────┘         └─────────────────────┘
         │                                   │
         └──── QR/Material bleiben beim Client ────┘
```

**Hub** produziert physisch; **Client** behält Datenhoheit (Material, Charge, `public_code`).

---

## 3. Abgrenzung — was bewusst nicht

| Nicht | Grund |
|-------|--------|
| Blanko-Plaketten / QR-Pool | Charge-first; Masse-Vorrat ≈ faktische Serialisierung |
| Cross-Organisation | Nur gleiche `organisation_id` |
| Material-Zugriff für Hub | Nur Druck-Snapshot (URL, Label, S/N, Spezifikation) |
| Zahlungs-Shop in MVP | Erst Auftrag + Status; Kosten optional als Hinweis |
| URL-Umbau `/i/m/{28}` | Kosmetik; kein Fulfillment-Hebel |

---

## 4. Ist-Stand (Anknüpfung)

| Komponente | Pfad / Entity |
|------------|----------------|
| Batch-QR | `PublicCodeService::ensureBatchPublicCode` |
| Kanonische URL | `/i/m/{materialCode}/b/{batchCode}` — [link-schema.md](../qr/link-schema.md) |
| Druckkorb | `PrintTaskItem`, `PrintTaskController` |
| Druck-UI | `TasksPrintView.vue` (Tab unter `/tasks/`) |
| Settings-Muster | `department_setting`, `DepartmentSettingController` |

Druckkorb ist heute **pro Department isoliert** (`department_id`). `PrintTaskItem` enthält **keine** Felder für Menge, Träger oder Format.

---

## 5. Druckspezifikation pro Zeile

Jede Bestellzeile braucht eine vollständige Spezifikation für den Hub.

### 5.1 Regeln nach Materialtyp

| `tracking_type` | QR-Logik | `print_quantity` |
|-----------------|----------|------------------|
| **serialized** | 1 Charge = 1 Einheit = 1 QR | **Immer 1** (fix, nicht editierbar) |
| **bulk** | 1 Charge = 1 QR für die Charge | **Vom Client** (≥ 1) |
| **activity / workshop** | 1 QR pro Anlass/Ticket | **Immer 1** |

**Hinweis Masse:** Ein QR pro Charge bedeutet nicht automatisch ein Etikett pro physischem Stück. `print_quantity` = wie viele **physische Kopien** desselben QR (Regal, Kiste, Reserve). Braucht jedes Stück einen **eigenen** QR → Einzelchargen à 1 Stk. anlegen (faktisch serialisiert).

**Default bei Masse (Vorschlag):** `print_quantity = 1`; optional Vorschlag `= batch.qty` mit UI-Warnung («100 identische Plaketten mit gleichem QR»).

### 5.2 Träger & Format

| Feld | Werte | Beschreibung |
|------|-------|--------------|
| `carrier` | `label` · `metal_plaque` | Folie/Papier vs. Metallplakette |
| `format_key` | z. B. `label_62x29`, `metal_30x30` | Referenz auf Format-Katalog |
| `format_label` | Anzeige | «Metallplakette 30×30 mm» (Snapshot) |
| `print_quantity` | int ≥ 1 | Anzahl physischer Stücke |

### 5.3 Validierung (Backend)

| Regel | Aktion |
|-------|--------|
| `serialized` | `print_quantity = 1` erzwingen |
| `bulk` | `print_quantity >= 1`; Warnung wenn `> batch.qty` |
| `activity` / `workshop` | `print_quantity = 1` |
| `metal_plaque` | nur wenn Hub `label_fulfillment_laser = true` |
| `format_key` | muss im Hub-Format-Katalog existieren |

---

## 6. Format-Katalog (Hub)

Hub definiert unter Settings, welche Formate er produziert:

```json
general.label_fulfillment_formats = [
  { "key": "label_62x29",  "carrier": "label",        "width_mm": 62,  "height_mm": 29,  "laser": false },
  { "key": "label_100x50", "carrier": "label",        "width_mm": 100, "height_mm": 50,  "laser": false },
  { "key": "metal_30x30",  "carrier": "metal_plaque", "width_mm": 30,  "height_mm": 30,  "laser": true },
  { "key": "metal_40x40",  "carrier": "metal_plaque", "width_mm": 40,  "height_mm": 40,  "laser": true }
]
```

Client wählt beim Senden nur aus Formate des gewählten Hubs. Export (PDF/SVG) nutzt mm-Masse aus dem Katalog.

---

## 7. Department-Settings (geplant)

| `setting_key` | Werte | UI |
|---------------|-------|-----|
| `general.label_fulfillment_hub` | `true` / leer | ☑ Etiketten/Plaketten für andere Departments |
| `general.label_fulfillment_laser` | `true` / leer | Hub: Metall/Plaketten |
| `general.label_fulfillment_labels_only` | `true` / leer | Hub: nur Folienetiketten |
| `general.label_fulfillment_formats` | JSON-Array | Hub: angebotene Formate |
| `general.label_fulfillment_hub_note` | Text | Kontakt, Lieferzeit, Kostenhinweis |
| `general.label_fulfillment_use_external` | `true` / leer | Client: extern beziehen |
| `general.label_fulfillment_preferred_hub_id` | Department-ID | Standard-Hub |

API: `GET/PATCH /api/departments/{id}/settings` (Gruppe `general`).

**Scope:** Nur Departments mit **dieselber `organisation_id`**, Hub-Flag gesetzt.

---

## 8. Datenmodell (geplant)

### Tabelle `label_fulfillment_order`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | CHAR(13) | PK |
| `client_department_id` | CHAR(12) | Besteller |
| `hub_department_id` | CHAR(12) | Produzent |
| `status` | string | siehe unten |
| `created_by_user_id` | CHAR(12) | |
| `assigned_to_user_id` | CHAR(12)? | Hub-Bearbeiter |
| `client_note` | text? | |
| `hub_note` | text? | |
| `delivery_hint` | text? | Abholung / Adresse |
| `default_carrier` | string? | Auftrags-Default |
| `default_format_key` | string? | Auftrags-Default |
| `submitted_at` | datetime | |
| `completed_at` | datetime? | |

**Status:** `submitted` → `accepted` → `in_production` → `shipped` → `completed` | `cancelled`

### Tabelle `label_fulfillment_order_item`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | CHAR(13) | PK |
| `order_id` | FK | |
| `source_print_task_item_id` | CHAR(13)? | optional Referenz Druckkorb |
| `entity_type` | string | `batch` \| `activity` \| `workshop` |
| `entity_id` | string | Referenz Client (Audit) |
| `tracking_type` | string? | `bulk` \| `serialized` (nur batch) |
| `batch_qty` | int? | Bestand der Charge (Info) |
| `label` | string | Anzeige + Plakette |
| `public_code` | string? | |
| `public_url` | string | QR-Inhalt |
| `print_quantity` | int | **Pflicht** — physische Stückzahl |
| `carrier` | string | `label` \| `metal_plaque` |
| `format_key` | string | z. B. `metal_30x30` |
| `format_label` | string | Anzeige-Snapshot |

**Snapshot-Pflicht:** Hub exportiert aus Order-Items, ohne Client-Material-API.

---

## 9. Ablauf

### Client

1. Material + Charge anlegen (QR entsteht automatisch).
2. Einträge in Druckkorb (`TasksPrintView` / Material-Detail).
3. **An Hub senden:** Hub wählen, pro Zeile **Menge, Träger, Format** (Sende-Dialog).
4. Druckkorb-Einträge nach Senden entfernen oder als `submitted` markieren.
5. Status verfolgen; bei `shipped`/`completed` Plaketten montieren.

### Hub

1. Inbox / Tab **Etiketten-Aufträge:** neue Orders der Organisation.
2. Detail: Summe «47 Plaketten, 12 Etiketten» pro Auftrag.
3. Annehmen → `accepted` → `in_production`.
4. **Sammel-Export** (PDF/SVG) mit korrekten mm-Formaten.
5. `shipped` setzen (+ optional Hub-Notiz).
6. Client-Benachrichtigung über Nachrichtenzentrale.

---

## 10. UI (geplant)

### Settings → Mein Department

- Hub: Checkbox + Laser/Etikett + Format-Katalog pflegen + Hinweistext
- Client: Checkbox «extern beziehen» + Standard-Hub

### Tasks → Druck (Client)

- Button **«An Hub senden»** neben Drucken / Alles drucken

### Sende-Dialog (Client)

| Material / Charge | Typ | Charge-Menge | Druck Anzahl | Träger | Format |
|-------------------|-----|--------------|--------------|--------|--------|
| Seil 10mm, Charge März | Masse | 100 | `[ 5 ]` | Etikett ▾ | 62×29 mm ▾ |
| Zelt Spatz SN-042 | Serie | 1 | **1** (fix) | Metall ▾ | 30×30 mm ▾ |

Optional Auftrags-Defaults (Träger/Format für alle Zeilen).

### Tasks → Etiketten-Aufträge (Hub)

- Queue mit Client-Name, Stückzahl-Summe, Status
- Detail, Export, Status ändern

### Inbox

- «Neuer Etiketten-Auftrag von …» (`label_fulfillment_order.submitted`)

---

## 11. API (Skizze)

| Methode | Route | Beschreibung |
|---------|-------|--------------|
| GET | `/api/departments/{id}/label-fulfillment/hubs` | Hub-Liste inkl. Formate |
| POST | `/api/departments/{id}/label-fulfillment/orders` | Auftrag aus Druckkorb-Items + Spezifikation |
| GET | `/api/departments/{id}/label-fulfillment/orders` | Client: eigene; Hub: eingehend |
| GET | `…/orders/{orderId}` | Detail inkl. Items |
| PATCH | `…/orders/{orderId}` | Status, Notizen (Hub) |
| GET | `…/orders/{orderId}/export` | PDF/SVG (Hub), mm aus `format_key` |

---

## 12. Export (Hub)

**Phase A:** PDF mit QR + Label + S/N, mm-genau nach `format_key`, `print_quantity` Kopien pro Zeile.

**Phase B:** SVG/EPS für externe Laserei; Begleit-PDF mit Client-Name, Order-ID, Stückliste.

---

## 13. Berechtigungen

| Aktion | Rolle |
|--------|-------|
| Settings Hub/Client/Formate | MW, DC |
| Auftrag senden | MW, DC (Client) |
| Auftrag bearbeiten / Export | MW, DC (Hub) |
| User (`u`) | Druckkorb lokal, kein Senden |

---

## 14. Umsetzungspakete

| # | Paket | Grösse | Abhängigkeit | Inhalt |
|---|--------|--------|--------------|--------|
| 1 | Settings Hub/Client | S | – | `department_setting`, API, Settings-UI |
| 2 | Format-Katalog Hub | S | 1 | JSON-Formate, Validierung |
| 3 | Hub-Liste API | S | 1, 2 | inkl. Formate pro Hub |
| 4 | Order-Entities + API | M | 1 | submit, list, status, Validierung |
| 5 | Client: Sende-Dialog | M | 3, 4 | Menge, Träger, Format pro Zeile |
| 6 | Hub: Inbox + Status | M | 4 | Tasks-Tab, Summen |
| 7 | Sammel-Export PDF | M | 2, 6 | mm-Layouts |
| 8 | Inbox-Benachrichtigungen | S | 6 | Nachrichtenzentrale |
| 9 | Druckkorb-Felder optional | S | 5 | Menge/Format schon beim Sammeln |
| 10 | SVG-Export, Preishinweis | S | 7 | optional |

**MVP:** Paket 1–7 — senden mit Spezifikation, annehmen, exportieren, abschliessen.

---

## 15. Druckkorb: heute vs. Zukunft

| | Heute | Fulfillment MVP | Optional später |
|--|--------|-----------------|-----------------|
| Spezifikation | — | im Sende-Dialog | in `PrintTaskItem` |
| Menge | — | `print_quantity` auf Order-Item | Default im Korb |
| Format | — | pro Order-Item | Merken pro Department |

**Empfehlung:** Spezifikation zuerst nur im **Sende-Dialog** (Paket 5); Druckkorb bleibt schlank.

---

## 16. Offene Punkte

| # | Thema | Tendenz |
|---|--------|---------|
| 1 | Mehrere Hubs pro Organisation | Ja, Client wählt |
| 2 | Activity/Workshop-QRs | Ja, gleicher Order-Flow, `print_quantity = 1` |
| 3 | Masse-Default `print_quantity` | Default 1; optional Vorschlag `batch.qty` mit Warnung |
| 4 | Abrechnung | Später; MVP: Freitext im Hub |
| 5 | eMatChef als zentraler Laser-Partner | Eigenes Produkt, nicht Phase 1 |
| 6 | Druckkorb nach Senden | Items aus `pending` entfernen, Order behält Snapshot |

---

## 17. Vergleich Branche

Typische Inventory-Tools (Snipe-IT, Cheqroom): **Catalog-first + Sammeldruck**, kein integrierter Hub zwischen Mandanten. eMatChef-Differenzierung: **Organisations-interner Fulfillment-Hub** mit **Druckspezifikation** (Menge, Metall/Etikett, Format) für Departments ohne Hardware.

---

## 18. Siehe auch

- [link-schema.md](../qr/link-schema.md)
- [qr-public-pages.md](../qr/qr-public-pages.md)
- [nachrichtenzentrale.md](../nachrichtenzentrale.md)
- `backend/src/Controller/PrintTaskController.php`
- `backend/src/Entity/PrintTaskItem.php`
- `frontend/src/views/TasksPrintView.vue`
