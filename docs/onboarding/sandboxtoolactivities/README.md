# Aktivitäten-Touren: Hybrid-Sandbox

Übungsdaten für den Onboarding-Block **Aktivitäten** — sichtbar nur während einer laufenden Tour, dauerhaft gespeichert (kein Purge nach Tour-**Ende**).

**Implementierung:** `OnboardingSandboxService`, `POST /api/departments/{id}/onboarding-sandbox`, Frontend `onboardingSandbox.ts` + `useOnboardingTour`.

## 1. Ziel

- Nur bei **Tour starten** sichtbar (`include_onboarding_sandbox=1` / Header `X-Onboarding-Tour`)
- In normalen Listen **ausgeblendet**
- Pro **Department + User** nachvollziehbar (`onboarding_sandbox_state`)
- **Bleibt** nach Tour-Ende (Späteinstieger, Rolle **u**) — erst bei **Tour nochmals starten** wird der User-Übungsfall ersetzt
- Freigabe am Demo-Camp **automatisch** (Skip), damit Packen → Transport → Retour durchspielbar sind

## 2. Betroffene Touren

`category: 'activities'` in `frontend/src/config/onboardingTours.ts`:

`activity-create` → `activity-camp-create` → `activity-approve` (optional/skip) → `issue-return` → `issue-handoff` → `activity-store` → `activity-close` / `workshop-overview`

## 3. Hybrid-Modell

### 3.1 Pro Department (Shared Kit)

Einmal pro Department, Flag `onboarding_sandbox = true`, **ohne** User-Owner — bereitgestellt per `ensure`:

| Demodata | Name (Konstante) | Zweck |
|--|--|--|
| Eventstandort (Fallback) | `demo_venue` | Pack-Touren ohne eigenen Standort |
| Blache 64 | `Blache 64 (Onboarding)` | Pack-/Ausleih-Demo |
| Packpapier | `Packpapier (Onboarding)` | Verbrauch/Meter-Demo |
| Statikseil | `Statikseil (Onboarding)` | inventarisiertes Material |
| Demofahrzeug | `Demofahrzeug (Onboarding)` | Transport |

In normalen Kontakt-/Material-/Fuhrpark-Listen unsichtbar.

### 3.2 Create-Touren = interaktive Demo-Anlage

Bei **`activity-create`** / **`activity-camp-create`**:

1. Tour-Schritte bis Anlegen laufen normal (Nav → Neu → Typ → …).
2. Ab dem **ersten Speichern** während der Tour (inkl. **Eventstandort**) gilt: Entity bekommt `onboarding_sandbox = true` (Backend wertet Tour-Header/Query aus).
3. Vorgeschlagene Namen: **`demo_activity`** / **`demo_camp`** (Wizard vorbefüllt; User darf ändern — Flag bleibt).
4. **Keine** parallele Vorab-User-Aktivität durch `ensure` — die im Wizard angelegte Aktivität **ist** der Übungsfall.
5. Registry zeigt auf diese IDs; Folge-Touren nutzen dieselbe Demo weiter.

### 3.3 Pro User (Registry)

Pro `(department_id, user_id)` in `onboarding_sandbox_state`:

| Spalte | Bedeutung |
|--|--|
| `activity_id` | Demo-Aktivität (`demo_activity`) |
| `camp_id` | Demo-Camp (`demo_camp`) |
| `venue_id` | Demo-Eventstandort (interaktiv oder Kit-`demo_venue`) |
| `updated_at` / `last_for_tour` | letzter ensure / Register |

**Warum beide Typen:** Nur Camp scheitert für Rolle u / Aktivitäts-Tour; nur Aktivität deckt Freigabe/Lager nicht ab.

### 3.4 Restart («Tour nochmals durchgehen»)

- **Create-Touren** (`activity-create` / `activity-camp-create`): `reset: true` → Soft-Delete der eigenen User-Sandbox-Aktivität/Camp (und User-Demo-Standort ≠ Kit-`demo_venue`), Pack-Zeilen weg, Registry leeren; Shared Kit bleibt; User legt neu an.
- **Pack-/Folge-Touren**: kein Purge — die im Wizard angelegte Demo bleibt und wird weiterverwendet; ensure setzt nur Status/Pack zurück.

Kein Purge nur weil die Tour normal beendet wurde.

## 4. Sichtbarkeit

- Default-API-Listen: Sandbox raus
- Tour aktiv: **nur** Shared-Kit + **eigene** User-Sandbox-Aktivitäten/Adressen (keine Produktiv-Materialien in der Suche)
- Fremde User-Sandboxes bleiben unsichtbar
- Verfügbarkeits-API: bei Tour-Header ebenfalls nur `onboarding_sandbox`-Materialien

## 5. Verfügbarkeit / Mengen / Nebenwirkungen

1. Reservationen/Pack-Mengen aus `onboarding_sandbox`-Aktivitäten **ignorieren** für Normal-Verfügbarkeit
2. Kit mit hohem Bestand; Reset/ensure setzt Pack-Stand der User-Demo zurück
3. Demofahrzeug: Listen-Filter; blockiert keine echten Camps
4. Kit-Material eigener Bestandspfad
5. **Keine Buchhaltung:** `ActivityAccountingCostService` legt für Sandbox-Aktivitäten keine Follow-ups an; Rechnungs-Listen filtern sie aus
6. **Keine Nachrichten:** Inbox-Meldungen (MW/User, Issues, Retour, Pack-Kistencheck, Dept-Invites, …) werden für Sandbox-Aktivitäten nicht erzeugt
7. **Pack-Seed:** `issue-handoff` bekommt Packliste mit `quantity_issued` (Schaden-/Verlust-Icons); Store/Close mit Retour-Mengen

## 6. Freigabe-Skip & Tour-Flow

- Freigabe-Tour **optional** (`optional: true` auf `activity-approve`)
- Pack-Start: Camp auto-`approved` via ensure(forTour), auch ohne Freigabe-Tour
- Handoff-Tour: Demo auf **`at_event`** inkl. Packliste mit ausgegebenen Mengen (Schaden-/Verlust-Icons); MW **klickt** Journey-Schritte. Ohne MW-Recht setzt die Tour `packStep` selbst
- Packen → Transport → Retour → Einlagern auf Demodaten

## 7. API

### Ensure

`POST /api/departments/{id}/onboarding-sandbox`  
Body: `{ "forTourId"?: string, "reset"?: boolean }`

1. Shared-Kit sicherstellen  
2. Optional `reset` → User-Fälle ersetzen  
3. Create-Touren: **kein** Pre-Create von Aktivität/Camp  
4. Sonstige Touren: User-Fälle finden/anlegen, Status je `forTourId`  
5. Response: `{ activityId, campId, venueId, materialIds, vehicleId, statuses }`

### Create während Tour

- `POST /api/activities` und `POST /api/addresses` (sowie relevante Updates): bei Tour-Header/`include_onboarding_sandbox` → `onboarding_sandbox = true`
- Danach Registry aktualisieren (`registerActivity` / `registerVenue`)

Service: `backend/src/Service/Onboarding/OnboardingSandboxService.php`

## 8. Frontend

- `frontend/src/api/onboardingSandbox.ts` — `ensureOnboardingSandbox(dept, tourId, { reset })`
- `useOnboardingTour.ts`: Aktivitäten-Tour-Start → ensure; **Create-Touren** mit `reset: true` (alten Übungsfall ersetzen); Pack-/Folge-Touren ohne Purge (Demo weiterverwenden); Include-Flag solange Tour aktiv
- Wizard: bei Create-/Camp-Tour Namen `demo_activity` / `demo_camp` vorbefüllen
- Hinweis: Übungsdaten — ausserhalb der Tour ausgeblendet; Freigabe am Demo-Lager automatisch

## 9. Abgrenzung

- Kein Purge nach Block-Ende (nur bei Restart)
- Settings-/reine Material-Touren unverändert
- Kein Ersatz für globalen Dev-Demo-Reset (`docs/DEV-TOOLS-BACKLOG.md`, `app:dev-demo:reset`)

## 10. Wartung

Bei Änderungen an Aktivitäten-Touren, Create-Wizard oder Listen-Filtern diese Spec und die ensure-/Register-Logik mitdenken.
