# Umbauplan: Vuetify (Frontend)

Abarbeitbare Checkliste für die schrittweise Einführung von **Vuetify 3** und **`E*`-Komponenten**. Regeln: [vuetify-standards.md](./vuetify-standards.md).

**Stand:** Mai 2026 · **Status:** Phase 4 abgeschlossen — **Phase 5** fast fertig (Review 068 offen). Phase 3 (AutoSave auf `E*`) **verschoben** — nach Fortschritt Phase 5.

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
| 1 | App-Shell (Layout, Nav, Mobile, **Variante B** + Dev-Banner, **UI-Playground**) | 013–028, **017b–017g**, **028a–028b** | nach 028b |
| 2 | `E*`-Formular-Basis | 029–042 | nach 042 |
| 3 | AutoSave auf `E*` | 043–050 | nach 050 — **verschoben** (siehe unten) |
| 4 | Layout-Bausteine (Dialog, Liste, PageShell) | 051–060 | nach 060 — **abgeschlossen** |
| 5 | Einstieg (Login, Landing, Pending) | 061–068 | nach 068 — **aktuell** |
| 6 | Aktivitäten | 069–084 | nach 084 |
| 7 | Material | 085–092 | nach 092 |
| 8 | Kontakte, Aufgaben, Inbox | 093–098 | nach 098 |
| 9 | Department-Settings | 099–108 | nach 108 |
| 10 | Supplier + Buchhaltung + Werkstatt | 109–114 | nach 114 |
| 11 | Admin / Org / Superadmin | 115–120 | nach 120 |
| 12 | Aufräumen & Abschluss | 121–127 | nach 127 |

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
| 011 | Kurztest 375px + 1280px auf erster Seite mit `v-app` (Phase 1 Schritt 013, nicht vorher) | [x] 2026-05-31 |
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

**Architektur-Ziel (Variante B):** genau **ein** `v-app` in `App.vue`; darunter zuerst der Dev-Banner (wenn aktiv), dann der restliche Inhalt im **verbleibenden** Viewport — kein `min-height: 100vh` zusätzlich zum Banner.

```
App.vue
└── v-app (einzige Instanz, height: 100dvh)
    ├── DevEnvironmentBanner     ← oben, nimmt natürliche Höhe ein
    └── router-view              ← nutzt restliche Höhe (flex: 1; min-height: 0)
          └── AppLayout (ohne v-app)
                ├── v-navigation-drawer
                ├── v-app-bar (TopHeader)
                └── v-main
```

Die Schritte **013–017** waren ein Zwischenstand (`v-app` nur in `AppLayout`). **017b–017g** ziehen auf Variante B nach.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 013 | `AppLayout.vue`: Root `v-app`, `v-main`, Platz für Drawer + Header | [x] 2026-05-31 |
| 014 | `SidebarNavigation.vue` → `v-navigation-drawer` (permanent desktop, temporary mobile) | [x] 2026-05-31 |
| 015 | `useDisplay()`: Hamburger in `TopHeader` / `v-app-bar` schaltet Drawer auf Mobile | [x] 2026-05-31 |
| 016 | `margin-left: 64px` in `.main-content` entfernen; Layout über Vuetify | [x] 2026-05-31 |
| 017 | `TopHeader.vue` → `v-app-bar` / `v-toolbar` (Titel, Suche, User-Menü) | [x] 2026-05-31 |
| 017b | **`App.vue`:** einziges `v-app` um `DevEnvironmentBanner` + `router-view` (+ globale Dialoge/Toasts innerhalb `v-app`) | [x] 2026-05-31 |
| 017c | **`DevEnvironmentBanner`:** erstes Kind in `v-app`; `position: sticky` entfernen (Banner ist normaler Block oben) | [x] 2026-05-31 |
| 017d | **`App.vue`:** Wrapper um `router-view` mit `flex: 1; min-height: 0; overflow: hidden` (oder äquivalent), damit der Inhalt **unter** dem Banner skaliert | [x] 2026-05-31 |
| 017e | **`AppLayout.vue`:** inneres `v-app` entfernen; Shell nur noch Drawer + `v-app-bar` + `v-main`; `.app-layout` mit `height: 100%` statt `min-height: 100vh` | [x] 2026-05-31 |
| 017f | **Routen ohne `AppLayout`** (Login, Landing, öffentlich): prüfen, dass sie im Root-`v-app` korrekt mit/ohne Banner laufen (kein zweites `v-app`) | [x] 2026-05-31 |
| 017g | **Test Dev-Banner:** mit `VITE_SHOW_DEV_BANNER=1` / Test-Host — kein Doppel-Scroll; Drawer/App-Bar im sichtbaren Bereich unter dem Banner; 375px + 1280px | [x] 2026-05-31 |
| 018 | `PageShell.vue` (neu): `v-container`, Slots title / actions / filters / default | [x] 2026-05-31 |
| 019 | Eine kleine eingeloggte View auf `PageShell` umstellen (Pilot: `NotificationsCenterView` oder Dashboard-Teil) | [x] 2026-05-31 |
| 020 | Safe-Area Utilities (`pb-safe`) in global oder Vuetify-Layout übernehmen | [x] 2026-05-31 |
| 021 | Sidebar-Farbe `#26353b` + Logo `EmcLogoMark` beibehalten (Custom am Drawer) | [x] 2026-05-31 |
| 022 | Supplier-Routen: gleiche Shell oder dokumentierte Abweichung | [x] 2026-05-31 |
| 023 | Regression: Navigation alle Hauptlinks, aktiver Zustand | [x] 2026-05-31 |
| 024 | Regression: Department-Wechsel im Header | [x] 2026-05-31 |
| 025 | Mobile: Drawer schliesst nach Navigation; kein doppeltes Scrollen (inkl. mit Dev-Banner, siehe 017g) | [x] 2026-05-31 |
| 026 | `keep-alive` in `AppLayout` weiter funktionsfähig | [x] 2026-05-31 |
| 027 | Screenshot/Doku: Shell vorher/nachher in PR-Beschreibung | [x] 2026-05-31 |
| 028 | **Review-Stopp Phase 1 (Shell):** Shell auf Phone + Desktop freigegeben? | [x] 2026-05-31 |
| 028a | **Dev UI Playground** (vor Phase 2): `views/dev/DevUiPlaygroundView.vue`, Route **`/:departmentId/dev/ui-playground`** (Alias **`/:departmentId/sandbox`**) in `AppLayout`, Vuetify-Sandbox; Sidebar nur `isDevToolsEnvironment()` | [x] 2026-05-31 |
| 028b | **Review-Stopp Phase 1 (gesamt):** Playground auf Dev-Host erreichbar; Theme/Shell sichtbar → **Phase 2 freigeben** | [x] 2026-05-31 |

**Wartung Playground:** Ab Phase 2 bei jedem neuen `E*`-/Layout-Baustein einen Eintrag auf der Playground-Seite ergänzen (bis Löschung in Schritt 127).

---

## Phase 2 — `E*`-Formular-Basis

**Ziel:** Standard-Inputs nur noch über `E*` in Views (Implementierung in `form/base/`). Neue `E*`-Komponenten zusätzlich auf der **Dev UI Playground**-Seite (028a) zeigen.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 029 | `ETextField.vue` (wrap `v-text-field`, outlined, density, Fehler-Slots) + Eintrag auf Dev UI Playground | [x] 2026-05-31 |
| 030 | `ESelect.vue` | [x] 2026-05-31 |
| 031 | `ECheckbox.vue` / `ESwitch.vue` | [x] 2026-05-31 |
| 032 | `ETextarea.vue` | [x] 2026-05-31 |
| 033 | `EButton.vue` (Varianten primary/secondary/text) | [x] 2026-05-31 |
| 034 | `EDialog.vue` + `ECard` (Modal-Grundgerüst) | [x] 2026-05-31 |
| 035 | Gemeinsame Props dokumentieren (label, disabled, rules) in `form/base/README.md` oder Standards | [x] 2026-05-31 |
| 036 | Eintrag `E*` in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) | [x] 2026-05-31 |
| 037 | Pilot: `LoginView` Formularfelder auf `E*` (ohne AutoSave) | [x] 2026-05-31 |
| 038 | Abgleich mit bestehendem `outlined-field.css` — was bleibt, was entfällt | [x] 2026-05-31 |
| 039 | Vitest/Snapshot optional für `ETextField` (Smoke) | [-] optional, übersprungen (kein Vitest-Setup im Frontend) |
| 040 | Kein `v-text-field` in Views ausserhalb `form/base/` (grep-Check) | [x] 2026-05-31 |
| 041 | Touch: Input font-size ≥ 16px auf Mobile | [x] 2026-05-31 |
| 042 | **Review-Stopp Phase 2:** Look der Felder = eMatChef-Design? | [x] 2026-05-31 |

---

## Phase 3 — AutoSave auf `E*` *(verschoben)*

**Ziel:** `AutoSaveField` nutzt innen `ETextField`; Loader/Diskette bleiben.

**Reihenfolge:** Phase 3 wird **nach Phase 5** (Einstieg) angegangen — wenn Login/Landing/Pending weit genug auf Vuetify/`E*` stehen. Bis dahin bleibt AutoSave auf nativen Inputs + bestehendem CSS (Material Detail unverändert).

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 043 | `AutoSaveFieldShell`: Text/Number/Date → `ETextField` | [ ] verschoben |
| 044 | Select/Checkbox-Zweige auf `ESelect` / `ECheckbox` | [ ] verschoben |
| 045 | `auto-save-field.css` an Vuetify-Feldanatomy anpassen (Balken unten, Diskette) | [ ] verschoben |
| 046 | Regression: `MaterialDetailView` Stammdaten Auto-Save | [ ] verschoben |
| 047 | Regression: Fehler + Retry + Blur-Revert | [ ] verschoben |
| 048 | Kein direktes `VTextField` in `autoSave/*` | [ ] verschoben |
| 049 | Doku AutoSave in wiederverwendbare-komponenten.md aktualisieren | [ ] verschoben |
| 050 | **Review-Stopp Phase 3:** Auto-Save UX unverändert oder besser? | [ ] verschoben |

---

## Phase 4 — Layout-Bausteine

**Ziel:** Wiederkehrende Muster zentral; weniger `page-layout.css` in migrierten Views.

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 051 | `PageShell` in 2 weiteren Views (`ContactsView`, `TasksShellView`) | [x] 2026-05-31 |
| 052 | Filter-Zeile: `EFilterRow` + `ESearchField` (Suche) / `ESelect` (Filter), Pilot Kontakte | [x] 2026-05-31 |
| 053 | `GlobalToastContainer` → Vuetify `v-alert` (tonal) + Theme, `Teleport` body | [x] 2026-05-31 |
| 054 | Confirm/Prompt-Dialoge auf `EDialog` (`useConfirm`, `usePrompt`) | [x] 2026-05-31 |
| 055 | Leere Zustände: `EEmptyState` (Pilot Kontakte) | [x] 2026-05-31 |
| 056 | Loading: `ELoadingState` + `v-skeleton-loader` (Pilot Kontakte) | [x] 2026-05-31 |
| 057 | Tabellen-Entscheid: [table-patterns.md](./ui/table-patterns.md) | [x] 2026-05-31 |
| 058 | `EResponsiveDataList`: Desktop-Tabelle + Mobile-Liste — Pilot **Material** (`MaterialListDataTable`, `MaterialListMobile`) | [x] 2026-05-31 |
| 059 | `tables.css` / `modals.css`: Migrationshinweise in Dateikopf | [x] 2026-05-31 |
| 060 | **Review-Stopp Phase 4:** Pattern für alle Feature-Teams klar? | [x] 2026-05-31 |

### Entscheide Phase 4 (festgehalten)

| Thema | Entscheid |
| ----- | --------- |
| PageShell / Filter | `PageShell` + `EFilterRow` + `ESearchField`/`ESelect` in migrierten Views (Pilot: Kontakte, Tasks) |
| Toasts / Dialoge | `GlobalToastContainer` → `v-alert`; Confirm/Prompt → `EDialog` |
| Leer / Laden | `EEmptyState` + `ELoadingState` statt `.empty-state` / `.loading-state` |
| Tabellen | Entscheid in [table-patterns.md](./table-patterns.md): HTML-`<table>` für reiche Listen; `v-data-table` in List-Komponenten; Mobile via `EResponsiveDataList` |
| Responsive Listen | `EResponsiveDataList` mit `#table` / `#mobile`; Breakpoint `useDisplay().mdAndUp` (md = 960px) |
| Legacy CSS | `tables.css` / `modals.css` bleiben für nicht migrierte Views; Hinweise im Dateikopf (059) |

### Review-Stopp Phase 4 (Schritt 060)

| Bereich | Phone (~375px) | Desktop (~1280px) |
| ------- | -------------- | ----------------- |
| Material-Liste: `v-list` statt Tabelle | [x] | — |
| Material-Liste: `v-data-table` + Combo-Expand | — | [x] |
| Kontakte: PageShell, Filter, Empty, Loading | [x] | [x] |
| Confirm/Prompt-Dialog (`EDialog`) | [x] | [x] |
| Toast (`v-alert` tonal) | [x] | [x] |
| Dev UI Playground: Material-Sandbox = Produktions-Pattern | [x] | [x] |

**Freigabe:** Phase 5 (Login, Landing, Pending) — Phase 3 (AutoSave) weiterhin verschoben.

---

## Phase 5 — Einstieg & öffentliche Einstiege

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 061 | `LoginView.vue` vollständig Vuetify + `E*` | [x] 2026-05-31 |
| 062 | `VerifyEmailView.vue` | [x] 2026-05-31 |
| 063 | `PendingAssignmentView.vue` | [x] 2026-05-31 |
| 064 | `LandingHomeView` + `PublicSiteLayout` (Marketing) | [x] 2026-05-31 |
| 065 | Blog/FAQ/TOS/Impressum — einheitlich oder Phase 12 Rest | [x] 2026-05-31 |
| 066 | `DisplayEntryView` / `DepartmentDisplayView` — optional, Entscheid im Review | [x] 2026-05-31 — Einstieg + PIN auf `E*`; Infoscreen-Layout unverändert |
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
| 127 | **Dev UI Playground entfernen** (temporär seit 028a): View, Route, Sidebar-Eintrag, Locale-Keys, ggf. `views/dev/` — nach vollständiger Migration nicht mehr nötig | [ ] |

---

## Notizen aus gemeinsamer Durchsicht

| Datum | Phase/Schritt | Entscheid |
| ----- | ------------- | --------- |
| 2026-05-31 | Phase 0 | Dark Mode später; MDI = `@mdi/font`; Pilot 007 entfällt; Reset entschärfen; Umsetzung separater Chat |
| 2026-05-31 | Phase 0 Umsetzung | Branch `feat/vuetify-phase-0-foundation`; Vuetify **4.0.8** (npm latest); Build grün |
| 2026-05-31 | Schritt 011 | Kurztest Dashboard (`AppLayout`/`v-app`) @ 375×812 + 1280×800: kein horizontaler Page-Scroll; Mobile noch enge Sidebar (64px) — Drawer-Umbau folgt Phase 1 (014–016) |
| 2026-05-31 | Schritte 014–017 | `v-navigation-drawer` (rail/hover desktop, temporary mobile), `v-app-bar` + Hamburger, Layout ohne `margin-left` |
| 2026-05-31 | Phase 1 Plan | **Variante B:** ein `v-app` in `App.vue`, Dev-Banner zuerst, Inhalt verkleinert — Schritte **017b–017g** |
| 2026-05-31 | Phase 1 Plan | **028a–028b:** Dev UI Playground (Sidebar nur `isDevToolsEnvironment()`), vor Phase 2; Löschung Schritt **127** |
| 2026-05-31 | Phase 1 abgeschlossen | **020–028b:** Safe-Area, Sidebar-Token, Supplier=AppLayout, Scroll/Drawer-Fix, Phase-2-Freigabe |
| 2026-05-31 | Phase 2 abgeschlossen | Review 042; 039 Vitest übersprungen |
| 2026-05-31 | Phase 4 abgeschlossen | Review 060; 058 Pilot Material (`EResponsiveDataList` + `v-data-table`/`v-list`); 057 [table-patterns.md](./ui/table-patterns.md) |
| 2026-05-31 | Schritt 061 | `LoginView`: alle Formulare auf `E*` + `v-alert` + `ECard`; Reset-Code via `EOtpInput` |
| 2026-05-31 | Schritt 062 | `VerifyEmailView`: `ECard`, `ELoadingState`, `v-alert`, `EButton`, i18n |
| 2026-05-31 | Reihenfolge | **Phase 4 vor Phase 3** — AutoSave auf `E*` erst nach Fortschritt Phase 5 |
| 2026-05-31 | Phase 5 | **064–066:** Marketing (Landing, Blog/FAQ/TOS/Impressum), Display-Einstieg + PIN auf `E*` |

---

## Phase 1 — PR-Checkliste (Schritt 027)

Für die PR-Beschreibung Screenshots / Kurztest:

| Bereich | Phone (~375px) | Desktop (~1280px) |
| ------- | -------------- | ----------------- |
| Dev-Banner + App-Bar + Drawer sichtbar, kein Überlap | ☐ | ☐ |
| `v-main` scrollt, kein Doppel-Scroll (Fenster + Main) | ☐ | ☐ |
| Sidebar: Rail + Hover-Expand, aktiver Nav-Link | — | ☐ |
| Hamburger → Drawer → Link → Drawer zu | ☐ | — |
| Department-Wechsel im Header, Route `/neueId/…` | ☐ | ☐ |
| Supplier `/supplier/:id/profile` gleiche Shell | ☐ | ☐ |
| UI Sandbox `/{id}/sandbox` erreichbar (Dev-Host) | ☐ | ☐ |
| `MaterialsView` / `ActivitiesView` keep-alive (Tab wechseln, zurück) | ☐ | ☐ |

**Vorher/Nachher:** optional 1× Dashboard-Screenshot alt (CSS-Sidebar) vs. neu (Vuetify-Drawer) beilegen.

---

## Nächster Schritt (aktuell)

**Phase 5** — Schritt **068** (Review-Stopp: Login, Landing, Blog/FAQ/TOS/Impressum, Display-Einstieg @ 375px + 1280px). Optional danach **067** (öffentliche QR-Views) oder direkt **Phase 6** (Aktivitäten). Phase 3 (AutoSave) ist verschoben.
