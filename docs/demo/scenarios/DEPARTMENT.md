# Szenario: Normales Department

**Stand:** 8. Oktober 2026. Demo-Szenario für die normale Materialverwaltung (Pfadi-Abteilung, Lager, Aktivitäten). Gemeinsame Regeln: [../README.md](../README.md), [../SEED-KONZEPT.md](../SEED-KONZEPT.md), [../BUSINESS-CLOCK.md](../BUSINESS-CLOCK.md). Marken **IST / TEILWEISE / SOLL / OFFEN** wie dort.

**Status des Szenarios: SOLL.** Es gibt heute **kein** Demo-Department für ein normales Department und keinen Seed dafür. Die unten genannten Funktionen der Anwendung sind vorhanden (**IST**); es fehlen die Demo-Daten und der Seed. Der Ausbau folgt nach Grossanlass ([../README.md §6](../README.md#6-entwicklung-und-teststrategie)).

## 1. Zweck

Ein realistisches Demo-Department zeigt die normale Materialverwaltung von Anfang bis Ende: Lager, Ausgabe an Aktivitäten, Rückgabe, Inventur, Werkstatt, QR. Es nutzt dieselben Entities, Services, REST-APIs und Vue-Komponenten wie die echte Anwendung und ist fachlich vom Grossanlass getrennt ([GROSSANLASS.md](./GROSSANLASS.md)). Beide nutzen dieselben Domain-Strukturen.

## 2. Seed-Umfang (SOLL)

| Bereich | Inhalt | Funktion in der Anwendung |
| --- | --- | --- |
| Benutzer, Rollen, Gruppen | Materialwart, Leitung, Stufenleitende, Mitglieder; Gruppen/Stufen | **IST** (Rollen-User via `app:create-role-users`; Demo-Konten in `demo-accounts.json`) |
| Materiallager und Artikel | Lagerorte (Gestelle, Fächer), Artikel mit Chargen, Kombos, Vorlagen | **IST** (`StorageRack`, `StorageSlot`, `MaterialBatch`, [Kombos](../../material/combos/README.md), [Vorlagen](../../material/templates/README.md)) |
| Bestände, Lagerbewegungen | Anfangsbestand, Zu-/Abgänge, Einkäufe | **IST** (Funktion), Seed **SOLL** |
| Ausgaben und Rückgaben | Ausgabe an Aktivitäten, Teil- und Vollrückgabe, Fehlmengen, Verbrauch | **IST** ([../../activities/](../../activities/README.md)) |
| Aktivitäten | Anlässe in allen Status (`draft` … Rückgabe), Packliste/Pack-Journey, J+S-Material | **IST** ([status.md](../../activities/status.md), [js-material](../../activities/js-material/README.md)) |
| Inventur | Inventuraufgaben, Abweichungen | **IST/TEILWEISE** (`InventoryTask`; Geräte-Inventur laut [devices](../../devices/README.md) später) |
| Wartungen und Reparaturen | Werkstatt-Tickets mit Phasen, Lieferanten-Reparatur | **IST** ([workshop](../../workshop/README.md), [supplier-portal](../../supplier/supplier-portal.md)) |
| Weitere Department-Funktionen | Buchhaltung, Nachrichten, Geräte, Medien, öffentliche Seiten | **IST** ([accounting.md](../../accounting.md), [nachrichtenzentrale.md](../../nachrichtenzentrale.md)); Umfang pro Funktion **OFFEN** |

Seed-Regeln (Isolation, Idempotenz, Mengenbilanz, Reset): [../SEED-KONZEPT.md](../SEED-KONZEPT.md). Das Szenario darf weder Grossanlass-Departments noch andere Departments verändern.

## 3. Realistische Demo-Abläufe (SOLL)

Jeder Ablauf soll in der Demo mit den echten Services durchspielbar sein.

1. **Anlass planen und packen:** Aktivität anlegen und einreichen → bestätigen → Packliste/Pack-Journey → Material scannen → gepackt.
2. **Ausgabe:** Material an Leitende ausgeben, Abholung, Status «am Anlass».
3. **Rückgabe:** Teilrückgabe, fehlendes oder defektes Material erfassen, Verbrauchsmaterial abgleichen, Rückgabe abschliessen.
4. **Defekt → Werkstatt:** defekt gemeldetes Material → Werkstatt-Ticket → Reparatur (intern/Lieferant) → zurück ins Lager.
5. **Inventur:** Inventuraufgabe, Zählung, Abweichungen, Bestandskorrektur als Lagerbewegung.
6. **Wartung:** wiederkehrende Prüfungen (z. B. Zelte, Sicherheitsmaterial) mit Fälligkeit.
7. **Einkauf/Nachbestellung:** Bestand unter Mindestmenge → Bestellung → Wareneingang → Charge mit QR-Etikett.
8. **J+S-Leihmaterial:** Bestellformular, Dotation, Check-Flow.

Rollen-Sichten (Materialwart, Leitung, Mitglied) werden mit den Demo-Konten gezeigt, nicht über Sonderrechte.

## 4. Zeitstrahl

**SOLL.** Anker E in der Nähe von «heute». Innerhalb des Demo-Departments gleichzeitig:

| Phase | Beispiele |
| --- | --- |
| Vergangen | abgeschlossene Aktivität mit vollständiger Rückgabe, geschlossene Werkstattfälle, erledigte Inventur |
| Laufend | Aktivität «am Anlass», offene Ausgabe, Ticket in Reparatur |
| Kommend | geplante Aktivität im Planungsfenster, Wartungsfälligkeit, Rückgabefrist, geplante Inventur |

Man reist mit der Header-Uhr durch diese Zeitpunkte, ohne neu zu seeden. Es gilt die Trennung gespeichert / berechnet / geplant ([../SEED-KONZEPT.md §5](../SEED-KONZEPT.md#5-zeitstrahl)): Ein Zeitsprung erfindet keine Ausgabe, Rückgabe oder Erledigung.

## 5. QR und öffentliche Funktionen

| Funktion | Marke |
| --- | --- |
| Chargen-QR (`/i/m/:mat/b/:batch`), Aktivitäts-QR (`/i/a/…`), Werkstatt-QR (`/i/w/…`), Lagerort-QR | **IST** ([../../qr/](../../qr/README.md)) |
| QR-Scanner (`/open-from-qr`), Lager-Geräte (`devices.`) | **IST/TEILWEISE** ([devices](../../devices/README.md)) |
| Öffentliche Seiten und Kontaktformular der Abteilung | **IST** |
| Demo-Seed erzeugt gültige `public_code`s für Chargen, Aktivitäten, Tickets | **SOLL** |

Öffentliche Demo-Flows lösen keine echten externen Wirkungen aus ([../README.md §5](../README.md#5-demo-sicherheit-und-side-effect-sandbox)).

## 6. BusinessClock

- **IST:** Ein Demo-Department ohne Grossanlass kann den Offset nutzen und reisen ([../BUSINESS-CLOCK.md](../BUSINESS-CLOCK.md)).
- **TEILWEISE:** Es gibt keinen Ausgangspunkt-Resolver; «Zurücksetzen» führt auf die reale Zeit. Ein Resolver für dieses Szenario ist ohne neue Spalte möglich (**SOLL**).
- **SOLL:** Zeitabhängige Services des normalen Departments (Aktivitäts-Fristen, Rückgabe-Fälligkeit, Wartungsfälligkeit, Inventur, Werkstatt-Erinnerungen) migrieren auf die BusinessClock.

## 7. Abgrenzung zu Grossanlass

Kein gemeinsames Szenario. Gemeinsam sind Domain-Strukturen (Material, Chargen, QR, Werkstatt), Seed-Rahmen und BusinessClock. Grossanlass-spezifische Konzepte (Ressorts, Einsätze, Beschaffung, Disposition) gehören nach [GROSSANLASS.md](./GROSSANLASS.md).

## 8. Offene Fragen

- **Frage 8:** Welche Services normaler Departments sind zeitabhängig und migrieren auf die BusinessClock (Inventur, Wartung, Ausleihe-Fristen, Werkstatt-Erinnerungen)? Je Stelle gegen den Code zu prüfen.
- **Frage 3 (gemeinsam, siehe [../SEED-KONZEPT.md §7](../SEED-KONZEPT.md#7-szenario-registry-soll)):** Seed-Mechanik (`ensure*`-Services oder Fixtures/Registry) vor dem Ausbau dieses Szenarios entscheiden.
- Welche Wartungs-/Inventur-Abläufe sind in der Anwendung heute vollständig genug für eine Vorführung? Noch nicht gegen den Code geprüft.
