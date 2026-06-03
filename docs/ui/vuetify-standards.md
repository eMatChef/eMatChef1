# UI-Standard: Vuetify (eMatChef Frontend)

Verbindliche Regeln für die schrittweise Einführung von **Vuetify 3** in der Vue-SPA. Der konkrete Ablauf steht in [vuetify-migration-plan.md](./vuetify-migration-plan.md).

**Stand:** Juni 2026 · **Status:** **Umgesetzt** (Phasen 0–11) — Wartungsmodus; Restliste Phase 12 in [vuetify-migration-plan.md](./vuetify-migration-plan.md).

---

## Ziele

- **Einheitliches UI** über alle Bereiche der App (Layout, Formulare, Dialoge, Feedback).
- **Responsive Verhalten** (inkl. Smartphone) über Vuetify-Breakpoints und `useDisplay()`, nicht über ad-hoc-`@media` pro View.
- **Weniger Duplikat-CSS** — gemeinsame Patterns in Wrapper-Komponenten und Theme, nicht in jeder View neu.
- **Bestehende Bausteine nutzen** — `AutoSaveField`, Marken-Tokens, zentrale Utils bleiben; sie werden innen auf Vuetify umgestellt.

---

## Geltungsbereich

| Bereich | Vuetify-Pflicht | Anmerkung |
| -------- | ---------------- | ---------- |
| `app.ematchef.ch` (eingeloggte App) | ja, nach Migration | Hauptfokus |
| Login, Landing, öffentliche Marketing-Seiten | ja, wenn migriert | gleiche Theme-Konfiguration |
| Öffentliche QR/Lookup-Seiten (`/i/…`) | optional | dürfen schlank custom bleiben, wenn Aufwand unverhältnismässig |
| Infoscreen `/display/…` | optional | oft Vollbild-Kiosk, eigenes Layout möglich |
| **`devices.ematchef.ch`** | **nein** (eigenes UI) | siehe [devices/concept.md](../devices/concept.md) — nicht in diesen Plan mischen |
| Supplier-Portal | ja, im gleichen Stil wie App | mit App-Shell oder angepasster Shell, aber gleiche `E*`-Komponenten |

---

## Komponenten-Schichten

**Verbindlicher Präfix der Mittelschicht: `E*`** (= eMatChef-eigenes Design auf Vuetify). Es gibt keinen parallelen `R*`- oder anderen Wrapper-Namespace.

| Präfix | Bedeutung | Beispiel |
| ------ | --------- | -------- |
| `V*` | Vuetify (Bibliothek) | `VTextField` — nur in `form/base/` |
| **`E*`** | **eMatChef UI-Design** (Defaults, Look) | `ETextField`, `EDialog` |
| `Emc*` | Marken-Assets, kein Formular-Wrapper | `EmcLogoMark` |
| (Name) | Domäne / Speichern | `AutoSaveField`, `ActivityDateField` |

Neue und migrierte UI folgt **drei Ebenen** (unten = nur intern, oben = in Views verwenden):

```
Views / Feature-Komponenten
        ↓
  AutoSaveField, domain-spezifische Felder (z. B. ActivityDateField)
        ↓
  E*-Wrapper (eMatChef-Design: Dichte, Varianten, Theme)
        ↓
  Vuetify (VTextField, VSelect, VDialog, …)
```

### 1. Vuetify (`V*`)

- Direkt in Views **verboten**, ausser in `components/form/base/` (dort nur als Implementierung der `E*`-Komponenten) und explizit dokumentierten Ausnahmen.
- Konfiguration zentral: `frontend/src/plugins/vuetify.ts`.

### 2. eMatChef-Design (`E*`)

- Pfad: `frontend/src/components/form/base/`
- Beispiele: `ETextField`, `ESelect`, `ECheckbox`, `ETextarea`, `EDialog`, `EButton`
- **Ein Standard, einmal definiert:** Vuetify `variant="outlined"` + globales `e-form-field.css` (in `style.css`) mappt Marken-Tokens auf `.v-field__outline` — **nicht** 100× eigenes Feld-CSS in Views.
- Label extern (`.field-outline-label` aus `outlined-field.css`), wie AutoSave/Material-Detail.
- **Keine** Vuetify-`defaults` für Formulare in `vuetify.ts` — Variant/Look leben in E* + CSS.
- **Regel:** Vuetify-Input in 2+ Screens → E*-Wrapper, dann überall E* verwenden.
- Details: [`form/base/README.md`](../../frontend/src/components/form/base/README.md)
- **Icon-Welt:** `Icon*`-SVG parallel zu MDI — Inventar und MDI-Zuordnung: [icon-inventory.md](./icon-inventory.md)

### 3. Domäne / API / Auto-Save

- **`AutoSaveField`** bleibt die öffentliche API für speichernde Felder; innen nutzt es **`ETextField`** (nie direkt `VTextField` ausser innerhalb von `ETextField.vue`).
- Domänen-Wrapper (z. B. `ActivityDateField`) dürfen Vuetify oder vorübergehend `@vuepic/vue-datepicker` nutzen — Status im Migrationsplan.
- Neue API-Felder: bevorzugt `AutoSaveField` + `save`-Prop, nicht eigene Debounce-Logik in der View.

---

## Theme und Markenfarben

Vuetify-Theme **muss** an `frontend/src/styles/ui/brand-tokens.css` ausgerichtet werden:

| Token (CSS) | Vuetify `theme.colors` |
| ----------- | ---------------------- |
| `--color-primary` | `primary` |
| `--color-primary-light` | optional `primary` lighten / secondary accent |
| `--color-error` | `error` |
| `--color-text` / `--color-text-muted` | über `on-surface` / Custom-CSS wo nötig |
| `--emc-logo-bg` | Sidebar/Logo-Bereich (ggf. Custom, nicht nur Theme) |

- **Kein zweites Farbsystem** in migrierten Views (keine neuen hardcodierten `#10b981` in scoped CSS).
- Sidebar-Farbe (`#26353b`) kann als Custom-CSS am Drawer bleiben, bis Theme erweitert wird.

---

## Responsive / Mobile

### Breakpoints

Vuetify-Standard nutzen (`xs` … `xxl`). **Keine neuen** willkürlichen Pixel-Grenzen (520, 599, …) in migrierten Dateien.

| Bedarf | API |
| ------ | --- |
| „Ist Smartphone?“ | `useDisplay().mobile` oder `smAndDown` |
| Layout zwei Spalten | `v-col` mit `:cols="12"` und `:md="6"` |
| Navigation | `v-navigation-drawer` — auf Mobile temporär, Toggle über App-Bar |

### Mobile-First-Arbeitsweise

1. Basis-Layout für schmale Viewports (eine Spalte, volle Breite).
2. Erweiterung mit `mdAndUp` / Grid-Spalten.
3. Touch-Ziele min. ~44px; Formular-`font-size` min. 16px auf iOS (Zoom vermeiden).
4. Safe-Area: globale Utilities in `styles/ui/safe-area.css` (`pb-safe`, `px-safe`, `--emc-safe-*`); `index.html` mit `viewport-fit=cover`. App-Shell: `page-content` und Sidebar berücksichtigen `env(safe-area-inset-bottom)`.

### Safe-Area Utilities

| Klasse / Token | Verwendung |
| -------------- | ---------- |
| `--emc-safe-top` … `--emc-safe-left` | CSS-Variablen aus `env(safe-area-inset-*)` |
| `.pb-safe`, `.pt-safe`, `.px-safe`, `.py-safe` | Padding in Views / fixierte Leisten |
| `.mb-safe`, `.bottom-safe-fab` | FABs und fixierte Buttons |

### Hover

Keine Funktion nur per `:hover` — `(hover: hover) and (pointer: fine)` beibehalten, wo Hover-Zusatzinfos nötig sind.

---

## Layout-Patterns (migriert)

| Alt (CSS-Klasse / Pattern) | Neu (Vuetify) |
| --------------------------- | ------------- |
| `.page-header` + `.header-content` | `v-row` / `v-col` + `v-toolbar-title` oder Slot in `PageShell` |
| `.filter-bar` | `v-row` mit `v-text-field` / `v-select`, `density="compact"` |
| `.btn`, `.btn-primary` | `v-btn` mit `color="primary"` |
| Modals (custom) | `v-dialog` + `v-card` |
| Einfache Tabellen | Siehe [table-patterns.md](./table-patterns.md): HTML-`<table>` für Haupt-Listen; `v-data-table` nur für flache Admin-Listen; Mobile Karten via `EResponsiveDataList` (058) |
| Loading / Empty | `v-skeleton-loader`, `v-alert`, `v-empty-state` (falls genutzt) |

**`PageShell`:** `components/layout/PageShell.vue` — `v-container`, Slots `#title`, `#subtitle`, `#actions`, `#filters`, Default.

---

## Supplier-Portal (Phase 1)

- **Gleiche Shell** wie die Department-App: Route `/supplier/:companyId/*` nutzt **`AppLayout.vue`** (Drawer, `TopHeader`, `v-main`).
- Sidebar zeigt **„Meine Firma“**-Untermenü statt Department-Links; kein separates `v-app`.
- Views unter `views/supplier/` behalten vorerst eigenes Page-CSS (`.supplier-page-header`); schrittweise `PageShell` in späteren Phasen.

---

## Root-Layout: ein `v-app` + Dev-Banner (Variante B)

- **Genau eine** `v-app`-Instanz in `App.vue` (nicht pro `AppLayout` / Route).
- **`DevEnvironmentBanner`** (`components/common/DevEnvironmentBanner.vue`) ist das **erste Kind** innerhalb `v-app`, wenn `shouldShowDevEnvironmentBanner()` true ist.
- Der Banner ist **kein Overlay** und kein zusätzliches `100vh`: er belegt seine Zeilenhöhe oben; `router-view` füllt den **restlichen** Platz (`flex: 1`, `min-height: 0` auf dem Wrapper).
- **`AppLayout`** enthält nur Drawer, `v-app-bar`, `v-main` — **ohne** eigenes `v-app`; Höhe `100%` des Eltern-Wrappers, nicht `min-height: 100vh`.
- Globale Overlays (`GlobalToastContainer`, Dialoge) bleiben innerhalb derselben `v-app` (wie heute in `App.vue`).
- Umsetzung: [vuetify-migration-plan.md](./vuetify-migration-plan.md) Schritte **017b–017g**.

---

## Dev UI Playground (temporär, nur Testumgebung)

- **Zweck:** Vuetify-Theme, Shell und später alle **`E*`**-Bausteine an einer Stelle prüfen — **vor** und während der Migration, nicht für Endnutzer auf Produktion.
- **Sichtbarkeit:** wie der gelbe Dev-Banner — `isDevToolsEnvironment()` aus `@/utils/devEnvironmentBanner` (nicht nur `import.meta.env.DEV`). Sidebar-Hauptmenü-Eintrag nur dann.
- **Route:** **`/:departmentId/dev/ui-playground`** (Alias **`/:departmentId/sandbox`**) in `AppLayout`; nur wenn `isDevToolsEnvironment()`.
- **Inhalt:** Abschnitte Formulare, Buttons, Dialoge, Breakpoint-Anzeige; ab Phase 2 je neue `E*`-Komponente ergänzen. **Roh-`V*`** nur auf dieser Seite, klar als „Sandbox“ gekennzeichnet — produktive Views nutzen `E*`.
- **Lebensdauer:** anlegen Schritt **028a**, pflegen bis Migration fertig, entfernen Schritt **127** (Phase 12).

---

## Koexistenz während der Migration

Solange nicht alle Views migriert sind:

| Erlaubt | Verboten in **migrierten** Dateien |
| ------- | ------------------------------------- |
| Alte `.btn` / `forms.css` in nicht migrierten Views | Neue Abhängigkeit von `styles/ui/buttons.css` |
| Beide Shells kurz parallel (nur mit Feature-Flag/Branch, nicht in `prod` dauerhaft) | Mischung `v-btn` und `.btn-primary` **in derselben** Komponente |
| Domain-CSS (`styles/views/activities/…`) für Fachlogik | Duplikat von Vuetify-Layout (padding, border-radius) in scoped CSS |

**Faustregel:** Datei im Migrationsplan als `[x]` markiert → nur noch Vuetify + `E*` + erlaubtes Domain-CSS.

---

## Theme: Light / Dark

- **Phase 0 ff.:** nur Vuetify-Theme **`light`** (Farben aus `brand-tokens.css`).
- **Dark Mode:** bewusst **später** — erst nach stabiler Light-Migration; dann eigenes `theme.themes.dark` + Review.

---

## Icons (MDI)

- **Entscheid:** `@mdi/font` global importieren (`import '@mdi/font/css/materialdesignicons.css'`), Vuetify `defaultSet: 'mdi'`.
- **Grund:** wenig Setup in Phase 0; Icons in `v-btn`, `v-navigation-drawer`, `v-select` funktionieren sofort.
- Bestehende SVG-Icon-Komponenten (`components/icons/`) **parallel** behalten (Sidebar, Domain-Icons).
- **Optional später (Phase 12):** Umstieg auf `@mdi/js` nur für genutzte Icons (kleineres Bundle).

---

## Globaler CSS-Reset mit Vuetify

**Problem:** In `style.css` steht heute `* { margin: 0; padding: 0; box-sizing: border-box; }`. Das **padding: 0 auf allen Elementen** bricht Vuetify-Komponenten (`v-list-item`, `v-field`, Innenabstände).

**Empfehlung (Phase 0, Schritt 004):**

1. **`box-sizing: border-box`** auf `*, *::before, *::after` **behalten**.
2. **`padding: 0` vom Universal-Selektor entfernen** — Padding nur dort nullen, wo ihr es wirklich braucht (z. B. `body { margin: 0; }`).
3. **`margin: 0` nicht auf `*`**, sondern minimal: `body { margin: 0; }` (ggf. Überschriften später gezielt).
4. **`vuetify/styles` nach `style.css`** in `main.ts`, damit Komponenten-Styles greifen.
5. Nach der Änderung: **eine alte View + eine neue `v-btn`** kurz prüfen (kein „zusammengequetschter“ Listeneintrag).

**Nicht empfohlen:** Vuetify-Reset zusätzlich voll drüberlegen **ohne** euren `*` anzupassen — doppelte Kämpfe. **Auch nicht:** alles an Vuetify übergeben und gleichzeitig `* { padding: 0 }` behalten.

---

## i18n

- Vuetify-Locale über `vue-i18n`-Adapter (gleiche Sprachen wie App: `de`, `en-US`, …).
- UI-Texte weiter in `locales/*.json`, nicht in Komponenten hardcoden.

---

## Tailwind

- **Entscheidung (Phase 12):** `@tailwind` in `style.css` bleibt vorerst (PostCSS-Pipeline unverändert).
- **Produktive Views:** keine Tailwind-Utility-Klassen für Layout/Spacing — nur Vuetify + `E*` + Domain-CSS.
- **Entfernung:** optional später, wenn Build-Pipeline ohne Tailwind getestet ist; kein Blocker für Migration.

## Direkte Vuetify-Nutzung in Views (Ausnahmen)

| Erlaubt in Views | Grund |
| ---------------- | ----- |
| `v-btn` **innerhalb** `v-btn-toggle` | Toggle-Gruppen (z. B. Material-Liste, Werkstatt-Ansicht) |
| `v-tabs`, `v-data-table`, `v-list`, `v-alert`, `v-chip` | Layout-Bausteine Phase 4/7, noch kein E*-Wrapper |
| `v-card` in Marketing/Landing | Ausnahme dokumentiert |

**Verboten:** `v-text-field`, `v-select`, `v-dialog`, freistehendes `v-btn` in Feature-Views → nur `E*`.

---

## Tests & Definition of Done (pro migrierter View/Komponente)

- [ ] Build (`vue-tsc`, `vite build`) ohne Fehler
- [ ] Viewport **375px** und **1280px** manuell geprüft (kein horizontaler Page-Scroll)
- [ ] Fokus sichtbar, Dialog schliessbar, Formular mit Tastatur nutzbar
- [ ] Keine neuen `!important`-Kämpfe mit Vuetify
- [ ] Eintrag in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md), falls neue `E*`-Bausteine
- [ ] Schritt im [vuetify-migration-plan.md](./vuetify-migration-plan.md) auf `[x]`

---

## Dokumentation pflegen

| Änderung | Wo |
| -------- | --- |
| Neue `E*`-Komponente | [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) |
| Regel / Breakpoint / Theme | diese Datei |
| Tabellen (v-data-table vs. HTML vs. Mobile) | [table-patterns.md](./table-patterns.md) |
| Fortschritt Umbau | [vuetify-migration-plan.md](./vuetify-migration-plan.md) |
| Nur Geräte/Lager-UI | [devices/](../devices/) |

---

## Verwandte Dateien (Ist-Zustand)

| Thema | Pfad |
| ----- | ---- |
| Marken-Tokens | `frontend/src/styles/ui/brand-tokens.css` |
| Global CSS (wird schrittweise ersetzt) | `frontend/src/style.css`, `frontend/src/styles/ui/*` |
| App-Shell | `frontend/src/components/layout/AppLayout.vue`, `SidebarNavigation.vue`, `TopHeader.vue` |
| Auto-Save | `frontend/src/components/common/autoSave/` |
| Datepicker (bis Migration) | `@vuepic/vue-datepicker` in `ActivityDateField.vue` |
