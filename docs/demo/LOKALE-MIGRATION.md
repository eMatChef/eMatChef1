# Lokale Demo-Migration (WSL + Docker, Datenbank `mvdb`)

**Stand:** 8. Oktober 2026. Einmaliger Plan, um die lokale Entwicklungsdatenbank auf die neuen Demo-Seeds umzustellen und die alten Demo-Departments zu entfernen. Marken **IST / SOLL / OFFEN** wie in [README.md](./README.md). **Ausgeführt am 8. Oktober 2026 (IST):** Backup, Migrationen, `app:demo:sync --all`, `app:demo:verify --all` und die `old-`-Umbenennung (§8). Gelöscht wurde **nichts**; die Löschung der Legacy-Daten (§5, «Später») ist weiter ein eigener, späterer Schritt. Die Inventur davor war ausschliesslich lesend, die Proben liefen auf Kopien.

**Änderung der Entscheidung (8. Oktober 2026): Nichts wird gelöscht.** Die in diesem Dokument beschriebene Löschung der Legacy-Demo-Departments ist **verschoben**. Die Legacy-Strukturen bleiben unverändert bestehen, bis **alle** neuen Seeds fertig sind; sie werden vorläufig nur durch das Präfix `old-` im Anzeigenamen gekennzeichnet (§5a). Die Löschung (§5, «Später») ist ein eigener, späterer Arbeitsschritt mit eigener Freigabe.

Festgelegte Entscheidungen: WSL + Docker ist die Ausführungsumgebung · `mvdb` bleibt bestehen (kein globaler Reset, keine Volume-/DB-Löschung) · Demo-Organisationen und gemeinsame Demo-Benutzer entstehen vollständig durch `app:demo:sync --all` · alte Demo-Departments werden nicht übernommen, sondern erst **nach** erfolgreichem Sync lokal entfernt.

## 1. Umgebung (IST, geprüft)

| Punkt | Befund |
| --- | --- |
| Compose-Projekt | `ematchef`; Container `ematchef-db-1` (postgres:16-alpine, healthy), `-backend-1`, `-frontend-1`, `-nginx-1`, `-adminer-1` |
| Datenbank | `mvdb`, Benutzer `mvuser`, 22 MB, 34 Benutzer/Profile; Volume `ematchef_postgres_data`; **kein Host-Port** (Zugriff nur im Netz oder per `docker exec`) |
| Backend | `APP_ENV=dev`, `DATABASE_URL=…@db:5432/mvdb`; Quellcode per Bind-Mount `./backend` (es läuft immer der **ausgecheckte Branch**, derzeit `feat/demo-seed-architecture`); `EMATCHEF_ENV_NAME` ist nicht gesetzt, was auf einem dev-Kernel als `local` gilt: Demo-Befehle und löschende Befehle sind erlaubt |
| Migrationsstand | **257** Einträge, letzter `Version20261008110000` (am 8. Oktober 2026 angewendet: `Version20261008100000` Szenario-Schlüssel/Ledger, `Version20261008110000` Ledger-Hash; beide additiv). Vorher 255, letzter `Version20261007100000` |
| Achtung Entrypoint | `backend/docker-entrypoint.sh` führt bei **jedem Start des Backend-Containers** `doctrine:migrations:migrate` aus. Ein `docker compose up/restart backend` würde die beiden Migrationen also ohne weiteres Zutun auf `mvdb` anwenden. Vorher Backup (§5, Schritt 1) |
| Abweichung | 3 Migrationen sind in `mvdb` eingetragen, aber in diesem Branch nicht vorhanden (`Version20260805120000`, `Version20260813190000`, `Version20260813210000`, von anderen Branches). Harmlos («Executed Unavailable»), nicht ändern |
| Sonst | Keine Demo-Seeds laufen automatisch (kein Deployment-Schritt, kein Cron) |

## 2. Einsatzbereitschaft (IST)

- Tests: PHPUnit 847 Tests grün (6 übersprungen = Integrationstest ohne Test-DB), PHPStan ohne Fehler.
- **Probelauf auf einer Kopie** (`pg_dump` von `mvdb` → Wegwerf-PostgreSQL auf Port 55433, tmpfs): alle Schritte aus §5 liefen durch, Zahlen in §4. Die Kopie ist inzwischen verworfen; die Probe lässt sich jederzeit wiederholen.
- Beim Probelauf gefunden und behoben: (a) übernommene Datensätze wurden wegen eines noch nicht geflushten Ledger-Eintrags fälschlich als «Abweichung» gemeldet und nicht aktualisiert; (b) `app:demo:sync` zeigte Abweichungen, Konflikte und Hinweise nicht an. Beides ist korrigiert und getestet.
- **Gebaut (IST):** `app:demo:legacy-rename` (§5a) kennzeichnet die Legacy-Strukturen mit `old-`; getestet (Unit, Integration gegen isolierte DB, Probelauf auf einer `mvdb`-Kopie: 44 Namen, alle übrigen Spalten und Zeilenzahlen identisch).
- **Noch nicht gebaut (verschoben):** ein Werkzeug für die spätere Löschung. Die Probe lief mit einem Wegwerf-Skript. **OFFEN (D2).**

## 3. Inventur der Demo-Departments (read-only, `mvdb`)

| Department | ID | Organisation | `demo_mode` | Mitglieder | Eindeutig Legacy-Demo? |
| --- | --- | --- | --- | --- | --- |
| Demo Grossanlass | `7aa39b221bab` | Cevi Schweiz `3ec70bfc4560` | ja | 9 (`ga-*@demo.ematchef.ch`, sonst nirgends Mitglied) | **Ja, vom Seed erzeugt** (alle 8 Einsätze tragen `[demo:v1:…]`, 4 Gruppen, 2 Zusagen, 2 Orte, 9 Fahrkarten, Uhr-Offset 101960 s) |
| Demo-Grossanlass-Event | `3dc94912d836` | Demo-Organisation `5f35b7cde9b5` | nein | 0 | **Ja, aber von Hand aufgebaut:** 34 Gruppen (u. a. Event-Jobs-Katalog), 23 Orte, 15 Kosten, 4 Packs, 3 Einsätze, 4 Fahrzeugbedarfe, 23 Beschaffungskategorien, 12 Audit-Einträge. Kein echtes Mitglied, kein fremder Verweis |
| Demo-Grossanlass-Camp | `72605b231274` | Demo-Organisation | nein | 0 | Ja (nur Rahmen: Haupt-Aktivität, Config, Kategorien, 23 Beschaffungskategorien); Kind von `7ae5770a1180` |
| Demo-Department | `638c8d301090` | Demo-Organisation | nein | 0 | Ja (nur Standard-Rahmen: Kostenstellen, Kategorie, Einstellungen); Kind von `7ae5770a1180` |
| Demo-Department-Parent | `7ae5770a1180` | Demo-Organisation | nein | 0 | Ja (nur Standard-Rahmen); Eltern von `638c8d301090` und `72605b231274` |
| Organisation «Demo-Organisation» | `5f35b7cde9b5` | – | – | – | Ja: enthält nur die vier obigen Departments, keine weiteren Verweise (`external_structure_identity`: 0) |

Kriterien für «eindeutig»: (1) keine Mitgliedschaft eines Benutzers ausserhalb der Demo-Domain, (2) kein Verweis fremder Departments (Eltern/Kind ausserhalb der Liste, Teilnehmer, Gast-/Host-Freigaben, Zusagen-Eigentümer, verknüpfte Lieferanten, `last_used_department_id`), (3) Name und Organisation passen zur Allowlist, (4) kein Szenario-Schlüssel. Alle fünf Departments erfüllen sie. Die Audit-Einträge von `3dc94912d836` stammen von `superadmin@demo` und vier inzwischen gelöschten Benutzern.

**Nicht Legacy-Demo, bleibt unberührt:** `Cevi ZH11` (`4a7ff445ba70`, Org Cevi Schweiz): importiertes Department ohne Material und Aktivitäten, dessen **9 Mitglieder ausschliesslich Demo-Konten** sind (matwart, depchef, leader1–3, user, orgchef, suborgchef, superadmin: Folge von Problem P4). Das Department wird nicht gelöscht. Weiter unberührt: Cevi Schweiz, Pfadibewegung Schweiz (PFF 2027, Pfadi Zürich u. a.), Hardscout, J+S, e2e-smoke-Konto.

## 4. Abhängigkeiten und Probelauf-Ergebnisse

**Fremdschlüssel auf `department`:** 55 Spalten, fast alle `ON DELETE CASCADE`; `RESTRICT` nur bei `supplier_delivery`; `SET NULL` bei `department.parent_id`, `user.last_used_department_id`, Beitrittsanfragen, Lieferant-Verknüpfung. Organisation → Department ist `CASCADE` (Organisation erst löschen, wenn sie leer ist).

**Zeilen je Legacy-Department (Auszug, `mvdb`):** Event 34 Gruppen, 23 Orte, 23+23 Kategorien/Beschaffung, 15 Kosten; Demo Grossanlass 4 Gruppen, 8 Einsätze, 8 Packs, 9 Fahrkarten, 9 Mitgliedschaften; die übrigen drei je rund 20 Rahmenzeilen (Kostenstellen + Regeln, Einstellungen, Kategorie, Audit, Posteingang).

**Lücken des bestehenden Wipe-Dienstes** (im Probelauf gefunden, würden Waisen hinterlassen): `accounting_cost_center_rule`, `public_code` und `department_grossanlass_pack` (+ `_pack_line`) haben `department_id` **ohne** Fremdschlüssel und werden vom Wipe nicht erfasst; sie müssen ausdrücklich gelöscht werden. `DemoGrossanlassWipeService` verlangt ausserdem `demo_mode`, die Departments Event/Camp tragen das Flag nicht; die Bereinigung setzt es für die Allowlist ausdrücklich in derselben Transaktion.

**Probelauf auf der Kopie (Reihenfolge §5):** Migration ✓ · `sync --all`: Camp 26 neu/5 übernommen, Event 24 neu/10 übernommen/9 aktualisiert, Materialverwaltung 22 neu/7 übernommen; 0 Konflikte, 0 Abweichungen · zweiter Sync: 0 Änderungen · `verify --all` ✓ · Bereinigung: Event 295, Camp 38, Demo-Department 8, Parent 8, Demo Grossanlass 66 Zeilen, Organisation 1 · fremde Daten (Hash über `department`, `membership`, `group`, `activity`, `material_item`, `group_membership`) **unverändert** · 23 Demo-Benutzer und 94 Ledger-Einträge **unverändert** · Waisen-Scan: nur die schon vorher vorhandenen (5× `accounting_cost_center_rule` eines früher gelöschten Departments `0eb93be81f7e`, 2× `audit_event` `GLOBAL000000`) · `verify` ✓ und Sync danach ohne Änderung.

Beim Sync werden die neun Rollen-User in `Cevi ZH11` per Demo-Passwort-Nachweis übernommen (Hinweis «bleibt Mitglied von Cevi ZH11», nichts verändert). Die `ga-*`-Konten werden aus `Demo Grossanlass` in das neue Event-Department übernommen.

## 5. Plan für die einmalige lokale Ausführung (SOLL)

Alle Befehle im Backend-Container: `docker exec ematchef-backend-1 php bin/console …` (Kernel `dev` = `local`, keine Freigabe-Variablen nötig). Nach **jedem** Schritt prüfen und bei Abweichung stoppen.

| # | Schritt | Befehl / Inhalt | Prüfung |
| --- | --- | --- | --- |
| 0 | Voraussetzungen | Branch `feat/demo-seed-architecture` ausgecheckt; Backend-Container **nicht** neu starten (Entrypoint würde migrieren) | `git branch --show-current`; `docker ps` |
| 1 | **Backup** | `docker exec ematchef-db-1 pg_dump -U mvuser -d mvdb -Fc > ~/backups/ematchef/mvdb-<datum>.dump` (ausserhalb des Repos, Rechte 600). Wiederherstellbarkeit beweisen: Dump in eine **Wegwerf**-DB restoren und `select count(*) from department` vergleichen (15) | Datei vorhanden, Restore ok |
| 2 | Migrationen | `php bin/console doctrine:migrations:migrate` (2 additive Migrationen) | `doctrine:migrations:status`: New = 0 |
| 3 | Sync trocken | `php bin/console app:demo:sync --all --dry-run` | keine Konflikte erwartet |
| 4 | Sync | `php bin/console app:demo:sync --all` | Camp/Event/Materialverwaltung «OK»; Hinweise zu Cevi ZH11 erwartet; **Konflikte = 0**, sonst stoppen |
| 5 | Verify + Wiederholung | `app:demo:verify --all`; Sync ein zweites Mal | alles ✓, zweiter Sync 0 Änderungen; im UI mit `ga-mw@`, `matwart@`, `camp-mw@` (Passwort `test!ematchef`) anmelden |
| 6 | **Legacy kennzeichnen** (erst nach 5; ersetzt die Löschung) | `php bin/console app:demo:legacy-rename` (Dry-Run, zeigt den Plan und den Bestätigungscode), dann `--execute --confirm=<Code>`; Details §5a. Danach `app:demo:verify --all` | Dry-Run: 44 Namen (1 Organisation, 5 Departments, 38 Gruppen); danach «Nichts zu tun» |
| 7 | Nachher | `app:demo:sync --all` (Idempotenz-Check), `app:demo:verify --all` | alle neuen Departments unverändert ✓; Demo-Benutzer 23, Ledger 94 |
| 8 | Optional, nur nach Freigabe (D1) | Mitgliedschaften der neun Demo-Konten in `Cevi ZH11` entfernen | – |
| **Später** | **Legacy-Löschung (verschoben, nicht Teil des aktuellen Auftrags)** | Beschreibung der früheren Schritte 6 und 7 unten als Referenz |
| (früher 6) | Legacy-Bereinigung | Allowlist §3: Reihenfolge `3dc94912d836`, `72605b231274`, `638c8d301090`, `7ae5770a1180`, `7aa39b221bab`, danach Organisation `5f35b7cde9b5`. Je Department eigene Transaktion. Vorbedingungen prüfen (§3 Kriterien, ID **und** Name), `demo_mode` setzen, GA-Departments über `DemoGrossanlassWipeService`, übrige über `DepartmentResetService::resetDepartment`, dazu `public_code`, `accounting_cost_center_rule`, `department_grossanlass_pack(_line)`, `department_calendar_period`, `department_setting`, `inbox_message`, `audit_event`, `membership`, `last_used_department_id` → NULL, zuletzt Zeile `department`; Organisation nur, wenn leer | Zeilenzahlen = §4; Fingerabdruck fremder Daten unverändert |
| (früher 7) | Nachher (nur nach Löschung) | `app:demo:sync --all` (setzt Primär-Mitgliedschaften der `ga-*` im neuen Event-Department neu, weil `Demo Grossanlass` dann wegfällt), `app:demo:verify --all`, Waisen-Scan (nur die zwei bekannten Altlasten) | Demo-Benutzer 23, Ledger 94 |

**Rückweg:** Umbenennung: dieselben Namen von Hand zurücksetzen (nur Anzeigenamen; die alten Namen stehen im Dry-Run/Backup) oder das Backup aus Schritt 1 in eine neue Datenbank restoren. Bis zur späteren Löschung genügt es, die neuen Departments nicht zu nutzen (additiv). Nach Schritt 6: Dump aus Schritt 1 in eine **neue** Datenbank restoren und die Backend-`DATABASE_URL` umstellen; `mvdb` selbst wird nicht gelöscht oder überschrieben, solange nicht ausdrücklich entschieden.

**Warum diese Reihenfolge:** Backup vor jeder Änderung; Migration vor dem Sync (Spalten/Tabellen); Sync **vor** der Bereinigung (Vorgabe: erst entfernen, wenn die neuen Seeds erfolgreich sind) und weil die Demo-Konten dadurch bereits neue Mitgliedschaften haben; Bereinigung zuletzt; ein zweiter Sync danach korrigiert Primär-Markierungen.

### 5a. Legacy-Umbenennung `old-` (IST, auf `mvdb` ausgeführt am 8. Oktober 2026)

`php bin/console app:demo:legacy-rename [--execute --confirm=<Code>]` · Klassen `LegacyDemoRename`, `DemoLegacyRenameCommand`.

| Aspekt | Verhalten |
| --- | --- |
| Erlaubte Umgebung | **ausschliesslich lokal** (`DemoEnvironmentGuard::localOnlyDenial`: Nicht-prod-Kernel, `EMATCHEF_ENV_NAME` leer oder `local`); auf Develop, Staging, Production und prod-Kernel verweigert, auch als Dry-Run |
| Standard | **Dry-Run**: zeigt Plan (Typ, ID, aktuell, neu, Status) und den Bestätigungscode, schreibt nichts |
| Ausführen | `--execute --confirm=<Code>`; der Code ist ein Hash des Plans und passt nur zum geprüften Stand. Ohne oder mit falschem Code: Abbruch |
| Identifikation | feste IDs (fünf Departments, Organisation `5f35b7cde9b5`) **und** Namensprobe: weicht der Name vom inventarierten ab, wird übersprungen und gemeldet. Gleichnamige Departments/Gruppen anderer IDs werden nie angefasst. Departments mit `demo_scenario_key` werden nie angefasst |
| Gruppen | alle Gruppen der fünf Legacy-Departments (Auswahl über `department_id`, nicht über den Namen) |
| Doppeltes Präfix | ausgeschlossen: ein Name, der schon mit `old-` beginnt (Gross-/Kleinschreibung egal), gilt als erledigt. Der Befehl ist idempotent (zweiter Lauf: «Nichts zu tun») |
| Kollisionen | Zielname schon vergeben (gleiche Organisation bzw. gleiches Department und Elterngruppe) oder länger als 255 Zeichen → übersprungen und gemeldet |
| Geändert | **nur** `name` von `organisation`, `department`, `group` (eine Transaktion). IDs, Codes, externe Identitäten, Benutzer, Mitgliedschaften, Gruppenmitgliedschaften, Prozessdaten, Szenario-Registry und Ledger bleiben unverändert; es wird nichts gelöscht |
| Nicht umbenannt | Haupt-Aktivität und Kalenderperiode der Grossanlass-Legacy-Departments (spiegeln den Departmentnamen, sind Fachdaten); technisch ohne Wirkung auf Schlüssel |
| Neue Seeds | übernehmen keine Legacy-Daten. Die alten Seed-Dienste legen unter den **ausgemusterten Namen** (`Demo Grossanlass`, `Demo-Grossanlass-Event`, …, und jedem Namen mit `old-`) nichts mehr neu an: `DemoGrossanlassSeedService::ensureDepartment` wirft, `app:create-role-users --with-ga-demo` bricht vor jedem Schreiben ab, `app:demo-grossanlass:event-jobs` verweigert `old-`-Departments. Vorhandene unveränderte Legacy-Departments funktionieren bis zur Umbenennung wie bisher |
| Probelauf | Kopie von `mvdb`: 44 Namen (1 Organisation, 5 Departments, 38 Gruppen); Fingerabdrücke aller übrigen Spalten von `organisation`, `department`, `group`, `membership`, `group_membership`, `user`, `profile`, `department_grossanlass_einsatz`, `activity` und die Zeilenzahlen identisch; zweiter Lauf ohne Änderung; anschliessend `app:demo:sync --all` unverändert erfolgreich, legt keine alten Namen neu an |

Die Legacy-Daten bleiben bis zur Fertigstellung **aller** neuen Seeds bestehen. Erst dann folgt, separat und mit eigener Freigabe, die Löschung nach dem Plan unten («Später»).

## 8. Ausführungsprotokoll `mvdb` (8. Oktober 2026, IST)

Ausgeführt im Container `ematchef-backend-1` (`APP_ENV=dev`, `EMATCHEF_ENV_NAME` leer = lokal, `MAILER_DSN=null://null` für die Befehle gesetzt) gegen `ematchef-db-1` / `mvdb`. Reihenfolge wie §5; vor den schreibenden Schritten stimmten Datenbank, Departments (15), Benutzer (34) und Migrationsstand (255) mit der Inventur überein.

| Schritt | Ergebnis |
| --- | --- |
| Backup | `~/backups/ematchef/mvdb-20261008-203119-pre-demo-activation.dump` (`pg_dump -Fc`, 596 KB, Rechte 600, SHA-256 `fe1f3162…0043` in `….dump.sha256`). **Restore-Test** in eine isolierte Wegwerf-DB (eigener Container, Port 55434): erfolgreich, Zeilenzahlen aller 130 Tabellen identisch mit `mvdb`, Migrationsstand und 15 Departments stimmen |
| Migrationen | genau die zwei erwarteten, 9 SQL-Statements, 108 ms; danach 257 Einträge |
| `app:demo:sync --all` | Camp: 26 neu, 5 übernommen · Event: 24 neu, 10 übernommen, 9 aktualisiert · Materialverwaltung: 22 neu, 7 übernommen · **0 Konflikte, 0 Abweichungen, 0 verwaist**. Hinweise: die neun Rollen-User bleiben Mitglied von `Cevi ZH11` (unverändert) |
| `app:demo:verify --all` | alle drei ✓ (vor und nach der Umbenennung) |
| `app:demo:legacy-rename` Dry-Run | exakt die Allowlist: Organisation `5f35b7cde9b5`, Departments `7aa39b221bab`, `3dc94912d836`, `72605b231274`, `638c8d301090`, `7ae5770a1180`, 38 Gruppen; Bestätigungscode `6e6b98e11c` (identisch zum Probelauf auf der Kopie) |
| Umbenennung | `--execute --confirm=6e6b98e11c`: 44 Namen mit `old-` gekennzeichnet; zweiter Lauf «Nichts zu tun»; kein doppeltes Präfix |
| Danach | `sync --all`: alles unverändert (0 neu, 0 aktualisiert); `verify --all` ✓ |

**Neue Demo-Departments:**

| Szenario | Department | ID | Organisation | Mitglieder | Uhr (Offset) |
| --- | --- | --- | --- | --- | --- |
| `materialverwaltung` | Demo Materialverwaltung | `b57aa6184ef5` | Demo Organisation Materialverwaltung (`0e3b342a2963`) | 9 | reale Zeit |
| `grossanlass-event` | Demo Grossanlass Event | `2d42e87c5197` | Demo Organisation Grossanlass Event (`9f35acfd679f`) | 12 | 138467 s |
| `grossanlass-camp` | Demo Grossanlass Camp | `c8f78a2d6766` | Demo Organisation Grossanlass Camp (`c79529db1108`) | 7 | 138467 s |

**Legacy (unverändert ausser Anzeigenamen, `old-`):** `old-Demo Grossanlass` (`7aa39b221bab`, Org Cevi Schweiz, 9 Mitglieder, Offset 101960 s), `old-Demo-Grossanlass-Event`, `old-Demo-Grossanlass-Camp`, `old-Demo-Department`, `old-Demo-Department-Parent` (alle in `old-Demo-Organisation`, 0 Mitglieder); alle 38 Gruppen `old-…`.

**Nachprüfung (Vorher-/Nachher-Snapshots von `mvdb`):**

- Alle 34 bisherigen Benutzer: Passwort-Hash, Zustand und `last_used_department_id` **unverändert**; kein Benutzer entfernt; neu nur `camp-mw`, `camp-lw`, `camp-bereich`, `camp-helfer` (Lieferant war schon vorhanden). 23 Demo-Konten gesamt.
- Mitgliedschaften: 40 → 68; **keine** entfernt oder verändert (nur 28 neue: 12 Event, 9 Materialverwaltung, 7 Camp). Gruppenmitgliedschaften 13 → 29 (+16 neue; die 4 der Legacy-Gruppen und 9 anderer Departments unverändert).
- Profile: nur die neun `ga-*` haben die Katalognamen übernommen (vorgesehene Aktualisierung); alle anderen Profile unverändert.
- Fremde Departments (Cevi ZH11, Corps Musegg, Hardscout, J+S, PFF 2027, Pfadi Effi/Luzern/Zytturm/ZüriOberland/Zürich): Zeilen und Namen unverändert. `Cevi ZH11` hat weiter 9 Mitglieder.
- Tabellenzahlen: nur die erwarteten Zunahmen (3 Departments/Organisationen, 4 Benutzer/Profile, Gruppen +21, Kostenstellen, Aktivitäten +2 usw.); die Umbenennung selbst änderte keine Zeilenzahl.
- Ledger: `demo-users` 23, Camp 23, Event 25, Materialverwaltung 23.
- Es wurden keine Nachrichten versendet (kein Mailer-Aufruf in den Seeds, zusätzlich `MAILER_DSN=null://null`).

**Beobachtungen / Folgen:**

1. **Primär-Mitgliedschaft:** Die `ga-*`-Konten sind weiter **primär in `old-Demo Grossanlass`**, im neuen Event-Department nicht primär (der Sync setzt nie eine zweite Primäre). Ein Login mit `ga-*` landet deshalb zunächst im Legacy-Department. Erst nach der späteren Löschung (oder wenn man dort die Primär-Markierung entfernt, D1/D6) wird das neue Department primär; ein erneuter Sync erledigt das dann automatisch. Gleiches gilt für `superadmin`/`orgchef`/`suborgchef`, die in `Cevi ZH11` primär bleiben.
2. Die drei Migrationen «Executed Unavailable» (§1) bestehen unverändert.
3. Keine Fehler, keine Abbrüche; es musste kein Schritt wiederholt werden.

## 6. Sind die gemeinsamen Demo-Benutzer gefährdet? (geprüft)

**Nein.** Die Umbenennung ändert keine Benutzerdaten. Die spätere Bereinigung löscht nur Zeilen mit `department_id` der Allowlist; `user`, `profile`, `user_totp` und der Ledger-Bereich `demo-users` werden nicht berührt. Im Probelauf blieben alle 23 Demo-Konten (inklusive Passwort-Hashes) und alle 94 Ledger-Einträge identisch. Es verschwinden nur die **alten Mitgliedschaften** in `Demo Grossanlass` (9); die neuen Mitgliedschaften in Event/Camp/Materialverwaltung bleiben. Kein Benutzer hat ausserhalb der Allowlist eine Mitgliedschaft, die gelöscht würde (`Cevi ZH11` wird nicht angefasst). `User.last_used_department_id` zeigt aktuell auf keine Legacy-Department (0 Treffer); der Plan setzt es dennoch auf NULL. Der Runner schützt gemeinsame Benutzer zusätzlich bei jedem künftigen Szenario-Reset ([SEED-KONZEPT.md §7.5, §7.11](./SEED-KONZEPT.md#711-phase-2-demo-organisationen-ist)).

## 7. Offene Entscheidungen

| # | Frage | Empfehlung |
| --- | --- | --- |
| D1 | Mitgliedschaften der neun Demo-Konten in `Cevi ZH11` nach dem Sync entfernen? Sonst bleibt `superadmin` dort primär und die Sync-Hinweise bleiben | Entfernen (Department ist danach leer, wird aber nicht gelöscht), als separater Schritt 8 |
| D2 | (für die spätere Löschung) Bereinigungswerkzeug: eigener Command `app:demo:legacy-cleanup` (Allowlist ID+Name, Dry-Run als Standard, `--execute --confirm`, nur `local`/freigegebenes `develop`) oder einmaliges Skript? | Command mit Dry-Run, damit der Plan reproduzierbar und testbar ist; danach wieder entfernen |
| D3 | Der von Hand aufgebaute Inhalt von `Demo-Grossanlass-Event` (34 Gruppen, 23 Orte, 15 Kosten …) geht verloren, ausser er steckt im Backup. Soll vorher etwas gesichert oder in den Seed-Katalog übernommen werden? | Backup genügt, wenn der Inhalt nicht gebraucht wird; sonst vorher gezielt exportieren |
| D4 | Bestehende Waisen (5× `accounting_cost_center_rule`, 2× `audit_event` `GLOBAL000000`) mitbereinigen? | Nein, ausserhalb dieses Auftrags |
| D6 | Primär-Mitgliedschaft der `ga-*`-Konten (und der Admin-Konten) liegt weiter im Legacy-Department bzw. in `Cevi ZH11`. Bis zur Löschung dort manuell lösen oder so lassen? | So lassen bis zur Löschung (der Sync korrigiert danach automatisch) |
| D5 | Wipe-Dienst um die drei Tabellen ohne Fremdschlüssel (`public_code`, `accounting_cost_center_rule`, `department_grossanlass_pack(_line)`) erweitern? | Ja, in Phase 3 (Reset); für die einmalige Bereinigung gesondert aufgeführt |
