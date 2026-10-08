# Seed-Konzept

**Stand:** 8. Oktober 2026. Gemeinsame Bausteine und Regeln für alle Demo-Seeds. Szenario-spezifischer Inhalt: [scenarios/DEPARTMENT.md](./scenarios/DEPARTMENT.md), [scenarios/GROSSANLASS.md](./scenarios/GROSSANLASS.md). Übersicht: [README.md](./README.md). Marken **IST / TEILWEISE / SOLL / OFFEN** wie dort.

## 1. Zweck

Seeds erstellen zusammenhängende Szenarien mit realistischen Daten, Beziehungen, Mengen und Zuständen. Sie verwenden die bestehenden Domain-Strukturen (Entities, Services), keine zweite Schreibschicht.

## 2. Bestehende Seed-Bausteine (IST)

| Baustein | Ort |
| --- | --- |
| Rollen-User und Demo-Konten | `app:create-role-users`, `app:dev-demo:reset`, `backend/data/seeds/dev-demo/demo-accounts.json`, [Seed-README](../../backend/data/seeds/dev-demo/README.md) |
| Grossanlass-Szenario | `DemoGrossanlassSeedService` (`ensure*`-Methoden) |
| Grossanlass-Event-Jobs | `DemoGrossanlassEventJobsSeedService`, `DemoGrossanlassEventJobsCommand` |
| Demo-Lieferant | `DemoSupplierSeedService` |
| Wipe | `DemoGrossanlassWipeService`, `app:demo-grossanlass:wipe` |
| Initialdaten, Org-Subset | `CreateInitialDataCommand`, `ImportOrgSubsetCommand`, `backend/data/seeds/orgs/…` |
| Demo-Uhr am Ausgangspunkt | `DemoGrossanlassSeedService::ensureDemoClock`, [business-clock.md](../grossanlass/business-clock.md) |

Es gibt **keinen** gemeinsamen Szenario-Rahmen; die Muster sind in `DemoGrossanlassSeedService` implementiert.

## 3. Isolation und Idempotenz

**Anforderungen an jeden Seed (SOLL):**

- **Wiederholbar und möglichst idempotent.** Nochmaliges Ausführen legt nichts doppelt an. Vorbild (**IST**): `ensure*` suchen vor dem Anlegen.
- **Department-isoliert.** Ein Seed arbeitet ausschliesslich im eigenen Demo-Department. Er darf andere Departments, Organisationen und Szenarien weder verändern noch löschen. Zuordnung über `demo_mode` und Seed-Tags, nie über den Department-Namen.
- **Verstellte Demo-Zeit bleibt.** Erneutes Seeden überschreibt einen gesetzten Offset nicht (**IST** für Grossanlass; für andere Szenarien **SOLL**).
- **Reale Zeit in Audit-Spalten.** `created_at`/`updated_at` bleiben Systemzeit; fachliche Zeitstempel folgen dem Zeitstrahl.

**Bekannter Mangel (IST):** Wiederholte Läufe von `app:create-role-users` über bestehende Demo-User scheitern ohne separate Fixes (FK `activity_grossanlass_wish_response.updated_by_user_id`; `uniq_membership_one_primary_per_user`). Siehe [business-clock.md](../grossanlass/business-clock.md). Idempotenz ist heute nicht durchgängig gegeben. Stand der Prüfung am 8. Oktober 2026 (Code gelesen, Seeds nicht ausgeführt): `CreateRoleUsersCommand` setzt beim Löschen nur `created_by`-Referenzen um (`reassignCreatedByReferences`), nicht `updated_by_user_id`; `createMembership`/`updateMembership` setzen `is_primary`, ohne eine andere Primär-Mitgliedschaft desselben Users zu lösen. Beide Probleme sind damit nicht behoben.

## 4. Reset

Daten neu und Zeit zurück sind getrennte Vorgänge (**IST**, Tabelle in [business-clock.md](../grossanlass/business-clock.md)):

| Vorgang | Wirkung |
| --- | --- |
| Header «Zurücksetzen» | nur Demo-Zeit, keine Daten |
| Seed erneut (`--skip-delete`) | aktualisiert Bestehendes, Uhr bleibt |
| Wipe + Seed | echter Reset inklusive Ausgangsuhr (heute nur Grossanlass-Wipe) |
| `app:dev-demo:reset` | nur Rollen-User, berührt Department und Demo-Zeit nicht |

**SOLL:** Ein Reset-Weg pro Szenario (Wipe nur des eigenen Demo-Departments), nicht szenariospezifisch zusammengebastelt.

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

## 6. Mengenbilanz und Datenintegrität

**SOLL:** Seeds sind in sich konsistent:

- Mengen addieren sich über die Bewegungen der Charge ([materialfluss.md §14](../grossanlass/materialfluss.md)) und entsprechende Bestandsbewegungen im normalen Department.
- Invariante (Grossanlass): bestellt ≥ geliefert ≥ zugewiesen ≥ gepackt; Summe der Disposition = zurückgenommene Menge.
- Invariante (Department): Bestand = Summe der Lagerbewegungen; ausgegebene Menge = zurückgegebene + offene Menge.
- Beziehungen vollständig (keine verwaisten Referenzen), Rollen und Mitgliedschaften gültig, Eigentümer und Herkunft gesetzt.
- Jeder `public_code` löst auf einen Demo-Datensatz auf.
- Ein Test prüft diese Invarianten je Szenario.

## 7. Szenario-Registry (SOLL)

**Status: nicht gebaut.** Heute gibt es Einzel-Services pro Seed. Mögliche Zielarchitektur:

- Ein Szenario-Interface (Kennung, Seed-Tag, Anker, `ensure`, `wipe`, Ausgangspunkt-Resolver).
- Eine Registry, die Szenarien findet (analog zu `app.clock_origin_resolver`) und über ein gemeinsames Command ausführt.
- Gemeinsame Hilfen: Tagging, Department-Isolation, Zeitstrahl-Anker, Reset, Prüfung der Invarianten.
- Bereich A (Grossanlass) und B (Department) nutzen denselben Rahmen.

**OFFEN (Frage 3):** Seed-Mechanik: bestehende `ensure*`-Services beibehalten oder Doctrine-Fixtures/Szenario-Klassen mit Registry? Wunsch: ein gemeinsamer Rahmen für beide Bereiche. Diese Frage ist vor dem Ausbau von Bereich B zu entscheiden.
