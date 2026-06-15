# KALA — Grossanlass / Kantonallager

Spezifikation für Aktivitäten vom Typ **`kala`**: department-übergreifende Grossanlässe (PFF, Kantonslager, Gross-Event), mit **eigenständigem Anlass-Department**, **flexibler Struktur** (verschachtelt oder parallel), Planungsrunden und getrennter Material-Sicht.

**Stand:** Juni 2026 · **Status:** Spezifikation (Ziel); Umsetzung offen · wird iterativ aus Konzept-Chats ergänzt

**Verwandt:** [status.md](../status.md) · [material-pipeline.md](../material-pipeline.md) · [pack-workflow-rules.md](../pack-workflow-rules.md) · [js-material/README.md](../js-material/README.md) · [Supplier-Portal §1b](../../supplier/supplier-portal.md#1b-drei-produktlinien-nicht-vermischen)

---

## Inhaltsverzeichnis

1. [Zielbild und Abgrenzung](#1-zielbild-und-abgrenzung)
2. [Anlass-Department (Flag beim Erstellen)](#2-anlass-department-flag-beim-erstellen)
3. [Struktur — offen (verschachtelt oder parallel)](#3-struktur--offen-verschachtelt-oder-parallel)
4. [Aktivitätstyp `kala`](#4-aktivitätstyp-kala)
5. [Setup-Seite (eigene Route, Stepper)](#5-setup-seite-eigene-route-stepper)
   - [5.4 Zwischenspeichern](#54-zwischenspeichern-pro-schritt)
   - [5.5 Wiederaufnehmen & Navigation](#55-wiederaufnehmen--navigation)
   - [5.6 UI-Shell (wie Detail)](#56-ui-shell-wie-detail)
6. [Teilnehmer & Einladungen](#6-teilnehmer--einladungen)
7. [Ressorts & Unterlager](#7-ressorts--unterlager)
8. [Planungsrunden (Zeitfenster)](#8-planungsrunden-zeitfenster)
9. [Material-Sichtbarkeit und Buchung](#9-material-sichtbarkeit-und-buchung)
10. [J+S über KALA (Vorgabenrunde)](#10-js-über-kala-vorgabenrunde)
11. [Datenmodell](#11-datenmodell)
12. [Activity-Flow, Pack, Detail-Ansicht](#12-activity-flow-pack-detail-ansicht)
13. [API (Ziel)](#13-api-ziel)
14. [Implementierungsphasen](#14-implementierungsphasen)
15. [Offene Fragen](#15-offene-fragen)
16. [Explizit out of scope (v1)](#16-explizit-out-of-scope-v1)

---

## 1. Zielbild und Abgrenzung

KALA (Kantonallager / Grossanlass) ist **kein normales Gruppen-Lager** (`camp`) und kein Einzel-Event (`event`). Mehrere Departments arbeiten **strukturell** an einem Anlass mit:

- **Zentrallager** im **Anlass-Department** (Chief)
- **Eigenes Material** je Teilnehmer-Department (bleibt im eigenen Lager bis Ausgabe)
- **J+S** optional zentral in einer **Vorgabenrunde** (siehe [js-material](../js-material/README.md))

| | `camp` / `event` | **`kala`** |
|--|------------------|------------|
| Fokus | eine Gruppe / Abteilung | Kanton, PFF, Grossanlass |
| Host-Department | normales Pfadi-Department | **eigenständiges Anlass-Department** (Flag) |
| Partner | ad hoc `invited_departments` | **strukturelle** Teilnehmer + Unterlager |
| Wizard | Modal-Dialog | **eigene Setup-Seite** mit Stepper |
| Material-Sicht | Host + Partner | **pro Department nur eigenes**; Anlass-Dept sieht alles |
| Ressorts | — | funktionale Bereiche + Verantwortliche |
| Zeitsteuerung | Activity-Status | zusätzlich **Planungsrunden** |

**Use Cases:** PFF (Pfadi Folks Fest), Kantonslager mit gemeinsamer J+S-Bestellung, Gross-Event mit mehreren Unterlagern.

---

## 2. Anlass-Department (Flag beim Erstellen)

### 2.1 Grundidee

Das **Chief-Department ist kein normales Pfadi-Department**, das zufällig hostet — es ist eine **eigenständige organisatorische Einheit** des Grossanlasses, z. B. «PFF 2027 Organisation» oder «KALA Kanton Thurgau».

Beim **Anlegen eines Departments** kann ein Flag gesetzt werden — **nur** durch **Organisationschef**, **Suborgchef** oder **Superadmin** (nicht MW/DC eines Pfadi-Departments):

| Flag (Ziel) | Bedeutung |
|-------------|-----------|
| `department.is_grossanlass = true` | Dieses Department ist ein **Anlass-Department** |
| `department.is_grossanlass = false` (Default) | Normales Department — **kein** KALA-Typ bei Aktivitäten |

Technischer Name in der Spec: `is_grossanlass` (UI: «Grossanlass-Department»). Alternative später: `department_kind = 'standard' | 'grossanlass'`.

### 2.2 Verhalten nach Flag

| Bereich | `is_grossanlass = false` | `is_grossanlass = true` |
|---------|--------------------------|-------------------------|
| **Aktivität anlegen** | `activity`, `camp`, `event`, `external` (wie heute) — **kein** `kala` | zusätzlich **`kala`** |
| **Material-Verwaltung** | eigenes Lager, normale MW-Workflows | **Zentrallager des Anlasses** + Koordination für Teilnehmer |
| **KALA-Setup** | — | Setup-Seite, Ressorts, Unterlager, Planungsrunden |
| **Teilnahme an fremdem KALA** | als Gast-Department (eigenes Material) | Anlass-Dept ist **nie** «nur Gast» |

**Ohne Flag** verhält sich das Department **vollständig wie heute** — eigenes Material, keine KALA-Funktionen. Der Typ `kala` erscheint im Erstell-Dialog **nicht**.

### 2.3 Hinweis wenn kein Grossanlass-Department (UI)

In einem **normalen** Department (`is_grossanlass = false`):

| Rolle | Chip `kala` | Hinweis zu KALA |
|-------|-------------|-----------------|
| **org / sub / sa** | nein | **ja** — Infobox (siehe unten) |
| **MW / DC / Leiter / User** | nein | **nein** — KALA wird **nicht** erwähnt (wie heute, nur activity/camp/event/external) |

**Nur Organisationschef / Suborgchef / Superadmin** (im normalen Pfadi-Department) sehen unter den Typ-Chips eine Infobox:

> **Grossanlass (KALA)** läuft über ein eigenes Grossanlass-Department. Legen Sie unter Verwaltung → Departments ein neues Department an und aktivieren Sie «Grossanlass-Department». Wechseln Sie danach in dieses Department, um eine KALA-Aktivität zu erstellen.

Optional Link: «Departments verwalten».

**MW, DC, Leiter und User** sehen weder den Chip `kala` noch einen Hinweistext — für sie existiert KALA im Erstell-Dialog nicht sichtbar.

| Rolle | Im **Anlass-Dept** (`is_grossanlass`) | Im **Pfadi-Dept** |
|-------|--------------------------------------|-------------------|
| org / sub / sa | Chip `kala` ja | Hinweis §2.3, kein Chip |
| MW / DC (Anlass-Dept: festzulegen) | Chip `kala` ja | — |
| MW / DC / Leiter / User | — | nur Standard-Typen, **kein** KALA-Bezug |

Backend: `POST type=kala` mit `department.is_grossanlass = false` → `403` (für alle Rollen; MW/User erreichen das nur über API, nicht über UI).

### 2.4 Anlegen-Flow (Gesamt)

```
1. org/sub legt Department an
   Name: «PFF 2027»
   ☑ Grossanlass-Department

2. User wechselt in dieses Department (Membership MW/Org)

3. Aktivitäten → «Neu»
   Typ-Chips: activity | camp | event | external | kala   ← kala nur hier sichtbar

4. KALA wählen + Name* → activity.department_id = Anlass-Department
   → Redirect Setup-Seite

5. Teilnehmer = normale Pfadi-Departments (gleiche Organisation / Suche)
   — keine Children des Anlass-Departments nötig
```

**Gegenbeispiel — Pfadi Winterthur (ohne Flag):**
- **org/sub:** «Neu» → activity/camp/event/external + Hinweis §2.3
- **MW/Leiter/User:** «Neu» → nur activity/camp/event/external, **ohne** KALA-Bezug

### 2.5 UI — Department erstellen / bearbeiten

| Ort | Element |
|-----|---------|
| Department-Modal / Admin | Checkbox «Grossanlass-Department» — **nur sichtbar für org/sub/sa** |
| `ActivityTypeChips` | `kala` nur wenn `activeDepartment.is_grossanlass === true` |
| `ActivityCreateWizard` / Typ-Schritt | Infobox §2.3 **nur** wenn `!is_grossanlass` **und** Rolle org/sub/sa |
| Backend `ActivityAccessService` | `canUserCreateActivityType(..., 'kala')` nur wenn Dept `is_grossanlass` |

**Hinweis:** Flag nachträglich setzen/entfernen — nur org/sub/sa; bei bestehenden KALA-Aktivitäten Warnung.

### 2.6 Zwei Department-Welten in einer Organisation

```
Organisation «Pfadi Kanton XY»
│
├── Department «Pfadi Winterthur»     is_grossanlass: false
├── Department «Pfadi Frauenfeld»     is_grossanlass: false
│
└── Department «PFF 2027»               is_grossanlass: true  ← Anlass-Department
      ├── MaterialItem = Zentrallager
      ├── KALA activity «PFF 2027»
      └── activity_kala_participant → Winterthur, Frauenfeld, …
```

Ein User kann in **beidem** Mitglied sein (MW Winterthur + Ressort-Lead in PFF 2027) — getrennte Memberships.

---

## 3. Struktur — offen (verschachtelt **oder** parallel)

KALA unterstützt **beide Organisationsformen** — wählbar pro Anlass, nicht fest verdrahtet. Ziel: kleine Grossanlässe einfach, grosse (PFF) hierarchisch.

### 3.1 Zwei Modi (beide gültig)

| Modus | Beschreibung | Typisch für |
|-------|--------------|-------------|
| **Verschachtelt** | Ressort → Unterlager → Pfadi-Department | PFF, grosse Events mit Funktionsbereichen |
| **Parallel** | Ressorts und Unterlager **getrennt**; Depts direkt am Unterlager oder nur als flache Teilnehmerliste | schlankes Kantonslager, wenig Ressort-Logik |

**Offen (Default):** `activity_kala_config.struktur_modus = 'offen'` — alles erlaubt, Chief mischt nach Bedarf.

```
struktur_modus
  'offen'       -- Default: beide Muster gemischt erlaubt
  'verschachtelt'  -- UI bevorzugt Baum; Unterlager sollen Ressort haben
  'parallel'    -- UI: getrennte Bereiche Ressorts | Unterlager | Teilnehmer
```

### 3.2 Verschachtelt (Ressort → Unterlager → Department)

```
Ressort «Verpflegung»
  ├── Verantwortliche: Max, Anna
  ├── Unterlager «Küche Nord»
  │     ├── Pfadi Winterthur
  │     └── Pfadi Frauenfeld
  └── Unterlager «Küche Süd»
        └── Pfadi Kreuzlingen
```

- `activity_kala_unterlager.ressort_id` **gesetzt**
- `activity_kala_participant.ressort_id` + `unterlager_id` abgeleitet

### 3.3 Parallel (getrennte Ebenen)

```
Ressorts (nur Funktion + User)
  ├── Ressort «Küche» — Max, Anna
  └── Ressort «Technik» — …

Unterlager (nur Dept-Cluster, ohne Ressort-Parent)
  ├── Unterlager «Region Ost» — Winterthur, Frauenfeld
  └── Unterlager «Region West» — Kreuzlingen

Teilnehmer (optional flach, ohne Unterlager)
  └── Pfadi XY — pending (bis Zuordnung)
```

- `activity_kala_unterlager.ressort_id` **null**
- `activity_kala_ressort` ohne Unterlager-Kinder — nur Roster
- `activity_kala_participant` mit `unterlager_id` **null** möglich (flache Einladung)

### 3.4 Was in beiden Modi gilt

| Regel | Verschachtelt | Parallel | Offen |
|-------|---------------|----------|-------|
| Ressort ohne Unterlager | optional | **ja** | ja |
| Unterlager ohne Ressort | **nein** | **ja** | ja |
| Unterlager ohne Department | ja | ja | ja |
| Department nur am Unterlager | empfohlen | empfohlen | optional (+ flach) |
| Ressort-User (`function_label`) | ja | ja | ja |
| Zentrallager = Anlass-Dept | ja | ja | ja |
| Eigenmaterial Pfadi-Dept | ja | ja | ja |

**Ein Department** max. **einmal** pro KALA-Aktivität (ein Unterlager oder flache Teilnahme).

Optional später: **Unterressorts** via `activity_kala_ressort.parent_id`.

| Konzept | Wer pflegt |
|---------|------------|
| **Anlass-Department** | org / sub / MW — Zentrallager, Gesamtsteuerung |
| **Struktur** (beide Modi) | nur Anlass-Dept — Setup Schritt `struktur` |
| **Eigenes Material / J+S** | Pfadi-Dept-MW in Planungsrunden |

---

## 4. Aktivitätstyp `kala`

- `activity.type = 'kala'`
- **Nur anlegbar**, wenn `activity.department_id` ein Department mit `is_grossanlass = true` ist
- Eigene Regeln: Setup-Seite, Ansichten, Berechtigungen, Pack-Profil (siehe §13)
- **Nicht** als Ersatz für `camp`/`event`

Beim Anlegen:

| Feld | Regel |
|------|-------|
| `name` | **Pflicht** (Schnellstart, vor Setup-Seite) |
| `status` | `draft` |
| `create_wizard_completed` | `false` bis Setup abgeschlossen |
| `department_id` | Anlass-Department (`is_grossanlass`) |

**Voraussetzung:** aktives Department mit `is_grossanlass = true` (siehe §2). In Pfadi-Departments: `kala` weder Chip noch Hinweis für MW/Leiter/User; **nur org/sub/sa** sehen den Hinweis §2.3.

Berechtigung Anlegen (innerhalb Anlass-Dept): org / sub / sa; MW/DC (festzulegen).

---

## 5. Setup-Seite (eigene Route, Stepper)

KALA-Setup ist **kein** Modal-Wizard wie camp/event, sondern eine **eigene Vollseite** (`KalaActivitySetupView`) — gleiche «Schwere» und Breite wie `ActivityDetailView`, aber im **Einrichtungsmodus** mit **mehr Schritten** und **Zwischenspeichern** nach jedem Schritt.

Camp/event speichert Zwischenstände im Modal (`saveDraftStep`, `create_wizard_completed: false`). KALA übernimmt das Prinzip, skaliert es aber auf eine **eigene Route** und **getrennte APIs** pro Setup-Bereich (Unterlager, Ressorts, Teilnehmer, …).

### 5.1 Zwei Phasen

```
Phase A — Schnellstart (kurzes Modal, nur Anlass-Department)
  Typ KALA + Name* → POST activity
    { type: 'kala', name, status: 'draft', create_wizard_completed: false }
  → Redirect Phase B

Phase B — Setup-Seite (eigenes Fenster)
  /:departmentId/activities/:activityId/setup
  /:departmentId/activities/:activityId/setup/:stepKey
  → horizontaler/vertikaler Stepper, **4 Schritte**
  → nach jedem «Weiter»: Zwischenspeichern (§5.4)
  → Schritt 4 «Setup abschliessen»:
       create_wizard_completed = true
       → Redirect /:departmentId/activities/:activityId (Detail)
```

**Phase A** legt nur die Activity an (Name + Typ). **Alles Weitere** passiert in Phase B — dort kann der Chief **tagelang** weiterarbeiten, Schritte verlassen und später fortsetzen.

### 5.2 Stepper-Schritte (Inhalt & Validierung)

| # | Key | Inhalt | Pflicht vor «Weiter» | Gespeichert in |
|---|-----|--------|----------------------|----------------|
| 1 | `stammdaten` | Name*, Zeitraum, Eventstandort, Notizen | Name, Zeitraum, Venue | `PATCH activity` |
| 2 | `struktur` | Ressorts, Unterlager, Depts, Verantwortliche — **Baum oder getrennte Panels** (§3, §5.2.1) | — (optional) | `PUT …/kala/struktur` |
| 3 | `runden` | Planungsrunden: Typ, `opens_at`, `closes_at` | — (min. 0 in v1) | `PUT …/kala/rounds` |
| 4 | `uebersicht` | Zusammenfassung (Baum Struktur), Vollständigkeits-Check | Pflichtfelder Schritt 1 | `POST …/kala/setup/complete` |

**Schritt `struktur`** ersetzt die früher getrennten Schritte Unterlager / Ressorts / Teilnehmer. Teilnehmer entstehen primär über Unterlager-Zuordnung; **parallel** auch flache Einladung (§6).

#### 5.2.1 UI Schritt `struktur` — zwei Darstellungen

Abhängig von `struktur_modus` (§3.1):

| UI | `verschachtelt` / Baum | `parallel` / Tabs |
|----|------------------------|-------------------|
| Ressorts + User | Knoten oben, Unterlager als Kinder | Tab «Ressorts» |
| Unterlager + Depts | unter Ressort-Knoten | Tab «Unterlager» |
| Flache Teilnehmer | — | Tab «Teilnehmer» (ohne UL) |
| `offen` | **Umschaltbar** Baum ↔ Tabs im Setup | |

**Baum (verschachtelt):**

```
+ Ressort «Verpflegung»                    [Verantwortliche …]
    + Unterlager «Küche Nord»              [+ Department]
        • Pfadi Winterthur
```

**Parallel (Tabs):**

```
[Ressorts] [Unterlager] [Teilnehmer]
  Tab Unterlager: «Region Ost» → Winterthur, Frauenfeld  (ressort_id leer)
  Tab Ressorts: «Küche» → User Max, Anna               (ohne Unterlager)
  Tab Teilnehmer: Pfadi XY eingeladen, noch kein Unterlager
```

Beim Hinzufügen eines Departments zu einem Unterlager: `activity_kala_participant` + Einladung. Flache Einladung: nur `participant` ohne `unterlager_id`.

Schritt 1 enthält **kein** Material und **keine** Teilnehmer — das trennt Setup klar vom späteren Betrieb (Planungsrunden in der Detail-Ansicht).

**Stepper-UI:** 4 Schritte; erledigte mit Häkchen. Schritt 1 Pflicht vor «Abschliessen».

### 5.3 Setup vs. Betrieb

| Aktion | Setup-Seite | Detail (nach Setup) |
|--------|-------------|---------------------|
| Stammdaten | ja (Schritt 1) | Anlass-Dept edit |
| Struktur (verschachtelt **oder** parallel) | ja (Schritt 2) | Anlass-Dept edit (eingeschränkt) |
| Planungsrunden definieren | ja (Schritt 3) | Anlass-Dept öffnet/schliesst |
| J+S / Eigenmaterial | nein | wenn Runde offen |
| Pack / Event | nein | nach `approved` |

### 5.4 Zwischenspeichern pro Schritt

**Pflicht bei «Weiter»:** aktueller Schritt wird **vor** dem Schrittwechsel an die API gesendet. Bei Fehler: auf dem Schritt bleiben, Fehlermeldung — kein stiller Verlust.

| Trigger | Verhalten |
|---------|-----------|
| Button **«Weiter»** | Validierung → API-Save Schritt → `setup_steps_completed` aktualisieren → nächster Schritt |
| Button **«Zurück»** | nur Navigation (Daten bereits gespeichert wenn «Weiter» gedrückt war) |
| Klick auf **Stepper-Label** (erledigter Schritt) | Navigation ohne erneutes Speichern |
| **AutoSave** (optional v1.1) | debounced PATCH im Schritt 1 (Stammdaten), analog `ActivityDraftOverviewForm` |
| Verlassen der Seite | Entwurf bleibt auf Server (`create_wizard_completed = false`) |

**Fortschritt auf dem Server** (Ziel):

```
activity_kala_config
  setup_steps_completed   JSON   -- z. B. ["stammdaten","unterlager",…]
  setup_last_step         string -- letzter bearbeiteter stepKey
  setup_updated_at        datetime
```

Oder äquivalent als Felder auf `activity` / `activity_kala_config` — Frontend kann nach Reload direkt zum `setup_last_step` springen.

**Schritt 4 «Setup abschliessen»:**

- Prüft: Schritt 1 vollständig (Pflicht)
- Setzt `create_wizard_completed = true`
- Optional: erste Planungsrunde auf `scheduled` belassen (noch nicht `open`)
- Redirect zur **Detail-Ansicht** — ab dann normale KALA-Tabs, kein Setup-Stepper mehr

### 5.5 Wiederaufnehmen & Navigation

| Situation | Verhalten |
|-----------|-----------|
| User öffnet KALA-Entwurf aus Liste | Wenn `!create_wizard_completed` → Redirect `/setup` (oder Banner «Setup fortsetzen») |
| User öffnet `/activities/:id` direkt | Chief + `!create_wizard_completed` → Redirect `/setup/:setup_last_step` |
| Gast-Pfadi-Dept öffnet KALA | **Kein** `/setup` — Detail mit Hinweis «Anlass wird eingerichtet» |
| Setup abgeschlossen | `/setup`-Route → Redirect Detail (Setup read-only oder 404) |

Zwischenspeichern bedeutet: **jeder Schritt ist ein persistenter Server-Zustand** — Browser schliessen, anderes Gerät, nächster Tag: Chief setzt am letzten Schritt fort.

### 5.6 UI-Shell (wie Detail)

Aufbau analog `ActivityDetailView` / `ActivitiesView` Detail-Root:

```
┌─────────────────────────────────────────────────────────────┐
│ ← Aktivitäten    PFF 2027          Entwurf · KALA · Setup   │
├─────────────────────────────────────────────────────────────┤
│ ① Stammdaten — ② Struktur — ③ Runden — ④ Übersicht │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   [ Inhalt des aktuellen Schritts — volle Seitenbreite ]    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Speichert… / Gespeichert ✓     ← Zurück    Weiter →       │
└─────────────────────────────────────────────────────────────┘
```

- **Kein** `v-dialog` — eigene Route-Komponente `KalaActivitySetupView.vue`
- Composable: `useKalaSetupWizard.ts` (Step-State, Validierung, Save-Handler pro Schritt)
- Step-Komponenten: `KalaSetupStepStammdaten.vue`, `KalaSetupStepUnterlager.vue`, …
- Footer: «Weiter» ruft `saveCurrentStepAndAdvance()` — nicht nur lokalen Index++

**Abgrenzung camp/event:** deren Wizard bleibt im Modal; KALA-Setup **nur** auf `/setup` — keine Duplikation in `ActivityCreateWizard.vue`.

### 5.7 Fehler & unvollständiger Entwurf

- Schritt 1 unvollständig → «Abschliessen» blockiert
- Struktur leer → **erlaubt** — Grossanlass ohne Hierarchie möglich
- `struktur_modus` wählbar in Schritt 2 (Default `offen`)
- Department an Unterlager **oder** flach → Einladung `pending` bis Annahme

---

## 6. Teilnehmer & Einladungen

Zwei Wege — **beide** in der Spec und API:

### 6.1 Über Unterlager (bevorzugt)

```
Department → Unterlager (optional unter Ressort)
  → activity_kala_unterlager_department
  → activity_kala_participant (ressort_id wenn gesetzt, unterlager_id Pflicht)
  → Inbox-Einladung
```

### 6.2 Flach (parallel, ohne Unterlager)

```
Department direkt einladen
  → activity_kala_participant (unterlager_id null, ressort_id null)
  → später im Setup/detail: Zuordnung zu Unterlager nachziehen
```

| Feld Participant | Verschachtelt | Parallel / flach |
|------------------|---------------|------------------|
| `department_id` | Pflicht | Pflicht |
| `unterlager_id` | gesetzt | **null** erlaubt |
| `ressort_id` | abgeleitet von UL | **null** erlaubt |
| `status` | pending / accepted / rejected | gleich |

### 6.3 Department-Auswahl

Beim Hinzufügen zu einem Unterlager:

1. Schnellauswahl: Pfadi-Departments derselben **Organisation** (`is_grossanlass = false`)
2. Suche: weitere Departments (`searchJoinableDepartments`)
3. Ein Department nur **einmal** pro KALA-Aktivität (ein Unterlager **oder** flach)

Eingeladene Departments laden **keine** weiteren ein.

### 6.4 Abgrenzung zu `invited_departments` (JSON)

KALA: `activity_kala_participant` + Struktur §3 (verschachtelt oder parallel). camp/event: `invited_departments` unverändert.

---

## 7. Ressorts & Unterlager

Gilt für **beide** Modi (§3). Felder bleiben gleich; **Beziehungen** optional.

### 7.1 Ressort

- Name, optional `parent_id` (Unterressort)
- Verantwortliche User (`activity_kala_ressort_user`)
- **Kann** Unterlager enthalten (`verschachtelt`) oder **allein** stehen (`parallel`)

### 7.2 Unterlager

- `ressort_id` **nullable** — gesetzt = unter Ressort; `null` = freistehend (parallel)
- Pfadi-Departments via `activity_kala_unterlager_department`
- Darf leer sein (nur Zentrallager-Bezug später)

### 7.3 Roster & Statistik

| Modus | Gruppierung Statistik |
|-------|----------------------|
| Verschachtelt | Ressort → Unterlager → Dept |
| Parallel | Filter nach Ressort **oder** Unterlager **oder** flache Teilnehmer |

API: `GET …/kala/struktur/tree` (Baum) und `GET …/kala/struktur/flat` ( drei Listen )

### 7.4 Material & Logistik (Phase 2+)

- Zentrallager = `MaterialItem` im **Anlass-Department**
- Ressort-/Unterlager-Leads: **Wünsche** → Anlass-MW weist aus Zentrallager zu
- Logistik-Steps Chief-definiert (pro Ressort oder Unterlager — festzulegen)

Eigene Tabellen — §11.9.

---

## 8. Planungsrunden (Zeitfenster)

```
activity.status           → Gesamt-Lebenszyklus (draft … completed)
activity_kala_round       → «Was ist JETZT erlaubt?»
```

| Runde `round_type` | Wer | Was |
|--------------------|-----|-----|
| `js_vorgabe` | Anlass-Dept öffnet → Depts füllen aus | J+S-Wünsche pro Pfadi-Dept |
| `eigenmaterial` | Pfadi-Depts (MW/Leiter) | Buchung aus **eigenem** Lager |
| `kala_central` | Anlass-MW | Zuweisung aus **Zentrallager** |
| `ressort_wuensche` | Ressort-Leads (Phase 2+) | Wunschlisten |
| `freigabe` | Anlass-MW | Gesamtprüfung vor `approved` |

Felder: `opens_at`, `closes_at`, `status` (`scheduled` | `open` | `closed`).

---

## 9. Material-Sichtbarkeit und Buchung

### 9.1 Sichtbarkeitsregel

| Betrachter | Sieht |
|------------|-------|
| Anlass-Dept / org / sub | alles: Zentrallager + alle Teilnehmer + J+S + Ressorts |
| Pfadi-Dept-MW (Gast) | **nur eigenes** Material (`material_item.department_id`) |
| Ressort-Lead (v1) | Roster + später eigene Ressort-Zeilen |

### 9.2 `activity_item` erweitern? — **Eher nein**

- Pfadi-Dept bucht **eigenes** Material → `material_item.department_id` = Pfadi-Dept (wie heute)
- Zentrallager = `MaterialItem` im **Anlass-Department**
- J+S → `activity_kala_js_submission`
- Ressort → `activity_kala_ressort_line` (Phase 2+)

Filter: `GET …/items` mit Viewer-Department; Anlass-Dept ohne Filter.

---

## 10. J+S über KALA (Vorgabenrunde)

Siehe [js-material/README.md](../js-material/README.md), erweitert um mehrere Pfadi-Departments:

```
Anlass-Dept öffnet Runde js_vorgabe
  → Pfadi-Depts: activity_kala_js_submission
  → Anlass-Dept aggregiert → activity_js_order → E-Mail J+S
  → Empfang Zentrallager → Verteilung
```

---

## 11. Datenmodell

### 11.1 Department (Erweiterung)

```
department.is_grossanlass   boolean NOT NULL DEFAULT false
```

### 11.2 Activity (Erweiterung)

```
activity.type = 'kala'
activity.create_wizard_completed   -- false während Setup
```

### 11.3 KALA-Konfiguration

```
activity_kala_config
  activity_id               FK UNIQUE
  struktur_modus            'offen' | 'verschachtelt' | 'parallel'  DEFAULT 'offen'
  has_struktur              boolean default false
  setup_steps_completed     JSON
  setup_last_step           string nullable
  setup_updated_at          datetime nullable
```

### 11.4 Ressorts (oberste Ebene)

```
activity_kala_ressort
  id, activity_id
  parent_id                 nullable   -- Unterressort (optional)
  name, notes, sort_order

activity_kala_ressort_user
  id, ressort_id, user_id
  function_label, role (lead|deputy|member), sort_order
```

### 11.5 Unterlager

```
activity_kala_unterlager
  id, activity_id
  ressort_id                NULLABLE   -- NULL = parallel (ohne Ressort)
  name, sort_order, notes

activity_kala_unterlager_department
  unterlager_id, department_id
```

### 11.6 Teilnehmer

```
activity_kala_participant
  id, activity_id, department_id
  ressort_id                NULLABLE   -- abgeleitet oder leer (flach)
  unterlager_id             NULLABLE   -- NULL = flache Einladung (parallel)
  group_id                  nullable
  status                    pending | accepted | rejected
  invited_at, decided_at, invited_by_user_id
```

### 11.7 Planungsrunden

```
activity_kala_round
  id, activity_id, round_type, opens_at, closes_at
  status, opened_by_user_id, closed_by_user_id, notes
```

### 11.8 J+S-Submission

```
activity_kala_js_submission / activity_kala_js_submission_item
```

### 11.9 Ressort-Material (Phase 2+)

```
activity_kala_logistics_step
activity_kala_ressort_request
activity_kala_ressort_line        -- optional unterlager_id
activity_kala_ressort_line_progress
```

**Baum-API** (`struktur_modus` verschachtelt / offen):

```json
{
  "struktur_modus": "verschachtelt",
  "ressorts": [
    {
      "name": "Verpflegung",
      "users": [{ "user_id": "…", "function_label": "Leitung" }],
      "unterlager": [
        { "name": "Küche Nord", "department_ids": ["…"] }
      ]
    }
  ],
  "unterlager_freistehend": [],
  "teilnehmer_flach": []
}
```

**Parallel-API** — zusätzlich / alternativ:

```json
{
  "struktur_modus": "parallel",
  "ressorts": [{ "name": "Küche", "users": [] }],
  "unterlager_freistehend": [
    { "name": "Region Ost", "department_ids": ["…"] }
  ],
  "teilnehmer_flach": [{ "department_id": "…" }]
}
```

---

## 12. Activity-Flow, Pack, Detail-Ansicht

- Status: wie camp/event ([status.md](../status.md)) + Planungsrunden
- Pack-Profil `kala`: Oberreiter Zentral (Anlass-Dept) | Pfadi-Dept | J+S
- **Anlass-Dept:** Übersicht | **Struktur** (Baum oder Tabs) | Runden | Material | J+S | Statistik | Pack
- **Gast-Pfadi-Dept:** Übersicht | Mein Material | J+S-Wunsch | Pack (eigenes)

---

## 13. API (Ziel)

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| PATCH | `/api/departments/{id}` | `is_grossanlass` (org/sub/sa) |
| POST | `/api/activities` | `type: kala` nur wenn `is_grossanlass` |
| GET/PATCH | `/api/activities/{id}/kala/setup` | Setup-Fortschritt |
| **GET/PUT** | **`/api/activities/{id}/kala/struktur`** | Baum + parallel in einem Payload (§11) |
| GET | `/api/activities/{id}/kala/struktur/tree` | Nur Baum-Ansicht |
| GET | `/api/activities/{id}/kala/struktur/flat` | Drei Listen (Ressorts / UL / flache TN) |
| GET | `/api/activities/{id}/kala/ressorts/roster` | Verantwortliche-Liste |
| GET/PUT | `/api/activities/{id}/kala/rounds` | Planungsrunden |
| POST | `/api/activities/{id}/kala/setup/complete` | Setup abschliessen |
| PATCH | `/api/activities/{id}/kala/setup/step/{stepKey}` | Zwischenspeichern |
| GET | `/api/activities/{id}/items` | Material-Filter §9 |

`ActivityAccessService`: `canUserCreateActivityType` prüft `is_grossanlass` für `kala`.

`useActivityGroupMemberScope` / `ActivityTypeChips`: `kala` nur wenn aktives Department `is_grossanlass`.

Frontend i18n (Vorschlag):

- `activities.kala.hintCreateGrossanlassDept` — nur org/sub/sa im Pfadi-Dept
- `activities.kala.errorDeptNotGrossanlass` — Backend-Fehler (API)

---

## 14. Implementierungsphasen

| Phase | Inhalt | DoD |
|-------|--------|-----|
| **0** | Dieses Dokument | Spec reviewed |
| **1** | `is_grossanlass` + Gate `kala` + Hinweis org/sub | Flag |
| **2** | Setup-Shell, 4 Schritte, Zwischenspeichern | `/setup` |
| 3 | `PUT …/kala/struktur` — **verschachtelt + parallel** (`struktur_modus`) | Beide Modi |
| **4** | Ressort-User + Roster-API | Verantwortliche |
| **5** | Planungsrunden | Zeitfenster |
| **6** | Material-Filter Pfadi-Dept | Sichtbarkeit |
| **7** | J+S-Vorgabenrunde | Submissions |
| **8** | Pack + Detail-Tabs | Betrieb |
| **9** | Ressort-Material + Logistik-Steps | Phase 2 |
| **10** | Statistik (nach Ressort/Unterlager) | Roll-up |

---

## 15. Offene Fragen

| # | Frage | Tendenz |
|---|-------|---------|
| 1 | Flag-Name `is_grossanlass` vs. `department_kind`? | v1: `is_grossanlass` boolean |
| 2 | Flag nachträglich entfernen bei laufendem KALA? | Warnung / sperren wenn offene KALA-Aktivitäten |
| 3 | `activity_item` erweitern? | **Eher nein** — siehe §9 |
| 4 | Unterressorts (`ressort.parent_id`)? | Optional Phase 2 |
| 5 | Unterlager ohne Department? | **Ja** |
| 6 | `struktur_modus` nachträglich wechseln? | **Ja** — UI-Hinweis bei Mischformen |
| 7 | Ein Anlass-Dept, mehrere KALA? | **Ja** |
| 8 | AutoSave Setup ohne «Weiter»? | v1: bei «Weiter»; Schritt 1 optional debounced |

---

## 16. Explizit out of scope (v1)

- KALA in normalem Pfadi-Department ohne Flag
- KALA als Ersatz für `camp`/`event`
- Ressort als eigenes `MaterialItem`-Lager
- Automatischer J+S-E-Mail-Versand
- Verrechnung zwischen Departments
- Gäste laden weitere Departments ein

---

## Siehe auch

- [Aktivitäten-Übersicht](../README.md)
- [J+S-Material](../js-material/README.md)
- [Material-Pipeline](../material-pipeline.md)
- [Pack-Workflow-Regeln](../pack-workflow-rules.md)
