# Lieferanten-Portal (Supplier Portal)

Konzept-Dokumentation für ein **Lieferanten-Portal** mit Firmen-Modell (`SupplierCompany`), mehreren Usern pro Firma, B2B-Katalog für Materialwarte (MW) und optional **eigenem Vermietungsbetrieb** (Operator). Später: Reparatur-Workflow.

**Stand:** Mai 2026 · Konzept; **Paket 0–2 implementiert** — Address-Scope, `supplier_company`, `ROLE_SUPPLIER`, Session + Voter

Verwandt:

- [plan.md](./plan.md) — Implementierungs-Checkliste (Pakete 0–15)
- [material/combos/README.md](../material/combos/README.md) — Vorlagen- und Combo-Modell
- [activities/material-pipeline.md](../activities/material-pipeline.md) — Material-Import und Bestand
- [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) — UI-Bausteine

---

## 1. Zielbild

Eine **Lieferanten-Firma** (z. B. Tortuga AG, Hajk, Zelt-Reparaturbetrieb) kann mehrere **Mitarbeiter-User** mit Rollen haben und:

1. **Einen Verkaufskatalog** pflegen (Artikel, Preise, Mengen, Artikelnummern)
2. **Material-Vorlagen** pflegen (Stücklisten, Combos, Konfiguratoren — analog zu globalen Vorlagen)
3. **Lieferungen / Übergaben** erfassen — inkl. **Seriennummern** für serialisiertes Material
4. **Sichtbarkeit wählen:** nur Entwurf, bestimmte Departments, oder global (mit Freigabe)
5. *(später)* **Reparaturen** bearbeiten, die an ihn geroutet wurden

Der **Materialwart (MW)** nutzt den Katalog wie einen **Shop**: Budget kalkulieren, Artikel und Vorlagen **importieren** — ohne manuelles Excel-Mapping. Bei echten Lieferungen kann der Lieferant **Seriennummern mit übergeben**; der MW bestätigt den Import. Der Lieferant schreibt **nicht direkt** in den Department-Bestand.

**Organisation:** Mehrere User pro Firma (`SupplierMembership` mit Rollen). Ein User kann gleichzeitig in **mehreren Departments** und **einer oder mehreren Supplier-Firmen** sein (Abschnitt Phase 1).

**Fundament zuerst:** Phase 1 legt `SupplierCompany` + Membership + öffentliche Kontaktdaten — **Shop/Delivery kommt in Phase 2 auf dieselbe Firma**, ohne erneuten Umbau.

```text
Lieferant pflegt Katalog + Vorlagen
        ↓
Lieferant erstellt Übergabe (Lieferschein) inkl. Seriennummern
        ↓
MW stöbert / kalkuliert Budget (Shop) · sieht offene Übergaben
        ↓
MW importiert bewusst → MaterialItem + MaterialBatch(s) im Department
        (serialisiert: 1 Batch pro SN auf material_batch.serial_number)
        ↓
(später) MW weist Reparatur zu → Lieferant bearbeitet Ticket
```

---

## 1b. Drei Produktlinien (nicht vermischen)

| Linie | Wer | Was | Technik |
|-------|-----|-----|---------|
| **1 — Pfadi-Bestand** | MW im Verein | Eigenes Material, buchen, packen | `material_item` im **eigenen** Department |
| **2 — J&S-Liste** | J&S + Leiter | Extern bestellen; J&S pflegt nur die **Liste**, steuert **kein** B2B-Portal | `is_js_material`, `dept_js00000` — **kein** Supplier-Portal |
| **3 — Supplier B2B** | Tortuga & Co. → MW | Katalog, Lieferung, Import, SN (Phase 2+) | `SupplierCompany`, `supplier_*` |

> **Supplier-Portal = Linie 3 only.** Phase 1 baut das Firmen-Fundament; Shop/Delivery in Phase 2 **on top** derselben `SupplierCompany`.

### Sonderfall J&S — explizit out of scope

J&S ist **kein** Supplier im B2B-Sinn: Sie pflegen eine **Referenzliste**, damit Leiter **extern bestellen** können — sie steuern **keinen** Katalog/Import/Liefer-Workflow für MW.

| | J&S | Supplier-Portal |
|--|-----|-----------------|
| Modell | `is_js_material = true` | `SupplierCompany` + Phase 2 Shop |
| Department | `dept_js00000` **bleibt unberührt** beim Umbau | — |
| SupplierCompany | **Nein** | Ja |
| Import durch MW | **Nein** (Planung/Buchung J&S-Position) | Ja (Phase 2) |

**Beim Address-/Supplier-Umbau:** Migration betrifft nur `GLOBAL000000` → `scope=global`. **`dept_js00000` und J&S-Material ändern sich nicht.**

---

## 2. Ist-Zustand in eMatChef

| Bereich | Stand heute |
|--------|-------------|
| **Lieferanten** | `Address` mit Typ `supplier` und **`scope`** (`department` \| `supplier` \| `global`) — globale Legacy-Stammdaten: `scope=global` (seit Paket 0); registrierte Firmen: `scope=supplier` (Paket 1+) |
| **Material-Import** | MW importiert CSV/XLSX; Lieferanten werden aus global/lokal aufgelöst (`MaterialImportService`) — **ohne Seriennummern** |
| **Seriennummern heute** | Am `MaterialBatch.serial_number`; Erfassung via Material-Wizard oder Vorlage→Material (`TemplateController`) — nicht im CSV-Import |
| **Globale Vorlagen** | `MaterialTemplate` mit `scope=global` — bearbeitbar nur für Superadmin / Org- / Suborg-Chef |
| **Reparaturen** | `WorkshopTicket` — Zuweisung an **interne User** (`assigned_to_user_id`); öffentlicher QR-Status (`PublicWorkshopView`) |
| **Supplier-Login** | **`ROLE_SUPPLIER`** + Session `supplier_companies[]` (Paket 2); Supplier-Dashboard/UI ab Paket 4 |
| **User ↔ Department** | `Membership` — ein User kann **mehrere** Departments mit Rollen (`mw`, `dc`, …) haben |
| **Vermietung (Operator)** | `Activity.type = external`, Mieter über `address_id`; `MaterialItem.rental_price_*`, `rental_external_allowed` |

Relevante Code-Stellen (Referenz):

- `backend/src/Entity/Address.php` — Lieferanten-Adressen
- `backend/src/Entity/MaterialTemplate.php` — globale vs. Department-Vorlagen
- `backend/src/Service/MaterialImportService.php` — Import-Pipeline (Menge/Preis, keine SN)
- `backend/src/Entity/MaterialBatch.php` — `serial_number`, `qty` (serialisiert: qty 1 pro SN)
- `backend/src/Entity/MaterialItem.php` — `tracking_type`: `serialized` | `bulk`
- `backend/src/Controller/TemplateController.php` — Vorlage → Material inkl. SN pro Komponente, `canEditGlobalTemplates()`
- `frontend/src/components/material/MaterialCreateWizard.vue` — SN-Liste, Duplikat-Prüfung
- `backend/src/Entity/WorkshopTicket.php` / `WorkshopController.php` — Werkstatt
- `frontend/src/views/settings/MaterialImportSettingsView.vue` — MW-Import-UI
- `frontend/src/views/settings/TemplatesSettingsView.vue` — Vorlagen-Verwaltung
- `frontend/src/views/GlobalAddressesView.vue` — globale Lieferanten-Adressen
- `backend/src/Entity/Membership.php` — Vorbild für `SupplierMembership`
- `backend/src/Entity/Activity.php` — Typ `external`, Vermietung / Preise

---

## 3. Phasenplan (beschlossene Reihenfolge)

| Phase | Inhalt | Priorität |
|-------|--------|-----------|
| **1** | `SupplierCompany`, Membership, Onboarding, Navigation „Meine Firma“, öffentliche Kontaktdaten | Fundament |
| **2** | Katalog + Vorlagen + Übergaben (Shop, Budget, Import inkl. SN) | Größter MW-Nutzen |
| **3** | Reparatur-Workflow (Tickets an Lieferant) | Prozess + Datenschutz |

> **Begründung:** Reparaturen sind prozess- und datenschutzintensiver (Aktivität, Fotos, Kosten). Katalog/Shop baut auf bestehendem Import- und Vorlagen-Modell auf und bringt dem MW sofort Mehrwert.

---

## Phase 1 — Login, Firma & Rollen

### Ziel

Mehrere User pro Lieferanten-Firma; **Support-Onboarding**; Navigation **„Meine Firma“** mit öffentlichen Kontaktdaten. Phase 1 = Fundament — **kein** Katalog, Shop oder Delivery.

### Onboarding (Antrag → Support → Login)

**Keine Self-Registration für neue Firmen.**

```text
Firma stellt Antrag an Support (ematchef.ch)
        ↓
Plattform-Admin legt SupplierCompany an (status = active)
        ↓
Optional: Legacy-Adresse (scope=global) → Firma verknüpfen
        ↓
Ersten User als Firmen-admin anlegen → Login-Zugang wird zugesandt
        ↓
Firma pflegt „Meine Firma“ (öffentliche Adresse + Kontakt)
        ↓
Weitere User: Firmen-admin per Team / Join-Code (siehe unten)
```

| Schritt | Wer |
|---------|-----|
| Antrag / Freischaltung | Support + Plattform-Admin |
| Legacy `scope=global` → Firma | Plattform-Admin („Als Supplier-Firma aktivieren“) |
| Erster Login | Support stellt Zugang zu |
| Weitere Mitarbeiter | Firmen-**admin** (Membership + Join-Code) |

### Navigation & Login-Verhalten

**Ein Login** — kein separates Supplier-Produkt.

| User | Verhalten nach Login |
|------|----------------------|
| **Kein** Department-Membership | **Supplier-only:** Start im Supplier-Bereich (`/supplier/…`) |
| Department **und** Supplier | **Normale App** (Dashboard, Dept-Menü) **plus** Supplier-Bereich im Menü |

**Sidebar** (wenn Supplier-Membership vorhanden), direkt unter Dashboard:

```text
Dashboard
▼ Meine Firma                    ← aufklappbar (Toggle), nur bei Supplier-Membership
    Profil & Kontakt             ← Phase 1 (öffentliche Stammdaten)
    Team                         ← Phase 1, nur admin (User, Join-Code)
    (ab Phase 2: Katalog, …)     ← erst wenn Capability + Phase 2
─────────────────────────────────
Material / Aktivitäten / …       ← normales Department-Menü
```

Submenüs **minimal** — nur was Phase 1 braucht; spätere Punkte erst wenn Phase 2/3 existieren.

**MW-Seite (Phase 2):** eigenes **Toggle-Submenü** z. B. „Lieferanten-Shop“ — dort liegt der MW-Hauptnutzen; Supplier-Menü bleibt schlank.

### „Meine Firma“ — Inhalt Phase 1

| Bereich | Inhalt | Sichtbarkeit |
|---------|--------|--------------|
| **Profil & Kontakt** | Firmenname, `manufacturer_key`; **Adresse + Kontakt** (`address`, `scope=supplier`) | Kontaktdaten **öffentlich** für alle MW (Import, Picker, später Shop) |
| **Team** | Memberships, Join-Code für weitere User | nur **admin** |
| **Operator** | Toggle „Wir vermieten auch“ → `linked_department_id` | nur **admin** (siehe unten) |

**Rollen Phase 1:**

| | **admin** | **member** |
|--|-----------|------------|
| Profil & Kontakt bearbeiten | ja | **lesen** |
| Team / Join-Code | ja | nein |
| Operator aktivieren | ja | nein |
| Später Katalog/Delivery | schreiben (admin + Feinrollen) | nach Feinrolle |

Feinrollen (`catalog`, `delivery`, …) ab Phase 2/3 — Phase 1 nur **`admin`** + **`member`**.

**Join-Code:** für **weitere User innerhalb der Firma** (analog Department-Join) — **nicht** für die Erst-Anlage der Firma (die läuft über Support).

### Datenmodell (Firma, nicht 1:1 User)

**Nicht:** `User 1:1 SupplierProfile`

**Sondern** — analog zu `Department` + `Membership`:

```text
SupplierCompany                    (die Firma: Tortuga AG)
    ├── supplier_address_id        → Address (type=supplier, scope=supplier) — öffentliche Kontaktdaten
    ├── name, manufacturer_key     → z. B. "tortuga", "hajk"
    ├── capabilities[]             → catalog | delivery | templates | repairs | operator
    ├── linked_department_id       → optional, eigenes Vermietungs-Department (Organisation daraus ableitbar)
    ├── status                     → pending | active | suspended
    │
    └── SupplierMembership         (mehrere User pro Firma)
            ├── user_id
            ├── role               → Phase 1: admin | member · ab Phase 2/3: catalog | delivery | repairs | billing | viewer
            └── is_primary         → Hauptkontakt (optional)
```

> **Kein `linked_organisation_id`:** Organisation ist über `linked_department_id → department.organisation_id` ableitbar.

Unique-Constraint: `(supplier_company_id, user_id)` — ein User pro Firma höchstens eine Membership.  
Unique empfohlen: `supplier_company.supplier_address_id` ↔ eine Haupt-Adresse pro Firma.

### Betriebsmodi: B2B vs. Operator

| Modus | Capability | Was passiert |
|-------|------------|--------------|
| **B2B-Lieferant** | `catalog`, `delivery`, `templates`, … | Katalog/Übergaben für **fremde** Pfadi-Departments (Phase 2+) |
| **Operator (Vermieter)** | `operator` + `linked_department_id` | Normales eMatChef im **bestehenden** Department (Bestand, `Activity external`, Packen) |

**Operator anlegen — keine neue Organisation:** Organisation ist über `linked_department_id → department.organisation_id` bereits gegeben. Plattform-Admin legt bei Bedarf Department an (Support-Prozess); Firmen-**admin** setzt nur **`linked_department_id`** + Capability `operator`. User braucht dort zusätzlich normale Department-`Membership` (`mw`, …).

```text
                    ┌─────────────────────────────┐
                    │  SupplierCompany (eine Firma) │
                    └─────────────────────────────┘
           │                                    │
   B2B-Portal (/supplier/…)              linked Department
   SupplierCatalogItem (Phase 2)         MaterialItem, Activity external, …
           │                                    │
           ▼                                    ▼
   Pfadi-MW importiert                   Eigene Mieter, Pack, Preise
```

**Regel:** Kein paralleles Supplier-Lager — Vermietung = bestehendes `MaterialItem` + `Activity` im Department. Der B2B-Katalog ist das **Angebot nach aussen** (Phase 2).

**Brücke (später):** optional „Aus Bestand ins B2B-Katalog publizieren" (`MaterialItem` → `SupplierCatalogItem`, one-way).

### Multi-Kontext: User in mehreren Welten

Ein User kann **gleichzeitig**:

- in **mehreren Departments** sein (`Membership`)
- in **einer oder mehreren Supplier-Firmen** sein (`SupplierMembership`)
- im **linked Department** derselben Firma operieren (eigene `Membership`, wenn `operator`)

**UX:** Primär **ein** Menü mit Toggle **Meine Firma** (nicht zwei getrennte Apps). Supplier-only-User ohne Department starten direkt unter `/supplier/…`.

Die drei Beziehungen sind **orthogonal** — getrennte Security-Checks:

| Route | Prüfung |
|-------|---------|
| `/dept/{id}/…` | `Membership` für dieses `department_id` |
| `/supplier/{companyId}/…` | `SupplierMembership` für diese `supplier_company_id` |

**Nach Login — Kontextwechsel** (wie Department-Wechsel heute):

```text
○ Pfadi Zürich (mw)
○ Pfadi Bern (dc)
○ Tortuga AG — Lieferant (admin)
○ Tortuga Vermietung (mw)          ← linked Department derselben Firma
```

- **`ROLE_SUPPLIER`** (Symfony): gesetzt, wenn User ≥ 1 aktive `SupplierMembership` hat — unabhängig von Departments
- Supplier-Portal **ohne** Membership → kein Zugriff auf fremde Department-Daten
- B2B **ohne** automatischen Department-Zugriff — auch nicht über Supplier-Rolle allein

### Rollen innerhalb der Firma (Phase 2+)

Ab Phase 2 zusätzliche Feinrollen — Phase 1 nur **`admin`** / **`member`** (siehe oben):

| Rolle | Typische Rechte |
|-------|-----------------|
| **admin** | User einladen, Firmendaten, alle Firmenbereiche |
| **catalog** | Katalog + Vorlagen (Phase 2) |
| **delivery** | Übergaben / Lieferscheine + SN |
| **repairs** | Werkstatt-Tickets (Phase 3) |
| **billing** | Preise, Rechnungsreferenzen |
| **viewer** | nur lesen |
| **member** | Phase 1: Kontakt lesen, kein Team |

Capabilities (`catalog`, `operator`, …) hängen an **`SupplierCompany`**, nicht am User — die Membership-Rolle steuert, was der User **innerhalb der Firma** darf.

### Typische Onboarding-Szenarien

| Firma | Setup |
|-------|--------|
| Hersteller/Händler (Tortuga) | Antrag → Support → `SupplierCompany` mit `[catalog, delivery, templates]` — kein linked Department |
| Nur Vermieter, kein B2B | **Kein** `SupplierCompany` — normales Department (Pfadi oder eigener Betrieb) |
| Hersteller **und** Vermieter | `SupplierCompany` + Firmen-admin aktiviert `operator` → **`linked_department_id`** (bestehendes Dept, keine neue Org) |
| Werkstatt (Phase 3) | `capabilities: [repairs]` |
| **J&S** | **Kein** Supplier-Portal — nur `is_js_material` (Abschnitt 1b) |

### Technik Phase 1

- Entities: `SupplierCompany`, `SupplierMembership` (+ Migration)
- **Address-Scope-Umbau** (Abschnitt 8) — zusammen mit Phase 1 oder unmittelbar davor
- API: Session liefert `supplier_companies[]`; Admin CRUD Firma; Firmen-admin Team + Join-Code
- Endpoints (Konzept): `GET /api/me/supplier-companies`, Admin `POST/PATCH …/supplier-companies`, Memberships, Legacy „global → Firma“
- `User::getRoles()` → `ROLE_SUPPLIER` bei aktiver Membership; Voter `assertSupplierCompanyAccess`
- Routing: `/supplier/{companyId}/…`; Sidebar-Toggle **Meine Firma**
- **Nicht Phase 1:** Katalog, Shop, Delivery, Reparaturen, Self-Registration neuer Firmen

### Definition of Done

- [x] `SupplierCompany` + `SupplierMembership` (Migration)
- [x] Address-Scope auf `address` (Migration GLOBAL000000 → `scope=global`; **J&S `dept_js00000` unberührt**)
- [ ] Support-Onboarding: Admin legt Firma an, erster User admin, Login-Zugang
- [ ] Legacy `scope=global` → SupplierCompany (Admin-Aktion)
- [x] Login; `ROLE_SUPPLIER`; Session mit `supplier_companies[]`
- [ ] Supplier-only wenn kein Department; sonst Toggle **Meine Firma** (Profil & Kontakt, Team für admin)
- [ ] Öffentliche Kontaktdaten über `address` (`scope=supplier`) — für MW sichtbar
- [ ] Team: admin verwaltet Memberships + Join-Code
- [ ] Operator: admin setzt `operator` + `linked_department_id` (**keine** Org-Erstellung im Portal)
- [ ] Supplier-APIs: nur eigene Firma; `status=active`; kein Cross-Leak
- [ ] **Nicht in DoD:** Shop, Katalog, Delivery (Phase 2)

---

## Phase 2 — Katalog & Vorlagen (Shop für MW)

### Ziel

Lieferant pflegt **Verkaufskatalog**, **Material-Vorlagen** und **Lieferungen/Übergaben** (inkl. Seriennummern). MW kann stöbern, **Budget kalkulieren** und bewusst **importieren**.

### A) Verkaufskatalog (`SupplierCatalogItem`)

Neue Entität — **nicht** identisch mit `MaterialItem` im Department. Stammdaten des Artikels, **ohne** konkrete Seriennummern.

| Feld | Zweck |
|------|--------|
| `name`, `sku`, `manufacturer` | Identifikation |
| `tracking_type` | `serialized` \| `bulk` — steuert Import-Verhalten |
| `unit_price`, `currency` | Budget / Einkauf |
| `min_qty`, `pack_size` | z. B. Einzelstück vs. 24er-Pack |
| `category_hint` | Mapping-Hilfe beim Import |
| `description`, `external_ref` | Artikeltext, Händler-Artikelnr. |
| `is_active` | Ein/Aus im Shop |
| `visibility`, `status` | siehe Abschnitt 4 |

> **Seriennummern gehören nicht auf den Katalog-Artikel**, sondern auf eine konkrete **Übergabe** (Abschnitt D). Im Katalog steht z. B. „Zelt Tortuga 8P — CHF 4'200"; die SN `T-2026-00481` existiert erst beim gelieferten Exemplar.

### B) Material-Vorlagen (supplier-owned)

**Eigene Tabellen** `supplier_material_template` (+ Komponenten/Options analog Combo) — **nicht** `material_template` erweitern.

| Welt | Tabellen | Owner |
|------|----------|-------|
| Plattform / Pfadi | `material_template` (+ `material_template_*`) | `scope=global` \| `department` |
| Supplier B2B | `supplier_material_template` (+ `supplier_material_template_*`) | `supplier_company_id` |
| Bestand | `material_item`, `material_batch` | Department |

Struktur analog zu `MaterialTemplate` (inkl. Combo-Komponenten, Options-Gruppen, Deltas — siehe [combos/README.md](../material/combos/README.md)):

- Owner: `supplier_company_id` (Firma, nicht einzelner User)
- MW sieht im Shop: Name, ca. Preis, Komponentenliste
- Import: „Als Material anlegen“ — baut auf `TemplateController` / Vorlage→Material auf; SN pro Komponente kann aus Übergabe vorbefüllt werden
- Optional nach Admin-Freigabe: **Kopie** nach `material_template scope=global` — nicht dieselbe DB-Zeile

### C) Lieferung / Übergabe (`SupplierDelivery`)

Konkrete **Warenübergabe** an ein Department — hier werden Seriennummern mitgegeben.

```text
SupplierCatalogItem          (Was: Artikel, Preis, tracking_type)
        ↓
SupplierDelivery             (Wann: Auftrag, Lieferschein, Rechnung, Ziel-Department)
        ↓
SupplierDeliveryLine         (Menge, Preis, serial_numbers[] / Komponenten-SN)
        ↓
MW bestätigt Import          → MaterialItem + MaterialBatch(s) im Department
```

**Kopf (`SupplierDelivery`):**

| Feld | Zweck |
|------|--------|
| `department_id` | Ziel-Department (Pfadi-Kunde) |
| `supplier_company_id` | Absender-Firma |
| `delivery_ref` | Lieferschein / Auftragsnr. |
| `invoice_ref` | Rechnungsnr. (optional) |
| `delivered_at` | Lieferdatum |
| `status` | `draft` \| `submitted` \| `imported` \| `cancelled` |
| `notes` | Bemerkungen |

**Zeile (`SupplierDeliveryLine`):**

| Feld | Zweck |
|------|--------|
| `catalog_item_id` | Referenz auf Katalog-Artikel |
| `qty` | Menge (bei bulk) |
| `unit_price` | Preis dieser Lieferung (kann vom Katalog abweichen) |
| `serial_numbers` | Liste der SN (bei `tracking_type = serialized`) |
| `component_serials` | Bei physischer Kombo: SN je Komponente (Set + Teile) |

**Zwei Abläufe (beide möglich):**

| Variante | Beschreibung |
|----------|--------------|
| **A — Übergabe (empfohlen)** | Lieferant erstellt `SupplierDelivery` mit Zeilen + SN → MW sieht „Offene Übergabe" → importiert |
| **B — Shop + SN-Vorschlag** | MW wählt Katalog im Shop; Lieferant hat SN vorgeschlagen (read-only oder editierbar für MW) |

Variante A ist klarer für echte Lieferungen (Werk, Lieferschein, Hersteller-Registrierung).

### D) Import-Verhalten (Seriennummern)

Analog zu `TemplateController::createMaterialFromTemplate` und Material-Wizard:

| `tracking_type` | Import erzeugt |
|-----------------|----------------|
| **bulk** | 1× `MaterialBatch` mit `qty = N`, ohne `serial_number` |
| **serialized** | **N× `MaterialBatch`**, je `qty = 1`, je `serial_number` aus Übergabe |
| **physical_combo** | Haupt-Batch + Komponenten-Batches mit je SN (Set im Sack + Innenzelt + Stangen) |

**Regeln:**

- Lieferant **schlägt** SN vor; MW **bestätigt** Import (Korrektur bei Tippfehlern möglich)
- Duplikat-Prüfung im Department wie im Wizard (keine doppelte SN)
- **Virtuelle Kombo:** SN oft erst beim Packen (`on_issue`) — Lieferant liefert eher **Einzelteil-SN**, nicht die Kombo-Hülle
- Supplier-Import ist **besser als CSV**, weil SN bereits beim Hersteller/Händler bekannt sind

### E) MW-Seite („Shop")

Neuer Bereich für MW/DC (Einstellungen oder Material) — **Toggle-Submenü** (Phase 2, MW-Hauptnutzen):

- Lieferanten filtern, Katalog durchsuchen
- Tab **„Offene Übergaben"** — Lieferungen mit vorbereiteten Seriennummern
- Merkliste / Warenkorb
- **Budget-Summe** (Menge × Preis) — ohne automatischen Bestandseintrag
- **Import** als zweiter, bewusster Schritt — inkl. SN-Vorschau vor Commit
- Vorlagen: Vorschau + „In Department übernehmen"

### Definition of Done

- [ ] Lieferant: CRUD Katalog + Vorlagen (Entwurf)
- [ ] Lieferant: Übergaben anlegen (`SupplierDelivery` + Zeilen + SN)
- [ ] Lieferant: Sichtbarkeit wählen (Abschnitt 4)
- [ ] MW: Shop-Ansicht, Budget-Kalkulation
- [ ] MW: Offene Übergaben einsehen, SN prüfen/korrigieren
- [ ] MW: Import Katalog-Artikel → Bestand (bulk + serialisiert)
- [ ] MW: Import Übergabe → `MaterialBatch`(s) mit `serial_number`
- [ ] MW: Import Vorlage → Material (bestehende Logik erweitern, SN aus Übergabe)
- [ ] Kein direkter Schreibzugriff des Lieferanten auf Department-`material_item`

---

## Phase 3 — Reparaturen

### Ziel

MW weist Reparatur an externen Lieferanten/Werkstatt zu; Lieferant bearbeitet Ticket im Portal.

### Konzept

- `WorkshopTicket` erweitern: z. B. `assigned_to_supplier_company_id` (Link zu `SupplierCompany`) oder `assigned_to_supplier_address_id`
- Sichtbar für User mit `SupplierMembership` (Rolle `repairs`) in dieser Firma
- Supplier-Dashboard: offen / in Bearbeitung / abgeschlossen
- Lieferant: Status, Kostenvoranschlag, Fotos, Abschluss
- MW: Rückmeldung im bestehenden `WorkshopView`

### Datenschutz

Lieferant sieht **nur**:

- Material (Name, Seriennummer/Kiste falls relevant)
- Schadensbeschreibung, Fotos der Meldung
- Kontakt Materialwart / Department

**Nicht:** ganze Aktivität, Gruppennamen, Teilnehmer, interne Notizen.

### Parallel

Öffentlicher QR-Code (`PublicWorkshopView`) bleibt für Einzeltickets ohne Login nutzbar.

### Definition of Done

- [ ] MW kann Ticket an Lieferant zuweisen
- [ ] Lieferant sieht nur zugewiesene Tickets
- [ ] Status-Workflow + Rückmeldung an MW
- [ ] Datenschutz-Grenzen in API erzwungen

---

## 4. Sichtbarkeit: global oder nicht

Lieferant wählt beim Speichern von Katalog-Artikel oder Vorlage:

```text
Sichtbarkeit:
  ○ Nur für mich (Entwurf)
  ○ Bestimmte Departments  [Einladung / Freischaltung]
  ○ Global (alle Departments)  →  Hinweis: „Wird von eMatChef geprüft"
```

### Modell

| Feld / Status | Bedeutung |
|---------------|-----------|
| `status: draft` | Nur Lieferant |
| `status: published` + `visibility: departments` | Nur freigeschaltete Departments |
| `status: pending_review` + `visibility: global` | Lieferant beantragt global |
| `status: published` + `visibility: global` | Nach Admin-Freigabe — wie heutige `scope=global`-Vorlagen |

### Regeln

| Sichtbarkeit | Wer darf sehen | Freigabe |
|--------------|----------------|----------|
| **Entwurf** | Nur Lieferant | sofort |
| **Bestimmte Departments** | Eingeladene MW | Lieferant sofort |
| **Global** | Alle Departments | **Admin-Freigabe Pflicht** |

> **Warum Review für global?** Falsche Combo-Stücklisten oder Preise dürfen nicht ungeprüft in alle Departments wandern. Department-spezifische Freigabe kann der Lieferant selbst steuern.

---

## 5. Architektur-Grundsätze

### Katalog ≠ Department-Bestand

```text
Lieferanten-Daten (Katalog, Vorlagen-Entwürfe)
        ≠
Department-Bestand (MaterialItem, MaterialBatch, Verfügbarkeit, Reservierung)
```

Import ist immer ein **bewusster MW-Schritt** — analog zu CSV-Import heute.

### Seriennummern: Vorschlag ≠ Bestand

```text
Lieferant: SupplierDelivery.serial_numbers     (Vorschlag / Übergabe)
        ↓  MW bestätigt Import
Department: MaterialBatch.serial_number        (verbindlicher Bestand)
```

- SN liegen **nicht** im Katalog-Stamm, sondern in der **Übergabe**
- MW kann SN vor Import **korrigieren** (Tippfehler)
- Validierung wie im Material-Wizard: Pflicht-SN bei serialisiert, keine Duplikate im Department
- Optional später: herstellerweite SN-Eindeutigkeit prüfen (Tortuga-SN global eindeutig?)

### Preise

- **Listenpreis** — vom Lieferanten im Katalog
- **Vereinspreis** — im Department-Batch (`unit_price`) nach Import / Verhandlung

Getrennt halten; der Shop zeigt Listenpreise für Budget, der Bestand kann abweichen.

### Multi-Tenancy

- Eine **SupplierCompany**, viele Departments/Organisationen als Kunden
- Mehrere **User pro Firma** über `SupplierMembership`
- Saubere Isolation: Supplier sieht nur Daten **einer aktiven Firma** (Kontext) und zugewiesene Tickets
- Lieferanten-Stammdaten über **Address-Scope** (Abschnitt 8), nicht über sichtbares Global-Department

### Auth-Matrix (Ziel)

**Plattform (Pfadi / Admin):**

| Rolle | Shop | Import | Global freigeben | Ticket zuweisen |
|-------|------|--------|------------------|-----------------|
| **MW / DC** | ja | ja | — | ja (Phase 3) |
| **Superadmin / Org** | ja | ja | ja | ja |

**Innerhalb SupplierCompany** (Membership-Rolle):

| Rolle | Katalog | Übergaben + SN | Reparaturen | User verwalten |
|-------|---------|----------------|-------------|----------------|
| **admin** | ja | ja | ja | ja |
| **catalog** | ja | lesen | — | — |
| **delivery** | lesen | ja | — | — |
| **repairs** | — | — | ja (Phase 3) | — |
| **billing** | Preise | lesen | — | — |
| **viewer** | lesen | lesen | lesen | — |

Capabilities der Firma (`catalog`, `repairs`, …) schränken ein, **welche Bereiche** überhaupt existieren — Rollen steuern **Schreiben vs. Lesen** darin.

---

## 6. Anbindung an bestehenden Code

| Bestehend | Nutzung in Lieferanten-Portal |
|-----------|-------------------------------|
| `Address` + **neuer `scope`** | `department` \| `supplier` \| `global` — ersetzt GLOBAL000000-Hack (Abschnitt 8) |
| `SupplierCompany.supplier_address_id` | Haupt-Adresse der Firma (FK auf `address`) |
| `Membership` | Vorbild für `SupplierMembership`; User kann **beides** haben |
| `Activity` (type `external`) | Operator-Modus: Vermietung im linked Department |
| `MaterialItem.rental_*` | Operator-Modus: Mietpreise im eigenen Bestand |
| `MaterialBatch.serial_number` | Zielfeld beim Import aus `SupplierDeliveryLine` |
| `MaterialItem.tracking_type` | Steuert 1 Batch vs. N Batches beim Import |
| `MaterialTemplate` + Combo-Options | **Unverändert** für Plattform/Pfadi; Supplier spiegelt in `supplier_material_template_*` |
| `MaterialImportService` | Import aus Katalog / Übergabe (Erweiterung für SN) |
| `TemplateController::createMaterialFromTemplate` | Vorlage aus Shop → Material; SN-Logik wiederverwenden |
| `MaterialCreateWizard` | Duplikat-Prüfung SN, UX-Referenz für Import-Dialog |
| `canEditGlobalTemplates()` | Erweitern: Supplier eigene Drafts; Admin Global-Freigabe |
| `WorkshopTicket` | Phase 3: Zuweisung an Supplier |
| `ImportTemplatesCommand` / JSON-Vorlagen | Parallel bis Migration geklärt; langfristig Supplier-Portal als Quelle |

---

## 7. Risiken & offene Punkte

| Thema | Hinweis |
|-------|---------|
| **Scope** | Volles Portal ist groß (vergleichbar Combo-Umbau); Phasen strikt einhalten |
| **Globale Freigabe** | Ohne Review: Qualitäts- und Haftungsrisiko |
| **Namenskonflikte** | Import gleicher Artikelnamen — Duplicate-Handling wie beim CSV-Import (`add_batch` / `skip` / `create`) |
| **Seriennummern** | Duplikate im Department; bei physischer Kombo konsistente Set-/Teile-SN |
| **SN vs. Katalog** | SN nicht im Katalog-Stamm führen — nur in `SupplierDelivery` |
| **Vorlagen-Combo-Modell** | Supplier-Editor muss Options-/Delta-Schema mitführen (README Abschnitt 6) |
| **Rechtliches (Phase 3)** | Externe Werkstatt sieht personenbezogene Daten? Minimalprinzip |
| **Multi-Firma-User** | User in mehreren `SupplierCompany` — Kontextwechsel wie bei Departments |
| **Operator + B2B** | Zwei Rollensysteme parallel: Supplier-Rollen vs. Department-`Membership` im linked Dept |
| **Address-Umbau** | Zentral — `MaterialImportService`, `GlobalAddressController`, Wizard, BatchModal, Seeds |
| **GLOBAL000000** | Nach Migration deprecaten/löschen (Abschnitt 8) |
| **J&S** | Nicht Supplier-Portal — `dept_js00000` / `is_js_material` beim Umbau unberührt |
| **Self-Registration Firmen** | Nein — Antrag an Support; Join-Code nur für Team innerhalb bestehender Firma |

---

## 8. Datenbank-Entscheidungen

### 8.1 Address-Scope-Modell (ersetzt Global-Suppliers-Department)

**Problem heute:** Jede `address` braucht `department_id`. „Globale“ Lieferanten landen im technischen Department `GLOBAL000000` — ein Hack, den Superadmin in `GlobalAddressesView` pflegt.

**Ziel:** Explizite Zugehörigkeit auf der Adresse — **genau ein** Kontext:

```text
address
  scope = department   →  department_id gesetzt      (Lager, Kunde, lokaler Lieferant, …)
  scope = supplier     →  supplier_company_id gesetzt (registrierte Firma mit Portal)
  scope = global       →  beides NULL                 (systemweite Stammdaten, Org/Superadmin)
```

**CHECK-Constraint (Konzept):**

```sql
-- genau ein Owner-Kontext:
(scope = 'department' AND department_id IS NOT NULL
 AND supplier_company_id IS NULL)
OR
(scope = 'global' AND department_id IS NULL
 AND supplier_company_id IS NULL)
OR
(scope = 'supplier' AND supplier_company_id IS NOT NULL
 AND department_id IS NULL)
```

| scope | `type` (Beispiel) | Wer pflegt | Wer sieht (MW) |
|-------|-------------------|------------|----------------|
| `department` | `supplier`, `customer`, … | MW des Departments | nur dieses Department |
| `supplier` | `supplier` | Supplier-Firma (Portal) | alle Departments (wenn Firma `active`) |
| `global` | `supplier` | Superadmin / Org | alle Departments |

`type` = **Verwendungszweck** (Lieferant vs. Kunde). `scope` = **Besitz / Sichtbarkeit**.

`material_batch.supplier_id` bleibt FK auf `address.id` — unabhängig vom Scope.

### 8.2 SupplierCompany ↔ Address

```text
SupplierCompany
    supplier_address_id  →  address (scope=supplier, type=supplier)
```

Beim Anlegen einer Firma (Support/Admin):

1. `SupplierCompany` anlegen
2. `address` mit `scope=supplier`, `type=supplier`, Kontaktdaten — **öffentlich** für alle MW
3. `supplier_company_id` ↔ `supplier_address_id` in einer Transaktion (siehe CHECK-Constraint)
4. UNIQUE empfohlen: eine Haupt-Adresse pro Firma

**Registrierte Firmen** sind die primäre Lieferanten-Quelle für MW (Shop, Import, Wizard).

### 8.3 Global-Scope vs. registrierte Supplier

Zwei Ebenen „systemweit sichtbar“ — nicht verwechseln:

| Konzept | Wo | Bedeutung |
|---------|-----|-----------|
| **`address.scope = global`** | `address` | Kuratierte Lieferanten-Stammdaten (Legacy / noch nicht registriert) — Superadmin/Org |
| **Katalog/Vorlage `visibility = global`** | `supplier_catalog_item` (Phase 2) | Shop-Inhalt für alle MW — mit Admin-Freigabe |

Langfristig: globale Adressen schrittweise in `SupplierCompany` überführen; MW-Listen priorisieren **registrierte** Firmen (`status=active`).

### 8.4 MW-Abfrage Lieferanten (Ziel)

```text
Lieferanten für Picker / Import / Shop:
  1) SupplierCompany WHERE status = active  JOIN address (Haupt-Adresse)
  2) address WHERE scope = global AND type = supplier  (Legacy, optional ausblenden)
  3) address WHERE scope = department AND department_id = :current  (lokal)
```

Ersetzt: `loadSuppliers(GLOBAL000000)` in `MaterialImportService` und `GLOBAL_SUPPLIER_DEPARTMENT_ID` im Frontend.

### 8.5 Ablösung Global-Suppliers-Department

| Schritt | Aktion |
|---------|--------|
| Migration | Alle `address` in `GLOBAL000000` → `scope=global`, `department_id=NULL` |
| Code | `GlobalAddressController` filtert `scope=global`, nicht `department_id=GLOBAL000000` |
| UI | Global Addresses = globale Lieferanten-Stammdaten (bis Firmen-Modell); später Supplier-Admin |
| Später | `GLOBAL000000` / `GLOBALORG001` löschen, wenn nichts mehr referenziert |

Das Global-Department wird **überflüssig**, nicht nur versteckt.

### 8.6 Tabellen-Übersicht nach Phase

**Phase 1 — Fundament**

| Tabelle | Neu/Geändert |
|---------|----------------|
| `supplier_company` | neu |
| `supplier_membership` | neu |
| `address` | `department_id` nullable; `scope`, `supplier_company_id` |
| `user` | optional `last_used_supplier_company_id` |

`supplier_company` (Felder):

- `id`, `name`, `manufacturer_key`
- `supplier_address_id` FK → `address` (UNIQUE empfohlen)
- `capabilities` JSON
- `linked_department_id` FK NULL — **kein** `linked_organisation_id`
- `status`, `created_at`, `updated_at`

**Phase 2 — B2B**

| Tabelle | Neu |
|---------|-----|
| `supplier_catalog_item` | neu |
| `supplier_catalog_department_access` | optional |
| `supplier_delivery` | neu |
| `supplier_delivery_line` | neu |
| `supplier_material_template` | neu |
| `supplier_material_template_component` | neu |
| `supplier_material_template_option_*` | neu (Combo-Spiegel) |

**Phase 3**

| Tabelle | Geändert |
|---------|----------|
| `workshop_ticket` | `assigned_to_supplier_company_id` FK NULL |

**Bewusst ohne `supplier_*`-Prefix**

- `material_item`, `material_batch` — Operator-Bestand im linked Department
- `material_template` — Plattform/Pfadi-Vorlagen (parallel, nicht mischen)

### 8.7 Migrations-Reihenfolge (empfohlen)

```text
M1  address-Scope + Migration GLOBAL000000 → scope=global
M2  supplier_company + supplier_membership
M3  user.last_used_supplier_company_id (optional)
M4  Queries/UI: Import, Wizard, GlobalAddresses auf neues Modell
M5  (Phase 2) supplier_catalog_*, supplier_delivery_*, supplier_material_template_*
M6  (Phase 3) workshop_ticket.assigned_to_supplier_company_id
M7  GLOBAL000000 / GLOBALORG001 entfernen (wenn leer)
```

M1+M2 können in Phase 1 gebündelt oder nacheinander ausgerollt werden.

### 8.8 Legacy-Daten

Bestehende globale Lieferanten-Adressen (Tortuga, Hajk, …):

```text
address (scope=global nach Migration)
  → Plattform-Admin: „Als Supplier-Firma aktivieren“
  → SupplierCompany + scope global → supplier + supplier_company_id
  → erster User admin, Login via Support
```

Bestehende `material_batch.supplier_id` bleiben gültig (gleiche `address.id`).

---

## 9. Nächste Schritte (Implementierung)

1. **M1 — Address-Scope:** `address.scope`, nullable `department_id`, `supplier_company_id`; Migration GLOBAL000000 → `scope=global`
2. **M2 — Phase 1:** `supplier_company`, `supplier_membership`, Support-Onboarding, Navigation „Meine Firma“, Join-Code Team, `ROLE_SUPPLIER`
3. **M4 — Queries/UI:** `MaterialImportService`, Wizard, `GlobalAddressesView` auf Scope-Modell; `GLOBAL_SUPPLIER_DEPARTMENT_ID` entfernen
4. **Phase 2a:** `supplier_catalog_item` + Supplier-CRUD + MW-Shop (read-only + Budget)
5. **Phase 2b:** `supplier_delivery` / `supplier_delivery_line` — Übergaben inkl. Seriennummern
6. **Phase 2c:** `supplier_material_template_*` + Import-Pfad
7. **Phase 2d:** Sichtbarkeit + Global-Review-Queue für Admins
8. **Phase 3:** `workshop_ticket.assigned_to_supplier_company_id` + Supplier-Reparatur-Dashboard
9. **M7 — Cleanup:** Global-Department/Org entfernen wenn obsolet

---

## Siehe auch

- [plan.md](./plan.md) — Abarbeitbare Implementierungs-Checkliste (Pakete 0–15)
- [material/combos/plan.md](../material/combos/plan.md) — Combo-Umbau (Vorlagen-Editor-Referenz)
- [qr/qr-public-pages.md](../qr/qr-public-pages.md) — Öffentliche Workshop-Seite (QR)
