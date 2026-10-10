# BusinessClock (Fachzeit, Demo-Zeitreise)

**Stand:** 8. Oktober 2026, Phase 1. Die Clock gehört zum **Department** (nicht zum Grossanlass); heute nutzen sie nur Grossanlass-Services. Beschreibt, was der Code heute tut. Alles unter «Phase 2» ist **nicht gebaut**.

## Drei Zeitbegriffe

| Begriff | Quelle | Verwendet für |
| --- | --- | --- |
| **Reale Systemzeit** | `new \DateTime()` / System-Uhr | JWT, Sessions, TOTP, OAuth-State, Token-Ablauf, Rate-Limits, technische Security, Audit (`created_at`/`updated_at`). Wird **nie** über die BusinessClock geführt. |
| **Business-Zeit** | `App\Service\Clock\BusinessClock` | Zeitabhängige Fachlogik eines Departments (bisher migriert: nur Grossanlass-Services): heute/morgen/überfällig, Planungsrunden-Auto-Schedule, Beschaffungs-Fortschritt, Fahrt-Freigabe, Fahrkarten-Verifikation, Wunsch-Zeiträume. |
| **Demo-Zeitreise** | Offset in `department` | Verstellbare Fachzeit für Demo-Departments (und in Nicht-prod-Umgebungen für Entwickler). |

## Auflösung `BusinessClock::now($context)`

Kontext = `Department` oder Department-ID (aus Route/Entity, nie aus Frontend-State als Vertrauensbasis).

| Kontext | Ergebnis |
| --- | --- |
| kein Kontext | reale Zeit |
| normales Department (prod) | reale Zeit — ein gespeicherter Offset wird ignoriert |
| `Department.demo_mode = true` (mit oder ohne Grossanlass) | reale Zeit + `demo_clock_offset_seconds` (NULL = 0) |
| kein Demo, Kernel `APP_ENV != prod` | Entwickler-Zeitreise mit demselben Offset (`mode = dev`) |

- Es gibt **keinen globalen Offset**; der Offset gehört zum einzelnen Department. Er ist weder global noch an die User-Session gebunden. Mehrere Demo-Departments sind voneinander unabhängig.
- `EMATCHEF_DEV_TOOLS=1` schaltet die Zeitreise **nicht** frei. Auf einem prod-Kernel (z. B. Develop-Droplet) reisen nur Demo-Departments.
- Offset statt eingefrorener Zeit: Nach einem Sprung auf 04.11. 10:00 läuft die Demo-Uhr weiter (10:01, 10:02 …).
- Die Mitgliedschaft in `Demo Grossanlass` wird **nicht** am Namen erkannt. `demo_mode` ist die einzige Markierung; der Name ist keine Berechtigungsgrenze. Die Migration markiert einmalig bestehende Departments mit dem Namen `Demo Grossanlass`.
- Commands/Jobs ohne Request müssen den Kontext explizit übergeben, sonst gilt reale Zeit.

## Datenmodell

`department`: `demo_mode BOOLEAN NOT NULL DEFAULT false`, `demo_clock_offset_seconds INT NULL`. Migration `Version20261007100000`. `DepartmentGrossanlassConfig` bleibt für Grossanlass-Daten (z. B. `planned_event_start`) zuständig.

## Demo-Ausgangspunkt und Seed-Zeitstrahl

«Zurücksetzen» ist über `ClockOriginResolverInterface` (Tag `app.clock_origin_resolver`) vom Fachbereich getrennt. Es gibt zwei Resolver: `ScenarioClockOriginResolver` für Departments mit `demo_scenario_key` (delegiert an das Szenario, siehe [demo/SEED-KONZEPT.md §7.0](../demo/SEED-KONZEPT.md#70-phase-1-gebauter-stand-ist)) und `GrossanlassClockOriginResolver` für Grossanlass-Departments ohne Schlüssel (Anlassbeginn minus 5 Tage, 09:00, Aufbauphase). Ein Demo-Department ohne Resolver wird auf die reale Zeit zurückgesetzt; ein eigener Ausgangspunkt für normale Demo-Departments ist über einen weiteren Resolver möglich, ohne neue Spalte (nicht gebaut).

`DemoGrossanlassSeedService` markiert das Department als `demo_mode` und setzt – nur wenn noch kein Offset gesetzt ist – die Uhr auf den Ausgangspunkt. Erneutes Seeden überschreibt eine verstellte Uhr nicht. Andere Dev-Departments (z. B. «Bootstrap Department») werden bewusst nicht als Demo markiert.

Zentraler Seed-Anker: E = Seed-Tag + 7 Tage. Termine relativ zu E: Vergangenheit (E−7 d, zurückgegeben), laufend (E−5 d, ausgegeben), kommende Stunden (E−5 d 14:00), kommende Tage (E+1/+2 d), Rückbau (E+12 d). Man kann ohne Re-Seed durch den Zeitstrahl reisen. «Zurücksetzen» stellt diesen Ausgangspunkt wieder her, nicht Offset 0.

## REST-API

`/api/departments/{id}/clock` (Controller `DepartmentClockController`)

- `GET` → `{ mode: real|demo|dev, now, real_now, offset_seconds, can_travel }`; `now`/`real_now` sind **naive Wandzeit** `YYYY-MM-DDTHH:mm:ss` ohne Zeitzone, wie alle Grossanlass-Zeiten (`starts_at`, `planned_event_start` …). Der Header zeigt sie unverändert an, damit er mit den Backend-Vergleichen übereinstimmt.
- `PUT { "now": "YYYY-MM-DDTHH:mm:ss" }` → Offset = Zielzeit − reale Zeit (±5 Jahre)
- `DELETE` → Demo: Ausgangspunkt; ohne Ausgangspunkt-Resolver und im Dev (kein Demo): reale Zeit

Berechtigung serverseitig: Mitglied des Departments (kleinste Demo-/Dev-Berechtigung, keine Department-Admin-Rolle nötig) **und** Department im Demo-Modus oder Kernel ≠ prod. Normales Department: `can_travel = false`, PUT/DELETE → 403.

## Frontend

`ClockControl.vue` im `TopHeader` (zwischen Glocke und User-Menü), nur sichtbar bei `can_travel = true` (für jedes Department, nicht an `is_grossanlass` gekoppelt). Anzeige `04.11.2026 10:30 · DEMO`, läuft lokal weiter. Popover: Datum, Uhrzeit, ±1 h, ±1 Tag, Zurücksetzen. API ausschliesslich über `apiClient` (`api/departmentClock.ts`), State in `stores/businessClock.ts`. Nach einer Zeitreise erhöht der Store `revision`; `AppLayout` nutzt sie im `key` der Route-Komponente, damit Ansichten über ihr normales Laden neu aufgebaut werden (kein paralleler Datenmechanismus). Keine Phasen-Schnellsprünge.

**Bekannte Einschränkung (bewusst):** Eine Zeitreise remountet die aktuelle Route und kann ungespeicherte Formulareingaben verwerfen. Ein eigener Refresh-/Event-Mechanismus ist nicht gebaut.

## Migrierte Zeitstellen

`GrossanlassPlanningRoundService` (Auto-Schedule, Runde öffnen/schliessen), `GrossanlassProcurementProgress` (laufende Aufgaben/Schichten), `GrossanlassUebersichtService` (heute/morgen-Buckets, Fahrt-Freigabe), `GrossanlassUserCardService` (Karte gedruckt, Fahrausweis verifiziert), `GrossanlassWishService` (Standard-Zeitraum), `GrossanlassPackService` (Fahrt-Freigabe). Alles andere (inkl. `GmailOAuthState`, Auth, Token) ist bewusst unverändert.

## Phase 2 (NICHT gebaut): Side-Effect-Sandbox

Inventar aus einer Code-Suche (vor Umsetzung je Adapter gegen den Code verifizieren). Heute löst ein Demo-Flow echte externe Wirkung aus, wenn die jeweilige Konfiguration vorhanden ist. Die Demo-Zeit sperrt nichts davon. Zu sperren bzw. umzuleiten:

- **E-Mail:** `Service/Mail/AppMailer` (Transport via `MailTransportResolver`); Versand aus Grossanlass-Flows (Runde geöffnet, Einladungen, Anfragen, Verifikation).
- **Gmail:** `GrossanlassGmailApi`, `GmailOAuthClient`, `Mailbox/GmailMailboxProvider` (Lesen und Entwürfe/Versand im verbundenen Postfach).
- **Outlook:** `Mailbox/OutlookGraphApi`, `OutlookOAuthClient`, `OutlookMailboxProvider`.
- **MiData/Hitobito:** `HitobitoApiClient`, `HitobitoOAuthClient`, `MiDataGroupImportService`.
- **Lieferantenkommunikation:** Anfragen/Offerten an Lieferanten aus der Beschaffung (Versand über Mailbox/AppMailer, Lieferantenportal).
- **Öffentliche Kontakte:** `Controller/Public/*` (Kontaktformular, Gast-Freigaben/QR), `GrossanlassInquiryWebLookup`.
- **Sonstige externe Writes:** `GrossanlassPlaceGeocoder` (externer Geocoder-Aufruf), `MediaUrlImportService` (Abruf fremder URLs), Uploads in Medien-/Dokumentenspeicher.

Offener Punkt für Phase 2: Sperre pro Adapter anhand von `demo_mode` (nicht des Namens) mit Ersatz durch Protokoll/Outbox.

## Demo seeden, aktualisieren, zurücksetzen

| Command | Bedeutung für die Demo-Zeit |
| --- | --- |
| `app:create-role-users --with-ga-demo` | **Bestehende Demo aktualisieren** (seit Phase 0 löscht der Command nichts; Freigabe über `EMATCHEF_ENV_NAME`, siehe [demo/SEED-KONZEPT.md §3.2](../demo/SEED-KONZEPT.md#32-phase-0-absicherung-ist)). Verwendet `Demo Grossanlass` wieder, hält `demo_mode`, legt keine doppelten User/Memberships/Einsätze an. Eine verstellte Demo-Zeit bleibt erhalten; nur bei fehlendem Offset (frisches Department) wird der Ausgangspunkt gesetzt. Mit `--delete-demo-users` (nur local, oder develop mit `EMATCHEF_DEMO_DESTRUCTIVE=1`) werden Konten mit exakter Adresse aus `demo-accounts.json` gelöscht und neu angelegt (neue User-IDs, Memberships der gelöschten User in anderen Departments entfallen). |
| `app:demo-grossanlass:wipe --confirm=<Name>` danach `app:create-role-users --with-ga-demo` | **Echter Demo-Reset** (Wipe nur für `demo_mode`-Departments und mit Freigabe für löschende Befehle). Das Department wird gelöscht und frisch angelegt, inklusive Demo-Zeit am Ausgangspunkt. |
| Header-«Zurücksetzen» (`DELETE …/clock`) | Nur die Demo-Zeit, keine Daten. |
| `app:dev-demo:reset` | Nur Rollen-User (ohne `--with-ga-demo`), berührt weder Department noch Demo-Zeit. |

Hinweis: Wiederholte Läufe von `app:create-role-users` über bestehende Demo-User scheitern ohne die separaten Seed-Fixes (FK `activity_grossanlass_wish_response.updated_by_user_id` beim Löschen, `uniq_membership_one_primary_per_user` bei `--skip-delete` mit altem Demo-Department). Diese Fixes sind nicht Teil der Clock-Änderung.
