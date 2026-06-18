# Scan-History & QR-URL-History

Depübergreifende Protokollierung von **QR-URLs** (Lebenszyklus) und **Scans** (Nutzung) — getrennt von der Aktivitäts-Pack-History ([newUI/SPEC §20.2](../activities/newUI/SPEC.md#202-history--audit-pack-journey)).

**Stand:** Juni 2026 · **Status:** Spezifikation (Ziel); Umsetzung offen

**Verwandt:** [link-schema.md](./link-schema.md) · [qr-public-pages.md](./qr-public-pages.md) · [label-fulfillment.md](../future/label-fulfillment.md) · [devices/pack-workflow.md](../devices/pack-workflow.md) · [newUI/SPEC §20.11](../activities/newUI/SPEC.md#2011-scan-history-abgegrenzung)

---

## Inhaltsverzeichnis

1. [Warum zwei Historien?](#1-warum-zwei-historien)
2. [QR-URL-History](#2-qr-url-history)
3. [Scan-History](#3-scan-history)
4. [Gemeinsame Regeln](#4-gemeinsame-regeln)
5. [Datenmodell (Ziel)](#5-datenmodell-ziel)
6. [API (Ziel)](#6-api-ziel)
7. [UI (Ziel)](#7-ui-ziel)
8. [Abgrenzungen](#8-abgrenzungen)
9. [Implementierungsphasen](#9-implementierungsphasen)

---

## 1. Warum zwei Historien?

| | **QR-URL-History** | **Scan-History** |
|--|---------------------|------------------|
| **Frage** | Wann wurde welche URL erzeugt / gedruckt / ungültig? | Wer hat welchen Code wann gelesen — mit welchem Ergebnis? |
| **Nature** | Stammdaten-Lebenszyklus (`public_code`, Etikett) | Nutzungs-/Ereignis-Log |
| **Häufigkeit** | selten (Anlage, Druck, Migration) | oft (jedes Scannen) |
| **Typische Quelle** | `PublicCodeService`, Druckkorb, Label-Fulfillment | `app.`, `devices.`, optional `qr.` |
| **Statistik** | «Wann zuletzt Etikett?», «Wurde gedruckt?» | «Oft gescannt», «Scan ohne Buchung» |

**Gemeinsam:** beide beziehen sich auf kanonische URLs laut [link-schema.md](./link-schema.md) — nicht auf interne UUIDs in der UI.

**Nicht dasselbe wie:**

- **Aktivitäts-Pack-History** — nur Buchungen (`move`) pro Anlass
- **Material-History** (`MaterialHistory`) — Stammdaten-Änderungen am Material
- **Journey «Letzte Scans»** — nur Session, nicht persistiert

---

## 2. QR-URL-History

### 2.1 Scope

Alle öffentlichen QR-URLs nach [link-schema.md](./link-schema.md):

| URL-Typ | Pfad | `entity_type` |
|---------|------|---------------|
| Material + Charge | `/i/m/{mat}/b/{batch}` | `material` + `batch` |
| Aktivität | `/i/a/{activity}` | `activity` |
| Werkstatt | `/i/w/{workshop}` | `workshop` |

### 2.2 Events

| `event` | Auslöser | Payload (Beispiel) |
|---------|----------|-------------------|
| `url_created` | `ensureBatchPublicCode`, Aktivität/Werkstatt angelegt | `public_url`, `material_code`, `batch_code` |
| `url_regenerated` | Code neu vergeben (selten) | `old_url`, `new_url` |
| `url_print_queued` | Eintrag Druckkorb (`PrintTaskItem`) | `print_task_id`, `print_quantity`, `format_key` |
| `url_print_done` | Browser-Druck / Hub «erfüllt» | `carrier`, `format_key` |
| `url_label_fulfilled` | Label-Fulfillment Hub (Zukunft) | `hub_department_id`, `spec_snapshot` |
| `url_invalid_access` | Aufruf ungültiger/legacy URL auf `qr.` | `requested_path`, `reason` |

**Nicht** jeder Seitenaufruf auf `qr.ematchef.ch` — nur **strukturierte** URL-Ereignisse und **ungültige** Zugriffe (Missbrauch/Legacy-Etiketten).

### 2.3 Nutzen

- Nachvollziehen: «Hat diese Charge schon ein Etikett?»
- Support: Finder scannt altes `/i/b/…`-Etikett → `url_invalid_access`
- Label-Fulfillment: Audit «Hub hat am … gedruckt»
- Kein Ersatz für Lager-Bestand — nur **URL-/Etikett-Lebenslauf**

---

## 3. Scan-History

### 3.1 Scope

Jeder **Scan-Vorgang** in eingeloggten Apps und optional auf öffentlichen QR-Seiten:

| Quelle (`scan_source`) | Beschreibung |
|------------------------|--------------|
| `app_pack` | Journey / Packliste Scan-Bar |
| `app_material` | Material-Suche mit Kamera (Zukunft) |
| `app_other` | sonstige Scan-Felder in `app.` |
| `devices_pack` | `devices.ematchef.ch` Pack-Session |
| `devices_other` | devices Home / sonst |
| `qr_public` | Besucher öffnet QR im Browser (optional, anonymisiert) |
| `manual_paste` | Text in Suchfeld eingefügt, Parser läuft |

Parser: [`scanParser.ts`](../../frontend/src/utils/scanParser.ts) — `activity`, `material_batch`, `unknown`.

### 3.2 Events — immer eine Zeile pro Scan

| Feld | Inhalt |
|------|--------|
| `scanned_at` | Timestamp |
| `user_id` | nullable (`qr_public` → null) |
| `department_id` | Kontext-Department (Session / Aktivität / Gerät) |
| `activity_id` | nullable — wenn Pack-Kontext bekannt |
| `scan_source` | siehe oben |
| `raw_input` | gekürzt (max. 512 Zeichen) — **keine** Passwörter |
| `parsed_type` | `activity` \| `material_batch` \| `workshop` \| `unknown` |
| `material_code`, `batch_code` | aus Parser / Lookup |
| `resolved_material_item_id`, `resolved_batch_id` | nach Lookup |
| `resolve_result` | siehe Matrix |
| `led_to_action` | `false` = nur Lookup; `true` = Move/Buchung/Navigation folgte |

### 3.3 `resolve_result` (Scan-Ergebnis)

Angelehnt an Journey [§6.3](../activities/newUI/SPEC.md#63-ergebnis-matrix) — **depübergreifend**:

| `resolve_result` | Bedeutung |
|------------------|-----------|
| `ok` | Entity gefunden, Kontext passt |
| `not_on_list` | Material nicht auf aktueller Packliste |
| `wrong_activity` | gehört anderer Aktivität |
| `wrong_batch` | Material ok, Charge passt nicht |
| `not_ready` | noch nicht gepackt |
| `unknown_code` | Parser/ Lookup fehlgeschlagen |
| `in_repair` | Batch in Werkstatt |
| `no_permission` | User darf nicht |
| `public_info_only` | `qr.` — nur Infoseite, keine App-Aktion |

### 3.4 Nutzen / Statistik

| Auswertung | Quelle |
|------------|--------|
| «Top 20 gescannte Materialien (Saison)» | `scan_history` GROUP BY `material_item_id` |
| «Oft gescannt, selten gebucht» | Scan-Häufigkeit vs. `activity_pack_event` (`led_to_action`) |
| «Unbekannte Scans» | `resolve_result = unknown_code` |
| «Falsche Etiketten» | `wrong_batch` + `url_invalid_access` kombiniert |
| MW-Hinweis Regal | häufigster Scan-Pfad pro Material |

**Aggregation UI:** wie Pack-History — Rohlog vollständig, Anzeige gruppiert (z. B. «Taschenlampe · 47 Scans diese Woche»).

---

## 4. Gemeinsame Regeln

| Regel | Wert |
|-------|------|
| **Department** | Primary-Scope für MW-Sicht; Org-Admin optional aggregiert |
| **Retention** | konfigurierbar pro Org (Vorschlag: Scan 24 Monate, URL-History unbegrenzt) |
| **DSGVO** | `user_id` für eingeloggte Scans; `qr_public` ohne Personenbezug |
| **Performance** | Insert asynchron (Queue) — Scan darf nicht blockieren |
| **Roh-URL** | kanonisch normalisieren vor Speicherung (`public_url` ohne Query-Noise) |

### Verknüpfung der beiden Historien

```
QR-URL-History:  batch B bekommt URL am 01.03., gedruckt am 02.03.
Scan-History:    12× Scan auf …/i/m/X/b/B im März (8× app_pack, 4× devices)
Pack-History:    6× move mit source=scan (nur erfolgreiche Buchungen)
```

---

## 5. Datenmodell (Ziel)

### 5.1 `qr_url_history`

```
qr_url_history
  id
  department_id
  entity_type          material | batch | activity | workshop
  entity_id            FK (material_item, batch, activity, …)
  public_url           kanonisch
  material_code        nullable
  batch_code           nullable
  event                url_created | url_print_queued | …
  payload              JSON
  user_id              nullable
  created_at
```

Index: `(department_id, entity_type, entity_id)`, `(public_url)`, `(created_at)`.

### 5.2 `scan_history`

```
scan_history
  id
  department_id
  user_id              nullable
  activity_id          nullable
  scan_source
  scanned_at
  raw_input            varchar(512)
  parsed_type
  material_code        nullable
  batch_code           nullable
  resolved_material_item_id  nullable
  resolved_batch_id          nullable
  resolve_result
  led_to_action        boolean default false
  pack_event_id        nullable FK → activity_pack_event (wenn gebucht)
  client_meta          JSON nullable  — app_version, journey_step, device_id
```

Index: `(department_id, scanned_at)`, `(resolved_material_item_id, scanned_at)`, `(activity_id)`.

**Partitionierung (später):** nach `scanned_at` Monat — bei hohem Volumen.

---

## 6. API (Ziel)

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| POST | `/api/departments/{id}/scan-events` | Client meldet Scan (nach Parse + Resolve) |
| GET | `/api/departments/{id}/scan-history` | MW: Liste, Filter, Pagination |
| GET | `/api/departments/{id}/scan-stats` | Aggregiert: Top-Material, Zeitraum |
| GET | `/api/materials/{id}/url-history` | URL-Lebenslauf einer Charge/Material |
| GET | `/api/batches/{id}/url-history` | Etikett/Druck pro Batch |

**Server-seitig** (nicht nur Client):

- `url_created` / `url_print_*` aus bestehenden Flows (`PublicCodeService`, `PrintTaskController`)
- `url_invalid_access` aus `PublicLookupController` bei 404/legacy

Berechtigung: `canManageMaterials` oder Dept-Setting `scan_history_visible`.

---

## 7. UI (Ziel)

| Ort | Inhalt |
|-----|--------|
| **Material-Detail** | Tab/Abschnitt «QR & Scans»: URL-History + letzte Scans auf dieses Material |
| **Department → Statistik** | Top gescannt, Scan vs. Buchung, unbekannte Codes |
| **Aktivität** | **kein** vollständiger Scan-Log — nur Link «Dept-Statistik»; Pack-Buchungen in Pack-History |
| **Journey** | «Letzte Scans» Session (unverändert); optional Link «Alle Scans dieses Materials» |

---

## 8. Abgrenzungen

| Feature | Hier | Woanders |
|---------|------|----------|
| Scan ohne Buchung | **Scan-History** | — |
| Buchung nach Scan | Verknüpfung `led_to_action` + `pack_event_id` | Pack-History §20.2 |
| «Oft ausgegeben» (Moves) | indirekt | Nutzungs-Statistik §20.10 |
| Etikett erzeugt | **QR-URL-History** | — |
| Stammdaten Material geändert | — | `MaterialHistory` |
| Öffentlicher Finder-Kontakt | — | Inbox `qr_found` |

---

## 9. Implementierungsphasen

| Phase | Deliverable |
|-------|-------------|
| **Q1** | `scan_history` Tabelle + POST aus `app.` Pack-Scan + `devices.` |
| **Q2** | `qr_url_history` aus `ensureBatchPublicCode` + Druckkorb |
| **Q3** | Department Scan-Statistik UI |
| **Q4** | Material-Detail «QR & Scans»; Aggregation |
| **Q5** | `url_invalid_access`; optional `qr_public` anonym |

**Abhängigkeit:** Pack-History Phase 8 ([newUI §12](../activities/newUI/SPEC.md#12-implementierungsphasen)) kann parallel — getrennte Tabellen.

---

## Siehe auch

- [link-schema.md](./link-schema.md) — kanonische URLs
- [newUI/SPEC §20.11](../activities/newUI/SPEC.md#2011-scan-history-abgegrenzung) — Abgrenzung Journey
- [newUI/SPEC §20.10](../activities/newUI/SPEC.md#2010-nutzungs-statistik-department) — Nutzung aus Moves
