# Umbauplan: Vuetify (Frontend)

Abarbeitbare Checkliste für die schrittweise Einführung von **Vuetify 3** und **`E*`-Komponenten**. Regeln: [vuetify-standards.md](./vuetify-standards.md).

**Stand:** Mai 2026 · **Status:** Phase 9 **in Arbeit** (099–100 erledigt). Phase 3 (AutoSave auf `E*`) **verschoben**.

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
| 5 | Einstieg (Login, Landing, Pending) | 061–068 | nach 068 — **abgeschlossen** |
| 6 | Aktivitäten | 069–084 | nach 084 — **abgeschlossen** |
| 7 | Material | 085–092 | nach 092 — **abgeschlossen** |
| 8 | Kontakte, Aufgaben, Inbox | 093–098 | nach 098 — **abgeschlossen** |
| 9 | Department-Settings | 099–108 | nach 108 — **in Arbeit** |
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
| 067 | Öffentliche QR-Views (`PublicMaterialView` etc.) — optional | [-] Footer vereinheitlicht; Layout-Migration Phase 12 |
| 068 | **Review-Stopp Phase 5:** Erster Eindruck (Login/Landing) auf Mobile OK? | [x] 2026-05-31 — Marketing + Copyright-Footer freigegeben |

---

## Phase 6 — Aktivitäten

**Hinweis:** Große Dateien — in mehrere PRs splitten (Liste / Detail / Wizard / Pack).

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 069 | `ActivitiesView`: Listen-Header + `PageShell` + Filter | [x] 2026-05-31 |
| 070 | Aktivitäten-Liste: Mobile-Karten oder scrollbare Tabelle | [x] 2026-05-31 |
| 071 | `ActivityCreateWizard.vue` + `ActivityCreateWizardForm.vue` Shell | [x] 2026-05-31 |
| 072 | Wizard Schritte: Stammdaten-Felder → `E*` / `AutoSaveField` | [x] |
| 073 | `ActivityZeitraumDatetimeFields` + Datepicker-Komponenten (Sandbox = Referenz, Wizard gleicher Stack) | [x] 2026-05-31 |
| 074 | `VDatePicker` / Menü: Doppelkalender, Presets, Touch-Wisch, Fixe Daten | [x] 2026-05-31 |
| 075 | Kalender-Marker (Feiertage, fcal, Fixe Daten); Schnellauswahl nur Lagerwoche/Sonstiges + Samstage | [x] 2026-05-31 |
| 076 | `ActivityDraftOverviewForm` Zeitraum-Auto-Save (Debounce + VDatePicker-Menüs) | [x] 2026-05-31 |
| 077 | `ActivityDetailView` Header + Tabs Shell | [x] 2026-05-31 |
| 078 | Tabs: Material, Kosten, Verbrauch — schrittweise | [x] 2026-05-31 |
| 079 | `ActivityPackListTab` — Shell + Zeilen (`EButton` in Pack-Zeilen) | [x] 2026-05-31 |
| 080 | Pack-Modals → `EDialog` via `PackWorkflowModal` | [x] 2026-05-31 |
| 081 | `activity-create-wizard.css` / `detail-panel.css`: deprecated Teile markieren | [x] 2026-05-31 |
| 082 | `useDisplay` statt neue `@media` in migrierten Activity-CSS | [x] 2026-05-31 |
| 083 | Regression: Aktivität anlegen, Zeitraum, Packliste Smoke | [x] 2026-05-31 |
| 084 | **Review-Stopp Phase 6:** Aktivitäten auf Phone nutzbar? | [x] 2026-05-31 |

---

## Phase 7 — Material

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 085 | `MaterialsView` Liste + Filter + `PageShell` | [x] 2026-05-31 |
| 086 | `MaterialDetailView` Header + Stammdaten-Shell (`E*` Header, manuelles Speichern) | [x] 2026-05-31 |
| 087 | `MaterialDetailView` Tabs → `v-tabs` / `v-tabs-window` + 4× `EDialog` | [x] 2026-05-31 |
| 088 | `MaterialImagePicker` / Medien — Vuetify wo sinnvoll | [x] 2026-05-31 |
| 089 | Import-UI (`MaterialImportSettingsView`) — Shell + Hauptaktionen | [x] 2026-05-31 |
| 090 | `materials-view.css` / `material-detail-view.css` bereinigen | [x] 2026-05-31 |
| 091 | Regression: Material suchen, Detail, QR | [x] 2026-05-31 |
| 092 | **Review-Stopp Phase 7** | [x] 2026-05-31 |

---

## Phase 8 — Kontakte, Aufgaben, Inbox

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 093 | `ContactsView` + `ContactDetailView` | [x] 2026-06-02 |
| 094 | `TasksShellView` / `TasksGeneralView` | [x] 2026-06-02 |
| 095 | `NotificationsCenterView` + Inbox-Modals | [x] 2026-05-31 — `v-tabs`, `ELoadingState`, `EButton`; 4× Inbox-Modals `EDialog`/`E*`; `tasks-tabs.css` + `inbox-modal.css` |
| 096 | `NotificationSenderBlock` / Avatare — Stil abgleichen | [x] 2026-05-31 — System/Activity-Icons auf `--color-primary` / `--color-primary-dark` |
| 097 | `contacts-view.css` responsive durch Vuetify ersetzen | [x] 2026-05-31 — Liste `EResponsiveDataList`/`v-data-table`; Wrapper `.contacts-table-wrapper` bleibt; tote `.contacts-table`-Regeln in `tables.css` → Phase 12 |
| 098 | **Review-Stopp Phase 8** | [x] 2026-05-31 |

---

## Phase 9 — Department-Settings

| Nr | Schritt | Status |
| -- | ------- | ------ |
| 099 | `SettingsView` Shell + Navigation | [x] 2026-05-31 — md+: statische Sidebar; Mobile: Hamburger + `v-menu`-Overlay; `settings-shell.css` |
| 100 | `UsersSettingsView` / `GroupsSettingsView` | [x] 2026-05-31 — `E*` Header/States/Dialoge; Tabellen noch HTML |
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
| 124 | `vue-datepicker` entfernen — nur wenn Schritt 074 auf VDatePicker umgestellt | [x] 2026-05-31 |
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
| 2026-05-31 | Phase 6 / 076 | Draft-Overview: Zeitraum PATCH mit Debounce (800ms) nach Picker/Uhr-Änderung; Blur bleibt; Nutzung bei `usageDatesLocked` nicht im Payload |
| 2026-05-31 | Phase 6 / 073–075 | **Zeitraum-UI freigegeben:** Sandbox = Referenz; `VDatePicker`, Doppelkalender, Presets, Touch-Wisch |
| 2026-05-31 | Phase 6 / 074–075 | **VDatePicker** (`VDateInput` labs): `@vuepic` entfernt; Feiertage + fcal via `useActivityDatePickerEvents` |
| 2026-05-31 | Phase 4 abgeschlossen | Review 060; 058 Pilot Material (`EResponsiveDataList` + `v-data-table`/`v-list`); 057 [table-patterns.md](./ui/table-patterns.md) |
| 2026-05-31 | Schritt 061 | `LoginView`: alle Formulare auf `E*` + `v-alert` + `ECard`; Reset-Code via `EOtpInput` |
| 2026-05-31 | Schritt 062 | `VerifyEmailView`: `ECard`, `ELoadingState`, `v-alert`, `EButton`, i18n |
| 2026-05-31 | Reihenfolge | **Phase 4 vor Phase 3** — AutoSave auf `E*` erst nach Fortschritt Phase 5 |
| 2026-05-31 | Phase 5 | **064–066:** Marketing (Landing, Blog/FAQ/TOS/Impressum), Display-Einstieg + PIN auf `E*` |
| 2026-05-31 | Phase 6 / 083 | Regression: `npm run build` grün; Pack-Modals → `PackWorkflowModal`/`EDialog`; `useDisplayHostClasses` an Hosts; manuelle Smoke-Checkliste unten |
| 2026-05-31 | Phase 6 / 084 | Review-Stopp: Phone-Layout freigegeben; Tab-Panel-Padding sm-down; Phase 7 freigegeben |
| 2026-05-31 | Phase 7 / 085–092 | Liste: `PageShell`, `v-tabs`, `EFilterRow`, `EResponsiveDataList`; Detail: `v-tabs` + Header-`E*`; Import-Shell; CSS-Legacy-Table entfernt; Review 092 |

---

## Phase 6 — Regression-Checkliste (Schritt 083)

**Automatisch (2026-05-31):** `vue-tsc` + Vite-Build grün; 7 Pack-Workflow-Modals nutzen `PackWorkflowModal`/`EDialog`; `detail-panel-legacy.css` nicht importiert; migrierte Activity-CSS ohne `@media` (Ausnahme: `detail-workflow.css` 768px).

**Manuell** (eingeloggt, Test-Abteilung; Phone ~375px + Desktop ~1280px):

| Bereich | Phone | Desktop |
| ------- | ----- | ------- |
| Aktivitäten-Liste: Filter/Tabs, Mobile-Karten, Stats ab sm | [x] | [x] |
| **Anlegen:** Wizard öffnen, Typ wählen, Zeitraum (Pill/Mobile-2-Zeiler), Footer-Buttons | [x] | [x] |
| **Anlegen:** Vorschau-Spalte ab 600px; Dialog-Rand ab 960px (nicht Fullscreen) | — | [x] |
| **Anlegen:** Draft «Weiter» / Speichern ohne Console-Error | [x] | [x] |
| **Detail:** Header scrollt mit Seite (Mobile); Workflow-Buttons horizontal scrollbar | [x] | [x] |
| **Detail:** Tabs wechseln (Übersicht, Material, …) | [x] | [x] |
| **Zeitraum Draft:** `ActivityDraftOverviewForm` Auto-Save nach Picker (Debounce) | [x] | [x] |
| **Datepicker:** Menü Presets + Kalender (Mobile: Presets unten) | [x] | [x] |
| **Packliste:** Tab sichtbar (Status ≥ approved); Stufen-Tabs, Zeilen-Aktionen | [x] | [x] |
| **Packliste:** Mind. 1 Modal öffnen/schliessen (`EDialog`, X in Titelzeile) | [x] | [x] |
| **Sandbox** `/{id}/sandbox` → Zeitraum-Demo (optional Referenz) | [x] | [x] |

**Referenz-Routen:** `/{departmentId}/activities`, `/{departmentId}/activities/:id`, `/{departmentId}/sandbox`.

### Review-Stopp Phase 6 (Schritt 084)

| Bereich | Phone (~375px) | Desktop (~1280px) |
| ------- | -------------- | ----------------- |
| Liste: `EResponsiveDataList` Karten unter md, kompakte Filter (`--sm-down`) | [x] | [x] |
| Wizard: Fullscreen unter md, Footer eine Zeile, Typ-Chips touch | [x] | [x] |
| Detail: TopHeader scrollt mit Inhalt; kein «Zurück» unter sm; Workflow nur Text | [x] | [x] |
| Detail: Tabs + Tab-Inhalt-Padding 12px (sm-down) | [x] | [x] |
| Zeitraum: Pill horizontal scroll / Mobile-2-Zeiler; Picker-Menü stapelt | [x] | [x] |
| Pack: `EDialog`-Modals, Workflow-Zeilen | [x] | [x] |
| Login-Shell @ 375px (Eingang zu Aktivitäten) | [x] | — |

**Freigabe:** Phase 7 (Material) — Rest: `detail-workflow.css` @media 768px Packliste optional später auf `useDisplayHostClasses`.

**Hinweis:** Authentifizierte Flows anhand Implementierung + Dev-Stack (`*.ematchef.test`); bei Abweichungen in Produktion Issue anlegen.

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

## Phase 7 — Regression-Checkliste (Schritt 091)

**Automatisch (2026-05-31):** `vue-tsc` + Vite-Build grün; `MaterialsView` nutzt `PageShell` + `EResponsiveDataList` (kein Legacy-`<table class="materials-table">`); `materials-view.css` ohne `.materials-table` / `.material-tabs`; `MaterialDetailView` mit `v-tabs` (kein `.tab-nav`).

**Manuell** (eingeloggt, Test-Abteilung; Phone ~375px + Desktop ~1280px):

| Bereich | Phone | Desktop |
| ------- | ----- | ------- |
| Material-Liste: Tabs, Suche, Filter, Mobile-Karten | [x] | [x] |
| Liste: Zeile öffnen → Detail | [x] | [x] |
| Detail: Header (Zurück, QR, Schliessen, Speichern) | [x] | [x] |
| Detail: Tabs wechseln (Daten, Bestand, …) | [x] | [x] |
| Detail: Mind. 1 `EDialog` (Komposition/Kiste) öffnen/schliessen | [x] | [x] |
| Bild: `MaterialImagePicker` Menü (Upload/URL) | [x] | [x] |
| Import (Settings): Tabs Import/Export, Datei-Upload | [x] | [x] |

**Referenz-Routen:** `/{departmentId}/materials`, `/{departmentId}/materials/:id`, `/{departmentId}/settings/material-import`.

**Rest Phase 7 (später):** Tab-Inhalte Detail (Batch-Tabellen, innere Buttons); Import-Mapping-Zellen (`ESelect`); vollständige Stammdaten auf `AutoSaveField` (Phase 3).

### Review-Stopp Phase 7 (Schritt 092)

| Bereich | Phone (~375px) | Desktop (~1280px) |
| ------- | -------------- | ----------------- |
| Liste: `EResponsiveDataList`, Filter unter Tabs | [x] | [x] |
| Detail: sticky `v-tabs`, scrollender Inhalt | [x] | [x] |
| Detail: Header-Aktionen erreichbar | [x] | [x] |
| Import: `PageShell` + Haupt-CTAs | [x] | [x] |

**Freigabe:** Phase 8 (Kontakte, Aufgaben, Inbox).

### Review-Stopp Phase 8 (Schritt 098)

| Bereich | Phone (~375px) | Desktop (~1280px) |
| ------- | -------------- | ----------------- |
| Kontakte: `EResponsiveDataList`, Suche, Detail | [x] | [x] |
| Aufgaben: Shell-Tabs + Status-Tabs (Marken-Grün) | [x] | [x] |
| Inbox: Posteingang/Gesendet `v-tabs`, Compose, Detail-Modals | [x] | [x] |
| `NotificationSenderBlock` Markenfarben | [x] | [x] |

**Referenz-Routen:** `/{departmentId}/contacts`, `/{departmentId}/tasks`, `/{departmentId}/notifications`.

**Rest Phase 8 (später):** `TasksPrintView` (Druck-Tab); tote `.contacts-table`-Selektoren in `tables.css`.

**Freigabe:** Phase 9 (Department-Settings).

---

## Nächster Schritt (aktuell)

**Phase 9** — **101** `MyDepartmentSettingsView` + Adresse + Join-Code. **099–100** erledigt; `GeneralSettingsView`/`AddonsSettingsView` teilweise `E*`.

### Erledigt: Schritt 073–075 — Zeitraum / Datepicker

**Referenz:** `/{departmentId}/sandbox` → Aktivität Zeitraum (`ActivityDatetimeSandboxFields`).

**Produktion:** `ActivityZeitraumDatetimeFields` in `ActivityCreateWizardForm` + `ActivityDraftOverviewForm` — dieselben Bausteine (`ActivityDateField`, `ActivityDateRangeField`, `ActivityTimeField`, `ActivityResponsiveDateTimeRow`). Wiederverwendung: [activity-datetime-fields.md](./activity-datetime-fields.md).

| Thema | Verhalten |
| ----- | --------- |
| Layout | Pill-Zeile ab `sm`, Mobile 2-zeilig |
| Zeitraum sm+ | Doppelkalender, gemeinsame Kopfzeile «Juni 2026 Juli» |
| Zeitraum Mobile | Ein Kalender, Schnellauswahl **unten** |
| Schnellauswahl | Nur **Lager** / **Event**: Samstage + Fixe Daten **Lagerwoche** / **Sonstiges** (keine Schulferien, kein Mat-Büro geschlossen) |
| Navigation | Pfeile, Mausrad (vertikal), Touch **links/rechts** |
| Marker | Feiertage, fcal, alle Fixe Daten; nur `department_break` blockiert Auswahl |

**Entscheid:** `VDatePicker` / `VDateInput` (Vuetify labs) — mobil-first, kein `@vuepic`.

### Erledigt: Schritt 076 — Zeitraum-Auto-Save (Draft-Overview)

- **Feldtypen:** Erstell-Wizard = normale `ETextField`/`ESelect` (kein AutoSave); Entwurfs-Detail = `AutoSaveField` (Diskette + Fortschrittsbalken bei Blur)
- `ActivityDraftOverviewForm`: Zeitraum wie AutoSave (Blur, `AutoSaveFieldShell`), nur camp/event/external
- **Entwurf verwerfen:** Wizard-Footer + Detail-Übersicht nach Server-Entwurf (Schritt 1 «Weiter»); `DELETE /api/activities/:id`
- Typ **activity:** kein Entwurfmodus (Single-Step-Wizard, direktes Einreichen)
- Menüs an `body` — Focusout ignoriert Picker-Overlays
- Bei `usageDatesLocked`: nur Material-Zeiten/-Daten speicherbar

---
