# eMatChef — Projektdokumentation

eMatChef verwaltet Materialbestand, Ausleihe (Aktivitäten) und Department-Rollen. Technisch: Vue-Frontend, Symfony-API, PostgreSQL. Diese Dokumentation beschreibt, wie eMatChef intern gebaut ist. Die öffentliche Benutzer- und Entwickleranleitung (Bedienung, Testumgebung, Demo-Konten) liegt im Repository [eMatChef/documentation](https://github.com/eMatChef/documentation) und wird als [docs.ematchef.ch](https://docs.ematchef.ch) veröffentlicht. Technische Details werden dort nicht dupliziert.

Neuer Chat: diese Datei lesen, dann höchstens die eine fachliche Datei zur Aufgabe. `docs/` nicht vollständig einlesen.

## Einstieg

| Frage | Datei |
| --- | --- |
| Systemaufbau | [ARCHITECTURE.md](./ARCHITECTURE.md) |
| Fachliche Beziehungen | [DOMAIN-MODEL.md](./DOMAIN-MODEL.md) |
| Entwickeln, Tests, Commands | [DEVELOPMENT.md](./DEVELOPMENT.md) |
| Git, Branches, CI/CD | [../CONTRIBUTING.md](../CONTRIBUTING.md) |
| Testumgebung, Demo-Konten, Test-TOTP, Benutzeranleitung | [docs.ematchef.ch](https://docs.ematchef.ch/de/entwicklung/testumgebung); Quelle der Demo-Konten: `backend/data/seeds/dev-demo/demo-accounts.json` |

## Dokumentationsquellen

| Frage | Ort |
| --- | --- |
| Wie ist eMatChef intern gebaut? (Architektur, Domain-Modell, Security, Entwicklungsregeln) | `docs/` in diesem Repository |
| Wie benutze, entwickle oder teste ich eMatChef? | [eMatChef/documentation](https://github.com/eMatChef/documentation) → [docs.ematchef.ch](https://docs.ematchef.ch) |
| Wo finde ich was? | GitHub-Wiki: nur Wegweiser, keine eigenen Inhalte |

Ändert sich dauerhaftes technisches Wissen, wird `docs/` aktualisiert. Ändert sich sichtbares Benutzerverhalten oder ein Workflow, wird die öffentliche Dokumentation im Repository `eMatChef/documentation` aktualisiert. Betrifft es beides, werden beide aktualisiert.

## Fachdoku (gezielt)

| Thema | Einstieg |
| --- | --- |
| Accounting | [accounting.md](./accounting.md) |
| Aktivitäten | [activities/README.md](./activities/README.md) |
| Grossanlass | [grossanlass/README.md](./grossanlass/README.md) |
| Material / Kombos / Vorlagen | [material/combos/README.md](./material/combos/README.md), [material/templates/README.md](./material/templates/README.md) |
| Werkstatt | [workshop/README.md](./workshop/README.md) |
| Nachrichten | [nachrichtenzentrale.md](./nachrichtenzentrale.md) |
| QR | [qr/README.md](./qr/README.md) |
| Lager-Geräte | [devices/README.md](./devices/README.md) |
| Lieferanten | [supplier/supplier-portal.md](./supplier/supplier-portal.md) |
| Übersetzungen | [TRANSLATION.md](./TRANSLATION.md) |
| UI | [ui/vuetify-standards.md](./ui/vuetify-standards.md), [wiederverwendbare-komponenten.md](./wiederverwendbare-komponenten.md) |
| E2E | [E2E.md](./E2E.md) |
| Deploy | [APP-ON-DROPLET.md](./APP-ON-DROPLET.md), [../deploy/SERVER-UPDATE.md](../deploy/SERVER-UPDATE.md) |
| Ideen, nicht Ist-Zustand | [future/README.md](./future/README.md) |

`docs/work/` und Dateien mit `plan` im Namen sind Arbeitsstände oder Zielbilder. Status immer gegen Code prüfen, nicht gegen den Dokumentkopf allein.
