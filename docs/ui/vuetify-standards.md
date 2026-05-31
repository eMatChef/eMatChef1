# UI-Standard: Vuetify (eMatChef Frontend)

Verbindliche Regeln für die schrittweise Einführung von **Vuetify 3** in der Vue-SPA. Der konkrete Ablauf steht in [vuetify-migration-plan.md](./vuetify-migration-plan.md).

**Stand:** Mai 2026 · **Status:** Spezifikation — Umsetzung beginnt mit Phase 0 des Migrationsplans.

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
- Beispiele (Zielbild): `ETextField`, `ESelect`, `ECheckbox`, `ETextarea`, `EDialog`, `EButton`
- Enthalten: Dichte, Varianten (`outlined`), Farben aus Theme, i18n-Anbindung wo nötig — **euer einheitliches Erscheinungsbild**, nicht das Roh-Default von Vuetify.
- **Regel:** Wenn ein Vuetify-Input in 2+ Screens vorkommt → zuerst `E*`-Wrapper, dann verwenden.
- **Nicht umbenennen** zu `R*`/`Ui*`/`Emc*` für Formulare; `Emc*` bleibt für Branding (Logo etc.).

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
4. `env(safe-area-inset-*)` für Vollbild/Drawer wo nötig (siehe globale Utilities in Migrationsplan Phase 0).

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
| Einfache Tabellen | `v-data-table` **oder** `v-list` + `v-card` auf Mobile |
| Loading / Empty | `v-skeleton-loader`, `v-alert`, `v-empty-state` (falls genutzt) |

**`PageShell` (geplant):** `v-container` + optional `v-row` für Titel, Actions, Filter — einmal bauen, in Views einbinden.

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

- **Entscheidung:** Während Vuetify-Migration **kein** neues Layout mit Tailwind-Klassen.
- Nach Abschluss Phase 11: Tailwind entfernen **oder** dokumentiert nur für Utilities (eine Linie, nicht beides für Spacing in derselben View).

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
