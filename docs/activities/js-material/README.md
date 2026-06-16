# J+S-Material (Leihmaterial Lagersport/Trekking)

Vollständige Spezifikation für **J+S-Leihmaterial** in eMatChef: frühe Entscheidung, digitales Bestellformular, Packliste und Retour — **nur** für Aktivitäten vom Typ **`camp`** und **`event`**.

**Stand:** Juni 2026 · **Status:** Spezifikation (Ziel); Umsetzung offen

**Verwandt:** [status.md](../status.md) · [material-pipeline.md](../material-pipeline.md) · [pack-workflow-rules.md](../pack-workflow-rules.md) · [Supplier-Portal §1b](../../supplier/supplier-portal.md#1b-drei-produktlinien-nicht-vermischen)

**Referenzformular (extern):** J+S «Leihmaterialbestellung Lagersport/Trekking» (BASPO/J+S, Formularstand 16.06.2021)

### Zwei J+S-PDFs — nicht verwechseln

| PDF | Datei (im Repo) | Bedeutung in eMatChef |
|-----|-----------------|------------------------|
| **Gesamtkatalog** | [`250821_JS_Leihmaterial_Katalog_DE.pdf`](./250821_JS_Leihmaterial_Katalog_DE.pdf) | Alles, was J+S an Leihmaterial **überhaupt** hat — **nur Referenz** («Katalog anzeigen» im Modal). **Nicht bestellbar** über unser Formular. |
| **Bestellformular Lagersport/Trekking** | [`bestellformular_lagersport_trekking_d.pdf`](./bestellformular_lagersport_trekking_d.pdf) | Das, was Pfadi **für Lager/Event (Sportart Lagersport/Trekking)** bei J+S **bestellen** darf. eMatChef-Katalog `dept_js00000` + PDF-Export = **1:1 diese Zeilen**. |

Andere Sportarten (z. B. Ski) haben **eigene** J+S-Bestellformulare — out of scope für eMatChef v1.

---

## Inhaltsverzeichnis

1. [Produktlinie und Abgrenzung](#1-produktlinie-und-abgrenzung)
2. [Geltungsbereich](#2-geltungsbereich)
3. [Grundprinzip: Zwei Material-Welten](#3-grundprinzip-zwei-material-welten)
4. [Frühe Entscheidung: J+S einbeziehen](#4-frühe-entscheidung-js-einbeziehen)
5. [Vorbefüllung des Bestellformulars](#5-vorbefüllung-des-bestellformulars)
6. [Digitales Bestellformular (Modal)](#6-digitales-bestellformular-modal)
7. [Dotations-Regeln](#7-dotations-regeln)
8. [Datenmodell](#8-datenmodell)
9. [UI — Tab Material](#9-ui--tab-material)
10. [UI — Packliste: Quellen-Oberreiter](#10-ui--packliste-quellen-oberreiter)
11. [J+S-Check-Flow in der Packliste](#11-js-check-flow-in-der-packliste)
12. [Aktivitäts-Status vs. J+S](#12-aktivitäts-status-vs-js)
13. [API (Ziel)](#13-api-ziel)
14. [Code-Referenzen](#14-code-referenzen)
15. [Implementierungsphasen](#15-implementierungsphasen)
16. [Explizit out of scope](#16-explizit-out-of-scope)

---

## 1. Produktlinie und Abgrenzung

J+S ist **Produktlinie 2** — getrennt von Pfadi-Bestand und Supplier-B2B:

| | Pfadi-Bestand | **J+S** | Supplier B2B |
|--|---------------|---------|--------------|
| Quelle | eigenes Department | globaler Katalog `dept_js00000` | `SupplierCompany` |
| Flag | — | `is_js_material = true` | — |
| Planung | Material-Tab, Suche | **Bestell-Modal** | Shop/Import (Phase 2) |
| Lager/Reservierung | ja | **nein** | ja (nach Import) |
| Packen im eigenen Lager | ja (MW) | **nein** | ja |
| Bestellung | intern (MW) | **extern per E-Mail an J+S** | B2B |

J+S pflegt nur die **Referenzliste** (`MaterialItem` mit `is_js_material`). eMatChef steuert **keinen** J+S-Shop — der Leiter/J+S-Coach sendet das ausgefüllte Formular per E-Mail (wie auf dem PDF verlangt: spätestens **5 Wochen vor Lieferdatum**).

---

## 2. Geltungsbereich

| Aspekt | Regel |
|--------|-------|
| Aktivitätstypen | **`camp`**, **`event`** only |
| Typ `activity` (Quick) | kein J+S |
| Typ `external` | kein J+S |
| Rollen | Leiter/Gruppenchef: Formular + Checks; MW: Übersicht, ggf. Unterstützung |
| Zeitpunkt Formular | ab Entwurf bis vor Abschluss (Bearbeitung solange sinnvoll) |
| Packliste J+S-Reiter | ab Logistics-Stufen **Transport hin → … → Transport zurück** (Camp/Event-Profil) |

---

## 3. Grundprinzip: Zwei Material-Welten

### 3.1 Eigenes / Partner-Material (unverändert)

- Planung über Tab **Material**: Suche (`ActivityMaterialAvailabilityLookup`)
- Quellen: eigenes Department, eingeladene Partner-Departments
- Reservierung über `activity_item` + Verfügbarkeit im Zeitraum
- Pack-Pipeline: MW packt → Transport → Event → Retour → Einlagern

### 3.2 J+S-Material (Ziel)

- **Nicht** in der Material-Suche, **nicht** im Wizard-Lookup, **nicht** unter «Vergessenes Material»
- Planung **ausschliesslich** über Modal **«J+S-Bestellung»** (Positionen aus Katalog-Picker **im Modal**, keine globale Suche)
- Positionen aus dem Formular → eigene Entität → Sync in Packliste
- Keine Lager-Reservierung, kein MW-Packen aus dem Regal
- Empfang/Retour über **Check-UI** im Packlisten-Reiter **J+S**

**Ist-Code (bewusst so):** J+S wird in der Suche ausgefiltert (`ActivityMaterialAvailabilityLookup.vue`, Kommentar in `activityMaterialAvailabilityScope.ts`).

**Keine** J+S-Zeilen als normale `activity_item` über die Suche — vermeidet Doppelungen und falsche Verfügbarkeitslogik.

---

## 4. Frühe Entscheidung: J+S einbeziehen

### 4.1 Wann fragen?

Sobald der Typ **`camp`** oder **`event`** gewählt ist — **nicht** erst beim Bestell-Modal.

| Moment | UI |
|--------|-----|
| Wizard Schritt **Stammdaten** | Toggle «J+S-Leihmaterial einbeziehen» |
| Wizard Schritt **Zeitraum** (nach Eventstandort) | optional **Anzahl Teilnehmende** (für Dotation) |
| Detail **Übersicht** (Entwurf) | Toggle + Teilnehmerzahl editierbar (AutoSave) |

**Warum früh?** Packliste, Reminder (5-Wochen-Frist), J+S-Reiter und Dotations-Vorschläge brauchen das Flag von Anfang an.

### 4.2 UI-Text (Vorschlag)

```
☐ J+S-Leihmaterial für dieses Lager/Event einbeziehen
  Material wird separat bei J+S bestellt (nicht aus dem eigenen Lager).
  Bestellformular und Pack-Checks folgen in der Aktivität.
```

Optional bei aktivem Flag:

```
Anzahl Teilnehmende (für J+S-Dotation): [ ____ ]
```

### 4.3 Felder auf der Aktivität

| Feld | Typ | Bedeutung |
|------|-----|-----------|
| `wants_js_material` | `boolean`, default `false` | Leiter will J+S von Anfang an |
| `participant_count` | `int`, nullable | Teilnehmerzahl für Dotation (sinnvoll wenn Flag gesetzt) |

Alternative «nur `activity_js_order`»: möglich, aber Flag auf `activity` ist einfacher für Wizard, Listen und Pack-Reiter ohne Join.

### 4.4 Verhalten wenn Flag = true

| Bereich | Verhalten |
|---------|-----------|
| Material-Suche | **ohne J+S** (unverändert) |
| Wizard Material-Schritt | Hinweis: «Eigenes Material hier; J+S über Bestellformular» |
| Tab Übersicht / Material | Karte «J+S-Bestellung» (Status: offen / Entwurf / bestellt) |
| Packliste | Reiter **J+S** sichtbar (leer bis Formular Positionen hat) |
| Einreichen | J+S-Formular **nicht** zwingend fertig — Warnung wenn Flag gesetzt und noch leer |

**Flag ausschalten:** nur im Entwurf; wenn schon Positionen oder PDF → Bestätigungsdialog.

---

## 5. Vorbefüllung des Bestellformulars

Beim ersten Öffnen des J+S-Modals (oder Anlegen von `activity_js_order`) werden Felder aus der Aktivität und Stammdaten übernommen. **Nur leere Felder** werden befüllt; manuell geänderte Felder (`user_overridden`) werden nicht überschrieben.

### 5.1 Bereits in der Aktivität vorhanden

| Quelle (`activity` / verknüpft) | Felder |
|--------------------------------|--------|
| `name` | Anlassname (Kontext) |
| `type` | `camp` → Kursart **Lager** vorschlagen |
| `usage_start` / `usage_end` | Nutzungszeitraum |
| `planning_start` / `planning_end` | Material Abholung / Rückgabe |
| `venue_address_id` | **Eventstandort** (Wizard-Pflicht bei camp/event) |
| `group_id` | Gruppe (Kontext) |
| `created_by_user_id` | Ersteller / Leitung |
| `responsible_user_id` | Verantwortliche Person (falls gesetzt) |
| `participant_count` | Block 2 — Teilnehmer |
| `wants_js_material` | steuert Sichtbarkeit |

Code-Referenz Venue: `useActivityCreateWizard.ts` (`venueAddressId`), `ActivityDraftOverviewForm.vue`.

### 5.2 Mapping → Formularblöcke

#### Block 1 — Personalien Kurs-/Lagerleitung

| PDF-Feld | Vorbefüllung | Lücke / manuell |
|----------|--------------|-----------------|
| Name, Vorname | Ersteller-Profil (`Profile.first_name`, `last_name`) | — |
| E-Mail | Profil | — |
| Adresse, PLZ/Ort, Kt. | nicht im Profil | Department-Adresse oder manuell; optional später User-Profil |
| Tel. | Venue-`Address.phone` oder Kontakt | oft Lager-Tel., nicht private Leitung |
| Personen-Nr. | — | User- oder Department-Setting `js_person_nr` |
| Angebotsnummer | — | manuell |

#### Block 2 — Informationen Kurs / Lager

| PDF-Feld | Vorbefüllung | Lücke |
|----------|--------------|-------|
| Kursart Lager / Kaderbildung | `type === 'camp'` → Lager | bei `event` wählen |
| Anzahl Teilnehmende | `activity.participant_count` | sonst im Modal |
| Lieferdatum | `planning_start` (Datum) | — |
| Datum Rücklieferung | `planning_end` (Datum) | — |
| J+S-Coach (Name, Personen-Nr.) | Department-Default oder letzte Bestellung | Setting siehe §5.3 |

#### Block 3 — Zustelladresse des Materials

| PDF-Feld | Vorbefüllung |
|----------|--------------|
| Bezeichnung Lieferort (Gebäudename) | `venue.name` |
| Name, Vorname, Adresse, PLZ, Ort, Kt. | Venue-`Address` |
| Tel. (Tag der Lieferung) | `venue.phone` |
| Tel. Lagerleitung (im Lager) | Ersteller-Kontakt oder Venue-Kontakt |
| Franko-Domizil / Abholung Thun | Default **Franko** wenn Venue gesetzt |

**Kern:** `venue_address_id` deckt Block 3 fast vollständig ab — Toggle und Teilnehmerzahl sinnvoll **nach** Eventstandort im Wizard.

#### Block 4 — Leihmaterial

Keine Vorbefüllung aus der Aktivitäts-Materialliste. Nach `participant_count`: **Dotations-Vorschläge** im Modal (Katalog-Picker, siehe §7).

### 5.3 Department-Defaults (Ziel)

Einmal pro Department pflegbar (MW / Depchef):

| Setting-Key | Inhalt |
|-------------|--------|
| `js.default_coach_person_nr` | J+S-Coach Personen-Nr. |
| `js.default_coach_first_name` | Vorname Coach |
| `js.default_coach_last_name` | Nachname Coach |
| `js.default_delivery_type` | `franko` \| `pickup_thun` |

Leiter kann im Modal überschreiben.

### 5.4 Sync bei Änderungen

Wenn `venue_address_id` oder Zeiträume in der Aktivität geändert werden und das J+S-Formular bereits existiert:

- Banner: «Standort/Zeitraum geändert — J+S-Formular aktualisieren?»
- Button «Zustelladresse aus Eventstandort übernehmen» (nur nicht-`user_overridden` Felder)

Service-Vorschlag: `JsOrderPrefillService` (Backend).

---

## 6. Digitales Bestellformular (Modal)

Das Modal bildet das offizielle PDF **inhaltlich** ab (Web-Formular; PDF-Export optional).

### 6.1 Aktionen

| Aktion | Ergebnis |
|--------|----------|
| Speichern (Entwurf) | JSON in DB, noch kein PDF |
| PDF erzeugen | Druck/PDF aus Formular + Positionen |
| Als bestellt markieren | Status `ordered`, Zeitstempel; Packliste startet Checks |
| Schliessen | Entwurf bleibt editierbar |

Versand an J+S: **manuell per E-Mail** (v1); kein automatischer Versand.

### 6.2 Block 4 — Material im Modal

- Eigener **Katalog-Picker** (Liste/Pagination aus `GET /api/materials/js-catalog`)
- Spalten: Material, Dotations-Hinweis, Stückzahl
- Vorschläge aus Teilnehmerzahl + §7
- Validierung gegen Bezugsberechtigung

**Katalog-Referenz:** [`250821_JS_Leihmaterial_Katalog_DE.pdf`](./250821_JS_Leihmaterial_Katalog_DE.pdf) — Link «Gesamtkatalog (Referenz)» im Modal, **nicht** zum Ausfüllen/Bestellen.

**Bestellbare Positionen:** nur Zeilen aus [`bestellformular_lagersport_trekking_d.pdf`](./bestellformular_lagersport_trekking_d.pdf) — Katalog-Picker Schritt Material.

---

## 7. Dotations-Regeln

Aus dem J+S-Formular — für **Vorschlagsmengen** und **Validierung** im Modal.

| Material | Regel (pro Kurs) |
|----------|------------------|
| Bindestrick | 1/TN, max. 50, **aufrunden auf 5** |
| Wolldecke | 2/TN, **aufrunden auf 5** |
| Kessel 15 l | 1 pro 6 TN |
| Kesselaufsatz nieder | 1 pro 6 TN |
| Handbeil | 1 pro 4 TN |
| Kochkessel 12 l (inkl. Deckel) | 1 pro 8 TN |
| Kompass Recta | 1 pro 2 TN, **aufrunden auf 5** |
| Kompass Silva | 1 pro 2 TN, **aufrunden auf 5** |
| Manipulierseil 10–15 m | 1 pro 2 TN |
| Pickel | 1 pro 4 TN |
| Beinstulpe refl. | 1/TN |
| Schwimmwesten (XXS–XL) | 1/TN, **max. 20/Kurs** |
| Schneeschaufel | 1/TN, nur Winter |
| Sonnenbrille SUVASOL | 1/TN, **max. 15/Kurs** |
| Spaten | 1 pro 4 TN |
| Speiseträger 20 l + Schöpfkelle | 1 pro 18 TN |
| Spielsets (Badminton, Volleyball, …) | siehe Formular, **max. 3 Sets/Kurs gesamt** |
| Zelttasche zu Zelttuch | 1/TN, **aufrunden auf 5** |
| Zelttuch | 1/TN, **aufrunden auf 10** |
| Ausschusszelttuch | 1/TN, **aufrunden auf 10** |

Implementierung: Config `js_dotation_rules` — Mapping `material_item_id` ↔ Formularzeile (`jsDotationRules.ts` / Backend-Service).

---

## 8. Datenmodell

### 8.1 Aktivität (Erweiterung)

```
activity.wants_js_material     boolean, default false
activity.participant_count     int|null
```

### 8.2 J+S-Bestellung

```
activity_js_order
  id
  activity_id              (FK, UNIQUE)
  status                   draft | ready | ordered | fulfilled | cancelled
  form_data                JSON (Blöcke 1–3, Overrides)
  participant_count        int (Spiegel oder aus activity)
  delivery_type            franko | pickup_thun
  ordered_at, ordered_by_user_id
  generated_pdf_media_id   (Medien-Speicher)
  created_at, updated_at

activity_js_order_item
  id
  js_order_id              (FK)
  material_item_id         (FK → J+S-Katalog)
  quantity_ordered         int
  dotation_suggested       int|null
  order_confirmed          bool   «bei J+S bestellt»
  quantity_received        int    «physisch erhalten»
  quantity_returned        int    «an J+S zurück»
  notes
  sort_order
```

### 8.3 Abgrenzung zu `activity_item`

| | `activity_item` | `activity_js_order_item` |
|--|-----------------|--------------------------|
| Quelle | Material-Suche | Bestell-Modal |
| Reservierung | ja | nein |
| Tab Material | ja | nur Summary / Link |
| Packliste | normale Pipeline | J+S-Check-Flow |

### 8.4 Sync → `activity_pack_item`

Beim Pack-Init oder Speichern der J+S-Bestellung:

- Pro `activity_js_order_item` → ein `activity_pack_item` mit `is_js_material = true`
- `quantity_ordered` = Bestellmenge
- `quantity_packed` = 0, `quantity_transport_to` = 0 (J+S überspringt MW-Pack/Transport)
- `quantity_issued` / `quantity_returned` aus Check-UI

Pack-API soll zusätzlich `source_department_id` / `source_department_name` liefern (für Oberreiter Eigen/Partner).

### 8.5 PDF-Ablage

Wie Buchhaltungsbelege über `MediaStorageService` → `activity_js_order.generated_pdf_media_id`.

---

## 9. UI — Tab Material (Camp/Event)

| Element | Verhalten |
|---------|-----------|
| Material-Suche | **ohne J+S** |
| Toggle «J+S einbeziehen» | siehe §4 (in Übersicht wiederholbar) |
| Karte «J+S-Bestellung» | Status, Positionen, «Formular öffnen» |
| Kein J+S in Suchergebnissen | — |

---

## 10. UI — Packliste: Quellen-Oberreiter

Ab Camp/Event-Logistics (**Transport hin** … **Transport zurück**), **zusätzlich** zu den Etappen-Tabs:

| Oberreiter | Filter |
|------------|--------|
| **Eigen** | `!is_js_material` und Material-Department = Host-Department |
| **Partner «Name»** | pro angenommener Einladung |
| **J+S** | `is_js_material` / `activity_js_order_item` |

MW-Stufe «Bestätigt → Gepackt»: J+S-Reiter leer oder Hinweis «Bestellung noch offen».

Sichtbar nur wenn `wants_js_material === true` (oder J+S-Positionen existieren).

---

## 11. J+S-Check-Flow in der Packliste

J+S durchläuft **nicht** die normale Lager-Pipeline.

### 11.1 Status pro Position

```
geplant → bestellt → erhalten → am_event → retour_js
```

| UI-Schritt | Feld | Bedingung |
|------------|------|-----------|
| Geplant | `quantity_ordered` | aus Formular |
| ☑ Bestellt | `order_confirmed` | nach E-Mail an J+S |
| Erhalten | `quantity_received` | Eingabe ≤ `quantity_ordered` |
| Am Event | `quantity_issued` | = `quantity_received` (Bestätigung) |
| Retour an J+S | `quantity_returned` | Eingabe ≤ `quantity_issued` |

### 11.2 UI pro Zeile (Reiter J+S)

```
Bindestrick Hanf blau/grau
  Bestellt: 25          ☑ Bei J+S bestellt (Datum)
  Erhalten: [ 25 ] / 25   [ Bestätigen ]
  Am Event: 25
  Retour:   [ 23 ] / 25   [ Rückgabe bestätigen ]
```

### 11.3 Regeln

- **Kein** Kistencheck, **keine** Packkisten-Rubrik für J+S
- **Kein** Einlagern (`quantity_stored` = 0) — Retour = Abgabe an J+S
- Verlust: Differenz `quantity_issued − quantity_returned` → Issue-Meldung (optional)
- `jsWorkflowSummary` (Ist in `ActivityPackListTab.vue`) → Header des J+S-Reiters

### 11.4 Pipeline-Mapping (J+S)

| Standard-Pipeline | J+S |
|-------------------|-----|
| `quantity_packed` | 0 |
| `quantity_transport_to` | 0 |
| `quantity_transport_back` | 0 |
| `quantity_issued` | nach «Erhalten» |
| `quantity_returned` | nach «Retour J+S» |
| `quantity_stored` | 0 |

---

## 12. Aktivitäts-Status vs. J+S

| Aktivitäts-Status | J+S |
|-------------------|-----|
| `draft` … `approved` | Formular editierbar; Flag setzbar |
| `packing` … `packed` | MW packt eigenes Material; J+S: «bestellt» markieren |
| `at_event` | «Erhalten» sollte erledigt sein |
| `returned` | J+S-Retour-Checks |
| `completed` | optional Blocker bei offenen J+S-Differenzen |

---

## 13. API (Ziel)

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| PATCH | `/api/activities/{id}` | `wants_js_material`, `participant_count` |
| GET | `/api/activities/{id}/js-order` | Bestellung + Positionen |
| PUT | `/api/activities/{id}/js-order` | Formular + Positionen speichern |
| POST | `/api/activities/{id}/js-order/mark-ordered` | Status `ordered` |
| POST | `/api/activities/{id}/js-order/generate-pdf` | PDF erzeugen |
| POST | `/api/activities/{id}/js-order/prefill` | Felder aus Aktivität übernehmen |
| PATCH | `/api/activities/{id}/js-order/items/{itemId}` | Check-Felder |
| GET | `/api/materials/js-catalog` | J+S-Katalog für Modal-Picker |

Berechtigung: `ActivityAccessService` — analog Material bearbeiten (Camp/Event).

---

## 14. Code-Referenzen

### 14.1 Ist (unverändert lassen)

| Thema | Ort |
|-------|-----|
| J+S aus Suche filtern | `frontend/.../ActivityMaterialAvailabilityLookup.vue` |
| Scope-Typ `js` ohne UI | `activityMaterialAvailabilityScope.ts` |
| J+S-Stammdaten | `MaterialItem.is_js_material`, Seeds `dept_js00000` |
| Pack-Summary J+S | `ActivityPackListTab.vue` → `jsWorkflowSummary` |
| Venue / Zeitraum Wizard | `useActivityCreateWizard.ts`, `ActivityDraftOverviewForm.vue` |
| Produktlinie | `docs/supplier/supplier-portal.md` §1b |

### 14.2 Ziel (neu)

| Thema | Ort (Vorschlag) |
|-------|-----------------|
| Backend Entity | `ActivityJsOrder.php`, `ActivityJsOrderItem.php` |
| Prefill | `JsOrderPrefillService.php` |
| Modal | `ActivityJsOrderModal.vue` |
| Dotation | `jsDotationRules.ts` |
| Pack-Reiter + Checks | `ActivityPackListTab.vue`, `packWorkflowRules.ts` |
| PDF | `ActivityJsOrderPdfService.php` |

---

## 15. Implementierungsphasen

| Phase | Inhalt | DoD |
|-------|--------|-----|
| **0** | Dieses Dokument | Spec reviewed ✓ |
| **1** | `wants_js_material` + Wizard-Toggle + Übersicht | Flag speichern ✓ |
| **2** | `participant_count` + Department J+S-Defaults | Dotation vorbereitet ✓ |
| **3** | DB + API `activity_js_order` (+ items), Modal Blöcke 1–3, Prefill | Speichern/Laden ✓ |
| **4** | Block 4: Katalog-Picker + Dotation | Positionen + Validierung ✓ |
| **5** | PDF-Export + Ablage | Drucken |
| **6** | Pack-API `source_department_*` + Oberreiter | Getrennte Listen |
| **7** | J+S-Reiter Check-UI + Backend-Moves | Empfang/Retour |
| **8** | Optional: Abschluss-Blocker, Inbox-Reminder 5 Wochen | MW-Hinweis |

---

## 16. Explizit out of scope

- J+S als Supplier-Portal / B2B-Import
- J+S in Material-Suche oder Verfügbarkeits-Reservierung
- MW-Packen / Kisten für J+S
- Automatischer E-Mail-Versand an J+S (v1)
- Typ `activity` / `external`

---

## Siehe auch

- [Material-Pipeline](../material-pipeline.md) — normale Pipeline vs. J+S-Ausnahme
- [Pack-Workflow-Regeln](../pack-workflow-rules.md) — Erweiterung um J+S-Profil
- [Aktivitäts-Status](../status.md) — Camp/Event Logistics
- [Supplier-Portal §1b](../../supplier/supplier-portal.md) — drei Produktlinien
