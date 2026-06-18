# Grossanlass

Spezifikation für department-übergreifende Grossanlässe (PFF, Kantonslager): **Grossanlass-Department** als Projekt-Container — nicht nur ein Activity-Typ, sondern ein **eigenes Produkt** mit Dashboard, Planung, Material und Gast-Teilnehmern.

**Stand:** Juni 2026 · **Status:** Spezifikation (Ziel); Umsetzung offen

**Verwandt:** [status.md](../activities/status.md) · [material-pipeline.md](../activities/material-pipeline.md) · [pack-workflow-rules.md](../activities/pack-workflow-rules.md) · [js-material/README.md](../activities/js-material/README.md) · [newUI/SPEC §19.3](../activities/newUI/SPEC.md#193-transport--touren--department-fuhrpark) (Fuhrpark) · [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) · [ui/vuetify-standards.md](../ui/vuetify-standards.md) · [nachrichtenzentrale.md](../nachrichtenzentrale.md)

---

## Kurzüberblick

| Was | Entscheidung |
|-----|--------------|
| UI-Name | **Grossanlass** |
| `activity.type` | `'grossanlass'` — mehrere pro Dept mit **`grossanlass_role`** |
| Department | `is_grossanlass = true` — das Projekt («PFF 2027») |
| Anlegen | **Verwaltung → Abteilungen → Hinzufügen ▼** → «Grossanlass hinzufügen» (org/sub/sa) → **Dashboard** |
| **Kein** Activity-Wizard | Beim Dept-Create: auto **1× Activity `anlass`**; weitere Phasen bei Bedarf |
| Sidebar | **Dashboard**, **Planung**, **Beschaffung** (Shell ab Phase 2) — Material/Materialübersicht **später**; **kein** `/activities`, **kein** Pfadi-`/accounting` — [§3.4](./README.md#34-hauptmenü--sortierung--sichtbarkeit) |
| Ressorts | **`Group`** im Grossanlass-Dept |
| Struktur & Teilnehmer | **Dept-weit** (ein Zyklus pro Anlass) |
| Materialien (Menü) | Stammdaten: **Eigen \| Leihweise \| Fahrzeuge** |
| Materialübersicht | Zuweisung / Ausgabe **pro Ressort & Unterkategorie**; Lager vs. draussen |
| **Phase 1** | Erstell-Button, Wizard, App-Shell, Platzhalter-Dashboard, MW-Benachrichtigung — [MVP §Phase 1](./MVP.md#phase-1-grundgerüst) |
| **Erster Schnitt (MVP gesamt)** | Phase 1 + Ressorts + Planungsrunde Bedarf — [MVP.md](./MVP.md) |
| Planungsrunden (MVP) | `ressort_wuensche` — wie [Google Form PFF 27](https://docs.google.com/forms/d/e/1FAIpQLSfbk4Cvu7fLpnvW_Upu89BziYJlhd6rDF917xGasM1LEq3kGg/viewform); später `detailplanung` §9.2 |
| **Entwurf → Freigabe** | CM plant alles; **Erst bei Freigabe** Einladungen an Gast-Depts |
| Ressort-Hierarchie | **Teilbereiche / Bauprojekte** via `group.parent_id` (CM im MVP; RL ergänzt später) |
| Gast-Pfadi-Dept | Weiter **`/activities`** — sichtbar **erst nach Freigabe** |
| **Keine Doppelspur** | Bestehende Layout-, UI-, API- und Inbox-Patterns erweitern — [§20](#20-implementierungsprinzipien--keine-doppelspur) |

Siehe auch: [MVP.md](./MVP.md) — erster Implementierungsschnitt.

---

## Inhaltsverzeichnis

1. [Zielbild](#1-zielbild)
2. [Grossanlass-Department anlegen](#2-grossanlass-department-anlegen)
3. [Navigation & Dashboard](#3-navigation--dashboard)
   - [3.1 Sidebar (Grossanlass-Dept)](#31-sidebar-grossanlass-dept)
   - [3.4 Hauptmenü — Sortierung & Sichtbarkeit](#34-hauptmenü--sortierung--sichtbarkeit)
   - [3.5 Routen & leere Seiten (Shell-first)](#35-routen--leere-seiten-shell-first)
   - [3.6 Einstellungen (Grossanlass-Dept)](#36-einstellungen-grossanlass-dept)
   - [3.7 Beschaffung — Budget & Kosten](#37-beschaffung--budget--kosten)
4. [Ressorts = Gruppen](#4-ressorts--gruppen)
5. [Struktur & Teilnehmer (Dept-weit)](#5-struktur--teilnehmer-dept-weit)
6. [Activities & Rollen](#6-activities--rollen)
7. [Planung & Entwurfmodus](#7-planung--entwurfmodus)
8. [Teilnehmer, Einladungen & Inbox](#8-teilnehmer-einladungen--inbox)
9. [Planungsrunden](#9-planungsrunden)
10. [Materialien — Zentral erfassen](#10-materialien--zentral-erfassen)
11. [Materialübersicht & Ausgabe](#11-materialübersicht--ausgabe)
12. [J+S](#12-js)
13. [Lebenszyklus & Archivierung](#13-lebenszyklus--archivierung)
14. [Datenmodell](#14-datenmodell)
15. [API (Ziel)](#15-api-ziel)
16. [Implementierungsphasen](#16-implementierungsphasen)
17. [Berechtigungs-Matrix](#17-berechtigungs-matrix)
18. [Offene Fragen](#18-offene-fragen)
19. [Out of scope (v1)](#19-out-of-scope-v1)
20. [Implementierungsprinzipien — keine Doppelspur](#20-implementierungsprinzipien--keine-doppelspur)

---

## 1. Zielbild

Grossanlass ist **kein** camp/event und **kein** erweitertes event. Es ist ein **Department-Projekt** mit:

- Zentrallager + Leihgaben + Fuhrpark
- Ressorts (Groups), Unterlager, eingeladene Pfadi-Departments
- Planungsrunden **vor** dem Event
- Mehrere **Activities** (Anlass, Aufbau, Abbau, Sitzungen, …) für Material & Pack pro Zeitraum
- Immer sichtbar: **was im Lager ist vs. zugewiesen vs. draussen** — gesamt und pro Ressort

```
Grossanlass-Dept «PFF 2027»
├── Entwurf (draft)        CM: Ressorts, Mitglieder, Runden, Struktur — keine Einladungen
├── Freigabe (publish)     → Einladungen an Gast-Depts
├── Dashboard              Status, Runden, Lager/draussen
├── Materialien            Eigen | Leihweise | Fahrzeuge (Stammdaten)
├── Planung                Struktur, Ressorts, Runden, Activities
├── Materialübersicht      Ausgabe pro Ressort / Teilbereich
└── Activities             anlass · aufbau · abbau · vorevent · nach_event
```

**Pfadi-Gast:** unverändert unter eigenem `/activities` (ein Grossanlass-Eintrag).

---

## 2. Grossanlass-Department anlegen

### 2.1 Wer darf was (Kurz)

| Aktion | GL (org/sub/sa) | CM | GM (Pfadi) |
|--------|-----------------|-----|------------|
| Grossanlass-Dept erstellen | ✓ | — | — |
| MW ernennen | ✓ | — | — |
| Betrieb (Material, Planung, …) | ✓ | ✓ | — |
| Einladung annehmen | — | — | ✓ |

Details: [§17](#17-berechtigungs-matrix).

### 2.2 Entry Point

```
Verwaltung → Abteilungen
  [ Hinzufügen ▼ ]                    nur org / sub / sa
    ├ Abteilung hinzufügen            → bestehendes DepartmentModal
    └ Grossanlass hinzufügen          → Wizard §2.3
```

Implementierung: [`DepartmentsSettingsView.vue`](../../frontend/src/views/settings/DepartmentsSettingsView.vue) — heute ein Button «Hinzufügen» → nur Abteilung; Ziel: `v-menu` mit zwei Einträgen. «Grossanlass hinzufügen» nur bei Rolle org/sub/sa (nicht nur `departments.edit`).

Im **Pfadi-Dept:** kein Typ `grossanlass` im Aktivitäten-Wizard.

### 2.3 Erstell-Wizard («Grossanlass hinzufügen»)

| # | Feld | Pflicht | Speicherung |
|---|------|---------|-------------|
| 1 | **Name** des Grossanlasses «PFF 2027» | ja | `department.name` (= Anlass-Name) |
| 2 | **Anlassdatum von** | ja | §2.4 |
| 3 | **Anlassdatum bis** | nein | §2.4 — wenn leer: gleicher Tag wie «von» |
| 4 | **Organisation** | ja | `department.organisation_id` — org/sub: vorausgewählt/eingeschränkt |
| 5 | **Übergeordnetes Department** | nein | `department.parent_id` — wie normale Abteilung |
| 6 | **Chief-MW (User)** | empfohlen | `membership` `role: mw`, `is_primary: true` — Warnung wenn leer |

**Nicht im Wizard** (Planung nach Create): Ressorts, Planungsrunden, Teilnehmer, Ort, Notizen, Freigabe.

User-Auswahl Chief-MW: gleiches Pattern wie [`DepartmentModal.vue`](../../frontend/src/components/DepartmentModal.vue) (Org-User-Suche).

### 2.4 Anlegen — Backend & Datums-Speicherung

**Nach «Erstellen»:**

```
POST /api/departments/grossanlass
  {
    name, organisation_id, parent_id?,
    planned_event_start, planned_event_end?,
    chief_mw_user_id?
  }

→ department.is_grossanlass = true
→ department_grossanlass_config
     status: draft
     struktur_modus: offen
     planned_event_start, planned_event_end    ← Dept-Anker (Planungsreferenz)
→ activity grossanlass, grossanlass_role: anlass
     name = department.name
     usage_start = planned_event_start         ← Kopie, operativ für Pack/Material
     usage_end   = planned_event_end
     status: draft
→ config.main_activity_id = activity.id
→ membership MW (falls chief_mw_user_id gesetzt)
→ Kostenstellen-Bootstrap wie normales Dept
→ Redirect: /:deptId/dashboard
```

**Datums-Logik:**

| Feld | Zweck |
|------|--------|
| `department_grossanlass_config.planned_event_*` | **Dept-Infos** — bleibt als Planungs-Anker, auch wenn Activity später verfeinert wird (Aufbau/Abbau getrennt) |
| `activity.usage_start` / `usage_end` | **Operativ** — gleiche Werte beim Create; in Planung → Stammdaten verfeinerbar |

Validierung: `planned_event_end >= planned_event_start` (falls gesetzt).

**Kein** camp/event-Activity-Wizard, **kein** 4-Schritt-Setup.

### 2.5 Chief-MW — Benachrichtigung & Zugang

Wenn beim Create ein **Chief-MW** (`chief_mw_user_id`) gesetzt wird:

| Kanal | Inhalt | Pattern |
|-------|--------|---------|
| **E-Mail** | «Du wurdest Materialchef von ‹PFF 2027›» + Link | wie [`DepartmentController::addMember`](../../backend/src/Controller/DepartmentController.php) → `sendDepartmentMemberAddedEmail` |
| **Inbox** | Nachricht an User mit Deep-Link `/{departmentId}/dashboard` | neue Kategorie z. B. `grossanlass_mw_assigned` in `inbox_message` |

**Inbox-Payload (Vorschlag):**

```json
{
  "department_id": "…",
  "department_name": "PFF 2027",
  "role": "mw",
  "is_grossanlass": true,
  "dashboard_url": "/{departmentId}/dashboard",
  "planned_event_start": "…",
  "planned_event_end": "…"
}
```

**Dept-Wechsel:** MW sieht das neue Dept im **Profilmenü → Abteilung wechseln** ([`TopHeader.vue`](../../frontend/src/components/layout/TopHeader.vue) Dropdown), sobald `membership` existiert und `/api/users/{id}/memberships` `department.is_grossanlass` liefert.

Optional im Dropdown: Label «Grossanlass» neben Dept-Name zur Unterscheidung von Pfadi-Depts.

### 2.6 Flag-Verhalten

| | Pfadi-Dept | Grossanlass-Dept |
|--|------------|------------------|
| Sidebar «Aktivitäten» | ja | **nein** |
| Sidebar «Dashboard» | — | **ja (Default)** |
| Material | Lager | Zentrallager + Leihgaben + Fuhrpark |
| Groups | Truppen | Ressorts |

---

## 3. Navigation & Dashboard

### 3.0 Phase 1 — Grundgerüst (Platzhalter)

**Ziel:** Nach Create sieht Ersteller und Chief-MW das **bestehende zentrale Layout** — keine neue App-Hülle ([§20](#20-implementierungsprinzipien--keine-doppelspur)).

| Baustein | Verhalten Phase 1 |
|----------|-------------------|
| **Layout** | [`AppLayout.vue`](../../frontend/src/components/layout/AppLayout.vue) — `TopHeader` + `SidebarNavigation` + `router-view` |
| **Route** | `/:departmentId` bzw. `/:departmentId/dashboard` — wie Pfadi-Dept ([Router](../../frontend/src/router/index.ts)) |
| **Redirect nach Create** | org/sub/sa → `/{neueDeptId}/dashboard` |
| **Sidebar** | Grossanlass-Branch §3.1 / §3.4: **Dashboard** aktiv; **kein** «Aktivitäten»; **Planung** ab PR2a; Aufgaben/Nachrichten ab PR2a |
| **Dashboard-Inhalt** | **Minimal:** Name, geplantes Datum, Badge «Entwurf», Kurztext — **keine** Widgets (Runden, Lager, Ressorts) |
| **MW-Zugang** | E-Mail + Inbox §2.5; Dept-Wechsel im Profil-Dropdown |

```
┌─ TopHeader (bestehend) ────────────────────────────────┐
│ … · Profil ▼ → Abteilung wechseln · PFF 2027 · …      │
└────────────────────────────────────────────────────────┘
┌ Sidebar ─┐ ┌─ /{deptId}/dashboard (Phase 1) ───────────┐
│ Dashboard│ │  PFF 2027                      [Entwurf]   │
│ (kein    │ │  17.07.2027 – 19.07.2027                 │
│ Aktivit.)│ │  Willkommen — Planung folgt in Phase 2.  │
└──────────┘ └───────────────────────────────────────────┘
```

Details DoD: [MVP §Phase 1](./MVP.md#phase-1-grundgerüst).

### 3.1 Sidebar (Grossanlass-Dept)

Gleiche **App-Shell** ([`AppLayout`](../../frontend/src/components/layout/AppLayout.vue), [`SidebarNavigation`](../../frontend/src/components/layout/SidebarNavigation.vue)) — **conditional** Branch bei `department.is_grossanlass`. Details: [§3.4](#34-hauptmenü--sortierung--sichtbarkeit), [§20](#20-implementierungsprinzipien--keine-doppelspur).

**Kein** separates Grossanlass-Menü. **`/activities`:** ausgeblendet.

| Menü (Ziel) | Route | Phase sichtbar |
|-------------|-------|----------------|
| **Dashboard** | `/:deptId` | 1 ✓ |
| **Planung** | `/:deptId/planung` | 2+ |
| Materialien | `/:deptId/materials` | später (Menü erst mit Inhalt) |
| Materialübersicht | `/:deptId/material-uebersicht` | später |
| Aufgaben, Nachrichten, Einstellungen | wie Pfadi (Settings gefiltert §3.6) | 2+ |
| **Beschaffung** | `/:deptId/beschaffung` | 2+ Shell (PR2c); Inhalt ab Phase 5 §3.7 |

Planung hat **Tabs innen** (§3.5) — **nicht** jedes Tab ein Sidebar-Eintrag.

### 3.2 Dashboard — Widgets (ab Phase 2+)

Phase 1 nur Platzhalter §3.0. Vollständige Widgets:

| Widget | Inhalt |
|--------|--------|
| **Entwurf-Banner** | solange `status: draft` — «Grossanlass freigeben» + Checkliste §7.2 |
| **Anlass-Phase** | Entwurf · Planung · Aufbau · Event · Abbau · Abgeschlossen |
| **Planungsrunden** | Offen / geplant / geschlossen am Haupt-`anlass` |
| **Lager vs. draussen** | Gesamt: im Lager · zugewiesen · draussen (issued) |
| **Pro Ressort (Kurz)** | Verpflegung: 80/200 draußen · Technik: … |
| **Teilnehmer** | In Entwurf: geplant · Nach Freigabe: pending / accepted |
| **Nächste Activities** | Aufbau 15.7., Vorevent Sitzung 12.5., … |
| **Checkliste vor Start** | Runden geschlossen? Wünsche offen? |

Klick → Planung, Materialübersicht oder gefiltertes Ressort.

### 3.3 Dashboard — Layout (Wireframe)

```
┌────────────────────────────────────────────────────────────┐
│  PFF 2027                          Phase: Planung          │
├────────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐  │
│  │ Runden      │ │ Lager 1'240 │ │ Zusagen 8/12        │  │
│  │ 2 offen     │ │ Draußen 156 │ │ pending             │  │
│  └─────────────┘ └─────────────┘ └─────────────────────┘  │
│  Ressorts                                                    │
│  Verpflegung    Lager 400 │ zugewiesen 120 │ draußen 80    │
│  Technik        …                                          │
│  Nächste Termine                                             │
│  • Aufbau 15.7. · Vorevent Sitzung 12.5.                   │
└────────────────────────────────────────────────────────────┘
```

### 3.4 Hauptmenü — Sortierung & Sichtbarkeit

Implementierung: [`SidebarNavigation.vue`](../../frontend/src/components/layout/SidebarNavigation.vue) — `isGrossanlassDept` + dedizierte `showGrossanlass*`-Flags (nicht alles über `showStandardDeptSidebarLinks` ausblenden).

#### Sortierung (oben → unten)

| # | Label (i18n) | Icon (MDI) | Route | Anzeige |
|---|--------------|------------|-------|---------|
| 0 | Logo / Home | — | `/{deptId}` | immer |
| 1 | **Dashboard** | `mdi-view-grid` | `/{deptId}` | immer |
| 2 | **Planung** | `mdi-clipboard-text-outline` | `/{deptId}/planung` | ab Phase 2 |
| 3 | Materialien | `mdi-package-variant` | `/{deptId}/materials` | **später** — Menüpunkt erst wenn §10 live |
| 4 | Materialübersicht | `mdi-truck-delivery-outline` | `/{deptId}/material-uebersicht` | **später** — Menüpunkt erst wenn §11 live |
| 5 | **Beschaffung** | `mdi-cart-outline` | `/{deptId}/beschaffung` | ab Phase 2 — **Shell** §3.7; Inhalt Phase 5 |
| — | *Divider* | | | |
| 6 | **Aufgaben** | `mdi-clipboard-list` | `/{deptId}/tasks` | ab Phase 2 (Runden-Inbox, MW-Tasks) |
| 7 | **Nachrichten** | `mdi-bell-outline` | `/{deptId}/notifications` | ab Phase 2 |
| 8 | Kontakte | `mdi-account-group` | `/{deptId}/contacts` | optional (CM; RL vorerst aus) |
| 9 | **Einstellungen** | `mdi-cog-outline` | `/{deptId}/settings` | immer |
| 10 | Hilfe | `mdi-help-circle-outline` | `/{deptId}/help/overview` | optional wie Pfadi |

#### Bewusst ausgeblendet (Grossanlass-Dept)

Aktivitäten, **Pfadi-Buchhaltung** (`/accounting` — voller Tab-Stack), Werkstatt, Lieferanten-Shop, Statistik. Stattdessen **Beschaffung** §3.7 (eigenes Modul).

#### Darstellung

- Gleiche CSS-Klassen: `nav-item`, `nav-icon--mdi`, `nav-label` — wie Pfadi.
- Aktiv: `route.path.includes('/planung')` bzw. `isDeptSectionNavActive('planung')`.
- Dept-Wechsel: Tag «Grossanlass» in [`TopHeader`](../../frontend/src/components/layout/TopHeader.vue) (bereits Phase 1).

#### Phase 1 vs. Phase 2+ (Ist → Soll)

| Eintrag | Phase 1 (Ist) | Phase 2+ (Soll) |
|---------|---------------|-----------------|
| Dashboard | ✓ | ✓ |
| Planung | — | ✓ |
| Beschaffung | — | ✓ Shell (PR2c); Inhalt Phase 5 |
| Aufgaben / Nachrichten | aus | ✓ (Benachrichtigung Runden §9.0.1) |
| Einstellungen | ✓ (volle Pfadi-Liste) | ✓ gefiltert §3.6 |
| Material / Materialübersicht | — | Menü erst mit Feature |

### 3.5 Routen & leere Seiten (Shell-first)

**Prinzip:** Routen und **leere Shells** früh anlegen; Inhalt in PR2–PR4 nachziehen — gleiche Patterns wie Phase-1-Dashboard ([`DashboardView.vue`](../../frontend/src/views/DashboardView.vue) Grossanlass-Branch).

#### Routen (Router unter `/:departmentId`)

| Route | View (Vorschlag) | Phase | Inhalt initial |
|-------|------------------|-------|----------------|
| `` / `` / `dashboard` | `DashboardView` | 1 ✓ | Platzhalter §3.0 |
| **`/planung`** | `GrossanlassPlanungView` | **2a** | Tabs + `EEmptyState` |
| **`/beschaffung`** | `GrossanlassBeschaffungView` | **2c** | Tabs + `EEmptyState` §3.7 — **keine API** |
| `/planung/rounds/:roundId` | `GrossanlassRoundDetailView` | 4 | Wunschformular |
| `/material-uebersicht` | — | später | **keine Route** bis §11 |
| `/materials` | bestehende `MaterialsView` | später | Wiederverwendung unverändert |

#### Planung — Layout (Tabs, kein Sidebar-Spam)

Pattern wie [`SettingsView.vue`](../../frontend/src/views/SettingsView.vue): **Subnav/Tabs + `router-view`**, optional [`SettingsSubnavList`](../../frontend/src/components/settings/SettingsSubnavList.vue) oder `v-tabs`.

```
/:deptId/planung
├── ?tab=ressorts     → GrossanlassRessortsTab.vue      (PR2)
├── ?tab=rounds       → GrossanlassRoundsTab.vue        (PR3)
└── (später: stammdaten, freigabe, …)
```

| Tab | Label | Phase | Leer-Zustand |
|-----|-------|-------|--------------|
| **Ressorts & Mitglieder** | `grossanlass.planung.tabRessorts` | 2 | `EEmptyState` + «Ressort hinzufügen» |
| **Planungsrunden** | `grossanlass.planung.tabRounds` | 3 | `EEmptyState` + «Runde anlegen» |

Wünsche (PR4): **kein** eigener Sidebar/Tab — Detail unter `/planung/rounds/:id` oder Panel in Tab «Planungsrunden».

#### Wiederverwendbare Bausteine (Pflicht §20)

| Baustein | Pfad | Verwendung Grossanlass |
|----------|------|------------------------|
| **`PageShell`** | [`PageShell.vue`](../../frontend/src/components/layout/PageShell.vue) | Planung, Runden-Detail, Dashboard (wie Phase 1) |
| **`EEmptyState`** | [`EEmptyState.vue`](../../frontend/src/components/layout/EEmptyState.vue) | leere Ressort-/Runden-Listen |
| **`ELoadingState`** | [`ELoadingState.vue`](../../frontend/src/components/layout/ELoadingState.vue) | API-Laden |
| **`EButton`**, **`EDialog`**, **`ETextField`**, **`ESelect`** | `components/form/base/` | Formulare — [vuetify-standards.md](../ui/vuetify-standards.md) |
| **`section-card`** | CSS wie Dashboard | Karten-Inhalt |
| **Ressort-Baum** | Orientierung [`GroupsSettingsView.vue`](../../frontend/src/views/settings/GroupsSettingsView.vue) (`hierarchicalGroups`, Mitglieder-Tabelle) | Tab Ressorts — **API** über `/grossanlass/groups` |
| **User-Suche** | [`DepartmentModal.vue`](../../frontend/src/components/DepartmentModal.vue) / Wizard MW-Suche | Mitglieder zu Ressort |
| **`ActivityDateRangeField`** | [`ActivityDateRangeField.vue`](../../frontend/src/components/activities/wizard/ActivityDateRangeField.vue) | Runde `opens_at`/`closes_at`, Wunsch-Zeitraum |
| **Tasks / Inbox** | [`useDepartmentTasks`](../../frontend/src/composables/useDepartmentTasks.ts), [`NotificationsCenterView`](../../frontend/src/views/NotificationsCenterView.vue) | Runde geöffnet, MW assigned |
| **i18n** | `de.json` → `grossanlass.planung.*`, `sidebar.planung` | keine Hardcodes |

Weitere Übersicht: [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md).

#### PR-Schnitt Shell vs. Inhalt

| PR | Navigation & Shell | Inhalt |
|----|-------------------|--------|
| **PR2a** | Sidebar Planung; Route `/planung`; Tabs + `EEmptyState`; Aufgaben/Nachrichten wieder ein; **Settings gefiltert** §3.6 | — |
| **PR2b** | — | Ressort-Baum + API §4 |
| **PR2c** | Sidebar **Beschaffung**; Route `/beschaffung`; Tab-Shell + `EEmptyState` §3.7 | — (**kein** Backend) |
| **PR3** | Tab Planungsrunden Shell | Runden CRUD + open/close |
| **PR4** | Runden-Detail-Route | Wunschformular §9.1 |
| **PR5** | — | Beschaffung-Inhalt §3.7 (nach PR4) |

**Nicht** vorzeitig: Routes/Menü für Materialübersicht und Materialien — vermeidet tote Links.

### 3.6 Einstellungen (Grossanlass-Dept)

The **Pfadi-Settings-Subnav** ([`SettingsView.vue`](../../frontend/src/views/SettingsView.vue)) wird bei `department.is_grossanlass` **gefiltert** — ab Phase 2 (PR2a). Kein separates Settings-Layout.

#### Rollen (zwei Ebenen)

| Ebene | Rollen | Bedeutung |
|-------|--------|-----------|
| **Department** | **MW**, **DC**, **U** | Zugang zum Grossanlass-Projekt; CM = MW/DC |
| **Ressort** (`GroupMembership`) | **Leader** (RL), **Member** | Zugehörigkeit zu Ressort/Bauprojekt — **nicht** unter «Benutzer» |

**L1–L3** (Pfadi-Leiter) entfallen im Grossanlass-Dept. RL wird in **Planung → Ressorts** vergeben, nicht als Department-Rolle.

#### Sichtbare Menüpunkte (Settings-Subnav)

| Menü | Phase | Wer | Anmerkung |
|------|-------|-----|-----------|
| **Mein Department** | 2+ | alle | Name, Org, IDs, Gefahrenzone; ggf. Anlass-Metadaten |
| **Benutzer** | 2+ | MW/DC | MW/DC/U einladen — **keine** Pfadi-Rollen-Matrix |
| **Zeit/Ort** | 2+ | MW/DC | optional — Locale/Zeitzone wie Pfadi |
| **Gruppen** | — | — | **ausblenden** — Ressorts nur in **Planung** §4.4 |
| **Kategorien, Regale, Join-Code, Fixe Daten, Infoscreens, Aktivitäten, Werkstatt, Standorte, Rechnungsadresse, Öffentliche Material-Seite, Vorlagen, Import/Export, Add-ons** | — | — | **ausblenden** (Pfadi-Material/Camp) |

Später (Material-Phase §10): **Kategorien**, **Regale & Fächer**, **Standorte**, **Vorlagen** wieder für CM — Menüpunkt erst mit Feature.

#### Sicht pro Department-Rolle

| Rolle | Settings |
|-------|----------|
| **MW / DC** | Mein Department, Benutzer (+ später Material-Stammdaten) |
| **U** | nur **Mein Department** (read-only) — analog `USER_ALLOWED_MENU_IDS`, **ohne** Gruppen |
| **RL** | wie **U** — Ressort-Verwaltung in **Planung**, nicht Settings |

Implementierung: `visibleMenuItems` in `SettingsView.vue` — Branch `isGrossanlassDept` + `GROSSANLASS_SETTINGS_MENU_IDS`; i18n-Labels unverändert, ggf. später «Ressorts»-Hinweis in Planung statt «Gruppen».

### 3.7 Beschaffung — Budget & Kosten

**Kein** Pfadi-Modul «Buchhaltung» ([accounting.md](../accounting.md)) — kein Follow-up-Warteschlange, Abschreibung oder Aktivitäts-Verbrauch.

**Ein Modul** für Budget-Übersicht und Beschaffungs-Workflow: Wünsche bündeln → Offerten → Budget → bestellen → Kosten → **erhalten**. Die **Übersicht** ist die Budget- & Kosten-Home (Soll/Ist); die weiteren Tabs sind der Weg dorthin.

#### Phase 2 (PR2c) — nur Shell

| Lieferbar | Nicht in Phase 2 |
|-----------|------------------|
| Sidebar «Beschaffung», Route `/{deptId}/beschaffung` | API, Datenmodell, echte Budget-Zahlen |
| View `GrossanlassBeschaffungView` + [`PageShell`](../../frontend/src/components/layout/PageShell.vue) | Wunsch-Aggregation, Offerten, Bestellungen |
| Tabs mit [`EEmptyState`](../../frontend/src/components/layout/EEmptyState.vue) pro Tab | CSV-Export, Material-Batch bei «erhalten» |

```
/:deptId/beschaffung
├── ?tab=uebersicht    → EEmptyState «Budget-Übersicht folgt nach Planungsrunden»
├── ?tab=bedarf        → EEmptyState
├── ?tab=offerten      → EEmptyState
├── ?tab=bestellungen  → EEmptyState
└── ?tab=erhalten      → EEmptyState
```

i18n: `sidebar.beschaffung`, `grossanlass.beschaffung.tab*`.

#### Phase 5 (PR5) — Inhalt (nach PR4 Wünsche)

Abhängigkeit: **PR4** (`activity_grossanlass_wish_line`) → dann Bedarf aus Wünschen aggregieren.

| Tab | Inhalt |
|-----|--------|
| **Übersicht** | Soll/Ist gesamt + pro Ressort; offene Offerten; bestellt nicht erhalten |
| **Bedarf** | CM bündelt Wunsch-Zeilen zu Beschaffungspositionen (merge/split) |
| **Offerten** | 1..n Angebote pro Position (Lieferant, CHF, Notiz/PDF-Ref); eine «gewählt» → Soll |
| **Bestellungen** | Status «bestellt», Betrag, Bestelldatum, Rechnungsreferenz |
| **Erhalten** | «Vollständig erhalten» / Teillieferung; später Anbindung Zentrallager §10 |

**Status** pro Position (Kanban): `bedarf` → `offerte_eingeholt` → `budgetiert` → `bestellt` → `teilweise_erhalten` → `erhalten`.

#### Berechtigung (Ziel)

| Aktion | MW/DC | RL | U |
|--------|:-----:|:--:|:-:|
| Beschaffung lesen (Übersicht) | ✓ | optional später eigenes Ressort | — |
| Bedarf, Offerten, Budget, Bestellen | ✓ | — | — |
| Erhalten markieren | ✓ | — | — |

Phase 2 Shell: Route erreichbar für MW/DC; Tabs zeigen nur Empty State.

#### Datenmodell (Ziel, §14.4 — Phase 5+)

```
activity_grossanlass_procurement_line   — aus wish_line(s), group_id, qty, status
activity_grossanlass_quote              — procurement_line_id, supplier, amount_chf, selected
activity_grossanlass_procurement_order  — bestellt_am, cost_chf, order_ref, received_at?
```

Optional später: `material_batch_id` bei «erhalten» → Zentrallager §10.

#### API (Ziel — **nicht** Phase 2)

```
GET/POST/PUT/DELETE  …/grossanlass/beschaffung/lines
GET/POST/PUT/DELETE  …/grossanlass/beschaffung/lines/{id}/quotes
POST                 …/beschaffung/lines/{id}/order
POST                 …/beschaffung/lines/{id}/received
GET                  …/grossanlass/beschaffung/overview
```

Intern §20: keine parallele Ledger-UI — Export ans Vereins-Finanztool optional über bestehende Accounting-Entitäten **später**, nicht MVP.

---

## 4. Ressorts = Gruppen

Ressorts = **`Group`** im Grossanlass-Department + `GroupMembership`.

| Ebene | Department | Bedeutung |
|-------|------------|-----------|
| **Ressort** | Grossanlass-Dept | Organisatoren, Material-Wünsche, Ausgabe |
| **Teilbereich / Bauprojekt** | Grossanlass-Dept (`group.parent_id`) | Untergliederung z. B. Bau → Bühne, Wasserstelle |
| **Teilnehmer-Gruppe** | Pfadi-Dept (Gast) | Lokale Stufe bei Annahme (`guest_group_id`) |

### 4.1 Hierarchie — Ressort → Teilbereich

Keine extra Tabelle — **`Group.parent_id`**:

```
Group «Bau»                         ← Ressort (parent_id: null)
  Group «Bühne»                     ← Teilbereich / Bauprojekt
  Group «Wasserstelle»
  Group «Sanitär»

Group «Verpflegung»
  Group «Küche Nord»
  Group «Küche Süd»
```

Optional `group.kind`: `ressort` | `teilbereich` (UI-Label «Bauprojekt» bei Bau-Ressorts).

**Tiefe:** max. **10** Ebenen (`parent_id`-Kette) — Validierung beim Anlegen/Verschieben.

**Material & Ausgabe:** Wünsche, Zuweisung und Pack können an **Ressort oder Teilbereich** gebunden werden — feinere Planung und gezielte Ausgabe («Ausgabe Bühne»).

### 4.2 Wer pflegt die Hierarchie?

| Aktion | CM/MW | Mitglied im Ressort (RL/User) |
|--------|-------|-------------------------------|
| **Ressort** (Wurzel) anlegen | ✓ | — |
| **Teilbereich / Bauprojekt** anlegen | ✓ | ✓ — **immer**, auch im Entwurf; **nicht** an Gast-Freigabe gebunden |
| **Mitglieder** zuweisen | ✓ | ✓ im **eigenen** Ressort-Baum |
| **Löschen** | ✓ | — |

**Teilbereiche / Bauprojekte:** jederzeit für berechtigte User — kein Warten auf `published`.

`group.allow_rl_structure` (Default `true`): Mitglieder dürfen Kinder-Groups unter ihrem Knoten anlegen.

### 4.3 Löschen

| Regel | |
|-------|---|
| Erlaubt | Nur wenn **keine `GroupMembership`** im **gesamten Subtree** (Knoten + alle Kinder bis Tiefe 10) |
| Leere Unter-Bauprojekte | **Rekursiv mitlöschen**, wenn nirgends Members |
| Blockiert | Wenn noch **`activity_grossanlass_wish_line.group_id`** auf Knoten im Subtree verweist (Wünsche zuerst löschen/umhängen) |

### 4.4 UI & API (Phase 2)

**Einstieg:** `/:deptId/planung` → Tab **«Ressorts & Mitglieder»** (nicht separates Layout).

Intern weiterhin `Group` + `GroupMembership` — **API-Fassade** unter Grossanlass (keine parallele Gruppen-Logik §20):

```
GET/POST/PUT/DELETE  /api/departments/{id}/grossanlass/groups
POST/DELETE          …/groups/{groupId}/members
```

Optional Link von **Einstellungen → Gruppen** für Grossanlass-Dept — Haupt-UX bleibt Planung-Tab.

---

## 5. Struktur & Teilnehmer (Dept-weit)

Gilt für **den Anlass-Zyklus** am Department — nicht pro Aufbau-Activity.

`department_grossanlass_config.struktur_modus`: `offen` | `verschachtelt` | `parallel`

### Verschachtelt

```
Group «Verpflegung» → Unterlager «Küche Nord» → Pfadi Winterthur
```

### Parallel

```
Ressorts (Groups)     | Unterlager (frei)      | Teilnehmer (flach)
Verpflegung — Max     | Region Ost — WT, FF    | Pfadi XY pending
```

- Ein Pfadi-Dept **max. einmal** pro Zyklus
- In **Entwurf:** Participant `status: planned` — **keine** Inbox (§7, §8)
- Struktur in **Planung** bearbeiten (CM/GL)

---

## 6. Activities & Rollen

Jede **Activity** ist ein **buchbarer Zeitraum** — Material & Pack wie bei camp/event, aber `type = grossanlass`.

### 6.1 Rollen (`grossanlass_role`)

| Rolle | Bedeutung | Beispiel |
|-------|-----------|----------|
| **`anlass`** | Haupt-Event | PFF 17.–19.7. — **auto beim Dept-Create** |
| **`aufbau`** | Aufbauphase | Zeltaufbau 15.–16.7. |
| **`abbau`** | Abbau | 20.–21.7. |
| **`vorevent`** | Vor dem Event | Org-Sitzung, Probe |
| **`nach_event`** | Nach dem Event | Wrap-up, Rücktransport |

Alle: `activity.type = 'grossanlass'`, `activity.department_id` = Grossanlass-Dept.

### 6.2 Verknüpfung

```
activity.parent_activity_id   → Haupt-anlass (nullable nur für anlass selbst)
```

Dashboard und Materialübersicht aggregieren **alle** Activities; Filter nach Rolle möglich.

### 6.3 Anlegen weiterer Activities

**Planung** oder Dashboard: «+ Aufbau», «+ Vorevent», …

Kurzformular ( **kein** camp/event-Wizard):

- Rolle, Name, Zeitraum (`usage_start` / `usage_end`)
- `POST /api/activities` `{ type: grossanlass, grossanlass_role, parent_activity_id?, name, … }`

Materialbuchung & Pack an **dieser** Activity; Bestand kommt aus **Zentrallager** / Ressort-Zuteilung.

### 6.4 Gast-Sicht

Pfadi-Dept sieht **Haupt-`anlass`** in `/activities`. Aufbau/Abbau/Vorevents: Chief-intern (v1); Gäste nur bei explizitem Bedarf (out of scope v1).

---

## 7. Planung & Entwurfmodus

Route: `/:deptId/planung`

Jeder Anlass-Zyklus startet in **`department_grossanlass_config.status = draft`** (Entwurf). CM (Grossanlass-MW) bereitet **alles** vor; Gast-Depts und RL (operativ) sehen **nichts** bis **Freigabe**.

### 7.1 Entwurfmodus — was CM vorbereitet

| Bereich | Im Entwurf (CM/GL) |
|---------|---------------------|
| **Stammdaten** | Anlass-Zeitraum verfeinern (`activity.usage_*`; Anker: `planned_event_*` §2.4), Ort, Notizen |
| **Ressorts** | Groups anlegen, RL-Mitglieder zuweisen |
| **Teilbereiche / Bauprojekte** | CM im Entwurf (`parent_id`) §4.2 |
| **Teilnehmer-Depts** | in Struktur eintragen → `planned`, **keine Einladung** (nach MVP) |
| **Unterlager** | optional |
| **Planungsrunden** | anlegen + **öffnen** (MVP: Chief-intern, §9.1) |
| **Activities** | Aufbau, Vorevent, … anlegen (optional im Entwurf) |
| **Material Stammdaten** | Eigen / Leihweise / Fahrzeuge §10 (nach MVP) |

**Gesperrt im Entwurf (volle Spec):** Inbox an Gäste, Gast-Sicht in Pfadi-`/activities`.  
**MVP Chief-intern:** Planungsrunde `open` + Wünsche §9.1 **ohne** Gast-Freigabe.

### 7.2 Freigabe («Grossanlass freigeben»)

Button in **Dashboard** und **Planung** — nur CM/GL.

**Mindest-Checks (v1):**

- Haupt-`anlass`: Zeitraum gesetzt
- mind. 1 Teilnehmer-Dept in Struktur (empfohlen; konfigurierbar)

**Aktion `POST …/grossanlass/publish`:**

```
department_grossanlass_config.status = published
published_at, published_by_user_id

Alle participant: planned → pending
→ syncGrossanlassParticipantInvites()  → Inbox an Gast-MW/DC

Planungsrunden: bleiben scheduled (CM öffnet manuell oder per Auto)

RL: Zugriff auf eigenes Ressort — Teilbereiche anlegen §4.2
```

**UI nach Freigabe:** Dashboard ohne Entwurf-Banner; Widget «Zusagen pending».

**Rücknahme Freigabe:** v1 **nicht** — nur Dept-Archivierung oder manuelles Entfernen einzelner Participants.

### 7.3 Planung — Bereiche (Tabs)

| Tab | Inhalt |
|-----|--------|
| **Stammdaten** | §7.1 |
| **Struktur** | Ressorts, Teilbereiche, Unterlager, Teilnehmer-Depts §5 |
| **Ressorts & Mitglieder** | §4 — Baum (max. 10), Mitglieder, Lösch-Regeln |
| **Planungsrunden** | §9 — anlegen, open/close, Auto-Schedule, Wünsche |
| **Activities** | §6 — Phasen / Vorevents |
| **Freigabe** | Checkliste + Button (nur wenn `draft`) |

Kein separater `/setup`-Stepper.

---

## 8. Teilnehmer, Einladungen & Inbox

### 8.1 Flow (Entwurf → Freigabe → Annahme)

```
Entwurf:
  CM trägt Pfadi-Dept in Struktur ein
  → activity_grossanlass_participant (status: planned)
  → KEINE Inbox, Gast sieht nichts

Freigabe (§7.2):
  → alle planned → pending
  → syncGrossanlassParticipantInvites()
  → Inbox bei Gast-MW/DC

GM: annehmen → accepted + guest_group_id (lokale Gruppe)
GM: ablehnen → rejected
```

**Wichtig:** Einladung **nicht** beim Struktur-Speichern — **nur** bei Freigabe (gebündelt).

### 8.2 Inbox

**Pattern:** bestehend `CATEGORY_ACTIVITY_DEPT_INVITE` / `activity_department_invite` — `activity_type: grossanlass` im Payload.

**Empfänger:** `RECIPIENT_DEPARTMENT_MW` im Gast-Dept.

**Payload (zusätzlich):**

```json
{
  "activity_type": "grossanlass",
  "participant_id": "…",
  "unterlager_name": "Region Ost",
  "ressort_name": "Verpflegung",
  "source_department_name": "PFF 2027"
}
```

**UI Zeile:**

- Betreff: «PFF 2027 — Einladung Grossanlass»
- Preview: Chief-Dept · Ressort/Unterlager · Zeitraum

**Aktionen:** Ablehnen | Annehmen → Detail (Gast) + **Gruppe wählen** (wie camp/event).

**Chief bei Accept:** Hub-Widget / optional `invite_accepted` Inbox. **Reject:** nur Status in Planung (v1 kein Inbox).

**Sync:** `syncGrossanlassParticipantInvites()` bei **`publish`** und wenn nach Freigabe neue Depts hinzugefügt werden (`planned` → sofort `pending` + Inbox).

**Purge:** bei Activity `completed` / `cancelled` — wie heute.

### 8.3 i18n (Vorschlag)

- `grossanlass.invite.inboxSubject`
- `grossanlass.invite.inboxPreview`
- `grossanlass.invite.acceptedChief`

---

## 9. Planungsrunden

Am **Haupt-`anlass`** (`grossanlass_role: anlass`). **Mehrere Runden** gleichzeitig möglich (auch mehrere `open`).

### 9.0 Rundentypen — Übersicht

| `round_type` | Phase | Art | Beschreibung |
|--------------|-------|-----|--------------|
| **`ressort_wuensche`** | **MVP** | Eingabe | Grobe Bedarfserfassung pro Ressort/Bauprojekt §9.1 |
| **`detailplanung`** | später | Eingabe | Feinplanung am Bauprojekt — Bezug `group_id`, engere Felder §9.2 |
| `js_vorgabe` | später | Eingabe (Gast) | Pfadi-Depts: J+S |
| `eigenmaterial` | später | Eingabe (Gast) | eigenes Lager |
| `grossanlass_central` | später | Steuerung | CM: Zuweisung Zentrallager → Ressorts |
| `freigabe` | später | Steuerung | Freigabe vor Aufbau/Event |

**MVP:** nur **`ressort_wuensche`**. Orientierung am bisherigen [Google Form «Infrastruktur Planung» (PFF 27)](https://docs.google.com/forms/d/e/1FAIpQLSfbk4Cvu7fLpnvW_Upu89BziYJlhd6rDF917xGasM1LEq3kGg/viewform).

### 9.0.1 Runde anlegen (CM/MW)

| Feld | Pflicht | Beschreibung |
|------|---------|--------------|
| **Name** | ja | z. B. «Infrastruktur Bedarf 2026» |
| **`round_type`** | ja | MVP: fest `ressort_wuensche` |
| **`opens_at`** | nein | Start (manuell oder Auto) |
| **`closes_at`** | nein | Ende (manuell oder Auto) |
| **`use_auto_schedule`** | nein | MW wählt Auto — System öffnet/schliesst bei `opens_at`/`closes_at` |

**Status:** `scheduled` → **`open`** (manuell oder Auto) → **`closed`**.

**Workflow:**

1. CM/MW legt Runde an (`scheduled`)
2. **Öffnen** manuell oder per Auto bei `opens_at`
3. Bei **`open`:** Inbox (+ optional E-Mail) an **Mitglieder** der betroffenen Ressorts: «Runde ‹Name› offen — Bedarf einreichen»
4. Wünsche nur solange **`open`**
5. **Schliessen** manuell oder Auto bei `closes_at`

**Regeln:**

- **Mehrere** Runden parallel `open` erlaubt
- Runden **dürfen sich überschneiden** — erlaubte Aktionen = **Vereinigung** offener Runden
- Chief-intern — **ohne** Gast-Freigabe (`publish`)

### 9.1 Bedarfserfassung (`ressort_wuensche`) — MVP-Kern

In einer **offenen** Runde trägt RL/User (oder CM) **Wunsch-Zeilen** ein.

**Mapping Google Form → eMatChef:**

| Google Form (PFF 27) | Feld `wish_line` |
|----------------------|------------------|
| Was brauchst du? (Material, Maschinen, Spezielles) | `label` + `wish_kind` |
| Wie viel brauchst du davon? | `quantity` |
| Wo brauchst du es? Ort | **`location`** |
| Zeitrahmen / Wann benötigst du das Material | `valid_from` / `valid_to` (+ optional `timeframe_notes`) |
| Welches Ressort? oder Bereich? | `group_id` (Dropdown Baum) |

| Feld | Typ | Pflicht |
|------|-----|---------|
| **Ressort / Bauprojekt** | `group_id` | ja |
| **Art** | `material` \| `fahrzeug` \| `beides` | ja |
| **Bezeichnung** | Freitext (`label`) | ja |
| **Anzahl** | Zahl | ja |
| **Ort** | `location` | ja |
| **Zeitraum** | `valid_from` / `valid_to` | ja |
| **Notizen** | Text | nein |
| **Status** | `requested` (MVP) | — |

**Bearbeiten / Löschen:** nur **Autor** (`created_by_user_id`), nur solange Runde **`open`**. CM sieht alle, ändert fremde Zeilen im MVP **nicht**.

**MVP:** Fahrzeug = Bedarf melden (Freitext) — **kein** Fuhrpark-Stammdaten (§10.3). Material = Freitext — **kein** Katalog-Zwang.

**Berechtigung:** User nur Zeilen im **eigenen Ressort-Baum**; CM/MW alle Ressorts.

**UI:** Planung → Tab «Planungsrunden» → Runde `open` → Formular (wie Google Form) + Liste; CM: aggregierte Sicht pro Ressort.

Siehe §14.4 (`activity_grossanlass_wish_line`).

### 9.2 Detailplanung (`detailplanung`) — nach MVP

Zweite **Eingaberunde** — feinere Planung am **Bauprojekt** (Blatt-Knoten im Baum), nicht Ersatz für `ressort_wuensche`:

```
Runde 1: ressort_wuensche   «Was brauchen wir grob?»
Runde 2: detailplanung      «Wie genau am Bauprojekt Bühne?»
```

Gleiche `wish_line`-Struktur möglich; UI kann strengere Pflichtfelder haben. Optional später: `target_group_id` auf der Runde (Scope «nur unter Bau»).

---

## 10. Materialien — Zentral erfassen

Menü **Materialien** = **Stammdaten**, activity-unabhängig.

### 10.1 Tabs

| Tab | Modell | Beschreibung |
|-----|--------|--------------|
| **Eigen** | `MaterialItem` | Gehört Grossanlass-Dept (Zentrallager) |
| **Leihweise** | `material_usage_grant` | Nutzung **von–bis**, fremder Owner |
| **Fahrzeuge** | `department_vehicle` | Fuhrpark — siehe [newUI §19.3](../activities/newUI/SPEC.md#193-transport--touren--department-fuhrpark) |

### 10.2 Leihweise

```
material_usage_grant
  department_id           Grossanlass-Dept (Nutzer)
  source_type             department | organisation | external
  source_department_id    nullable
  source_label            «Pfadi Winterthur», «Garage XY»
  valid_from, valid_to    Pflicht
  material_item_id        nullable — Verknüpfung wenn im System
  name, quantity          ad hoc
```

Verfügbar nur im Nutzungsfenster. Nicht Eigentum — erscheint in Verfügbarkeit wie Bestand.

### 10.3 Fahrzeuge

Stammdaten im Grossanlass-Dept; Leih-Fahrzeuge von Partner-Dept mit `lending_department_id` + Zeitraum (analog Leihweise).

---

## 11. Materialübersicht & Ausgabe

**Operativ** — Verteilung, nicht Stammdaten. Route: `/:deptId/material-uebersicht`.

### 11.1 Status pro Position

| Status | Bedeutung |
|--------|-----------|
| **Im Lager** | Zentrallager, unzugewiesen |
| **Zugewiesen** | Ressort / Unterkategorie, noch nicht physisch raus |
| **Draussen** | Ausgegeben / im Einsatz (Pack/Move) |

### 11.2 Ansichten

```
Filter: [ Gesamt ] [ Ressort ▼ ] [ Teilbereich ▼ ] [ Activity ▼ ]

Ressort «Bau»
  Teilbereich «Bühne»
    Gerüst × 4     Lager 0 │ zugewiesen 4 │ draußen 0
  Teilbereich «Wasserstelle»
    …
```

- **CM:** alle Ressorts + Zuweisung aus Lager
- **RL:** nur eigenes Ressort (§17)
- Filter **Activity:** Material für Aufbau vs. Anlass vs. Vorevent
- Partner-Dept-Material: optional aggregierte Spalte (Detail im Gast-Dept)

### 11.3 Workflow

1. Nach Freigabe: CM öffnet Runde `ressort_wuensche`
2. RL wünscht pro Ressort/Teilbereich (Material + Fahrzeuge)
3. Runde `grossanlass_central`: CM weist zu → **Zugewiesen**
4. Pack/Ausgabe an Activity, optional pro Teilbereich → **Draussen**

Daten: `activity_grossanlass_ressort_line` (+ Progress) Phase 2+; v1 minimal: Zuweisung + Pack-Status.

---

## 12. J+S

Siehe [js-material/README.md](../activities/js-material/README.md). Runde `js_vorgabe` am Haupt-`anlass`:

```
je Pfadi-Dept: activity_grossanlass_js_submission
→ CM aggregiert → activity_js_order → Zentrallager
```

Cross-Link in js-material-Spec ergänzen bei Implementierung.

---

## 13. Lebenszyklus & Archivierung

| Situation | Verhalten |
|-----------|-----------|
| Haupt-`anlass` completed | Dashboard «Abgeschlossen»; Hinweis Dept archivieren |
| **Archivieren** | `department.archived_at`, read-only |
| **Weiterführen** | Neuer Anlass-Zyklus: neuer Haupt-`anlass`, Struktur/Groups wiederverwendbar |

Activities (Aufbau, …) schliessen unabhängig; Dept-Abschluss wenn Haupt-`anlass` completed.

---

## 14. Datenmodell

### 14.1 Department

```
department.is_grossanlass
department.archived_at
```

Membership-API / Session: `department.is_grossanlass` in User-Memberships für Sidebar-Switch und Profil-Dept-Wechsel §2.5.

Inbox (neu): `InboxMessage::CATEGORY_GROSSANLASS_MW_ASSIGNED` (Chief-MW bei Create §2.5); `CATEGORY_GROSSANLASS_ROUND_OPENED` (Runde geöffnet §9.0.1).

### 14.2 Department-Konfiguration (Struktur Dept-weit)

```
department_grossanlass_config
  department_id             FK UNIQUE
  main_activity_id          FK → activity (anlass)
  status                    draft | published
  published_at              nullable
  published_by_user_id      nullable
  struktur_modus            offen | verschachtelt | parallel
  planned_event_start       datetime — Anlassdatum «von» (Wizard + Dept-Anker)
  planned_event_end         datetime nullable — «bis»; Default = Starttag
```

Group-Erweiterung (Ressorts):

```
group.parent_id             nullable → Teilbereich unter Ressort
group.kind                  ressort | teilbereich  (optional)
group.allow_rl_structure    boolean DEFAULT true
```

### 14.3 Activity

```
activity.type = 'grossanlass'
activity.parent_activity_id   nullable → Haupt-anlass
activity_grossanlass_config
  activity_id               FK UNIQUE
  grossanlass_role          anlass | aufbau | abbau | vorevent | nach_event
```

### 14.4 Struktur, Teilnehmer, Runden

```
activity_grossanlass_unterlager
activity_grossanlass_unterlager_department
activity_grossanlass_participant
  status                    planned | pending | accepted | rejected
  group_id                  nullable — Ressort/Teilbereich-Bezug
  unterlager_id, guest_group_id, …
activity_grossanlass_round
  id
  activity_id              FK → Haupt-anlass
  name                     string — Pflicht
  round_type               ressort_wuensche | detailplanung | …
  status                   scheduled | open | closed
  opens_at                 nullable datetime
  closes_at                nullable datetime
  use_auto_schedule        boolean DEFAULT false
  opened_at                nullable — Audit
  closed_at                nullable
  created_by_user_id       FK
  created_at, updated_at
activity_grossanlass_wish_line
  id
  round_id                 FK
  group_id                 FK → Ressort oder Bauprojekt
  wish_kind                material | fahrzeug | beides
  label                    Freitext-Bezeichnung
  quantity                 int
  location                 string — Ort («Wo brauchst du es?»)
  valid_from, valid_to
  timeframe_notes          nullable — Freitext-Zeitraum ergänzend
  notes                    nullable
  status                   requested | assigned | …
  created_by_user_id       FK — Autor (edit/delete §9.1)
  created_at, updated_at
activity_grossanlass_procurement_line   — Phase 5 §3.7
  wish_line_ids[], group_id, label, quantity, status
activity_grossanlass_quote
  procurement_line_id, supplier, amount_chf, selected, notes
activity_grossanlass_procurement_order
  procurement_line_id, ordered_at, cost_chf, order_ref, received_at?
activity_grossanlass_ressort_line       — Zuweisung Zentrallager (später)
activity_grossanlass_js_submission
```

Ressorts: **`Group`** + `GroupMembership` — siehe §4, §14.2.

### 14.5 Material

```
material_item                           — Eigen (bestehend)
material_usage_grant                    — Leihweise (neu)
department_vehicle                      — Fahrzeuge (newUI §19.3)
```

---

## 15. API (Ziel)

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| POST | `/api/departments/grossanlass` | Dept + auto `anlass` §2.3–2.4 |
| POST | **`/api/departments/{id}/grossanlass/publish`** | **Freigabe** §7.2 (nach MVP) |
| GET | `/api/departments/{id}/grossanlass/dashboard` | Widget-Daten §3.2 |
| GET/POST/PUT/DELETE | `/api/departments/{id}/grossanlass/groups` | Ressort-Baum §4.4 |
| POST/DELETE | `…/grossanlass/groups/{groupId}/members` | Mitglieder §4 |
| GET/PUT | `/api/departments/{id}/grossanlass/planung/struktur` | Struktur §5 (nach MVP) |
| GET/POST/PUT | `/api/departments/{id}/grossanlass/planung/rounds` | Runden §9 |
| POST | `…/planung/rounds/{roundId}/open` | Runde öffnen |
| POST | `…/planung/rounds/{roundId}/close` | Runde schliessen |
| GET/POST/PUT/DELETE | `…/planung/rounds/{roundId}/wishes` | Wunsch-Zeilen §9.1 |
| GET | `…/grossanlass/beschaffung/overview` | Übersicht Soll/Ist §3.7 (**Phase 5**) |
| CRUD | `…/grossanlass/beschaffung/lines` (+ quotes, order, received) | Beschaffung §3.7 (**Phase 5**) |
| POST | `/api/activities` | `grossanlass` + `grossanlass_role` §6 |
| GET | `/api/departments/{id}/grossanlass/material-uebersicht` | §11 |
| CRUD | `/api/departments/{id}/material-usage-grants` | Leihweise §10 |
| POST | `…/grossanlass/participants/{id}/respond` | accept/reject §8 |
| PATCH | `…/grossanlass/participants/{id}` | `guest_group_id` |

Berechtigungen: [§17](#17-berechtigungs-matrix).

---

## 16. Implementierungsphasen

| Phase | Inhalt | DoD |
|-------|--------|-----|
| **0** | Dokumentation | reviewed |
| **1** | **Grundgerüst** §3.0 | [MVP Phase 1](./MVP.md#phase-1-grundgerüst) |
| **2** | Navigation (Planung, **Beschaffung-Shell**), **Settings gefiltert** §3.6, Ressorts — Planung-Tab, API groups §4 | [MVP Phase 2](./MVP.md#phase-24-nach-phase-1) |
| **3** | Planungsrunden — Name, Auto-Schedule, open/close, Benachrichtigung §9 | PR3 |
| **4** | Wunschformular `ressort_wuensche` §9.1 (Google Form) | PR4 |
| **5** | **Beschaffung** — Bedarf, Offerten, Budget, Bestellung, Erhalten §3.7 | PR5 |
| **6** | `detailplanung`-Runden §9.2 | nach MVP |
| **7** | **`publish`** + Gast-Inbox + accept | §7.2, §8 |
| **8** | Materialübersicht v1 | §11 |
| **9** | Material leiweise, Fahrzeuge | §10 |
| **10** | Activities Phasen, J+S, Pack | §6, §12 |

---

## 17. Berechtigungs-Matrix

| Kürzel | Bedeutung |
|--------|-----------|
| **GL** | org / sub / sa |
| **CM** | MW/DC im Grossanlass-Dept |
| **RL** | Ressort-Lead (GroupMembership) |
| **GM** | MW/DC Gast-Pfadi-Dept |

### Department & Navigation

| Aktion | GL | CM | RL | GM |
|--------|:--:|:--:|:--:|:--:|
| Grossanlass-Dept anlegen | ✓ | — | — | — |
| Dashboard / Planung / Beschaffung / Materialübersicht | ✓ | ✓ | ✓* | — |
| Materialien Stammdaten | ✓ | ✓ | — | — |
| Struktur & Teilnehmer (Entwurf) | ✓ | ✓ | — | — |
| **Grossanlass freigeben** | ✓ | ✓ | — | — |
| Ressort / Bauprojekt anlegen | ✓ | ✓ | ✓* | — |
| Ressort löschen (Subtree-Regel §4.3) | ✓ | ✓ | — | — |
| Planungsrunde anlegen / open / close | ✓ | ✓ | — | — |
| Planungsrunde **Wünsche einreichen** | — | ✓ | ✓* | — |
| Wunsch **bearbeiten/löschen** (eigene, Runde open) | — | ✓ | ✓* | — |
| Activity (Phase) anlegen | ✓ | ✓ | — | — |
| Materialübersicht gesamt | ✓ | ✓ | — | — |
| Materialübersicht eigenes Ressort | — | ✓ | ✓ | — |
| Zuweisung Zentrallager → Ressort | ✓ | ✓ | — | — |
| **Beschaffung** (Bedarf, Offerten, Bestellen) | ✓ | ✓ | — | — |
| **Erhalten** markieren | ✓ | ✓ | — | — |
| Beschaffung **Shell** (Phase 2, leer) | ✓ | ✓ | — | — |
| Settings (Benutzer, Dept) | ✓ | ✓ | — | — |
| Settings (Pfadi-Material-Tabs) | — | — | — | — |
| Einladung accept/reject | — | — | — | ✓ |
| Grossanlass in Pfadi-`/activities` | — | — | — | ✓** |

\* RL/User: gefilterte Sicht im **eigenen Ressort-Baum**; Bauprojekte **jederzeit** anlegbar §4.2. \** + `guest_group_id` für Leiter/User.

### Backend (Ziel)

`GrossanlassAccessService`: `canUserCreateGrossanlassDepartment`, `canUserManagePlanung`, `canUserViewMaterialUebersichtScope`, `canUserRespondToParticipantInvite` — Wiederverwendung `canInvitedDepartmentMwAssignGroup`, `canUserSeeInvitedActivityInList`.

---

## 18. Offene Fragen

| # | Frage | Tendenz |
|---|-------|---------|
| 1 | `function_label` auf `group_membership` | optional v1 |
| 2 | Struktur-FK: `department_id` vs. `main_activity_id` | beides möglich; Config verlinkt |
| 3 | ~~RL Teilbereiche erst nach Freigabe?~~ | **Nein** — §4.2: immer für berechtigte User |
| 4 | Neue Depts nach Freigabe | sofort `pending` + Inbox |
| 5 | Inbox-Kategorie Runde geöffnet | `grossanlass_round_opened` (neu) |

---

## 19. Out of scope (v1)

- Grossanlass ohne eigenes Department
- `/activities`-Liste im Grossanlass-Dept
- Activity-Erstell-Wizard (camp/event-Style)
- Ressorts an `event` anflanschen
- Typ «KALA»
- E-Mail/Push für Einladungen
- Automatischer J+S-Versand

---

## 20. Implementierungsprinzipien — keine Doppelspur

Grossanlass ist **Erweiterung** der bestehenden App — **kein** paralleles Produkt mit eigenem Layout, eigenen Formular-Stilen oder eigener Benachrichtigungs-Pipeline.

**Regel:** Vor jeder neuen Komponente/Service prüfen, ob ein zentraler Baustein existiert und nur **gebrancht** oder **konfiguriert** werden muss (`is_grossanlass`, Sidebar-Einträge, Inbox-Kategorie).

### Frontend — zentral nutzen

| Bereich | Wiederverwenden | Nicht bauen |
|---------|-----------------|-------------|
| **Shell** | [`AppLayout.vue`](../../frontend/src/components/layout/AppLayout.vue), [`TopHeader.vue`](../../frontend/src/components/layout/TopHeader.vue), [`SidebarNavigation.vue`](../../frontend/src/components/layout/SidebarNavigation.vue), [`PageShell.vue`](../../frontend/src/components/layout/PageShell.vue) | eigenes `GrossanlassLayout`, zweite Sidebar |
| **Settings-Subnav** | [`SettingsView.vue`](../../frontend/src/views/SettingsView.vue) — gefiltert §3.6 | volle Pfadi-Liste ungefiltert |
| **Beschaffung** | `GrossanlassBeschaffungView` + Tab-Shell §3.7 (Phase 2); Inhalt PR5 | Pfadi-`/accounting` einbinden |
| **Planung / Tabs** | Pattern [`SettingsView.vue`](../../frontend/src/views/SettingsView.vue) + [`SettingsSubnavList`](../../frontend/src/components/settings/SettingsSubnavList.vue); Shell-first §3.5 | jedes Tab als Sidebar-Eintrag |
| **Ressort-Baum** | Orientierung [`GroupsSettingsView.vue`](../../frontend/src/views/settings/GroupsSettingsView.vue) — API-Fassade `/grossanlass/groups` | parallele Gruppen-UI-Logik |
| **Route** | `/:departmentId/…` wie Pfadi-Dept ([Router](../../frontend/src/router/index.ts)) | neues URL-Schema `/grossanlass/…` |
| **Formulare / Dialoge** | `E*`-Bausteine (`EDialog`, `ETextField`, `ESelect`, `EButton`) — [vuetify-standards.md](../ui/vuetify-standards.md) | rohe `V*`-Felder oder Custom-CSS pro View |
| **Wizard Create** | Pattern [`DepartmentModal.vue`](../../frontend/src/components/DepartmentModal.vue) (Org, Parent-Baum, User-Suche) | komplett neues Formular-Design |
| **Menü Hinzufügen ▼** | `v-menu` wie [`SettingsView.vue`](../../frontend/src/views/SettingsView.vue) | zweiter separater Header-Button |
| **Loading / Empty** | `ELoadingState`, `EEmptyState` | eigene Spinner/Leerseiten |
| **Datum Anlass** | [`ActivityDateRangeField`](../../frontend/src/components/activities/wizard/ActivityDateRangeField.vue) / [activity-datetime-fields.md](../ui/activity-datetime-fields.md) | ad-hoc Date-Inputs |
| **Dept-Wechsel** | bestehendes Profil-Dropdown in `TopHeader` | eigener Dept-Switcher |
| **i18n** | `de.json` / bestehende Key-Struktur (`settings.…`, `components.…`) | hardcodierte Strings |

Übersicht weiterer Bausteine: [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md). **Planung im Detail:** [§3.5](#35-routen--leere-seiten-shell-first).

### Backend — zentral nutzen

| Bereich | Wiederverwenden | Nicht bauen |
|---------|-----------------|-------------|
| **Department** | `Department`, `Membership`, Create-Flow [`DepartmentController`](../../backend/src/Controller/DepartmentController.php) | parallele «Grossanlass»-Entität ohne Dept |
| **Groups / Ressorts** | bestehendes `Group` + API-Fassade `/grossanlass/groups` §4.4 | eigene Ressort-Tabelle |
| **Activities** | `Activity` + `type: grossanlass` | camp/event-Wizard duplizieren |
| **MW-Zuweisung** | `addMember`-Logik, `sendDepartmentMemberAddedEmail` | separater Mail-Weg |
| **Inbox** | [`InboxMessageService`](../../backend/src/Service/InboxMessageService.php), Tabelle `inbox_message` — [nachrichtenzentrale.md](../nachrichtenzentrale.md) | zweites Notification-System |
| **Beschaffung** | eigene Entitäten §3.7 / §14.4 — Fassade `/grossanlass/beschaffung/*` (**Phase 5**) | Follow-ups, Abschreibung, Pfadi-Kostenstellen-CRUD |
| **Einladungen (später)** | `CATEGORY_ACTIVITY_DEPT_INVITE` / camp-event-Pattern | neuer Invite-Stack |
| **Rechte** | `AdminCapabilityChecker`, Membership-Rollen | eigene Parallel-Matrix |

Neu darf es nur sein, was **domänenspezifisch** ist: `is_grossanlass`, `department_grossanlass_config`, `activity_grossanlass_*`, Inbox `grossanlass_mw_assigned`, `grossanlass_round_opened`.

### UI-Grossanlass vs. Pfadi — nur Unterschiede

| | Pfadi-Dept | Grossanlass-Dept |
|--|------------|------------------|
| Sidebar | Standard | **Conditional** in `SidebarNavigation` (kein `/activities`, ggf. Planung) |
| Dashboard-View | `DashboardView` oder conditional / eigene View **in gleicher Route** | gleiche Shell |
| Datenflag | — | `department.is_grossanlass` in Membership-Response |

**Phase 1:** kein neues Design-System — Platzhalter-Dashboard mit gleichen Layout-Tokens wie [`DashboardView.vue`](../../frontend/src/views/DashboardView.vue).

---

## Siehe auch

- [Aktivitäten-Übersicht](../activities/README.md)
- [J+S-Material](../activities/js-material/README.md)
- [newUI / Fuhrpark](../activities/newUI/SPEC.md)
