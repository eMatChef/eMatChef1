# Grossanlass — Bauprojekt, GA-Ort, Aufgaben & Material

Produktklärung 18.09.2026. **Dieses Dokument gilt** für Projektplanung am Bauprojekt, Event-Standorte und den Helferauftrag. Ergänzt [20260823_New_concept.md](./20260823_New_concept.md) §12.3 (Einsatz) und [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) (Fahrauftrag, Pack, Standort-QR).

**Status:** Phasen P0–P8 in [§8](#8-phasen) sind umgesetzt. Ist-Ergänzungen, die nach diesem Dokument dazukamen: [README §0.2](./README.md#02-baum-ressort-bereich-bauprojekt) und [§0.3](./README.md#03-bauprojekt-karte-aufgaben-fahrzeugwunsch) (Bauvorhaben-Status, gespeichertes `grossanlass_kind = bereich`, Teilen, Polygon, Aufgaben-Zeitblock/Beschrieb/Verantwortliche, Fahrzeugwunsch mit Kategorie und Beschaffungsposition).

**Zentrale Spezifikation:** [materialfluss.md](./materialfluss.md) beschreibt den kompletten Grossanlass-Materialfluss (Herkunft, Übergabe, Bestand, Bauprojektzuweisung, Rückbau, Rückgabe).

**Verwandt:** [README.md](./README.md) · [materialfluss.md](./materialfluss.md) · [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) · [qr/link-schema.md](../qr/link-schema.md)

---

## 1. Satz Zielbild

Ein **Bauprojekt** (Bühne, Wasserstelle) ist Ort + grobes Zeitfenster + zwei Listen: **Aufgaben** (Arbeit) und **Material** (Bedarf). Materialzeilen **sind Wünsche** — sichtbar unter Wünsche & Ideen, mitzählend beim Bündeln (Gator Bühne + Gator Sicherheit = eine Position). **Einsatz** reserviert vorhandenes Material im Projektfenster. **Fahrauftrag** fährt gepacktes Material zum **selben GA-Ort-QR**. Helfer bekommen einen Druck: Karte, Standort-QR, Aufgaben, Material. Kein zweites QR-System, keine zweite Fahrt-Tabelle, kein doppeltes Bündeln.

---

## 2. Was nicht vermischen

| Welt | Gegenstand | QR |
| --- | --- | --- |
| **Lager** | **Lagerstandort** (Lagerplatz), Regal, Fach | `/i/l/`, `/i/r/`, `/i/s/` |
| **Adresse** | Event, Zustellpunkt, `event_poi` in Kontakten | kein Pack-Ziel |
| **GA-Ort** | Standorte **im** Anlass | kanonisch `/i/ga/{code}`, Alias `/i/p/{code}` |

**Lagerplatz = Lagerstandort** (`Address` `type=storage`, `department_grossanlass_place.kind=lagerplatz`, gespiegelt per `storage_address_id`). Nicht manuell als Event-GA-Ort anlegen. Ausgabe und Selbstabholung laufen über diesen bestehenden Standort. Fahrauftrag, Helferauftrag und Bauprojekt zielen nur auf **GA-Ort** (andere `kind`). Lager-QR und Kontakt-POI nicht als Bühnen-Schild verwenden.

`kind: order` am Einsatz bleibt **Nachbedarf**, nicht Bauauftrag und nicht Fahrt.

---

## 3. Objekte

```
GA-Ort ─────────────────┬── kind (bauprojekt | unterlager | anfahrt | poi)
  QR /i/ga/{code}       ├── group_id? → Bauprojekt / Ressort
                        ├── unterlager_id?
                        └── Position: WGS84; Stern = auf Stammdaten; map_x / map_y auf dem Geländeplan

Lagerstandort (Lagerplatz)  Address type=storage · QR /i/l/ sichtbar am Standort — kein Event-GA-Ort

Bauprojekt (Group, teilbereich)
  grobes Fenster (von–bis)
  ├── Aufgaben[]          Arbeit für die Crew — nicht in Bedarf
  └── Material = Wünsche  group_id = dieses Projekt
            ↓
       Bedarf bündeln (über Ressorts hinweg)
            ↓
       Zusage / Lager
            ↓
       Einsatz im Projektfenster → Pack → GA-Ort-QR
```

### 3.1 GA-Ort (`department_grossanlass_place`)

Ein Typ, aufgeschlüsselt über `kind`:

| `kind` | Beispiel | Verknüpfung |
| --- | --- | --- |
| `bauprojekt` | Bühne, Wasserstelle (Punkt) | Zuordnung über `department_grossanlass_place_group` (siehe 3.1b) |
| `area` | Bereich als Polygon | Zuordnung über `department_grossanlass_place_group` |
| `unterlager` | Region Ost | `unterlager_id` (Ist: Seed aus Unterlager) |
| `anfahrt` | Anfahrpunkt / Tor für Fahrten | Ziel-Ort für Fahrauftrag |
| `poi` | Tor, Infopoint | ohne Projekt |

`kind: lagerplatz` nur über Lagerstandort-Sync, nicht im GA-Ort-Dialog. Zentraler Materialplatz = **Lagerstandort** unter Standorte (bereits da).

**IDs:** intern 12-stellig wie bisher (`pl…` bleibt gültig). **Neue public_codes** Prefix `ga` (12 Zeichen). Lookup akzeptiert alte `pl…`-Codes.

**QR**

| | Pfad | Lookup |
| --- | --- | --- |
| Kanonisch (neu drucken) | `/i/ga/{code}` | `GET /api/public/lookup/ga/{code}` |
| Alias (gedruckte Schilder) | `/i/p/{code}` | `GET /api/public/lookup/p/{code}` |

`entity_type` bleibt `ga_place`. Scanner-Typ `ga_place` für beide Pfade. Pack-Ankommen (`scanArrive`) ändert sich nicht — Ziel ist weiterhin `destination_place_id`.

### 3.1b Zuordnung Standort ↔ Gruppe (`department_grossanlass_place_group`)

Ein **Standort** (Punkt oder Polygon) ist eigenständig: eigene ID, Name, Koordinaten, Polygon und QR-Code. Gruppen (Ressort, Bereich, Bauprojekt) werden ihm **zugeordnet**.

- **Tabelle:** `place_id`, `group_id`, Primärschlüssel `(place_id, group_id)`, Fremdschlüssel mit `ON DELETE CASCADE`, normaler Index auf `group_id` (kein Unique, `Version20261014090000`). Ein Standort darf mehreren Gruppen gehören und eine Gruppe mehreren Standorten: **ein Polygon plus beliebig viele Punkte**, auch bei Aufträgen und Bauprojekten.
- **Hauptstandort:** `findForGroup()` und das Feld `place` der Gruppen-API liefern den Hauptstandort: das Polygon, sonst die älteste Zuordnung. `places` enthält alle Standorte, Hauptstandort zuerst. `place_id` in der Gruppen-API ersetzt weiterhin nur den Hauptstandort gleicher Art; zusätzliche Punkte laufen über `POST /groups/{id}/places` (`place_id` oder `name`, `latitude`, `longitude`), `PATCH`/`DELETE /groups/{id}/places/{placeId}` (Berechtigung wie Gruppe bearbeiten; gemeinsam genutzte Punkte nur mit Recht auf die Standortverwaltung). Punkte ändern nie das Polygon; Entkoppeln löscht nie einen Standort.
- **Entkoppeln** entfernt ausschliesslich die Zuordnung. Standort, Name, Koordinaten, Polygon und QR-Code bleiben erhalten. Löschen geschieht nur in der Standortverwaltung (und nur ohne Blocker).
- **Name:** Der Standortname gehört dem Standort. Der Gruppenname ist nur der Vorschlag beim erstmaligen Anlegen; Gruppen-Umbenennungen ändern ihn nie. Eine Umbenennung ist eine bewusste Aktion (Feld «Standortname» im Karteneditor bzw. Standortverwaltung).
- **Punkt oder Bereich** wählt der Benutzer frei (`place_kind`: `point` | `area`), unabhängig vom Gruppentyp.
- **API:** Orte liefern `group_ids` (alle Gruppen). `group_id` ist nur ein **Übergangswert**: gesetzt, wenn der Standort genau einer Gruppe gehört, sonst `null` (nie eine beliebig gewählte Gruppe). Schreiben akzeptiert `group_ids` (ersetzt die Zuordnung); das alte `group_id` wird nur bei höchstens einer Zuordnung akzeptiert. Gruppen-API: `place_id`, `place_kind`, `place_name`.
- **Berechtigungen:** Eine Gruppenberechtigung erlaubt, die eigene Zuordnung zu setzen oder zu lösen. Wird ein Standort von mehreren Gruppen genutzt, sind Änderungen an Name, Typ oder Polygon nur mit dem Recht auf die Standortverwaltung (Anlassübersicht / Einsatz-Meldung) möglich; die Oberfläche weist darauf hin, dass sie für alle Gruppen gelten.
- **Übergangsspalte:** `department_grossanlass_place.group_id` ist **nicht mehr führend**. Sie wird vom `GrossanlassPlaceService` nur gespiegelt (genau eine Gruppe = Wert, sonst `NULL`) und kann in einer späteren Migration entfernt werden, sobald kein Rollback auf den alten Stand mehr nötig ist. Lesen darf niemand mehr aus ihr (`DepartmentGrossanlassPlace::getLegacyGroupId()` ist `@deprecated`).
- **Migration:** `Version20261013090000` legt die Tabelle an und übernimmt alle bisherigen Zuordnungen verlustfrei (wiederholbar); `down()` stellt die Spiegelspalte wieder her.

### 3.1a Eine Karte (`department_grossanlass_map` + Stammdaten-Leaflet)

OSM/Swissimage ist für Helfer zu voll — Punkte verschwimmen. **Eine Leaflet-Karte** (Eventstandort blau, Zustellpunkt orange) plus **GA-Orte**. Unter **Stammdaten** nur die wichtigen: Eventstandort, Zustellpunkt, mit Stern markierte Orte (Abladezone, Anfahrt). Unter **Standorte** alle Pins, inkl. Bauprojekt-Standort für den Bauauftrag. Optional liegt ein hochgeladener **Geländeplan als Overlay** über dem Ausschnitt (Bounds am Map-Datensatz). Helferdruck nutzt den Plan; die Pins sind dieselben GA-Orte, kein zweites Marker-Objekt.

```
Stammdaten-Karte (WGS84) — nur die wichtigen
  Eventstandort · Zustellpunkt · GA-Orte mit Stern (z. B. Abladezone)
Tab Standorte — alle Pins, inkl. Bauprojekt
  └── Stern am GA-Ort = erscheint auf Stammdaten; Rest intern
  └── optional Overlay = eigener Hintergrund
```

GA-Ort: `latitude` / `longitude` auf der Leaflet-Karte. Liegt ein Overlay mit Bounds, werden `map_id` + `map_x` / `map_y` (0–1 auf dem Bild) mitgeführt — für Druck und Scan-Ausschnitt.

Nicht: zweite Bild-Karte neben Stammdaten, nicht `Address.event_poi` als Pack-Ziel, nicht Lager-QR. Punkte = GA-Orte.

### 3.2 Bauprojekt

Knoten im Ressort-Baum (habt ihr). Neu: **grobes Zeitfenster** am Projekt (Aufbau Fr–So). Das ist Projektzeit, keine Doppelbuchung. Einsätze liegen **in** diesem Fenster.

Ziel der Buchung: **ein** Knoten (`group_id`) — Ressort oder Bauprojekt. Eltern-Ressort folgt aus dem Baum. Crew aus einem anderen Ressort = Zuweisung an der Aufgabe, nicht zweites Materialziel.

### 3.3 Zwei Listen am Projekt

| | **Aufgaben** | **Material** |
| --- | --- | --- |
| Inhalt | Was die Crew tut | Was dort liegen muss |
| Speichern | Arbeitspaket am Bauprojekt | **Wunschzeile** auf diesem `group_id` |
| Wünsche & Ideen | nein | ja, dieselbe Liste |
| Bedarf bündeln | nein | ja |
| Einsatz | nein | ja, sobald Bestand/Zusage da ist |
| Helferdruck | Checkliste | Mitnahmeliste / Soll vor Ort |

Normalweg: Material am Projekt erfassen → Wunsch (Zeitraum aus Projektfenster vorausgefüllt). Einsatz-Tool ist nicht die Einkaufsliste. Lücke: Einsatz ohne `wish_line_id` später explizit «als Wunsch übernehmen» — nicht automatisch bei jeder Buchung.

### 3.4 Einsatz und Fahrauftrag (unverändert zentral)

Kette aus [rollen-postfach-fahrten.md §7](./rollen-postfach-fahrten.md#7-einsatz-aus-wunsch--fahrt-ist-eine-checkbox):

```
Einsatz (Fahrt) → Pack-QR /i/k/… → unterwegs → GA-Ort-QR /i/ga/… → da
```

Kein paralleles Trip-Objekt für «Helfer hinschicken». Helfer zu Fuss: gedruckter Auftrag + Ort-QR. Chauffeur: bestehender Fahrauftrag.

---

### 3.5 Freigaberegel der Aufgabenübersicht (`/{departmentId}/ga/tasks`)

Die Aufgabenübersicht ist die operative Ansicht und zeigt nur freigegebene Arbeiten und Fahraufträge. Die Freigabe wird im Backend entschieden, nicht aus UI-Bezeichnungen abgeleitet:

- **Arbeit** (`DepartmentGrossanlassTask`): freigegeben, wenn der Bauauftrag (`Group::isBuildReleased()`) den Status `build`, `use`, `teardown` oder `done` hat. Fehlender, `planned`-, `quoted`- oder `aborted`-Status gilt nicht als freigegeben. Die Aufgabe trägt das Feld `released`.
- **Fahrauftrag** (`DepartmentGrossanlassEinsatz::isReleasedForExecution()`): freigegeben bei Status `issued`/`returned` oder gesetztem «Fahrt frei», nie bei `pending_approval`. Die Übersichtszeilen tragen das Feld `released`.
- **Durchsetzung:** Für Helfer (`GrossanlassAccessService::seesOnlyReleasedWork`) filtern `GET …/groups/tasks`, `GET …/groups/{id}/tasks`, `GET …/uebersicht/meine-einsaetze` und `GET …/uebersicht/scan-context` nicht freigegebene Einträge heraus; einzelne Aufgaben eines nicht freigegebenen Bauauftrags liefern «nicht gefunden». Planungsrollen (`mw`, `cmw`, Anlassübersicht) erhalten weiterhin alle Status; die Aufgabenübersicht filtert zusätzlich im Frontend auf `released`.
- Freigegebene Einträge öffnen weiterhin im bestehenden Aufgaben- und Fahrauftragsdialog.

## 4. Ein Scan, zwei Bedeutungen

Dieselbe Ort-Seite (`PublicGrossanlassPlaceView`):

1. Pack aktiv (Fahrauftrag) → **Ankommen** wie heute (`scanArrive`, falscher QR = Abbruch)
2. Eingeloggt / Auftrag offen → **dieses Bauprojekt:** Aufgaben, Soll-Material (Wünsche), Packs am Ort

Helfer zu Fuss: nur 2. Chauffeur mit Palette: 1, danach dieselbe Liste. Ein Codepfad, Seite erweitern statt fork.

Drei QR-Sorten, keine vierte:

| QR | Bedeutung |
| --- | --- |
| **GA-Ort** `/i/ga/` | Standort im Event |
| **Pack** `/i/k/` | Palette auf der Fahrt |
| **User-Karte** `/i/c/` | Helfer, Ausgabe, Fahren |

---

## 5. Helferauftrag (Druck)

Ein Blatt / eine Ansicht pro Bauprojekt (oder Schicht), **zusammengesetzt** aus zentralen Objekten:

1. Ausschnitt der **Karte** (Geländeplan-Overlay wenn vorhanden) mit diesem Punkt
2. Standort-QR (`/i/ga/…`)
3. Aufgaben
4. Material (Soll, später Ist aus Einsätzen/Packs)

Kein neues Etiketten-System. `PublicQrTag` + bestehende Druckkorb-Muster. Druckkarte = Geländeplan-Overlay wenn vorhanden, sonst Stammdaten-Karte, nicht OSM als Helfer-Hintergrund.

---

## 6. Ablauf

```
Bauprojekt anlegen + grober Termin + GA-Ort (kind=bauprojekt) + QR
    ├─ Aufgaben: Arbeit für die Crew
    └─ Material: Wunschzeilen (in Wünsche sichtbar)
            ↓
       Bedarf bündeln
            ↓
       Zusage / Lager
            ↓
       Einsätze im Projektfenster (Konflikte, Fahrt, Pack)
            ↓
       Druck: Karte + QR + Aufgaben + Material
            ↓
       Helfer vor Ort, Ort-QR scannen
```

Der Ablauf oben ist Ist, soweit P1–P8 abgehakt sind. Bauvorhaben-Status, Polygon, Teilen, Fahrzeugwunsch und die Aufgabenfelder Start/Dauer/Beschrieb/Verantwortliche stehen in [README §0](./README.md#0-ist-stand), nicht in der Phasenliste.

---

## 7. Bewusst nicht

- Bauauftrag-QR neben dem GA-Ort
- Zweite Fahrauftrag-Tabelle
- Material als Einsatz **und** als Wunsch pflegen
- Wünsche automatisch aus jedem Einsatz
- `Address.event_poi` oder Lager-QR als Event-Standort
- Lagerplatz manuell als Event-GA-Ort anlegen (das ist der gespiegelte Lagerstandort)
- OSM/Swissimage **ohne** Overlay als einzige Helfer-Geländekarte (Hintergrund bleibt das hochgeladene Bild, jetzt auf der Stammdaten-Karte)
- Aufgaben in den Bedarf-Pool

---

## 8. Phasen

- [x] **P0** Dieses Soll-Dokument; Verweise in README / Fahrten / QR-Schema
- [x] **P1** GA-Ort: `kind`, neue public_codes Prefix `ga`, QR `/i/ga/` + Alias `/i/p/`, UI Standorte vom Lager getrennt
- [x] **P2** Grobes Zeitfenster am Bauprojekt
- [x] **P3** Material am Projekt erfassen = Wunschzeile (in Wünsche + Bündeln)
- [x] **P4** Aufgaben am Bauprojekt
- [x] **P5** Ort-Scan-Seite: Ankommen + Aufgaben + Material (kein Fork)
- [x] **P6** Helferdruck aus Ort + Aufgaben + Wünsche
- [x] **P7** Eine Karte: Stammdaten-Leaflet mit Eventstandort, Zustellpunkt und GA-Orten; optional Geländeplan als Overlay
- [x] **P8** Einsatz aus dem Bauprojekt im bestehenden Einsatz-Tool (Fenster + GA-Ort vorausgefüllt)

Umsetzung: bestehende `GrossanlassPlaceService` / Pack / Wunsch erweitern — keine Parallelmodule.


### 3.1c Helferbedarf (`department_grossanlass_group_helper_need`)

Aufträge und Bauaufträge erfassen zuerst nur die **benötigte Anzahl Helfer**; optional Funktionen mit Anzahl (z. B. 2 Elektriker, 4 Aufbauhelfer). Zeile mit leerem `label` = Gesamtbedarf, weitere Zeilen = Funktionen. Funktionen **teilen** den Gesamtbedarf auf und werden nicht zusätzlich gezählt: der Gesamtbedarf ist mindestens die Summe der Funktionen. API: `GET`/`PUT /groups/{id}/helper-need`; das Briefing enthält `helper_need`. Der Bedarf ist getrennt von der **Einteilung** (Helferpool-Mitglieder der Gruppe mit `membership.is_helper` und Aufgabenzuweisungen, je Person einmal). Die Auftragskarten (`order-cards`, `staffing.need`/`staffing.assigned`) zeigen «eingeteilt / benötigt»; ohne erfassten Bedarf ist `need` `null`. Migration `Version20261014100000`.

**Helferbedarf je Aufgabe (`department_grossanlass_task_helper_need`, Migration `Version20261015090000`).** Der Bedarf wird in «Aufgaben & Material» bei jeder Aufgabe erfasst: Gesamtzahl, optional Funktionen mit Anzahl (gleiche Semantik wie oben, Primärschlüssel `(task_id, label)`). Aufgaben-API (`POST`/`PATCH /groups/{id}/tasks`) nimmt und liefert `helper_need`. Personen werden weiterhin erst später zugewiesen. Zeitlich getrennte Aufgaben werden **nicht addiert**: `GrossanlassBauprojektService::helperDemandOverTime()` liefert den Spitzenbedarf (Maximum der gleichzeitig laufenden Aufgaben; Aufgaben ohne Zeitblock zählen immer mit) und die Abschnitte als `timeline`; das Briefing und `GET /groups/{id}/helper-need` enthalten sie unter `from_tasks`. Die Auftragskarten (`staffing.need`) nutzen den Spitzenbedarf, sobald eine Aufgabe Bedarf hat. Der gruppenweite Bedarf (`group_helper_need`) bleibt als Altbestand erhalten und lesbar; unter `/ga/my-unit` wird er nicht mehr bearbeitet, sondern nur noch als Hinweis angezeigt, solange keine Aufgabe einen Bedarf hat. Auftrag und Bauauftrag werden unter `/ga/my-unit` und `/ga/planning/jobs` über denselben Dialog `GrossanlassOrderDialog` angelegt und bearbeitet (Reiter «Grunddaten» und «Aufgaben & Material»; Formularlogik in `utils/grossanlassOrderForm.ts`). Neue Aufträge erfassen keinen gruppenweiten Bedarf mehr.

**Gemeinsamer Dialog (`/ga/my-unit`).** «Bearbeiten» und «Aufgaben & Material» öffnen denselben Dialog mit den Reitern «Grunddaten» und «Aufgaben & Material» (`GrossanlassBauprojektPanel` mit `scope="tasks-material"`). Wer die Gruppe nicht bearbeiten darf, sieht nur den zweiten Reiter. Beide Reiter bleiben gemountet; beim Schliessen warnt der Dialog bei ungespeicherten Grunddaten oder Aufgaben/Material.

### 3.1d Helferpool V1: Personalbedarf, Zuteilung, Deckung (Migration `Version20261018090000`)

Stand: Datenmodell, Services, REST-API und Oberfläche ([§3.1e](#31e-helferpool-v1-rest-api-und-oberfläche)).

**Eine Datenquelle.** Der Personalbedarf liegt in `department_grossanlass_staffing_need` (Aufgabe, Funktionswunsch `label`, `required_count`, Zeitfenster `[starts_at, ends_at)`). `department_grossanlass_task_helper_need` und `department_grossanlass_group_helper_need` bleiben als Legacy bestehen (Tabellenkommentar), werden nicht mehr gelesen oder geschrieben. Die Migration übernahm sie wiederholbar und ohne Verdopplung: je Aufgabe eine Zeile je Funktion plus der Rest (`Gesamt − Σ Funktionen`) ohne `label`, Summe = `max(Gesamt, Σ Funktionen)`. Der alte Gruppenbedarf wurde nur übernommen, wenn keine Aufgabe der Gruppe Bedarf hatte (Standardaufgabe «Allgemein»). `membership.is_helper` wurde zu `department_grossanlass_helper` (mit `user_id`); `membership.role = h` wird nicht migriert.

**Bedarf.** Gehört immer zu einer Aufgabe. Bedarf am Bauprojekt selbst hängt an der lazy angelegten Standardaufgabe «Allgemein» (`task.is_default`, höchstens eine je Gruppe), die in der Aufgabenübersicht und über die Aufgaben-API nicht erscheint. Fenster = Aufgabenzeit, sonst Bauprojekt-Fenster (`window_end` inklusiv, intern exklusives Ende), sonst undatiert: ein undatierter Bedarf ist nicht zuteilbar. Die bisherige Eingabe «Gesamtzahl + Funktionen» (`helper_need` an Aufgabe und Gruppe) ist das *Bündel* der Bedarfe mit dem Fenster der Aufgabe; weitere Bedarfe mit eigenem Fenster bleiben davon unberührt. Ändern sich Aufgabenzeit oder Bauprojekt-Fenster, folgt das Bündel. Eine Änderung, die Zuteilungen aus dem Fenster schiebt, wird mit 409 `outside_need_window` abgelehnt.

**Helfer und Verfügbarkeit** (`GrossanlassHelperPoolService`). Helfer gehören zum GA-Host (`department_id`, optional `origin_department_id`), brauchen kein Login. Archivieren statt löschen. Das Profil (Migration `Version20261101090000`): Beruf (`occupation`), Ressort (`area`), Fähigkeiten (`skills`, Namen), Qualifikationen (`qualifications`: Bezeichnung, optional Gültigkeit) und für externe Helfer Führerausweis-/Bedienklassen (`drive_classes`, `drive_valid_until`, Werte aus `GrossanlassDriveCategories`). **Verknüpfte Helfer** (`user_id`, Konto muss Mitglied des GA sein; höchstens ein Helfer je Konto, keine doppelte E-Mail im GA) beziehen Name, E-Mail und Führerausweis aus dem zentralen Profil (`UserDriveLicense`, `drive_license.source = profile`, nur lesend; GA schreibt nie ins Profil). Fähigkeiten, Qualifikationen, Telefon, Notiz und Verfügbarkeit bleiben GA-lokal und werden beim Verknüpfen nicht überschrieben. Konten mit Helferrolle erscheinen über `GrossanlassHelperAccountSync` auch bei weiteren Rollen genau einmal; fällt die Rolle weg, wird ein vom Abgleich angelegter Eintrag (`source = api`) archiviert (Zuteilungshistorie bleibt); im Helferpool bewusst verknüpfte Helfer (`source = manual`/`import`) bleiben aktiv. Dasselbe Formular (`GrossanlassHelferFormDialog`, fünf Accordions) dient dem Helferpool und dem Unterreiter «Helfer» der GA-Benutzerverwaltung; Detail ist überall `GrossanlassHelferDrawer`. **Fähigkeitenkatalog** pro GA (`department_grossanlass_skill`, Präfix `sk`): Helferprofil und Funktionswunsch des Personalbedarfs wählen aus derselben Liste (Katalog plus bereits verwendete `label`); neue Namen werden beim Speichern aufgenommen. Fähigkeiten sind Wünsche, nie Voraussetzung der Zuteilung; im Zuteilungsdialog stehen passende Helfer oben. Der Funktionswunsch bleibt ein `label` des Bedarfs, der Gesamtbedarf wird dadurch nicht erhöht. Verfügbarkeit sind explizite Fenster pro Helfer, die sich nicht überschneiden dürfen (direkt anschliessende sind erlaubt). Eine Änderung, die eine aktive Zuteilung ungedeckt liesse, ergibt 409 `in_use`.

**Zuteilung** (`GrossanlassStaffingAssignmentService`). Ganz oder teilweise (eigenes Fenster im Bedarfsfenster, z. B. 08–10 und 10–12). Jeder Schreibweg läuft in einer Transaktion unter `SELECT … FOR UPDATE` auf dem Helfer (`GrossanlassStaffingLocks`) und prüft alle aktiven Zuteilungen des Helfers:

| Fall | Ergebnis |
| --- | --- |
| Keine Verfügbarkeit deckt das Fenster | 409 `no_availability`, nicht überschreibbar |
| Überschneidung mit anderer aktiver Zuteilung | 409 `double_booking`; mit `override_reason` (Begründung) zulässig, an der Zuteilung gespeichert (`override_*`) und im Protokoll `department_grossanlass_staffing_log` |
| Bedarf undatiert / Fenster ausserhalb | 409 `need_undated` / `outside_need_window` |
| Helfer archiviert | 409 `helper_archived` |
| Mehr Zuteilungen als Bedarf | erlaubt, Warnung `overbooked` |

Bewusst kein Exclusion Constraint und kein `btree_gist`: Ausnahmen müssen möglich bleiben, die Sperre pro Helfer serialisiert gleichzeitige Zuteilungen. `assignee_user_id` (Aufgabenverantwortliche) zählt nicht als Zuteilung. Fremdbelegung durch externe Quellen und API-Synchronisation sind nicht Teil von V1.

**Berechtigung.** Helferpool, Zuteilungen, Overrides, Konfliktliste und Zeitplan: `hv` (GA-HV) und `subhv` (GA-SUBHV) immer; `mw` (GA-MW) und `dc` (GA-OK = OK-Leitung) standardmässig, je abschaltbar über die zwei unabhängigen GA-Einstellungen `staffing_mw_allowed` / `staffing_ok_allowed` (`department_grossanlass_config`, Standard `true`, im Tab Stammdaten der Planung von MW/Co-MW änderbar). Zentral geprüft in `GrossanlassAccessService::canManageStaffing()` (Matrix `GrossanlassAccessRoles::canManageStaffing()`); alle anderen Rollen unverändert (`cmw` verwaltet nicht). Die Rollen vergibt die GA-Leitung im jeweiligen GA. `GET /staffing/overview` liefert `can_manage` für die UI. Personalbedarf anlegen und ändern folgt dem Bearbeitungsrecht am Bauprojekt; die Übersicht ohne Namen lesen zusätzlich `mw`/`cmw`/`dc` (`canSeeStaffingOverview()`).

**Deckung** (`GrossanlassStaffingCoverageService`, `GrossanlassStaffingTime`). Immer aus den tatsächlichen Zuteilungen berechnet, nie gespeichert: Abschnitte mit gleicher Besetzung, Lücken (`missing`), `fill_ratio` (gedeckte / benötigte Personenminuten), Überbesetzung. `overview()` liefert Bedarf, Deckung und Gruppensummen ohne Helfernamen (zwei Abfragen, keine Helfertabelle); Namen gibt es nur in `needDetail()` und `schedule()`. `conflicts()` meldet Doppelbuchung (`excused` bei begründeter Ausnahme), Zuteilung ausserhalb der Verfügbarkeit oder des Bedarfsfensters, Überbesetzung und undatierten Bedarf. Die Auftragskarten (`staffing.need`/`assigned`) nutzen den Spitzenbedarf der Bedarfe und die Zahl zugeteilter Helfer.

Tests: `tests/Service/Grossanlass/GrossanlassStaffingTimeTest.php` (rein) und `tests/Integration/GrossanlassStaffingIntegrationTest.php` (Datenbank mit `EMATCHEF_TEST_DB_URL`, braucht `php -d memory_limit=2G`).

### 3.1e Helferpool V1: REST-API und Oberfläche

Alle Endpunkte unter `/api/departments/{id}/grossanlass/` (`GrossanlassStaffingController`, nur Grossanlass-Departments, Mitgliedschaft nötig, nach der Ersteinrichtung wie alle GA-Endpunkte). Fehler: 400 ungültige Eingabe, 403 keine Berechtigung, 404 unbekannt, **409** fachlicher Konflikt mit `{"error", "code": "staffing_conflict", "conflict": {"kind", …}}`.

| Endpunkt | Wer | Zweck |
| --- | --- | --- |
| `GET/POST helpers`, `GET/PATCH helpers/{id}`, `POST helpers/{id}/archive`, `…/restore` | hv/subhv | Helfer (Query: `status` active/archived/all, `q`) |
| `GET/POST helper-skills` | Lesen: Mitglied, Anlegen: hv/subhv (+ mw/dc nach Freigabe) | Fähigkeitenkatalog (Liste von Namen) |
| `GET helper-linkable-users` | Verwaltung | Konten des GA für die Verknüpfung (`helper_id` = bereits verknüpft) |
| `GET/POST helpers/{id}/availability`, `PATCH/DELETE helper-availability/{id}` | hv/subhv | Verfügbarkeitsfenster `starts_at`/`ends_at` |
| `GET staffing/overview?group_ids=&from=&to=` | hv/subhv, mw/cmw/dc | Bedarf, Deckung, Gruppensummen **ohne Helfernamen** |
| `GET staffing/needs/{id}` | hv/subhv | Bedarf mit zugeteilten Helfern (Namen) |
| `GET/POST groups/{gid}/staffing-needs`, `PATCH/DELETE …/{needId}` | Bearbeitungsrecht am Bauprojekt (Lesen: Sichtrecht) | Personalbedarf anlegen/ändern; ohne `task_id` an der Standardaufgabe «Allgemein» |
| `POST staffing/assignments`, `PATCH/DELETE staffing/assignments/{id}` | hv/subhv | Zuteilen (`need_id`, `helper_id`, optional `starts_at`/`ends_at`, `override_reason`), Fenster ändern (`version` optional), stornieren |
| `GET staffing/schedule?from=&to=`, `GET staffing/conflicts` | hv/subhv | Zeitplan mit Namen, Konfliktliste |

Konflikt-`kind`: `double_booking` (mit `override_possible`; Wiederholung mit `override_reason` speichert eine protokollierte Ausnahme), `no_availability`, `outside_need_window`, `need_undated`, `helper_archived`, `in_use`, `stale`. Antworten von Zuteilen/Ändern: `{assignment, warnings[]}` mit Warnungen `overbooked` und `double_booking_override`.

**Ablauf.** (1) GA-HV/GA-SUBHV (oder GA-MW/GA-OK, sofern freigegeben) erfasst Helfer und deren Verfügbarkeit im Drawer. (2) Der Personalbedarf entsteht bei den Aufgaben und Bauprojekten (Zeitfenster der Aufgabe oder des Bauprojekts). (3) Im Tab «Personalbedarf» «Helfer zuteilen»: Helfer wählen, Fenster bei Teilzeit kürzen. (4) Bei Doppelbuchung zeigt der Dialog die Überschneidung und verlangt eine Begründung, fehlende Verfügbarkeit lässt sich nicht übergehen. (5) Die Deckung rechnet der Server aus den Zuteilungen; Lücken stehen je Zeitabschnitt am Bedarf. Das Frontend nutzt `api/grossanlassStaffing.ts` (Axios-`apiClient`), Rollenprüfung `gaCanManageStaffing()`/`gaCanSeeStaffing()` in `utils/grossanlassAccess.ts` (Tabs und Aktionen der Helferpool-Ansicht folgen `can_manage` aus der Übersicht); Router und Sidebar zeigen den Helferpool für `hv`, `subhv`, `mw`, `cmw`, `dc` (Bereichsleitung nicht mehr). Tests: `backend/tests/Integration/GrossanlassStaffingApiTest.php`, `frontend/src/api/grossanlassStaffing.spec.ts`, `…/helferpool/gaHelferUi.spec.ts`.

**Lokaler Migrationsstatus:** «Executed Unavailable» (3) betrifft die in `mvdb` angewendeten Versionen `20260805120000`, `20260813190000` und `20260813210000`, deren Dateien in diesem Branch fehlen (von einem anderen Stand ausgeführt). Sie sind für diese Arbeit ohne Wirkung.

**Helferrolle → Helferpool.** `GrossanlassHelperAccountSync` gleicht aktive Konten mit Helferrolle (`membership.is_helper` oder Rolle `h`, auch neben anderen Rollen) mit dem Pool ab: beim Lesen der Poolliste und bei Mitgliedschaftsänderungen. Fehlende Konten werden angelegt (`source=api`, `user_id`), ein Eintrag ohne Konto mit gleicher E-Mail wird verknüpft statt dupliziert, Wegfall der Rolle archiviert (Verfügbarkeit, Zuteilungen und Historie bleiben; neue Zuteilung gesperrt). Erneute Rollenvergabe über die Benutzerverwaltung reaktiviert; reines Lesen reaktiviert nie. Externe Helfer ohne Konto bleiben unberührt.

## 9. API-Daten (externe Helferpools)

Reiter «API-Daten» im Helferpool (nur GA-HV/GA-SUBHV bzw. wer den Helferpool verwalten darf). Controller `GrossanlassExternalSourceController` unter `/api/departments/{id}/grossanlass/external-sources`; Services `GrossanlassExternalSourceService` (Verbindung, Originalantworten, Mapping), `GrossanlassExternalSyncService` (Vorschau und Synchronisation), reine Logik in `GrossanlassExternalMapping`, HTTP in `GrossanlassExternalHttpFetcher`. Migration `Version20261102090000`.

- **Keine erfundene Schnittstelle.** Es gibt kein fest verdrahtetes Mission-Control-Format. Jede Quelle hat eine frei konfigurierbare Zuordnung (Punktpfade wie `person.name` je Zielfeld). Ohne API-Zugang wird eine JSON-Testantwort eingespielt.
- **Zugangsschlüssel** liegen mit `SecretBox` (AES-256-GCM) verschlüsselt in `secret_enc`; das API gibt nur `has_secret` zurück. Abruf: nur https, keine Weiterleitungen, keine privaten Netze, 5 MB / 30 s.
- **Speicherung:** `ext_payload` (Original-JSON je Abruf, identische Antworten nur als erneut gesehen), `ext_record` (je Quelle und externer Buchungs-ID genau ein Datensatz mit letztem Zustand, erstem Import, letzter Synchronisation, Helfer), `ext_change` (alter/neuer Wert), `ext_run` (Historie mit Zählern und Fehlern, ohne Zugangsdaten).
- **Helferpool:** pro externem Helferschlüssel (Quelle + `helper_key`, sonst E-Mail innerhalb der Quelle) ein Helfer mit `source = api`, nie per Namen zusammengeführt; eine gleiche E-Mail eines anderen Helfers wird nicht übernommen (Hinweis). Jede aktive Buchung ist ein Verfügbarkeitsfenster (`ext_record_id`).
- **Konflikte:** Wird eine Buchung verschoben, storniert oder entfernt, werden Zuteilungen des Helfers, die nicht mehr in seiner Verfügbarkeit liegen, auf Status `conflict` gesetzt (Protokolleintrag `conflict`). Sie zählen nicht zur Deckung, erscheinen als Konflikt `external_booking_invalid` und lösen sich beim Wiederpassen (`resolved`) oder durch Neuzuteilung.
- **Entfernte Buchungen** nur, wenn die Quelle als vollständig bestätigt ist, `total_path`/`next_path` nicht widersprechen, keine ungültigen Einträge vorliegen und die Antwort nicht leer ist.
- **Vorschau** führt dieselbe Synchronisation in einer zurückgerollten Transaktion aus. Eine Synchronisation läuft atomar; bei Fehlern bleiben die bestehenden Daten unverändert, nur der fehlgeschlagene Lauf wird festgehalten.
- Bewusst nicht: Veröffentlichen von eMatChef-Aufträgen in der Quelle, Ändern externer Schichten, mehrseitiger Abruf (Paginierung), automatischer Zeitplan.
