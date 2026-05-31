# Tabellen-Patterns (eMatChef Frontend)

**Stand:** Mai 2026 · **Schritt:** Phase 4 abgeschlossen (057–059) im [vuetify-migration-plan.md](./vuetify-migration-plan.md)

Entscheidung, wann **`v-data-table`**, wann **semantische HTML-Tabellen** und wann **Kartenlisten auf Mobile** — verbindlich für alle migrierten Views.

---

## Kurzfassung

| Situation | Entscheidung |
| --------- | ------------ |
| Haupt-Listen (Kontakte, Material, Aktivitäten, …) | **HTML-`<table>`** + `tables.css` — **kein** pauschaler Umstieg auf `v-data-table` |
| Mobile (kurzfristig) | **Horizontal scroll** (`overflow-x: auto`) — bereits im Einsatz, beibehalten bis Wrapper da ist |
| Mobile (mittelfristig, hoher Traffic) | **`EResponsiveDataList`** (Schritt 058): Desktop Tabelle, Mobile Karten — ein Datenmodell, zwei Darstellungen |
| Einfache Admin-/Read-only-Listen | **`v-data-table`** als Kandidat bei Migration (Sortierung, Pagination) |
| Detail-/Fach-Tabellen (Chargen, Zusammensetzung, …) | **HTML-`<table>`** dauerhaft — zu domain-spezifisch |

**Kein `VDataTable` direkt in Views** — falls eingeführt, nur über einen späteren `E*`-Wrapper (analog `ETextField`).

---

## Ist-Zustand (Mai 2026)

- **~40** eigene `<table>`-Implementierungen (Listen, Settings, Supplier, Buchhaltung, Detail-Panels).
- **Kein** `v-data-table` im produktiven Code.
- Gemeinsame Listen-Styles: `frontend/src/styles/ui/tables.css` (`.contacts-table`, `.materials-table`, `.activities-table`, …).
- **Mobile heute:** ab ~1024px `overflow-x: auto` + `min-width: 800px` auf der Tabelle (z. B. `contacts-view.css`, `materials-view.css`) — Nutzer scrollen horizontal, kein Layout-Wechsel.
- **Reiche Zeilen:** Avatare, Badges, mehrzeilige Zellen, Inline-Filter in `<th>`, Doppelklick → Detail (`ContactsView`).
- **Sortierung:** teils custom per Klick auf `<th>` (`ActivitiesView`), nicht zentral.

---

## Warum nicht alles auf `v-data-table`?

`v-data-table` lohnt sich vor allem bei **flachen Spalten**, **eingebauter Sortierung/Pagination/Selektion** und **wenig Custom-Markup pro Zelle**.

In eMatChef haben viele Listen:

- zusammengesetzte Zellen (Avatar + Name + Badge),
- domänenspezifische Aktionen pro Zeile,
- Filter in Spaltenköpfen,
- Doppelklick-Navigation,
- verschachtelte Tabellen in Detail-Views.

Ein blindes Mapping auf `v-data-table` würde **mehr Template-Komplexität** (`item.*` Slots für jede Spalte) bringen als Nutzen — und bestehendes Verhalten (Doppelklick, Soft-Loading) müsste neu verdrahtet werden.

---

## Drei Tabellen-Kategorien

### Kategorie A — Haupt-Listen (Feature-Views)

**Beispiele:** `ContactsView`, `MaterialsView`, `ActivitiesView`, `TasksGeneralView`, `NotificationsCenterView`, `WorkshopView` (Teile).

| Aspekt | Regel |
| ------ | ----- |
| Markup | Semantisches `<table>` + Klassen aus `tables.css` |
| Vuetify | Layout drumherum (`PageShell`, `EFilterRow`, `EEmptyState`, `ELoadingState`) |
| Sort/Filter | Bestehende Logik beibehalten; bei Neubau `useDisplay()` statt neuer `@media`-Pixel |
| Mobile kurzfristig | Wrapper `overflow-x: auto`; Breakpoint **`md` (960px)** statt 1024px bei nächster CSS-Berührung |
| Mobile mittelfristig | `EResponsiveDataList` (058) — nur für Views mit hohem Mobile-Anteil |

**Priorität für Karten-Mobile (058):** Kontakte → Material → Aktivitäten → Aufgaben/Inbox.

### Kategorie B — Einfache Admin-/Read-only-Tabellen

**Beispiele:** `SecurityMonitoringView`, `AccountingCostCentersView`, Supplier-`data-table`-Views, einfache Mitgliederlisten ohne Rich Cells.

| Aspekt | Regel |
| ------ | ----- |
| Migration | **`v-data-table`** prüfen, wenn ≤ 6 flache Spalten und Sort/Pagination von Vuetify profitiert |
| Wrapper | Später optional `EDataTable` in `form/base/` oder `layout/` — Views nutzen nur `E*` |
| Mobile | `v-data-table` mit `mobile` / horizontalem Scroll von Vuetify **oder** gleicher `EResponsiveDataList`-Slot |

### Kategorie C — Detail- und Fach-Tabellen

**Beispiele:** `.batch-table`, `.used-in-table`, `.composition-table`, `.combo-sub-table`, Import-Preview, Wizard-Zeilen.

| Aspekt | Regel |
| ------ | ----- |
| Markup | **Immer HTML-`<table>`** (oder `v-table` nur für reines Styling, wenn nötig) |
| `v-data-table` | **Nein** — verschachtelt, zu viele Sonderfälle |
| Mobile | Innerhalb Panels scrollen; keine Karten-Umstellung, ausser expliziter UX-Anforderung |

---

## Mobile: Tabelle vs. Kartenliste

### Option 1 — Horizontal scroll (aktuell, kurzfristig)

```css
/* Ziel: Breakpoint md (960) via useDisplay / Vuetify, nicht 1024px ad-hoc */
.list-table-wrapper {
  overflow-x: auto;
}
.list-table {
  min-width: 800px;
}
```

**Pro:** Kein zweites Template, schnell, bereits produktiv.  
**Contra:** Schlechtere UX auf schmalen Phones, versteckte Spalten.

**Entscheid:** **Beibehalten** bis `EResponsiveDataList` für die jeweilige View existiert. In **neuen** migrierten CSS-Dateien keine neuen `@media (max-width: 1024px)` — stattdessen `md` / `useDisplay().mdAndDown`.

### Option 2 — Kartenliste auf Mobile (Ziel für Haupt-Listen)

Ab **`mdAndDown`** (≤ 960px): gleiche Daten, Darstellung als **`v-list`** / **`v-card`** mit 2–4 Kernfeldern + Chevron/CTA.

```
Desktop (mdAndUp)          Mobile (mdAndDown)
┌─────────────────┐        ┌──────────────────┐
│ table thead/tbody│   →   │ v-card / v-list   │
│ 5–8 Spalten      │        │ Titel, Meta, Badge│
└─────────────────┘        └──────────────────┘
```

**Umsetzung:** Schritt **058** — `EResponsiveDataList` mit Slots `#table` und `#card` (oder `#item`), nicht pro View duplizieren.

**Pro:** Touch-freundlich, wichtigste Infos sichtbar.  
**Contra:** Zwei Darstellungen pflegen — deshalb **nur** über zentralen Wrapper.

### Option 3 — `v-data-table` mobile mode

Vuetify kann Tabellen auf Mobile vereinfachen. **Nicht Standard** in eMatChef, weil Kategorie-A-Listen ohnehin custom sind. Bei Kategorie B evaluieren.

---

## Zustände (Loading / Empty / Error)

| Zustand | Kategorie A (Listen) | Kategorie B (`v-data-table`) |
| ------- | -------------------- | ---------------------------- |
| Loading initial | `ELoadingState variant="table"` | `ELoadingState` oder `:loading` an `v-data-table` |
| Loading inline | `ELoadingState variant="inline"` | `:loading` |
| Leer | `EEmptyState` | `EEmptyState` im `#no-data` Slot oder statt Tabelle |
| Fehler | `EEmptyState` / Alert + `EButton` Retry | gleich |

Einheitlich **`E*`-Bausteine**, nicht pro View eigene Spinner-Divs.

---

## Breakpoints

| Alt | Neu (migriert) |
| --- | -------------- |
| `@media (max-width: 1024px)` | `useDisplay().mdAndDown` oder Vuetify-Grid `:cols="12"` |
| `@media (max-width: 768px)` | `smAndDown` wo sinnvoll |
| `min-width: 800px` auf Tabelle | Beibehalten bei Scroll-Strategie; in Karten-Views entfällt |

Konfiguration: `plugins/vuetify.ts` — **`md: 960`**.

---

## Migrations-Reihenfolge (Querverweise)

| Phase | Schritt | Tabellen-Thema |
| ----- | ------- | -------------- |
| 4 | 057 | **Dieses Dokument** |
| 4 | 058 | `EResponsiveDataList` — Pilot **Material** (`MaterialsView`) |
| 4 | 059 | Migrationshinweis in `tables.css`, `modals.css` |
| 6 | 070 | Aktivitäten-Liste: Mobile-Karten oder scroll |
| 7 | 085 | Material-View: PageShell + restliche Layout-Bausteine |
| 8 | 093–097 | Kontakte responsive / Karten |

---

## Checkliste pro migrierter Listen-View

- [ ] Kategorie A/B/C gewählt (in PR-Beschreibung erwähnen)
- [ ] `PageShell` + Filter-Zeile wo passend
- [ ] `ELoadingState` / `EEmptyState` statt `.loading-state` / `.empty-state`
- [ ] Mobile: Scroll **oder** `EResponsiveDataList` — nicht beides unkoordiniert
- [ ] Kein direktes `VDataTable` in der View
- [ ] Keine neuen `@media`-Pixel-Grenzen (520, 1024, …)

---

## Verwandte Dateien

| Thema | Pfad |
| ----- | ---- |
| Shared Listen-CSS | `frontend/src/styles/ui/tables.css` |
| Kontakte (Scroll) | `frontend/src/styles/contacts-view.css` |
| Material (Scroll) | `frontend/src/styles/materials-view.css` |
| Layout-Bausteine | `frontend/src/components/layout/` |
| UI-Standard | [vuetify-standards.md](./vuetify-standards.md) |
| Migrationsplan | [vuetify-migration-plan.md](./vuetify-migration-plan.md) |
