# Grossanlass — MVP

> Erster lieferbarer Schnitt. Vollständige Spezifikation: [README.md](./README.md).

---

## MVP-Ziel (gesamt)

**Chief-intern:** Grossanlass anlegen → **Ressort-Baum** → **Planungsrunde** mit **Bedarfserfassung**. Gast-Einladungen, Materialübersicht, Leihweise/Fuhrpark und Phasen-Activities folgen später.

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

| Phase | Inhalt | Spec |
|-------|--------|------|
| **2** | Ressorts + Unterressorts (`group.parent_id`) | §4 |
| **3** | Planungsrunde CRUD + open/close | §9 |
| **4** | Wunsch-Formular Bedarfserfassung | §9.1 |

---

## Lieferumfang MVP (Phase 1 + 2–4)

| # | Feature |
|---|---------|
| 1–8 | Phase 1 § oben |
| 9 | Ressorts + Bauprojekte |
| 10 | Planungsrunde `ressort_wuensche` |
| 11 | Wunsch-Zeilen (Ressort, Art, Anzahl, Zeitraum, Notizen) |
| 12 | Dashboard erweitert: Ressort-Liste, Runden-Status |

---

## Nicht im MVP (gesamt)

- **`publish`** + Gast-Inbox + accept (§7.2, §8)
- Teilnehmer-Depts / Unterlager / Strukturmodi verschachtelt (§5)
- Materialübersicht, Leihweise, Fahrzeug-Stammdaten (§10–11)
- Phasen-Activities, J+S (§6, §12)
- E-Mail/Push bei **Gast**-Einladung (Chief-MW-Benachrichtigung **ist** in Phase 1)

---

## PR-Schnitt (gesamt)

| PR | Inhalt |
|----|--------|
| **PR1** | **Phase 1** — Entry, Wizard, Create, Shell, Platzhalter-Dashboard, MW E-Mail+Inbox, Dept-Wechsel |
| **PR2** | Ressort-Baum |
| **PR3** | Planungsrunde |
| **PR4** | Wunsch-Formular |

---

## DoD MVP (gesamt)

- [ ] Alle DoD Phase 1
- [ ] CM kann Ressorts und Bauprojekte anlegen
- [ ] CM kann Planungsrunde öffnen; RL/CM trägt Wünsche ein
- [ ] CM sieht aggregierte Wunschliste pro Ressort

---

## API

**Phase 1:**

| Methode | Pfad |
|---------|------|
| POST | `/api/departments/grossanlass` |

**Phase 2–4:** siehe README §15.

---

## Abhängigkeiten

**Prinzip:** [README §20](./README.md#20-implementierungsprinzipien--keine-doppelspur) — erweitern, nicht duplizieren.

- Bestehend: `AppLayout`, `TopHeader`, `SidebarNavigation`, `E*`-Formulare, Router `/:departmentId`, `Department`, `Activity`, `Membership`, `InboxMessageService`, `VerificationEmailService`, `DepartmentModal`-Patterns
- Neu Phase 1: `department.is_grossanlass`, `department_grossanlass_config`, `activity_grossanlass_config`, Inbox-Kategorie `grossanlass_mw_assigned`
- Neu Phase 2–4: `group.parent_id`, `activity_grossanlass_round`, `activity_grossanlass_wish_line`
