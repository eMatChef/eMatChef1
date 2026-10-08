# Seed-Konzept

**Stand:** 8. Oktober 2026 (Architektur-Review), Phase 0 «Absichern» (umgesetzt, §3.2), Phase 1 «Gemeinsame Infrastruktur» (umgesetzt, §7.0) und Phase 2 «Demo-Organisationen» (umgesetzt, §7.11). Tests und Migrationsprüfungen liefen nur gegen isolierte Wegwerf-Datenbanken; keine Seeds, Resets oder Migrationen auf der Entwicklungsdatenbank `mvdb`. Gemeinsame Bausteine und Regeln für alle Demo-Seeds. Szenario-spezifischer Inhalt: [scenarios/DEPARTMENT.md](./scenarios/DEPARTMENT.md), [scenarios/GROSSANLASS.md](./scenarios/GROSSANLASS.md). Übersicht: [README.md](./README.md). Marken **IST / TEILWEISE / SOLL / OFFEN** wie dort.

**Lesehinweis:** §2–§4 beschreiben den Ist-Zustand und seine Probleme. §7 ist die empfohlene Soll-Architektur, §8 der Umgang mit bestehenden Commands und Daten, §9 die Umsetzungsphasen mit Tests, §10 die offenen Entscheidungen. Nichts in §7–§9 ist gebaut.

## 1. Zweck

Seeds erstellen zusammenhängende Szenarien mit realistischen Daten, Beziehungen, Mengen und Zuständen. Sie verwenden die bestehenden Domain-Strukturen (Entities, Services), keine zweite Schreibschicht.

**Beschlossenes Zielbild (SOLL):** drei voneinander unabhängige Demo-Departments, jedes mit eigener BusinessClock, mehreren Prozesszuständen und unabhängigem Reset:

| Szenario | Schlüssel (Vorschlag) | Inhalt |
| --- | --- | --- |
| Materialverwaltung | `materialverwaltung` | Lager, Gruppen, Benutzer, mindestens eine Aktivität je vorhandenem Typ, Materialbedarf, Packen, Ausgabe, Rückgabe, Inventur, Werkstatt. [scenarios/DEPARTMENT.md](./scenarios/DEPARTMENT.md) |
| Grossanlass Event | `grossanlass-event` | Bauprojekte, Ressorts, Beschaffung, Wareneingang, Packen, Logistik, Rückbau, Weiterverkauf. [scenarios/GROSSANLASS.md](./scenarios/GROSSANLASS.md) |
| Grossanlass Camp | `grossanlass-camp` | Lagerinfrastruktur, Gruppen, Zelte, Küche, Materialverteilung, Transporte, Retouren. [scenarios/GROSSANLASS.md](./scenarios/GROSSANLASS.md) |

Gewünschtes Verhalten (SOLL, verbindlich für §7–§9):

| Vorgang | Verhalten |
| --- | --- |
| Develop-Deployment | fehlende Demo-Daten automatisch ergänzen, bestehende erhalten (**Sync**) |
| Sync | idempotent, keine Duplikate, keine ungewollten Statusänderungen |
| Reset | gezielt je Szenario, ausfallsicher, löscht keine fremden Daten |
| Clock-Reset | nur Zeit |
| Browser-Reload | nur Daten laden, nie schreiben |
| Staging / Production | niemals automatische Demo-Resets; Develop mit `APP_ENV=prod` nur mit expliziter Freigabe |
| Externe Wirkung | Demo-Seeds schreiben nichts nach aussen |

## 2. IST-Architektur

### 2.1 Bausteine

| Baustein | Ort | Was er tut |
| --- | --- | --- |
| `app:create-role-users [--skip-delete] [--with-ga-demo]` | `Command/CreateRoleUsersCommand` (475 Z.) | Löscht (ohne `--skip-delete`) Demo-User, legt Superadmin, 8 Rollen-User, 9 GA-User, Demo-Lieferant an, setzt Test-TOTP. Mit `--with-ga-demo` zusätzlich Department + Szenario. Alles in einem Command, ohne Transaktion. |
| `app:dev-demo:reset [--e2e-password] [--skip-e2e]` | `Command/DevDemoResetCommand` | Ruft `app:create-role-users` **ohne Optionen** auf (also mit Löschen, ohne GA-Demo), danach `app:ensure-e2e-user`. Der Name «reset» ist irreführend: Department, Daten und Demo-Zeit werden nicht angefasst. |
| `app:recreate-test-users` | `Command/RecreateTestUsersCommand` | Löscht und legt `admin@/manager@/user@ematchef.ch` (Passwort `test!ematchef`) neu an. Siehe P8. |
| `DemoGrossanlassSeedService` | `Service/Bootstrap` (626 Z.) | `ensureDepartment` (Department `Demo Grossanlass`, `demo_mode`, Config, Kalender, Haupt-Aktivität), `ensureDemoScenario` (Ressort-Baum, Zuordnungen, Zusagen, Orte, Einsätze, Fahrkarte), `ensureTimeline`, `ensureDemoClock`. Muster `ensure*` = suchen, sonst anlegen. |
| `DemoGrossanlassEventJobsSeedService` + `app:demo-grossanlass:event-jobs` | `Service/Bootstrap`, `Command/` | 20 Bauaufträge mit Material (Gruppen + Wünsche) in einem **bereits existierenden** Grossanlass-Department `Demo-Grossanlass-Event`. Legt das Department nicht an (`seedByName` wirft sonst), setzt kein `demo_mode`. Braucht User `ga-ok`/`ga-mw`/`superadmin` aus dem Rollen-User-Seed. |
| `DemoSupplierSeedService` | `Service/Bootstrap` | Testfirma + `supplier@demo.ematchef.ch`; idempotent (`ensure*`). |
| `DemoGrossanlassWipeService` + `app:demo-grossanlass:wipe [--name]` | `Service/Bootstrap`, `Command/` | Löscht ein Grossanlass-Department samt Fachdaten (rohe SQL-`DELETE`s, dann `DepartmentResetService::resetDepartment`, dann Department). |
| `DepartmentResetService` | `Service/` | Allgemeiner Daten-Reset eines Departments (Aktivitäten, Material, Lager, Gruppen …); wird auch vom REST-Endpunkt `resetDb` (nur `isDevToolsEnabled`) genutzt. |
| `DevBootstrapContextService` | `Service/Bootstrap` | Liefert «die erste sichtbare Organisation / das erste sichtbare Department» (sonst `Bootstrap Organisation/Department`). Hier landen die Rollen-User. |
| `InboxDemoSeedService` | `Service/` | Musterdaten für die Nachrichtenzentrale; mit festen IDs `demo-um-001` …, kein Szenario-Bezug. |
| `BusinessClock`, `ClockOriginResolverInterface`, `GrossanlassClockOriginResolver` | `Service/Clock`, `Service/Grossanlass` | Offset je Department; **ein** Resolver: Anlassbeginn − 5 Tage, 09:00, für jedes Grossanlass-Department mit Config. Details: [business-clock.md](../grossanlass/business-clock.md). |
| `DemoAccounts`, `demo-accounts.json` | `Util/`, `backend/data/seeds/dev-demo/` | Einzige Quelle der Demo-Konten (Domain `demo.ematchef.ch`, Passwort, Test-TOTP). |
| `DevEnvironmentService::isDevToolsEnabled()` | `Service/` | `Kernel ≠ prod` **oder** `EMATCHEF_DEV_TOOLS=1`. Einzige Schranke aller Seed-/Wipe-Commands (ausser `app:recreate-test-users`, siehe P8). |

Die bestehenden Seed-Services kennen weder die Registry noch den Szenario-Schlüssel noch das Ledger. Das Gerüst dafür ist seit Phase 1 vorhanden (§7.0), wird aber von keinem Seed genutzt.

### 2.2 Umgebung und Deployment (IST)

- Develop **und** Staging laufen mit `APP_ENV=prod` und setzen beide `EMATCHEF_DEV_TOOLS=1` (`deploy/docker-compose.override.develop.example.yml`, `…staging.example.yml`, `deploy/*-droplet.env.example`). `isDevToolsEnabled()` kann sie daher **nicht unterscheiden**. Production setzt das Flag nicht (`…prod.example.yml`).
- `cd-develop.yml` führt `deploy/prod-update.sh reset|up` aus: `git reset`, Container neu, Warten auf Backend-Entrypoint (Composer + `doctrine:migrations:migrate`), Cache-Warmup. **Kein Seed-Schritt.** Demo-Daten auf Develop entstehen heute nur durch manuell ausgeführte Commands.
- `backend/docker-entrypoint.sh` läuft in **jeder** Umgebung bei jedem Backend-Start (Migrationen). Er ist deshalb kein geeigneter Ort für Seeds.
- `MAILER_DSN` fällt auf `null://null` zurück; Develop/Staging nutzen laut [mail/README.md](../mail/README.md) Mailtrap.

### 2.3 Externe Writes (IST, Code gelesen)

In `Service/Bootstrap/*`, `CreateRoleUsersCommand`, `DevDemoResetCommand` und `EnsureE2eUserCommand` finden sich keine Aufrufe von Mailer, HTTP-Client, Gmail/Outlook/Hitobito, Geocoder oder Medien-Import. Die Seeds schreiben nur in die eigene DB. Das ist **nicht** durch eine Sperre abgesichert, sondern Zufall der aktuellen Implementierung; die Side-Effect-Sandbox ([README §5](./README.md#5-demo-sicherheit-und-side-effect-sandbox)) ist nicht gebaut. Seeds, die Domain-Services statt direkter Entity-Writes verwenden (SOLL, [README §1](./README.md#1-ziele-und-grundprinzipien)), können über diese Services externe Wirkung erben; deshalb braucht jeder Seed-Schritt einen Test «kein externer Aufruf» (§9).

## 3. Isolation und Idempotenz

**Anforderungen an jeden Seed (SOLL):**

- **Wiederholbar und idempotent.** Nochmaliges Ausführen legt nichts doppelt an und ändert keinen vom Benutzer fortgeschrittenen Zustand zurück. Vorbild für das Suchen vor dem Anlegen (**IST**): `ensure*`.
- **Department-isoliert.** Ein Seed arbeitet ausschliesslich im eigenen Demo-Department. Er darf andere Departments, Organisationen und Szenarien weder verändern noch löschen. Zuordnung über `demo_mode` und einen Szenario-Schlüssel, nie über den Department-Namen.
- **Verstellte Demo-Zeit bleibt.** Erneutes Seeden überschreibt einen gesetzten Offset nicht (**IST** für Grossanlass; für andere Szenarien **SOLL**).
- **Reale Zeit in Audit-Spalten.** `created_at`/`updated_at` bleiben Systemzeit; fachliche Zeitstempel folgen dem Zeitstrahl.

### 3.1 IST-Probleme

**Status nach Phase 0 (siehe §3.2):** P1, P4, P7 (Teil), P8 und P12 (Teil) sind entschärft; P2/P3 sind für den Standardlauf umgangen, nicht an der Wurzel behoben. Die Tabelle beschreibt den Befund vom 8. Oktober 2026; die Spalte «Folge» gilt vor Phase 0.

Befunde aus dem Code-Review vom 8. Oktober 2026. «Belegt» = im gelesenen Code nachvollzogen, nicht ausgeführt.

| # | Problem | Beleg | Folge |
| --- | --- | --- | --- |
| P1 | **Löschen trifft auch echte Konten.** Die Löschschleife in `app:create-role-users` wählt jedes Profil mit Demo-Domain **oder** `str_ends_with($email, '@ematchef.ch')`. | `CreateRoleUsersCommand` Löschschleife | Auf Develop/Staging werden alle `*@ematchef.ch`-Konten (ausser Demo-Superadmin und E2E-Smoke) samt Memberships gelöscht, auch echte Testkonten des Teams. Die Legacy-Mail-Migration deckt nur die Konten aus `demo-accounts.json` ab. |
| P2 | **FK `updated_by_user_id` (bekannter Fehler 1).** Die DB-Constraint `fk_grossanlass_wish_response_updated_by` wurde in `Version20260630120000` **ohne** `ON DELETE` angelegt (also NO ACTION), das Entity-Mapping sagt `onDelete: 'SET NULL'` (Abweichung Mapping ↔ DB). `reassignCreatedByReferences` setzt nur `created_by_user_id` von vier Entities um. | Migration Z. 148 ff.; `ActivityGrossanlassWishResponse` Z. 56; `reassignCreatedByReferences` | Löschen eines Users, der eine Wunsch-Antwort zuletzt bearbeitet hat, scheitert mit FK-Verletzung. Es gibt weitere `created_by`/`updated_by`-Spalten (z. B. `DepartmentCalendarPeriod`, `Activity`, `User.createdBy`), die nicht umgehängt werden; Vollständigkeit nicht geprüft. |
| P3 | **Primär-Mitgliedschaft (bekannter Fehler 2).** `createMembership`/`updateMembership` setzen `is_primary` für das Ziel-Department, ohne eine bestehende Primär-Mitgliedschaft desselben Users zu lösen. Der Index `uniq_membership_one_primary_per_user` ist in **keiner Migration und keinem Entity-Mapping** im Repository definiert (Volltextsuche), existiert also nur in bestehenden Datenbanken. | `CreateRoleUsersCommand::createMembership/updateMembership`; Suche in `migrations/`, `src/` | Mit `--skip-delete` und einem User, der schon anderswo primär ist (altes oder fremdes Department), schlägt der Flush fehl. Schema-Drift: frische DBs (`bootstrap-empty-postgres.sh`, `schema:create`) haben den Index womöglich nicht und verhalten sich anders. **OFFEN:** Herkunft des Index klären. |
| P4 | **Rollen-User landen in einem beliebigen Department.** `findOrCreateOrganisationAndDepartment` nimmt die erste für Zuweisung sichtbare Organisation und deren erstes sichtbares Department. | `DevBootstrapContextService` | Auf einer DB mit echten Departments werden Demo-Rollen-User (mit bekanntem Passwort) Mitglieder eines **echten** Departments. Reihenfolge ist nicht stabil. |
| P5 | **Sync setzt Zustand zurück.** `ensureEinsatz` überschreibt bei jedem Lauf `status`, `delivery`, `qty`, Zeiten, `tripReleasedAt = null`, `packed = false` und ruft `ensureDefaultPack` auf. Der Seed-Zeitstrahl (`ensureTimeline`) nutzt dieselbe Funktion. | `DemoGrossanlassSeedService::ensureEinsatz` | Ein erneuter Seed macht Fortschritt der Demo (z. B. «ausgegeben» → «zurückgegeben», freigegebene Fahrt, gepackt) rückgängig. Ungewollte Statusänderung. |
| P6 | **Seed-Identität steckt in Freitext.** Einsätze werden über `who LIKE '%[demo:v1:…]%'` gefunden, Event-Jobs über Gruppenname und `SEED_TAG` in Beschreibungen, Department über **Name** + Organisation. | `ensureEinsatz`, `ensureDepartment`, `EventJobs…::findBauten` | Wer das Feld bearbeitet oder das Department umbenennt, erzeugt beim nächsten Sync Duplikate oder ein zweites Department. Name als Schlüssel widerspricht [README §2](./README.md#2-demo-departments). `ensureDepartment` setzt `demo_mode` auf **jedes** gleichnamige Grossanlass-Department der Organisation. |
| P7 | **Wipe ist nicht atomar und nicht auf Demo begrenzt.** `wipeDepartment` führt `deleteGrossanlassDomain` ohne Transaktion aus, danach `resetDepartment` (eigene Transaktion) und weitere `DELETE`s wieder ausserhalb. `wipeByName`/`--name` prüft nur `isGrossanlass()`, **nicht** `demo_mode`. | `DemoGrossanlassWipeService` | Abbruch mittendrin hinterlässt ein halb gelöschtes Department. `--name=<echtes Grossanlass-Dept>` löscht ein nicht-Demo-Department. Nur Grossanlass wipebar; normale Departments haben keinen Szenario-Wipe. Das Department wird gelöscht (neue ID, Memberships, `last_used_department`, öffentliche Links, QR-Codes verlieren Bezug). |
| P8 | **`app:recreate-test-users` hat keine Schranke.** Der Constructor injiziert kein `DevEnvironmentService`; der Command läuft in jeder Umgebung. Er legt Konten auf der **echten** Domain `@ematchef.ch` mit dem öffentlich bekannten Passwort an und entfernt User per `remove` ohne FK-Behandlung. | `RecreateTestUsersCommand` | In Production ausführbar: bekannte Zugangsdaten auf der Produktivdomain. Zusätzlich kollidiert die Domain mit P1. |
| P9 | **Kein Szenario-Rahmen, gemischte Zuständigkeiten.** User-Seed, Department-Seed, Lieferanten-Seed und TOTP laufen in einem Command; Event-Jobs setzen die Rollen-User als Vorbedingung voraus und laufen in einem separaten Command ohne Anlage des Departments. `DevDemoResetCommand` ruft Commands über `Application::find()->run()`. | siehe §2.1 | Szenarien sind nicht einzeln, nicht in definierter Reihenfolge und nicht unabhängig ausführbar. |
| P10 | **Keine Transaktionen / Teilzustände.** `createUser` + `flush` pro Gruppe, `ensureDepartment` flusht mehrfach, `ensureDemoScenario` flusht an mehreren Stellen. Ein Fehler (P2, P3) hinterlässt ein Teilergebnis (z. B. gelöschte User, Superadmin neu, GA fehlt). | `CreateRoleUsersCommand`, Seed-Services | Nach einem Abbruch ist der Zustand nicht definiert; erneutes Ausführen kann am selben Fehler scheitern. |
| P11 | **Passwort/Status werden bei jedem Lauf zurückgesetzt** (`createUser` für bestehende User: `setPassword`, `setState('active')`, `setEmailVerified`, Namen). | `CreateRoleUsersCommand::createUser` | Für Demo gewünscht; aber ohne Szenario-Bezug und auch für nicht-Demo-Konten mit Demo-Adresse wirksam. |
| P12 | **Gating zu grob.** `isDevToolsEnabled()` gilt gleich für Develop und Staging (§2.2). Es gibt keine Unterscheidung «Sync erlaubt» / «Reset erlaubt». | `DevEnvironmentService`, Override-Beispiele | Auf Staging können Seed- und Wipe-Commands (und `resetDb`-Endpunkt für Department-Manager) laufen, sobald jemand sie aufruft. Für automatische Deployment-Schritte fehlt jede Schranke. |
| P13 | **Wipe-Reihenfolge handgepflegt.** `deleteGrossanlassDomain` listet Tabellen von Hand; neue Grossanlass-Tabellen (Pack, Retouren, Weiterverkauf …) werden leicht vergessen und blockieren per FK den Department-Löschvorgang. | `DemoGrossanlassWipeService` | Wipe bricht (mit P7: halb gelöscht) oder lässt Waisen. Kein Test belegt Vollständigkeit. |
| P14 | **Zeitdrift der Demo-Uhr.** Der Offset wird einmal gesetzt; danach läuft die Demo-Fachzeit in Echtzeit weiter und überholt den Zeitstrahl (Anker E = Seed-Tag + 7 d). Ein unbeaufsichtigtes Develop-Demo «wandert» durch die Phasen. | `ensureDemoClock`, [business-clock.md](../grossanlass/business-clock.md) | Kein Fehler, aber für Vorführungen relevant: Clock-Reset ist nötig; gespeicherte Ist-Daten (Frage 1) passen nach Tagen nicht mehr zur Uhr. |
| P15 | **Tests.** Es existiert nur `CreateRoleUsersCommandGuardTest` (Schranke) und `DemoAccountsTest`; kein Test für Idempotenz, Isolation, Wipe, Primär-Mitgliedschaft oder Mengenbilanz der Seeds. | `backend/tests/` | Regressionen in Seeds fallen erst manuell auf. |

### 3.2 Phase 0: Absicherung (IST)

Umgesetzt, ohne Registry und ohne Szenario-Migration. Klasse `Service/Demo/DemoEnvironmentGuard`.

| Regel | Marke |
| --- | --- |
| Umgebung wird über `EMATCHEF_ENV_NAME` benannt (`local`, `develop`, `staging`, `production`). Auf einem prod-Kernel ist ein fehlender oder unbekannter Name **gesperrt**; `EMATCHEF_DEV_TOOLS=1` und `APP_ENV=prod` reichen nicht. Leerer Name ist nur auf einem Nicht-prod-Kernel `local`. | **IST** |
| *Hinzufügende* Demo-Befehle (`app:create-role-users`, `app:dev-demo:reset`, `app:demo-grossanlass:event-jobs`): Dev-Tools aktiv, Name bekannt, nicht `production`. | **IST** |
| *Löschende* Befehle (`app:demo-grossanlass:wipe`, `app:recreate-test-users`, `app:create-role-users --delete-demo-users`): zusätzlich nur `local`, oder `develop` mit `EMATCHEF_DEMO_DESTRUCTIVE=1`. **Staging und Production nie.** | **IST** |
| `app:create-role-users` löscht standardmässig nichts. Löschen nur mit `--delete-demo-users` und nur Konten mit **exakter** Adresse aus `demo-accounts.json` (nicht Superadmin, Lieferant, E2E-Smoke). `*@ematchef.ch` und die Demo-Domain allein sind keine Ownership. `--skip-delete` ist veraltet und wirkungslos. | **IST** (P1) |
| Rollen-User gehen nur an ein Department mit `demo_mode` (eindeutig, ohne Grossanlass), an ein per `--department=<id>` genanntes Demo-Department (`--mark-department-demo` markiert es ausdrücklich) oder an ein neu angelegtes Demo-Department in einer DB ganz ohne sichtbares Department. Sonst Abbruch. Nie «erstes sichtbares Department». | **IST** (P4) |
| Neue Primär-Mitgliedschaft wird nur gesetzt, wenn der User nicht schon anderswo primär ist; fremde Mitgliedschaften werden nie geändert. | **IST** (Umgehung von P3; Ursache des Index unklar, E9) |
| Umbenennung alter `*@ematchef.ch`-Konten (`migrateLegacyDemoEmails`) nur mit Freigabe für löschende Befehle. | **IST** |
| `DemoGrossanlassSeedService::ensureDepartment` übernimmt kein gleichnamiges Department ohne `demo_mode`; `app:demo-grossanlass:event-jobs` schreibt nur in Departments mit `demo_mode` (`--mark-demo` ausdrücklich). | **IST** (P6, Teil) |
| Wipe: nur `demo_mode`-Departments, eindeutiger Name, Bestätigung `--confirm=<Name>`, eine äussere Transaktion mit Rollback; Fehlerfälle enden mit Exit-Code 1. | **IST** (P7, Teil) |
| `app:recreate-test-users`: gesperrt ohne Freigabe; bricht ab, wenn ein Zielkonto Mitgliedschaften hat. Weiterhin veraltet (Entfernen empfohlen, Phase 1/8). | **IST** (P8, Teil) |

| REST `POST /api/departments/{id}/reset-db` und `…/reset-activities` prüfen `DemoEnvironmentGuard::destructiveDenial()` **vor** der Berechtigungsprüfung: Staging und Production sind unabhängig von `EMATCHEF_DEV_TOOLS` gesperrt (403), Develop nur mit `EMATCHEF_DEMO_DESTRUCTIVE=1`, local frei. Authentifizierung und Rollenprüfung (Superadmin oder Department-Manager) bleiben unverändert. Gilt für **jedes** Department, nicht nur für Demo; Test `DepartmentResetEndpointsGuardTest` über alle Umgebungen. | **IST** (P12) |

**Nicht Teil von Phase 0 (weiter SOLL / offen):** Demo-Sperre der REST-Resets für Demo-Departments bzw. Umleitung auf den Szenario-Reset (E7; die Umgebungsfreigabe ist erledigt), Szenario-Schlüssel, Ledger, Registry, vollständiger Reset, Löschlisten-Test (P13), `ensureEinsatz`-Überschreiben (P5), wurzelhafte Behebung von P2 (FK `updated_by`) und P3 (Index-Herkunft), Deployment-Sync.

**Bekannter, nicht zusammenhängender Befund (`lint:container`):** `ApiDiscoveryController` bekommt `%env(default::APP_MAIN_SITE_ORIGIN)%`, das bei nicht gesetzter Variable `null` ergibt, für einen `string`-Parameter. `lint:container` schlägt dadurch fehl, wenn `APP_MAIN_SITE_ORIGIN` fehlt (in `.env` nicht definiert; mit gesetzter Variable ist der Lint grün, geprüft). Alt (Commit `ffaf86ca`), unabhängig von Phase 0; Server setzen die Variable. Ein Fix (`?string` oder `default:`-Prozessor mit Leerstring) ist bewusst nicht Teil dieses Branches.

**Betrieb:** Auf Develop und Staging muss `EMATCHEF_ENV_NAME` gesetzt werden (Beispiele in `deploy/`); bis dahin sind die Demo-Befehle dort gesperrt (gewollt). Auf Develop mit bestehenden Departments braucht `app:create-role-users` einmalig `--department=<id>` (Demo-Department) oder `--mark-department-demo`.

## 4. Reset

Daten neu und Zeit zurück sind getrennte Vorgänge (**IST**, Tabelle in [business-clock.md](../grossanlass/business-clock.md)):

| Vorgang | Wirkung |
| --- | --- |
| Header «Zurücksetzen» (`DELETE …/clock`) | nur Demo-Zeit, keine Daten |
| `app:create-role-users [--with-ga-demo]` | aktualisiert Bestehendes, löscht nichts (seit Phase 0), Uhr bleibt (Problem P5) |
| `app:demo-grossanlass:wipe` danach `app:create-role-users --with-ga-demo` | echter Reset inklusive Ausgangsuhr, nur Grossanlass (Probleme P7, P13) |
| `app:dev-demo:reset` | nur Rollen-User anlegen/aktualisieren (seit Phase 0 ohne Löschen), berührt Department und Demo-Zeit nicht |

**SOLL:** Ein Reset-Weg pro Szenario, ausfallsicher, nur im eigenen Demo-Department (§7.7).

## 5. Zeitstrahl

### 5.1 Phasen

Seeds erzeugen einen konsistenten Zeitstrahl um einen Seed-Anker. Innerhalb eines Szenarios existieren vergangene, laufende und kommende Vorgänge; man reist durch die Zeit, **ohne neu zu seeden**. Die Phasen sind szenariospezifisch: Grossanlass siehe [GROSSANLASS.md §3](./scenarios/GROSSANLASS.md#3-zeitstrahl), normales Department siehe [DEPARTMENT.md §4](./scenarios/DEPARTMENT.md#4-zeitstrahl).

### 5.2 Gespeichert, berechnet, geplant

| Art | Definition | Entsteht durch | Reagiert auf Zeitsprung? | Beispiel |
| --- | --- | --- | --- | --- |
| **Gespeicherter tatsächlicher Zustand** | Was physisch oder rechtlich passiert ist, als Datensatz mit fachlichem Zeitstempel | Seed oder Benutzeraktion | **Nein** | Wareneingang 12 Stück; Charge-Bewegung; erledigte Aufgabe; quittierte Rückgabe |
| **Zeitabhängiger berechneter Zustand** | Wird bei jeder Abfrage aus gespeicherten Daten und `BusinessClock::now()` abgeleitet, nie persistiert | Service/Query | **Ja** | «überfällig», «heute fällig», «läuft gerade», Fortschritt, Fahrt-Freigabe |
| **Zukünftiger geplanter Vorgang** | Datensatz mit Soll-Zeit in der Zukunft, hat noch nicht stattgefunden | Seed oder Planung | Wechselt nur die **Anzeige** (kommend → heute → überfällig); es entsteht kein Ist-Zustand | geplante Fahrt, geplante Rückgabe, Retour-Frist |

Folgen:

- Springt die Uhr über eine geplante Rückgabe hinaus, wird sie **überfällig**, nicht erledigt. Erledigt wird sie nur durch eine Benutzeraktion oder bereits geseedete Ist-Daten.
- Springt die Uhr zurück, werden gespeicherte Ist-Daten nicht entfernt.
- Auto-Schedules (z. B. Planungsrunde) sind berechnet. Echte Job-Effekte (Versand) gehören in die Sandbox ([README §5](./README.md#5-demo-sicherheit-und-side-effect-sandbox)).

### 5.3 OFFEN (Frage 1): Ist-Daten vs. Uhr

Gespeicherte Ist-Daten späterer Phasen existieren schon, wenn die Uhr noch am Anfang steht (z. B. eine quittierte Rückgabe bei Uhr = Planungsphase). Der Seed muss je Phase festlegen, ab welcher Uhrzeit Ist-Daten sichtbar sein dürfen. Optionen:

- (a) Der Seed liefert nur Ist-Daten bis zum Ausgangspunkt; spätere Phasen sind Plan-Daten, die der Benutzer selbst abarbeitet.
- (b) Der Seed liefert alle Phasen und Ansichten filtern nach `occurred_at ≤ BusinessClock::now()`. Das verletzt «Uhr verändert keine Zustände» nicht, braucht aber Filter in jeder Abfrage.
- (c) Mehrere Ausgangspunkte (Snapshots).

**Empfehlung (SOLL, nicht entschieden):** (a) als Standard. Mehrere «Prozesszustände» je Szenario (§1) entstehen durch **mehrere Vorgänge in unterschiedlichen Zuständen zum Ausgangspunkt** (ein Vorgang abgeschlossen, einer laufend, einer geplant), nicht durch Ist-Daten aus der Zukunft. (b) und (c) nur, wenn eine Vorführung explizit rückwärts reisen muss. Mit P14 verträglich: Der Seed ist am Ausgangspunkt konsistent; ein Clock-Reset stellt diese Konsistenz wieder her.

## 6. Mengenbilanz und Datenintegrität

**SOLL:** Seeds sind in sich konsistent:

- Mengen addieren sich über die Bewegungen der Charge ([materialfluss.md §14](../grossanlass/materialfluss.md)) und entsprechende Bestandsbewegungen im normalen Department.
- Invariante (Grossanlass): bestellt ≥ geliefert ≥ zugewiesen ≥ gepackt; Summe der Disposition = zurückgenommene Menge.
- Invariante (Department): Bestand = Summe der Lagerbewegungen; ausgegebene Menge = zurückgegebene + offene Menge.
- Beziehungen vollständig (keine verwaisten Referenzen), Rollen und Mitgliedschaften gültig, Eigentümer und Herkunft gesetzt.
- Jeder `public_code` löst auf einen Demo-Datensatz auf.
- Ein Test prüft diese Invarianten je Szenario (`verify()`, §7.2).

## 7. Szenario-Registry (SOLL)

**Status:** Das Gerüst (Phase 1) ist gebaut, siehe §7.0. Alles andere in §7.1–§7.10 ist die empfohlene Soll-Architektur aus dem Review vom 8. Oktober 2026 und **nicht** gebaut. Entscheidungen, die der Teamfreigabe bedürfen, stehen in §10.

### 7.0 Phase 1: gebauter Stand (IST)

Code unter `backend/src/Service/Demo/Scenario/`, Commands unter `backend/src/Command/Demo*Command.php`. PHPUnit und PHPStan laufen grün. Die Migration wurde zusätzlich gegen eine isolierte Wegwerf-PostgreSQL-16-Instanz validiert (siehe «Validierung» unten); die Entwicklungsdatenbank `mvdb` wurde nicht berührt.

| Baustein | Stand | Marke |
| --- | --- | --- |
| `DemoScenarioInterface` (Tag `app.demo_scenario`): `key`, `label`, `expectsGrossanlass`, `supportsReset`, `sync`, `reset`, `verify`, `clockOrigin` | gebaut | **IST** |
| `DemoScenarioRegistry`: nur die drei Schlüssel `materialverwaltung`, `grossanlass-event`, `grossanlass-camp`, je einmal; unbekannt oder doppelt → Fehler | gebaut | **IST** |
| Die drei Szenarien (`MaterialverwaltungScenario`, `GrossanlassEventScenario`, `GrossanlassCampScenario`): seit Phase 2 synchronisieren sie die Organisationsstruktur (§7.11); Prozessdaten fehlen (**SOLL**, Phasen 4, 5), `supportsReset() = false` | gebaut | **TEILWEISE** |
| `department.demo_scenario_key` (VARCHAR 40, NULL, eindeutiger Index `uniq_department_demo_scenario_key`, DB-CHECK «Schlüssel nur mit `demo_mode`»), Migration `Version20261008100000`, rein additiv | gebaut, gegen isolierte PG-16 validiert | **IST** |
| `DemoScenarioIdentity::assign`: Schlüssel nur an Departments mit `demo_mode`, passendem Grossanlass-Typ, ohne anderen Schlüssel und nur, wenn der Schlüssel frei ist. Nie über Namen. Echte Departments werden abgewiesen. | gebaut | **IST** |
| Ledger `demo_seed_record` (Entity `DemoSeedRecord`, `DemoSeedLedger`): `(scenario_key, seed_key)` eindeutig, Seed-Schlüssel müssen mit `<szenario>:` beginnen, `record()` ist idempotent und überschreibt nie einen fremden Eintrag, FK auf Department (`ON DELETE CASCADE`). Noch **kein** bestehender Seed schreibt hinein. | gebaut | **IST** (Nutzung ab Phase 2: **SOLL**) |
| `SeedContext`: erzwingt Isolation (Department muss genau den Schlüssel des Szenarios tragen und `demo_mode` haben), Ledger-Zugriff nur für das eigene Szenario, Dry-Run schreibt nichts | gebaut | **IST** |
| `DemoScenarioRunner`: `sync` und `reset` unter Advisory-Lock (`pg_try_advisory_xact_lock`) und in einer Transaktion mit Rollback; `reset` nur bei `supportsReset()`, und die Department-Zeile samt Schlüssel muss danach unverändert bestehen, sonst Rollback | gebaut | **IST** |
| `ScenarioClockOriginResolver` (Tag `app.clock_origin_resolver`): Departments **mit** Schlüssel nutzen den Ausgangspunkt ihres Szenarios; Event und Camp delegieren vorläufig an den bestehenden Grossanlass-Ausgangspunkt (Anlassbeginn − 5 Tage, 09:00); Materialverwaltung hat keinen (reale Zeit). `GrossanlassClockOriginResolver` gilt nur noch für Departments **ohne** Schlüssel, bestehende Departments verhalten sich unverändert. | gebaut | **IST** |
| Commands (alle mit `DemoEnvironmentGuard`, Freigabe vor jedem Zugriff): `app:demo:status` (lesend), `app:demo:verify` (lesend), `app:demo:sync [--scenario=K\|--all] [--dry-run]`, `app:demo:adopt --scenario=K --department=ID`, `app:demo:reset --scenario=K --confirm=K` (destruktive Freigabe, Bestätigung) | gebaut | **IST** |
| `app:demo:reset` ist für **alle** echten Szenarien gesperrt («noch nicht verfügbar»); der Reset-Pfad ist nur mit Test-Szenarien getestet | gebaut | **IST** |
| `sync` legt seit Phase 2 die Organisationsstruktur an (§7.11); Prozessdaten legt es noch nicht an. Die bestehenden Seed-Services (`app:create-role-users`, Event-Jobs, Wipe) laufen unverändert und kennen die Registry nicht | | **IST** |
| Keine automatische Ausführung: kein Eintrag in `prod-update.sh`, CD oder Entrypoint | | **IST** |
| Legacy-Departments (`Demo Grossanlass`, `Demo-Grossanlass-Event`, …) werden **nicht** übernommen: sie bleiben bis zur späteren Löschung unverändert bestehen und werden lokal mit `app:demo:legacy-rename` durch das Präfix `old-` gekennzeichnet ([LOKALE-MIGRATION.md §5a](./LOKALE-MIGRATION.md#5a-legacy-umbenennung-old-ist-auf-mvdb-ausgeführt-am-8-oktober-2026), Ausführung auf `mvdb` am 8. Oktober 2026 in [§8](./LOKALE-MIGRATION.md#8-ausführungsprotokoll-mvdb-8-oktober-2026-ist)); die alten Seed-Dienste legen die alten Namen nicht neu an | gebaut | **IST** (E4 entschieden: nicht übernehmen, später löschen) |

**Validierung (isolierte Wegwerf-DB, 8. Oktober 2026):**

| Prüfung | Ergebnis |
| --- | --- |
| Gesamte Migrationskette (253 Migrationen) auf leerer DB inklusive `Version20261008100000` | läuft durch |
| Rollback `execute --down`: Spalte, Index, CHECK und Tabelle verschwinden vollständig; danach erneut `migrate` | in Ordnung |
| Bestehende Departments (echt, Demo-Grossanlass, anderes Demo) vor/nach der Migration: alle bisherigen Spalten byte-identisch, `demo_scenario_key` überall NULL | in Ordnung |
| Eindeutiger Schlüssel: zweites Department mit gleichem Schlüssel wird abgewiesen; mehrere NULL erlaubt | in Ordnung |
| CHECK: Schlüssel an Department ohne `demo_mode` wird abgewiesen | in Ordnung |
| Ledger: doppeltes `(scenario_key, seed_key)` und unbekannte `department_id` werden abgewiesen; `NULL` erlaubt; Department löschen entfernt seine Ledger-Einträge (`CASCADE`), andere Departments bleiben | in Ordnung |
| Mapping ↔ DB für `Department` und `DemoSeedRecord` (SchemaTool-Diff nur für diese beiden Entities): keine Abweichung bei `demo_scenario_key`, Indizes und FK. Verbleibend nur das bei allen Entities mit `CHARACTER(12)` bekannte Rauschen (`ALTER … TYPE VARCHAR(12)`) | in Ordnung |
| Echte Services gegen die DB: `assign` weist echtes Department ab, Sync/Verify/Ledger idempotent, Advisory-Lock-SQL (`pg_try_advisory_xact_lock(hashtext(?))`) funktioniert und eine zweite Verbindung wird abgewiesen, Reset ohne Freigabe verweigert, Departments ohne Schlüssel unberührt | in Ordnung |
| `doctrine:schema:validate` | **bricht ab, unabhängig von Phase 1:** doppelter Indexname `uniq_cf87ef08a6005ca0` an `activity_grossanlass_round_form` (Mapping einer früheren Entity). Ein Voll-Diff ist deshalb nicht möglich; geprüft wurde per Teil-Diff (siehe oben). Offen: separat beheben. |

Die Validierung lief gegen einen kurzlebigen Container mit tmpfs-Daten (eigener Name, nur `127.0.0.1`, danach entfernt); es ist nicht Teil der CI.

**Wiring-Tests:** `tests/Wiring/FreshKernel` bootet den Test-Kernel mit eigenem, temporärem Cache- und Log-Verzeichnis (wird danach gelöscht). Ein veralteter `var/cache/test` kann die Tests nicht mehr verfälschen; die CI braucht keinen manuellen Cache-Eingriff.

**Betrieb:** Nach dem Deployment der Migration (`doctrine:migrations:migrate` im Entrypoint) existieren Spalte und Tabelle; ohne `app:demo:adopt` ändert sich für bestehende Departments nichts.


### 7.1 Bewertung der Optionen

| Frage | Empfehlung | Begründung |
| --- | --- | --- |
| Gemeinsame Szenario-Registry? | **Ja.** Ein Interface, eine per Tag gesammelte Registry (Muster `app.clock_origin_resolver`), ein gemeinsames Command. | Behebt P9 und P12; drei Szenarien mit gleichem Reset/Sync/Clock-Vertrag. Kein Framework nötig. |
| `ensure*`-Services oder Doctrine-Fixtures? | **`ensure*`-Services behalten**, hinter dem Szenario-Interface gekapselt. Keine Doctrine-Fixtures. | Fixtures sind auf «Datenbank leeren und laden» ausgelegt (widerspricht Sync/Erhalt) und umgehen Domain-Services. Die bestehenden `ensure*`-Methoden werden in Idempotenz und Identität korrigiert, nicht ersetzt (Antwort auf Frage 3, Empfehlung). |
| Stabile Seed-Identitäten? | **Ja, durch Szenario-Schlüssel am Department und Seed-Schlüssel je Datensatz** (§7.3). | Behebt P6; Name und Freitext sind keine Schlüssel. |
| Ownership-Nachweis? | **Ja, Ledger-Tabelle** `demo_seed_record` für alles, was nicht per `department_id` zuordenbar ist (§7.4). | Erlaubt gezielten Reset und «keine fremden Daten löschen». |
| Transaktionen? | **Eine Transaktion pro Szenario-Schritt**, Advisory Lock je Szenario (§7.5). | Behebt P7, P10. |
| Command-Struktur? | **Ein `app:demo:*`-Namensraum**, alte Commands werden Aliasse oder entfallen (§7.8, §8). | Behebt P9. |
| Deployment? | **Opt-in Sync-Schritt in `prod-update.sh`**, nur Develop, nie Reset (§7.9). | Behebt P12. |

### 7.2 Szenario-Interface

Skizze, kein Code. Ein Szenario ist eine Klasse mit Tag `app.demo_scenario`:

| Mitglied | Aufgabe |
| --- | --- |
| `key()` | stabiler Schlüssel: `materialverwaltung`, `grossanlass-event`, `grossanlass-camp` |
| `dependencies()` | Schlüssel anderer Szenarien oder gemeinsamer Bausteine (nur `identities`, siehe §7.6); keine Abhängigkeit zwischen Szenarien |
| `sync(SeedContext)` | additiv und idempotent (§7.6) |
| `reset(SeedContext)` | löscht den Inhalt dieses Szenarios (§7.7) |
| `clockOrigin(Department)` | Ausgangspunkt der Uhr; ersetzt `GrossanlassClockOriginResolver` durch einen Registry-Resolver, der pro Schlüssel delegiert (Event und Camp können verschiedene Ausgangspunkte haben) |
| `verify()` | prüft Mengenbilanz, Beziehungen, `public_code`-Auflösung (§6); ohne Schreibzugriff |

`SeedContext` bündelt: Szenario-Department, EntityManager, Seed-Anker (Zeit), Akteur, `dryRun`-Flag, Ledger-Zugriff. Dry-Run (`--dry-run`) berechnet, was `sync` anlegen würde, und schreibt nichts.

### 7.3 Stabile Identitäten

| Ebene | Empfehlung |
| --- | --- |
| Department | neue nullable, eindeutige Spalte `department.demo_scenario_key` (Migration, Phase 1). `demo_mode = true` bleibt die Markierung für Zeit/Sicherheit; der Schlüssel ist der Zuordnungsanker für Sync und Reset. Abfrage nie über den Namen. Der Name darf geändert werden. |
| Datensätze | Seed-Schlüssel `scenario_key:logischer_name` (z. B. `grossanlass-event:einsatz:aufbau-tische`), geführt im Ledger (§7.4), **nicht** als Freitext-Tag in `who`/Beschreibung. Bestehende `[demo:v1:…]`-Tags werden einmalig ins Ledger übernommen (Phase 2) und danach nicht mehr gelesen. |
| Benutzer | feste E-Mail aus `demo-accounts.json` (bereits stabil). Neue Konten für Event/Camp/Materialverwaltung kommen in dieselbe Datei (Single Source, wird auch für docs.ematchef.ch genutzt). |
| IDs | **Keine** deterministischen IDs aus dem Schlüssel (IDs sind 12-stellig und von `IdGenerator` verwaltet; Hash-Kollisionen und Sonderfälle vermeiden). Zuordnung läuft über das Ledger (Entscheidung E3, §10). |

### 7.4 Ownership: Ledger

Tabelle `demo_seed_record` (Vorschlag): `scenario_key`, `seed_key`, `entity_class`, `entity_id`, `department_id` (nullable), `created_at`; eindeutig über `(scenario_key, seed_key)`.

- Wird vom Seed beim Anlegen geschrieben, nie von der Anwendung gelesen.
- `sync` sucht über das Ledger, nicht über Freitext (P6). Existiert der Eintrag, aber die Entity wurde gelöscht, legt `sync` sie neu an (Ledger-Eintrag wird ersetzt).
- `reset` löscht erst die per `department_id` zuordenbaren Inhalte, dann Ledger-Einträge, die auf nicht-Department-Objekte zeigen (z. B. Lieferanten-Testfirma).
- Alles ausserhalb von Department und Ledger gilt als **fremd** und wird nie gelöscht.
- Alternative ohne Ledger (nur `department_id`-Scope + Szenario-Schlüssel) ist für Departments ausreichend, reicht aber nicht für geteilte Objekte (Demo-User, Lieferant) und nicht für das Wiederfinden einzelner Seed-Vorgänge. Entscheidung E3.

### 7.5 Transaktionen und Parallelität

- `sync(scenario)` = **eine** DB-Transaktion (`Connection::transactional` um alle `flush`es des Szenarios; zwischenzeitliche `flush`es für ID-Abhängigkeiten sind innerhalb der Transaktion unkritisch). Bei Fehler Rollback, Szenario unverändert.
- `reset(scenario)` = eine Transaktion für das Löschen (inklusive Nested-Reset von `DepartmentResetService`, der eine eigene `beginTransaction` nutzt; mit einer äusseren Transaktion nur zulässig, wenn DBAL-Nesting/Savepoints geprüft ist, siehe Test T6). Anschliessend **separat** `sync` in einer zweiten Transaktion. Stirbt der Prozess dazwischen, ist das Szenario leer, aber konsistent; erneutes `sync` stellt es her (sync ist idempotent).
- **Advisory Lock** je `scenario_key` (`pg_try_advisory_lock`): verhindert, dass Deployment-Sync und manueller Reset gleichzeitig laufen. Wer den Lock nicht erhält, bricht mit klarer Meldung ab (kein Warten im Deployment).
- Benutzer-Identitäten (§7.6) laufen in einer eigenen Transaktion vor den Szenarien.

### 7.6 Sync-Semantik

Regeln, die jedes Szenario einhält (Test T2–T4):

1. **Anlegen, wenn Ledger-Eintrag oder Entity fehlt; sonst nicht anfassen.** Bestehende Entities werden **nicht** auf Seed-Werte zurückgesetzt. Ausnahmen sind eine feste Liste unveränderlicher Stammfelder (z. B. Name, Gruppenstruktur), nie Status, Mengen, Zeiten oder Zuweisungen. Behebt P5.
2. **Ergänzen statt ersetzen.** Fehlt ein Kind-Datensatz (z. B. eine Position), wird nur dieser ergänzt.
3. **Konsistenzprüfung vor dem Anlegen** abhängiger Daten (Mengenbilanz, §6); `sync` bricht ab, wenn der vorhandene Bestand die Invarianten verletzt, und meldet das, ohne zu «reparieren».
4. **Zeit:** `demo_clock_offset_seconds` wird nur bei fehlendem Offset gesetzt (wie `ensureDemoClock` heute). Sync ändert nie die Uhr eines bestehenden Departments.
5. **Benutzer (Identity-Schritt):** Konten aus `demo-accounts.json` werden angelegt oder aktualisiert (Passwort, Status), **nie gelöscht**. Memberships werden pro Szenario-Department gesetzt. Primär-Mitgliedschaft: ein User hat genau eine; `sync` löst eine andere Primär-Mitgliedschaft in derselben Transaktion nur, wenn sie ein Demo-Department betrifft, sonst bleibt sie und die neue Membership wird nicht primär (Behebt P3, ohne fremde Zuordnungen anzufassen).
6. **Kein Schreiben ausserhalb des eigenen Departments** (ausser Identity- und Lieferanten-Schritt). Ein Test (T3) vergleicht vor/nach `sync` die Zeilenzahlen aller fremden Departments.
7. **Keine Abhängigkeit zwischen Szenarien:** Event-Jobs brauchen nicht mehr die `ga-*`-User eines anderen Szenarios, sondern die Szenario-eigenen Konten (behebt P9).

### 7.7 Reset-Semantik

- **Geltungsbereich:** nur Departments mit `demo_mode = true` **und** passendem `demo_scenario_key`. `--name` entfällt (P7). Reset eines Departments ohne Schlüssel ist verboten.
- **Variante A (empfohlen):** Inhalt löschen, **Department-Zeile behalten** (stabile ID für Memberships, öffentliche Links, QR-Codes, `last_used_department_id`), Ledger-Einträge des Szenarios entfernen, Uhr auf Ausgangspunkt, danach `sync`. Muss auch Config, Kalenderperiode und Haupt-Aktivität der Grossanlass-Departments neu aufbauen, die heute in `ensureDepartment` entstehen.
- **Variante B:** Department löschen und neu anlegen (heutiges Verhalten). Einfacher, aber neue ID, Verlust von Memberships und Links, FK-Risiko (P13).
- **Löschreihenfolge:** wird aus einer Tabellenliste mit FK-Reihenfolge erzeugt und per Test gegen das Schema geprüft (T5), nicht von Hand ergänzt. Neue Tabellen mit `department_id` oder FK auf Department/Aktivität lassen den Test fehlschlagen, bis sie eingetragen sind.
- **Schutz:** Bestätigung per `--confirm=<scenario_key>` (nicht-interaktiv) oder interaktiv; Umgebungsfreigabe nach §7.9.
- **Clock-Reset** (Header «Zurücksetzen», `DELETE …/clock`) bleibt davon getrennt und ändert nur den Offset (**IST**). **Browser-Reload** lädt nur Daten; kein Lesezugriff der Demo-Ansichten darf `sync` oder `reset` auslösen (T9).
- **Reset eines Szenarios berührt weder andere Szenarien noch Benutzerkonten.** Konten werden nicht gelöscht, nur Memberships im zurückgesetzten Department (die Membership-Zeilen bleiben bei Variante A bestehen).

### 7.8 Command-Struktur

| Command | Aufgabe | Schranke |
| --- | --- | --- |
| `app:demo:sync [--scenario=<key>\|--all] [--dry-run]` | additiver Sync inklusive Identity-Schritt | Sync-Freigabe |
| `app:demo:reset --scenario=<key> --confirm=<key>` | Reset eines Szenarios, danach Sync | Reset-Freigabe (strenger) |
| `app:demo:verify [--scenario=<key>\|--all]` | `verify()` (nur lesend); für CI und Deploy-Prüfung | keine (nur lesend) |
| `app:demo:status` | Liste der Szenarien: Department, Ledger-Umfang, Uhr (Offset/Ausgangspunkt) | keine (nur lesend) |

Kein Command löscht ohne Szenario-Schlüssel. Alle Commands ausser `verify`/`status` prüfen die Freigabe (§7.9) **vor** der ersten Abfrage.

### 7.9 Umgebungsschutz und Deployment-Integration

**Problem:** `APP_ENV=prod` + `EMATCHEF_DEV_TOOLS=1` ist auf Develop und Staging identisch (P12). Es braucht eigene, explizite Schalter:

| Schalter (Vorschlag) | Standard | Wirkung |
| --- | --- | --- |
| `EMATCHEF_DEMO_SYNC=1` | aus | erlaubt `app:demo:sync` (additiv). Nur auf Develop in der Compose-Umgebung gesetzt. |
| `EMATCHEF_DEMO_RESET=1` | aus | erlaubt `app:demo:reset`. **Nie** in `prod-update.sh` oder CD gesetzt; nur manuell pro Sitzung (`docker compose exec -e EMATCHEF_DEMO_RESET=1 …`). |
| `EMATCHEF_ENV_NAME` | leer | `develop`/`staging`/`production`/`local`; Reset und Sync verweigern bei `production`, unabhängig von allen anderen Flags. |

- **Lokal** (`APP_ENV≠prod`): wie bisher über `isDevToolsEnabled()`, Reset mit `--confirm`. **Fail closed:** unbekannter oder leerer `EMATCHEF_ENV_NAME` auf einem prod-Kernel = verboten.
- **Staging:** Sync nur manuell und nur mit gesetztem Schalter; **kein automatischer** Schritt, **nie** automatischer Reset (Vorgabe). Ob Staging überhaupt Demo-Daten tragen soll, ist offen (E5).
- **Production:** beide Schalter nie gesetzt; `EMATCHEF_ENV_NAME=production` verbietet zusätzlich. Mindestens ein Test belegt, dass beide Commands in dieser Konfiguration mit Fehler und ohne DB-Zugriff enden (T1).
- **Deployment (Develop):** neuer Schritt in `deploy/prod-update.sh` **nach** `wait_for_backend_ready` und Cache-Warmup, ausgeführt nur, wenn `EMATCHEF_DEMO_SYNC=1` im Backend-Container gesetzt ist (`docker compose exec -T backend php bin/console app:demo:sync --all --no-interaction --env=prod`). Dasselbe Skript wird von `cd-develop.yml`, `cd-staging.yml` und `cd-prod.yml` aufgerufen (nur `EMATCHEF_GIT_BRANCH` und Projektname unterscheiden sich). Der Schritt muss daher auf Staging und Production durch den fehlenden Schalter **und** `EMATCHEF_ENV_NAME` inaktiv bleiben; ein Skript-Test (T1) prüft das.
  - **Fehlerverhalten:** ein fehlgeschlagener Sync darf das Deployment nicht fehlschlagen lassen (Demo-Daten sind nicht kritisch), muss aber im CD-Log sichtbar warnen und mit Exit-Code im Summary stehen (Entscheidung E6).
  - **Nicht** im Docker-Entrypoint (läuft in jeder Umgebung bei jedem Start, vor App-Bereitschaft) und **nicht** im Request-Pfad.
- **Der Sync-Schritt im Deployment enthält nie `reset`.** Ein erkannter Konflikt (Invariante verletzt, Lock belegt, Ledger/DB inkonsistent) endet mit Warnung, nicht mit Löschen.

### 7.10 Keine externen Writes

- Seeds laufen mit einem **Seed-Kontext-Flag**, das Mailer, Gmail/Outlook/Hitobito-Adapter, Geocoder und Medien-Import fail-closed sperrt (Teil der Sandbox, [README §5](./README.md#5-demo-sicherheit-und-side-effect-sandbox)). Bis die Sandbox existiert: Seeds rufen **ausschliesslich** Entities/Repositories und Domain-Services ohne externe Adapter auf; ein Test (T8) lässt `sync` mit Mock-Transport/HTTP-Client laufen und prüft, dass kein Aufruf erfolgt.
- Neue Benutzer lösen keine Verifikations- oder Willkommensmail aus (`VerificationEmailService` ist Controller-Pfad; der Identity-Schritt setzt `emailVerified` direkt wie heute).

### 7.11 Phase 2: Demo-Organisationen (IST)

Code: `backend/src/Service/Demo/Organisation/`, Katalog: `backend/data/seeds/dev-demo/organisations.json` (versioniert über `catalogVersion`, aktuell `2026.10.4`). Migration `Version20261008110000` (zwei nullable Spalten am Ledger). Aufgerufen über `app:demo:sync --scenario=<key>|--all` (und `app:demo:verify`); **kein** Deployment-Schritt.

**Was ein Sync je Szenario anlegt:** Organisation → Department (plus zusätzliche Hierarchie-Departments, s. u.) → Gruppenbaum → Benutzer → Mitgliedschaften → Gruppenmitgliedschaften → Verwaltungsbereiche. Jedes Szenario hat eine **eigene** Organisation und ein eigenes Szenario-Department. **Demo-Benutzer sind reine Testdaten ohne Bezug zu echten Identitäten und gehören keinem Szenario:** ein Konto darf in mehreren Demo-Departments Mitglied sein, je Department aber nur einmal (Katalog-Validator). Konten ohne Department-Mitgliedschaft (`sharedAccounts`, derzeit der Lieferant) legt jeder Sync mit an. Auf einer **neuen Develop-Installation** erzeugt `app:demo:sync --all` damit sämtliche Demo-Konten selbstständig.

**Rollenmodell der Demo-Daten (IST seit Katalog 2026.10.3):** Globale Rollen (`sa`, `org`, `sub`) stehen in `profile.roles` und sind **keine** Mitgliedschaftsrolle. Superadmin, Orgchef und Suborgchef erhalten **nie** automatisch eine MW-Rolle (der Katalog-Validator lehnt `mw`/`cmw` für diese Konten ab; `verify` meldet sie als Verstoss). Ihre Verwaltungszuständigkeit steht in `adminScopes` (Department-Wurzeln im Admin-Scope des Profils, `profile.admin_capabilities.scope.department_root_ids`; der Unterbaum gehört dazu), ihre operativen Rollen nur ausdrücklich unter `members`. Früher sähte der Katalog für alle drei Konten `mw` in allen drei Szenario-Departments; das war ein Seed-Fehler (Ursache: `CreateRoleUsersCommand::getMembershipRole` speicherte sa/org/sub als `mw`, der Katalog übernahm das).

**Seed-Katalog (Version 2026.10.4):**

| Szenario | Organisation / Departments | Gruppen | Konten (Rolle) |
| --- | --- | --- | --- |
| `materialverwaltung` | Demo Organisation Materialverwaltung. Hierarchie: **Demo Kantonalverband** (selbst Department und Parent) → **Demo Materialverwaltung** (Szenario-Department) \| **Demo Abteilung Süd** → **Demo Abteilung Süd Aussenstelle** (drei Ebenen; zusätzliche Departments mit `demo_mode`, ohne Szenario-Schlüssel, ohne Prozessdaten) | Stufen (Biber, Wölfe, Pfadis, Pios, Rover), Materialteam | matwart `mw`, depchef `dc`, leader1–3 `l1`–`l3`, user `u`; **superadmin `u`** (primär, soweit nicht anderswo primär), **suborgchef `u`**; orgchef ohne Mitgliedschaft |
| `grossanlass-event` | Demo Organisation Grossanlass Event / Demo Grossanlass Event; **Gast-Abteilung** *Demo Gast-Abteilung* (normales Department, als angenommener Teilnehmer `DepartmentGrossanlassParticipant` des Grossanlasses) | Infrastruktur › Material & Logistik, Bauten, Wasser & Sanitär | ga-mw `mw`, ga-cmw `cmw`, ga-ok `dc`, ga-komm `komm`, ga-spon `spon`, ga-lw `lw`, ga-clw `clw`, ga-bereich `bl`, ga-helfer `u`; **superadmin `lw`** (nicht primär); **matwart `mw` zusätzlich in der Gast-Abteilung** (Kontextwechsel zwischen Materialverwaltung und Gast-Abteilung) |
| `grossanlass-camp` | Demo Organisation Grossanlass Camp / Demo Grossanlass Camp | Lagerinfrastruktur › Zelte, Küche; Material & Logistik › Materialverteilung, Transporte, Retouren; Lagergruppen › Gruppe A, Gruppe B | camp-mw `mw`, camp-lw `lw`, camp-bereich `bl`, camp-helfer `u`; **orgchef `u`**, **suborgchef `l2`** (beide nicht primär) |

**Verwaltungsbereiche (`adminScopes`, `roots` = Department-Wurzeln samt Unterbaum, `organisation` = die Organisation des Szenarios; kombinierbar, auch über Szenarien hinweg):** `orgchef` → Department *Demo Kantonalverband* (Kantonalverband, Materialverwaltung, Abteilung Süd, Aussenstelle) **und** Organisation *Demo Organisation Grossanlass Camp*; `suborgchef` → Department *Demo Abteilung Süd* (Abteilung Süd und Aussenstelle, **nicht** Kantonalverband oder Materialverwaltung) **und** Organisation *Demo Organisation Grossanlass Event* (Event und Gast-Abteilung). Jedes Szenario verwaltet im Profil nur den Anteil innerhalb seiner Organisation und Departments; fremde Einträge und von Hand ergänzte Zuweisungen bleiben unberührt, ein dort abweichend gesetzter Eintrag wird nie überschrieben. Der Orgchef ist im Camp zugleich normales Mitglied (`u`): Verwaltungs- und Mitgliedskontext sind getrennte Einträge. Superadmin hat keinen Scope (globaler Systemkontext, kein Department in der DB). **Ohne Zuweisung gäbe es keine Verwaltungsrechte**; kein Demo-Konto ausser den beiden hat eine Zuweisung. Orgchef und Suborgchef sind dadurch auf die Demo-Strukturen beschränkt (vorher: leerer Scope = unbeschränkt, also auch `Cevi ZH11`).

**Abdeckung der Fälle:** Superadmin mit globalem Kontext **und** normalen Department-Rollen (`u`, `lw`); Orgchef auf übergeordneter Ebene (Kantonalverband); Suborgchef für eine untergeordnete Hierarchie (Abteilung Süd); Orgchef/Suborgchef gleichzeitig als normale Mitglieder anderer Departments (`u` im Camp bzw. in der Materialverwaltung, `l2` im Camp); unterschiedliche Rollen derselben Person (superadmin `u`/`lw`, suborgchef `u`/`l2`); mehrstufige Parent-/Child-Departments; Gast-Abteilung im Grossanlass. Es gibt weiterhin genau die 23 Konten aus `demo-accounts.json`; keine zusätzlichen Benutzer, keine Änderung von Passwörtern oder TOTP.

Der Katalog gilt nur für die Organisationsstruktur. Prozessdaten (Zusagen, Einsätze, Aktivitäten, Material, Bedarf) fehlen weiter (**SOLL**, Phasen 4/5); die bestehenden Dienste `DemoGrossanlassSeedService::ensureDemoScenario` und Event-Jobs laufen unabhängig.

**Seed-Identitäten:** Ledger-Schlüssel `<szenario>:organisation`, `:department`, `:department:<key>` (zusätzliche Departments), `:department-parent` (Parent des Szenario-Departments), `:group:<key>`, `:membership:<konto>` (Szenario-Department) bzw. `:membership:<department-key>:<konto>`, `:groupmember:<konto>:<gruppe>`, `:participant:<department-key>` (Gast-Teilnehmer), `:adminscope:<konto>` (Verwaltungsbereich; Entity = Profil). Benutzer haben den **szenariounabhängigen** Schlüssel `demo-users:user:<konto>` (Ledger-Bereich `demo-users`, kein registriertes Szenario, ohne Department-Bezug); die Organisation ist global im Szenario-Ledger. Anzeigenamen sind nie Schlüssel; Katalog-Schlüssel werden nicht umbenannt.

**Sync-Verhalten (Dreiwege-Vergleich Katalog / aktuell / zuletzt vom Seed geschrieben, `managed_hash`):**

| Fall | Verhalten |
| --- | --- |
| Eintrag fehlt | anlegen, im Ledger verbuchen |
| Datensatz wurde gelöscht, Ledger-Eintrag besteht | unter demselben Seed-Schlüssel neu anlegen |
| aktuell = Katalog | nichts tun |
| aktuell = zuletzt geschrieben, Katalog hat sich geändert | **kontrolliert aktualisieren** (Version wird vermerkt) |
| aktuell ≠ zuletzt geschrieben (manuell geändert) | **nicht überschreiben**, als «Abweichung» melden |
| Eintrag nicht mehr im Katalog | **nicht löschen**, als «Verwaist» melden |
| Mitgliedschaft in `retiredMembers` (früher fälschlich gesät, z. B. automatische MW-Rolle von Superadmin/Orgchef/Suborgchef) | **entfernen nur, wenn** der Ledger-Eintrag sie als vom Seed geschrieben ausweist (aktuell = zuletzt geschrieben), sie keine Gruppenmitgliedschaft im Department hat; dann Mitgliedschaft **und** Ledger-Eintrag weg, zweiter Lauf tut nichts. Manuell geändert → bleibt, Hinweis. Eine Mitgliedschaft desselben Kontos im Katalog (z. B. superadmin `mw` → `u`) wird dagegen über den normalen Dreiwege-Vergleich aktualisiert. Mitgliedschaften ausserhalb der Szenario-Departments (z. B. in `Cevi ZH11`) werden nie angefasst |
| Verwaltungsbereich oder Parent bereits anders gesetzt | nie überschreiben; «Konflikt» bzw. «Abweichung» |
| vorhandener Datensatz ohne Ledger-Eintrag | nur bei nachweislicher Ownership übernehmen, sonst «Konflikt» (nichts verändern) |

Verwaltete Felder: Organisationsname, Departmentname, Parent (zusätzliche Departments und Szenario-Department), Gruppe (Name, Eltern, Sortierung, Art, Beschreibung), Benutzer (Vor-, Nachname, Spitzname), Mitgliedschaft (Rolle, Primär), Gruppenmitgliedschaft (Rolle, Primär), Teilnehmer-Status, Verwaltungsbereich (`department_root_ids`). **Nie** verwaltet nach der Anlage: Passwörter, Zustand, globale Profilrollen, TOTP, Clock-Offset, `demo_mode`/Grossanlass-Flag, Logistik-Gruppe (nur einmal gesetzt, wenn leer). Test-TOTP der Admin-Konten wird nur bei Neuanlage gesetzt.

**Ownership-Regeln:**

| Datensatz | Übernahme eines bestehenden Datensatzes |
| --- | --- |
| Benutzer | nur bei **exakter** Katalogadresse auf der reservierten Demo-Domain **und** eindeutigem Nachweis: (a) alle Mitgliedschaften gehören zu Demo-Departments (oder keine), oder (b) das Passwort ist das öffentliche Demo-Passwort. Mitglied eines echten Departments **und** anderes Passwort → Konflikt, Konto und Mitgliedschaften bleiben unverändert. Bei (b) bleibt die Mitgliedschaft im fremden Department unverändert und wird als Hinweis gemeldet (z. B. Alt-Rollen-User aus Phase 0/P4). Nach der Übernahme gilt der Bestand als Baseline und folgt dem Katalog (Namensfelder). Echte Benutzer (andere Adressen) werden nie übernommen, verändert oder gelöscht. |
| Department | **keine** automatische Übernahme. Ein bestehendes Demo-Department (z. B. `Demo Grossanlass`) wird nur durch `app:demo:adopt` ausdrücklich zugeordnet (E4); danach arbeitet Sync in dessen Organisation, ohne eine eigene anzulegen. Sonst legt Sync ein neues Department an. |
| Gruppe, Mitgliedschaft | nur innerhalb des bereits eigenen Departments: gleichnamige/gleiche Gruppe unter demselben Elternknoten, die noch keinem Ledger-Eintrag gehört (einmalige Übernahme), bzw. vorhandene Mitgliedschaft derselben Person |
| Organisation | nie übernommen; fehlt der Ledger-Eintrag, wird sie neu angelegt |
| Lieferant (`sharedAccounts`) | bei Neuanlage über `DemoSupplierSeedService` (Benutzer, Testfirma, Mitgliedschaft); ein vorhandenes Konto wird nur verbucht, nie verändert (kein Passwort-Reset) |

**Gemeinsam genutzte Demo-Benutzer und Reset:** Ein Szenario-Reset (SOLL, Phase 3) darf nur Inhalte seines Departments entfernen. Benutzer, Profile und der Ledger-Bereich `demo-users` bleiben erhalten; der Runner prüft das (vor/nach dem Reset müssen dieselben gemeinsamen Benutzer existieren, sonst Rollback). Mitgliedschaften im zurückgesetzten Department werden dort neu aufgebaut.

**Ablauf einer Erstinstallation:** Benutzer (global) → Organisation → Department (Grossanlass über den bestehenden Rahmen `DemoGrossanlassSeedService::ensureDepartment` mit Config, Kalender, Haupt-Aktivität und Uhr am Ausgangspunkt; Materialverwaltung mit Kostenstellen und Werkstatt-Kategorien) → Szenario-Schlüssel setzen → Gruppen → Mitgliedschaften. Alles in **einer** Transaktion unter Advisory-Lock (Runner). Mitgliedschaft `is_primary` wird nur gesetzt, wenn der User nicht schon anderswo primär ist.

**Verify:** meldet fehlende Seed-Einträge, fehlende Datensätze und Struktur-Verstösse (Gruppe/Mitgliedschaft nicht im Szenario-Department). Abweichungen und Verwaiste sind keine Verstösse, erscheinen nur beim Sync. **Dry-Run:** zählt fehlende Einträge, schreibt nichts.

**Tests:** Unit (`DemoOrganisationCatalogTest` inkl. Rollen-/Hierarchie-/Scope-Regeln, `ManagedSeedApplierTest`, `AdminDepartmentScopeAuthorizationTest`) laufen in der CI. Der Integrationstest `tests/Integration/DemoOrganisationSeedIntegrationTest` (Erstinstallation, wiederholter Sync, Rollenmatrix je Department, Hierarchie, Verwaltungsbereiche, Gast-Abteilung, Korrektur der früheren MW-Mitgliedschaften nur bei unverändertem Seed-Stand, geschützte Zustände, Strukturänderung, Konflikt/Übernahme, gelöschte Zeilen, Dry-Run, Unabhängigkeit der Szenarien, Legacy-Umbenennung, echte Daten unverändert) läuft nur mit `EMATCHEF_TEST_DB_URL` gegen eine **isolierte** Datenbank (Name `val_*` oder `*_test`, nie `mvdb`) und ist ohne die Variable übersprungen, also **nicht** Teil der CI. Er wurde gegen eine Wegwerf-PostgreSQL-16 ausgeführt (siehe Abschlussbericht der Rollenkorrektur: alle Tests grün); die Migration wurde dort inklusive Rollback geprüft.

**Bekannte Grenzen / offen:**

- Camp-Konten stehen in `demo-accounts.json`, das auch die öffentliche Doku (`docs.ematchef.ch`) speist; das Doku-Repository muss die neuen Konten und die fehlende `legacyEmail` tolerieren (nicht geprüft).
- `app:create-role-users` listet am Ende alle Katalogkonten, auch die noch nicht angelegten Camp-Konten.
- Die alten Rollen-User aus Phase 0 liegen auf Develop womöglich in einem echten Department (P4): mit dem Demo-Passwort werden sie übernommen (Mitgliedschaft im fremden Department bleibt, Hinweis); mit geändertem Passwort bleiben sie ein Konflikt und müssen manuell bereinigt werden.
- Anlagen- und Gruppen-IDs der Departments sind zufällig (`grp…`); stabil ist allein der Ledger-Schlüssel.
- Dauer: ein Voll-Sync legt ca. 23 Benutzer (inkl. Lieferant) mit Passwort-Hash an; der Integrationstest braucht rund eine Minute.

## 8. Umgang mit bestehenden Commands und Daten (SOLL)

| Baustein | Empfehlung |
| --- | --- |
| `app:create-role-users` | **Löschschleife entfernen** (P1, P2). Gelöscht wird nichts mehr. Logik zerlegt: Identity-Schritt (Konten, TOTP, Memberships), Lieferant, Szenarien. Für eine Übergangszeit dünner Alias auf `app:demo:sync --all` mit Deprecation-Hinweis; `--skip-delete` ist wirkungslos (immer an), `--with-ga-demo` entspricht `--scenario=grossanlass-event`. |
| `app:dev-demo:reset` | Umbenennen oder Alias auf Identity-Sync + E2E-Smoke; Name und Hilfetext korrigieren (kein Reset). Ausgabe `*@ematchef.ch` auf `@demo.ematchef.ch` korrigieren. |
| `app:recreate-test-users` | **Sofort absichern oder entfernen** (P8): mindestens `isDevToolsEnabled()` prüfen; Empfehlung entfernen, da `demo-accounts.json` die Konten abdeckt. Das ist eine Sicherheitskorrektur und nicht von der übrigen Architektur abhängig (Phase 0). |
| `app:demo-grossanlass:wipe` | durch `app:demo:reset --scenario=…` ersetzen; `--name` entfällt. Bis dahin: Bestätigung und `demo_mode`-Prüfung ergänzen. |
| `app:demo-grossanlass:event-jobs` | wird Teil von `grossanlass-event` (`sync`), legt das Department selbst an; eigener Command entfällt. |
| `app:ensure-demo-supplier` | bleibt als Baustein im Identity-/Lieferanten-Schritt. |
| `DemoGrossanlassSeedService` | wird `grossanlass-event`-Szenario; `ensureEinsatz` ohne Überschreiben von Status/Packzustand (P5); `ensureDepartment` über `demo_scenario_key` statt Name (P6). |
| `DemoGrossanlassWipeService` | wird `reset` des Szenarios; Tabellenliste wird testgeprüft (P13); Transaktion (P7). |
| `DepartmentResetService` | bleibt für den (devtools-geschützten) REST-Endpunkt und als Baustein des Szenario-Resets. Der Endpunkt `resetDb` ist **kein** Demo-Reset (seit Phase 0 umgebungsgeschützt) und sollte für Demo-Departments gesperrt oder auf den Szenario-Reset umgeleitet werden (OFFEN, E7). |
| `DevBootstrapContextService` | der Materialverwaltungs-Seed legt sein **eigenes** Demo-Department an (`materialverwaltung`); die Rollen-User gehören dorthin, nicht ins «erste sichtbare Department» (P4). Für `app:ensure-e2e-user`/Superadmin-Bootstrap unverändert. |
| `InboxDemoSeedService` | bleibt, wird dem passenden Szenario zugeordnet (OFFEN, E8). |
| Bestehende Demo-Departments `Demo Grossanlass`, `Demo-Grossanlass-Event` | **nicht löschen.** Einmalige, idempotente Migration (Phase 2): `Demo Grossanlass` bekommt `demo_scenario_key = grossanlass-event` (behält ID, Memberships, Uhr); `Demo-Grossanlass-Event` (nur Event-Jobs) wird in dieses Department überführt oder bleibt als Legacy bis zur Freigabe (OFFEN, E4). |
| Bestehende Rollen-User in einem Fremd-Department (P4) | **nicht automatisch umhängen.** Der Identity-Schritt legt die Memberships im Materialverwaltungs-Demo-Department an; alte Memberships werden in `app:demo:status` als «Altlast» gemeldet und manuell bereinigt. |
| Bestehende Daten auf Develop | `sync` ergänzt nur; ohne Ledger-Eintrag gefundene, passende Daten (Tags `[demo:v1:…]`, Name) werden **einmalig adoptiert** (Ledger-Eintrag), nicht dupliziert und nicht verändert. |

## 9. Umsetzungsphasen mit Tests (SOLL)

Jede Phase ist eigenständig freigebbar und endet mit grünen Tests. Phasen 0 und 1 enthalten keine neuen Szenarien. Migrationen (Phase 1, 2) sind additiv und verändern keine bestehenden Migrationen (AGENTS.md).

| Phase | Inhalt | Tests |
| --- | --- | --- |
| **0 Absichern (IST, umgesetzt)** | `app:recreate-test-users` abgesichert (P8); Löschschleife nur noch exakte Katalog-Adressen, opt-in (P1); Department-Ownership statt «erstes sichtbares» (P4); `demo_mode`-Prüfung, Bestätigung und Transaktion im Wipe (P7); `DemoEnvironmentGuard` (P12). Kein neues Feature. | Umgesetzt: `DemoEnvironmentGuardTest` (Matrix kernel × Name × Flag), `CreateRoleUsersCommandGuardTest`, `RecreateTestUsersCommandGuardTest`, `DemoSeedOwnershipTest` (Wipe, Department-Ownership, gleichnamiges Department), `DemoAccountsTest::testOnlyExactCatalogueEmailsAreSeedOwned`. |
| **1 Gerüst (IST, umgesetzt; siehe §7.0)** | `DemoScenarioInterface`, Registry, `SeedContext`, Runner mit Lock/Transaktion, `demo_scenario_key` (Migration), Ledger (Migration), Commands `app:demo:status/verify/sync/adopt/reset` (Reset gesperrt), Szenario-Ausgangspunkt-Resolver. Umgebungsschalter stammen aus Phase 0. **Nicht erledigt (weiter offen, E9):** Index-Herkunft `uniq_membership_one_primary_per_user` und Mapping↔DB-Abweichung `updated_by_user_id` klären. | Umgesetzt: Registry (Schlüssel, Duplikate), Identität (`assign`-Regeln, echte Departments abgewiesen), Isolation (`SeedContext`, Ledger-Scope und Schlüsselpräfix), Runner (Lock, Rollback, Department-Zeile bleibt), Commands über die Umgebungsmatrix, Reset gesperrt für alle echten Szenarien, Clock-Resolver, Container-Wiring (`tests/Service/Demo/Scenario/`). **Nicht** getestet: Migration und Doctrine-Mapping gegen eine echte Datenbank. |
| **2 Demo-Organisationen (IST, umgesetzt für Organisationsstruktur; siehe §7.11)** | Umgesetzt: Katalog, Konten, Gruppen, Memberships, Primär-Regel, Übernahme mit Ownership-Nachweis, drei Szenarien. **Noch offen aus dieser Phase:** bestehendes `Demo Grossanlass` + Event-Jobs als Prozessdaten von `grossanlass-event`, `ensureEinsatz` ohne Überschreiben (P5), Adoption der Alt-Tags ins Ledger. | **T2** Idempotenz: `sync` zweimal → identische Zeilenzahlen, keine Duplikate; **T4** Zustandserhalt: Einsatz-Status ändern, `sync`, Status bleibt; **T3** Isolation: andere Departments unverändert; **T10** Primär-Mitgliedschaft mit Vor-Zustand (User primär anderswo) → kein Fehler, nur eine Primäre; Wiederholung der beiden bekannten FK-/Primär-Fälle als Regressionstest. |
| **3 Reset** | `reset` je Szenario (Variante A oder B nach Entscheid E1), Transaktion, Advisory Lock, `--confirm`, Tabellenliste mit Schema-Test; `app:demo:reset`. | **T5** jede Tabelle mit FK auf Department/Aktivität ist in der Löschliste (Schema-Introspektion); **T6** Abbruch mitten im Reset (injizierte Exception) → Rollback, Szenario unverändert; **T7** Reset von Szenario A lässt B, Konten und fremde Departments unberührt; Lock-Test (zweiter Lauf verweigert). |
| **4 Materialverwaltung** | neues Szenario `materialverwaltung` (Lager, Gruppen, Konten, Aktivitäten je Typ, Bedarf, Packen, Ausgabe, Rückgabe, Inventur, Werkstatt) mit eigenem Clock-Resolver; Rollen-User wandern in dieses Department (P4). | **T2–T4** wie oben; **verify()** Mengenbilanz Department (§6); `public_code`-Auflösung; Test, dass die Uhr unabhängig von Event/Camp ist. |
| **5 Grossanlass Camp** | neues Szenario `grossanlass-camp`; eigener Ausgangspunkt. Abhängig von Entscheid E2 (Domain-Modell Camp). | **T2–T4**, **verify()** Mengenbilanz Grossanlass (§6), Isolation zu Event. |
| **6 Deployment** | Sync-Schritt in `prod-update.sh` hinter `EMATCHEF_DEMO_SYNC`; Log/Summary; Doku in `deploy/`/`docs/APP-ON-DROPLET.md`. | **T1** Skript-Test (Shell-Mock): Schritt läuft nur mit Schalter, nie mit `reset`; **T9** Browser-Reload/GET-Endpunkte schreiben nichts (Request-Test auf Clock- und Lese-Endpunkte); manuelle Develop-Abnahme (Deployment zweimal, Daten unverändert). |
| **7 Sandbox** | Side-Effect-Sandbox ([README §5](./README.md#5-demo-sicherheit-und-side-effect-sandbox)), Seed-Kontext-Flag. | **T8** pro Adapter ein Test; Seeds mit gesperrten Adaptern laufen durch. |

Reihenfolge begründet: 0 sofort (Sicherheit), 1 vor allen Szenarien, 2 vor 3, weil Reset die Identitäten und das Ledger voraussetzt. Phasen 4 und 5 sind unabhängig voneinander. Die in [README §6](./README.md#6-entwicklung-und-teststrategie) festgelegte Reihenfolge (Grossanlass zuerst) bleibt gültig: Phase 2 deckt Event.

## 10. Offene Entscheidungen

Nummerierung E1… ist neu (Seed-Architektur); Fragen 1–9 bleiben in [README §7](./README.md#7-offene-architekturfragen-übersicht).

| # | Entscheidung | Empfehlung | Betrifft |
| --- | --- | --- | --- |
| E1 | Reset: Department-Zeile behalten (A) oder löschen und neu anlegen (B)? | A | §7.7 |
| E2 | Domain-Modell **Camp**: dieselben Grossanlass-Entities (`is_grossanlass`, Zelte/Küche als Ressorts/Material) oder eigenes Profil? Welche Camp-Funktionen (Zelte, Küche) existieren im Backend heute? Nicht geprüft. | zuerst Bestandsaufnahme, dann Entscheid | [GROSSANLASS.md](./scenarios/GROSSANLASS.md) |
| E3 | Ownership: Ledger-Tabelle (in Phase 1 gebaut, noch ungenutzt), nur `department_id` + Schlüssel, oder deterministische IDs? | Ledger | §7.3, §7.4 |
| E4 | Schicksal der bestehenden Departments `Demo Grossanlass` und `Demo-Grossanlass-Event`: zusammenführen in `grossanlass-event` (Empfehlung, entspricht dem Zielbild), Daten migrieren oder Legacy stehen lassen? (Beantwortet Frage 2 inhaltlich, Umsetzung offen.) | zusammenführen, Legacy bleibt bis Freigabe | §8 |
| E5 | Soll **Staging** Demo-Daten tragen (nur manueller Sync) oder keine? | nur manuell, nie automatisch | §7.9 |
| E6 | Deployment: Sync-Fehler nur warnen (Empfehlung) oder Deployment abbrechen? | warnen | §7.9 |
| E7 | REST `resetDb`/`resetActivities`: Umgebungsfreigabe ist seit Phase 0 umgesetzt. Offen: für Demo-Departments sperren oder auf den Szenario-Reset umleiten? | sperren | §8 |
| E8 | Zuordnung `InboxDemoSeedService` und weiterer Hilfs-Seeds zu Szenarien. | Materialverwaltung | §8 |
| E9 | Herkunft von `uniq_membership_one_primary_per_user` (nirgends im Repo definiert) und der Abweichung `ON DELETE` bei `fk_grossanlass_wish_response_updated_by`; Behebung per Migration oder Mapping-Anpassung. | in Phase 1 klären | P2, P3 |
| E10 | Mehrere Prozesszustände: Variante (a) «nur Ist-Daten bis Ausgangspunkt» (Frage 1). | (a) | §5.3 |
| E11 | Aktivitätstypen für «mindestens eine Aktivität je Typ»: `activity.type` ist ein String ohne zentrale Aufzählung; Typen und Packprofile (`profileForActivityType`) müssen vor Phase 4 inventarisiert werden. | Inventar in Phase 4 | [DEPARTMENT.md](./scenarios/DEPARTMENT.md) |
