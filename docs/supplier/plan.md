# Umbauplan: Lieferanten-Portal (Supplier Portal)

Abarbeitbare Checkliste für Phase 1–3. Das **Warum/Zielmodell** steht in [supplier-portal.md](./supplier-portal.md). Dieser Plan = **Was & in welcher Reihenfolge**.

**Stand:** Mai 2026 · Phase 1 in Paketen 0–8; Phase 2/3 als grobe Pakete (Detail folgt mit Phase-2-Start). **Erledigt: Paket 0–6**

---

## Leitprinzipien

- **Phasen strikt** — Phase 1 = Firma + Membership + Kontakt + Navigation; **kein** Katalog, Shop, Delivery, Reparaturen.
- **Drei Produktlinien trennen** — Pfadi-Bestand · J&S (`dept_js00000`, `is_js_material`) · Supplier B2B. J&S und Supplier-Portal **nicht** vermischen (siehe [supplier-portal.md §1b](./supplier-portal.md#1b-drei-produktlinien-nicht-vermischen)).
- **Address-Scope zuerst** — `GLOBAL000000`-Hack ersetzen; Migration betrifft nur globale Lieferanten, **nicht** `dept_js00000`.
- **SupplierCompany = Fundament** — Phase 2 Shop/Delivery baut auf derselben Firma auf, kein Rebuild.
- **Eigene Tabellen für B2B** — `supplier_*`; `material_template` und Pfadi-Bestand **nicht** erweitern.
- **Jedes Paket ist in einem Chat erledigbar** und hinterlässt einen lauffähigen Stand (Build + relevante Tests grün).
- **Reihenfolge einhalten** — spätere Pakete bauen auf früheren auf (Spalte „Hängt ab von").
- **Doku mitführen** — bei Modell-/Entscheidungsänderungen `supplier-portal.md` aktualisieren.
- **Übersetzung** — nur `de.json` und `en.json` nachführen.

## Status-Legende

`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt

---

## Übersicht

| # | Paket | Phase | Größe | Hängt ab von | Status |
|---|-------|-------|-------|--------------|--------|
| 0 | Address-Scope + Migration `GLOBAL000000` | 1 | M | – | [x] |
| 1 | `supplier_company` + `supplier_membership` | 1 | M | 0 | [x] |
| 2 | Auth: `ROLE_SUPPLIER`, Session, Voter | 1 | S–M | 1 | [x] |
| 3 | Plattform-Admin: Onboarding + Legacy-Promote | 1 | M | 1, 2 | [x] |
| 4 | Frontend-Shell: Routing, Layout, Sidebar | 1 | M | 2 | [x] |
| 5 | „Meine Firma“: Profil & Kontakt | 1 | M | 1, 2, 4 | [x] |
| 6 | Team + Join-Code | 1 | M | 2, 5 | [x] |
| 7 | Operator: Capability + `linked_department_id` | 1 | S | 1, 5 | [ ] |
| 8 | MW-Listen: Import, Global Addresses, Konstanten | 1b | M | 0, 1 | [ ] |
| 9 | Katalog `supplier_catalog_item` + Supplier-CRUD | 2a | L | 1–7 | [ ] |
| 10 | Übergaben `supplier_delivery*` + SN | 2b | XL | 9 | [ ] |
| 11 | Vorlagen `supplier_material_template_*` | 2c | XL | 9 | [ ] |
| 12 | MW-Shop, Budget, Import-Pfade | 2d | XL | 9–11 | [ ] |
| 13 | Sichtbarkeit + Global-Review | 2e | M | 9, 12 | [ ] |
| 14 | Reparaturen an Lieferant | 3 | L | 1–7 | [ ] |
| 15 | Cleanup: `GLOBAL000000` / `GLOBALORG001` | 1/2 | S | 0, 8 | [ ] |

> **Phase 1 DoD:** Login, `SupplierCompany`, Team, öffentliche Kontaktdaten, Sidebar **Meine Firma** — ohne Shop. Paket 8 kann parallel zu 4–7 laufen, sollte aber vor Phase-2-Start fertig sein.

---

## Zentrale Steuerstellen (bei jedem Paket berücksichtigen)

Diese Stellen strahlen bei Address-/Supplier-Änderungen am stärksten aus:

**Backend**
- `backend/src/Entity/Address.php` — Scope-Modell (`department` \| `supplier` \| `global`)
- `backend/src/Service/MaterialImportService.php` — `loadSuppliers(GLOBAL000000)` → neues Modell (Paket 8)
- `backend/src/Controller/GlobalAddressController.php` — Filter `scope=global` statt `GLOBAL000000`
- `backend/src/Entity/Membership.php` — Vorbild für `SupplierMembership`
- `backend/src/Controller/JoinRequestController.php` — Join-Code-Muster für Team (Paket 6)
- `backend/src/Service/Bootstrap/GlobalSystemSeedDefaults.php` — Seeds / `GLOBAL000000`

**Frontend**
- `frontend/src/stores/auth.ts` — `departments[]` heute → ergänzen: `supplierCompanies[]`, optional `lastUsedSupplierCompanyId`
- `frontend/src/router/index.ts` — Guards `/dept/…` vs. `/supplier/…`; Supplier-only Redirect
- `frontend/src/components/layout/SidebarNavigation.vue` — Toggle **Meine Firma**
- `frontend/src/views/GlobalAddressesView.vue` — globale Lieferanten (Legacy + später Promote)
- `frontend/src/components/material/MaterialCreateWizard.vue` — Lieferanten-Picker
- `frontend/src/views/settings/MaterialImportSettingsView.vue` — Import-Lieferantenliste

**Tests / Seeds**
- `backend/data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json` — J&S unberührt lassen; globale Supplier nach M1 prüfen

---

## Paket 0 — Address-Scope + Migration `GLOBAL000000` (M1)

**Ziel:** Expliziter `address.scope`; globale Lieferanten ohne `GLOBAL000000`-Department. **J&S `dept_js00000` unberührt.**

**Betroffen:**
- `backend/src/Entity/Address.php`
- `backend/migrations/` (neue Migration)
- `backend/src/Controller/GlobalAddressController.php` (Lesefilter)
- `backend/src/Service/Bootstrap/GlobalSystemSeedDefaults.php` (Seed anpassen)
- ggf. alle Stellen mit hart codiertem `GLOBAL000000` (grep)

**Schritte:**
- [x] Spalten `scope` (`department` \| `supplier` \| `global`), `supplier_company_id` (nullable FK, Phase 1 vorbereiten), `department_id` nullable
- [x] CHECK-Constraint: genau ein Kontext (siehe [supplier-portal.md §8.1](./supplier-portal.md#81-address-scope-modell-ersetzt-global-suppliers-department))
- [x] Migration: alle `address` in `GLOBAL000000` → `scope=global`, `department_id=NULL`
- [x] Bestehende Department-Adressen → `scope=department` (Default)
- [x] `GlobalAddressController`: CRUD nur `scope=global AND type=supplier` (nicht mehr `department_id=GLOBAL000000`)
- [x] Regression: `material_batch.supplier_id` FK bleibt gültig (gleiche `address.id`)

**Definition of Done:** Globale Lieferanten ohne Global-Department referenzierbar; J&S-Seeds unverändert; PHPUnit/Migration grün.

---

## Paket 1 — `supplier_company` + `supplier_membership` (M2)

**Ziel:** Firmen-Entität und Membership analog `Department` + `Membership`.

**Betroffen:**
- `backend/src/Entity/SupplierCompany.php` (neu)
- `backend/src/Entity/SupplierMembership.php` (neu)
- `backend/migrations/`
- `backend/src/Repository/` (neu)

**Schritte:**
- [x] `supplier_company`: `name`, `manufacturer_key`, `supplier_address_id` (FK, UNIQUE empfohlen), `capabilities` JSON, `linked_department_id` NULL, `status` (`pending` \| `active` \| `suspended`), Timestamps
- [x] **Kein** `linked_organisation_id`
- [x] `supplier_membership`: `supplier_company_id`, `user_id`, `role` (`admin` \| `member` in Phase 1), `is_primary` optional; UNIQUE `(supplier_company_id, user_id)`
- [x] Optional: `user.last_used_supplier_company_id` (M3)
- [x] Firma + `address` (`scope=supplier`) in **einer Transaktion** anlegen (Chicken-Egg: `supplier_company_id` ↔ `supplier_address_id`)

**Definition of Done:** Entities + Migration; manuell/Faker anlegbare Firma mit Haupt-Adresse; keine API/UI nötig.

---

## Paket 2 — Auth: `ROLE_SUPPLIER`, Session, Voter

**Ziel:** Symfony und Frontend kennen Supplier-Kontext; Zugriff nur auf eigene aktive Firma.

**Betroffen:**
- `backend/src/Entity/User.php` — `getRoles()` ergänzen
- Session-/Me-Endpoint (z. B. bestehender Auth-Controller)
- `backend/src/Security/` — Voter `SupplierCompanyVoter` (neu)
- `frontend/src/stores/auth.ts`
- `frontend/src/api/` — Typen für `SupplierCompany`, Membership

**Schritte:**
- [x] `ROLE_SUPPLIER` wenn ≥ 1 `SupplierMembership` und Firma `status=active`
- [x] Me-Response: `supplier_companies[]` mit `{ id, name, role, status, capabilities }`
- [x] Voter: `assertSupplierCompanyAccess($companyId)` — Membership + `status=active`; bei `suspended`/`pending` blockieren
- [x] Frontend Store: `supplierCompanies`, Getter `hasSupplierAccess`, `isSupplierOnly` (kein Department)
- [x] Kein automatischer Department-Zugriff über Supplier-Rolle

**Definition of Done:** Login liefert Supplier-Liste; Voter blockt fremde/inaktive Firmen; Type-check grün.

---

## Paket 3 — Plattform-Admin: Onboarding + Legacy-Promote

**Ziel:** Support-Prozess — Firma anlegen, erster User admin; globale Adresse → Firma.

**Betroffen:**
- `backend/src/Controller/SupplierCompanyAdminController.php` (neu) oder Erweiterung Superadmin-Controller
- `frontend/src/views/` — Admin-UI (z. B. unter Verwaltung/Global Addresses)
- E-Mail/Invite-Flow (bestehendes User-Anlegen wiederverwenden)

**Schritte:**
- [x] `POST /api/admin/supplier-companies` — Firma + Adresse + Capabilities + `status=active`
- [x] Ersten User als Membership `admin` anlegen / bestehenden User zuweisen; Login-Zugang (Support-Prozess)
- [x] `PATCH` Capabilities, `status`, `linked_department_id` (nur Plattform-Admin für sensible Felder)
- [x] Legacy: Aktion „Als Supplier-Firma aktivieren“ auf `address` (`scope=global`) → `SupplierCompany` + Adresse `scope=supplier` + `supplier_company_id`
- [x] **Keine** Self-Registration neuer Firmen
- Globale **Material-Vorlagen**-Übernahme (`material_template` → `supplier_material_template*`) folgt in **Paket 11** — siehe [supplier-portal.md §8.8](./supplier-portal.md#88-legacy-daten)

**Definition of Done:** Admin kann Tortuga als Firma anlegen; Legacy-Promote (Adresse) ohne Datenverlust; erster User ist `admin`.

---

## Paket 4 — Frontend-Shell: Routing, Layout, Sidebar

**Ziel:** Ein Login, Supplier-Bereich unter `/supplier/{companyId}/…`; Sidebar-Toggle **Meine Firma**.

**Betroffen:**
- `frontend/src/router/index.ts`
- `frontend/src/components/layout/SidebarNavigation.vue`
- `frontend/src/layouts/` — `SupplierLayout.vue` (neu) oder Erweiterung App-Layout
- `frontend/src/locales/de.json`, `en.json`

**Schritte:**
- [x] Routen: `/supplier/:companyId/profile`, `/team`, … (Phase 1 minimal)
- [x] Guard: Supplier-Membership für `companyId`; Department-Routen unverändert (`Membership`)
- [x] **Supplier-only** (kein Department): nach Login Redirect `/supplier/{defaultCompanyId}/…`
- [x] **Department + Supplier:** normales Dashboard + aufklappbarer Block unter Dashboard:

```text
▼ Meine Firma
    Profil & Kontakt
    Team                    (nur admin)
```

- [x] Firmen-Kontextwechsel wenn User in mehreren `SupplierCompany` (analog Department-Wechsel)
- [x] i18n: `sidebar.myCompany`, Unterpunkte

**Definition of Done:** Navigation sichtbar/nicht sichtbar je nach Membership; Supplier-only landet im Supplier-Bereich; Build grün.

---

## Paket 5 — „Meine Firma“: Profil & Kontakt

**Ziel:** Firmenadmin pflegt öffentliche Stammdaten; Member read-only.

**Betroffen:**
- `backend/src/Controller/SupplierCompanyController.php` (neu)
- `frontend/src/views/supplier/SupplierProfileView.vue` (neu)
- `frontend/src/api/supplier.ts` (neu)
- Öffentlicher Read-Endpoint für MW (Liste aktiver Firmen + Kontakt) — kann schon hier minimal

**Schritte:**
- [x] `GET/PATCH /api/supplier-companies/{id}` — Profil (admin schreibt, member liest)
- [x] `PATCH` aktualisiert verknüpfte `address` (`scope=supplier`, `type=supplier`): Name, Firma, Strasse, PLZ, Ort, Tel, E-Mail, …
- [x] `manufacturer_key` pflegen (Unique-Validierung)
- [x] Öffentlich: `GET /api/supplier-companies?status=active` oder dedizierter Picker-Endpoint für MW (Kontakt sichtbar)
- [x] UI: Formular Profil & Kontakt; Member sieht disabled/read-only

**Definition of Done:** Admin speichert Kontakt; MW-API liefert aktive Firmen mit Adresse; Member kann nicht schreiben.

---

## Paket 6 — Team + Join-Code

**Ziel:** Firmen-admin verwaltet Kollegen; Join-Code nur **innerhalb** bestehender Firma.

**Betroffen:**
- `backend/src/Controller/SupplierMembershipController.php` (neu)
- `backend/src/Controller/JoinRequestController.php` — Muster für Supplier-Join (neuer Pfad oder Erweiterung)
- `frontend/src/views/supplier/SupplierTeamView.vue` (neu)

**Schritte:**
- [x] CRUD Memberships: Liste, Rolle setzen (`admin` \| `member`), entfernen
- [x] Join-Code pro `SupplierCompany` (generieren, rotieren) — analog Department-Join
- [x] Beitritt: User authentifiziert → Membership `member` (oder vom Admin hochgestuft)
- [x] Nur **admin** darf Team + Join-Code; Join-Code **nicht** für Erst-Anlage der Firma
- [x] UI: Team-Tabelle, Invite/Join-Code-Anzeige, Rolle ändern

**Definition of Done:** Zweiter User tritt per Code bei; admin verwaltet Rollen; kein Zugriff für `member` auf Team-API.

---

## Paket 7 — Operator: Capability + `linked_department_id`

**Ziel:** Firmen-admin aktiviert Vermietung über bestehendes Department — **keine** Org-Erstellung im Portal.

**Betroffen:**
- `SupplierCompanyController` — PATCH capabilities + `linked_department_id`
- `frontend/src/views/supplier/SupplierProfileView.vue` — Toggle „Wir vermieten auch"

**Schritte:**
- [ ] UI-Toggle setzt Capability `operator` + wählt `linked_department_id` (Picker bestehender Departments — vom Support vorbereitet)
- [ ] Validierung: Department existiert; Org ableitbar; User braucht **eigene** Department-`Membership` für Operator-Arbeit (Hinweis in UI)
- [ ] Entfernen des Toggles: Capability `operator` weg, `linked_department_id` NULL (Bestand im Dept bleibt)
- [ ] Kein paralleles Supplier-Lager — Vermietung = normales `material_item` im linked Dept

**Definition of Done:** Admin verknüpft Dept; Capability gespeichert; B2B-Katalog weiterhin Phase 2.

---

## Paket 8 — MW-Listen: Import, Global Addresses, Konstanten (M4)

**Ziel:** Bestehende MW-Flows nutzen Address-Scope + registrierte Firmen; `GLOBAL000000` im Frontend-Code entfernen.

**Betroffen:**
- `backend/src/Service/MaterialImportService.php`
- `frontend/src/views/GlobalAddressesView.vue`
- `frontend/src/components/material/MaterialCreateWizard.vue`
- `frontend/src/views/settings/MaterialImportSettingsView.vue`
- `frontend/src/utils/organisationUserPicker.ts` (falls `GLOBAL_SUPPLIER` constant)
- ggf. Batch-Modal / Address-Picker

**Schritte:**
- [ ] Lieferanten-Query (siehe [supplier-portal.md §8.4](./supplier-portal.md#84-mw-abfrage-lieferanten-ziel)):
  1. `SupplierCompany` `status=active` + Haupt-Adresse
  2. `address` `scope=global` AND `type=supplier` (Legacy)
  3. `address` `scope=department` AND `department_id = :current`
- [ ] `MaterialImportService.loadSuppliers` auf neues Modell
- [ ] Frontend: `GLOBAL_SUPPLIER_DEPARTMENT_ID` / `GLOBAL000000` entfernen
- [ ] `GlobalAddressesView`: weiterhin Legacy pflegen + Link „Als Supplier-Firma aktivieren" (Paket 3)
- [ ] Tests: Import mit globalem + department-scoped Supplier; J&S-Material unverändert

**Definition of Done:** MW sieht Lieferanten in Import/Wizard wie heute (+ aktive Firmen); kein Hardcode `GLOBAL000000` mehr im Frontend.

---

## Paket 9 — Katalog `supplier_catalog_item` (Phase 2a)

**Ziel:** Lieferant pflegt Verkaufsstamm **ohne** SN; MW sieht noch keinen vollen Shop (CRUD + Liste reicht als Zwischenstand).

**Betroffen:**
- `backend/src/Entity/SupplierCatalogItem.php` (neu)
- `backend/migrations/`
- Supplier-CRUD-Controller; später MW read-API

**Schritte:**
- [ ] Felder laut [supplier-portal.md Phase 2A](./supplier-portal.md#phase-2--katalog--vorlagen-shop-für-mw)
- [ ] CRUD nur eigene Firma; Capability `catalog` erforderlich
- [ ] `visibility` / `status` Felder vorbereiten (Logik Paket 13)
- [ ] **Keine** Seriennummern auf Katalog-Entity

**Definition of Done:** Lieferant legt Artikel an; isoliert pro Firma; noch kein MW-Import.

---

## Paket 10 — Übergaben `supplier_delivery*` + SN (Phase 2b)

**Ziel:** Lieferschein mit Zeilen und Seriennummern; MW sieht offene Übergaben.

**Betroffen:**
- `SupplierDelivery`, `SupplierDeliveryLine` (neu)
- Import-Service-Erweiterung (Vorschau SN)

**Schritte:**
- [ ] Kopf + Zeilen; Status `draft` → `submitted` → `imported`
- [ ] SN auf `SupplierDeliveryLine`, nicht Katalog
- [ ] Capability `delivery`; Rolle `delivery` (Feinrolle) ab Phase 2
- [ ] Validierung: `tracking_type` serialized → SN-Liste; Duplikat-Hinweis

**Definition of Done:** Lieferant übermittelt Übergabe mit SN; MW kann Liste lesen (Import in Paket 12).

---

## Paket 11 — Vorlagen `supplier_material_template_*` (Phase 2c)

**Ziel:** Supplier-eigene Vorlagen/Combos — Spiegel des Combo-Modells in `supplier_*`-Tabellen.

**Betroffen:**
- `supplier_material_template*` (neu, analog [combos/README.md](../material/combos/README.md) §6)
- **Nicht** `material_template` erweitern

**Schritte:**
- [ ] Basis-Entity + Komponenten + Options-/Delta-Tabellen
- [ ] Supplier-Editor (Progressive Disclosure wie Combo-Editor)
- [ ] MW-Import-Pfad: Vorlage → Material (SN aus Übergabe vorbefüllen) — Erweiterung `TemplateController`-Logik
- [ ] **Legacy-Übernahme:** Admin-Aktion „Globale Vorlagen übernehmen" — `material_template` (`scope=global`, `manufacturer` match) → **Kopie** nach `supplier_material_template*` (Komponenten + Options mit); Match über `manufacturer` ↔ `supplier_company.manufacturer_key` (normalisiert)
- [ ] Globale Vorlagen bleiben parallel (MW-Abwärtskompatibilität); optional als Legacy markieren/ablösen
- [ ] Optional Portal: Hinweis „N globale Vorlagen für euren Hersteller — als Basis importieren"

**Definition of Done:** Supplier-Vorlage anlegbar; Import erzeugt Department-Material wie Plattform-Vorlage; Admin kann globale Herstellervorlagen in die Firma **kopieren** (ohne `material_template`-Zeile zu verschieben).

---

## Paket 12 — MW-Shop, Budget, Import (Phase 2d)

**Ziel:** MW-Hauptnutzen — stöbern, budgetieren, bewusst importieren (Katalog + Übergabe + Vorlage).

**Betroffen:**
- Neues MW-Submenü „Lieferanten-Shop" (Toggle in Sidebar)
- `MaterialImportService` / neuer Supplier-Import-Service
- `TemplateController::createMaterialFromTemplate` — SN-Logik wiederverwenden

**Schritte:**
- [ ] Shop-UI: Filter, Merkliste, Budget-Summe
- [ ] Tab „Offene Übergaben"
- [ ] Import bulk + serialisiert → `MaterialBatch`(s) mit `serial_number`
- [ ] Kein direkter Lieferanten-Schreibzugriff auf `material_item`

**Definition of Done:** End-to-end MW importiert aus Shop/Übergabe inkl. SN; siehe [supplier-portal.md Phase 2 DoD](./supplier-portal.md#definition-of-done-1).

---

## Paket 13 — Sichtbarkeit + Global-Review (Phase 2e)

**Ziel:** Entwurf / Department / global mit Admin-Freigabe.

**Schritte:**
- [ ] `visibility`, `status` (`draft`, `published`, `pending_review`) auf Katalog + Vorlagen
- [ ] Admin-Queue für globale Freigabe
- [ ] Unterscheidung `address.scope=global` vs. Katalog `visibility=global` (§8.3)

**Definition of Done:** Globaler Shop-Inhalt nur nach Review sichtbar.

---

## Paket 14 — Reparaturen an Lieferant (Phase 3)

**Ziel:** `WorkshopTicket` an `SupplierCompany`; minimaler Datenzugriff.

**Betroffen:**
- `backend/src/Entity/WorkshopTicket.php` — `assigned_to_supplier_company_id`
- `WorkshopController.php`, Supplier-Reparatur-Views
- `PublicWorkshopView` bleibt parallel

**Schritte:**
- [ ] MW weist Ticket zu; Capability `repairs` + Rolle `repairs`
- [ ] Supplier-Dashboard: Status, Kostenvoranschlag, Fotos
- [ ] API filtert personenbezogene Aktivitätsdaten weg

**Definition of Done:** Siehe [supplier-portal.md Phase 3 DoD](./supplier-portal.md#definition-of-done-2).

---

## Paket 15 — Cleanup: `GLOBAL000000` / `GLOBALORG001` (M7)

**Ziel:** Technisches Global-Department entfernen, wenn nichts mehr referenziert.

**Hängt ab von:** Paket 0, 8 (+ ideally Phase 2 live)

**Schritte:**
- [ ] Prüfen: keine FK auf `GLOBAL000000`; Seeds angepasst
- [ ] Department + Org löschen/deprecaten
- [ ] Doku + Kommentare aufräumen

**Definition of Done:** Kein produktiver Code-Pfad mehr über `GLOBAL000000`.

---

## Offene Punkte (vor dem jeweiligen Paket entscheiden)

| Thema | Wann klären |
|-------|-------------|
| Exakter Me-Endpoint / Session-Shape | Paket 2 |
| Admin-UI: eigene View vs. Erweiterung `GlobalAddressesView` | Paket 3 |
| Join-Code: eigene Entity vs. Setting auf Firma | Paket 6 |
| Öffentlicher Firmen-Picker: ein Endpoint vs. zwei (admin vs. MW) | Paket 5 |
| Index-/Kaskaden-Details neuer Tabellen | Jeweiliges Paket |
| E-Mail bei Team-Join | Paket 6 (optional Phase 1) |

> **Entschieden** (Details in [supplier-portal.md](./supplier-portal.md)):
> - Kein Self-Signup Firmen; Join-Code nur Team
> - Phase 1 Rollen: `admin` \| `member`
> - Kein `linked_organisation_id`
> - SN nur auf Delivery, nicht Katalog
> - J&S out of scope; `dept_js00000` unberührt bei M1
> - Globale Vorlagen (`material_template scope=global`) ohne `SupplierCompany` gültig; Übernahme = Kopie nach `supplier_material_template*` (Paket 11), Match `manufacturer` ↔ `manufacturer_key`

---

## Siehe auch

- [supplier-portal.md](./supplier-portal.md) — Konzept & Zielmodell
- [material/combos/plan.md](../material/combos/plan.md) — Combo-Umbau (Vorlagen-Editor-Referenz Phase 2)
- [activities/material-pipeline.md](../activities/material-pipeline.md) — Material-Import und Bestand
