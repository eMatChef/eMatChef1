# Umbauplan: Vuetify (Frontend)

Abarbeitbare Checkliste für die schrittweise Einführung von **Vuetify 3** und **`E*`-Komponenten**. Regeln: [vuetify-standards.md](./vuetify-standards.md).

**Stand:** Mai 2026 · **Status:** Phase 0 umgesetzt — **Phase 1** als Nächstes (App-Shell).

---

## So gehen wir den Plan gemeinsam durch

1. **Eine Phase lesen** (Übersicht + Review-Stopp-Fragen).
2. **Anpassen:** Schritte streichen, verschieben oder splitten — direkt in dieser Datei oder im Chat festhalten.
3. **Umsetzen:** erst wenn die Phase freigegeben ist; pro PR lieber 3–8 Schritte einer Phase, nicht 50 auf einmal.
4. **Abhaken:** `[ ]` → `[x]`; optional Datum/Kürzel in Klammern: `[x] 2026-05-31 M`.

**Review-Stopp** = bewusst pause, bevor die nächste Phase startet (Build, Screenshots 375px + 1280px).

---

## Status-Legende

`[ ]` offen · `[~]` in Arbeit · `[x]` erledigt · `[-]` bewusst übersprungen (Begründung notieren)

---

## Phasen-Übersicht

| Phase | Thema | Schritte | Review-Stopp |
| ----- | ----- | -------- | ------------- |
| 0 | Fundament (Vuetify installieren, Theme) | 001–012 | nach 012 |
| 1 | App-Shell (Layout, Nav, Mobile) | 013–028 | nach 028 |
| 2 | `E*`-Formular-Basis | 029–042 | nach 042 |
| 3 | AutoSave auf `E*` | 043–050 | nach 050 |
| 4 | Layout-Bausteine (Dialog, Liste, PageShell) | 051–060 | nach 060 |
| 5 | Einstieg (Login, Landing, Pending) | 061–068 | nach 068 |
| 6 | Aktivitäten | 069–084 | nach 084 |
| 7 | Material | 085–092 | nach 092 |
| 8 | Kontakte, Aufgaben, Inbox | 093–098 | nach 098 |
| 9 | Department-Settings | 099–108 | nach 108 |
| 10 | Supplier + Buchhaltung + Werkstatt | 109–114 | nach 114 |
| 11 | Admin / Org / Superadmin | 115–120 | nach 120 |
| 12 | Aufräumen & Abschluss | 121–126 | nach 126 |

**Ausnahme:** `devices.ematchef.ch` — nicht in diesem Plan ([devices/concept.md](../devices/concept.md)).

---

## Phase 0 — Fundament

**Ziel:** Vuetify läuft, Theme = Markenfarben, noch keine produktive View-Umbauten.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 001 | `vuetify`, `vite-plugin-vuetify`, `@mdi/font` installieren (siehe Entscheide Phase 0) | [x] 2026-05-31 |
| 002 | `frontend/src/plugins/vuetify.ts`: nur Theme **`light`**, `primary`/`error` aus `brand-tokens.css`; **kein Dark Mode** (später) | [x] 2026-05-31 |
| 003 | Plugin in `main.ts` registrieren; `import 'vuetify/styles'` **nach** `style.css` | [x] 2026-05-31 |
| 004 | Globalen Reset in `style.css` entschärfen (siehe Entscheide Phase 0) | [x] 2026-05-31 |
| 005 | `vue-i18n`-Locale-Adapter für Vuetify (de / en-US) | [x] 2026-05-31 |
| 006 | Dev-Server + `vite build` grün | [x] 2026-05-31 |
| 007 | ~~Pilot-Route~~ **entfällt** — Smoke erst ab Phase 1 (`v-app` in `AppLayout`) oder Phase 5 (Login) | [-] |
| 008 | Ordner `frontend/src/components/form/base/` + `index.ts`-Barrel anlegen | [x] 2026-05-31 |
| 009 | MDI: `@mdi/font` + `icons: { defaultSet: 'mdi' }` — in Standards dokumentiert | [x] 2026-05-31 |
| 010 | ESLint/TS: keine Warnungen durch Vuetify-Imports | [x] 2026-05-31 |
| 011 | Kurztest 375px + 1280px auf erster Seite mit `v-app` (Phase 1 Schritt 013, nicht vorher) | [-] Phase 1 |
| 012 | **Review-Stopp Phase 0:** Build grün, Theme light OK → Phase 1 freigeben | [x] 2026-05-31 |

### Entscheide Phase 0 (festgehalten)

| Thema | Entscheid |
| ----- | --------- |
| Dark Mode | **Später** — Phase 0 nur `theme.themes.light` |
| MDI | **`@mdi/font`** global (einfach, Vuetify-Standard); bestehende `Icon*`-SVGs bleiben parallel. Optional Phase 12: Wechsel auf `@mdi/js` |
| Pilot-View 007 | **Nicht nötig** — übersprungen |
| CSS-Reset 004 | **Reset entschärfen** (siehe [vuetify-standards.md](./vuetify-standards.md#globaler-css-reset-mit-vuetify)) — nicht Vuetify-Reset blind übernehmen |

---

## Phase 1 — App-Shell

**Ziel:** Eingeloggte App nutzt `v-app` / Drawer / App-Bar; Mobile-Navigation funktioniert.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 013 | `AppLayout.vue`: Root `v-app`, `v-main`, Platz für Drawer + Header | [ ] |
| 014 | `SidebarNavigation.vue` → `v-navigation-drawer` (permanent desktop, temporary mobile) | [ ] |
| 015 | `useDisplay()`: Hamburger in `TopHeader` / `v-app-bar` schaltet Drawer auf Mobile | [ ] |
| 016 | `margin-left: 64px` in `.main-content` entfernen; Layout über Vuetify | [ ] |
| 017 | `TopHeader.vue` → `v-app-bar` / `v-toolbar` (Titel, Suche, User-Menü) | [ ] |
| 018 | `PageShell.vue` (neu): `v-container`, Slots title / actions / filters / default | [ ] |
| 019 | Eine kleine eingeloggte View auf `PageShell` umstellen (Pilot: `NotificationsCenterView` oder Dashboard-Teil) | [ ] |
| 020 | Safe-Area Utilities (`pb-safe`) in global oder Vuetify-Layout übernehmen | [ ] |
| 021 | Sidebar-Farbe `#26353b` + Logo `EmcLogoMark` beibehalten (Custom am Drawer) | [ ] |
| 022 | Supplier-Routen: gleiche Shell oder dokumentierte Abweichung | [ ] |
| 023 | Regression: Navigation alle Hauptlinks, aktiver Zustand | [ ] |
| 024 | Regression: Department-Wechsel im Header | [ ] |
| 025 | Mobile: Drawer schliesst nach Navigation; kein doppeltes Scrollen | [ ] |
| 026 | `keep-alive` in `AppLayout` weiter funktionsfähig | [ ] |
| 027 | Screenshot/Doku: Shell vorher/nachher in PR-Beschreibung | [ ] |
| 028 | **Review-Stopp Phase 1:** Shell auf Phone + Desktop freigegeben? | [ ] |

---

## Phase 2 — `E*`-Formular-Basis

**Ziel:** Standard-Inputs nur noch über `E*` in Views (Implementierung in `form/base/`).

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 029 | `ETextField.vue` (wrap `v-text-field`, outlined, density, Fehler-Slots) | [ ] |
| 030 | `ESelect.vue` | [ ] |
| 031 | `ECheckbox.vue` / `ESwitch.vue` | [ ] |
| 032 | `ETextarea.vue` | [ ] |
| 033 | `EButton.vue` (Varianten primary/secondary/text) | [ ] |
| 034 | `EDialog.vue` + `ECard` (Modal-Grundgerüst) | [ ] |
| 035 | Gemeinsame Props dokumentieren (label, disabled, rules) in `form/base/README.md` oder Standards | [ ] |
| 036 | Eintrag `E*` in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) | [ ] |
| 037 | Pilot: `LoginView` Formularfelder auf `E*` (ohne AutoSave) | [ ] |
| 038 | Abgleich mit bestehendem `outlined-field.css` — was bleibt, was entfällt | [ ] |
| 039 | Vitest/Snapshot optional für `ETextField` (Smoke) | [ ] |
| 040 | Kein `v-text-field` in Views ausserhalb `form/base/` (grep-Check) | [ ] |
| 041 | Touch: Input font-size ≥ 16px auf Mobile | [ ] |
| 042 | **Review-Stopp Phase 2:** Look der Felder = eMatChef-Design? | [ ] |

---

## Phase 3 — AutoSave auf `E*`

**Ziel:** `AutoSaveField` nutzt innen `ETextField`; Loader/Diskette bleiben.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 043 | `AutoSaveFieldShell`: Text/Number/Date → `ETextField` | [ ] |
| 044 | Select/Checkbox-Zweige auf `ESelect` / `ECheckbox` | [ ] |
| 045 | `auto-save-field.css` an Vuetify-Feldanatomy anpassen (Balken unten, Diskette) | [ ] |
| 046 | Regression: `MaterialDetailView` Stammdaten Auto-Save | [ ] |
| 047 | Regression: Fehler + Retry + Blur-Revert | [ ] |
| 048 | Kein direktes `VTextField` in `autoSave/*` | [ ] |
| 049 | Doku AutoSave in wiederverwendbare-komponenten.md aktualisieren | [ ] |
| 050 | **Review-Stopp Phase 3:** Auto-Save UX unverändert oder besser? | [ ] |

---

## Phase 4 — Layout-Bausteine

**Ziel:** Wiederkehrende Muster zentral; weniger `page-layout.css` in migrierten Views.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 051 | `PageShell` in 2 weiteren Views (z. B. `ContactsView`, `TasksGeneralView`) | [ ] |
| 052 | Filter-Zeile: Pattern mit `v-row` / `v-col` + `ETextField` / `v-select` | [ ] |
| 053 | `GlobalToastContainer` → Vuetify-Snackbar oder bestehend + Theme | [ ] |
| 054 | Confirm/Prompt-Dialoge auf `EDialog` prüfen (`useConfirm`, `usePrompt`) | [ ] |
| 055 | Leere Zustände: `v-alert` / zentrale Empty-Komponente | [ ] |
| 056 | Loading: `v-skeleton-loader` Pattern definieren | [ ] |
| 057 | Tabellen-Entscheid dokumentieren: `v-data-table` vs. Kartenliste auf Mobile | [ ] |
| 058 | `ResponsiveDataList` (optional): Desktop-Tabelle, Mobile-Karten — nur wenn gebraucht | [ ] |
| 059 | `tables.css` / `modals.css`: Migrationshinweise in Dateikopf | [ ] |
| 060 | **Review-Stopp Phase 4:** Pattern für alle Feature-Teams klar? | [ ] |

---

## Phase 5 — Einstieg & öffentliche Einstiege

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 061 | `LoginView.vue` vollständig Vuetify + `E*` | [ ] |
| 062 | `VerifyEmailView.vue` | [ ] |
| 063 | `PendingAssignmentView.vue` | [ ] |
| 064 | `LandingHomeView` + `PublicSiteLayout` (Marketing) | [ ] |
| 065 | Blog/FAQ/TOS/Impressum — einheitlich oder Phase 12 Rest | [ ] |
| 066 | `DisplayEntryView` / `DepartmentDisplayView` — optional, Entscheid im Review | [ ] |
| 067 | Öffentliche QR-Views (`PublicMaterialView` etc.) — optional | [ ] |
| 068 | **Review-Stopp Phase 5:** Erster Eindruck (Login/Landing) auf Mobile OK? | [ ] |

---

## Phase 6 — Aktivitäten

**Hinweis:** Große Dateien — in mehrere PRs splitten (Liste / Detail / Wizard / Pack).

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 069 | `ActivitiesView`: Listen-Header + `PageShell` + Filter | [ ] |
| 070 | Aktivitäten-Liste: Mobile-Karten oder scrollbare Tabelle | [ ] |
| 071 | `ActivityCreateWizard.vue` + `ActivityCreateWizardForm.vue` Shell | [ ] |
| 072 | Wizard Schritte: Stammdaten-Felder → `E*` / `AutoSaveField` | [ ] |
| 073 | `ActivityZeitraumDatetimeFields` + `ActivityDateField` / `ActivityDateRangeField` | [ ] |
| 074 | Entscheid Datepicker: `@vuepic` behalten vs. `VDatePicker` — dokumentieren | [ ] |
| 075 | Falls VDatePicker: Feiertags-Marker portieren | [ ] |
| 076 | `ActivityDraftOverviewForm` Zeitraum-Auto-Save | [ ] |
| 077 | `ActivityDetailView` Header + Tabs Shell | [ ] |
| 078 | Tabs: Material, Kosten, Verbrauch — schrittweise | [ ] |
| 079 | `ActivityPackListTab` — Unter-PRs (nur Shell, dann Zeilen, dann Modals) | [ ] |
| 080 | Pack-Modals (`PackAddContainerModal`, …) → `EDialog` | [ ] |
| 081 | `activity-create-wizard.css` / `detail-panel.css`: deprecated Teile markieren | [ ] |
| 082 | `useDisplay` statt neue `@media` in migrierten Activity-CSS | [ ] |
| 083 | Regression: Aktivität anlegen, Zeitraum, Packliste Smoke | [ ] |
| 084 | **Review-Stopp Phase 6:** Aktivitäten auf Phone nutzbar? | [ ] |

---

## Phase 7 — Material

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 085 | `MaterialsView` Liste + Filter + `PageShell` | [ ] |
| 086 | `MaterialDetailView` Stammdaten → `AutoSaveField` + `E*` | [ ] |
| 087 | Tabs (Defekte, Historie, …) einzeln | [ ] |
| 088 | `MaterialImagePicker` / Medien — Vuetify wo sinnvoll | [ ] |
| 089 | Import-UI (`MaterialImportSettingsView`) | [ ] |
| 090 | `materials-view.css` / `material-detail-view.css` bereinigen | [ ] |
| 091 | Regression: Material suchen, Detail, QR | [ ] |
| 092 | **Review-Stopp Phase 7** | [ ] |

---

## Phase 8 — Kontakte, Aufgaben, Inbox

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 093 | `ContactsView` + `ContactDetailView` | [ ] |
| 094 | `TasksShellView` / `TasksGeneralView` | [ ] |
| 095 | `NotificationsCenterView` + Inbox-Modals | [ ] |
| 096 | `NotificationSenderBlock` / Avatare — Stil abgleichen | [ ] |
| 097 | `contacts-view.css` responsive durch Vuetify ersetzen | [ ] |
| 098 | **Review-Stopp Phase 8** | [ ] |

---

## Phase 9 — Department-Settings

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 099 | `SettingsView` Shell + Navigation | [ ] |
| 100 | `UsersSettingsView` / `GroupsSettingsView` | [ ] |
| 101 | `MyDepartmentSettingsView` + Adresse + Join-Code | [ ] |
| 102 | `CategoriesSettingsView` / `StorageSettingsView` | [ ] |
| 103 | `AddonsSettingsView` / Permissions | [ ] |
| 104 | `MaterialImportSettingsView` (falls nicht Phase 7) | [ ] |
| 105 | `MyDepartmentDisplayScreensView` | [ ] |
| 106 | `DepartmentOnboardingWizard` | [ ] |
| 107 | Modals (`DepartmentModal`, `AddressModal`) → `EDialog` | [ ] |
| 108 | **Review-Stopp Phase 9** | [ ] |

---

## Phase 10 — Supplier, Buchhaltung, Werkstatt

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 109 | Supplier-Views (`SupplierProfileView`, Catalog, Deliveries, Repairs, …) | [ ] |
| 110 | `SupplierShopView` | [ ] |
| 111 | `AccountingShellView` + Unter-Views | [ ] |
| 112 | `WorkshopView` | [ ] |
| 113 | `VerwaltungView` (Department-Kontext, falls getrennt von Admin) | [ ] |
| 114 | **Review-Stopp Phase 10** | [ ] |

---

## Phase 11 — Admin / Org / Superadmin

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 115 | `VerwaltungView` Admin-Dashboard + Tree-Views | [ ] |
| 116 | `OrganisationsSettingsView` / `DepartmentsSettingsView` | [ ] |
| 117 | `AdminUsersSettingsView` / `GlobalAdminRolesSettingsView` | [ ] |
| 118 | Security, Jobs, Integrations, Mail-Verwaltung | [ ] |
| 119 | `DashboardView` (Superadmin/Admin) | [ ] |
| 120 | **Review-Stopp Phase 11** | [ ] |

---

## Phase 12 — Aufräumen & Abschluss

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 121 | grep: keine `v-text-field` / `v-btn` in Views ausser `form/base` | [ ] |
| 122 | Ungenutzte `styles/ui/buttons.css`, `forms.css`, … in migrierten Bereichen deprecaten | [ ] |
| 123 | Tailwind: entfernen oder Rolle in Standards festhalten | [ ] |
| 124 | `vue-datepicker` entfernen — nur wenn Schritt 074 auf VDatePicker umgestellt | [ ] |
| 125 | [vuetify-standards.md](./vuetify-standards.md) Status auf „umgesetzt“ / Wartung | [ ] |
| 126 | **Review-Stopp Phase 12:** Migration abgeschlossen oder Restliste dokumentiert | [ ] |

---

## Notizen aus gemeinsamer Durchsicht

| Datum | Phase/Schritt | Entscheid |
| ----- | ------------- | --------- |
| 2026-05-31 | Phase 0 | Dark Mode später; MDI = `@mdi/font`; Pilot 007 entfällt; Reset entschärfen; Umsetzung separater Chat |
| 2026-05-31 | Phase 0 Umsetzung | Branch `feat/vuetify-phase-0-foundation`; Vuetify **4.0.8** (npm latest); Build grün |

---

## Nächster Schritt (aktuell)

**Phase 1:** App-Shell (`v-app` in `AppLayout`, Drawer, App-Bar) ab Schritt 013.
