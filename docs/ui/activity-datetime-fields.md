# Activity Date/Time Fields — Wiederverwendung

**Stand:** Juni 2026 · Bausteine unter `frontend/src/components/activities/wizard/`

Referenz-UI: Sandbox `/{departmentId}/sandbox` → «Aktivität Zeitraum».

---

## Empfohlen: `ActivityDateTimeFields`

Eine Zeile: Datum (ein Tag **oder** Zeitraum) + optional Von/Bis-Uhrzeit. Layout Pill ab `sm`, Mobile 2-zeilig.

```vue
<script setup>
import { ActivityDateTimeFields } from '@/components/activities/wizard'
</script>

<!-- Ein Tag + Uhrzeiten -->
<ActivityDateTimeFields
  v-model:day="day"
  v-model:time-from="timeFrom"
  v-model:time-to="timeTo"
  date-mode="single"
  :department-id="departmentId"
  :show-presets="false"
  :show-markers="true"
  label-from="Von"
  label-to="Bis"
/>

<!-- Zeitraum, mit Schnellauswahl -->
<ActivityDateTimeFields
  v-model:range="range"
  v-model:time-from="timeFrom"
  v-model:time-to="timeTo"
  date-mode="range"
  :department-id="departmentId"
  :show-presets="true"
  :show-markers="true"
  label-from="Von"
  label-to="Bis"
/>

<!-- Nur Datum, kein Uhrzeit -->
<ActivityDateTimeFields
  v-model:day="day"
  date-mode="single"
  :show-time="false"
  label-from=""
  label-to=""
/>
```

| Prop | Default | Bedeutung |
|------|---------|-----------|
| `date-mode` | — | `'single'` \| `'range'` |
| `show-presets` | `false` | Schnellauswahl (Samstage + Lagerwoche/Sonstiges) |
| `show-markers` | `true` | Punkte/Tooltip (Feiertage, fcal, Fixe Daten) |
| `show-time` | `true` | `ActivityTimeField` Von/Bis |
| `department-id` | `null` | Marker + Fixe Daten laden |
| `disabled` / `times-locked` | `false` | Datum / Uhr sperren |
| `blocked-usage-range` | `null` | Material-Uhr: Nutzungszeit blockieren |
| `layout` | `'auto'` | `'auto'` \| `'pill'` \| `'stacked'` |

**v-model:** `day` (single), `range` (range), `timeFrom`, `timeTo`.

---

## Aktivitäten-Wizard / Detail

`ActivityZeitraumDatetimeFields` — Nutzung + Material-Planung inkl. Typ-Logik (Aktivität = ein Tag, Lager/Event = Zeitraum + Presets).

---

## Einzelbausteine (feiner steuern)

| Komponente | Modell | Wichtige Props |
|------------|--------|----------------|
| `ActivityDateField` | `Date \| null` | `show-presets`, `show-markers`, `department-id` |
| `ActivityDateRangeField` | `[Date, Date] \| null` | `show-presets`, `show-markers`, Doppelkalender ab `sm` |
| `ActivityTimeField` | `Date \| null` | `locked`, `blocked-usage-range` |
| `ActivityResponsiveDateTimeRow` | Slots `#date`, `#timeFrom`, `#timeTo` | nur Layout |

Presets bei Zeitraum: nur **Lagerwoche** und **Sonstiges** (+ Samstage), keine Schulferien/Mat-Büro in der Liste.

---

## Kalender-UX (alle Modi)

| Thema | Verhalten |
|-------|-----------|
| Desktop/Tablet Zeitraum | Doppelkalender, Schnellauswahl **rechts** |
| Mobile Zeitraum | Ein Kalender, Schnellauswahl **unten** |
| Navigation | Kopfzeile-Pfeile, Mausrad (vertikal), Touch **links/rechts** wischen |
| Sperre | Nur Tage «Mat-Büro geschlossen» (`department_break`) nicht wählbar |

`show-markers="false"` → keine Punkte, keine Sperre über Fixe Daten.

---

## Siehe auch

- [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) — Übersicht aller wiederverwendbaren Bausteine
- [fixe-daten.md](../setting/fixe-daten.md) — API Fixe Daten, Marker-Arten
- [vuetify-migration-plan.md](./vuetify-migration-plan.md) — Phase 6, Schritte 073–075
