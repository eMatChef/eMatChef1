# BusinessClock im Demo-System

**Stand:** 8. Oktober 2026. Konzeptsicht auf die BusinessClock als **allgemeine Department-Funktion**. Technische Details (Auflösung, Datenmodell, REST-API, Frontend, migrierte Stellen, Seed-/Reset-Commands) stehen **nur** in [../grossanlass/business-clock.md](../grossanlass/business-clock.md) und werden hier nicht kopiert. Bei Abweichungen gilt jene Datei bzw. der Code.

Übersicht: [README.md](./README.md) · Marken **IST / TEILWEISE / SOLL / OFFEN** wie dort.

## 1. Grundsatz

| Department | Zeit | Marke |
| --- | --- | --- |
| Normales Department | reale Zeit | **IST** |
| Demo-Department | reale Zeit plus isolierter Demo-Offset | **IST** |

Die Clock gehört zum **Department**, nicht zum Grossanlass. Die Uhr läuft nach einem Zeitsprung weiter (Offset statt eingefrorener Zeit). Es gibt keinen globalen Offset; mehrere Demo-Departments sind unabhängig.

## 2. Fachzeit vs. reale Systemzeit

| Begriff | Verwendet für |
| --- | --- |
| **Reale Systemzeit** | JWT, OAuth, Sessions, TOTP, Token-Ablauf, Rate-Limits, technische Security, Audit (`created_at`/`updated_at`). Läuft **nie** über die BusinessClock. |
| **Fachzeit (BusinessClock)** | zeitabhängige Fachlogik: heute/morgen/überfällig, Planungsfenster, Fristen, laufende und kommende Vorgänge, zeitabhängige Übersichten |

Sicherheitsrelevante Zeiten bleiben immer reale Systemzeit (**IST**).

## 3. Verändert keine Daten

Die BusinessClock verändert keine historischen Datenbankzustände. Ein Zeitsprung erfindet insbesondere keine Materialbewegung, keine Rückgabe und keine Aufgabenerledigung. Die Unterscheidung gespeichert / berechnet / geplant steht in [SEED-KONZEPT.md §5](./SEED-KONZEPT.md#5-zeitstrahl).

## 4. Zeitreise

- **IST:** Header-Popover (`ClockControl.vue`), nur bei `can_travel = true`, für jedes Department. Berechtigung serverseitig: Department-Mitglied und (Demo-Modus oder Kernel ≠ prod). Normales Department: keine Zeitreise. Details: [business-clock.md](../grossanlass/business-clock.md).
- **IST:** Zurücksetzen stellt den Ausgangspunkt des Szenarios her (nicht Offset 0), sofern ein Resolver existiert; sonst reale Zeit. Ein Szenario liefert seinen Ausgangspunkt über `ClockOriginResolverInterface`.
- **TEILWEISE:** Normales Demo-Department hat Offset, aber keinen Resolver ([scenarios/DEPARTMENT.md](./scenarios/DEPARTMENT.md)).
- **IST (Phase 1):** `ScenarioClockOriginResolver` liefert den Ausgangspunkt für Departments mit `demo_scenario_key`; Event und Camp delegieren vorläufig an den bisherigen Grossanlass-Ausgangspunkt, Materialverwaltung hat noch keinen. `GrossanlassClockOriginResolver` gilt nur noch für Departments ohne Schlüssel (bestehende Demo-Departments unverändert). Der Offset wird von keinem neuen Code gesetzt oder überschrieben.
- **SOLL (Seed-Architektur):** Jedes der drei Demo-Szenarien (`materialverwaltung`, `grossanlass-event`, `grossanlass-camp`) hat eine **eigene** Uhr am eigenen Department mit eigenem Ausgangspunkt. Der heutige `GrossanlassClockOriginResolver` (gilt für jedes Grossanlass-Department gleich) wird durch einen Resolver ersetzt, der pro Szenario-Schlüssel delegiert ([SEED-KONZEPT.md §7.2](./SEED-KONZEPT.md#72-szenario-interface)). Seed-Sync setzt den Offset nur bei fehlendem Offset und verstellt nie eine laufende Uhr; Szenario-Reset setzt die Uhr auf den Ausgangspunkt; Clock-Reset im Header ändert **nur** die Zeit ([SEED-KONZEPT.md §7.6–§7.7](./SEED-KONZEPT.md#76-sync-semantik)).
- **Bekannte Eigenschaft (IST):** Die Demo-Uhr läuft nach dem Setzen in Echtzeit weiter und überholt den Seed-Zeitstrahl (Befund P14 in [SEED-KONZEPT.md §3.1](./SEED-KONZEPT.md#31-ist-probleme)); Clock-Reset stellt den Ausgangspunkt wieder her.
- **SOLL:** Phasen-Schnellsprünge im Header (z. B. «Aufbau», «Anlassbetrieb», «Rückbau») über denselben `PUT …/clock`, sobald der Zeitstrahl ([SEED-KONZEPT.md §5](./SEED-KONZEPT.md#5-zeitstrahl)) existiert.
- **Bekannte Einschränkung (IST):** Eine Zeitreise remountet die aktuelle Route; ungespeicherte Formulare können verloren gehen.

## 5. Abdeckung der Fachlogik

| Aspekt | Marke |
| --- | --- |
| Migrierte Stellen (Planungsrunde, Beschaffungs-Fortschritt, Übersicht, Fahrkarte, Wunsch-Zeitraum, Pack-Freigabe) | **IST**, nur Grossanlass-Services |
| Zeitabhängige Logik ausserhalb Grossanlass (Aktivitäten, Ausleihe-Fristen, Inventur, Wartungsfälligkeit, Werkstatt-Erinnerungen, Retouren-Fälligkeit) | **SOLL**, je Stelle gegen den Code zu prüfen |
| Commands/Jobs mit Department-Kontext (z. B. `WorkshopOrderRemindersCommand`) | **SOLL**; Kontext muss explizit übergeben werden, sonst reale Zeit |

**OFFEN (Frage 8):** Welche Services normaler Departments sind zeitabhängig und migrieren auf die BusinessClock? Siehe [scenarios/DEPARTMENT.md](./scenarios/DEPARTMENT.md#8-offene-fragen).

## 6. Zeitzonen

**IST:** Demo- und Grossanlass-Zeiten sind **naive Wandzeit** (`YYYY-MM-DDTHH:mm:ss` ohne Zeitzone), wie `starts_at` und `planned_event_start`. Die API liefert `now` und `real_now` in dieser Form; der Header zeigt sie unverändert an, damit er mit den Backend-Vergleichen übereinstimmt. Der Offset ist die Differenz Zielzeit − reale Zeit (±5 Jahre). Ein `PUT` mit expliziter Zeitzone oder Offset im String (z. B. `…+00:00`) wird als absoluter Zeitpunkt gelesen; ohne Angabe gilt die PHP-Standardzeitzone des Servers, in der auch `real_now` formatiert wird.

**OFFEN (Frage 9):** Zeitzonen für öffentliche Seiten und Jobs sind nicht entschieden.
