# Vorschau: Anwendung des Event-Seeds 2027 auf `mvdb` (NICHT angewendet)

**Stand:** 11. Oktober 2026. **Status: Vorschau, wartet auf ausdrückliche Freigabe.** An `mvdb` wurde nichts verändert. Diese Vorschau ersetzt die frühere Vorschau mit dem Anlasszeitraum 04.–06.06.2027 (fachlich überholt, darf nicht angewendet werden).

## 1. Wie die Vorschau entstanden ist

Ein schreibgeschützter `pg_dump` von `mvdb` wurde in eine Wegwerf-Datenbank (`val_prev`, eigener PostgreSQL-Container) wiederhergestellt. Darauf liefen: Migration, `app:demo:sync --all`, ein zweiter `app:demo:sync --all` und `app:demo:verify --all`. Alle Zahlen unten stammen aus dem Vergleich der Kopie vor und nach dem Lauf; sie entsprechen dem, was dieselben Befehle auf `mvdb` bewirken würden.

Ergebnis auf der Kopie: Sync ohne Konflikte und ohne Abweichungen (keine von Hand veränderten Seed-Werte gefunden), zweiter Sync mit 0 Änderungen, Verify für alle vier Szenarien ohne Verstösse.

**Wichtig:** `mvdb` steht noch auf dem Stand vor mehreren Commits dieses Branches (z. B. fehlen Karte, Einsätze, Packaufträge und Beschaffungsabläufe des Events vollständig). Die Vorschau zeigt deshalb die **gesamte** noch nicht angewendete Änderung, nicht nur die dieses Auftrags.

## 2. Voraussetzungen und Reihenfolge (SOLL, nach Freigabe)

1. **Backup** von `mvdb` (`pg_dump -Fc`, ausserhalb des Repos, Wiederherstellung in Wegwerf-DB prüfen), wie in [../LOKALE-MIGRATION.md §5](../LOKALE-MIGRATION.md).
2. **Migration** `Version20261012100000`: neue Tabelle `department_grossanlass_budget_area` (additiv, kein bestehender Datensatz betroffen). Achtung: Der Backend-Container migriert bei jedem Start automatisch. Ein Neustart vor der Freigabe würde diese Migration bereits auf `mvdb` anwenden; das Backend deshalb bis dahin nicht neu starten.
3. `app:demo:sync --all`, danach `app:demo:verify --all` und ein zweiter Sync (erwartet: 0 Änderungen).
4. Nichts wird gelöscht; kein Reset, kein Legacy-Eingriff.

## 3. Welche Stammdaten und Zeiträume sich ändern (Event `2d42e87c5197`)

| Bereich | Vorher (`mvdb`) | Nachher |
| --- | --- | --- |
| Anlasszeitraum (`planned_event_start/end`, Hauptaktivität) | 04.06.2027 00:00 – 06.06.2027 23:59:59 | **19.05.2027 13:00 – 23.06.2027 12:00** |
| Kalenderperiode `grossanlass` | «Demo Grossanlass Event», 04.06. 16:00 – 06.06. 16:00 | «Event-Durchführung», 04.06.2027 16:00 – 06.06.2027 16:00 (Zeiten unverändert, nur Name; jetzt eigenständige fixe Phase) |
| Kalenderperiode `aufbau` / `abbau` | 19.05. 13:00 – 04.06. 16:00 / 06.06. 17:00 – 23.06. 12:00 | unverändert |
| Ortsbezeichnung (`location_text`) | «Demo-Festplatz Seewiese» | «Event Platz» |
| Demo-Uhr | steht auf 04.04.2027 16:00 (Offset läuft weiter) | unverändert; eine manuell verstellte Uhr bleibt |
| Schnellwahl der Uhr | gesetzt | unverändert |

## 4. Übernommene Legacy-Standorte (Quelle `3dc94912d836`, bleibt unverändert)

| Standort | Vorher im Event | Nachher |
| --- | --- | --- |
| Eventplatz (Adresse `3a87cd146ad0`, Typ `event`) | «Demo-Festplatz Seewiese», Seewiesenweg 1, 6000 Luzern, 47.0502 / 8.3093 (erfundene Ersatzadresse) | «Event Platz», **ohne Strasse**, PLZ/Ort 6212 Kaltbach (amtliche PLZ-Ebene von geo.admin.ch zu den Legacy-Koordinaten), 47.1783698 / 8.0470522 |
| Zustellpunkt (Typ `event_delivery`, neu, am Eventplatz) | – | «Event Platz – Zustellung», Ettiswilerstrasse 3, 6242 Wauwil, 47.1831479 / 8.02793 (Namensschreibfehler «Paltz» des Legacy-Eintrags nicht übernommen) |
| Lager (Typ `storage`, neu, primär) | – | «Lagerplatz Gelände», 6212 Kaltbach, 47.1804331 / 8.049413 |
| Bauprojekt-Orte | 22 Orte (20 Bauprojekt-Orte und 2 Orte der Ressort-Struktur) | unverändert 22; die 23 Legacy-Orte bestehen aus 20 gleichnamigen Bauprojekten und 3 von Hand angelegten Varianten (`FOH Bau`, `Holzbau Eingang`, `Demo-Beriech-Hauptbühne`), die fachliche Duplikate sind und nicht übernommen werden. Ihre Kartenpositionen gelten neu für `FOH-Erweiterung`, `Ticket-Häuschen Eingang`, `Hauptbühnen-Podest` |
| Kartenplatzierung | keine Karte | 20 Orte auf der Geländekarte (3 Positionen aus den Legacy-Handpositionen, 3 Nachbarpins leicht verschoben) |
| Lieferant Häberli Holz | – | Koordinaten des Legacy-Eintrags 47.1003743 / 8.1413014, Ort «Ruswil» (nicht «Wauwil») |

Eventplatz, Zustellpunkt und Lager haben drei verschiedene Koordinaten. Der Zustellpunkt liegt ausserhalb des Kartenausschnitts, Eventplatz und Lager liegen darin.

## 5. Neue Datensätze und geänderte Zeilen (Zeilenzahlen der Datenbank, vorher → nachher)

| Tabelle | Vorher | Nachher |
| --- | --- | --- |
| `demo_seed_record` (gesamt) | 274 | 346 (+72, davon Event: 169 → 241) |
| `address` | 60 | 62 (+ Zustellpunkt, + Lager) |
| `department_calendar_period` | unverändert (Anzahl) | Namen und Zeiten wie §3 |
| `department_grossanlass_map` | 1 (nur Legacy) | 2 (+ Event-Karte) |
| `department_grossanlass_budget_area` | – (neue Tabelle) | 2 (Material 2'000 CHF, Logistik 900 CHF) |
| `department_grossanlass_budget` / `activity_grossanlass_procurement_finance` | 1 / 1 | 2 / 2 (Anlass-Rahmen 2'900 CHF) |
| `activity_grossanlass_procurement_line` | 11 | 12 (Event: 4 → 5) |
| `activity_grossanlass_procurement_quote` / `_order` | 3 / 2 | 6 / 3 |
| `department_grossanlass_inquiry` | 5 | 9 (Event: 0 → 4) |
| `department_grossanlass_commitment` | 7 | 12 (Event: 0 → 5, davon 3 Chargen) |
| `department_grossanlass_cost` | 27 | 31 (Event: 4 → 8) |
| `department_grossanlass_einsatz` / `_pack` / `_pack_line` | 13 / 14 / 14 | 17 / 18 / 18 (Event: je 4) |
| `department_grossanlass_task` / `_vehicle_need` | 8 / 9 | 10 / 10 |
| `department_grossanlass_mail_template` | 9 | 11 (Standard-Mailvorlagen des Events) |
| `department_vehicle` | 3 | 6 (+ 3 Fahrzeuge) |
| `storage_rack` / `storage_slot` | 6 / 33 | 8 / 38 (+ 2 Regale, 5 Fächer) |
| `public_code` | 504 | 512 (+ 8 interne Lager-QR: Lagerstandort, 2 Regale, 5 Fächer) |
| `department_setting` | 79 | 80 |

**Geänderte bestehende Seed-Werte (nur nicht von Hand veränderte, per Dreiwege-Vergleich):** 94 Einträge des Events erhalten neue Katalogwerte: 26 Gruppen/Bauprojekte und 57 Materialwünsche (feste Zeitfenster 19.05.–16.06.2027), 5 Aufgaben, 5 Fahrzeugbedarfe (feste Tage) und die Eventplatz-Adresse. **Entfernt wird nichts** (0 entfernt, 0 verwaist).

## 6. Dateien (Dateispeicher `backend/var/uploads/2d42e87c5197/`)

| Datei | Zweck |
| --- | --- |
| `photos/grossanlass-map/<Karten-ID>/<…>.webp` (rund 87 KB) | Geländekarte aus `backend/data/seeds/dev-demo/files/event-gelaende.webp` (SHA-256 im Katalog geprüft; Quelle © swisstopo) |
| `documents/grossanlass-procurement-quote/<Offerten-ID>/<…>.pdf` ×2 (je rund 21 KB) | Demo-Offerten-PDFs der Ketten «Häberli Holz» und «Maki Landtechnik», verbunden mit den Offerten |

Legacy-Event und seine Dateien (`var/uploads/3dc94912d836/`) werden nicht angefasst.

**Hinweis zum Probelauf:** Weil die Kopie dieselben Department-IDs trägt wie `mvdb` und der Dateispeicher gemeinsam ist, hat der Probelauf diese drei Dateien vorübergehend unter `var/uploads/2d42e87c5197/` angelegt. Sie sind inzwischen wieder entfernt (das Verzeichnis existiert nicht mehr; geprüft nach dem Aufräumen der Testläufe). `mvdb` selbst ist unverändert (letzte Migration `Version20261011150000`, Anlasszeitraum weiterhin 04.–06.06.2027).

## 7. Was erhalten bleibt und geschützt ist

- Benutzer, Rollen, Mitgliedschaften, MFA und externe Identitäten: Zeilenzahlen von `user` (38), `membership` (75) und `department` (23) unverändert.
- Legacy-Event `3dc94912d836`: 34 Gruppen, 23 Orte, 4 Adressen unverändert; Camp, Setup und Materialverwaltung unverändert (Prüfsumme über alle Ledger-Einträge dieser drei Szenarien vor und nach dem Lauf identisch); alle übrigen Departments unverändert.
- Manuelle Änderungen: jeder Seed-Datensatz wird per Dreiwege-Vergleich behandelt (Seed-Wert, zuletzt geschriebener Wert, aktueller Wert). Von Hand Geändertes bleibt und erscheint als Abweichung; auf der Kopie traten keine auf.
- Bestehende QR-Codes der 20 vorhandenen Orte bleiben (sie sind bereits vergeben); die neuen Packaufträge und Lager-QR erhalten reproduzierbare Codes aus dem Seed-Schlüssel (`DemoStableCodes`). Keine Legacy-Codes werden kopiert.
- Zeitsprünge der Demo-Uhr buchen nichts.

## 8. Auswirkungen eines späteren Resets

Es gibt einen sicheren Reset ohne Löschung (`app:demo:reset --scenario=grossanlass-event --confirm=grossanlass-event`, siehe [GROSSANLASS-EVENT-ENTSCHEIDUNGEN.md §3](./GROSSANLASS-EVENT-ENTSCHEIDUNGEN.md)): Er setzt verwaltete Seed-Werte zurück (also auch von Hand geänderte Katalogwerte), legt gelöschte Seed-Datensätze mit demselben QR-Code neu an und setzt die Uhr auf 04.04.2027 16:00. Er löscht keine Wareneingänge, Packaufträge, Anfragen oder von Hand angelegten Chargen. Ein Reset mit Löschung ist noch nicht freigegeben.

## 9. Offene Punkte vor der Freigabe

1. (erledigt) Die drei Dateien des Probelaufs wurden entfernt (§6).
2. Soll die Ortsangabe «Kaltbach» (aus den Koordinaten abgeleitet) bleiben oder soll der Eventplatz ganz ohne PLZ/Ort geführt werden, wie im Legacy-Event?
3. Das Logistik-Limit (900 CHF) liegt absichtlich unter dem Teleskoplader (950 CHF), damit die Budgetüberschreitung prüfbar ist.
