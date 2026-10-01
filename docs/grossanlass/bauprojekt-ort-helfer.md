# Grossanlass — Bauprojekt, GA-Ort, Aufgaben & Material

Produktklärung 18.09.2026. **Dieses Dokument gilt** für Projektplanung am Bauprojekt, Event-Standorte und den Helferauftrag. Ergänzt [20260823_New_concept.md](./20260823_New_concept.md) §12.3 (Einsatz) und [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) (Fahrauftrag, Pack, Standort-QR).

**Status:** Soll — Phasen in [§8](#8-phasen) abhaken.

**Verwandt:** [README.md](./README.md) · [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) · [qr/link-schema.md](../qr/link-schema.md)

---

## 1. Satz Zielbild

Ein **Bauprojekt** (Bühne, Wasserstelle) ist Ort + grobes Zeitfenster + zwei Listen: **Aufgaben** (Arbeit) und **Material** (Bedarf). Materialzeilen **sind Wünsche** — sichtbar unter Wünsche & Ideen, mitzählend beim Bündeln (Gator Bühne + Gator Sicherheit = eine Position). **Einsatz** reserviert vorhandenes Material im Projektfenster. **Fahrauftrag** fährt gepacktes Material zum **selben GA-Ort-QR**. Helfer bekommen einen Druck: Karte, Standort-QR, Aufgaben, Material. Kein zweites QR-System, keine zweite Fahrt-Tabelle, kein doppeltes Bündeln.

---

## 2. Was nicht vermischen

| Welt | Gegenstand | QR |
| --- | --- | --- |
| **Lager** | **Lagerstandort** (Matplatz), Regal, Fach | `/i/l/`, `/i/r/`, `/i/s/` |
| **Adresse** | Event, Zustellpunkt, `event_poi` in Kontakten | kein Pack-Ziel |
| **GA-Ort** | Standorte **im** Anlass | kanonisch `/i/ga/{code}`, Alias `/i/p/{code}` |

**Matplatz = Lagerstandort** (`Address` `type=storage`), nichts Neues und kein GA-Ort-`kind`. Ausgabe und Selbstabholung laufen über diesen bestehenden Standort. Fahrauftrag, Helferauftrag und Bauprojekt zielen nur auf **GA-Ort**. Lager-QR und Kontakt-POI nicht als Bühnen-Schild verwenden.

`kind: order` am Einsatz bleibt **Nachbedarf**, nicht Bauauftrag und nicht Fahrt.

---

## 3. Objekte

```
GA-Ort ─────────────────┬── kind (bauprojekt | unterlager | anfahrt | poi)
  QR /i/ga/{code}       ├── group_id? → Bauprojekt / Ressort
                        ├── unterlager_id?
                        └── Position: WGS84; Stern = auf Stammdaten; map_x / map_y auf dem Geländeplan

Lagerstandort (Matplatz)  Address type=storage · QR /i/l/ sichtbar am Standort — kein GA-Ort

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
| `bauprojekt` | Bühne, Wasserstelle | `group_id` auf Group `grossanlass_kind = teilbereich` |
| `unterlager` | Region Ost | `unterlager_id` (Ist: Seed aus Unterlager) |
| `anfahrt` | Anfahrpunkt / Tor für Fahrten | Ziel-Ort für Fahrauftrag |
| `poi` | Tor, Infopoint | ohne Projekt |

Kein `kind: matplatz`. Zentraler Materialplatz = **Lagerstandort** unter Standorte (bereits da). Alte GA-Orte mit diesem kind bleiben lesbar, neue legt ihr nicht an.

**IDs:** intern 12-stellig wie bisher (`pl…` bleibt gültig). **Neue public_codes** Prefix `ga` (12 Zeichen). Lookup akzeptiert alte `pl…`-Codes.

**QR**

| | Pfad | Lookup |
| --- | --- | --- |
| Kanonisch (neu drucken) | `/i/ga/{code}` | `GET /api/public/lookup/ga/{code}` |
| Alias (gedruckte Schilder) | `/i/p/{code}` | `GET /api/public/lookup/p/{code}` |

`entity_type` bleibt `ga_place`. Scanner-Typ `ga_place` für beide Pfade. Pack-Ankommen (`scanArrive`) ändert sich nicht — Ziel ist weiterhin `destination_place_id`.

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

Heute ohne die neuen Felder: Projekt anlegen, Wünsche im Formular auf das Projekt, Einsätze danach — kein Arbeitspaket, kein Helferblatt.

---

## 7. Bewusst nicht

- Bauauftrag-QR neben dem GA-Ort
- Zweite Fahrauftrag-Tabelle
- Material als Einsatz **und** als Wunsch pflegen
- Wünsche automatisch aus jedem Einsatz
- `Address.event_poi` oder Lager-QR als Event-Standort
- Matplatz als zweiten GA-Ort-Typ (das ist der Lagerstandort)
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
