# Szenario: Grossanlass

**Stand:** 8. Oktober 2026. Demo-Szenario für Grossanlässe (PFF, Kantonslager) mit End-to-End-Materialfluss und aktuellem Implementierungsstand. Gemeinsame Regeln: [../README.md](../README.md), [../SEED-KONZEPT.md](../SEED-KONZEPT.md), [../BUSINESS-CLOCK.md](../BUSINESS-CLOCK.md). Marken **IST / TEILWEISE / SOLL / OFFEN** wie dort.

**Fachliche Quellen (nicht kopiert):** [../../grossanlass/README.md](../../grossanlass/README.md) (Ist-Stand), [materialfluss](../../grossanlass/materialfluss.md), [kosten](../../grossanlass/kosten.md), [rollen-postfach-fahrten](../../grossanlass/rollen-postfach-fahrten.md), [bauprojekt-ort-helfer](../../grossanlass/bauprojekt-ort-helfer.md), [business-clock](../../grossanlass/business-clock.md).

## 1. Zielbild

Das bestehende Grossanlass-UI ist das fachliche und visuelle Zielbild. Es wird bei der Backend-Anbindung **nicht** auf den bisherigen Backend-Funktionsumfang reduziert; fehlt dem Backend etwas, wird es über die bestehenden Entities und Services erweitert. Designverbesserungen während der Umsetzung sind ausdrücklich erwünscht. **Keine Doppelspur:** Bestehende Entities und Services wiederverwenden, keine parallelen Material-, Pack- oder Logistiksysteme ([../../grossanlass/README.md §20](../../grossanlass/README.md)).

**Erste Priorität der Entwicklung:** Grossanlass vollständig mit Backend/API verbinden, inklusive passender GA-Demodaten ([../README.md §6](../README.md#6-entwicklung-und-teststrategie)).

## 2. Seed-Umfang und Stand

**Zielumfang (SOLL):** Ressorts und Unterressorts · Benutzer, Rollen, Helfer · Aufträge und Bauaufträge · Bauprojekte · Materialbedarf · Lieferanten, Anfragen, Offerten, Zusagen, Bestellungen · Wareneingänge und Teillieferungen · Materialzuweisungen · Packaufträge und Paletten · QR-Labels · Transportbedarf, Fahrzeuge, Fahraufträge · Durchführung und Aufgaben · Rückbau und Retouren · Werkstatt, Entsorgung, Weiterverkauf · Kosten und Abschluss.

| Baustein | Marke | Quelle |
| --- | --- | --- |
| Department `Demo Grossanlass`, `demo_mode`, Ausgangsuhr | **IST** | `DemoGrossanlassSeedService::ensureDepartment`, `ensureDemoClock` |
| Ressort-Baum (Infrastruktur → Material & Logistik / Bauten / Wasser & Sanitär) | **IST** | `ensureDemoRessort`, `ensureGroup` |
| Rollen-User `ga-mw`, `ga-cmw`, `ga-ok`, `ga-komm`, `ga-spon`, `ga-lw`, `ga-clw`, `ga-bereich`, `ga-helfer` | **IST** | `demo-accounts.json`, [Seed-README](../../../backend/data/seeds/dev-demo/README.md) |
| Fahrkarte/Helfer-Verifikation | **IST** | `ensureHelperDriveCard` |
| Zusagen (Festtische, Transporter), Orte, Einsätze (Selbstabholung, Fahrauftrag, pending) | **IST** | `ensureCommitment`, `ensurePlace`, `ensureEinsatz` |
| Zeitstrahl relativ zum Anker E (klein) | **IST** | `ensureTimeline`, `eventWindow` |
| Event-Jobs: 20 Bauaufträge mit Material, nach Gewerken gruppiert, für Firmen-Anfragen (Department `Demo-Grossanlass-Event`) | **IST** | `DemoGrossanlassEventJobsSeedService`, `DemoGrossanlassEventJobsCommand` |
| Demo-Lieferant (Testfirma) | **IST** | `DemoSupplierSeedService` |
| Wipe | **IST** | `DemoGrossanlassWipeService`, `app:demo-grossanlass:wipe` |
| Offerten, Bestellungen, Wareneingänge/Teillieferungen, Zuweisungen | **SOLL** (Seed) | Entities teilweise vorhanden |
| Packaufträge/Paletten, QR-Labels, Transportbedarf mit Touren | **SOLL** (Seed) | Backend-Pack/Fahrten vorhanden, UI Packen/Dispo auf Mock |
| Durchführung/Aufgaben | **SOLL** | UI Aufgaben auf Mock |
| Rückbau, Retouren, Werkstatt, Entsorgung, Weiterverkauf | **SOLL** | Rückbau/Weiterverkauf auf Mock; Entsorgung laut [materialfluss §10.3](../../grossanlass/materialfluss.md) nicht gebaut |
| Kosten und Abschluss | **SOLL** (Seed) | Kostenübersicht im Backend vorhanden |

Frontend-Mocks (**TEILWEISE**): `frontend/src/views/grossanlass/*/ga*Mock.ts` (Aufgaben, Aufträge, Ausgabe, Helferpool, Dispo, Packen, Ressourcen, Rückbau, Weiterverkauf) sowie `grossanlassEinsatzPreviewData.ts`, `grossanlassZusagePreviewStore.ts`. Sie werden je Ansicht durch API-Daten ersetzt (**SOLL**), Reihenfolge siehe §5.

## 3. Zeitstrahl

**SOLL:** Sieben Phasen um den Seed-Anker E (heute Seed-Tag + 7 Tage):

| # | Phase | Typischer Inhalt |
| --- | --- | --- |
| 1 | Planung und Beschaffung | Bedarf, Anfragen, Offerten, Zusagen, Bestellungen |
| 2 | Aufbauvorbereitung | Wareneingang, Zuweisung, Packen, QR-Etiketten, Transportplanung |
| 3 | Aufbau | Fahrten, Ausgabe an Ressorts/Bauprojekte, Bauaufgaben |
| 4 | Anlassbetrieb | Durchführung, Betriebsaufgaben, Helfer-Einsätze |
| 5 | Rückbau | Rücknahme, Rücktransport, Disposition |
| 6 | Retouren und Weiterverkauf | Rückgabe an Firmen, Werkstatt, Entsorgung, Verkauf an Interessenten |
| 7 | Nachbearbeitung und Abschluss | Kostenabschluss, offene Posten, Archiv |

**IST:** Der Seed deckt nur einen Ausschnitt ab: E−7 d zurückgegeben, E−5 d ausgegeben, E−5 d 14:00 kommende Stunden, E+1/+2 d kommende Tage, E+12 d Rückbau. Der Ausgangspunkt ist Anlassbeginn minus 5 Tage, 09:00 (`GrossanlassClockOriginResolver`). Gespeichert / berechnet / geplant: [../SEED-KONZEPT.md §5](../SEED-KONZEPT.md#52-gespeichert-berechnet-geplant).

## 4. End-to-End-Ablauf (SOLL in der Demo)

```
Bedarf → Anfrage → Offerte → Zusage/Bestellung → Wareneingang → Zuweisung
→ Packen → QR-Etikett → Transport → Durchführung → Rückbau
→ Rückgabe / Umlagerung / Werkstatt / Entsorgung / Weiterverkauf → Abschluss
```

Planung, Material, Beschaffung, Logistik, Aufgaben, Fahrzeuge, Helfer und Kosten müssen zusammenhängen. Fachliche Grundlage: [materialfluss.md](../../grossanlass/materialfluss.md) (Charge → Einsatz → Pack → Ort).

| Schritt | Backend | UI | Demo-Seed |
| --- | --- | --- | --- |
| Bedarf | **IST** | **IST** | **TEILWEISE** (Event-Jobs) |
| Anfrage / Offerte / Zusage / Bestellung | **IST** ([§4.4](../../grossanlass/materialfluss.md)) | **IST** | **TEILWEISE** (Zusagen) |
| Wareneingang, Teillieferung | **IST** (mengenbasiert) | **IST** | **SOLL** |
| Zuweisung / Einsatz | **IST** | **IST** | **TEILWEISE** (Einsätze) |
| Packen, Paletten | **IST** (`GrossanlassPackService`), Lücken [§7.2](../../grossanlass/materialfluss.md) | `GrossanlassPackenPlanner` mit Mock | **SOLL** |
| QR-Etikett | **IST** (Charge/Pack/Ort/Karte) | **IST** | **SOLL** |
| Transport, Fahrzeuge, Fahraufträge | **TEILWEISE** | Logistik/Dispo auf Mock | **TEILWEISE** (Fahrauftrag) |
| Durchführung, Aufgaben | **TEILWEISE** | Mock | **SOLL** |
| Rückbau, Teilmengen-Disposition | **TEILWEISE** (Teilmengen fehlen, [§10](../../grossanlass/materialfluss.md)) | Mock | **SOLL** |
| Werkstatt | **TEILWEISE** (kein Bezug zu Charge/Menge) | **IST** | **SOLL** |
| Entsorgung | **SOLL** (kein Feld) | Mock | **SOLL** |
| Weiterverkauf | **TEILWEISE** (nur Gast-Department-Verkauf ohne physische Übergabe) | Mock | **SOLL** |
| Kosten, Abschluss | **IST** | **IST** | **SOLL** |

Hinweis: Die Spalten Backend/UI für Bedarf bis Zuweisung sowie Kosten stützen sich auf die Fachdokumente, nicht auf eine Einzelprüfung des Codes.

## 5. Reihenfolge der Anbindung (Vorschlag)

Entlang des Materialflusses: Zusagen/Wareneingang/Zuweisung → Packen/QR → Logistik/Dispo → Aufgaben → Rückbau → Werkstatt/Entsorgung/Weiterverkauf → Kosten/Abschluss. Je Ansicht: Backend-Lücke schliessen, Seed ergänzen, Mock entfernen, Test. Endgültige Reihenfolge ist Sache des Teams. Die Sandbox ([../README.md §5](../README.md#5-demo-sicherheit-und-side-effect-sandbox)) ist vor der Vorführung von Anfrage-/Offerten-Versand nötig.

## 6. Öffentliche Funktionen

Grossanlass-spezifisch: öffentliche Helfer-Karte (`/c/…`), Ort (`/i/p/…`), Pack (`/i/k/…`) (**IST**); Weiterverkauf an **externe Interessenten** mit Materialübernahme (**SOLL**: heute nur Mock-UI und `guest_share kind=sale`, keine öffentliche Seite, kein Backend-Pfad). Allgemeines: [../README.md §4](../README.md#4-öffentliche-und-qr-funktionen).

## 7. Offene Fragen

- **Frage 2:** `Demo Grossanlass` (Rollen, Zusagen) und `Demo-Grossanlass-Event` (20 Bauaufträge) zu **einem** durchgängigen Szenario vereinen oder getrennt lassen?
- **Frage 7:** Backend-Lücken des Zielbilds: Disposition mit Teilmengen, Entsorgung/Verbrauch/Verlust, physische Verkaufsübergabe (`sold_handover`), externe Interessenten. Entitätsentwurf offen; muss auf den bestehenden Charge-Bewegungen aufbauen ([materialfluss §10, §14](../../grossanlass/materialfluss.md)).
- **Frage 1 (gemeinsam):** Ist-Daten späterer Phasen vs. Uhr, siehe [../SEED-KONZEPT.md §5.3](../SEED-KONZEPT.md#53-offen-frage-1-ist-daten-vs-uhr).
- **Fragen 5, 6 (gemeinsam):** Sandbox-Schnitt und simulierte Gegenseite (Lieferant antwortet, Interessent meldet sich), siehe [../README.md §5](../README.md#5-demo-sicherheit-und-side-effect-sandbox).
