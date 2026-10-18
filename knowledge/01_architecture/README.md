# 01 Architektur

Technische Grundlagen von eMatChef. Basis ist der Code im Arbeitsstand (inkl. uncommitteter Änderungen) beim Verfassen; `docs/` wurde nur als historische Referenz genutzt und gegen den Code geprüft. Fachliche Details der Kernmodule folgen später in `knowledge/02_core/`.

## Statuslabels

| Label | Bedeutung |
| --- | --- |
| `IMPLEMENTIERT` | Im Code geprüft, Pfad genannt |
| `ENTSCHIEDEN` | Verbindliche Regel (z. B. `AGENTS.md`), nicht technisch erzwungen |
| `GEPLANT` | Nur Absicht, nicht im Code |
| `UNKLAR` | Nicht verifiziert oder widersprüchlich |

Pfade sind relativ zum Repository-Root.

## Wegweiser

| Frage | Dokument |
| --- | --- |
| Was ist eMatChef, welche Bereiche gibt es? | [01_Systemuebersicht.md](./01_Systemuebersicht.md) |
| Stack, Verzeichnisse, Laufzeit, Hosts | [02_Systemarchitektur.md](./02_Systemarchitektur.md) |
| Zentrale Entitäten und Beziehungen | [03_Domaenenmodell.md](./03_Domaenenmodell.md) |
| Organisation / Department / Grossanlass, Datentrennung | [04_Mandantenstruktur.md](./04_Mandantenstruktur.md) |
| Zentrale Module, Erweiterungspunkte, Abhängigkeiten | [05_Modularchitektur/README.md](./05_Modularchitektur/README.md) |
| REST-API, `apiClient`, Fehler | [06_API-Architektur.md](./06_API-Architektur.md) |
| Authentifizierung, Rollen, Berechtigungen | [07_Sicherheit-und-Berechtigungen.md](./07_Sicherheit-und-Berechtigungen.md) |
| PostgreSQL, Doctrine, Migrationen | [08_Datenhaltung.md](./08_Datenhaltung.md) |

## Quellen ausserhalb dieses Ordners

- Entwicklungsregeln: `AGENTS.md`
- Historische Detaildoku (Status gegen Code prüfen): `docs/README.md`
- Auth-Detailregeln (MFA, Step-up, OAuth, MiData): `docs/authentication-security.md`

## Übergreifend offene Fragen

Gesammelt am Ende der jeweiligen Dokumente unter «Offen». Wichtigste: einheitliche Durchsetzung der Department-Trennung (siehe [04](./04_Mandantenstruktur.md)) und Kopplung zentraler Module an Grossanlass (siehe [05/03](./05_Modularchitektur/03_Abhaengigkeiten.md)).
