# Lokale Demo-Migration (WSL + Docker, Datenbank `mvdb`)

**Stand:** 8. Oktober 2026. Einmaliger Plan, um die lokale Entwicklungsdatenbank auf die neuen Demo-Seeds umzustellen und die alten Demo-Departments zu entfernen. Marken **IST / SOLL / OFFEN** wie in [README.md](./README.md). Dieses Dokument ist ein **Plan**: Auf `mvdb` wurden bisher **keine** Migrationen, Seeds oder Löschungen ausgeführt; die Inventur war ausschliesslich lesend, alle Proben liefen auf einer Kopie.

Festgelegte Entscheidungen: WSL + Docker ist die Ausführungsumgebung · `mvdb` bleibt bestehen (kein globaler Reset, keine Volume-/DB-Löschung) · Demo-Organisationen und gemeinsame Demo-Benutzer entstehen vollständig durch `app:demo:sync --all` · alte Demo-Departments werden nicht übernommen, sondern erst **nach** erfolgreichem Sync lokal entfernt.

## 1. Umgebung (IST, geprüft)

| Punkt | Befund |
| --- | --- |
| Compose-Projekt | `ematchef`; Container `ematchef-db-1` (postgres:16-alpine, healthy), `-backend-1`, `-frontend-1`, `-nginx-1`, `-adminer-1` |
| Datenbank | `mvdb`, Benutzer `mvuser`, 22 MB, 34 Benutzer/Profile; Volume `ematchef_postgres_data`; **kein Host-Port** (Zugriff nur im Netz oder per `docker exec`) |
| Backend | `APP_ENV=dev`, `DATABASE_URL=…@db:5432/mvdb`; Quellcode per Bind-Mount `./backend` (es läuft immer der **ausgecheckte Branch**, derzeit `feat/demo-seed-architecture`); `EMATCHEF_ENV_NAME` ist nicht gesetzt, was auf einem dev-Kernel als `local` gilt: Demo-Befehle und löschende Befehle sind erlaubt |
| Migrationsstand | 255 Einträge, letzter `Version20261007100000`. **Offen:** `Version20261008100000` (Szenario-Schlüssel, Ledger), `Version20261008110000` (Ledger-Hash). Beide additiv |
| Achtung Entrypoint | `backend/docker-entrypoint.sh` führt bei **jedem Start des Backend-Containers** `doctrine:migrations:migrate` aus. Ein `docker compose up/restart backend` würde die beiden Migrationen also ohne weiteres Zutun auf `mvdb` anwenden. Vorher Backup (§5, Schritt 1) |
| Abweichung | 3 Migrationen sind in `mvdb` eingetragen, aber in diesem Branch nicht vorhanden (`Version20260805120000`, `Version20260813190000`, `Version20260813210000`, von anderen Branches). Harmlos («Executed Unavailable»), nicht ändern |
| Sonst | Keine Demo-Seeds laufen automatisch (kein Deployment-Schritt, kein Cron) |

## 2. Einsatzbereitschaft (IST)

- Tests: PHPUnit 847 Tests grün (6 übersprungen = Integrationstest ohne Test-DB), PHPStan ohne Fehler.
- **Probelauf auf einer Kopie** (`pg_dump` von `mvdb` → Wegwerf-PostgreSQL auf Port 55433, tmpfs): alle Schritte aus §5 liefen durch, Zahlen in §4. Die Kopie ist inzwischen verworfen; die Probe lässt sich jederzeit wiederholen.
- Beim Probelauf gefunden und behoben: (a) übernommene Datensätze wurden wegen eines noch nicht geflushten Ledger-Eintrags fälschlich als «Abweichung» gemeldet und nicht aktualisiert; (b) `app:demo:sync` zeigte Abweichungen, Konflikte und Hinweise nicht an. Beides ist korrigiert und getestet.
- **Noch nicht gebaut:** das Werkzeug für die Legacy-Bereinigung (§5, Schritt 6). Die Probe lief mit einem Wegwerf-Skript. **OFFEN (D2).**

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
| 6 | **Legacy-Bereinigung** (erst nach 5) | Allowlist §3: Reihenfolge `3dc94912d836`, `72605b231274`, `638c8d301090`, `7ae5770a1180`, `7aa39b221bab`, danach Organisation `5f35b7cde9b5`. Je Department eigene Transaktion. Vorbedingungen prüfen (§3 Kriterien, ID **und** Name), `demo_mode` setzen, GA-Departments über `DemoGrossanlassWipeService`, übrige über `DepartmentResetService::resetDepartment`, dazu `public_code`, `accounting_cost_center_rule`, `department_grossanlass_pack(_line)`, `department_calendar_period`, `department_setting`, `inbox_message`, `audit_event`, `membership`, `last_used_department_id` → NULL, zuletzt Zeile `department`; Organisation nur, wenn leer | Zeilenzahlen = §4; Fingerabdruck fremder Daten unverändert |
| 7 | Nachher | `app:demo:sync --all` (setzt Primär-Mitgliedschaften der `ga-*` im neuen Event-Department), `app:demo:verify --all`, Waisen-Scan (nur die zwei bekannten Altlasten) | Demo-Benutzer 23, Ledger 94 |
| 8 | Aufräumen | Nur nach Freigabe (D1): Mitgliedschaften der neun Demo-Konten in `Cevi ZH11` entfernen | – |

**Rückweg:** Bis Schritt 6 genügt es, die neuen Departments nicht zu nutzen (additiv). Nach Schritt 6: Dump aus Schritt 1 in eine **neue** Datenbank restoren und die Backend-`DATABASE_URL` umstellen; `mvdb` selbst wird nicht gelöscht oder überschrieben, solange nicht ausdrücklich entschieden.

**Warum diese Reihenfolge:** Backup vor jeder Änderung; Migration vor dem Sync (Spalten/Tabellen); Sync **vor** der Bereinigung (Vorgabe: erst entfernen, wenn die neuen Seeds erfolgreich sind) und weil die Demo-Konten dadurch bereits neue Mitgliedschaften haben; Bereinigung zuletzt; ein zweiter Sync danach korrigiert Primär-Markierungen.

## 6. Sind die gemeinsamen Demo-Benutzer gefährdet? (geprüft)

**Nein.** Die Bereinigung löscht nur Zeilen mit `department_id` der Allowlist; `user`, `profile`, `user_totp` und der Ledger-Bereich `demo-users` werden nicht berührt. Im Probelauf blieben alle 23 Demo-Konten (inklusive Passwort-Hashes) und alle 94 Ledger-Einträge identisch. Es verschwinden nur die **alten Mitgliedschaften** in `Demo Grossanlass` (9); die neuen Mitgliedschaften in Event/Camp/Materialverwaltung bleiben. Kein Benutzer hat ausserhalb der Allowlist eine Mitgliedschaft, die gelöscht würde (`Cevi ZH11` wird nicht angefasst). `User.last_used_department_id` zeigt aktuell auf keine Legacy-Department (0 Treffer); der Plan setzt es dennoch auf NULL. Der Runner schützt gemeinsame Benutzer zusätzlich bei jedem künftigen Szenario-Reset ([SEED-KONZEPT.md §7.5, §7.11](./SEED-KONZEPT.md#711-phase-2-demo-organisationen-ist)).

## 7. Offene Entscheidungen

| # | Frage | Empfehlung |
| --- | --- | --- |
| D1 | Mitgliedschaften der neun Demo-Konten in `Cevi ZH11` nach dem Sync entfernen? Sonst bleibt `superadmin` dort primär und die Sync-Hinweise bleiben | Entfernen (Department ist danach leer, wird aber nicht gelöscht), als separater Schritt 8 |
| D2 | Bereinigungswerkzeug: eigener Command `app:demo:legacy-cleanup` (Allowlist ID+Name, Dry-Run als Standard, `--execute --confirm`, nur `local`/freigegebenes `develop`) oder einmaliges Skript? | Command mit Dry-Run, damit der Plan reproduzierbar und testbar ist; danach wieder entfernen |
| D3 | Der von Hand aufgebaute Inhalt von `Demo-Grossanlass-Event` (34 Gruppen, 23 Orte, 15 Kosten …) geht verloren, ausser er steckt im Backup. Soll vorher etwas gesichert oder in den Seed-Katalog übernommen werden? | Backup genügt, wenn der Inhalt nicht gebraucht wird; sonst vorher gezielt exportieren |
| D4 | Bestehende Waisen (5× `accounting_cost_center_rule`, 2× `audit_event` `GLOBAL000000`) mitbereinigen? | Nein, ausserhalb dieses Auftrags |
| D5 | Wipe-Dienst um die drei Tabellen ohne Fremdschlüssel (`public_code`, `accounting_cost_center_rule`, `department_grossanlass_pack(_line)`) erweitern? | Ja, in Phase 3 (Reset); für die einmalige Bereinigung gesondert aufgeführt |
