# PDF-/Scan-Import Materialeinkauf

**Status:** Spec / Scope bestätigt · noch nicht implementiert  
**Stand:** August 2026  
**Quelle:** [Ideen-Backlog Top-10 #6](../future/ideen-backlog.md)

Verwandt:

- [templates-import-export.md](./templates-import-export.md) — CSV-/JSON-Import (anderer Kanal; Semantik `create` / `add_batch` wiederverwenden)
- [../supplier/supplier-portal.md](../supplier/supplier-portal.md) — Lieferanten-Auflösung (`Address`, `MaterialWizardSupplierService`)
- [../supplier/plan.md](../supplier/plan.md) — Supplier-Portal (B2B-Shop/Delivery ≠ PDF-Einkauf)
- [../wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) — zentrale Frontend-Bausteine (Pflicht)
- [../ui/vuetify-standards.md](../ui/vuetify-standards.md) — `E*`-Form-Bausteine
- Grossanlass-Offerten: `GrossanlassProcurementQuotePdfTextService` — Text-Extract + Kontakt-Heuristik (Reuse)

---

## 1. Ziel

MW lädt einen **Lieferschein / eine Rechnung** (digitales PDF **oder Scan/Foto**) hoch. Das System erkennt Lieferanten-/Adressdaten und **eine oder mehrere Positionen**. Pro Position entscheidet der MW im Review:

- bestehenden Artikel wählen → **Batch (Charge) hinzufügen**
- kein Treffer → **Artikel anlegen + erster Batch**

Lieferant, Menge, Preis, Datum, Rechnungsnr. landen am **Batch**, nicht am Artikel-Stamm.

**Kein Auto-Commit** — vor dem Speichern immer durchschauen und korrigieren.

---

## 2. Ist-Stand (Anknüpfung)

| Baustein | Relevanz |
|----------|----------|
| `MaterialCreateWizard` — Schritt Erstellmodus | Einstieg: neue Karte „Aus PDF / Scan“ |
| `MaterialBatch.supplier_id` | Lieferant gehört zur Charge |
| `MaterialItem.manufacturer` | nur Freitext-Hersteller am Artikel |
| `MaterialImportService` | Duplicate-Logik `create` / `add_batch` / `skip` |
| `MaterialWizardSupplierService` | Lieferant matchen / lokal anlegen |
| `GrossanlassProcurementQuotePdfTextService` | `pdftotext` + Kontakt-Heuristik (keine Positionen) |
| Beleg-Upload (Accounting) | Speichern ohne Parse — optional PDF nach Commit anhängen |
| Dompdf / pdftk | nur PDF **erzeugen** — nicht für Import |

**Fehlt:** PDF-/OCR-Parse → Positionen; Wizard-Flow „Position für Position“; dedizierte Review-UI.

---

## 3. Scope

### In Scope (MVP)

1. Einstieg im Material-Wizard (Moduswahl oben)
2. Upload PDF **oder** Scan/Foto (Kamera/Datei)
3. Extraktion: Text-PDF (`pdftotext` + Fallback) und Scan/OCR
4. Erkennung: Lieferant (Firma, Adresse, Kontakt) + Positionen (Name, Menge, Preis) + optional Rechnungsnr. / Datum / Betrag
5. **Review-Schritt Pflicht** — alle Felder editierbar
6. Mehrere Positionen: **nacheinander** abarbeiten (eine nach der anderen anlegen)
7. Pro Position: Artikel suchen → Batch; sonst Artikel + Batch
8. Tracking: **bulk** oder **serialized**; SN nur wenn vorhanden/eingegeben, sonst keine SN
9. Commit über bestehende Create- / `addBatch`-APIs
10. Lieferant am Batch vorausfüllen / matchen / bei Bedarf lokal anlegen
11. **Zentrale, wiederverwendbare Bausteine** — Wizard nur Host; Capture/Parse/Review auch für BatchModal & Co. nutzbar (siehe §6)

### Out of Scope (MVP)

- Massen-Commit aller Zeilen in einem Klick (wie CSV-Import-Tabelle)
- Combo-/Vorlagen-Auflösung aus PDF
- Seriennummern automatisch aus PDF (nur manuell im Review, wenn serialisiert)
- Supplier-Shop / Delivery ersetzen
- Hersteller-spezifische Layout-Parser (Tortuga, Hajk, …) — später optional

---

## 4. UX-Flow

```text
Neues Material
    ↓
Erstellmodus wählen
  · Einzelstück | physische Kombo | virtuelle Kombo   (bestehend)
  · Aus PDF / Scan                                  (neu)
    ↓
Datei wählen (PDF) oder fotografieren / Scan hochladen
    ↓
Parse (Backend) → Vorschau
    ↓
Review Kopf: Lieferant, Adresse, Rechnungsnr., Datum
    ↓
Positionsliste (1…N)
    ↓
Position i von N bearbeiten:
  · Felder korrigieren (Name, Menge, Preis, Tracking, SN falls serialisiert)
  · Artikel suchen / Match bestätigen  ODER  „Neu anlegen“
  · Speichern → Artikel+Batch bzw. nur Batch
  · weiter zu Position i+1  (oder überspringen)
    ↓
Fertig / Wizard schliessen
  (optional: Original-PDF als Beleg anhängen)
```

### Mehrere Positionen

- Parse liefert **N Zeilen**; UI zeigt Fortschritt „Position 2 von 5“.
- Jede Position wird **einzeln** reviewed und committed (kein Bulk-Save).
- Überspringen erlaubt (Zeile bleibt offen oder wird verworfen — UI-Detail).
- Lieferanten-Kopfdaten gelten für die ganze Session (alle Batches derselben Rechnung), pro Batch übernehmbar.

### Review-Prinzip

Alles was erkannt wurde, ist Vorschlag. Speichern erst nach expliziter Bestätigung der aktuellen Position. Unsichere Felder markieren (Confidence / Warnung), nicht stillschweigend übernehmen.

---

## 5. Datenmodell-Mapping

| Erkannt / eingegeben | Ziel |
|----------------------|------|
| Lieferant Name, Strasse, PLZ, Ort, Tel, E-Mail | `Address` (`type=supplier`) → `MaterialBatch.supplier_id` |
| Rechnungsnr. | `MaterialBatch.invoice_number` (API ggf. nachziehen, falls Create/Batch sie heute nicht setzt) |
| Datum | `MaterialBatch.acquired_on` |
| Positionsname | `MaterialItem.name` (bei Neu) bzw. Suche |
| Menge | `MaterialBatch.qty` (bulk) bzw. N× Batch qty=1 (serialized) |
| Einzelpreis | `MaterialBatch.unit_price` |
| Tracking bulk / serialized | `MaterialItem.tracking_type` (bei Neu); SN → `MaterialBatch.serial_number` nur wenn gesetzt |
| Hersteller-Text (optional) | `MaterialItem.manufacturer` — **nicht** der Lieferant |

**Regel:** Lieferant = Einkaufsquelle am **Batch**. Hersteller am Artikel ist optional und unabhängig.

---

## 6. Architektur: zentrale Bausteine (Pflicht)

**Regel:** Keine Doppelspur im Wizard. Logik und UI als **eigene Bausteine** bauen, die der Wizard nur einbettet — und die später z. B. in `BatchModal`, Werkstatt-Einkauf oder Grossanlass-Offerte wiederverwendet werden können.

Vor jeder neuen Datei prüfen: existiert schon etwas in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md)? Dann erweitern/konfigurieren statt neu erfinden.

### 6.1 Bestehende Bausteine nutzen

| Baustein | Pfad | Rolle hier |
|----------|------|------------|
| **PhotoUpload** / `useMediaUpload` | `components/media/` | Scan/Foto erfassen (Kamera/Datei); defer-Modus bis Parse |
| **PurchaseReceiptFileInput** | `components/material/PurchaseReceiptFileInput.vue` | Ausgangspunkt Dateiwahl; auf PDF+Bild erweitern oder in generischen Capture-Baustein überführen |
| **DepartmentAddressAutocomplete** + **AddressModal** | `components/addresses/`, `AddressModal.vue` | Lieferant suchen / anlegen nach Prefill |
| **MaterialWizardSupplierService** (API) | Backend | Lieferanten-Match wie Wizard/CSV |
| **SearchSuggestion** / Material-Suche | `useSearchNavigation` + bestehende Suche | Artikel matchen vor Batch |
| **E\*** Form (`ETextField`, `EButton`, `EDialog`, …) | `components/form/base` | Review-Felder, keine Custom-Inputs |
| Create / `addBatch` APIs | `POST /api/materials`, `…/batches` | Commit — kein paralleler Write-Pfad |
| Grossanlass PDF-Text | `GrossanlassProcurementQuotePdfTextService` | **extrahieren** in shared Service, Grossanlass nur noch Consumer |

### 6.2 Neue Bausteine (wiederverwendbar, nicht Wizard-intern)

Zielstruktur — Wizard bleibt dünner Host:

```text
frontend/src/
  components/purchase-document/     # oder material/purchase-document/
    PurchaseDocumentCapture.vue     # PDF + Bild/Kamera → File
    PurchaseDocumentReviewHeader.vue  # Lieferant, Nr., Datum (editierbar)
    PurchaseDocumentLineStepper.vue   # Position i von N, Skip/Next
    PurchaseDocumentLineForm.vue      # Name, Menge, Preis, Tracking, SN
  composables/
    usePurchaseDocumentParse.ts     # Upload → parse API → state
    usePurchaseDocumentSession.ts   # lines[], index, supplier, skip/done
  api/
    purchaseDocument.ts             # parse dry-run Typen + Client

backend/src/Service/Document/
  PdfTextExtractService.php         # pdftotext + Fallback (Grossanlass nutzt mit)
  DocumentOcrService.php            # Scan/Bild → Text (MVP-Pfad)
  PurchaseDocumentParseService.php  # Kontakt + lines[] + confidence
```

| Baustein | Verantwortung | Andere Consumer (später) |
|----------|----------------|--------------------------|
| **PurchaseDocumentCapture** | Eine Datei: PDF oder Scan/Foto | BatchModal „Charge aus Beleg“, Grossanlass Offerte, Accounting-Beleg |
| **usePurchaseDocumentParse** | Parse-Aufruf, Loading/Error, Roh-Ergebnis | jeder Capture-Consumer |
| **PurchaseDocumentReviewHeader** | Lieferanten-Prefill + Korrektur | Grossanlass Kontakt-Extract ersetzen/teilen |
| **PurchaseDocumentLineStepper** + **LineForm** | Position für Position | Wizard; optional BatchModal wenn 1 Zeile |
| **PdfTextExtractService** | Nur Text aus PDF | Grossanlass, Purchase-Parse, künftige Docs |
| **PurchaseDocumentParseService** | Heuristik Positionen + Meta | nur Einkauf; Kontakt-Teil ggf. shared DTO mit Grossanlass |

### 6.3 Einbettung im Wizard (Host, kein Monolith)

```text
MaterialCreateWizard
  creation_mode += „Aus PDF / Scan“
        ↓
  <PurchaseDocumentCapture />
        ↓
  usePurchaseDocumentParse → Vorschau
        ↓
  <PurchaseDocumentReviewHeader />   // Lieferant via DepartmentAddressAutocomplete
  <PurchaseDocumentLineStepper>
      <PurchaseDocumentLineForm />   // + Material-Suche
      → commit create | addBatch
  </PurchaseDocumentLineStepper>
```

- Wizard liefert nur `departmentId`, Rechte-Kontext und nach Commit Navigation/Toast.
- **Keine** Parse-/OCR-Logik und **kein** Positions-State im Wizard verdoppeln.
- Session-State im Composable: nach Position N weiter; Wizard kann Session resetten beim Schliessen.

### 6.4 Wiederverwendung — geplante zweite Einstiege

| Ort | Nutzung |
|-----|---------|
| **MaterialCreateWizard** (MVP) | voller Flow, N Positionen nacheinander |
| **BatchModal** (später) | Capture + Review → Prefill einer Charge (oft 1 Position) |
| **Grossanlass Offerte** | shared Text-Extract + optional ReviewHeader für Kontakt |
| **Werkstatt PurchaseLine** | optional Capture → Lieferant/Preis vorausfüllen |

Neue Einstiege = Host + Props (`mode: 'full-session' | 'single-line' | 'contact-only'`), nicht Copy-Paste.

### 6.5 Backend-Technik

| Stück | Aufgabe |
|-------|---------|
| `PdfTextExtractService` | Grossanlass-Logik generalisieren; Grossanlass-Service wird Wrapper/Consumer |
| `DocumentOcrService` | Scan/Foto → Text |
| `PurchaseDocumentParseService` | Kontakt + Positionen; Confidence pro Feld |
| API Dry-Run | `POST …/materials/purchase-document/parse` → Vorschau, **kein** Schreiben |
| Commit | bestehende Material-/Batch-APIs |

Parse und Commit getrennt: Frontend steuert Position-für-Position.

### Extraktion

```text
Upload
  → MIME/Typ?
       PDF digital  → PdfTextExtractService
       Bild / Scan  → DocumentOcrService
  → Rohtext
  → PurchaseDocumentParseService
  → JSON { supplier, meta, lines[] }
```

OCR ist MVP-Scope („auch Scans“), darf hinter dem Text-PDF-Pfad folgen; Capture + Review bleiben gleich.

### 6.6 Doku-Pflicht bei Umsetzung

1. Neue Bausteine in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) eintragen (Tabelle + kurzes Beispiel).
2. Grossanlass auf `PdfTextExtractService` umbiegen (kein zweiter Extract-Pfad).
3. Keine parallelen CSS-Inseln — Tokens/`E*`-Styles bzw. `styles/components/` wenn 2+ Consumer.

---

## 7. Rechte & Grenzen

- Gleiche Rolle wie Material anlegen / Batch hinzufügen (typisch MW/DC im Department)
- Dateigrösse und MIME begrenzen (PDF, JPEG, PNG, WebP — konkret in Implementierung)
- Kein Schreibzugriff aus dem Parse-Endpoint
- Originaldatei nur speichern wenn explizit als Beleg gewünscht (Accounting-Follow-up)

---

## 8. Abgrenzung zu anderen Imports

| Kanal | Wann |
|-------|------|
| **PDF/Scan (diese Spec)** | Einzelrechnung, Wizard, Position für Position |
| **CSV/XLSX** (`MaterialImportSettingsView`) | Tabellen-Massenimport |
| **Supplier-Delivery / Shop** | strukturierte B2B-Übergabe inkl. SN |
| **Vorlagen JSON** | Template-Stamm, kein Einkauf |

---

## 9. Offene Implementierungsdetails (nicht Scope-Blocker)

| Thema | Hinweis |
|-------|---------|
| OCR-Engine | Tesseract vs. externer Dienst — Entscheidung bei Umsetzung |
| `invoice_number` an Create/Batch-API | prüfen/nachziehen falls UI-Feld heute nicht persistiert |
| Übersprungene Positionen | Session merken vs. verwerfen |
| Sehr lange Rechnungen (20+ Zeilen) | Fortschritt + Skip reichen; kein CSV-Ersatz |
| Combo-Material aus PDF | bewusst später |
| Capture vs. PurchaseReceiptFileInput | erweitern vs. neuer `PurchaseDocumentCapture` — bei Umsetzung entscheiden, ein Baustein behalten |

---

## 10. Definition of Done (MVP)

- [ ] Wizard-Karte „Aus PDF / Scan“ (Host only)
- [ ] Wiederverwendbare Bausteine: Capture, Parse-Composable, ReviewHeader, LineStepper/Form
- [ ] Shared `PdfTextExtractService`; Grossanlass ohne parallelen Extract
- [ ] Parse digitaler PDFs + Scans/Fotos → Vorschau
- [ ] Review Kopf + Positionen; Speichern erst nach Bestätigung
- [ ] Mehrere Positionen nacheinander (Fortschritt i von N)
- [ ] Pro Position: Match → Batch **oder** Neu → Artikel + Batch
- [ ] Lieferant am Batch; Tracking bulk/serialized; SN nur wenn gesetzt
- [ ] Kein Auto-Commit; korrigierbare Felder
- [ ] Eintrag in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md)
- [ ] i18n `de` / `en`; Rechte analog Material-Create

---

## Siehe auch

- [../future/ideen-backlog.md](../future/ideen-backlog.md) — Top-10 #6
- [templates-import-export.md](./templates-import-export.md)
- [../supplier/supplier-portal.md](../supplier/supplier-portal.md)
- [../wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md)
