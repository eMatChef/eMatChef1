# Icon-Inventar: `Icon*` (SVG) vs. MDI (Vuetify)

**Stand:** Juni 2026 · Zwei parallele Systeme (Entscheid Phase 0).

| System | Verwendung | Import |
| ------ | ---------- | ------ |
| **MDI** (`@mdi/font`) | Vuetify-UI: `v-icon icon="mdi-…"`, Tabs, Buttons, `EEmptyState` | global via `vuetify.ts` |
| **`Icon*`-SVG** | Sidebar, Pack-Workflow, Suche im Header, Settings-Subnav | `import { IconX } from '@/components/icons'` |

**Nicht ersetzen:** `EmcLogoMark` (Marke).

**Dev UI Playground** (ehem. Icon-Vergleich) wurde in Phase 12 entfernt — diese Datei ist die Referenz. Optional später: Route `/{id}/verwaltung/icons` nur für Superadmin.

---

## Alle `Icon*`-Komponenten → MDI-Vorschlag

Stroke-Icons (Feather/Lucide-Stil, 24×24). MDI ist gefüllt/outlined — optisch **ähnlich**, nicht pixelgleich. Vor Umstellung Sidebar visuell prüfen.

| Komponente | MDI (empfohlen) | Alternativen | Hauptverwendung |
| ---------- | ----------------- | ------------ | ----------------- |
| `IconSearch` | `mdi-magnify` | — | `GlobalSearchInput`, `SearchFieldInput` |
| `IconClose` | `mdi-close` | — | `GlobalSearchInput` (Suche zuklappen) |
| `IconPlus` | `mdi-plus` | — | diverse Listen/Actions |
| `IconEdit` | `mdi-pencil` | `mdi-pencil-outline` | Tabellen, Detail |
| `IconTrash` | `mdi-delete` | `mdi-delete-outline` | Löschen |
| `IconEye` | `mdi-eye` | `mdi-eye-outline` | Anzeigen |
| `IconAlertTriangle` | `mdi-alert` | `mdi-alert-outline` | Warnungen |
| `IconArrowRight` | `mdi-arrow-right` | — | Pack-Workflow, Navigation |
| `IconArrowLeft` | `mdi-arrow-left` | — | Zurück |
| `IconArrowUp` | `mdi-arrow-up` | — | Sortierung / Move |
| `IconChevronRight` | `mdi-chevron-right` | — | Listen, Accordion |
| `IconChevronDown` | `mdi-chevron-down` | — | Sidebar «Meine Firma», Dropdowns |
| `IconCollapse` | `mdi-chevron-down` | *(Duplikat zu ChevronDown)* | Collapse-UI |
| `IconCross` | `mdi-close` | `mdi-close-circle` | Abbrechen |
| `IconDashboard` | `mdi-view-grid` | `mdi-view-dashboard` | Sidebar Dashboard, Settings «Mein Department» |
| `IconActivities` | `mdi-calendar` | `mdi-calendar-month` | Sidebar Aktivitäten, Settings |
| `IconMaterials` | `mdi-package-variant` | `mdi-cube-outline` | Sidebar Material, Settings Lager |
| `IconContacts` | `mdi-account-group` | `mdi-account-multiple` | Sidebar Kontakte, Settings Gruppen |
| `IconEmployees` | `mdi-account-group` | *(SVG identisch mit Contacts)* | Settings Benutzer, Join-Code |
| `IconTasks` | `mdi-clipboard-list` | `mdi-format-list-checks` | Sidebar Aufgaben, Pending |
| `IconJobs` | `mdi-briefcase` | `mdi-calendar-clock` | Verwaltung Jobs |
| `IconWorkshop` | `mdi-wrench` | `mdi-hammer-wrench` | Sidebar Werkstatt |
| `IconDisplay` | `mdi-monitor` | `mdi-television` | Sidebar Infoscreen, Display-Settings |
| `IconStatistics` | `mdi-chart-bar` | `mdi-poll` | Sidebar Statistik |
| `IconSettings` | `mdi-cog` | `mdi-cog-outline` | Sidebar Konfiguration, Settings-Subnav |
| `IconHelp` | `mdi-help-circle` | `mdi-help-circle-outline` | Sidebar Hilfe, Help-Subnav |
| `IconAccounting` | `mdi-cash` | `mdi-wallet-outline` | Sidebar Buchhaltung |
| `IconBell` | `mdi-bell` | `mdi-bell-outline` | Sidebar Nachrichtenzentrale |
| `IconPackage` | `mdi-package-variant` | `mdi-truck` | Supplier «Meine Firma», Lieferanten-Shop |

---

## MDI ohne `Icon*`-Pendant (häufig in migrierten Views)

Diese Icons nutzt Vuetify direkt — kein eigener `Icon*`-Wrapper:

| MDI | Typische Stelle |
| --- | ---------------- |
| `mdi-plus` | `EButton`, «Neu»-Actions |
| `mdi-menu` | Settings/Help Mobile-Subnav |
| `mdi-view-column` / `mdi-format-list-bulleted` | Werkstatt Kanban/Tabelle |
| `mdi-clock-outline` | Coming-soon / leer |
| `mdi-content-save` | AutoSave-Shell (Kommentar in `AutoSaveFieldShell`; aktuell **inline-SVG**, nicht MDI-Klasse) |
| `mdi-magnify` | Material-Listen-Tabs (teilweise) |

Vollständige MDI-Nutzung: `rg 'icon="mdi-' frontend/src` (≈ 50+ Dateien).

---

## Migrations-Empfehlung (Phase 12 optional / eigene Mini-Phase)

**Erledigt (2026-05–06):** Sidebar, Settings-/Help-Subnav, Verwaltung-Subnav, `GlobalSearchInput`, **Pack-Workflow** (`PackMoveControls` + Container-Cards), AutoSave-Shell (`mdi-content-save`, `mdi-refresh`, `mdi-close`).

1. ~~**Sidebar + Help/Settings-Subnav**~~ ✓
2. ~~**Pack-Workflow** (`IconArrow*` → `mdi-arrow-left/right/up`)~~ ✓ 2026-05-31
3. ~~**`GlobalSearchInput`**~~ ✓
4. **Duplikate bereinigen:** `IconCollapse` vs. `IconChevronDown`; `IconEmployees` vs. `IconContacts` (ein MDI, zwei Labels).
5. ~~**AutoSaveFieldShell**~~ ✓ Phase 3
6. ~~**Listen-/Tabellen-Actions**~~ ✓ `TableIconButton` + MDI (`mdi-pencil`, `mdi-delete-outline`, …); `SearchFieldInput` → MDI
7. **@mdi/js:** aktiv via `vuetify/iconsets/mdi-svg` (kein `@mdi/font` in `main.ts`)
8. **Icon-Duplikate:** `IconCollapse`→`IconChevronDown`, `IconEmployees`→`IconContacts`, `IconCross`→`IconClose` (Barrel-Aliase)

**Bundle:** Wechsel auf `@mdi/js` (tree-shaken) erst wenn Sidebar weitgehend MDI ist (Phase-0-Fussnote).

---

## Verweise

- [vuetify-standards.md](./vuetify-standards.md) — MDI global, `Icon*` parallel
- [vuetify-migration-plan.md](./vuetify-migration-plan.md) — Phase 0 Entscheid MDI, Phase 12 Playground entfernt
