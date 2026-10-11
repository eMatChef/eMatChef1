# Grossanlass: offene Punkte und bekannte Abweichungen

Stand: 11.10.2026. Nur dokumentiert, nicht behoben.

## Nicht getestet

- **Zeitfenster-Ring bei einem Bauauftrag.** Der Ring im Gantt (`Einsätze planen`) soll den zentralen Auftragsdialog direkt auf «Grunddaten» öffnen (`openWindowFromBelegung`). In der Demo-Umgebung gibt es Ringe nur an Bereichen (Unterressorts); diese öffnen den Bereichsdialog. Für einen Browser-Test fehlt ein Bauauftrag mit sichtbarem Zeitfenster-Ring. Geprüft sind nur der Klick auf den Auftragsnamen («Aufgaben & Material») und der Erhalt von Ansicht, Scrollposition und Filtern bei Soft-Reload in Monat, Woche und Tag.

## Bestehende Schema-Abweichungen (nicht durch die Migrationen `Version20261012110000` bis `Version20261016090000`)

- `doctrine:schema:validate` bricht ab: «An index with name `uniq_cf87ef08a6005ca0` was already defined on table `activity_grossanlass_round_form`» (Mapping-Problem der Entity, unabhängig von diesen Änderungen).
- Frische Datenbank (alle Migrationen) gegenüber `mvdb` (Dev):
  - `mvdb` kennt drei ausgeführte Migrationen ohne lokale Datei («Executed Unavailable 3») und die Tabelle `workshop_ticket_history`, die in dieser Codeversion nicht angelegt wird.
  - Die frische Datenbank hat `activity_pack_group_intent`, die in `mvdb` fehlt.
  - Index- und Constraint-Namen weichen bei `department`, `user`, `profile`, `refresh_tokens` und `department_grossanlass_config` ab.
  - Die Tabellen und Spalten der sechs Migrationen sind in beiden Datenbanken identisch.
- `Version20261016090000` verwendet `ADD CONSTRAINT` ohne `IF NOT EXISTS` und ist deshalb nicht beliebig wiederholbar.
- Die Rücknahme von `Version20261013090000` stellt `department_grossanlass_place.group_id` nur für eindeutig zugeordnete Standorte wieder her.
