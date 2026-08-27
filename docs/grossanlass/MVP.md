# Grossanlass — MVP

> Erster lieferbarer Schnitt. Vollständige Spezifikation: [README.md](./README.md) (§9 Wünsche & Ideen). Partneranfragen / Grob–Fein / Formular-Typen: [20260823_New_concept.md](./20260823_New_concept.md).

---

## MVP-Ziel (gesamt)

**Chief-intern (gebaut):** Grossanlass anlegen → **Ressort-Baum** → **Formular** mit Bedarfserfassung (`ressort_wuensche`).

**Soll danach (Spec, nicht MVP-DoD):** Tab «Wünsche & Ideen»; Typen Material / Firmenvorschlag / Frei; Beschaffung Anfragen. Gast-Einladungen, Materialübersicht, Leihweise/Fuhrpark und Phasen-Activities folgen später.

---

## Phase 1 — Grundgerüst

**Lieferbar zuerst.** Alles Weitere (Ressorts, Runden, Wünsche) baut darauf auf.

**Implementierung:** Nur zentrale Bausteine erweitern — **keine Doppelspur** ([README §20](./README.md#20-implementierungsprinzipien--keine-doppelspur)).

### Umfang Phase 1

| # | Feature | Spec |
|---|---------|------|
| 1 | **Verwaltung → Abteilungen → Hinzufügen ▼** — «Abteilung hinzufügen» \| «Grossanlass hinzufügen» | README §2.2 |
| 2 | **Wizard:** Name, Anlassdatum von/bis, Organisation, Parent-Dept?, Chief-MW | §2.3 |
| 3 | **`POST /api/departments/grossanlass`** — `is_grossanlass`, Config, auto `anlass`, `planned_event_*` | §2.4 |
| 4 | **Zentrales Grundgerüst** — bestehendes `AppLayout` (TopHeader + Sidebar), Route `/:departmentId/dashboard` | §3.0 |
| 5 | **Platzhalter-Dashboard** — Name, Datum, Badge «Entwurf», Willkommen-Text | §3.0 |
| 6 | **Sidebar-Switch** — kein «Aktivitäten» bei `is_grossanlass`; Dashboard als Home | §3.1 |
| 7 | **Chief-MW: E-Mail + Inbox** mit Link `/{departmentId}/dashboard` | §2.5 |
| 8 | **Dept-Wechsel** — neues Dept im **Profilmenü → Abteilung wechseln** (Dropdown) | §2.5 |

### Nicht in Phase 1

- Ressorts / Bauprojekte
- Planungsrunden / Wünsche
- Material, Materialübersicht
- Freigabe / Gast-Einladungen
- Dashboard-Widgets (Lager, Runden, Teilnehmer)

### DoD Phase 1

- [ ] org/sub/sa: Dropdown «Grossanlass hinzufügen» → Wizard → «Erstellen»
- [ ] Redirect Ersteller nach `/{deptId}/dashboard`
- [ ] Platzhalter-Dashboard zeigt Name + geplantes Datum + «Entwurf»
- [ ] Grossanlass-Dept: Sidebar ohne «Aktivitäten»; gleiche App-Shell wie Pfadi-Dept
- [ ] Chief-MW erhält **E-Mail** und **Inbox-Nachricht** mit Dashboard-Link
- [ ] Chief-MW: neues Dept in **Profil → Abteilung wechseln**; Wechsel zeigt Grossanlass-Sidebar + Platzhalter-Dashboard
- [ ] Membership-API liefert `department.is_grossanlass`
- [ ] Kein paralleles Layout/Notification-System — nur Erweiterung zentraler Bausteine (§20)

### PR1 (Implementierung)

**Keine Doppelspur:** Layout, `E*`-Formulare, Inbox, E-Mail, Router und Dept-Wechsel wie in README §20 — nur Flags, neue Endpoints und conditional UI.

| Teil | Backend | Frontend |
|------|---------|----------|
| Create | `POST …/grossanlass`, Membership MW, E-Mail, Inbox | Wizard, Menü §2.2 |
| Shell | `is_grossanlass` in Memberships | `SidebarNavigation`-Branch, `GrossanlassDashboardView` oder conditional in `DashboardView` |
| Notify | `grossanlass_mw_assigned` Inbox + `sendDepartmentMemberAddedEmail` | Inbox-Zeile mit Navigation |

---

## Phase 2–4 (nach Phase 1)

Details: README §3.4–§3.7, §4, §9, §15.

### Phase 2a — Navigation, Settings & Planungs-Shell (PR2a)

| # | Feature |
|---|---------|
| 1 | Sidebar: **Planung** (`mdi-clipboard-text-outline` → `/{deptId}/planung`) |
| 2 | **Aufgaben** + **Nachrichten** wieder sichtbar (Runden-Inbox §9.0.1) |
| 3 | Route + View `GrossanlassPlanungView`: [`PageShell`](../../frontend/src/components/layout/PageShell.vue), Tabs wie [`SettingsView`](../../frontend/src/views/SettingsView.vue) |
| 4 | Tab «Ressorts & Mitglieder» + «Planungsrunden» mit [`EEmptyState`](../../frontend/src/components/layout/EEmptyState.vue) |
| 5 | **Settings gefiltert** bei `is_grossanlass`: nur Mein Department, Benutzer (MW/DC), optional Zeit/Ort — **kein** Gruppen, Material, Werkstatt, … — [README §3.6](./README.md#36-einstellungen-grossanlass-dept) |
| 6 | i18n: `sidebar.planung`, `grossanlass.planung.*` |
| 7 | **Keine** Routes/Menü für Material / Materialübersicht / Pfadi-`/accounting` |

### Phase 2b — Ressorts & Mitglieder (PR2b)

| # | Feature |
|---|---------|
| 1 | Tab **«Ressorts & Mitglieder»** — Inhalt statt Empty State |
| 2 | Baum-Editor: Ressort, **Unterressort** und **Bauprojekt** (`grossanlass_kind`), **max. 10** Ebenen — Orientierung [`GroupsSettingsView`](../../frontend/src/views/settings/GroupsSettingsView.vue) |
| 3 | Mitglieder pro Knoten (`GroupMembership`) |
| 4 | **Löschen:** nur ohne Members im **gesamten Subtree**; leere Kinder rekursiv; blockiert bei Wunsch-Referenzen |
| 5 | **Unterressort / Bauprojekt:** CM + Mitglieder im Ressort — **jederzeit** §4.2; beim Anlegen **Art wählen** |
| 6 | API: `/api/departments/{id}/grossanlass/groups` (+ members); Felder `kind`, `node_type` |

### Phase 2c — Beschaffung Shell (PR2c)

**Nur Route + leere Seiten** — kein Backend, keine Budget-Zahlen. Spec: [README §3.7](./README.md#37-beschaffung--budget--kosten).

| # | Feature |
|---|---------|
| 1 | Sidebar: **Beschaffung** (`mdi-cart-outline` → `/{deptId}/beschaffung`) |
| 2 | View `GrossanlassBeschaffungView`: [`PageShell`](../../frontend/src/components/layout/PageShell.vue) |
| 3 | Tabs (Übersicht, Bedarf, Offerten, Bestellungen, Erhalten) — je [`EEmptyState`](../../frontend/src/components/layout/EEmptyState.vue) |
| 4 | i18n: `sidebar.beschaffung`, `grossanlass.beschaffung.*` |
| 5 | **Keine** API, **kein** Datenmodell in Phase 2 |

### Phase 3 — Planungsrunden (PR3)

| # | Feature |
|---|---------|
| 1 | Runde anlegen: **Name**, `round_type: ressort_wuensche`, `opens_at`/`closes_at`, **`use_auto_schedule`** (MW wählt) |
| 2 | Status `scheduled` → `open` → `closed`; **mehrere** Runden parallel |
| 3 | Manuell open/close + Auto bei Zeitfenster |
| 4 | Bei **open:** Inbox (+ optional E-Mail) an Ressort-Mitglieder §9.0.1 |

### Phase 4 — Wunschformular (PR4)

| # | Feature |
|---|---------|
| 1 | Formular wie [Google Form Infrastruktur Planung (PFF 27)](https://docs.google.com/forms/d/e/1FAIpQLSfbk4Cvu7fLpnvW_Upu89BziYJlhd6rDF917xGasM1LEq3kGg/viewform) §9.1 |
| 2 | Felder: Ressort, Art, Bezeichnung, Anzahl, **Ort**, Zeitraum, Notizen |
| 3 | Nur in Runde **`open`**; **Autor** darf eigene Zeilen editieren/löschen |
| 4 | CM: aggregierte Liste pro Ressort |
| 5 | API: `…/rounds/{roundId}/wishes` |

### Phase 5 — Beschaffung Inhalt (PR5)

**Nach PR4** (Wünsche). Spec: [README §3.7](./README.md#37-beschaffung--budget--kosten).

| # | Feature |
|---|---------|
| 1 | Wünsche → **Bedarf** bündeln (merge/split) |
| 2 | **Offerten** pro Position; gewählte Offerte → Budget-Soll |
| 3 | Tab **Übersicht:** Soll/Ist gesamt + pro Ressort |
| 4 | **Bestellen** + Kosten erfassen; Status «erhalten» / Teillieferung |
| 5 | API: `…/grossanlass/beschaffung/*` §15 |
| 6 | Später: «erhalten» → Zentrallager-Batch §10 |

### Phase 6+ (nach MVP)

- `round_type: detailplanung` — Feinplanung am Bauprojekt §9.2
- `publish`, Gast-Einladungen, Materialübersicht, …

---

## Lieferumfang MVP (Phase 1 + 2–4)

| # | Feature |
|---|---------|
| 1–8 | Phase 1 § oben |
| 9 | **PR2a:** Sidebar Planung, Planungs-Shell, **Settings gefiltert**, Empty States |
| 10 | **PR2b:** Ressort-Baum (max. 10), Lösch-Regeln |
| 11 | **PR2c:** Beschaffung — Sidebar + Route + Tab-Shell (**leer**, keine API) |
| 12 | Planungsrunde `ressort_wuensche` (Name, Auto-Schedule, mehrere parallel) |
| 13 | Wunsch-Zeilen inkl. **Ort** — Mapping Google Form |
| 14 | Dashboard erweitert: Ressort-Liste, Runden-Status |

---

## Nicht im MVP (gesamt)

- **Beschaffung Inhalt** (Offerten, Bestellungen, Erhalten) — **Phase 5 / PR5**, nicht MVP
- **`detailplanung`**-Rundentyp (Phase 6+) §9.2
- **`publish`** + Gast-Inbox + accept (§7.2, §8)
- Teilnehmer-Depts / Unterlager / Strukturmodi verschachtelt (§5)
- Materialübersicht, Leihweise, Fahrzeug-Stammdaten (§10–11)
- **Pfadi-Buchhaltung** (`/accounting` — Follow-ups, Abschreibung, Gruppenkosten aus Aktivitäten)
- Phasen-Activities, J+S (§6, §12)
- E-Mail/Push bei **Gast**-Einladung (Chief-MW-Benachrichtigung **ist** in Phase 1)

---

## PR-Schnitt (gesamt)

| PR | Inhalt |
|----|--------|
| **PR1** | **Phase 1** — Entry, Wizard, Create, Shell, Platzhalter-Dashboard, MW E-Mail+Inbox, Dept-Wechsel |
| **PR2a** | Sidebar Planung, Route `/planung`, Tabs + Empty States, **Settings §3.6**, Aufgaben/Nachrichten — [README §3.5](./README.md#35-routen--leere-seiten-shell-first) |
| **PR2b** | Ressort-Baum + API |
| **PR2c** | Beschaffung Shell (Route `/beschaffung`, Empty Tabs) — [README §3.7](./README.md#37-beschaffung--budget--kosten) |
| **PR3** | Planungsrunde |
| **PR4** | Wunsch-Formular |
| **PR5** | Beschaffung Inhalt (nach PR4) |

---

## DoD MVP (gesamt)

- [ ] Alle DoD Phase 1
- [ ] Grossanlass-Settings: nur Mein Department + Benutzer (MW/DC); **kein** Gruppen/Material/Werkstatt
- [ ] Beschaffung: Route `/beschaffung` erreichbar, Tabs mit Empty State — **ohne** Backend
- [ ] CM/MW und Ressort-Mitglieder können Baum pflegen (max. 10): **Unterressort** oder **Bauprojekt** unter jedem Knoten wählbar; Löschen nur ohne Members im Subtree
- [ ] CM/MW kann benannte Runde `ressort_wuensche` anlegen, open/close, Auto-Schedule
- [ ] Bei Runden-Start: betroffene Mitglieder erhalten Inbox (optional E-Mail)
- [ ] User trägt Wünsche ein (Google-Form-Felder); Autor editiert/löscht eigene Zeilen in `open`-Runde
- [ ] CM sieht aggregierte Wunschliste pro Ressort

---

## API

**Phase 1:**

| Methode | Pfad |
|---------|------|
| POST | `/api/departments/grossanlass` |

**Phase 2–4:**

| Methode | Pfad |
|---------|------|
| GET/POST/PUT/DELETE | `/api/departments/{id}/grossanlass/groups` | Ressort-Baum; POST/PUT: `kind` = `ressort` \| `teilbereich` §4 |
| POST/DELETE | `…/grossanlass/groups/{groupId}/members` |
| GET/POST/PUT | `…/grossanlass/planung/rounds` |
| POST | `…/planung/rounds/{roundId}/open` |
| POST | `…/planung/rounds/{roundId}/close` |
| GET/POST/PUT/DELETE | `…/planung/rounds/{roundId}/wishes` |

**Phase 5 (PR5):**

| Methode | Pfad |
|---------|------|
| GET | `…/grossanlass/beschaffung/overview` |
| CRUD | `…/grossanlass/beschaffung/lines` (+ quotes, order, received) |

Vollständig: README §15.

---

## Abhängigkeiten

**Prinzip:** [README §20](./README.md#20-implementierungsprinzipien--keine-doppelspur) — erweitern, nicht duplizieren.

- Bestehend: `AppLayout`, `TopHeader`, `SidebarNavigation`, `E*`-Formulare, Router `/:departmentId`, `Department`, `Activity`, `Membership`, `InboxMessageService`, `VerificationEmailService`, `DepartmentModal`-Patterns
- Neu Phase 1: `department.is_grossanlass`, `department_grossanlass_config`, `activity_grossanlass_config`, Inbox-Kategorie `grossanlass_mw_assigned`
- Neu Phase 2–4: `activity_grossanlass_round`, `activity_grossanlass_wish_line`; Inbox `grossanlass_round_opened`
- Neu Phase 5: `activity_grossanlass_procurement_*` §3.7
- Später: `round_type: detailplanung` §9.2
