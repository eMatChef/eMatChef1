# 05 Modularchitektur

Wegweiser zu Architekturprinzipien für zentrale, wiederverwendbare Module. Fachliche Details je Kernmodul folgen in `knowledge/02_core/`.

| Dokument | Inhalt |
| --- | --- |
| [01_Modulprinzipien.md](./01_Modulprinzipien.md) | Was «Modul» hier heisst, Schichtung, Regeln gegen Doppelimplementierung |
| [02_Moduluebersicht.md](./02_Moduluebersicht.md) | Tatsächlich vorhandene zentrale Module mit Pfaden, Nutzern, Erweiterungspunkten |
| [03_Abhaengigkeiten.md](./03_Abhaengigkeiten.md) | Abhängigkeitsrichtungen, beobachtete Kopplungen |

`IMPLEMENTIERT` Es gibt kein formales Modulsystem (keine Symfony-Bundles, kein Plugin-Mechanismus, keine Domain-Namespaces ausser Service-Unterordnern). «Modul» bezeichnet hier einen abgegrenzten Service-Ordner bzw. eine Frontend-Baugruppe, die von mehreren Bereichen genutzt wird.
