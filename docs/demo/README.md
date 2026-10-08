# Demo-System

**Stand:** 8. Oktober 2026. Übergeordnete Beschreibung des Demo-Systems von eMatChef. Die Demo-Infrastruktur gilt für **alle** Departments. Grossanlass ist ein Szenario unter mehreren.

**Leseregel:** Jeder Abschnitt trägt eine Marke.

| Marke | Bedeutung |
| --- | --- |
| **IST** | Im Code vorhanden (Stand siehe oben). |
| **TEILWEISE** | Ansatz vorhanden, aber unvollständig oder nur als Mock. |
| **SOLL** | Beschlossenes Zielbild, noch nicht gebaut. |
| **OFFEN** | Architekturfrage ohne Entscheid. |

Nichts unter **SOLL** darf als vorhanden dargestellt werden. Wo diese Dokumente und der Code kollidieren, gilt der Code; das Dokument wird dann angepasst.

## Dokumente

| Datei | Inhalt |
| --- | --- |
| [README.md](./README.md) (diese Datei) | Ziele, Prinzipien, Architektur, Sicherheit/Sandbox, öffentliche QR-Funktionen, Entwicklung und Tests |
| [BUSINESS-CLOCK.md](./BUSINESS-CLOCK.md) | Fachzeit vs. Systemzeit, Zeitreise, Zeitzonen, Sicherheitsgrenzen |
| [SEED-KONZEPT.md](./SEED-KONZEPT.md) | Gemeinsame Seed-Bausteine, Isolation, Idempotenz, Reset, Zeitstrahl, Mengenbilanz, Szenario-Registry |
| [scenarios/DEPARTMENT.md](./scenarios/DEPARTMENT.md) | Szenario: normales Department (Materialverwaltung) |
| [scenarios/GROSSANLASS.md](./scenarios/GROSSANLASS.md) | Szenario: Grossanlass, Ablauf und Implementierungsstand |
| [DEMO-KONZEPT.md](./DEMO-KONZEPT.md) | Nur noch Einstieg mit Verweisen (alte Links bleiben gültig) |

**Nicht hier dupliziert** (nur verlinkt):

| Thema | Quelle |
| --- | --- |
| BusinessClock, technische Details (Auflösung, API, Frontend, Commands) | [../grossanlass/business-clock.md](../grossanlass/business-clock.md) |
| Grossanlass Ist-Stand, Materialfluss, Kosten | [../grossanlass/README.md](../grossanlass/README.md), [../grossanlass/materialfluss.md](../grossanlass/materialfluss.md), [../grossanlass/kosten.md](../grossanlass/kosten.md) |
| QR-Links und öffentliche QR-Seiten | [../qr/README.md](../qr/README.md) |
| Mail | [../mail/README.md](../mail/README.md) |
| Systemaufbau, Domain-Modell, Entwickeln | [../ARCHITECTURE.md](../ARCHITECTURE.md), [../DOMAIN-MODEL.md](../DOMAIN-MODEL.md), [../DEVELOPMENT.md](../DEVELOPMENT.md) |
| Demo-Konten (Single Source) | `backend/data/seeds/dev-demo/demo-accounts.json`, [Seed-README](../../backend/data/seeds/dev-demo/README.md) |

---

## 1. Ziele und Grundprinzipien

eMatChef soll ein vollständiges, realistisches Demo-System besitzen. Die Demo ist keine optische Vorschau, sondern läuft möglichst auf denselben Symfony-Services, Entities, REST-APIs und Vue-Komponenten wie die echte Anwendung (**SOLL**).

1. **Keine Demo-Parallelwelt.** Ein Demo-Department ist ein Department mit Daten, kein eigener Code-Pfad. Verzweigungen nach `demo_mode` sind die Ausnahme und auf Zeit ([BUSINESS-CLOCK.md](./BUSINESS-CLOCK.md)) und Side-Effects ([§5](#5-demo-sicherheit-und-side-effect-sandbox)) begrenzt.
2. **Mock-Daten werden schrittweise durch echte Backend-Daten ersetzt.** Pro Ansicht entfällt der Mock, sobald API und Seed die Ansicht tragen.
3. **Das bestehende Grossanlass-UI ist das fachliche und visuelle Zielbild** (für Grossanlass). Es wird bei der Backend-Anbindung nicht auf den bisherigen Backend-Funktionsumfang reduziert; fehlt dem Backend etwas, wird es über die bestehenden Entities und Services erweitert ([scenarios/GROSSANLASS.md](./scenarios/GROSSANLASS.md)).
4. **Designverbesserungen während der Umsetzung sind ausdrücklich erwünscht.** Mock-UIs sind Zielbild, kein Pixel-Pflichtenheft.
5. **Eine Demo, mehrere Zwecke:** Produktvorführung, Entwicklung, manuelle End-to-End-Tests, automatisierte Tests und Prüfung des vollständigen Materialflusses. Was die Demo nicht sauber durchspielt, ist ein Fund für die Anwendung.
6. **Bestehende Strukturen wiederverwenden.** Keine parallelen Material-, Pack- oder Logistiksysteme für die Demo.

## 2. Demo-Departments

Ein Demo-Department ist ein `Department` mit `demo_mode = true` (**IST**, [BUSINESS-CLOCK.md](./BUSINESS-CLOCK.md)). Die Markierung erfolgt nur über das Flag, **nie** über den Namen; der Name ist keine Berechtigungsgrenze.

| Szenario | Dokument | Demo-Department heute | Stand |
| --- | --- | --- | --- |
| Materialverwaltung (`materialverwaltung`) | [DEPARTMENT.md](./scenarios/DEPARTMENT.md) | keines | **SOLL** |
| Grossanlass Event (`grossanlass-event`) | [GROSSANLASS.md](./scenarios/GROSSANLASS.md) | `Demo Grossanlass`, zusätzlich `Demo-Grossanlass-Event` (heute zwei Departments, schmal) | **IST** (schmal) / Zusammenführung **SOLL** |
| Grossanlass Camp (`grossanlass-camp`) | [GROSSANLASS.md §8](./scenarios/GROSSANLASS.md#8-szenario-grossanlass-camp-soll) | keines | **SOLL** |

**SOLL (beschlossen):** Es gibt **drei voneinander unabhängige** Demo-Departments, je mit eigener BusinessClock, eigenem Ausgangspunkt, mehreren Prozesszuständen und unabhängigem Reset. Sie verwenden dieselben bestehenden Domain-Strukturen und dieselbe Seed- und Zeit-Infrastruktur ([SEED-KONZEPT.md](./SEED-KONZEPT.md)), aber keine gemeinsamen Datensätze und keine Abhängigkeit zwischen Szenarien. Weitere Szenarien (z. B. Lieferant, Werkstatt-Schwerpunkt) sind denkbar, aber nicht geplant.

## 3. Gemeinsame technische Architektur

| Baustein | Marke | Beschreibung |
| --- | --- | --- |
| Demo-Flag `department.demo_mode`, Offset `demo_clock_offset_seconds` | **IST** | Department-Eigenschaft, allgemein, nicht Grossanlass-spezifisch |
| `BusinessClock` | **IST** | [BUSINESS-CLOCK.md](./BUSINESS-CLOCK.md); Migration der Zeitstellen bisher nur Grossanlass |
| Zeitreise im Header (`ClockControl.vue`, `/api/departments/{id}/clock`) | **IST** | für jedes Department, nicht an Grossanlass gekoppelt |
| Ausgangspunkt-Resolver (`ClockOriginResolverInterface`, Tag `app.clock_origin_resolver`) | **IST** (ein Resolver: Grossanlass) | weitere Resolver ohne neue Spalte möglich |
| Seed-Services (`ensure*`-Muster) | **IST** (nur Grossanlass, Lieferant); bekannte Probleme P1–P15 | [SEED-KONZEPT.md §3.1](./SEED-KONZEPT.md#31-ist-probleme) |
| Rollen-User, Demo-Konten | **IST** | `app:create-role-users`, `app:dev-demo:reset`, `demo-accounts.json` |
| Umgebungsfreigabe für Demo-Befehle (`EMATCHEF_ENV_NAME`, `EMATCHEF_DEMO_DESTRUCTIVE`, fail-closed) | **IST** (Phase 0) | [SEED-KONZEPT.md §3.2](./SEED-KONZEPT.md#32-phase-0-absicherung-ist) |
| Gemeinsamer Szenario-Rahmen / Registry, Szenario-Schlüssel am Department, Ownership-Ledger | **SOLL** (Empfehlung, Freigabe offen) | [SEED-KONZEPT.md §7](./SEED-KONZEPT.md#7-szenario-registry-soll) |
| Sync/Reset-Commands `app:demo:*`, Umgebungsschalter, Deployment-Sync (nur Develop) | **SOLL** | [SEED-KONZEPT.md §7.8–§7.9](./SEED-KONZEPT.md#78-command-struktur) |
| Seed-Umsetzungsphasen und Tests | **SOLL** | [SEED-KONZEPT.md §9](./SEED-KONZEPT.md#9-umsetzungsphasen-mit-tests-soll) |
| Side-Effect-Sandbox | **SOLL** | [§5](#5-demo-sicherheit-und-side-effect-sandbox) |

### Reale APIs und Domain-Services

**SOLL:** Demo-Ansichten sprechen dieselben REST-Endpunkte an wie die echte Anwendung, ausschliesslich über den bestehenden `apiClient` (`frontend/src/api/`). Seeds legen Daten über die Domain-Services und Entities an, nicht über eine separate Schreibschicht. Berechtigungen werden wie in der echten Anwendung serverseitig geprüft; die Demo erhält keine Sonderrechte, ausser der Zeitreise-Berechtigung ([BUSINESS-CLOCK.md](./BUSINESS-CLOCK.md)).

**IST:** Das gilt für die Backend-Funktionen, die es gibt. Mehrere Grossanlass-Ansichten laufen noch auf Frontend-Mocks ([GROSSANLASS.md](./scenarios/GROSSANLASS.md)).

## 4. Öffentliche und QR-Funktionen

`app.ematchef.test` umfasst nicht nur die interne Materialverwaltung. Die Demo muss auch abdecken, was Aussenstehende oder das Handy im Lager sehen. Fachliche Quelle: [../qr/](../qr/README.md).

| Funktion | Marke | Fundstelle |
| --- | --- | --- |
| Öffentliche QR-Seiten Material/Charge (`/i/m/:mat/b/:batch`), Aktivität (`/i/a/…`), Werkstatt (`/i/w/…`) | **IST** | [qr-public-pages.md](../qr/qr-public-pages.md), `PublicMaterialView`, `PublicActivityView`, `PublicWorkshopView` |
| Öffentliche Grossanlass-Seiten: Helfer-Karte (`/c/…`), Ort (`/i/p/…`), Pack (`/i/k/…`) | **IST** | `PublicGrossanlassCardView`, `PublicGrossanlassPlaceView`, `PublicGrossanlassPackView`; API `PublicLookupController` (`/api/public/lookup/{m,a,w,c,p,ga,k}`) |
| QR-Scanner (Weiterleitung `/open-from-qr`) | **IST** | `OpenFromQrView`, [scan-and-url-history.md](../qr/scan-and-url-history.md) |
| QR-Etiketten (Druck/PDF) | **IST** | [../qr/](../qr/README.md) |
| Öffentliches Kontaktformular der Abteilung | **IST** | `Controller/Public/PublicContactController` |
| Öffentliche Demo-Ansicht Material | **IST** (Demo-Route) | `/qr-demo/material`, `QrMaterialDemoView` |
| Display-Ansicht | **IST** | Route `/display/:publicId` |
| Lager-Geräte-Scan (`devices.`) | **TEILWEISE** | [../devices/README.md](../devices/README.md); Scan-Hub und Retour sind dort **Soll** |
| Material-Weiterverkauf mit externen Interessenten und Materialübernahmen | **SOLL** | heute nur Mock-UI und `guest_share kind=sale` an Gast-Departments, siehe [GROSSANLASS.md](./scenarios/GROSSANLASS.md) |
| Seeds erzeugen gültige `public_code`s, die auf Demo-Daten auflösen | **SOLL** | [SEED-KONZEPT.md](./SEED-KONZEPT.md) |

**Anforderung (SOLL):** Öffentliche Demo-Flows (Scannen, Kontaktformular absenden, Interesse bekunden, Übernahme bestätigen) funktionieren möglichst realistisch, lösen aber **keine echten externen Nebenwirkungen** aus ([§5](#5-demo-sicherheit-und-side-effect-sandbox)).

**OFFEN (Frage 4):** Öffentliche Routen haben keinen eingeloggten Department-Kontext. Wie erkennen sie Demo-Department, Demo-Uhr und Sandbox? Vermutlich über das Department des aufgelösten Codes, nicht über die Session.

## 5. Demo-Sicherheit und Side-Effect-Sandbox

**Anforderung (SOLL):** Demo-Departments dürfen keine unbeabsichtigten echten externen Aktionen auslösen. Aktionen sollen fachlich nachvollziehbar **simuliert** werden (Protokoll/Outbox statt Versand), damit eine Vorführung zeigt, was passiert wäre.

**Status: nicht implementiert.** Heute löst ein Demo-Flow echte externe Wirkung aus, wenn die jeweilige Konfiguration vorhanden ist. Die Demo-Zeit sperrt nichts davon. Die Sandbox ist auch nicht teilweise als gegeben zu betrachten.

**Zu sperren bzw. umzuleiten** (Inventar aus [business-clock.md, Phase 2](../grossanlass/business-clock.md); je Adapter vor der Umsetzung gegen den Code zu verifizieren):

| Bereich | Bekannte Stellen |
| --- | --- |
| E-Mail | `Service/Mail/AppMailer`, `MailTransportResolver` |
| Gmail | `GrossanlassGmailApi`, `GmailOAuthClient`, `GmailMailboxProvider` |
| Outlook | `OutlookGraphApi`, `OutlookOAuthClient`, `OutlookMailboxProvider` |
| MiData/Hitobito | `HitobitoApiClient`, `HitobitoOAuthClient`, `MiDataGroupImportService` |
| Lieferantenkommunikation | Versand über Mailbox/AppMailer, Lieferantenportal |
| Öffentliche Kontaktfunktionen | `Controller/Public/*`, `GrossanlassInquiryWebLookup` |
| Weitere externe Schreibzugriffe | `GrossanlassPlaceGeocoder`, `MediaUrlImportService`, Medien-/Dokument-Uploads |

**Zielarchitektur (SOLL, Entwurf):**

1. Eine zentrale Stelle, nicht verstreute `if ($demo)` in Controllern. Bevorzugt ein Entscheid an der Adapter-Grenze (Dekorator/Resolver pro Schnittstelle), analog zu `MailTransportResolver`.
2. Sperre anhand `demo_mode` des Kontext-Departments, nicht des Namens.
3. Ersatz durch Protokoll: Kanal, Empfänger, Inhalt, Auslöser, Demo-Zeit; in der Demo einsehbar.
4. Simulierte Antworten nur als bewusst ausgelöste Demo-Aktion (z. B. «Lieferant antwortet mit Offerte»), die dieselben Services aufruft wie eine echte Antwort.
5. Fail closed: Ein Adapter ohne Sandbox-Entscheid sendet im Demo-Department nicht.
6. Je Adapter ein Test, der belegt, dass im Demo-Department nichts nach aussen geht.

Bis zur Umsetzung gilt organisatorisch: Demo nur gegen Mailtrap-Umgebungen ([../mail/README.md](../mail/README.md)) und ohne verbundene Postfächer.

**OFFEN (Fragen 5 und 6):**
- Sandbox-Schnitt: Dekorator je Adapter oder zentrales Outbox-Ereignis? Wo wird das Demo-Protokoll angezeigt (Nachrichtenzentrale, eigene Ansicht)?
- Simulierte Gegenseite: Wie lösen Demo-Benutzer «Lieferant antwortet» oder «Interessent meldet sich» aus (Lieferantenportal mit Demo-Lieferant, Demo-Aktions-Menü)?

## 6. Entwicklung und Teststrategie

### Umgebung (IST/SOLL)

Entwickelt wird vorerst nacheinander in **einer** lokalen Umgebung, `https://app.ematchef.test` ([../DEVELOPMENT.md](../DEVELOPMENT.md)). Es werden keine zusätzlichen Docker-Stacks und keine Git-Worktrees vorausgesetzt. Folge: ein Satz Demo-Departments pro Datenbank, daher Isolation und Wiederholbarkeit der Seeds ([SEED-KONZEPT.md](./SEED-KONZEPT.md)).

### Reihenfolge (SOLL)

0. **Seed-Absicherung und Gerüst** (Phasen 0–3 in [SEED-KONZEPT.md §9](./SEED-KONZEPT.md#9-umsetzungsphasen-mit-tests-soll)): gefährliche Altcommands absichern, Szenario-Registry, Reset, Umgebungsschalter.
1. **Grossanlass Event** vollständig mit Backend/API verbinden, inklusive passender GA-Demodaten und möglicher Designverbesserungen ([GROSSANLASS.md](./scenarios/GROSSANLASS.md)). Sandbox (§5) vor der Vorführung von Anfrage-/Offerten-Versand.
2. Danach **Materialverwaltung** ([DEPARTMENT.md](./scenarios/DEPARTMENT.md)), inklusive BusinessClock-Migration ausserhalb Grossanlass, und **Grossanlass Camp**. Beide sind voneinander unabhängig.
3. **Develop-Deployment-Sync** (SEED-KONZEPT Phase 6) erst, wenn Sync idempotent und getestet ist.

### Tests

| Zweck | Ansatz (SOLL) |
| --- | --- |
| Produktvorführung | stabiler Seed-Zustand, definierter Ausgangspunkt, Sandbox aktiv |
| Entwicklung | wiederholbarer Seed, schneller Reset |
| Manuelle E2E-Tests | Zeitstrahl durchreisen, Materialfluss ohne Re-Seed |
| Automatisierte Tests | Seed-Idempotenz, Isolation, Mengenbilanz, Service-/API-Tests mit festem Offset, Playwright-Smoke ([../E2E.md](../E2E.md)) |
| Sandbox | je Adapter ein Test |

**IST:** `BusinessClockTest`, `BusinessClockWiringTest`, `DepartmentClockControllerTest` (Backend), `businessClock.spec.ts` (Frontend). Die vorhandenen `ga*Mock.spec.ts` prüfen die Mocks und entfallen mit ihnen.

## 7. Offene Architekturfragen (Übersicht)

Die Fragen stehen am fachlich passenden Ort. Nummerierung unverändert gegenüber dem bisherigen Konzept.

| # | Frage | Ort |
| --- | --- | --- |
| 1 | Gespeicherte Ist-Daten späterer Phasen vs. Uhr | [SEED-KONZEPT.md §5](./SEED-KONZEPT.md#5-zeitstrahl) |
| 2 | ~~Ein Grossanlass-Szenario oder zwei Departments~~ **Entschieden (Zielbild):** zwei unabhängige Grossanlass-Szenarien, Event und Camp. Offen bleibt die Umsetzung für die bestehenden Departments (E4) | [GROSSANLASS.md](./scenarios/GROSSANLASS.md#7-offene-fragen) |
| 3 | Seed-Mechanik: `ensure*`-Services oder Fixtures/Registry. **Empfehlung:** `ensure*` behalten, hinter gemeinsamem Szenario-Interface mit Registry; keine Doctrine-Fixtures. Freigabe offen | [SEED-KONZEPT.md §7](./SEED-KONZEPT.md#7-szenario-registry-soll) |
| E1–E11 | Seed-Architektur: Reset-Variante, Camp-Domain, Ledger, Altbestand, Staging, Deployment-Fehlerverhalten, Index-Herkunft u. a. | [SEED-KONZEPT.md §10](./SEED-KONZEPT.md#10-offene-entscheidungen) |
| 4 | Öffentliche Routen und Demo-Kontext | [§4](#4-öffentliche-und-qr-funktionen) |
| 5 | Sandbox-Schnitt und Protokoll-Anzeige | [§5](#5-demo-sicherheit-und-side-effect-sandbox) |
| 6 | Simulierte Gegenseite | [§5](#5-demo-sicherheit-und-side-effect-sandbox) |
| 7 | Backend-Lücken des Grossanlass-Zielbilds (Teilmengen, Entsorgung, Verkaufsübergabe, Interessenten) | [GROSSANLASS.md](./scenarios/GROSSANLASS.md#7-offene-fragen) |
| 8 | Zeitabhängige Services normaler Departments | [DEPARTMENT.md](./scenarios/DEPARTMENT.md#8-offene-fragen) |
| 9 | Zeitzonen | [BUSINESS-CLOCK.md §6](./BUSINESS-CLOCK.md#6-zeitzonen) |

## 8. Pflege

Diese Dokumente sind die zentrale Beschreibung der Demo-Ziele. Sie sind bei Bedarf mitzupflegen, sobald sich ein Demo-Seed, die BusinessClock, ein Demo-Ablauf, die Sandbox oder die öffentliche Demo-Ansicht ändert. Beim Umsetzen wird die Marke (**SOLL** → **IST**) angepasst und die Frage gestrichen oder entschieden. Fachdetails gehören in die verlinkten Fachdokumente. Sichtbares Bedienverhalten gehört zusätzlich in die öffentliche Dokumentation (`eMatChef/documentation`); Demo-Konten dort kommen nur aus `demo-accounts.json`.
