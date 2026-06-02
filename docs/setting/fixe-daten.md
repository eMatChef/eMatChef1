# Fixe Daten

Manuell gepflegte Zeiträume pro Abteilung (Ferien, Lagerwoche, Mat-Büro geschlossen, …) — Ersatz für Schulferien per fcal, wenn fcal ausbleibt oder zu teuer ist. CH-Feiertage bleiben automatisch (`swissMovableFeasts.ts`).

**Stand:** Juni 2026 · **Status:** umgesetzt

---

## Ziel

- Einstellungen: Menü **„Fixe Daten“** zwischen **Join-Code** und **InfoScreen**.
- **Sehen + Verwalten:** nur **MW** und **DC** (`mw`, `matwart`, `dc`, `depchef`) — Menü, Route, API, Kalender-Marker.
- Marker im **Aktivitäten-Datepicker** (`useActivityDatePickerEvents`).
- **fcal** bleibt auf **Meine Abteilung** (`calendar.fcal_geo_id`); nicht auf dieser Seite.

| Quelle | Wo |
|--------|-----|
| CH-Feiertage | berechnet, nicht in DB |
| Schulferien fcal | optional, Meine Abteilung |
| Fixe Daten | `department_calendar_period`, diese Seite |

---

## Tabelle `department_calendar_period`

Ein Eintrag = ein Zeitraum (inklusive `start_date` … `end_date`).

| Spalte | Beschreibung |
|--------|--------------|
| `id`, `department_id` | wie üblich (CHAR 12) |
| `label` | **Art** — fix vorgegebene Kategorie (Select): `school_vacation` · `department_break` · `camp_week` · `other` |
| `name` | **Name** — frei vom User (Text), z. B. «Herbstferien», «Sommerlager Matra» |
| `start_date`, `end_date` | DATE |
| `created_by_user_id`, `created_at`, `updated_at` | optional / Standard |

**API:** `GET|POST /api/departments/{departmentId}/calendar-periods` · `PATCH|DELETE …/{id}`  
**API:** nur MW/DC (GET und Schreiben).

---

## Frontend

- Route: `my-department/fixed-dates` · View: `MyDepartmentFixedDatesView.vue` (Vorbild: `MyDepartmentDisplayScreensView.vue`)
- Liste: Von · Bis · Art (`label`) · Name (`name`) · Bearbeiten/Löschen (nur MW/DC)
- Formular: `start_date`, `end_date`, `label` (Select), `name` (Text)
- i18n: `settings.nav.fixedDates`, `settings.fixedDates.*` (`de.json`, `en.json`)

**Kalender:** Perioden in `useActivityDatePickerEvents` laden (Jahre: aktuell −1 … +2), Tage in Marker-`Set` mergen.  
**Schnellauswahl** im Datepicker-Menü (rechts Desktop / unten Mobile): «Nächster Samstag», «Übernächster Samstag» plus noch nicht abgelaufene Fixe Daten **nur Lagerwoche und Sonstiges** (Art + Name). Schulferien und Mat-Büro geschlossen erscheinen nur als Kalender-Marker, nicht in der Schnellauswahl. Im Create-Wizard nur bei Aktivitätstyp **Lager** und **Event** (`ActivityZeitraumDatetimeFields`).

---

## Checkliste

| # | Aufgabe | Status |
|---|---------|--------|
| 1 | Migration + Entity | [x] |
| 2 | API CRUD + Rechte MW/DC | [x] |
| 3 | Settings-Menü, View, API-Client, i18n | [x] |
| 4 | Datepicker-Marker | [x] |

**DoD:** CRUD für MW/DC; Marker im Aktivitäts-Kalender; fcal unverändert auf Meine Abteilung.
