# Onboarding — Spezifikation

> Überarbeitung des Department-Onboardings: Setup-Wizard, persistenter Hub, thematische In-App-Touren.
> Referenz-Inspiration: Inventory ONE (Willkommen → Hub → Spotlight-Touren auf echten Seiten).

**Stand:** August 2026 — Hub unter Hilfe → Einrichtung · Spotlight-Touren live · Dokumentation-Tab (Happy Path) · **#4 Einführungsrunde** (Touren + Rollenfilter) · **Zielgruppe:** Frontend + Backend

**Implementierung:**

- [`OnboardingHubView.vue`](../../frontend/src/views/onboarding/OnboardingHubView.vue) — Touren + Einrichtungsassistent (Checkliste nur MW/DC)
- [`HelpDokumentationView.vue`](../../frontend/src/views/help/HelpDokumentationView.vue) — Happy Path + Mini-FAQ
- [`onboardingChecklist.ts`](../../frontend/src/utils/onboardingChecklist.ts) — Checkliste + Auto-Erkennung
- [`onboardingTours.ts`](../../frontend/src/config/onboardingTours.ts) · [`useOnboardingTour.ts`](../../frontend/src/composables/useOnboardingTour.ts) · [`OnboardingTourOverlay.vue`](../../frontend/src/components/onboarding/OnboardingTourOverlay.vue)
- [`onboardingGate.ts`](../../frontend/src/utils/onboardingGate.ts) — `canUseDepartmentOnboarding` (Checkliste) vs. `canUseDepartmentTours` / `canUseHelpEinrichtung`
- [`MyDepartmentSettingsView.vue`](../../frontend/src/views/settings/MyDepartmentSettingsView.vue) — Admin-Reset

**Entfernt (2025):** Vollbild Welcome/Setup, `DepartmentOnboardingWizard`, Resume-Button in `AppLayout`.

> **Pflicht bei UI-Änderungen:** siehe [§15 Wartung](#15-wartung--checkliste-bei-ui-änderungen).

---

## 0. UI — Hilfe → Einrichtung

Der Hub lebt unter **`/:departmentId/help/einrichtung`** (Shell: `HelpView`). Dokumentation unter **`/:departmentId/help/dokumentation`**.

### Accordion / Inhalt

| Panel | Verhalten |
|-------|-----------|
| **Geführte Touren** | Immer sichtbar; Liste rollenabhängig gefiltert |
| **Einrichtungsassistent** | Nur MW/DC; aufgeklappt solange Checklist-Punkte offen |

Mobile-first über `PageShell`, Checkliste + Tour-Karten.

---

## 1. Zielbild

Drei getrennte Concerns — nicht vermischen:

| Concern | Frage | Steuerung |
|---------|-------|-----------|
| **Setup** | Ist das Department eingerichtet? | Checkliste (department-weit + Fortschritt pro User) |
| **Lernen** | Welche Funktionen kenne ich? | Thematische Touren (persönlich, versioniert) |

---

## 2. URL-Strategie

### 2.1 Grundsatz

| Bereich | URL-Muster | Begründung |
|---------|------------|------------|
| **Setup & Hub** | Eigene Routes unter `/:departmentId/onboarding/…` (Welcome/Setup) · Hub unter **`/:departmentId/help/einrichtung`** | Vollbild für Erststart; Checkliste im Hilfe-Bereich |
| **Spotlight-Touren** | **Normale Seiten-URL + Query-Parameter** | Nutzer lernt auf der echten Seite; kein paralleles Tour-Layout |

Touren laufen **auf der bestehenden Route** — analog Inventory ONE:

```text
app.inventory-one.com/#/receivers?onboardingTourStep=who-has-what-step
```

Bei eMatChef (Vue Router, History-Mode):

```text
/{departmentId}/materials?onboardingTour=material-create&onboardingTourStep=1
/{departmentId}/activities?onboardingTour=activity-create&onboardingTourStep=category-tabs
/{departmentId}/settings/categories?onboardingTour=categories&onboardingTourStep=2
```

### 2.2 Query-Parameter (Touren)

| Parameter | Typ | Pflicht | Beschreibung |
|-----------|-----|---------|--------------|
| `onboardingTour` | string | ja | Tour-ID (stabil, kebab-case), z. B. `material-create` |
| `onboardingTourStep` | string \| number | nein | Schritt: Zahl (`1`, `2`) oder Slug (`recipient-types`, `new-button`) |

**Regeln:**

- Fehlt `onboardingTourStep` → Tour startet bei Schritt 1.
- Unbekannte Tour-ID → Query ignorieren, Seite normal rendern (kein Fehler).
- «Weiter» → `router.replace` mit gleicher Path, aktualisiertem `onboardingTourStep` (kein History-Spam).
- «Überspringen» / «Fertig» → Query-Parameter entfernen (`router.replace({ query: {} })`).
- Deep-Link aus Hub: Navigation zur Ziel-Route **mit** Query — Nutzer landet direkt im Tour-Schritt.

**Warum Query statt eigener Tour-Route?**

- Kein Duplikat-Layout; Spotlight highlightet echte DOM-Elemente.
- Bookmark-/Teilbarkeit: Link zeigt dieselbe Seite wie im Alltag.
- Router-Guards und Berechtigungen der Zielseite greifen unverändert.
- Bei UI-Refactors nur Tour-Definition + `data-onboarding`-Targets anpassen.

### 2.3 Routes (Setup & Hub)

| Route | Layout | Inhalt |
|-------|--------|--------|
| `/:departmentId/onboarding/welcome` | Vollbild, **keine Sidebar** | Willkommen, «Einrichtung starten» |
| `/:departmentId/onboarding/setup` | Vollbild | Setup-Wizard Phase 1 |
| `/:departmentId/help` | Normale App-Shell | **Hilfe** mit Tabs (Subnav) |
| `/:departmentId/help/einrichtung` | Tab «Einrichtung» | Touren (alle berechtigten Rollen) + Checkliste (MW/DC) |
| `/:departmentId/help/dokumentation` | Tab «Dokumentation» | Happy Path + Mini-FAQ |

Nach Abschluss oder «Später» → Redirect auf `/:departmentId/dashboard`.

---

## 3. User-Flows

### 3.1 Erster Besuch (MW / Depchef)

```text
Login → Department gewählt
  → /{deptId}/onboarding/welcome
  → /{deptId}/onboarding/setup  (Makro-Phasen)
  → «Fertig» oder «Später einrichten»
  → /{deptId}/dashboard
```

Auto-Open nur wenn: Rolle MW/DC · kein Grossanlass-Dept · Setup nicht abgeschlossen.

### 3.2 Hilfe-Bereich (Sidebar: «Hilfe»)

Ein Sidebar-Eintrag **«Hilfe»** mit Subnav-Tabs (wie Konfiguration):

- **Einrichtung** — Checkliste, Setup-Fortsetzung (nur MW/DC)
- **Dokumentation** — FAQ/Handbuch, später Touren-Übersicht

Badge an «Hilfe»: offene Einrichtungs-Schritte (solange Checkliste nicht vollständig).

### 3.3 Tour aus Hub starten

```text
Hub → Karte «Material erfassen» → Start
  → router.push({
       path: `/${deptId}/materials`,
       query: { onboardingTour: 'material-create', onboardingTourStep: '1' }
     })
  → MaterialsView mountet → useOnboardingTour() liest Query → Spotlight-Overlay
```

### 3.4 Admin: Reset nach App-Update

**Einstellungen → Mein Department → Onboarding (Administration)**

| Reset-Typ | Wirkung | Use Case |
|-----------|---------|----------|
| **Soft Reset** | Neue Touren/Checklist-Items freischalten, `seenOnboardingVersion` senken | App-Update mit neuer Funktion |
| **Hard Reset** | Setup-Checkliste department-weit auf «offen» | Neues Department / kompletter Neustart |

Soft Reset setzt nur Version/Touren zurück — bestehende MW-Entscheidungen und Fortschritt bleiben erhalten.

---

## 4. Setup-Wizard — Makro-Phasen

Bestehende 10 Schritte (`ONBOARDING_TOTAL_STEPS`) bleiben als **Checklist-Items**; Erststart bündelt sie in 4 Phasen:

| Phase | Titel | Enthält (bestehend) | Pflicht |
|-------|-------|---------------------|---------|
| 1 | Dein Department | Adresse, Settings | Ja (Defaults vorausgefüllt) |
| 2 | Dein Team | Gruppe, Einladungen, Rollen | Empfohlen |
| 3 | Dein Lager | Kategorien-Vorlagen, Lageradresse, Regale | Empfohlen |
| 4 | Los geht's | Erstes Material, optional Mini-Ausleihe | Optional |

Welcome-Text: *«Ca. 5 Minuten — du kannst jederzeit pausieren.»*

Inline-Eingaben wo möglich; komplexe Schritte verlinken in Settings (`goToAndComplete`-Pattern beibehalten, Wizard schliesst).

---

## 5. Onboarding-Hub

### 5.1 Setup-Checkliste

Fortschrittsbalken + Items aus `DepartmentOnboardingState.completed`:

```text
☑ Department-Adresse
☑ Settings initialisiert
☐ Gruppe erstellt
☐ Nutzer eingeladen
…
```

Klick auf offenes Item → passende Settings-Route oder Setup-Phase.

### 5.2 Themen-Touren

| `onboardingTour` | Titel | Audience | Start-Route | Hinweise |
|------------------|-------|----------|-------------|----------|
| `material-create` | Material erfassen | MW/DC | Materials | — |
| `activity-create` | Aktivität anlegen | alle Tour-Rollen | Activities | Typ Aktivität, Single-Layout-Wizard |
| `activity-camp-create` | Lager (Camp) anlegen | alle + Camp-Recht | Activities | inkl. J+S-Toggle |
| `issue-return` | Packen & Ausgabe | MW/DC | Activities | Pack-Happy-Path |
| `categories` | Kategorien verwalten | MW/DC | SettingsCategories | — |
| `invite-users` | Team einladen | MW/DC | SettingsUsers | — |
| `default-coach` | Standard-Coach | MW/DC | SettingsActivities | J+S Defaults |

Status pro Tour: `offen` · `erledigt` (localStorage, tour-`version`).

Vollständiges Target-Inventar: [§15](#15-wartung--checkliste-bei-ui-änderungen).

### 5.3 Hub-Aktionen

- «Einrichtung fortsetzen» (wenn Setup offen)
- Link zu Einstellungen

---

## 6. Spotlight-Touren (In-App)

### 6.1 Technik

- Overlay-Komponente `OnboardingTourOverlay.vue` oder **driver.js**
- UI-Targets: `data-onboarding="material-new-btn"` an relevanten Elementen
- Tour-Definitionen zentral (JSON/TS), nicht in Views hardcoden

**Keine Screenshots in Live-Touren** — veralten bei UI-Änderungen. Screenshots optional nur als Vorschau-Karten im Hub.

### 6.2 Composable `useOnboardingTour()`

```typescript
// Pseudocode — Ziel-API
const { activeTour, activeStep, next, skip, finish } = useOnboardingTour()

// Beim Mount der View:
// 1. route.query.onboardingTour + onboardingTourStep lesen
// 2. Tour-Definition laden
// 3. Ziel-Element warten (nextTick / MutationObserver falls lazy)
// 4. Overlay positionieren
// 5. next() → router.replace mit neuem onboardingTourStep
// 6. finish() → Query löschen, completedTours persistieren
```

### 6.3 Beispiel Tour-Definition

```typescript
// frontend/src/config/onboardingTours.ts
export const onboardingTours = {
  'material-create': {
    version: 1,
    route: '/:departmentId/materials',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="material-new"]',
        titleKey: 'onboarding.tours.materialCreate.step1Title',
        bodyKey: 'onboarding.tours.materialCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="material-category"]',
        titleKey: 'onboarding.tours.materialCreate.step2Title',
        bodyKey: 'onboarding.tours.materialCreate.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="material-storage"]',
        titleKey: 'onboarding.tours.materialCreate.step3Title',
        bodyKey: 'onboarding.tours.materialCreate.step3Body',
      },
    ],
  },
} as const
```

### 6.4 Tour-Schritt-URLs (Beispiele)

```text
/{deptId}/materials?onboardingTour=material-create&onboardingTourStep=1
/{deptId}/materials?onboardingTour=material-create&onboardingTourStep=2
/{deptId}/materials?onboardingTour=material-create&onboardingTourStep=3

/{deptId}/settings/users?onboardingTour=invite-users&onboardingTourStep=recipient-types
```

Slug-Schritte (`recipient-types`) sind sinnvoll, wenn Schritte umbenannt/neu eingefügt werden — Zahlen reichen für stabile lineare Touren.

---

## 7. Datenmodell

### 7.1 Department (Backend — bestehend erweitern)

```text
onboarding.done_all              = "0" | "1"
onboarding.phase1_settings_done  = "0" | "1"
onboarding.version               = "3"          // App-Onboarding-Version
onboarding.reset_at              = ISO-8601
onboarding.reset_reason          = "app_update_2.4"
```

API (bestehend):

- `getDepartmentOnboardingStatus`
- `markDepartmentOnboardingDone`
- `resetDepartmentOnboardingDone` → bei Soft Reset nur Version/Flags, nicht zwingend `done_all`

### 7.2 Persönliche Präferenzen (neu, Backend)

Pro **Profil + Department** (nicht nur localStorage):

```typescript
interface OnboardingUserPrefs {
  seenOnboardingVersion: number
  completedTours: Record<
    string,
    { doneAt: string; tourVersion: number }
  >
  // Optional: Fortschritt Setup-Wizard wenn von localStorage migriert
  setupState?: DepartmentOnboardingState
}
```

Endpoint-Vorschlag:

```text
GET  /api/departments/{id}/onboarding-prefs
PATCH /api/departments/{id}/onboarding-prefs
```

Migration: bestehende localStorage-Keys (`onboarding_done_*`, `onboarding_state_*`) beim ersten Load ins Backend übernehmen.

### 7.3 App-Onboarding-Version (Frontend)

```typescript
// frontend/src/config/onboardingVersion.ts
export const ONBOARDING_VERSION = 3

export const ONBOARDING_CHANGELOG: Record<number, {
  label: string
  newTours: string[]
  newChecklistItems: string[]
}> = {
  3: {
    label: 'Pack-Workflow Tour',
    newTours: ['issue-return'],
    newChecklistItems: [],
  },
}
```

Login-Logik:

```text
if (user.seenOnboardingVersion < ONBOARDING_VERSION) {
  → neue Items/Touren als «Neu» markieren
  → Sidebar-Badge bei offenen Schritten
}
```

---

## 8. Settings-Integration

### 8.1 MW (Self-Service)

Block in **Einstellungen → Mein Department**:

```text
┌─ Einrichtung & Hilfe ─────────────────────────┐
│ Status: 7/10 erledigt                           │
│ [Hilfe → Einrichtung öffnen]  → /{deptId}/help/einrichtung │
└─────────────────────────────────────────────────┘
```

### 8.2 Admin (bestehend erweitern)

Block bleibt für `canManageJoinCode`; Text präzisieren:

- **Soft Reset:** «Neue Funktionen freischalten (Update X.Y)»
- **Hard Reset:** «Onboarding für alle zurücksetzen» (löscht auch lokale Prefs aller Mitglieder — mit Bestätigung)

Nach Reset: Redirect `/{deptId}/onboarding/welcome` optional nur für den Admin, nicht für alle User.

---

## 9. Rollen & Ausnahmen

| Rolle | Setup-Checkliste | Hub Einrichtung | Touren |
|-------|------------------|-----------------|--------|
| MW / DC | Ja | Ja | Alle |
| User (`u`/`user`) + L1–L3 | Nein | Ja (nur Touren) | `activity-create`; `activity-camp-create` nur wenn Camp anlegen erlaubt |
| SA / Org / Sub | Nein | Nein | — |
| Grossanlass-Dept | Nein (eigenes Konzept später) | — | — |

Guards: [`onboardingGate.ts`](../../frontend/src/utils/onboardingGate.ts) (`canUseDepartmentOnboarding` vs. `canUseDepartmentTours` / `canUseHelpEinrichtung`), Overlay in [`AppLayout.vue`](../../frontend/src/components/layout/AppLayout.vue) an `canUseTours`.

---

## 10. Migration vom Ist-Zustand

| Heute | Neu |
|-------|-----|
| Modal über Dashboard | Hub unter Hilfe → Einrichtung |
| Auto-Open immer | Nur wenn Setup offen (MW/DC) |
| Touren: — | Query auf normaler Seiten-URL |
| Reset nur dept-wide + localStorage | + persönliche Prefs, Soft/Hard, Version |

Bestehenden Wizard-Inhalt und `DepartmentOnboardingState` **weiterverwenden** — Präsentation und Navigation ändern.

---

## 11. Implementierung — PR-Schnitte

### PR1 — Fundament (Erstbesuch)

- [x] `OnboardingLayout` — mobile-first, zentriert
- [x] Routes: `onboarding/welcome`, `onboarding/setup`
- [x] Router-Guard: Dashboard → Welcome wenn Setup offen
- [x] Setup Makro-Phase 1 (Adresse + Settings)
- [x] AppLayout: Modal-Auto-Open entfernt; Resume → Setup-Route
- [x] Sidebar «Hilfe» mit Tab Einrichtung + Badge
- [ ] `OnboardingUserPrefs` Backend (optional; aktuell localStorage)

### PR2 — Setup umbauen

- [x] Hub unter `/help/einrichtung` mit Checkliste (10 Items)
- [ ] Setup Makro-Phasen 2–4 inline (Team, Lager, Material)

### PR3 — Touren

- [x] `useOnboardingTour()` + `OnboardingTourOverlay`
- [x] Query-Parameter `onboardingTour` + `onboardingTourStep`
- [x] `data-onboarding` an Kern-Views (Material, Aktivitäten, Settings, Pack-Hinweise)
- [x] Tour-Definitionen: `material-create`, `activity-create`, `activity-camp-create`, `issue-return`, …

### PR3b / Top-10 #4 — Einführungsrunde finalisieren

- [x] Dokumentation-Tab Happy Path + FAQ
- [x] User/L1–L3: Hub mit Aktivitäts-Touren (ohne Checkliste)
- [x] Aktivitäts-Touren am aktuellen Wizard inkl. J+S (Camp)
- [x] Pack-Tour (`issue-return`) für MW erweitert
- [x] Wartungs-Checkliste in diesem Dokument (§15)

### PR4 — Updates

- [ ] `ONBOARDING_VERSION` + Changelog
- [ ] Soft Reset Admin
- [ ] «Neu»-Badges

---

## 12. UI-Texte (Entwurf DE)

| Kontext | Text |
|---------|------|
| Welcome | «Willkommen bei eMatChef. In wenigen Schritten richten wir dein Material-Department ein.» |
| Settings MW | «Einrichtung & Hilfe — Status: 7 von 10 erledigt» |
| Admin Soft-Reset | «Neue Funktionen freischalten. Bestehende Daten bleiben erhalten.» |
| Tour weiter | «Weiter (Schritt {n} von {total})» |
| Tour überspringen | «Überspringen» |

i18n-Keys unter `onboarding.*` anlegen.

---

## 13. Offene Entscheidungen

1. **Soft Reset setzt `onboarding.done_all` zurück?** → Empfehlung: **Nein**, nur Version/Touren.
2. **Schritt-IDs: Zahl vs. Slug?** → Beides erlaubt; Slugs für erweiterbare Touren.
3. **Grossanlass-Onboarding** → separates Dokument, wenn Phase 1 Grossanlass steht.

---

## 14. Referenz-Links

- Inventory ONE Getting Started: Sidebar-Hub + Tour-Query auf Zielseite
- Tour-Config: [`frontend/src/config/onboardingTours.ts`](../../frontend/src/config/onboardingTours.ts)
- J+S (Camp-Tour-Abhängigkeit): [`docs/activities/js-material/README.md`](../activities/js-material/README.md)

---

## 15. Wartung / Checkliste bei UI-Änderungen

**Regel:** Wer UI an Elementen mit `data-onboarding="…"`, Wizard-Steps, «Neu»-Buttons, Aktivitäts-Typ-Chips, Pack-Tabs/Stepper oder Settings-Targets ändert, **muss** die Touren mitdenken — sonst brechen Spotlights still.

### Checkliste (vor Merge)

1. Existiert das Target noch? Selector in `onboardingTours.ts` stimmt?
2. Sind Tour-Schritte und Reihenfolge noch sinnvoll (inkl. Stepper vs. Single-Layout)?
3. i18n-Texte (`onboarding.tours.*`) noch korrekt?
4. Tour-`version` erhöhen, wenn Schritte sich ändern (alte «erledigt»-Markierung wird ungültig)?
5. Rollenfilter (`audience` / `requiresCampCreate`) noch passend?
6. Kurz vom Hub starten und die betroffene Tour durchklicken.

### Inventar Tour-ID → Targets → Code

| Tour-ID | Route | Wichtige Targets | Komponenten |
|---------|-------|------------------|---------------|
| `material-create` | Materials | `material-new`, `material-creation-individual`, `material-wizard-general`, `material-wizard-category` | `MaterialsView`, `MaterialCreateWizard` |
| `activity-create` | Activities | `activity-new`, `activity-type-activity`, `#activity-create-grunddaten`, `#activity-create-zeitraum` | `ActivitiesView`, `ActivityTypeChips`, `ActivityCreateWizardForm` — bei Tour: Typ-Chips auch wenn nur 1 Typ erlaubt (`ActivityCreateWizard`) |
| `activity-camp-create` | Activities | `activity-new`, `activity-type-camp`, `#activity-create-grunddaten`, `activity-camp-js-material`, `activity-wizard-next` | wie oben + J+S-Toggle |
| `issue-return` | Activities | `activities-list-filters`, `activities-packing-filter`; Detail: `activity-detail-packs-tab`, `activity-pack-stepper` | `ActivitiesView`, `ActivityDetailView`, `MaterialJourneyStepper` |
| `categories` | SettingsCategories | `settings-category-new`, `settings-category-list` | `CategoriesSettingsView` |
| `invite-users` | SettingsUsers | `settings-user-add` | `UsersSettingsView` |
| `default-coach` | SettingsActivities | `settings-js-coach`, `#js-default-coach-person-nr` | `ActivitySettingsView` |

Zentrale Definition: [`onboardingTours.ts`](../../frontend/src/config/onboardingTours.ts) (Kommentar am Dateianfang verweist hierher).
