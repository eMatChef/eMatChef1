# Grossanlass Event 2027: offene Entscheidungen (Prozessverlauf, Rücknahme, Reset)

**Stand:** 11. Oktober 2026. Ergänzt [GROSSANLASS.md](./GROSSANLASS.md) und [../SEED-KONZEPT.md §5.3 / §7](../SEED-KONZEPT.md). Marken **IST** / **SOLL** / **OFFEN**. Dieses Dokument legt nur Entscheidungen zur Freigabe vor; umgesetzt ist, was unter «IST» steht.

## 1. Prozessverlauf über die BusinessClock

### 1.1 Befund: welche Module kennen historische Zustände

Geprüft gegen Entities und Services (Stand dieses Branches). «Rekonstruierbar» heisst: der Zustand zu einem früheren Zeitpunkt lässt sich aus gespeicherten Daten korrekt ableiten, ohne etwas auszublenden oder zu überschreiben.

| Modul | Gespeicherte Zeitangaben | Status-/Standorthistorie | Zustand zu früherem Zeitpunkt rekonstruierbar? |
| --- | --- | --- | --- |
| Wareneingang (`department_grossanlass_charge_movement`) | `occurred_at` je Bewegung | Ledger: jede Bewegung bleibt | **Ja** (Eingangsmenge zu jedem Zeitpunkt) |
| Bestellung (`procurement_order`) | `ordered_at`, `delivery_at` | nein | **Teilweise** (Bestellzeitpunkt ja, spätere Änderungen überschreiben) |
| Anfrage (`inquiry`) | `asked_at`, Verlauf `thread` mit Zeitstempeln | Status nur aktuell | **Teilweise** (Verlauf ja, Status nein) |
| Offerte (`procurement_quote`) | `created_at` | `selected` ohne Zeitpunkt | **Nein** (Wahl der Offerte nicht datiert) |
| Kostenposition (`cost`) | `created_at`, `updated_at` | Status nur aktuell | **Nein** |
| Einsatz / Packauftrag / Fahrt | `starts_at`, `ends_at`, `trip_released_at` | Pack-Status und `current_place_id` nur aktuell | **Nein** (Standort und Status des Packs ohne Verlauf) |
| Aufgaben, Materialwünsche, Fahrzeugbedarfe | geplante Termine | Status nur aktuell | **Nein** |
| Rücknahme / Disposition | `occurred_at` (Bewegungsarten vorhanden) | nicht buchbar: `BOOKABLE_KINDS = [received]` | **Nicht vorhanden** |

Es gibt für den Grossanlass keine Verlaufstabelle (nur `activity_history`, `material_history`, `workshop_ticket_history` und das allgemeine `audit_event`). Eine zeitabhängige Darstellung früherer Zustände wäre deshalb für die meisten Module nur durch Ausblenden oder Überschreiben möglich. Das ist ausdrücklich ausgeschlossen.

### 1.2 IST (umgesetzt)

- Der Seed ist ein **konsistenter Ausgangszustand zur Ausgangszeit 04.04.2027 16:00** (Beschaffungsphase): Bedarf, Anfragen versendet, Offerten (mit PDF), Zusage (Teleskoplader), Budgetentscheid, erste Bestellung (aufgegeben am 01.04.2027), Lieferungen ausstehend, Budget-Limits, Lager, Fahrzeuge, Fahrerkarten, Chargen.
- Die BusinessClock ändert nur die Anzeige und Zeitberechnung. Ein Zeitsprung bucht nichts: weder Bestellung noch Bestand noch Packstatus (`DemoSharedOrganisationEventIntegrationTest::testProcurementFlowToTheSiteRunsThroughTheServicesAndClockJumpsBookNothing` springt durch alle sechs Schnellwahl-Zeitpunkte und vergleicht den Datenstand).
- Spätere Schritte sind **ausdrücklich ausgelöste Aktionen** über die bestehenden Dienste (Wareneingang buchen, Packen, Fahrt freigeben, Pack scannen). Derselbe Test führt den Ablauf Bedarf → Anfrage → Offerte → Zusage → Bestellung → Wareneingang → Budget «effektiv» vor und lehnt Überlieferung und nicht buchbare Rückbuchungen ab.
- Keine Frontend-Simulation: es gibt keine Statusänderung ohne Backend-Aktion.

### 1.3 OFFEN: Entscheidung zur Freigabe

Der Auftrag verlangt, dass sich Januar bis August 2027 «nachvollziehbar über die BusinessClock» betrachten lassen. Mit den heutigen Datenmodellen ist das ohne künstliches Ausblenden nicht möglich. Optionen:

| Option | Inhalt | Aufwand | Bewertung |
| --- | --- | --- | --- |
| **A. Phasen-Zustände (Empfehlung)** | Der Seed kennt benannte Zustände (Planung, Beschaffung, Aufbau, Durchführung, Abbau, Abschluss). Jeder Zustand ist ein **vollständiger, konsistenter Datenstand**, der durch dieselben Dienste wie im Betrieb aufgebaut wird (Bestellungen, Wareneingänge, Packaufträge, Fahrten mit echten Zeitstempeln), und setzt die Uhr passend. Die Auswahl erfolgt ausdrücklich im Event-Reset («Zurücksetzen auf Phase …»), nicht durch einen Zeitsprung. | mittel: Seed-Schritte pro Phase, Reset-Funktion (§3) als Voraussetzung | Keine Rekonstruktion, kein Ausblenden; ein Zeitsprung bleibt wirkungslos auf Daten. Nachteil: Phasenwechsel ist ein Reset, kein Vor-/Zurückspulen. |
| B. Verlaufsprotokoll mit Rekonstruktion | Neue Ereignistabelle für alle Prozessmodule (Event Sourcing light), Ansichten lesen «Zustand zum Zeitpunkt T». | gross, Eingriff in alle Services und Listen | Fachlich sauberste Zeitreise, aber Architekturumbau über das Demo-Ziel hinaus. Nur mit gesonderter Freigabe. |
| C. Leselogik mit Filter `occurred_at ≤ Uhr` | Nur für Module mit Bewegungsledger (Wareneingang) rekonstruierbar, alle anderen nicht. | klein | Würde zu uneinheitlichen Ansichten führen und bei den übrigen Modulen doch ausblenden; **abzulehnen**. |
| D. Nur Ausgangszustand (heutiger IST) | Spätere Phasen führt der Benutzer von Hand aus (Drehbuch in der Doku). | keiner | Erfüllt «keine Simulation», zeigt aber Juni bis August nicht ohne manuelle Arbeit. |

Empfehlung: **A** für die Demo; **B** nur, wenn echte Zeitreise im Produkt gewünscht ist. Für A fehlen zwei Voraussetzungen: ein belastbarer Reset (§3) und die Rücknahme-Buchungen (§2).

## 2. Rückbau, Rücktransport, Kontrolle (Lücke im Modell)

**IST:** `DepartmentGrossanlassChargeMovement` kennt die Bewegungsarten `returned_to_store`, `to_workshop`, `returned_to_owner`, `sold_handover`, `disposed`, `consumed`, `lost`, bucht aber nur `received` (`BOOKABLE_KINDS`). Pack-Scans bilden nur Hin-Transport und Ankunft am Ort ab (`scanStart`, `scanArrive`). Es gibt keinen Rücktransport-Status, keine Rücknahmekontrolle und keine Schäden-/Fehlmengenbuchung im Grossanlass ([materialfluss.md §10](../../grossanlass/materialfluss.md): «Teilmengen fehlen»).

**Folge für den Demo-Verlauf:** Die Kette endet im Seed und in den Tests bei «Material erreicht den Einsatzort». Rücktransport → Kontrolle → Lager sind nicht seedbar, ohne die Datenmodelle zu erweitern.

**OFFEN (Freigabe nötig):** Rücknahme buchbar machen (Bewegungsarten freischalten, Mengenbilanz «Summe der Disposition = zurückgenommene Menge» als Invariante, Rücktransport als Pack-Zustand, Schäden/Fehlmengen über `lost` und `to_workshop`). Das ist ein Backend-Ausbau (Service, Migration nicht zwingend, UI), kein Seed-Thema.

## 3. Event-Reset

### 3.1 IST

- Generischer Reset-Rahmen (`DemoScenarioRunner::reset/previewReset`, `DemoResetService`): Umgebungs-Guard, Szenario-Bestätigung, Plan-Hash der Vorschau, eine Transaktion unter Advisory-Lock, Prüfung, dass Department-Zeile und gemeinsame Demo-Benutzer unverändert bleiben. Freigegeben ist nur `grossanlass-setup` (`DemoResetService::SUPPORTED`).
- `DemoSetupResetter` zeigt das sichere Muster: Inventar aller Tabellen mit `department_id`, jede Tabelle ist BEHALTEN, ZURÜCKSETZEN oder (Standard für Unbekanntes) **blockierend**.
- Seed-Verwaltung: `ManagedSeedApplier` kann verwaltete Werte erzwingen (`SeedContext::withForcedManaged()` bei `resettable`), und gelöschte Seed-Datensätze werden mit demselben Seed-Schlüssel wieder angelegt. Neue QR-Codes (Orte, Packaufträge, Lagerstandort, Regale, Fächer) sind **aus Szenario und Seed-Schlüssel abgeleitet** (`DemoStableCodes`) und bleiben deshalb nach Löschen und Wiederanlegen gleich.
- Die Uhr lässt sich im Header auf den Ausgangspunkt zurücksetzen; der Seed-Sync verstellt eine manuell gesetzte Uhr nie.

### 3.2 Warum kein pauschaler Event-Reset umgesetzt ist

Der Seed-Ledger kennt nur die **direkt vom Seed angelegten** Datensätze. Folgedaten, die die Dienste beim Seeden erzeugen (Kostenzeilen, Zusagen aus Bestellungen, Mail-Vorlagen, Posteingangsnachrichten, Packzeilen), und alles, was Testpersonen danach von Hand anlegen, tragen **keinen Eigentumsnachweis**. Ob eine Zeile sicher entfernt werden darf, ist damit nicht eindeutig feststellbar. Zusätzlich fehlt dem bestehenden Wipe-Dienst die Abdeckung mehrerer Tabellen ohne Fremdschlüssel auf `department` (`accounting_cost_center_rule`, `public_code`, `department_grossanlass_pack` samt Zeilen) und der Dateien unter `var/uploads/<Department>/` (Karte, Offerten-PDFs). Eine pauschale Löschung würde Waisen und verwaiste Dateien hinterlassen. Deshalb gibt es **keinen** Event-Reset, der Daten löscht.

### 3.3 Vorschlag (OFFEN, Freigabe nötig)

1. **Eigentumsnachweis erweitern:** Beim Seeden jede neu entstandene Zeile in `department_grossanlass_*`, `activity_grossanlass_*`, `storage_*`, `address`, `public_code` über ein Vorher/Nachher der Primärschlüssel je Seed-Schritt als **abgeleiteten Ledger-Eintrag** festhalten (Typ `derived`). Danach ist jede Zeile des Departments entweder Seed (`seed`/`derived`) oder von Hand (`manual`, nicht im Ledger).
2. **Inventar nach dem Muster `DemoSetupResetter`:** Jede Tabelle mit `department_id` oder indirektem Bezug (inklusive der Tabellen ohne Fremdschlüssel und der Dateien) ist ausdrücklich BEHALTEN / ZURÜCKSETZEN / LÖSCHEN; Unbekanntes blockiert.
3. **Stufen:** (a) *Seed-Werte wiederherstellen* (verwaltete Werte erzwingen, gelöschte Seed-Zeilen mit gleichem QR neu anlegen, Uhr auf 04.04.2027 16:00); (b) *Von Hand erzeugte Prozessdaten entfernen* nur nach zweiter ausdrücklicher Bestätigung mit der Liste der betroffenen Tabellen und Zeilenzahlen; (c) *Phasenzustand* (§1.3 Option A).
4. **Schutz:** Benutzer, Rollen, Mitgliedschaften, MFA, externe Identitäten, andere Departments, das Legacy-Event und `mvdb`-fremde Daten bleiben unangetastet; Reset nur im Event-Department, nur mit Plan-Hash der Vorschau und Szenario-Bestätigung, alles in einer Transaktion, Dateien erst nach Commit.
5. **Tests:** Reset auf einer isolierten Datenbank mit zwei Departments (Event und fremdes Department), Prüfung auf Waisen (Fremdschlüssel und Dateien), Idempotenz, unveränderte QR-Codes.

Bis zur Freigabe bleiben `DemoResetService::SUPPORTED` und `GrossanlassEventScenario::supportsReset()` unverändert (kein Reset für das Event).
