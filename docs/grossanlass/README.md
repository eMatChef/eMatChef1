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
| Sidebar | **Dashboard**, **Materialien**, **Planung**, **Materialübersicht** — **kein** `/activities` |
| Ressorts | **`Group`** im Grossanlass-Dept |
| Struktur & Teilnehmer | **Dept-weit** (ein Zyklus pro Anlass) |
| Materialien (Menü) | Stammdaten: **Eigen \| Leihweise \| Fahrzeuge** |
| Materialübersicht | Zuweisung / Ausgabe **pro Ressort & Unterkategorie**; Lager vs. draussen |
| **Phase 1** | Erstell-Button, Wizard, App-Shell, Platzhalter-Dashboard, MW-Benachrichtigung — [MVP §Phase 1](./MVP.md#phase-1-grundgerüst) |
| **Erster Schnitt (MVP gesamt)** | Phase 1 + Ressorts + Planungsrunde Bedarf — [MVP.md](./MVP.md) |
| Planungsrunden (MVP) | `ressort_wuensche` — Wünsche Chief-intern, ohne Gast-Freigabe |
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
| **Sidebar** | Grossanlass-Branch §3.1: **Dashboard** aktiv; **kein** «Aktivitäten»; Planung/Material optional Stub oder ausgeblendet |
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

Gleiche **App-Shell** (`AppLayout`, `SidebarNavigation`), andere Einträge:

| Menü | Route (Vorschlag) | Inhalt |
|------|-------------------|--------|
| **Dashboard** | `/:deptId/dashboard` | Status-Übersicht (Default nach Login/Dept-Wechsel) |
| **Materialien** | `/:deptId/materials` | Stammdaten §10 |
| **Planung** | `/:deptId/planung` | Struktur, Teilnehmer, Runden, Activities §7 |
| **Materialübersicht** | `/:deptId/material-uebersicht` | Ausgabe §11 |
| Gruppen (Settings) | wie heute | Ressorts pflegen |
| Aufgaben, Inbox, … | unverändert | — |

**`/activities` im Grossanlass-Dept:** ausgeblendet.

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

**Material & Ausgabe:** Wünsche, Zuweisung und Pack können an **Ressort oder Teilbereich** gebunden werden — feinere Planung und gezielte Ausgabe («Ausgabe Bühne»).

### 4.2 Wer pflegt die Hierarchie?

| Ebene | Entwurf (`draft`) | Nach Freigabe (`published`) |
|-------|-------------------|-----------------------------|
| **Ressort** (oberste) | CM | CM (edit eingeschränkt) |
| **Teilbereich / Bauprojekt** | **CM** (MVP) | CM + **RL** des Ressorts |
| **Mitglieder (RL)** | CM | CM + RL im eigenen Ressort |

**MVP:** CM legt Ressorts und Unterressorts (Bauprojekte) **schon im Entwurf** an — RL-Struktur nach Freigabe ergänzt §17.

`group.allow_rl_structure` (Default `true`): RL darf Kinder-Groups anlegen.

Einstieg: **Planung**, **Gruppen** oder **Materialübersicht** gefiltert nach Teilbereich.

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
| **Ressorts & Mitglieder** | Groups + GroupMembership |
| **Planungsrunden** | §9 — definieren in Entwurf, öffnen nach Freigabe |
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

Am **Haupt-`anlass`** (`grossanlass_role: anlass`):

| `round_type` | Wer | Was |
|--------------|-----|-----|
| `js_vorgabe` | Chief | Pfadi-Depts: J+S |
| **`ressort_wuensche`** | **RL / CM** | **Bedarfserfassung** — Material & Fahrzeug pro Ressort/Teilbereich §9.1 |
| `eigenmaterial` | Pfadi-Depts | eigenes Lager |
| `grossanlass_central` | CM | Zuweisung Zentrallager → Ressorts |
| `freigabe` | CM | Freigabe vor Aufbau/Event |

**Regeln:**

- Runden in **Entwurf** anlegen (`scheduled`); **öffnen** durch CM — **MVP:** auch ohne Gast-Freigabe (Chief-intern)
- Runden **dürfen sich überschneiden** — erlaubte Aktionen = **Vereinigung** offener Runden
- **Auto open/close:** `use_auto_schedule` + `opens_at` / `closes_at`
- Dashboard-Checkliste: «Vor Event-Start» alle Pflicht-Runden geschlossen

### 9.1 Bedarfserfassung (`ressort_wuensche`) — MVP-Kern

In einer **offenen** Runde trägt RL (oder CM für ein Ressort) **Wunsch-Zeilen** ein:

| Feld | Typ | Pflicht |
|------|-----|---------|
| **Ressort / Bauprojekt** | `group_id` (Ressort oder Kind via `parent_id`) | ja |
| **Art** | `material` \| `fahrzeug` \| `beides` | ja |
| **Bezeichnung** | Freitext («Gerüst», «Transporter 3.5t») | ja |
| **Anzahl** | Zahl | ja |
| **Zeitraum** | `valid_from` / `valid_to` | ja |
| **Notizen** | Text | nein |
| **Status** | `requested` (MVP) | — |

**MVP:** Fahrzeug = Bedarf melden (Freitext/Notizen) — **kein** Fuhrpark-Stammdaten (§10.3) nötig. Material = Freitext — **kein** Katalog-Zwang.

**Berechtigung:** RL nur Zeilen im **eigenen Ressort-Baum**; CM alle Ressorts.

**UI:** Planung → Tab «Planungsrunden» → Runde öffnen → Liste/Formular pro Ressort.

Siehe Datenmodell §14.4 (`activity_grossanlass_wish_line`).

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

Inbox (neu): `InboxMessage::CATEGORY_GROSSANLASS_MW_ASSIGNED = 'grossanlass_mw_assigned'` (Chief-MW bei Create §2.5).

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
  round_type                ressort_wuensche | …
  status                    scheduled | open | closed
activity_grossanlass_wish_line          — MVP Bedarfserfassung §9.1
  round_id                  FK
  group_id                  FK → Ressort oder Teilbereich
  wish_kind                 material | fahrzeug | beides
  label                     Freitext-Bezeichnung
  quantity                  int
  valid_from, valid_to
  notes                     nullable
  status                    requested | assigned | …
activity_grossanlass_ressort_line       — Zuweisung Zentrallager (Phase 2+)
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
| POST | **`/api/departments/{id}/grossanlass/publish`** | **Freigabe** §7.2 |
| GET | `/api/departments/{id}/grossanlass/dashboard` | Widget-Daten §3.2 |
| GET/PUT | `/api/departments/{id}/grossanlass/planung/struktur` | Struktur §5 |
| GET/PUT | `/api/departments/{id}/grossanlass/planung/rounds` | Runden §9 |
| GET/POST/PUT/DELETE | `…/planung/rounds/{roundId}/wishes` | Wunsch-Zeilen §9.1 |
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
| **1** | **Grundgerüst:** Entry §2.2, Wizard §2.3–2.4, MW E-Mail+Inbox §2.5, App-Shell, Platzhalter-Dashboard §3.0 | [MVP Phase 1](./MVP.md#phase-1-grundgerüst) |
| **2** | Ressort-Baum (`parent_id`), Planung-Tab | §4 |
| **3** | Planungsrunde + Bedarfserfassung §9.1 | Wünsche |
| **4** | **`publish`** + Inbox Gast + accept | §7.2, §8 |
| **5** | Materialübersicht v1 | §11 |
| **6** | Material leiweise, Fahrzeuge | §10 |
| **7** | Activities Phasen, J+S, Pack | §6, §12 |

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
| Dashboard / Planung / Materialübersicht | ✓ | ✓ | ✓* | — |
| Materialien Stammdaten | ✓ | ✓ | — | — |
| Struktur & Teilnehmer (Entwurf) | ✓ | ✓ | — | — |
| **Grossanlass freigeben** | ✓ | ✓ | — | — |
| Teilbereich anlegen | ✓ | ✓ | ✓* | — |
| Planungsrunde **definieren** (Entwurf) | ✓ | ✓ | — | — |
| Planungsrunde **öffnen** | ✓ | ✓ | — | — |
| Planungsrunde **Wünsche einreichen** | — | ✓ | ✓* | — |
| Activity (Phase) anlegen | ✓ | ✓ | — | — |
| Materialübersicht gesamt | ✓ | ✓ | — | — |
| Materialübersicht eigenes Ressort | — | ✓ | ✓ | — |
| Zuweisung Zentrallager → Ressort | ✓ | ✓ | — | — |
| Einladung accept/reject | — | — | — | ✓ |
| Grossanlass in Pfadi-`/activities` | — | — | — | ✓** |

\* RL: gefilterte Sicht; Teilbereiche nur nach Freigabe (`published`). \** + `guest_group_id` für Leiter/User.

### Backend (Ziel)

`GrossanlassAccessService`: `canUserCreateGrossanlassDepartment`, `canUserManagePlanung`, `canUserViewMaterialUebersichtScope`, `canUserRespondToParticipantInvite` — Wiederverwendung `canInvitedDepartmentMwAssignGroup`, `canUserSeeInvitedActivityInList`.

---

## 18. Offene Fragen

| # | Frage | Tendenz |
|---|-------|---------|
| 1 | `function_label` auf `group_membership` | optional v1 |
| 2 | Struktur-FK: `department_id` vs. `main_activity_id` | beides möglich; Config verlinkt |
| 3 | RL Teilbereiche: CM muss Freigabe abwarten? | Ja — nur nach `published` |
| 4 | Neue Depts nach Freigabe | sofort `pending` + Inbox |

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
| **Shell** | [`AppLayout.vue`](../../frontend/src/components/layout/AppLayout.vue), [`TopHeader.vue`](../../frontend/src/components/layout/TopHeader.vue), [`SidebarNavigation.vue`](../../frontend/src/components/layout/SidebarNavigation.vue) | eigenes `GrossanlassLayout`, zweite Sidebar |
| **Route** | `/:departmentId/…` wie Pfadi-Dept ([Router](../../frontend/src/router/index.ts)) | neues URL-Schema `/grossanlass/…` |
| **Formulare / Dialoge** | `E*`-Bausteine (`EDialog`, `ETextField`, `ESelect`, `EButton`) — [vuetify-standards.md](../ui/vuetify-standards.md) | rohe `V*`-Felder oder Custom-CSS pro View |
| **Wizard Create** | Pattern [`DepartmentModal.vue`](../../frontend/src/components/DepartmentModal.vue) (Org, Parent-Baum, User-Suche) | komplett neues Formular-Design |
| **Menü Hinzufügen ▼** | `v-menu` wie [`SettingsView.vue`](../../frontend/src/views/SettingsView.vue) | zweiter separater Header-Button |
| **Loading / Empty** | `ELoadingState`, `EEmptyState` | eigene Spinner/Leerseiten |
| **Datum Anlass** | [`ActivityDateRangeField`](../../frontend/src/components/activities/wizard/ActivityDateRangeField.vue) / [activity-datetime-fields.md](../ui/activity-datetime-fields.md) | ad-hoc Date-Inputs |
| **Dept-Wechsel** | bestehendes Profil-Dropdown in `TopHeader` | eigener Dept-Switcher |
| **i18n** | `de.json` / bestehende Key-Struktur (`settings.…`, `components.…`) | hardcodierte Strings |

Übersicht weiterer Bausteine: [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md).

### Backend — zentral nutzen

| Bereich | Wiederverwenden | Nicht bauen |
|---------|-----------------|-------------|
| **Department** | `Department`, `Membership`, Create-Flow [`DepartmentController`](../../backend/src/Controller/DepartmentController.php) | parallele «Grossanlass»-Entität ohne Dept |
| **Ressorts** | `Group`, `GroupMembership`, `parent_id` | eigene Ressort-Tabelle |
| **Activities** | `Activity` + `type: grossanlass` | camp/event-Wizard duplizieren |
| **MW-Zuweisung** | `addMember`-Logik, `sendDepartmentMemberAddedEmail` | separater Mail-Weg |
| **Inbox** | [`InboxMessageService`](../../backend/src/Service/InboxMessageService.php), Tabelle `inbox_message` — [nachrichtenzentrale.md](../nachrichtenzentrale.md) | zweites Notification-System |
| **Einladungen (später)** | `CATEGORY_ACTIVITY_DEPT_INVITE` / camp-event-Pattern | neuer Invite-Stack |
| **Rechte** | `AdminCapabilityChecker`, Membership-Rollen | eigene Parallel-Matrix |

Neu darf es nur sein, was **domänenspezifisch** ist: `is_grossanlass`, `department_grossanlass_config`, `activity_grossanlass_*`, Inbox-Kategorie `grossanlass_mw_assigned`.

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
