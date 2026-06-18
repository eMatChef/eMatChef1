# Material-Journey UI — Detail-Spezifikation

Technische und UX-Spezifikation für die **neue Material-Oberfläche** in der Aktivität: Screens, Komponenten, Journey-Steps, Scan-Auflösung, Rollen und API-Mapping.

**Stand:** Juni 2026 · **Status:** Spezifikation; Umsetzung Phase 1 offen (parallel zu Legacy)

**Konzept:** [README.md](./README.md)

**Verwandt:** [material-pipeline.md](../material-pipeline.md) · [pack-workflow-rules.md](../pack-workflow-rules.md) · [packStageQuantities.ts](../../../frontend/src/components/activities/packStageQuantities.ts) · [packWorkflowRules.ts](../../../frontend/src/components/activities/packWorkflowRules.ts)

---

## Inhaltsverzeichnis

1. [Scope und Nicht-Ziele](#1-scope-und-nicht-ziele)
2. [Aktivitäts-Tabs](#2-aktivitäts-tabs)
3. [Journey-Steps](#3-journey-steps)
4. [Screens und Layout](#4-screens-und-layout) — inkl. [Parallel-Route](#41-route--parallel-entwicklung), [Mobile-First](#47-mobile-first--design-prinzipien), [Bausteine](#48-zentrale-bausteine-integrationsreihenfolge)
5. [Checklisten-Zeilen](#5-checklisten-zeilen)
6. [Scan und Suche](#6-scan-und-suche)
7. [Sheets: Kiste, Kombi, Menge, Retour](#7-sheets-kiste-kombi-menge-retour)
8. [Rollen und Berechtigungen](#8-rollen-und-berechtigungen)
9. [API- und Daten-Mapping](#9-api--und-daten-mapping)
10. [Frontend-Architektur (Ziel)](#10-frontend-architektur-ziel)
11. [i18n (Vorschlag)](#11-i18n-vorschlag)
12. [Implementierungsphasen](#12-implementierungsphasen)
13. [Abnahme / DoD](#13-abnahme--dod)
14. [Nachlieferungs-Wunsch (Ersteller → MW)](#14-nachlieferungs-wunsch-ersteller--mw)
15. [Presence (Mehrbenutzer im Lager)](#15-presence-mehrbenutzer-im-lager)
16. [Getroffene Entscheidungen](#16-getroffene-entscheidungen)
17. [Detailregeln (Produkt)](#17-detailregeln-produkt)
18. [Nice-to-have Backlog](#18-nice-to-have-backlog)
19. [Klärungsdetails (ehemals offen)](#19-klärungsdetails-ehemals-offen)
20. [Integration & Erweiterungen](#20-integration--erweiterungen)
21. [UX & Technik](#21-ux--technik)
22. [Parität Legacy-Packliste](#22-parität-legacy-packliste--entschieden)

---

## 1. Scope und Nicht-Ziele

### In Scope (v1 Journey-UI)

- **Neue Packliste** als **eigene Route** `pack-journey` — **parallel** zu `ActivityPackListTab` (Tab `packs` bleibt Legacy bis Rollout)
- Einspaltige **Checkliste** pro Journey-Schritt, Stepper ab **`pack`**
- **Mobile-First:** Layout und Komponenten zuerst für Handy (≤768px), Desktop als Progressive Enhancement — [§4.7](#47-mobile-first--design-prinzipien)
- Schrittweise Einbindung **zentraler Bausteine** (shared Rules, APIs, Sheets) — [§4.8](#48-zentrale-bausteine-integrationsreihenfolge), [§12](#12-implementierungsphasen)
- **Scan/Suche** mit Kontext-Karte — **nur** in der Packliste
- **Kisten-** / **Phys.-Kombi-Sheets** und **Retour-Sheet** (gemischte Kisten)
- **Material-Tab:** UI refresh (visuell an Journey angeglichen), **keine** Scan-Logik
- MW: Regal-Gruppierung im Schritt Packen
- Quick- und Logistics-Profil

### Nicht in Scope (v1 Journey-UI)

- Grossanlass ([grossanlass/README.md](../../grossanlass/README.md))
- **Presence** — Phase 12 ([§15](#15-presence-mehrbenutzer-im-lager))
- Vollständiger Ersatz `ActivityPackListTab` am Tag 1 — Legacy bleibt Standard im Tab `packs` bis **Rollout-Phase 13**
- Inventur, Stammdaten
- **`devices.ematchef.ch`** — separates Handheld-/Scanner-Tool ([devices/pack-workflow.md](../../devices/pack-workflow.md)); Journey-UI in `app.` ist eigenständig. Einzelne UX-Ideen (Scan-Feedback, …) dürfen übernommen werden — [§20.9](#209-bezug-zu-devices)
- Material-**Substitution** — Zukunft ([§20.5](#205-material-substitution-zukunft))
- MW-**Aufgaben-Zuweisung** — erst bei mehreren MW / grösserem Betrieb
- **Scan-History** (depübergreifend) — nicht in Aktivitäts-Pack-History — [§20.11](#2011-scan-history-abgegrenzung) · [scan-and-url-history.md](../../qr/scan-and-url-history.md)

### Spätere Phase

- **Nachlieferungs-Wunsch** ([§14](#14-nachlieferungs-wunsch-ersteller--mw)) — Phase 10
- **J+S** als eigener Tab ([js-material/README.md](../js-material/README.md))
- **Benachrichtigungen** Pack fertig / Teilausgabe — [§20.1](#201-benachrichtigungen)
- **History/Audit** Journey — [§20.2](#202-history--audit-pack-journey)
- **Nutzungs-Statistik** (aus Moves, ohne Scans) — [§20.10](#2010-nutzungs-statistik-department)
- **Buchhaltung / External** — [§20.3](#203-buchhaltung--abschluss--external)

---

## 2. Aktivitäts-Tabs

### 2.1 Übersicht

| Tab (`ActivityDetailView`) | Phase | UI v1 |
|----------------------------|-------|--------|
| `overview` | immer | unverändert |
| `material` | `draft` … `approved` (+ readonly später) | **UI refresh**, Funktion gleich |
| `packs` | ab `packing` | **Legacy** — `ActivityPackListTab` (unverändert bis Rollout) |
| `pack-journey` (Route) | ab `packing` | **Journey-UI** — `ActivityMaterialJourneyView` (parallel, Beta) |
| `js` (Ziel) | camp/event | eigener Tab — J+S-Formular & Checks |
| `consumables` / `issues` | ab Event | Übersicht; Aktionen auch in Journey |

**Entscheidung:** **Kein** Journey-Step `plan`. Planen und Einreichen bleiben im Tab **Material**.

### 2.2 Tab Material — UI refresh (ohne Scan)

| Aspekt | Regel |
|--------|--------|
| Komponenten | `ActivityMaterialLinesTable`, `ActivityMaterialAvailabilityLookup` — **bestehend** |
| Suche | Textsuche + Verfügbarkeit im Aktivitätszeitraum — **wie heute** |
| **Kein** Scan | keine `scanParser`-Integration, keine QR-Bar, kein `MaterialScanResultCard` |
| UI-Ziel | gleiche Design-Sprache wie Packliste: `section-card`, Abstände, Listenzeilen, Badges — ohne Stepper |
| Einreichen | Status-Übergänge unverändert (`draft` → `submitted` / `approved`) |
| Implementierung | Phase **1b** (parallel oder nach Journey-Shell) |

### 2.3 Tab Packliste — Legacy (bleibt)

| Aspekt | Regel |
|--------|--------|
| Komponente | `ActivityPackListTab.vue` im `v-tabs-window-item value="packs"` — **unverändert** während paralleler Entwicklung |
| Standard | Produktions-UI für alle User bis Rollout Phase 13 |
| Link zur Journey | MW: «Neue Packliste (Beta)» → Route `pack-journey` |
| Status-Confirms | `packListTabRef` in `ActivityDetailView` bleibt an Legacy gekoppelt bis Confirm-Logik extrahiert ist ([§4.8](#48-zentrale-bausteine-integrationsreihenfolge)) |

### 2.3b Route Pack-Journey — neu (parallel)

| Aspekt | Regel |
|--------|--------|
| Route | `/:departmentId/activities/:activityId/pack-journey/:step?` |
| Komponente | `ActivityMaterialJourneyView.vue` — **eigene View**, nicht im Tab-Slot |
| Stepper | ab `pack` — siehe [§3](#3-journey-steps) |
| Scan | ja — [§6](#6-scan-und-suche) (ab Journey-Phase 4) |
| Zurück | Link «Klassische Packliste» → Tab `packs` |
| Status `draft` … `approved` | Redirect oder readonly Empty-State «MW packt noch» — [§22.10](#2210-frühe-pack-vorschau) |
| Mobile-First | Shell zuerst für Viewport &lt;768px bauen — [§4.7](#47-mobile-first--design-prinzipien) |

### 2.4 Ablauf Ersteller → MW

```
material: Positionen planen → einreichen (approved)
    ↓
packs:         Legacy — MW/Gruppe wie heute (Dual-Panel)
pack-journey:  MW Step pack → Gruppe issue/return → MW store (Ziel-UI)
```

### 2.5 Profil `external` (Vermietung)

| Aspekt | Regel |
|--------|--------|
| Journey | Quick-Profil; **MW bearbeitet alle Steps** — Gruppe readonly ([pack-workflow-rules.md](../pack-workflow-rules.md)) |
| Handoff | **keine** Gruppen-Handoff-Banner (§19.1) |
| **PDF Ausgabe** | bei Ausgabe / Abschluss: PDF «ausgegebene Positionen» — [§20.3](#203-buchhaltung--abschluss--external) |
| **Kosten** | Tab `costs`: `activity_rental`, Positionen bearbeiten, **Rabatt** setzen — nur `type === 'external'` |
| Journey-Hinweis | Step `issue`/`store`: Link Tab Kosten wenn Rental offen |

---

## 3. Journey-Steps

### 3.1 Step-Keys (intern)

**`plan` existiert nicht** in `journeyStepsForProfile`. Planung = Tab Material.

| `journey_step` | Quick (`activity`/`external`) | Logistics (`camp`/`event`) | Pipeline-Übergang |
|----------------|------------------------------|----------------------------|-------------------|
| `pack` | ✓ | ✓ | `confirmed_packed` |
| `transport_out` | — | ✓ | `packed_transport_to` |
| `issue` | ✓ («Ausgabe») | ✓ («Am Anlass») | `packed_at_event` / `transport_to_at_event` |
| `transport_back` | — | ✓ | `at_event_transport_back` |
| `return` | ✓ | ✓ | `at_event_returned` / `transport_back_returned` |
| `store` | ✓ (MW) | ✓ (MW) | `returned_unpack` → `stored` |

Logistics optional: readonly-Anker `at_event` nur als Status-Badge, **nicht** als eigener Stepper-Knoten bei quick.

### 3.2 Aktiver Step vs. Aktivitäts-Status

| `activity.status` | Default `journey_step` (Packliste) |
|-------------------|-------------------------------------|
| `draft` … `approved` | Tab `packs` nicht aktiv / Hinweis → Tab **Material** |
| `packing` | `pack` |
| `packed` | `issue` (Quick) / `transport_out` (Logistics) |
| `at_event` | `return` (oder `transport_back` bei offener Pipeline) |
| `returned` | `store` (MW) / `return` readonly (Gruppe) |
| `completed` | alle readonly |

Automatischer Vorschlag beim Öffnen; User darf **vergangene** Steps nur lesen, **zukünftige** nicht bearbeiten (wie heutige `showPackStageViewOnlyBanner`-Logik).

### 3.3 Mapping `journey_step` → `PackStage`

```typescript
// frontend/src/components/activities/materialJourneySteps.ts

type JourneyStep =
  | 'pack'
  | 'transport_out'
  | 'issue'
  | 'transport_back'
  | 'return'
  | 'store'

function journeyStepsForProfile(profile: PackWorkflowProfile): JourneyStep[]
```

**Quick** — `journeyStepsForProfile('quick')`:

`['pack', 'issue', 'return', 'store']`

**Logistics**:

`['pack', 'transport_out', 'issue', 'transport_back', 'return', 'store']`

Beispiel Quick:

| `journey_step` | `PackStage` |
|----------------|-------------|
| `pack` | `confirmed_packed` |
| `issue` | `packed_at_event` |
| `return` | `at_event_returned` |
| `store` | `returned_unpack` |

Logistics — vollständige Tabelle:

| `journey_step` | UI-Label | `PackStage` |
|----------------|----------|-------------|
| `pack` | Packen | `confirmed_packed` |
| `transport_out` | Transport hin | `packed_transport_to` |
| `issue` | Am Anlass | `transport_to_at_event` |
| `transport_back` | Transport zurück | `at_event_transport_back` |
| `return` | Retour | `transport_back_returned` |
| `store` | Einlagern | `returned_unpack` |

### 3.4 Stepper-Darstellung — dynamisch pro Profil

**Entscheidung:** Nur profil-relevante Steps — **keine grauen Platzhalter**, **kein `plan`**.

| Profil | `journeyStepsForProfile` |
|--------|--------------------------|
| **quick** / **external** | `pack` → `issue` → `return` → `store` |
| **logistics** | `pack` → `transport_out` → `issue` → `transport_back` → `return` → `store` |

- Quick: kein `at_event`-Knoten — nach Ausgabe + Status `at_event` ist `return` nächster bearbeitbarer Step
- Logistics: `issue` = UI «Am Anlass»; Status `at_event` hier setzen
- Logistics: Status `returned` im Step `return`, nicht bei `transport_back`

### 3.5 Camp/Event — Transport

Gleiche Checklisten-Shell pro Transport-Knoten (`transport_out`, `transport_back`). Phase **7**.

**Touren & Fahrzeuge:** Mehrere **Touren** pro Aktivität; Fahrzeuge aus **Department-Fuhrpark** (ausleihbar). Details [§19.3](#193-transport--touren--department-fuhrpark).

### 3.6 Entscheidung `plan` — **weggelassen**

Planung (`activity_item`, Einreichen) = Tab **Material** ([§2.2](#22-tab-material--ui-refresh-ohne-scan)). Journey beginnt bei **`pack`**.

---

## 4. Screens und Layout

### 4.1 Route / Parallel-Entwicklung

| Einstieg | Route / Tab | Komponente | Status |
|----------|-------------|------------|--------|
| **Legacy (Standard)** | Tab `packs` in `ActivityDetailView` | `ActivityPackListTab.vue` | produktiv, unverändert |
| **Journey (Beta)** | `/:departmentId/activities/:activityId/pack-journey/:step?` | `ActivityMaterialJourneyView.vue` | parallel entwickeln |
| Redirect (bestehend) | `…/activities/:id/packlist` | → Tab `packs` (Legacy) | bleibt |
| Deep-Link Journey | `…/pack-journey/issue` etc. | Default-Step aus Status wenn `:step` fehlt | [§3.2](#32-aktiver-step-vs-aktivitäts-status) |

- Query `?tab=packs` → weiterhin **Legacy**
- Journey: eigene Route — **kein** `v-if` alt/neu im gleichen Tab-Slot (weniger Risiko für `packListTabRef`)
- MW-Toggle: «Neue Packliste (Beta)» / «Klassische Ansicht» zwischen den Einstiegen
- Optional Persistenz: `localStorage` `packUiPreference` oder Dept-Setting `pack_ui_mode` — erst ab Rollout relevant

**Rollout (Phase 13):** Tab `packs` zeigt Journey; Legacy nur noch über «Klassische Packliste» / `?packUi=legacy`

### 4.2 Komponenten-Hierarchie (Packliste)

```
ActivityMaterialJourneyView.vue
├── MaterialJourneyStepper.vue
├── MaterialJourneyScanBar.vue
├── MaterialJourneyToolbar.vue      // Fortschritt, Filter-Tabs
├── MaterialJourneyTaskList.vue
│   ├── MaterialJourneyTaskRow.vue  // lose Zeile, Kiste, Set
│   └── MaterialJourneyRegalGroup.vue  // optional Wrapper
├── MaterialJourneyStepFooter.vue     // Primary Workflow-Button
├── MaterialCrateCheckSheet.vue       // Packen / Ausgabe: Kiste
├── MaterialReturnCrateSheet.vue      // Retour: gemischte Kiste (s. §7.5)
├── MaterialComboCheckSheet.vue
├── MaterialQuantitySheet.vue         // Teilmenge
├── MaterialScanResultCard.vue        // nach Scan
├── MaterialReplenishmentWishPanel.vue  // Ersteller: Wunsch + Verfügbarkeit (Phase 10)
├── MaterialReplenishmentWishList.vue   // MW: offene Wünsche (Phase 10)
└── MaterialJourneyLegacyLink.vue       // «Klassische Packliste» → Tab packs
```

### 4.3 `MaterialJourneyToolbar`

| Filter | Sichtbar wenn |
|--------|----------------|
| **Offen** | immer (default) |
| **Erledigt** | immer |
| **Nach Regal** | `journey_step === 'pack'` && MW |

Fortschritt: `doneCount / totalCount` für aktuellen Step — berechnet aus `packWorkflowRules` + `getStageLeftQty` / `getStageRightQty` (gleiche Logik wie `stageProgress`, aber **Positionen** statt Mengen-Prozent optional beides).

### 4.4 Status vs. Journey — **entschieden**

| UI-Ort | Verantwortung |
|--------|----------------|
| **Kopfzeile** `ActivityDetailView` | Aktivitäts-**Status**: `at_event`, `returned`, `completed`, Stornieren |
| **Journey** Route `pack-journey` | **Mengen** / Pipeline: packen, ausgeben, retournieren, einlagern |

- Journey-**Footer**: kein Status-Button — höchstens Hinweis «Noch {n} Positionen bis Status … möglich»
- Status-Confirm (`usePackWorkflowConfirm`) bleibt an Kopfzeile gekoppelt; Journey liefert `stageProgress` / Pending-Liste

### 4.5 `MaterialJourneyStepFooter`

| Step | Footer (Journey) |
|------|------------------|
| `issue` | Hinweis Teilausgabe / offene Positionen — **kein** Status-Knopf |
| `return` | Hinweis offene Retour — **kein** Status-Knopf |
| `store` | Link zu Completion-Checkliste — **kein** «Abgeschlossen»-Knopf (Status in Kopfzeile) |

### 4.6 Responsive (Progressive Enhancement)

**Ausgangspunkt Mobile** — siehe [§4.7](#47-mobile-first--design-prinzipien). Desktop erweitert, ersetzt nicht.

| Viewport | Kisten-Sheet | Stepper | Liste |
|----------|--------------|---------|-------|
| **&lt; 768px (Primary)** | **Vollbild** | horizontal, scrollbar | einspaltig, volle Breite |
| ≥ 768px | Side Sheet oder Dialog breit | horizontal | einspaltig (kein Dual-Panel) |
| ≥ 1200px | wie Tablet | optional vertikal links (240px) | optional Regal-Spalte |

### 4.7 Mobile-First — Design-Prinzipien

Die Journey-UI wird **von Mobile her** entworfen und implementiert — Gruppe und MW nutzen Packen/Ausgabe primär am Handy.

| Prinzip | Regel |
|---------|--------|
| **Referenz-Viewport** | 375×667 (iPhone SE) als Design-Baseline; testen bis 320px |
| **Eine Spalte** | Kein Dual-Panel, keine feste Sidebar — Checkliste immer vertikal |
| **Sticky Scan-Bar** | Oben fixiert; darunter scrollbare Liste |
| **Touch 44px** | Alle Primary-Aktionen, Zeilen-Tap, Filter-Chips — min. 44×44px ([§13](#13-abnahme--dod)) |
| **Sheets = Vollbild** | Default auf Mobile; Side Sheet erst ab `md` Breakpoint |
| **Thumb Zone** | Primary-Buttons unten sticky (`MaterialJourneyStepFooter`) |
| **Weniger Ebenen** | Max. 2 Ebenen sichtbar: Liste → Sheet (kein Modal-in-Modal) |
| **Typografie** | Lesbar ohne Zoom: Body ≥16px, Zeilenhöhe großzügig |
| **Desktop** | Mehr Platz = breitere Sheets, optional vertikaler Stepper — **keine** andere Informationsarchitektur |

**Review-Rhythmus:** Jede Implementierungsphase zuerst auf Mobile abnehmen, dann Desktop smoke-test.

### 4.8 Zentrale Bausteine — Integrationsreihenfolge

Neue UI **bindet bestehende Logik ein** — nicht duplizieren. Reihenfolge = Implementierungsphasen [§12](#12-implementierungsphasen).

| # | Baustein | Ort | Wann (Phase) | Rolle in Journey |
|---|----------|-----|--------------|------------------|
| 1 | **`materialJourneySteps.ts`** | neu | 1 | Step-Keys, Labels, Profil-Mapping quick/logistics |
| 2 | **`packWorkflowRules.ts`** | bestehend | 1–2 | Sichtbarkeit, Handoff, Kistencheck-Regeln |
| 3 | **`packStageQuantities.ts`** | bestehend | 2 | `getStageLeftQty` / `getStageRightQty` → offen/erledigt |
| 4 | **Pack-Items API** | bestehend | 1–2 | `GET pack-items`, reload — gleiche Calls wie Legacy |
| 5 | **`materialJourneyTaskList.ts`** | neu | 2 | Items → Zeilen (`loose`, `crate`, `combo`, …) |
| 6 | **Move-API** | bestehend | 2 | `POST` pack move — gleiche Payloads |
| 7 | **`MaterialCrateCheckSheet`** | neu | 3 | Kiste/virt. Kombi — Logik aus `packShellCrateHelpers` |
| 8 | **`MaterialReturnCrateSheet`** | neu | 6 | Retour-Stapel — äquivalent `PackReturnCrateModal` |
| 9 | **`scanParser.ts`** | bestehend | 4 | URL/Rohcode → `material_batch` / `activity` |
| 10 | **Scan-Resolve** | neu Composable | 4 | Lookup + Ergebnis-Matrix §6.3 |
| 11 | **`usePackWorkflowConfirm`** | extrahiert aus Legacy | 2+ | Status-Confirms Kopfzeile — shared mit Journey |
| 12 | **`ActivityDetailView` Header** | bestehend | 2+ | Status-Buttons; Journey liefert `stageProgress` |
| 13 | **Live-Sync / Polling** | neu Composable | 4–5 | §22.9 — gleiche Intervalle wie Legacy |
| 14 | **i18n** `activities.materialJourney.*` | neu | fortlaufend | [§11](#11-i18n-vorschlag) |

**Regel:** Pro Phase **höchstens 1–2 neue UI-Komponenten** + Anbindung an bestehende Bausteine — kein Big-Bang.

---

## 5. Checklisten-Zeilen

### 5.1 Zeilen-Typen

| `kind` | Quelle | Liste «Offen» wenn |
|--------|--------|-------------------|
| `loose` | `ActivityPackItem` | `getStageLeftQty(item, stage, profile) > 0` |
| `crate` | `ActivityPackContainer` | Container hat links noch Einheiten für `stage` |
| `combo` | `ActivityPackItem` (phys. combo) | wie `loose` + ggf. Check pending |
| `virtual_crate` | logischer Container (`pack_mode together`) | wie `crate` — **eine Zeile**, kein Kind in Liste |
| `self_provided` | Activity items | Hinweis-Block, kein move |
| `replenishment` | `activity_item.is_replenishment` | wie `loose`, Badge «Nachlieferung» |
| `not_taken` | Pipeline «nie ans Event» | eigene Zeile, Badge «Nicht mitgenommen», Gruppe + MW sichtbar |

### 5.2 Block «Material nachbuchen»

Eigener Abschnitt in der Journey (MW ab `pack`; Gruppe ab `at_event` über Nachlieferung/Wunsch).

| Wer | UI | API |
|-----|-----|-----|
| **MW** | Block «Material nachbuchen» — Suche (wie Packliste heute `addMaterial`) | bestehende Add-Material-API |
| **Gruppe** | ab `at_event`: Nachlieferung / Wunsch (§14) | `replenishment: true` / Wunsch |

**Wenn Aktivität schon `packed` / `at_event`:** Modal **vor** Speichern:

1. **Wer holt?** `leader_pickup` | `mw_delivers` | `mw_transport` (Logistics)
2. **Pipeline-Step / Ziel:** z. B. `pack`, `issue`, `transport_out` → setzt `replenishment_pack_stage`

MW-Entscheidung steuert, ob Leiter selbst abholt oder MW bringt/transportiert.

### 5.3 Virt. Kombi & `self_provided`

**Virt. Kombi `together`:** genau **eine** Zeile «logische Kiste» (Name = Komboname). Stock-Komponenten **nur** im Sheet ([virtual-combo-activities.md](../../material/combos/virtual-combo-activities.md)). Sheet = **`MaterialCrateCheckSheet`**.

**Virt. Kombi `loose`:** **N** Zeilen pro Kind-Material; MW organisiert Kiste selbst.

**`self_provided`:** Block «Mitbringen»; am Step **`issue`**: Bestätigung «Selbst mitgebracht?» (Checkbox) — Organisierung war Planungs-Sache.

**Erledigt:** Gegenstück `getStageRightQty > 0` oder vollständig für Step.

Filter: `materialJourneyTaskList.ts` → `packWorkflowRules.ts` (keine Duplikation).

### 5.4 Zeilen-Inhalt

| Feld | Anzeige |
|------|---------|
| Titel | `materialName` oder Container-Label |
| Subtitle | Regal (`storage_location`), «8 Teile», «Set» |
| Status | ○ ◐ ✓ |
| Badge | Verbrauch, J+S, Nachlieferung |
| Primary | kontextabhängig (s. README) |

### 5.5 Aktion «Alles auf einmal»

Lose Zeile: ein Tap bucht `maxForwardQty` für aktuellen `PackStage` (wie grüner Pfeil heute).

Kiste: Sheet-primary bucht Container-Bulk (wie «Alles von dieser Kiste ans Event» / `issueCrateAll`).

### 5.6 Sortierung

| Modus | Sortierung |
|-------|------------|
| Default | Kategorien (wie `groupsLeft`), Kisten-Block vor/nach lose (Kisten zuerst bei `issue`) |
| Nach Regal | `storage_location` ASC, dann Name |
| Erledigt | Zeit optional später; v1: alphabetisch |

**Tendenz Ausgabe:** Kisten oben, dann lose — [README §6](./README.md#6-checklisten-statt-dual-panel).

### 5.7 Nachlieferung (`is_replenishment`)

**Entscheidung:** Erscheint als neue **○-Zeile** in «Offen» — MW packt/bucht wie normale Bestellung, **kein** eigener Screen.

| Aspekt | Regel |
|--------|--------|
| Auslöser | MW bucht Nachlieferung (`replenishment: true`) oder Verbrauchsmaterial-Nachbuchung ab «Am Event» (bestehende API) |
| Darstellung | Badge **«Nachlieferung»**, Subtitle z. B. «Hinzugefügt am …» |
| Sortierung | Block **unter Kisten**, vor übrigem losem Bestand |
| Scan | Ergebnis `replenishment` → [Packen]/[Mitnehmen] je aktivem Step |
| Pipeline | `replenishment_pack_stage` wie heute — Zuwachs auf Stufe des **aktiven** Journey-Steps ([material-pipeline.md](../material-pipeline.md)) |
| Quick-Sonderfall | Wenn `packed < ordered` wegen Nachlieferung: Banner «Zuerst unter Packen» oder Auto-Fokus Step `pack` |

Unterschied zu [§14 Nachlieferungs-Wunsch](#14-nachlieferungs-wunsch-ersteller--mw).

### 5.8 Verbrauch / Verlust / Reparatur (Journey)

| Thema | UI in Journey | Zusätzlich |
|-------|---------------|------------|
| **Verbrauch** | Kisten-Sheet / Zeile «Verbrauch buchen»; Camp/Event kein Auto-Modal | Tab `consumables` |
| **Verlust / Reparatur** | Zeile ⋮ oder Kisten-Sheet; Phys.-Kombi: Komponente wählen | Tab `issues` |
| **Sichtbarkeit** | ab «Am Event» bis Retour — `packWorkflowRules.issuesVisible` | — |

Am Handy: Aktionen in Sheet/⋯-Menü, nicht drei Buttons pro Zeile.

---

## 6. Scan und Suche

**Gilt nur für Tab Packliste** — Tab Material: [§2.2](#22-tab-material--ui-refresh-ohne-scan).

### 6.1 Eingabe

- Textfeld «Suchen oder scannen» (sticky)
- Parser: bestehend `scanParser` / QR-URL `…/i/m/{mat}/b/{batch}`
- Hardware-Scanner (Enter): gleicher Pfad wie `DevicesScanCapture`

### 6.2 Auflösung

```
Scan/Suche
  → Parser: kanonische URL …/i/m/{materialCode}/b/{batchCode} ([link-schema.md](../../qr/link-schema.md))
  → optional GET /api/public/lookup/m/{mat}/b/{batch}
  → materialId + batchId + tracking_type
  → GET pack-items (bereits geladen) + Match
  → falls kein Match: GET Material-Suche innerhalb Aktivität
  → MaterialScanResultCard
```

Optional später: `GET /api/activities/{id}/material/resolve-scan?code=…`

**QR-Ableitung:** [§6.6](#66-qr--charge-ableitung)

### 6.3 Ergebnis-Matrix

| `result` | Bedingung | UI | Aktionen |
|----------|-----------|-----|----------|
| `not_on_list` | kein `activity_pack_item` | rot | — |
| `wrong_activity` | Item gehört anderer Activity | gelb | Link wechseln |
| `not_ready` | ordered, nicht packed | grau | «MW packt noch» |
| `wrong_batch` | Material auf Liste, andere Charge | orange | Hinweis erwartete Charge |
| `serialized_mismatch` | `tracking_type=serialized`, Charge ≠ verknüpft | rot | kein Move |
| `bulk_wrong_batch` | `tracking_type=bulk`, andere Charge | orange | **[Trotzdem]** nach Confirm |
| `in_repair` | Batch/Material in Werkstatt | orange | Badge «Werkstatt», kein Hard-Block v1 |
| `crate_shell` | Charge = Packkisten-Shell (`container_batch_id`) | blau | [Kiste öffnen] |
| `in_crate` | qty in container, step pack/issue | blau | [Kiste öffnen] |
| `loose_ready` | links > 0, lose | grün | [Packen]/[Mitnehmen] |
| `combo_check` | phys. combo, check pending | orange | [Set prüfen] |
| `already_done` | nur rechts | grün blass | «Bereits erledigt» |
| `replenishment` | `is_replenishment` | blau | [Packen]/[Mitnehmen] |
| `in_virtual_crate` | Kind-Material, Parent `pack_mode together` | blau | «Gehört zu Set «{name}»» → [Set öffnen] |

### 6.4 Textsuche

Filter auf `materialName`, Container-Name, Kategorie — scrollt zu Zeile oder zeigt gefilterte Liste.

### 6.5 Fehler & Konflikte

| Situation | Verhalten |
|-----------|-----------|
| **Netzwerkfehler** bei Scan/move | Toast/Snackbar: was fehlgeschlagen ist + **[Wiederholen]** — **keine** optimistische UI für Moves |
| **Gleichzeitiger Scan** derselben Zeile | Dialog: «{User B} hat gerade {Material} gebucht — trotzdem fortfahren?» — letzter `move` gewinnt (API Source of Truth) |
| **Presence light** (v1+) | Unter Scan-Bar: «{Name} · Regal {shelf}» — siehe [§15](#15-presence-mehrbenutzer-im-lager) |
| **Keine Berechtigung** Tab `packs` | Redirect + Audit — [§8.3](#83-unberechtigter-zugriff) |

### 6.6 QR & Charge-Ableitung

Kanonische Etiketten-URL ([link-schema.md](../../qr/link-schema.md)):

```text
https://qr.ematchef.ch/i/m/{materialCode}/b/{batchCode}
```

| `tracking_type` | QR-Bedeutung | Journey-Regel |
|-----------------|--------------|---------------|
| **serialized** | 1 Charge = 1 Stück = 1 QR | Scan muss **exakt** diese Charge sein (`linkedContainerBatchId`) — `serialized_mismatch` |
| **bulk** | 1 QR pro Charge (nicht pro Stück) | Material + Charge identifizierbar; andere Charge → `bulk_wrong_batch` + **Confirm** |

**Aus Scan ableitbar:**

| Daten | Nutzen |
|-------|--------|
| `material_id`, `batch_id` | Pack-Zeile, Move |
| `tracking_type` | strikt vs. warnen |
| Regal / Fach | Packen, Einlagern |
| Charge = Kisten-Shell | `crate_shell` → Container öffnen |
| Werkstatt-Bestand | `in_repair` — §22.8 |
| Kein Etikett | MW → Label anstossen ([§20.8](#208-etikett-beim-packen)) |

Etiketten-Druck / Hub: [label-fulfillment.md](../../future/label-fulfillment.md).

---

## 7. Sheets: Kiste, Kombi, Menge, Retour

### 7.1 `MaterialCrateCheckSheet` (Packen / Ausgabe)

**Props:** `containerId`, `journeyStep`, `leg: outbound | return | warehouse_store`

| Zeile | UI | Aktion |
|-------|-----|--------|
| Container-Item | Checkbox + Name + Soll | Toggle bucht Teil-menge oder markiert Check |
| Verbrauch (Retour) | wie `PackReturnCrateModal` | Button «Verbrauch buchen», kein Checkbox |

**Inhalt — Accordion Fix / Zusatz** ([§22.2](#222-fix-vs-zusatz-accordion)):

- Abschnitte: **Aus Packliste** → **Fix** → **Zusatz** (Logik `packShellCrateHelpers` / `subsectionKey`)
- **Standard eingeklappt**; Summary: «Fix 2/5 offen»
- Primary-Actions am Sheet-Ende immer sichtbar

**Primary:**

| Step | Label |
|------|-------|
| `pack` | «Kiste als gepackt markieren» |
| `issue` | «Kiste mitnehmen» |
| `return` | «Kiste retournieren» |
| `store` | «Kiste einlagern» |

**MW, Step `pack`:** Zeile ⋮ → **«Aus Kiste nehmen»** (`packCrateShowPullFromContainer` / assign-up) — [§22.4](#224-aus-kiste-zurückholen).

Backend: bestehende Container-Controller + `pack_crate_check` mit `leg` aus `packCrateCheckLeg.ts`.

#### 7.1.1 Shell (Kisten-Hülle)

Parität [pack-workflow-rules.md §6](../pack-workflow-rules.md):

| Regel | Journey |
|-------|---------|
| Shell **nicht** doppelt in Kategorie-Liste | Platzierung wie `packWorkflowRules` |
| **Einlagern** mit Inhalt | Inhalt zuerst ✓; **Shell-Zeile unten**, «Hülle einlagern» erst wenn Inhalt erledigt |
| **Leere Hülle** retour | Shell sofort einlagerbar |
| **Packen** | Shell / `container_batch_id` wie `PackCrateShellForwardModal` |

### 7.2 `MaterialComboCheckSheet`

Phys.-Kombi: BOM-Zeilen aus Peek/Sections (wie `PackCrateShellInlinePanel` / `PackPhysComboStoreChecklistModal`).

| Step | Verhalten |
|------|-----------|
| `pack` | alle Komponenten ✓ → Set `quantity_packed` |
| `issue` | outbound-Check |
| `store` | warehouse_store — Button «Komplettes Set ins Lager» wenn alle ✓ |

### 7.3 `MaterialQuantitySheet`

Teilmenge: Slider oder +/- , Max = `packIssueForwardMax` / `maxForwardQty` für aktuellen Stage.

### 7.4 Virt. Kombi `together`

Wie §5.1 — `MaterialCrateCheckSheet`.

### 7.5 `MaterialReturnCrateSheet` (Retour)

**Nicht** dasselbe Sheet wie Ausgabe. Basiert auf `PackReturnCrateModal.vue`.

| Bereich (`placement`) | Bedeutung |
|-----------------------|-----------|
| `in_crate` | mit Kiste retourniert |
| `loose` | retourniert, aber lose |
| `added` | physisch in Kiste, war woanders eingeplant — **gemischte Kisten** |
| `shell` | leere Hülle |

| Rolle | Modus |
|-------|--------|
| Gruppe | leicht: Checkboxen, kein Workshop-Ticket |
| MW | voll: Verbrauch, Verlust/Reparatur, Material zur Kiste hinzufügen |

Submit blockiert bei offenem Verbrauch in der Kiste (wie heute).

Lose Retour-Positionen: eigene Zeilen in der Offen-Liste, nicht im Kisten-Sheet.

**Retour-Stapel** ([§22.5](#225-retour-stapel)): ein Sheet, interne Queue wie `pendingReturnCrateBatch` — Header «Schritt 2 von 5», Sheet schliesst nicht zwischen Teilschritten.

### 7.6 `MaterialStoreShelveSheet` (Einlagern)

MW-only, Step `store`. Basiert auf heutigem Einlagern, aber als geführtes Sheet statt Dual-Panel.

| Einstieg | Ablauf |
|----------|--------|
| **Scan** Artikel | Resolve → Sheet mit Soll-Regal + Fach |
| **Tap** Zeile «Verräumen» | gleiches Sheet |

| Aktion | Ergebnis |
|--------|----------|
| Button **«Verräumt»** | `move` → `quantity_stored` mit Ziel-Regal/Fach |
| Scan **Regal → Fach** | gleiche Buchung ohne Button-Tap |

**Feedback (UX):**

1. Toast «Artikel verräumt» — verschwindet nach ~3 s
2. Sticky unten: **Ja** / **Nein** — **Ja** mit Countdown-Rahmen (~5 s), Default-Fokus Ja → nächster Artikel
3. **Nein** → Sheet bleibt offen (Fach korrigieren)

**Abschluss:** Wenn Retour vollständig eingelagert, aber Meldungen offen (defekt/fehlend) → Hinweis + Link Tab **Kosten** ([§17.13](#1713-kosten-buchhaltung--abschluss)).

### 7.7 Kiste anlegen / zuordnen (`MaterialAddCrateSheet`)

MW, Step `pack` — Toolbar **«In Kiste»** ([§17.3](#173-kisten--anlegen-zuordnen-zusammen)):

| Option | Aktion |
|--------|--------|
| **Kiste scannen** | QR Batch → `container_batch_id` |
| **Aus Liste** | `addContainerBatchOptions` / `GET container-batches` |
| **Neue Kiste** | wenn Dept-Regel erlaubt |

Danach Material zuordnen (Scan, Intent-Gruppe §19.2). Mehrere Chargen: **Batch-Picker** vor Zuordnung.

---

## 8. Rollen und Berechtigungen

Quelle unverändert: `canManageMaterials`, `packWorkflowRules.tabs` / `canEdit`, `packMwIsActivityCreator`, Gruppen-Scope.

| Aktion | Leiter | MW |
|--------|--------|-----|
| `pack` step bearbeiten | nein (readonly) | ja |
| `issue` / `return` | ja | ja (Notfall + Confirm) |
| `store` | nein | ja |
| Kistencheck voll | nein | ja (return/store) |
| Kistencheck leicht | ja (return) | ja |
| «Klassische Packliste» / Beta-Link | nein | ja (beide Richtungen bis Rollout) |
| Status `at_event` / `returned` | ja | ja mit MW-Confirm |
| `moveback` | nein | ja (Confirm bei Kiste/Set) |

Readonly-Banner: ein Banner max., Priorität: Kistencheck pending &gt; Teilausgabe &gt; Handoff — Matrix [§19.1](#191-handoff-banner--readonly-matrix).

### 8.1 Partner-Departments

| Regel | Verhalten |
|-------|-----------|
| MW bearbeitet | nur Positionen **eigenes Department** (`activity_item.department_id` = User-Dept) |
| Fremde Departments | **sichtbar** (readonly), Badge «{Dept-Name}» |
| Buchungen | `403` serverseitig; UI: Zeilen disabled, kein Scan-Move |
| Gruppe | unverändert — sieht aggregierte Liste, bearbeitet nur erlaubte Steps |

### 8.2 Gast-Einladung / Gruppen-Scope

Bestehende Regeln aus `ActivityDetailView` / Gruppen-Scope — **keine** neue Journey-Logik v1. Siehe [§17.9](#179-gast-einladung).

### 8.3 Unberechtigter Zugriff

User ohne Pack-Recht öffnet `/packs` oder `…/packs/:step`:

| Schritt | Verhalten |
|---------|-----------|
| Client | Redirect zur Aktivitäts-Übersicht + Toast «Keine Berechtigung» |
| Server | `403` auf Pack-APIs (wie heute) |
| Audit | Eintrag `security.unauthorized_pack_access` (user, activity, step, IP, UA) |
| Benachrichtigung | Inbox an Org-Admins + Dept-MW der Aktivität — Details [§19.4](#194-unberechtigter-zugriff--audit-vs-benachrichtigung) |

---

## 9. API- und Daten-Mapping

Keine neuen Pflicht-Endpoints für v1. Bestehende Nutzung:

| UI-Aktion | API |
|-----------|-----|
| Liste laden | `GET /api/activities/{id}/pack-items` |
| Container | `GET /api/activities/{id}/pack-containers` |
| Fortschritt | `GET /api/activities/{id}/pack-progress` |
| Lose bewegen | `POST …/pack-items/{id}/move` + `stage` |
| Zurück | `POST …/pack-items/{id}/moveback` |
| Container bulk | `ActivityPackContainerController` (bestehend) |
| Kistencheck | `POST …/pack-crate-check` (bestehend) |
| Status | `POST …/transitions` |
| Verbrauch | Consumption-Endpoints wie heute |
| Abschluss-Blocker | `GET …/transitions` Blocker-Liste |
| Nachlieferungs-Wünsche (Phase 10) | `GET/POST …/replenishment-wishes` — [§14](#14-nachlieferungs-wunsch-ersteller--mw) |

### 9.1 Composables

| Neu | Nutzt |
|-----|-------|
| `useMaterialJourney.ts` | Step aus URL/Status, `packWorkflowProfile` |
| `useMaterialJourneyTasks.ts` | `packWorkflowRules`, `packStageQuantities`, `packStageQuantityLayer` |
| `useMaterialScanResolve.ts` | `scanParser`, Pack-Items |
| `useMaterialJourneyConfirm.ts` | `usePackWorkflowConfirm` |
| `useReplenishmentWishes.ts` | Availability-Lookup, Wunsch-CRUD (Phase 10) |

### 9.2 Was nicht dupliziert wird

- `packWorkflowRules.ts` — Sichtbarkeit, Kistencheck-Regeln
- `packStageQuantities.ts` — left/right qty
- `PackPipelineService.php` — Server

---

## 10. Frontend-Architektur (Ziel)

### 10.1 Parallel-Struktur

```
frontend/src/
  views/
    ActivityPackJourneyView.vue          # Route pack-journey (Wrapper, lädt Activity)
  components/activities/
    ActivityPackListTab.vue              # Legacy — Tab packs, UNVERÄNDERT bis Phase 13
    ActivityMaterialJourneyView.vue      # Journey-Hauptkomponente
    materialJourney/
      MaterialJourneyStepper.vue
      MaterialJourneyScanBar.vue
      …                                  # siehe §4.2
    materialJourneySteps.ts
    materialJourneyTaskList.ts
    packWorkflowRules.ts                 # SHARED — Legacy + Journey
    packStageQuantities.ts               # SHARED
```

- **Kein** Ersetzen von `ActivityPackListTab` im Tab-Slot bis Rollout
- Journey-View: eigene Route, kann schlanker `ActivityDetailView`-Header nutzen (Zurück, Name, Status) oder eingebettet mit minimalem Chrome
- Link Legacy ↔ Journey: `MaterialJourneyLegacyLink` / Beta-Banner in beiden Richtungen

### 10.2 Shared Composables (schrittweise)

| Composable | Phase | Quelle |
|------------|-------|--------|
| `useMaterialJourneyData` | 1 | pack-items laden, step aus Route/Status |
| `useMaterialJourneyTasks` | 2 | `materialJourneyTaskList` + `packWorkflowRules` |
| `usePackWorkflowConfirm` | 2 | extrahiert aus `ActivityPackListTab` |
| `useMaterialJourneyScan` | 4 | `scanParser` + Resolve |
| `useMaterialJourneySync` | 4–5 | Polling §22.9 |

`ActivityPackListTab.vue` bleibt bis Phase 13; MW-Link «Klassische Packliste» in Journey.

**Devices:** `useDevicesPackSession` / `useDevicesPackHin` können `materialJourneySteps` für Stage-Labels teilen.

---

## 11. i18n (Vorschlag)

Prefix: `activities.materialJourney.*`

| Key | DE (Beispiel) |
|-----|----------------|
| `step.pack` | Packen |
| `step.issue` | Ausgabe |
| `step.transport_out` | Transport hin |
| `step.transport_back` | Transport zurück |
| `step.issueLogistics` | Am Anlass |
| `replenishment.badge` | Nachlieferung |
| `replenishmentWish.title` | Nachlieferung wünschen |
| `replenishmentWish.availability` | Im Lager: {n} verfügbar |
| `replenishmentWish.unavailable` | Aktuell nicht verfügbar |
| `replenishmentWish.submit` | Wunsch an Materialwart senden |
| `replenishmentWish.mwQueue` | Offene Nachlieferungs-Wünsche |
| `presence.userOnShelf` | {name} · {shelf} |
| `step.return` | Retour |
| `step.store` | Einlagern |
| `filter.open` | Offen |
| `filter.done` | Erledigt |
| `filter.byShelf` | Nach Regal |
| `scan.placeholder` | Suchen oder scannen… |
| `scan.notOnList` | Nicht für diesen Anlass bestellt |
| `crateSheet.title` | {name} |
| `footer.issueToEvent` | Wir fahren los |
| `footer.partialHint` | {n} Positionen bleiben im Lager |
| `legacyPackLink` | Klassische Packliste |
| `journeyBetaLink` | Neue Packliste (Beta) |

| `materialTab.searchOnly` | Material suchen… |

Prefix Material-Tab refresh (optional geteilt): `activities.materialTab.*`

---

## 12. Implementierungsphasen

**Strategie:** Legacy im Tab `packs` unangetastet · Journey auf Route `pack-journey` · **Mobile-First** · Bausteine schrittweise [§4.8](#48-zentrale-bausteine-integrationsreihenfolge).

| Phase | Deliverable | Bausteine | Mobile-DoD | Status |
|-------|-------------|-----------|------------|--------|
| **0** | README + SPEC | — | reviewed | ✓ |
| **1** | Route `pack-journey`, Shell, Stepper, leere Liste | `materialJourneySteps`, pack-items GET, Link Legacy↔Beta | 375px: Stepper + Empty-State + Zurück-Link | ✓ |
| **1b** | Tab `material`: UI refresh | Styles shared mit Journey | Mobile Lesbarkeit, kein Scan | ✓ |
| **2** | Lose Zeilen: `pack` + `issue` (quick) | `packWorkflowRules`, `packStageQuantities`, `materialJourneyTaskList`, move-API | Tap Zeile, Primary unten, move funktioniert | ✓ |
| **3** | `MaterialCrateCheckSheet` + Combo | `packShellCrateHelpers`, Container-API | Vollbild-Sheet, Accordion Fix/Zusatz | ✓ |
| **4** | Scan-Bar + Resolve | `scanParser`, Scan-Composable, Feedback-Banner | Sticky Scan, Grün/Rot, Letzte Scans Session | ✓ |
| **5** | Regal-Gruppierung MW | Filter «Nach Regal», `MaterialJourneyRegalGroup` | Filter-Chips touchbar | ✓ |
| **6** | `return` + `store` + Retour-Sheet | `MaterialReturnCrateSheet`, `MaterialStoreShelveSheet` | Retour-Stapel Vollbild | ✓ |
| **7** | Logistics Steps + Transport | Touren, Dept-Fuhrpark §19.3 | Stepper logistics auf Mobile | ✓ |
| **8** | History-Audit UI | Pack-Events §20.2 | Aggregation lesbar auf Mobile | ✓ |
| **8b** | Benachrichtigungen (Inbox) | §20.1 | Deep-Link → `pack-journey` | ✓ |
| **9** | J+S eigener Tab | js-material Spec | — | ✓ |
| **9b** | Nutzungs-Statistik (Moves) | §20.10 | — | ✓ |
| **10** | Nachlieferungs-Wunsch | §14 | Panel unter Scan-Bar | ✓ |
| **11** | Kisten-Intent «Zusammen» | §19.2 | — | ✓ |
| **12** | Presence light + Konflikt | §6.5, §15 | — | ✓ |
| **13** | **Rollout** | Tab `packs` → Journey default; Legacy «Klassische Packliste» | Toggle / `?packUi=legacy` | ✓ |

**Pro Phase:** nur die genannten Bausteine — keine Vorwegnahme späterer Sheets/Features.

**Abnahme je Phase:** zuerst Mobile (375px), dann Tablet/Desktop smoke-test.

---

## 13. Abnahme / DoD

### Funktional Quick (`activity`)

- [ ] Leiter: Ausgabe komplett ohne Dual-Panel (lose + Kisten)
- [ ] Leiter: Scan zeigt korrekten Kontext für bestelltes Material
- [ ] MW: Packen mit Regal-Sort und Kisten-Sheet
- [ ] Teilausgabe + Status `at_event` mit Confirm
- [ ] Retour + Einlagern MW
- [ ] Kistencheck outbound/return/store wie `pack-workflow-rules`
- [ ] Phys.-Kombi Set-Checkliste pack + issue + store
- [ ] Keine Doppelanzeige virt. Kombi / Kiste
- [ ] Pipeline-Sperren / Verfügbarkeit unverändert korrekt

### Nicht-Regression

- [ ] `external` nur MW bearbeitet
- [ ] Gruppe readonly ab korrektem Handoff
- [ ] Abschluss-Blocker `completed` wie heute

### Parallel & Mobile

- [ ] Tab `packs`: Legacy `ActivityPackListTab` unverändert funktionsfähig bis Rollout
- [x] Route `pack-journey`: Journey parallel erreichbar; Links Legacy ↔ Beta
- [ ] Jede Journey-Phase auf **375px** abgenommen vor Desktop

### UX

- [ ] Eine Hauptaktion pro Zeile auf Mobile (min. 44px Touch)
- [ ] Max. ein Info-Banner gleichzeitig
- [ ] Stepper dynamisch pro Profil, keine grauen Transport-Platzhalter bei quick

- [x] Material-Tab: UI refresh, **ohne** Scan; Suche/Verfügbarkeit wie bisher
- [x] Kein `plan`-Step im Stepper
- [ ] Handoff-Banner gemäß [§19.1](#191-handoff-banner--readonly-matrix)
- [ ] `not_taken` als eigene Zeile, Gruppe sichtbar
- [ ] Material nachbuchen: Modal bei `packed`/`at_event` ([§5.2](#52-block-material-nachbuchen))
- [x] Einlagern: `MaterialStoreShelveSheet` mit Verräum-Feedback ([§7.6](#76-materialstoreshelvesheet-einlagern))
- [ ] Partner-Department readonly ([§8.1](#81-partner-departments))
- [x] Deep Links Inbox → `pack-journey/:step` (ab Phase 8b)
- [ ] `external`: MW-only Journey, PDF + Kosten-Tab — [§2.5](#25-profil-external-vermietung)
- [ ] Completion-Blocker verlinken (kein Wizard) — [§20.3](#203-buchhaltung--abschluss--external)
- [ ] Parität Legacy §22: Shell, Accordion Fix/Zusatz, QR/Charge, Retour-Stapel, Live-Sync
- [x] Pack-History: Moves loggen, UI-Aggregation, **kein** Scan-Log — §20.2

---

## 14. Nachlieferungs-Wunsch (Ersteller → MW)

**Ziel:** Der **Ersteller / Leiter** kann dem Materialwart **vor** oder **während** des Anlasses zusätzliches Material **wünschen** — mit **Live-Verfügbarkeit** aus dem Lager («ist das überhaupt da?»). MW sieht die Wünsche, kann sie **ablehnen**, **erfüllen** (→ echte Nachlieferung) oder **nachbestellen**.

Ergänzt die bestehende MW-Nachlieferung (`replenishment: true` → `is_replenishment`), ersetzt sie nicht.

### 14.1 User Stories

| Rolle | Aktion |
|-------|--------|
| Ersteller | Material suchen/wählen, Menge + optional Notiz, **Verfügbarkeit** für Aktivitätszeitraum sehen |
| Ersteller | Wunsch absenden → MW erhält Hinweis (Inbox / Journey) |
| MW | Queue «Offene Wünsche» in Journey (z. B. Schritt `pack` oder globaler Block) |
| MW | [Erfüllen] → legt `activity_item` + Pack-Zeile an (`is_replenishment` oder normale Zeile je Regel) |
| MW | [Ablehnen] mit kurzer Begründung |

### 14.2 UI — Ersteller

Platzierung (Ziel):

- Panel unter Journey Scan-Bar (Packliste) **oder** Material-Tab — Ersteller sieht Verfügbarkeit bei Suche

```
┌─────────────────────────────────────────┐
│  Nachlieferung wünschen                 │
│  [ Material suchen… ]                   │
│                                         │
│  Taschenlampe · gewünscht: 5            │
│  Im Lager (Zeitraum): 12 verfügbar  ✓   │
│  Regal: B3                              │
│  Notiz: [ für Nachtwanderung ]          │
│  [ Wunsch senden ]                      │
├─────────────────────────────────────────┤
│  Meine Wünsche                          │
│  ◐ Seile 10m (2) — beim MW            │
│  ✓ Feuerzeug (1) — erfüllt            │
└─────────────────────────────────────────┘
```

**Verfügbarkeit:** bestehende Availability-API / `ActivityMaterialAvailabilityLookup`-Logik — gleicher Zeitraum wie Aktivität (`planning_*` / `usage_*`). Anzeige:

| Verfügbar | UI |
|-----------|-----|
| `qty >= gewünscht` | grün: «{n} verfügbar» |
| `0 < qty < gewünscht` | orange: «Nur {n} verfügbar» — Wunsch trotzdem möglich |
| `qty === 0` | rot: «Aktuell nicht verfügbar» — Wunsch mit Hinweis «MW muss ggf. beschaffen» |

### 14.3 UI — Materialwart

- Block in Journey (empfohlen: oben in `pack`, wieder sichtbar in `issue`/`return` wenn offen):
  - «3 offene Nachlieferungs-Wünsche»
- Zeile: Material, Menge, Ersteller, Verfügbarkeit zum Wunschzeitpunkt, Notiz
- Aktionen: **Erfüllen** (öffnet bestehenden Nachlieferungs-Flow / `replenishment: true`), **Ablehnen**, **Später**

Nach «Erfüllen»: Wunsch → `fulfilled`, Packliste zeigt ○-Zeile mit Badge «Nachlieferung» ([§5.7](#57-nachlieferung-is_replenishment)).

### 14.4 Datenmodell (Ziel)

Neue Entität — **nicht** in Journey v1:

```
activity_replenishment_wish
  id
  activity_id
  material_item_id
  quantity_requested
  notes                 nullable
  status                pending | fulfilled | rejected | cancelled
  requested_by_user_id
  requested_at
  decided_by_user_id    nullable
  decided_at            nullable
  rejection_reason      nullable
  fulfilled_activity_item_id  nullable  FK → activity_item
  availability_snapshot JSON nullable   { available, reserved, as_of }
```

### 14.5 API (Ziel)

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| GET | `…/activities/{id}/replenishment-wishes` | Liste (Ersteller: eigene; MW: alle) |
| POST | `…/activities/{id}/replenishment-wishes` | Wunsch anlegen (+ optional Availability-Check im Response) |
| PATCH | `…/replenishment-wishes/{id}` | MW: fulfill / reject / cancel |
| POST | `…/replenishment-wishes/{id}/fulfill` | Atomar: Wunsch erfüllen + `activity_item` / Nachlieferung anlegen |

Berechtigung: anlegen = Ersteller / Gruppen-Scope mit Material-Recht; bearbeiten = `canManageMaterials`.

### 14.6 Benachrichtigungen

- Neuer Wunsch → Inbox MW (wie andere Aktivitäts-Events)
- Erfüllt / Abgelehnt → Inbox Ersteller

---

## 15. Presence (Mehrbenutzer im Lager)

**Entscheidung:** **Nicht in Journey v1** — Phase 2+ / [devices D6](../../devices/rollout-plan.md).

| Element | Spec |
|---------|------|
| API | `PATCH …/activities/{id}/pack-session/presence` (neu) |
| Body | `{ shelf?: string, containerId?: string, journeyStep?: string }` |
| Timeout | ~60 s ohne Heartbeat |
| Anzeige | dezent unter Scan-Bar: «Max M. · Regal B3» |

**Journey v1:** leerer Slot `presence-slot` in `MaterialJourneyToolbar` — kein Heartbeat.

**Konflikt:** Presence = **nur Info**, kein Lock; `move`-API bleibt Source of Truth.

Bei Filter «Nach Regal» (MW): später Heartbeat mit `shelf` senden.

---

## 16. Getroffene Entscheidungen

| # | Thema | Status | Entscheidung |
|---|-------|--------|--------------|
| 1 | Stepper dynamisch | **entschieden** | pro Profil, keine grauen Platzhalter |
| 2 | Schritt `plan` | **entschieden** | **weglassen** — Tab Material |
| 3 | Material-Tab Scan | **entschieden** | **nein** — nur `ActivityMaterialAvailabilityLookup` |
| 4 | Parallel-Entwicklung | **entschieden** | Tab `packs` = Legacy; Route `pack-journey` = neu — §4.1, Rollout Phase 13 |
| 5 | Mobile-First | **entschieden** | Design/Implementierung ab 375px — §4.7 |
| 6 | Bausteine schrittweise | **entschieden** | Shared Rules/APIs, 1–2 UI-Komponenten pro Phase — §4.8, §12 |
| 7 | Sheet vs. Vollbild | **entschieden** | Vollbild &lt;768px (Mobile-Default) |
| 8 | Route Journey | **entschieden** | `…/pack-journey/:step?` — nicht Tab-Slot |
| 9 | Presence v1 | **entschieden** | nein — Phase 12 |
| 10 | Transport / Nachlieferung / virt. Kombi | **entschieden** | siehe §3.5, §5.7, §5.1 |
| 11 | Retour gemischt | **entschieden** | `MaterialReturnCrateSheet` §7.5 |
| 12 | Nachlieferungs-Wunsch | **Konzept** | §14, Phase 10 |
| 13 | J+S Tab | **tendenz** | eigener Tab, Phase 9 |
| 14 | Kopfzeile vs. Journey | **entschieden** | Status Kopfzeile, Mengen Journey — §4.4 |
| 15 | Material nachbuchen | **entschieden** | §5.2, §17.2 |
| 16 | Kisten-Workflow | **entschieden** | §17.3, Intent §19.2 |
| 17 | `not_taken` | **entschieden** | eigene Zeile §5.1 |
| 18 | Einlagern UX | **entschieden** | §7.6 |
| 19 | `moveback` | **entschieden** | eine Packstufe zurück §17.7 |
| 20 | Partner-Departments | **entschieden** | §8.1 |
| 21 | Handoff-Banner | **entschieden** | Matrix §19.1 |
| 22 | Unberechtigter Zugriff | **entschieden** | §8.3, §19.4 |
| 23 | Transport / Touren | **entschieden** | Dept-Fuhrpark, N Touren, §19.3 |
| 24 | Benachrichtigungen | **entschieden** | Inbox + Push (kein SMS) — §20.1 |
| 25 | History/Audit | **entschieden** | Pack-Events + Aggregation, **kein** Scan-Log — §20.2 |
| 26 | Nutzungs-Statistik | **entschieden** | aus Moves, Dept — §20.10 |
| 27 | Scan-History | **abgegrenzt** | depübergreifend, nicht Journey — §20.11 |
| 28 | Buchhaltung / External | **entschieden** | §20.3, kein Abschluss-Wizard |
| 29 | Schaden/Foto | **entschieden** | bestehende Meldungen/Werkstatt — §20.4 |
| 30 | Parität Legacy | **entschieden** | §22 |

Detailregeln: [§17](#17-detailregeln-produkt) · Backlog: [§18](#18-nice-to-have-backlog) · Klärungen: [§19](#19-klärungsdetails-ehemals-offen) · Integration: [§20](#20-integration--erweiterungen) · Parität: [§22](#22-parität-legacy-packliste--entschieden)

---

## 17. Detailregeln (Produkt)

Festgelegte Produktentscheidungen aus Review Juni 2026 — ergänzen §2–§8.

### 17.1 Kopfzeile vs. Journey

Siehe [§4.4](#44-status-vs-journey--entschieden). Kurz: **keine** Status-Buttons im Journey-Footer; Journey zeigt Fortschritt `N/M` und kontextuelle Hinweise.

### 17.2 Material nachbuchen

| Wer | Wann | UI |
|-----|------|-----|
| **MW** | ab Step `pack` | Block «Material nachbuchen» — Suche wie `addMaterial` |
| **Gruppe** | ab `at_event` | Nachlieferung / Wunsch (§14) |

**Spät-Nachbuchung** (`activity.status` bereits `packed` oder `at_event`): Modal **«Nachbuchung»** vor Speichern:

| Feld | Werte |
|------|-------|
| **Wer holt/bringt?** | `leader_pickup` · `mw_delivers` · `mw_transport` (nur Logistics) |
| **Pipeline-Ziel** | Journey-Step → `replenishment_pack_stage` |

API: bestehende Add-Material + `replenishment: true` / `replenishment_pack_stage` — kein neuer Endpoint v1.

### 17.3 Kisten — anlegen, zuordnen, «zusammen»

| Trigger | Verhalten |
|---------|-----------|
| MW packt vom **Regal** | Optional «In Kiste packen?» — **nicht** bei jedem Artikel |
| **Regal-Wechsel** | Frage erneut anbieten (Session-Flag `lastPromptedShelf`) |
| **Toolbar-Button** «In Kiste» | Modal: Kiste scannen → aktive Buchungen in Container |
| **«Zusammen packen»** ohne Kiste | Intent-Gruppe — [§19.2](#192-zusammen-packen-ohne-kiste--datenmodell) |

### 17.4 «Nicht mitgenommen» (`not_taken`)

- Eigene Zeile, Badge **«Nicht mitgenommen»**
- Sichtbar für **MW und Gruppe** (Step `return` / readonly in `store`)
- **Nicht** mit normalen Retour-Zeilen mischen — Filter `shouldIncludePackItemOnReturnNotTaken`

### 17.5 Einlagern (`store`)

Siehe [§7.6](#76-materialstoreshelvesheet-einlagern): Scan oder Listen-Tap → Regal/Fach → «Verräumt» oder Scan Regal+Fach → Toast + Ja/Nein-Countdown.

### 17.6 `self_provided`

Am Step **`issue`**: Checkbox/Confirm **«Selbst mitgebracht?»** pro Block — Organisierung war Planungs-Sache (Tab Material). Kein Pipeline-Move; nur Bestätigung für Leiter.

### 17.7 Rückwärts (`moveback`)

- **Eine Packstufe** zurück pro Zeile/Sheet — `POST …/moveback`
- MW: Confirm bei Kiste/Phys.-Set wenn Kistencheck betroffen
- In Journey-UI: Zeile ⋮ → «Schritt zurück» oder im Sheet
- **Kein** Zwang über «Klassische Packliste» für Normalfall (nach Rollout optional für MW)

### 17.8 Partner-Departments

Siehe [§8.1](#81-partner-departments).

### 17.9 Gast-Einladung

Bestehende Einladungs- und Gruppen-Scope-Regeln — Journey ändert nichts an `external` / Gast-Logik.

### 17.10 Randfälle

| Fall | Regel |
|------|--------|
| **Netzwerkfehler** | Meldung + **Wiederholen** — §6.5 |
| **Zwei User, eine Zeile** | Presence + Konflikt-Dialog — §6.5, Phase 12 |
| **Storno `cancelled`** | `packing`: nur MW. Material draussen (`at_event`…): nur MW bis Retour. MW-Storno → Modal **«Zuerst alles retournieren»** |
| **Leere Packliste** | technisch möglich; Einreichen ohne Material blockiert |
| **Keine Berechtigung** | §8.3 |
| **Deep Links** | Inbox → `…/activities/{id}?tab=packs&step=issue` (o. ä.) |

### 17.11 `moveback` vs. Status

`moveback` ändert **nur** Pipeline-Mengen — **nicht** den Aktivitäts-Status in der Kopfzeile.

### 17.12 MW-Notfall bei Gruppen-Handoff

Nur wenn MW **≠** Ersteller (`packMwGroupHandoffActive`): MW darf auf Steps `issue`/`return` **Notfall-Moves** mit Confirm — wie heute `mwHandoffAllowsPackLineControls`. Banner §19.1.

**MW = Ersteller:** kein Handoff, **kein** Gruppen-Notfallmodus — normale MW-Buchungen auf allen Steps.

### 17.13 Kosten, Buchhaltung & Abschluss

Siehe [§20.3](#203-buchhaltung--abschluss--external) für Details.

- Beim Auspacken (`store`): offene Defekte/Fehlmengen → Hinweis + Sprung Tab **Kosten**
- Status `completed` in **Kopfzeile** mit bestehender `ActivityCompletionChecklist` — **kein** separater «Abschluss-Wizard» in der Journey
- Blocker aus `GET …/transitions` (`completion_blockers`): Einlagern, Issues, Werkstatt, `accounting_followups` — MW/DC only ([status.md](../status.md))

---

## 18. Nice-to-have Backlog

Priorisiert — v1-Blocker nur wo explizit markiert.

| Prio | Feature | Phase | Notizen |
|------|---------|-------|---------|
| **hoch** | **History/Audit** Journey | **8** | Pack-Events + UI-Aggregation — [§20.2](#202-history--audit-pack-journey) |
| **hoch** | UX-Polish | laufend | Skeleton, Pull-refresh, leere Zustände — [§21](#21-ux--technik) |
| **hoch** | Fortschritt Übersicht | 2+ | «12/18 gepackt» auf Aktivitäts-Karte |
| **hoch** | Benachrichtigungen Push | 8+ | nach Inbox — [§20.1](#201-benachrichtigungen) |
| **mittel** | Presence light | 12 | MW-Indikator + Regal — §15 |
| **mittel** | Batch-Scan | 4+ | [§21](#21-ux--technik) |
| **mittel** | Keyboard-Shortcuts | 5+ | Desktop MW |
| **mittel** | Kamera-QR am Suchfeld | 4+ | |
| **mittel** | Teilausgabe-Chip | 2+ | + Inbox an Ersteller bei Teilausgabe — §20.1 |
| **mittel** | Smart Stepper | 2+ | |
| **mittel** | Plan vs. Ist (L3) | 8+ | |
| **mittel** | Transport-Touren + Dept-Fuhrpark | 7+ | §19.3 |
| **mittel** | Sammelaktionen | 5+ | z. B. «Alles in Regal X packen» — optional — §20.7 |
| **mittel** | Etikett beim Packen | 8+ | MW, wenn noch keins — §20.8 |
| **mittel** | Zeilen-Notizen | 8+ | optional, nicht zwingend — §20.6 |
| **mittel** | Material-Vorlagen | 10+ | eher virt. Kombos — §20.7 |
| **niedrig** | Pickliste drucken | 8+ | ressourcenschonend |
| **niedrig** | Infoscreen | 9+ | konfigurierbar |
| **mittel** | Nutzungs-Statistik (Moves) | 9+ | Department, ohne Scans — §20.10 |
| **Zukunft** | Scan-History depübergreifend | TBD | Scan-Funktion — §20.11 |
| **Zukunft** | Material-Substitution | TBD | Stammdaten — §20.5 |
| **Zukunft** | MW-Aufgaben-Zuweisung | TBD | mehrere MW |
| **bewusst nein** | SMS | — | Kosten; nur Inbox/Push |
| **bewusst nein** | Outdoor/High-Contrast | — | |
| **bewusst nein** | i18n FR/IT Journey | — | DE/EN reicht v1 |

---

## 19. Klärungsdetails (ehemals offen)

Explizite Festlegungen zu zuvor offenen Punkten — Basis für Implementierung Phase 2+.

### 19.1 Handoff-Banner & Readonly-Matrix

**Profil:** `activity` / `camp` / `event` (Gruppen-Handoff). **`external`:** MW arbeitet durchgehend — **keine** Handoff-Banner.

**Banner-Priorität** (max. **ein** Banner gleichzeitig):

1. Kistencheck pending (orange)
2. Teilausgabe-Hinweis (`issue`, offene Positionen)
3. Handoff-Banner (blau/info)
4. Partner-Dept readonly (dezent, kein Vollbanner)

#### Matrix: Gruppe (Leiter / L1–L3, `!canManageMaterials`)

| `activity.status` | Journey-Step (Default) | Banner (Gruppe) | Readonly Journey | Darf bearbeiten |
|-------------------|------------------------|-----------------|------------------|-----------------|
| `submitted` / `approved` | — (Vorschau) | «MW packt noch» | **ja** (Preview) | nein |
| `packing` | `pack` | «MW packt» | **ja** | nein |
| `packed` | `issue` | — | nein | **`issue`** (Ausgabe) |
| `at_event` | `return` | — | nein | **`return`** (leichter Check) |
| `returned` | `return` / `store` | **«Retour gemeldet — MW räumt ein»** | **ja** | nein (Verbrauch-Tab weiter) |
| `completed` | alle | — | **ja** | nein |

**Frühe Pack-Vorschau** (`submitted` / `approved` / `packing`, Gruppe): Empty-State «MW packt noch — {n} Positionen geplant», keine ○-Aktionen (`memberAwaitingMwPack`) — [§22.10](#2210-frühe-pack-vorschau).

#### Matrix: Materialwart — **ist Ersteller** (`packMwIsActivityCreator`)

| Bedingung | Banner | Rechte |
|-----------|--------|--------|
| MW = Ersteller der Aktivität | **keine** Handoff-Banner | volle MW-Rechte **alle** Steps (`pack` … `store`) |
| Gruppen-Notfallmodus | **entfällt** — MW ist zugleich Organisator, bucht normal | wie [pack-workflow-rules §3](../pack-workflow-rules.md) |

#### Matrix: Materialwart — **Ersteller** oder **`external`** (kein Handoff)

Gilt wenn `packMwIsActivityCreator` **oder** Profil `external`:

| `activity.status` | Banner MW | Readonly | Anmerkung |
|-------------------|-----------|----------|-----------|
| `packing` | — | nein | volle `pack`-Liste |
| `packed` | — | nein | `pack` + ggf. `issue` |
| `at_event` | — | nein | alle erlaubten Steps |
| `returned` | — | nein | `store` + Retour-Korrektur |
| `completed` | — | ja | Historie |

#### Matrix: Materialwart (**nicht** Ersteller) — Gruppen-Handoff

Bedingung: `packMwHandoffBannerVisible` = `canManageMaterials` && `isGroupHandoffProfile` && `!isMwActivityCreator` && Status `packed`|`at_event`.

| `activity.status` | Banner MW | MW darf Moves | Confirm |
|-------------------|-----------|---------------|---------|
| `packed` | **«Gruppe holt ab — du unterstützt nur im Notfall»** | nur `issue`/`return` (Notfall) | vor jedem Move + vor Status `at_event` |
| `at_event` | **«Gruppe retourniert — du unterstützt nur im Notfall»** | nur `issue`/`return` (Notfall) | vor Move + vor Status `returned` |
| `returned` | — | `store`, Korrektur | normal |

**Revert/Löschen** während Handoff: gesperrt — Toast «Während Gruppen-Übergabe nicht rückgängig» (wie `mwHandoffRevertLocked`).

**i18n-Keys** (bestehend): `mwHandoffBannerTitle/Body`, `mwReturnHandoffBannerTitle/Body`, `readonlyHintReturnedHandoff`.

### 19.2 «Zusammen packen» ohne Kiste — Datenmodell

**Problem:** MW will mehrere lose Positionen **zusammen** packen, ohne sofort eine physische Kiste zu scannen.

**Entscheidung:** **Zwei-Phasen-Modell** — Intent zuerst, Container später. **Kein** sofortiger logischer Container (das ist `virtual_combo` / `pack_mode together`).

| Phase | Speicher | UI |
|-------|----------|-----|
| **1 — Intent** | `activity_pack_group_intent` (neu, Phase 11) | MW wählt Zeilen → «Zusammen packen» → Badge «Zusammen (n)» an Zeilen, optional eingeckelte Gruppe |
| **2 — Materialisierung** | bestehende `activity_pack_container` | MW scannt Kiste **oder** «Neue Kiste» → alle Items mit gleichem `intent_id` werden `container_id` zugewiesen |

**Entität (Ziel):**

```
activity_pack_group_intent
  id
  activity_id
  label              nullable  — z.B. «Küche», «Zelt 3»
  created_by_user_id
  created_at
  resolved_at        nullable
  resolved_container_id  nullable FK → activity_pack_container
```

**Client-only Fallback (Phase 3):** Session-`Map<intentId, packItemIds[]>` bis API existiert — beim Kisten-Scan Bulk-Assign.

**Abgrenzung:**

| | Intent-Gruppe | Virt. Kombi `together` |
|--|---------------|------------------------|
| Auslöser | MW beim Packen | Ersteller in Planung |
| Zeile | lose Zeilen + Badge | eine «logische Kiste» |
| Backend | optional `intent_id` auf `pack_item` | `activity_pack_container` virt. |

### 19.3 Transport — Touren & Department-Fuhrpark

**Scope:** Phase 7 (Logistics `camp`/`event`) — umgesetzt in `pack-journey` (`transport_out` / `transport_back`).

#### Grundmodell

| Konzept | Regel |
|---------|--------|
| **Fuhrpark** | Stammdaten auf **Department-Ebene** (`department_vehicle`) — Fahrzeuge werden von Departments **ausgeliehen**, kein globaler Org-Pool |
| **Touren** | **Mehrere Touren** pro Aktivität und Richtung (`transport_out` / `transport_back`) |
| **Fahrzeug-Wiederverwendung** | Dasselbe Fahrzeug darf in **mehreren Touren** derselben Aktivität vorkommen (z. B. 2. Fahrt mit demselben Transporter) |
| **Tour-Label** | Auto-Vorschlag **Tour A**, **Tour B**, **Tour C** … — bei erneuter Wahl desselben Fahrzeugs: «Tour B (Blaustein — 2. Fahrt)» |

**Abgrenzung Pipeline:** `quantity_transport_to` / `quantity_transport_back` bleiben die fachliche Pipeline. Touren sind eine **Zuordnungs- und Planungsschicht** (welche Kisten auf welchem LKW, Ladeflächen-Check) — optional später Teilmoves pro Tour.

#### Department-Fuhrpark

```
department_vehicle
  id
  department_id          FK — Besitzer / Ausleiher-Department
  name                   z.B. «Blaustein», «Anhänger 12»
  plate                  nullable
  length_m, width_m, height_m
  max_payload_kg
  max_volume_m3          nullable
  is_active
  notes                  nullable
```

| Aspekt | Regel |
|--------|--------|
| **Sichtbarkeit** | MW sieht Fahrzeuge des **eigenen Departments** + ggf. Partner-Departments der Aktivität (readonly-Vorschau) |
| **Ausleihe** | Aktivität kann Fahrzeuge aus mehreren Departments nutzen (Camp mit Material von Dept A, Fahrzeug von Dept B) — Tour verknüpft `vehicle_id` + optional `lending_department_id` |
| **Pflege** | Department-Settings / MW-Verwaltung — nicht Journey v1 |

#### Touren pro Aktivität

```
activity_transport_tour
  id
  activity_id
  label                  — «Tour A» (auto oder editierbar)
  vehicle_id             FK → department_vehicle  (nicht unique pro Aktivität!)
  direction              — outbound | inbound   (= transport_out | transport_back)
  sort_order
  notes                  nullable
  created_by_user_id
```

```
activity_transport_tour_item
  tour_id
  pack_container_id      nullable
  pack_item_id           nullable
  quantity               nullable — Teilmenge
```

**UI** (Steps `transport_out` / `transport_back`):

1. Liste der Touren (Tour A, Tour B, …)
2. **«+ Tour»** → Fahrzeug aus Department-Fuhrpark wählen
3. Auto-Label: nächster Buchstabe; Hinweis wenn Fahrzeug schon in anderer Tour
4. Kisten / lose Positionen der Tour zuweisen (Checkbox, Scan oder Drag)
5. Pro Tour: Chip **Passt** / **Zu schwer** / **Volumen knapp**

#### Ladefläche & Gewicht

| Aspekt | Regel |
|--------|--------|
| **Abgleich** | nur der **dieser Tour zugewiesenen** Ladung — Summe `estimated_weight_kg` / `estimated_volume_m3` |
| **Schätzung** | `material_item`-Stammdaten × Menge; Kisten: Summe Inhalt oder Default-Kistenvolumen |
| **UI** | Hinweis-Chip — **kein** Hard-Block v1 |
| **Hard-Block** | optional Dept-/Org-Setting später |
| **Ohne Stammdaten** | Kategorie-Default oder MW-Notiz am Container |

#### `transport_back`

Spiegelung der Hinfahrt empfohlen: gleiche Tour-Labels (Tour A zurück, Tour B zurück) mit `direction: inbound` — MW kann Zuordnung anpassen wenn Ladung anders verteilt wird.

### 19.4 Unberechtigter Zugriff — Audit vs. Benachrichtigung

| Aspekt | Entscheidung |
|--------|--------------|
| **Audit-Log** | **immer** — Eintrag `security.unauthorized_pack_access` mit user, activity, step, timestamp, IP, User-Agent |
| **Benachrichtigung** | **ja, aktiv** — Inbox-Event an: Org-Admins (`canManageOrg`), Material-Verantwortliche der **Suborg/Dept** der Aktivität, optional Superadmin wenn `activity.org_id` ≠ User-Org |
| **Kein** Spam | gleicher User + gleiche Activity innerhalb **1 h** → höchstens **eine** Inbox-Meldung (Audit trotzdem jedes Mal) |
| **Inhalt Meldung** | «{User} hat ohne Berechtigung Packliste von {Activity} geöffnet (Step {step})» + Link Audit |
| **Client** | sofort Redirect — kein Warten auf Audit |

**Begründung:** Reines Logging reicht für Compliance nicht; aktive Meldung entspricht User-Vorgabe «ORG / SUBORG Superadmin informieren».

---

## 20. Integration & Erweiterungen

### 20.1 Benachrichtigungen

**Kanal:** Inbox (v1) → **Push in App** auf dem Handy (Zukunft). **Kein SMS** (Kosten).

| Event | Empfänger | Auslöser |
|-------|-----------|----------|
| **Packliste fertig** | Ersteller / Leiter | Aktivität → `packed` (Material abholbereit) |
| **Teilausgabe** | Ersteller | MW bestätigt Status **«Am Event»** mit Teilausgabe (Button/Confirm Teilausgabe) — nicht bei jeder einzelnen Zeile |
| Nachlieferungs-Wunsch | MW / Ersteller | §14 |
| Unberechtigter Zugriff | Org/Dept-Admins | §19.4 |

**Push (Zukunft):** gleiche Events wie Inbox — Web-Push oder native Wrapper; Opt-in pro User.

**Deep Link:** Meldung öffnet Route `…/pack-journey/:step` (Inbox + Glocken-Dropdown).

### 20.2 History / Audit (Pack-Journey)

**Priorität: hoch** — Phase 8. Erweitert bestehendes `activity_history` + Tab **Verlauf** (`ActivityHistoryTab`).

#### Abgrenzung

| In Scope | Nicht in Scope (hier) |
|----------|------------------------|
| Buchungen / Pipeline-Moves pro **Aktivität** | Reine Scan-Lookups ohne Move |
| Kistencheck, Status, Planungsänderungen | Scan-Fehler (`not_on_list`, …) |
| Aggregation in der **Anzeige** | **Scan-History** depübergreifend → [§20.11](#2011-scan-history-abgegrenzung) |

#### Events — Backend (vollständig speichern)

| `action` | Wann |
|----------|------|
| `pack_move` | `POST …/move` — Pipeline vorwärts |
| `pack_moveback` | `POST …/moveback` |
| `pack_container_bulk` | Kiste komplett / Container-Controller Bulk |
| `pack_crate_assign` | Material in Kiste / aus Kiste (`assign-up`) |
| `pack_crate_check` | **bestehend** — Kistencheck |
| `pack_add` / `material_changed` | Nachbuchung, Planung |
| `status_changed` | **bestehend** |
| `pack_reset_cancel` | **bestehend** |

**Nicht loggen:** Scan ohne erfolgreiche Buchung; fehlgeschlagene Scan-Auflösung.

#### Felder pro Eintrag (Ziel)

```
activity_pack_event  (oder activity_history.changes erweitert)
  id, activity_id, user_id, created_at
  action
  pack_item_id?, pack_container_id?, material_item_id?, batch_id?
  stage_from, stage_to, quantity
  journey_step?
  source: 'scan' | 'tap' | 'bulk'   // nur wenn Move — kein separates Scan-Log
```

`source: scan` markiert, dass die Buchung **via Scan** ausgelöst wurde — ersetzt **keine** Scan-History.

#### UI — Zusammenfassung (Anzeige)

Rohdaten bleiben vollständig; UI **darf aggregieren**:

| Regel | Anzeige |
|-------|---------|
| Gleicher User + gleicher `journey_step` + ≤ **2 min** | «Max · Packen · 14:32–14:45 · **23 Positionen**» |
| Gleiches Material mehrfach | «Taschenlampe · **×5** gepackt» |
| Bulk-Kiste | eine Zeile «Kochkiste 2 · komplett mitgenommen» |
| Drill-down | Tap auf Gruppe → Einzel-Events |

**Ort:**

| UI | Inhalt |
|----|--------|
| Tab **Verlauf** (`ActivityHistoryTab`) | alle Events inkl. Planung + Packen |
| Journey Zeile ⋮ → «Verlauf» | nur diese `pack_item_id` / Kiste |
| Filter | User, Step, Material, Kiste, Zeitraum |

API (Ziel): `GET …/activities/{id}/pack-events` oder erweitertes `GET …/history` mit `action`-Filter.

### 20.3 Buchhaltung, Abschluss & `external`

**Kein** monolithischer «Abschluss-Wizard» in der Journey — bestehendes Modell beibehalten:

| Element | Ort | Verhalten |
|---------|-----|-----------|
| Status `completed` | Kopfzeile `ActivityDetailView` | `usePackWorkflowConfirm` |
| Blocker-Liste | `ActivityCompletionChecklist` (MW/DC only) | `completion_blockers` aus `GET …/transitions` |
| Blocker-Typen | wie [status.md](../status.md) | Einlagern, Issues, Werkstatt-Tickets, `accounting_followups` (Verbrauch pro Dept, Nachlieferung, …) |
| Journey `store` | Hinweis + **Link** Tab Kosten / Issues / Werkstatt | kein eingebetteter Multi-Step-Wizard |

#### Profil `external` (Vermietung)

Nur `activity.type === 'external'`:

| Feature | Regel |
|---------|--------|
| **`activity_rental`** | Tab **Kosten**: Positionen bearbeiten, Beträge, **Rabatt** setzen |
| **PDF Ausgabe** | Generierung «Ausgegebene Materialien» bei Ausgabe/Abschluss — Übergabe-Quittung für externen Kunden |
| Journey | MW durchgehend; Gruppe readonly — [§2.5](#25-profil-external-vermietung) |
| Blocker | `activity_rental` / Buchhaltung in Completion-Checkliste wie heute |

### 20.4 Schaden & Foto

**Kein** separates Foto in der Journey. Schäden über **bestehende Meldungen** → Werkstatt-Ticket (Tab `issues`) — inkl. Foto-Upload dort.

Journey verlinkt bei Bedarf: Retour-Sheet / Zeile ⋮ → «Meldung erfassen».

### 20.5 Material-Substitution (Zukunft)

Wenn Material X nicht verfügbar: Ersatz Y — **muss in Material-Stammdaten** hinterlegt sein (`substitute_for` o. ä.). Journey zeigt Vorschlag bei Scan/Verfügbarkeit; Buchung erst wenn Stammdaten-Modell existiert. **Nicht v1.**

### 20.6 Zeilen-Notizen (optional)

MW/Leiter: kurze Notiz pro Pack-Zeile («nur für diesen Anlass», «Kratzer ok»). Feld `pack_item.notes` oder Activity-scoped. **Sinnvoll, nicht zwingend** — Phase 8+.

### 20.7 Vorlagen & Sammelaktionen

| Feature | Regel |
|---------|--------|
| **Material-Vorlagen** | Aktivitäten-Material aus Vorlage — eher über **virtuelle Kombos** / gespeicherte Sets; nicht 1:1 «letzte Aktivität kopieren» |
| **Sammelaktionen** | optional: «Alles in Regal X packen», «Alle offenen Kisten in Step pack» — MW-only |

### 20.8 Etikett beim Packen

Wenn Material **noch kein Etikett/QR** hat: MW kann aus Journey **Etikett anstossen** — Anbindung [label-fulfillment.md](../../future/label-fulfillment.md). Nur MW-Ebene, nicht Gruppe. Phase 8+.

### 20.9 Bezug zu `devices.`

`devices.ematchef.ch` = **separates** Terminal für Handheld/Barcode-Scanner — **kein** Scope der Journey-Spec.

**Übernehmbar in `app.`** (optional):

| Pattern | Nutzen |
|---------|--------|
| Scan-Erfolg/Fehler (Farbe, optional Ton) | [§21](#21-ux--technik) Zeile Scan-Feedback |
| Kompakte Fortschrittsanzeige X/Y | Journey-Footer |
| Aktivitäts-QR öffnet Packliste | Deep Link + Scan-Resolve |

Keine Pflicht-Parität mit devices-Flows (Hin/Retour-Umschalter, Offline-Queue).

### 20.10 Nutzungs-Statistik (Department)

**Phase 9+** — Auswertung «was oft genutzt wird», **ohne** Scan-Events.

| Aspekt | Regel |
|--------|--------|
| **Quelle** | abgeleitet aus **`pack_move`** / Pipeline (ausgegeben, gepackt, retourniert) — nicht aus Scan-Lookups |
| **Scope** | **Department** (ggf. Zeitraum: Monat, Saison) |
| **UI** | Department-Statistik / MW-Übersicht — **nicht** Aktivitäts-Tab «Verlauf» |
| **Metriken** | Top-Material nach Ausgabe/Pack; Häufigkeit pro `material_item_id`; optional pro Gruppe/Aktivitätstyp |

Aggregation: batch/nightly oder Materialized View aus `activity_pack_event` — kein separates Scan-Log nötig.

### 20.11 Scan-History (abgegrenzung)

**Bewusst nicht** Teil der Journey- oder Aktivitäts-Pack-History.

Vollständige Spezifikation (zwei verwandte, aber getrennte Historien): **[docs/qr/scan-and-url-history.md](../../qr/scan-and-url-history.md)**.

| Thema | Regel |
|-------|--------|
| **Scan-History** | depübergreifend, an der **Scan-Funktion** / Department-Ebene — wer/wann/was gescannt, `resolve_result`, optional `led_to_action` |
| **QR-URL-History** | Lebenszyklus der öffentlichen URL (`url_created`, Druck, `url_invalid_access`) — **nicht** jeder Seitenaufruf auf `qr.` |
| **Journey** | «Letzte Scans» nur **Session** (§21) — nicht persistiert |
| **Moves via Scan** | in Pack-History mit `source: scan` — reicht für «wie gebucht» |

Scan-Statistik («oft gescannt, selten gebucht») gehört zur **Scan-History**, nicht zu §20.2 / §20.10 (Moves).

---

## 21. UX & Technik

Umsetzen (v1–v2), ausser explizit ausgeschlossen.

| # | Thema | Spec |
|---|--------|------|
| 1 | **Virtual Scroll** | Listen &gt; ~50 Zeilen — `vue-virtual-scroller` o. ä. |
| 2 | **Pull-to-refresh** | Packliste neu laden |
| 3 | **Scan-Feedback** | Grün/Rot-Banner, optional Haptic/Sound (von devices-Idee) |
| 4 | **Letzte Scans** | 3–5 Einträge unter Scan-Bar — **nur Session**, nicht persistiert ([§20.11](#2011-scan-history-abgegrenzung)) |
| 5 | **Batch-Scan** | mehrere Codes hintereinander |
| 6 | **Keyboard-Shortcuts** | Desktop MW: `/` Suche, Enter bestätigen |
| 7 | **Skeleton / leere Zustände** | pro Step |
| 8 | **Touch 44px** | DoD §13 |
| 9 | **Session** | bei Resume: Packliste refresh |
| 10 | **Live-Sync** | Route `pack-journey` aktiv (ab Rollout auch Tab `packs`): **5 s** Polling wenn ≥1 anderer User die Aktivität offen hat; sonst **20 s**; bei Resume sofort refresh — [§22.9](#229-live-sync) |

**Bewusst nicht v1:**

- Outdoor / High-Contrast-Modus
- i18n FR/IT für Journey (DE/EN reicht)

---

## 22. Parität Legacy-Packliste — **entschieden**

Regeln aus `ActivityPackListTab` / `packWorkflowRules.ts` — in Journey abgebildet. Details in §6, §7, §19.

| # | Thema | Entscheidung | Phase | Spec |
|---|--------|--------------|-------|------|
| 1 | **Kisten-Hülle (Shell)** | Inhalt → Hülle; Shell nicht doppelt in Liste | 3 | [§7.1.1](#711-shell-kisten-hülle) |
| 2 | **Fix vs. Zusatz** | **Accordion**, default eingeklappt | 3 | [§22.2](#222-fix-vs-zusatz-accordion) |
| 3 | **Charge / QR** | Scan-Matrix + QR-Ableitung; bulk: Confirm | 4 | [§6.3](#63-ergebnis-matrix), [§6.6](#66-qr--charge-ableitung) |
| 4 | **Aus Kiste nehmen** | MW, nur `pack`, Zeile im Sheet | 3 | [§22.4](#224-aus-kiste-zurückholen) |
| 5 | **Retour-Stapel** | Eine Sheet-Queue «Schritt x/y» | 6 | [§22.5](#225-retour-stapel) |
| 6 | **Container-Batch** | Scan / Liste / neu | 3 | [§7.7](#77-kiste-anlegen--zuordnen-materialaddcratesheet) |
| 7 | **MW = Ersteller** | Volle MW-Rechte, **kein** Handoff/Notfallmodus | 2 | [§19.1](#191-handoff-banner--readonly-matrix), [§17.12](#1712-mw-notfall-bei-gruppen-handoff) |
| 8 | **Werkstatt** | Badge + Scan `in_repair`, kein Hard-Block v1 | 4 | [§22.8](#228-werkstatt--reparatur) |
| 9 | **Live-Sync** | 5 s mit anderem User, sonst 20 s | 4–5 | [§22.9](#229-live-sync) |
| 10 | **Frühe Vorschau** | Empty-State «MW packt noch» | 2 | [§22.10](#2210-frühe-pack-vorschau) |

### 22.2 Fix vs. Zusatz — Accordion

Im `MaterialCrateCheckSheet`: Abschnitte **Aus Packliste**, **Fix**, **Zusatz** als **Accordion** (nicht flache Liste). Standard **eingeklappt** — Summary zeigt Fortschritt («Fix 2/5»). Sortierung intern wie `packContainerItemSectionsForContainer`.

### 22.4 Aus Kiste zurückholen

Nur **MW**, nur Step **`pack`** (`packCrateShowPullFromContainer`). Zeile ⋮ → «Aus Kiste nehmen» — assign-up / bestehende API. Nicht in `issue`/`return`.

### 22.5 Retour-Stapel

`MaterialReturnCrateSheet` verarbeitet `pendingReturnCrateBatch`-äquivalent **intern**: nach Submit mehrere Schritte ohne Sheet schliessen; Header «Schritt {i} von {n}».

### 22.8 Werkstatt / Reparatur

| Ort | Verhalten |
|-----|-----------|
| Scan | `in_repair` — Hinweis «in Werkstatt» |
| Zeile `pack` | Badge **«Werkstatt»** wenn Bestand betroffen |
| Move | **nicht** blockieren v1 — MW-Entscheidung |

Schaden/Foto: bestehende **Meldungen** → Werkstatt ([§20.4](#204-schaden--foto)).

### 22.9 Live-Sync

| Bedingung | Intervall |
|-----------|-----------|
| Tab `pack-journey` / `packs` aktiv **und** ≥1 anderer User mit offener Activity/Pack-Session | **5 s** `GET pack-items` |
| nur eigener User | **20 s** |
| App aus Hintergrund | sofort **refresh** |
| Fremde Buchung | Liste aktualisiert; dezent «Packliste aktualisiert» |
| Eigener Konflikt | Dialog §6.5 |

Voraussetzung für 5 s: leichtes Presence / «activity viewers» (Phase 12); bis dahin Fallback 20 s.

### 22.10 Frühe Pack-Vorschau

Gruppe bei `submitted` / `approved` / `packing` (`memberAwaitingMwPack`): Journey readonly, Empty-State «MW packt noch — {n} Positionen geplant», keine Buchungs-Buttons.

---

## Siehe auch

- [README.md](./README.md) — Konzept und Motivation
- [pack-workflow-rules.md](../pack-workflow-rules.md) — fachliche Regeln (weiter gültig)
- [qr/link-schema.md](../../qr/link-schema.md) — QR-URL und Charge-Auflösung
- [pack-step-ui.md](../pack-step-ui.md) — heutige Dual-Panel-Schicht (Legacy)
- [devices/pack-workflow.md](../../devices/pack-workflow.md)
