# Grossanlass — Kostenübersicht Material & Logistik

Spezifikation zum Abarbeiten. Ergänzt [README §3.7](./README.md#37-beschaffung--budget--kosten) und [Konzept §12.2](./20260823_New_concept.md#122-herkunft-leihen-kaufen-kaufen-und-wieder-verkaufen).

**Stand:** 28. August 2026 · **Status:** Spezifikation (entschieden) · Umsetzung nach Phasen unten

**Verwandt:** [README.md](./README.md) · [20260823_New_concept.md](./20260823_New_concept.md) · [accounting.md](../accounting.md) (nur Abgrenzung — **nicht** anbinden)

---

## 1. Ziel

Eine **Kostenübersicht für Material & Logistik** im Grossanlass-Dept: Einkauf, Miete, Weiterverkauf (plus Sachleistung und Nebenkosten). Logistik **organisiert**; das Budget kann bei **einem anderen Ressort** liegen.

Kein Pfadi-`/accounting`. Grobe Soll/Ist-Spur, Cash vs. Netto, Rahmen pro Zahler.

---

## 2. Entschieden (nicht wieder öffnen)

| # | Entscheidung | Wert |
| --- | --- | --- |
| D1 | Zahler ≠ Organisator | Organisator immer Material & Logistik (MW). Zahler = Ressort-Budget oder Anlass-Topf. |
| D2 | Ein Zahler pro Zeile | Kein Prozent-Split in v1. Zwei Zahler → zwei Zeilen. |
| D3 | Anlass-Topf = Logistik-Knoten | Kein synthetischer `payer_group_id = NULL`-Topf. Der Gesamtrahmen sitzt auf dem konfigurierten Ressort (`logistics_group_id`, z. B. Material & Logistik). Setzen in der **Ressort-Übersicht** (Button an Ressort/Unterressort, Flag danach nur dort). |
| D4 | Rahmen | Gesamtrahmen **und** Rahmen pro Zahler. Kategorie-Rahmen bleibt MW-Planung, nicht Zahler. |
| D5 | Sachleistung | `loan` bleibt in der Liste, zählt **0 CHF** (ausser Nebenkosten). |
| D6 | Datenbank | Eigene Tabellen `department_grossanlass_*`. Kein `accounting_*`, keine Follow-ups, keine Kostenstellen. |
| D7 | Kauf ins Eigenlager | Pro Zeile `asset_treatment`: `expense` (Default) oder `inventory`. |
| D8 | Ledger ist Quelle | Offerte/Bestellung sind Belege. Summe der Übersicht = `department_grossanlass_cost`. |
| D9 | Eine Haupt-Art pro Position | Holz-Kauf und Gerüst-Miete nicht in derselben Beschaffungsposition. |

---

## 3. Ist vs. Soll

| Ist | Problem | Soll |
| --- | --- | --- |
| `activity_grossanlass_procurement_line.group_id` | nur **Bedarf** | zusätzlich **Zahler** auf der Cost-Zeile |
| Finanzen Ist = `procurement_order.cost_chf` | nur Kauf-Logik | Ledger mit Arten Miete / Leih / Weiterverkauf / Nebenkosten |
| Zusage `origin` loan/buy/buy_resale | keine Franken | Cost-Zeile verknüpft (`commitment_id`) |
| Finanzen «pro Ressort» | rollt Bedarf | Toggle **Nach Zahler** / **Nach Bedarf** |
| `activity_grossanlass_procurement_finance.rahmen_chf` | nur gesamt | plus `department_grossanlass_budget` pro Zahler |

Beschaffungs-Workflow (Bedarf → Anfragen → Offerten → Bestellungen → Erhalten) bleibt. Diese Spec ändert **nicht** den Mail-/Zusage-Fluss, sondern Geld und Rahmen.

---

## 4. Drei Rollen

| Rolle | Bedeutung | Speicherung |
| --- | --- | --- |
| **Bedarf** | Wer das Material/Fahrzeug braucht | `requesting_group_id` (Default aus `procurement_line.group_id` / Wunsch) |
| **Organisator** | Wer anfragt, mietet, kauft, verkauft | immer MW / Material & Logistik — **kein** DB-Feld |
| **Zahler** | Wessen Rahmen belastet wird | `payer_group_id`; `NULL` = zentraler Logistik-Topf |

Beispiel: Bau will Gerüst. Logistik mietet. Zahler = Bau. MW-Übersicht: «organisiert, zahlt Bau». Bau in Mein Ressort: Miete auf ihrem Rahmen.

Default beim Anlegen: `payer_group_id = requesting_group_id`. MW überschreibt vor «Nehmen».

`payer_group_id` und `requesting_group_id` müssen Groups **desselben** Grossanlass-Depts sein (oder Zahler `NULL`).

---

## 5. Kostenarten

`cost_kind` — Geldfluss, nicht nur Herkunft der Zusage.

| Wert | UI | Cash raus | Gegenbuchung | Netto-Anlass |
| --- | --- | --- | --- | --- |
| `purchase` | Einkauf | Kaufpreis | — | `cash_out` wenn `expense`; **0** wenn `inventory` |
| `rental` | Miete | Miete + Depot | Depot zurück | Miete (`cash_out − deposit_returned`; Depot nicht in Netto) |
| `loan` | Leih / Sachleistung | 0, plus optionale Nebenkosten | — | 0 bzw. nur Ancillary |
| `buy_resale` | Ankauf / Weiterverkauf | Kaufpreis | Verkaufserlös | `cash_out − proceeds_actual` |
| `ancillary` | Nebenkosten | Transport, Reinigung, Schaden, … | — | voll `cash_out` |

Herkunft an der Zusage (`DepartmentGrossanlassCommitment.origin`) und `cost_kind` müssen zusammenpassen:

| `origin` | Erlaubtes `cost_kind` (Hauptzeile) |
| --- | --- |
| `loan` | `loan` oder `rental` (bezahlt vs. Sachleistung) |
| `buy` | `purchase` |
| `buy_resale` | `buy_resale` |

`ancillary` immer zusätzlich, unabhängig von `origin`.

**Miete vs. Leih:** nicht in denselben Topf. Bezahlte Leihe = `rental`. Gratis-Sachleistung = `loan`.

---

## 6. Cash vs. Netto

Zwei Totale überall (Karten, Tabellen, Ressort-Sicht). Nicht als DB-Spalten speichern — ableiten.

```
cash     = SUM(cash_out_chf)                    -- was bis jetzt raus muss / raus ist
netto    = SUM(netto_line)
netto_line:
  purchase + expense     → cash_out
  purchase + inventory   → 0
  rental                  → cash_out − deposit_returned
  loan                    → 0
  buy_resale              → cash_out − proceeds_actual
  ancillary               → cash_out
```

Null-Beträge als 0 behandeln, nicht als SQL-NULL in der Summe (NULL = «noch nicht erfasst» → Zeile zählt 0 in der Summe, bleibt aber sichtbar).

**Soll** = `soll_chf` (gewählte Offerte / Mietkondition). Delta = Soll − Ist-Netto bzw. Soll − Cash, in der UI beschriften («Offerten − Netto», «Offerten − Cash»).

Weiterverkauf: erwarteter Erlös (`proceeds_expected_chf`) nur als Prognose, nicht in Ist-Netto. Ist-Netto erst mit `proceeds_actual_chf`.

---

## 7. Datenmodell

Dept-weit, nicht pro Activity. Prefixe `department_grossanlass_*`.

### 7.1 `department_grossanlass_cost` (Ledger)

Quelle für die Kostenübersicht. 1:n von Beschaffungsposition bzw. Zusage (Hauptzeile + optionale Ancillary).

```
department_grossanlass_cost
  id                         CHAR(12) PK
  department_id              FK → department  ON DELETE CASCADE
  procurement_line_id        CHAR(12) NULL FK → activity_grossanlass_procurement_line ON DELETE SET NULL
  commitment_id              CHAR(12) NULL FK → department_grossanlass_commitment ON DELETE SET NULL
  cost_kind                  VARCHAR  purchase | rental | loan | buy_resale | ancillary
  asset_treatment            VARCHAR  expense | inventory   -- nur purchase; sonst NULL
  requesting_group_id        CHAR(12) NULL FK → group ON DELETE SET NULL
  payer_group_id             CHAR(12) NULL FK → group ON DELETE RESTRICT
  category_id                CHAR(12) NULL FK → activity_grossanlass_procurement_category ON DELETE SET NULL
  label                      VARCHAR(255)
  partner_address_id         CHAR(12) NULL FK → address ON DELETE SET NULL
  soll_chf                   DECIMAL(12,2) NULL
  cash_out_chf               DECIMAL(12,2) NULL
  deposit_chf                DECIMAL(12,2) NULL
  deposit_returned_chf      DECIMAL(12,2) NULL
  proceeds_expected_chf     DECIMAL(12,2) NULL
  proceeds_actual_chf       DECIMAL(12,2) NULL
  status                     VARCHAR  planned | committed | paid | for_sale | sold | returned | cancelled
  notes                      TEXT NULL
  created_at, updated_at
```

Indexe: `department_id`; `(department_id, payer_group_id)`; `(department_id, requesting_group_id)`; `(department_id, cost_kind)`; `procurement_line_id`; `commitment_id`.

Constraints (App + DB wo sinnvoll):

- `department` muss `is_grossanlass`.
- Groups und Category gehören zum selben `department_id`.
- `asset_treatment` nur wenn `cost_kind = purchase`, sonst NULL. Default beim Anlegen: `expense`.
- `deposit_*` nur bei `rental` relevant; andere Arten NULL.
- `proceeds_*` nur bei `buy_resale`.
- Mindestens eines von `procurement_line_id` / `commitment_id` / `label` (Ad-hoc-Nebenkosten ohne Linie erlaubt, `label` Pflicht).

Entity: `DepartmentGrossanlassCost` analog `DepartmentGrossanlassCommitment`.

### 7.2 `department_grossanlass_budget` (Rahmen)

```
department_grossanlass_budget
  id                         CHAR(12) PK
  department_id              FK → department ON DELETE CASCADE
  payer_group_id             CHAR(12) NULL FK → group ON DELETE CASCADE
  rahmen_chf                 DECIMAL(12,2) NOT NULL
  updated_at
  UNIQUE (department_id, payer_group_id)   -- MySQL: NULL-Zahler = eine Gesamt-Zeile
```

- `payer_group_id IS NULL` → Gesamtrahmen / zentraler Logistik-Topf (ersetzt fachlich `activity_grossanlass_procurement_finance.rahmen_chf`).
- `payer_group_id` gesetzt → Rahmen dieses Ressorts (Zahler).

Kategorie-Rahmen bleibt auf `activity_grossanlass_procurement_category.rahmen_chf` (MW-Planung).

**Migration Finance:** bestehende `procurement_finance.rahmen_chf` einmalig nach `budget` mit `payer_group_id NULL` kopieren. Danach ist `budget` Quelle. `GET overview` / Save-Rahmen schreiben `budget`. Die alte Spalte kann eine Phase als Spiegel bleiben, dann droppen — nicht parallel pflegen.

### 7.3 Bestehende Tabellen — nicht umbiegen

| Tabelle | Rolle danach |
| --- | --- |
| `activity_grossanlass_procurement_line` | Bedarf, Status-Kanban, `group_id` = Bedarf. Optional später `cost_kind` Spiegel — **nicht** Pflicht, Ledger reicht. |
| `activity_grossanlass_procurement_quote` | Beleg Soll; beim «Offerte wählen» `cost.soll_chf` setzen |
| `activity_grossanlass_procurement_order` | Beleg Kauf (`cost_chf`); erzeugt/updated Cost `purchase`/`buy_resale` `cash_out_chf` |
| `activity_grossanlass_procurement_finance` | nach Migration obsolet für Rahmen |
| `department_grossanlass_commitment` | Objekt/Herkunft; Cost über `commitment_id` |

**Keine** Franken in `commitment.item_details` JSON.

### 7.4 Verboten

- `AccountingBooking`, `AccountingAcquisitionFollowUp`, `AccountingCostCenter`, Activity-Verbrauch
- Shared Ledger mit einem Pfadi-Dept
- Abschreibungsmodul / Bexio in v1

---

## 8. Status der Cost-Zeile

| `status` | Bedeutung |
| --- | --- |
| `planned` | Art/Zahler da, noch keine Bindung |
| `committed` | Offerte/Mietkondition / Bestellung ausgelöst |
| `paid` | Cash erfasst (Rechnung/Miete geflossen) |
| `for_sale` | nur `buy_resale`: retour intern, noch zu verkaufen |
| `sold` | `buy_resale`: (teilweise) verkauft; `proceeds_actual` gesetzt |
| `returned` | Leih/Miete zurück beim Geber; Depot-Thema abgeschlossen |
| `cancelled` | nicht genommen / storniert; zählt nicht in Cash/Netto |

Übergänge grob:

```
planned → committed → paid
                ↘ returned          (loan / rental)
paid → for_sale → sold              (buy_resale)
beliebig → cancelled
```

Teilverkauf: Menge/Erlös auf derselben Zeile aktualisieren; Status `sold` erst wenn nichts mehr offen (sonst `for_sale` mit Teil-`proceeds_actual`). v1: eine Zeile, kein Split nach Käufer.

---

## 9. Lebenszyklus — wann die Zeile entsteht

| Zeitpunkt | Aktion |
| --- | --- |
| Bündeln oder Zuteilung / Nehmen | Cost anlegen: `cost_kind`, Zahler, Bedarf, Kategorie, Label. Pflicht **vor** Nehmen, damit Mails nicht «Leihgabe» versprechen. |
| Offerte gewählt | `soll_chf` = Offertbetrag; Status mindestens `committed` |
| Bestellung (nur Kauf / buy_resale) | `cash_out_chf` aus `order.cost_chf`; Status `committed` oder `paid` |
| Mietkondition (kein Order-Zwang) | `soll_chf` / `cash_out_chf`, `deposit_chf` an der Cost-Zeile |
| Erhalten + Zusage | `commitment_id` setzen |
| Depot zurück | `deposit_returned_chf`; bei voller Rückgabe + Objekt retour → `returned` |
| Zu verkaufen | `buy_resale` → `for_sale`, `proceeds_expected_chf` |
| Verkauf (Gast-Abteilung oder extern) | `proceeds_actual_chf`; `sold` wenn erledigt |

Bestellung **nur** für echten Einkauf. Miete/Leih ohne `procurement_order`.

Nebenkosten: neue Cost-Zeile `ancillary`, gleicher `procurement_line_id` und/oder `commitment_id`, **gleicher Zahler** Default (überschreibbar).

---

## 10. UI

### 10.1 Kosten = eigene Seite

View [`GrossanlassBeschaffungFinanzenView.vue`](../../frontend/src/views/grossanlass/GrossanlassBeschaffungFinanzenView.vue) unter **`/kosten`**, Nav-Eintrag **Kosten** (MW/DC). Nicht Pfadi-`/accounting`. Alte URL `/beschaffung/finanzen` leitet um.

Beschaffung-Tabs bleiben der Workflow Bedarf → Anfragen → Offerten → Zuteilung → Erhalten.

**Karten oben**

- Rahmen gesamt, Rest (Rahmen − Cash und/oder Rahmen − Netto — beide zeigen, Rest-Label klar)
- Cash gesamt, Netto gesamt
- Drei Arten-Karten: Einkauf / Miete / Weiterverkauf (Cash + Netto)
- Unverändert nützlich: offene Offerten, bestellt nicht erhalten (aus Beschaffungs-Status, nicht aus Cost)

**Toggle:** Nach Zahler | Nach Bedarf

**Filter:** `cost_kind`, Kategorie, Status, «Zahler nicht Logistik» (nicht der Logistik-Knoten / Anlass-Topf)

**Tabelle Zeile:** Label, Partner, Art, Bedarf-Ressort, Zahler (Logistik-Knoten oder anderes Ressort), Menge wenn bekannt, Soll, Cash, Erlös/Depot, Netto, Status.

Rahmen-Inputs: Gesamtrahmen = Budget des Logistik-Knotens; Tabelle **pro Zahler**. Kategorie-Rahmen-Block behalten (MW).

### 10.2 Position / Zusage

Beim Bündeln und im Zusage-Dialog:

- Herkunft / `cost_kind` (Miete vs. Leih getrennt)
- Zahler (Dropdown Ressort-Baum; Logistik-Knoten = Anlass-Topf, kein zweiter NULL-Eintrag)
- Kauf: `asset_treatment`
- `buy_resale`: erwarteter Verkaufspreis (optional bis `for_sale`)

### 10.3 Mein Ressort

Nur Cost-Zeilen, deren `payer_group_id` im eigenen Ressort-Baum liegt (Wurzel + Kinder). Optional zweiter Block «Bedarf bei uns, zahlt ein anderes Ressort» — v1 darf weggelassen werden.

RL sieht **nicht** den Gesamt-OK-Rahmen, nur den eigenen Zahler-Rahmen + eigene Zeilen.

### 10.4 i18n

Keys unter `grossanlass.beschaffung.kosten.*` und bestehende `finanzen.*` erweitern, nicht ersetzen. `de.json` / `en.json`.

---

## 11. API

Fassade unter dem Grossanlass-Dept, analog Beschaffung. Kein Accounting-Controller.

```
GET    /api/departments/{id}/grossanlass/beschaffung/costs
POST   /api/departments/{id}/grossanlass/beschaffung/costs
GET    /api/departments/{id}/grossanlass/beschaffung/costs/{costId}
PATCH  /api/departments/{id}/grossanlass/beschaffung/costs/{costId}
DELETE /api/departments/{id}/grossanlass/beschaffung/costs/{costId}

GET    /api/departments/{id}/grossanlass/beschaffung/budgets
PUT    /api/departments/{id}/grossanlass/beschaffung/budgets
       body: { payer_group_id: string | null, rahmen_chf: number }

GET    /api/departments/{id}/grossanlass/beschaffung/overview
       — erweitern: totals.cash_chf, netto_chf, by_kind[], by_payer[], by_requester[]
```

`overview` (Soll):

```
totals: rahmen_chf, cash_chf, netto_chf, soll_chf,
        rahmen_minus_cash_chf, rahmen_minus_netto_chf,
        open_quotes_count, ordered_not_received_count
by_kind: [{ cost_kind, cash_chf, netto_chf, soll_chf, line_count }]
by_payer: [{ payer_group_id, payer_name, rahmen_chf, cash_chf, netto_chf, line_count }]
          logistics_group_id = Anlass-Topf (eine Zeile, nicht extra NULL)
by_requester: analog heutiges by_group (Bedarf)
by_category: bleibt (Soll/Ist aus Cost, nicht nur Order)
```

Query `GET costs`: `payer_group_id`, `requesting_group_id`, `cost_kind`, `status`, `category_id`. RL: Server filtert auf eigenen Baum wenn nicht MW/DC.

Bestehende Line/Quote/Order-APIs: beim Select-Quote und Create-Order **Cost mitziehen** (Service, nicht der Client als einzige Quelle).

---

## 12. Rechte

| Aktion | MW/DC | RL | U |
| --- | --- | --- | --- |
| Übersicht gesamt, Rahmen gesamt, alle Zeilen | ✓ | — | — |
| Cost anlegen/ändern, Art/Zahler, Budgets | ✓ | — | — |
| Eigenen Zahler-Rahmen + eigene Zahler-Zeilen lesen | ✓ | ✓ | — |
| Eigenen Rahmen setzen | ✓ | — v1 | — |

«Eigen» = `payer_group_id` im Subtree der RL-GroupMembership.

---

## 13. Ableitung Netto / Rest (Server)

Eine Funktion im Service, nicht in jeder Vue-Datei kopieren. PHP-Unit-Tests für:

- purchase expense vs inventory
- rental mit/ohne Depot zurück
- buy_resale vor/nach Erlös
- loan immer 0
- cancelled ignorieren
- NULL-Beträge = 0 in Summen

Frontend darf dieselbe Formel spiegeln für Optimistic UI; Quelle bleibt API `netto_chf` pro Zeile (mitgeben, nicht nur totals).

---

## 14. Phasen (Abarbeiten)

Checkboxen in diesem File abhaken. Nicht alles in einem PR.

### Phase K1 — Modell

- [x] Entity `DepartmentGrossanlassCost` + Migration
- [x] Entity `DepartmentGrossanlassBudget` + Migration
- [x] Constants analog `DepartmentGrossanlassCommitment::ORIGINS`
- [x] Finance-Rahmen → Budget `payer_group_id NULL` migrieren
- [x] Keine Accounting-Imports in den neuen Services

**DoD:** Schema steht; bestehende Beschaffung läuft unverändert.

### Phase K2 — Art + Zahler

- [x] Cost beim Bündeln / Nehmen anlegen (Default Zahler = Bedarf, Kind Default `loan` oder aus Origin-Map)
- [x] UI: Herkunft **Miete** vs **Leih** getrennt; Zahler-Dropdown (Logistik-Knoten = Anlass-Topf)
- [x] Anlass-Topf in der Ressort-Übersicht setzen (Button an Ressort/Unterressort, Flag sobald gesetzt)
- [x] Kauf: `asset_treatment` (Default expense)
- [x] Validierung: eine Haupt-Art pro `procurement_line_id` (Ancillary ausgenommen)

**DoD:** Keine Zusage ohne Cost-Art; Zahler gespeichert.

### Phase K3 — Übersicht

- [x] `overview` um Cash/Netto, `by_kind`, `by_payer`, `by_requester` erweitern
- [x] Finanzen-View: Karten, Toggle Zahler/Bedarf, Filter
- [x] Rahmen gesamt + pro Zahler speichern über `budgets`
- [x] Tests Overview-Aggregation

**DoD:** MW sieht Cash vs. Netto und wer zahlt, ohne Accounting.

### Phase K4 — Miete / Depot / Bestellung → Ledger

- [x] Quote selected → `soll_chf`
- [x] Order create/update → Cost `purchase`/`buy_resale` `cash_out_chf`
- [x] Miete: Beträge an Cost ohne Order
- [x] Depot zurück

**DoD:** Ist kommt nicht mehr allein aus `procurement_order`.

### Phase K5 — Weiterverkauf

- [x] Status `for_sale` / `sold`
- [x] `proceeds_expected_chf` / `proceeds_actual_chf`
- [x] Netto = Kauf − Ist-Erlös
- [x] Gast-Verkauf und externer Verkauf **dieselbe** Erlös-Spalte (kein zweites Konto)

**DoD:** Prognose vs. Ist-Erlös in der Übersicht.

### Phase K6 — Mein Ressort

- [x] Gefilterte Cost-Liste + eigener Rahmen
- [x] API erzwingt Filter für RL

**DoD:** Bau sieht nur Bau-Zahler-Zeilen und Bau-Rahmen.

---

## 15. Out of scope (v1)

- Pfadi-Buchhaltung, Follow-ups, Beleg-Workflow wie Accounting, Bexio
- Prozent-Splits / mehrere Zahler auf einer Zeile
- Automatische Rechnung an Gast-Pfadi-Depts (nur Erlös-Betrag auf der Cost-Zeile)
- Vereins-Abschreibung / Nutzungsdauer
- Pflicht-Ressort «Logistik» / `logistics_group_id` in Config
- RL setzt eigenen Rahmen
- CSV-Export (kann folgen, nicht blockierend)
- Franken in Commitment-JSON

---

## 16. Code-Orientierung (bestehend)

| Bereich | Pfad |
| --- | --- |
| Finanzen UI | `frontend/src/views/grossanlass/GrossanlassBeschaffungFinanzenView.vue` |
| Overview API | `frontend/src/api/grossanlassProcurement.ts` (`GrossanlassProcurementOverview`) |
| Overview Server | `backend/src/Service/Grossanlass/GrossanlassProcurementService.php` (`…overview`) |
| Rahmen heute | `ActivityGrossanlassProcurementFinance` |
| Herkunft Zusage | `DepartmentGrossanlassCommitment::ORIGIN_*` |
| Zusage-Dialog | `frontend/src/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue` |
| UI-Bausteine | `ETextField`, `EButton`, `EEmptyState`, `PageShell` — [vuetify-standards](../ui/vuetify-standards.md) |

Keine neue Sidebar. Kein `GrossanlassLayout`.

---

## 17. Kurz-Beispiele

**Gerüst, zahlt Bau**

- Bedarf Bau, Zahler Bau, `rental`, Cash 4 000 + Depot 1 000
- Nach Rückgabe Depot 1 000: Cash immer noch 5 000 erfasst, Netto 4 000, Status `returned`

**Transporter Weiterverkauf, zahlt Logistik zentral**

- `payer_group_id` NULL, `buy_resale`, Kauf 12 000, erwartet 10 500, verkauft 10 200
- Cash 12 000, Netto 1 800

**Paletten Eigenkauf, bleibt im Bestand**

- `purchase` + `inventory`, Zahler zentral
- Cash 800, Netto 0 (Anlass nicht belastet; OK sieht die Cash-Spitze trotzdem)

**Sachleistung Gator**

- `loan`, Soll/Cash/Netto 0; Zeile sichtbar für Logistik; Ancillary Anlieferung 150 auf gleicher oder neuer Zeile, Zahler wie Hauptzeile oder überschrieben
