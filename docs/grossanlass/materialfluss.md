# Grossanlass — Materialfluss

Zentrale fachliche Spezifikation für den Grossanlass-Materialfluss. Diese Seite beschreibt den fachlichen End-to-End-Prozess für Herkunft, Eigentum, Übergabe, Wareneingang, Bestand, Bauprojektzuweisung, Rückbau und Rückgabe. Sie ist die fachliche Quelle für den Grossanlass und kein Verweis auf den normalen Department-/Activity-Materialfluss.

**Abgrenzung:** [../activities/material-pipeline.md](../activities/material-pipeline.md) beschreibt den regulären Department-/Activity-Prozess. Ein normales Department oder Camp kann Material an einen Grossanlass übergeben; das ist eine definierte Übergabe zwischen zwei Produktwelten und keine gemeinsame Materialpipeline. Der Grossanlass muss Herkunft, Eigentümer, übergebene Menge, aktuellen Zustand und spätere Rückgabe separat erfassen.

**Stand:** 5. Oktober 2026, abgeglichen mit dem Code (Audit Materialfluss).

**Leseregel:** **IST** ist im Code belegt. **PARTIAL** heisst: ein Teil ist gebaut, der Rest fehlt (jeweils benannt). **SOLL** ist nicht gebaut. Technische Namen in `code` beschreiben den heutigen Code; SOLL-Abschnitte legen keine Tabellen- oder Feldnamen fest.

---

## 1. Ziel und Leitprinzipien

Der Grossanlass hat einen eigenen fachlichen Materialprozess. Die zentrale Frage ist nicht nur «wo liegt das Material?», sondern auch:

- Woher kommt es?
- Wem gehört es?
- Wie viel wurde übergeben?
- Was ist derzeit physisch beim Grossanlass?
- Was ist reserviert, zugewiesen, unterwegs, vor Ort oder zurückzunehmen?
- Was muss später an den Eigentümer, ein Department, eine Firma oder die Werkstatt zurück?

Wichtige Grundsätze:

- Herkunft, Eigentum und aktueller Standort werden getrennt betrachtet.
- Mengen werden mengenbasiert dokumentiert; ein einzelner Gesamtstatus ersetzt nicht mehrere Teilmengen-Zustände.
- Teilmengen sind ausdrücklich erlaubt (z. B. Pack 1 + Pack 2, Teilrückgabe, Rückgabe an Firma).
- Die Übergabe von Department/Camp an Grossanlass ist eine Leihe/Übergabe, nicht dieselbe Welt wie der normale Materialfluss im Department.
- Der bereitstellende Department muss erkennen können, welche Menge dem Grossanlass übergeben wurde und welche Menge noch nicht zurückgegeben ist.
- Bestehende Strukturen (Charge, Einsatz, Pack, GuestShare, Druck) werden erweitert. Keine zweite Bestands-, Fahrt-, Wareneingangs- oder Übergabewelt.

---

## 2. Kernmodell: Artikel → Charge → Einsatz → Pack → Ort (IST)

Die physisch relevante Bestandseinheit im Grossanlass ist heute die **Zusage** `DepartmentGrossanlassCommitment`. In der UI heisst sie **Charge** (`frontend/src/views/grossanlass/gaCharge.ts`). Der gesamte GA-Bestand (Materialübersicht → Bestand: Eigen, Leihweise, Gäste) besteht aus Chargen. `MaterialItem` des Grossanlass-Departments wird im GA-Materialfluss nicht verwendet.

```
Artikel            Gruppierung von Chargen (UI), kein eigener Datensatz
  → Charge         department_grossanlass_commitment — Menge, Herkunft, Zeitfenster
    → Einsatz      department_grossanlass_einsatz — bucht Menge einer Charge für Zeitraum/Ressort
      → Pack       department_grossanlass_pack (+ _pack_line) — Transportidentität mit QR
        → Ort      department_grossanlass_place — GA-Ort, Matplatz (Lager), Unterlager
```

### 2.1 Charge (IST)

Eine Charge hat heute unter anderem:

| Feld | Bedeutung |
| --- | --- |
| `quantity` | Menge der Charge |
| `origin` | Herkunft: `loan` \| `buy` \| `buy_resale` \| `own` (GA-Bestand) \| `donation` (Schenkung / Sponsoring / Sachleistung, bleibt beim GA) |
| `return_required` | Rückgabepflicht, explizit; Vorgabe aus der Herkunft (Leihe = ja), bei Kauf aus der gewählten Offerte |
| `owner_kind`, `owner_department_id` | Eigentümer getrennt von der Herkunft: `grossanlass` \| `external` \| `department` (dann Relation zum Department) |
| `procurement_line_id` | Relation zur Bedarfsposition |
| `source` | Firma / Herkunft als Anzeigetext |
| `family` | `material` \| `vehicle` |
| `present_from/to`, `handover_from/to`, `return_from/to`, `wish_from/to` | Zeitfenster (vor Ort, Übergabe, Rückgabe, Wunsch) |
| `barcode` | `ZS-…`, beim Anlegen automatisch gesetzt |
| `inquiry_id` | Bezug zur Anfrage |
| `item_details` (JSON) | u. a. `from_line_id` (bleibt für bestehende Leser; die Relation ist `procurement_line_id`), `quote_id`, `order_id`, `order_ref`, `inbound_status` (vom Server aus den Bewegungen abgeleitet), `inbound_mode` (`pickup` \| `delivery`), `pickup_einsatz_id`, `delivery_einsatz_id`, `qty_checked`, `parts` |
| `packed`, `returned_to_firm` | Booleans für Pack und Retour an Firma, jeweils für die ganze Charge |

Chargen entstehen aus einer Anfrage (`…/zusagen/from-inquiry/{id}`, `origin=loan`), aus einer Bestellung (Kauf-Charge mit `origin=buy`, eine pro Bedarfsposition, Rückgabepflicht aus der Offerte), manuell oder aus einer übernommenen Department-Freigabe ([§9](#9-department--camp--grossanlass), Eigentümer = Department).

Die Charge-Menge ist die erwartete Menge. Sie lässt sich nicht unter die bereits erhaltene Menge senken. Eingang, Fehlmenge und Eingangsstand kommen aus den Charge-Bewegungen ([§14](#14-charge-bewegungen)).

### 2.2 Artikel (IST)

Ein Artikel ist eine **Gruppierung** von Chargen im Frontend (`commitmentStemKey`: gleiche Bedarfsposition `from_line_id`, sonst gleicher Name). Ein Artikel ist kein einzelnes physisches Objekt und kein eigener Datensatz.

### 2.3 Einsatz (IST)

Ein Einsatz bucht eine Menge (`qty`) **einer** Charge (`commitment_id`) für einen Zeitraum, ein Ressort/Bauprojekt (`group_id`) und optional einen Wunsch (`wish_line_id`). Er ist die Reservierung/Zuweisung. Zustellung: `delivery` = `trip` (Fahrt) oder `pickup` (Selbst abholen). Status: `planned`, `pending_approval`, `issued`, `returned`. Details: [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md).

### 2.4 Pack / Palette (IST)

Ein Pack gehört zu genau einem Einsatz und hat einen eigenen QR (`/i/k/{code}`). Pack-Zeilen tragen `commitment_id`, `qty_needed` und `qty_packed`. Mehrere Packs pro Einsatz und Teilpack sind möglich ([§7](#7-einsatz-pack-teilpack-transport)).

### 2.5 Abgrenzungen

- **Charge ist nicht Pack.** Die Charge ist Bestand mit Herkunft; der Pack ist die Transportidentität für eine Bewegung.
- **Artikel ist nicht zwingend ein einzelnes Objekt.** Er gruppiert Chargen.
- **Mengenware:** eine Charge mit Menge > 1 (z. B. 100 Schrauben).
- **Unikat:** eine Charge mit Menge 1. Die Verfügbarkeitsregel ([§7.4](#74-verfügbarkeit-und-überbuchungsschutz-ist)) behandelt Chargen mit Menge ≤ 1 und Fahrzeuge als Unikat: nie zwei Einsätze gleichzeitig.
- **Gebinde / Charge im engeren Sinn:** z. B. 20 Holzbalken als eine Charge oder pro Gebinde eine Charge.

---

## 3. Fachlicher End-to-End-Ablauf

```
Bauprojekt / Ressort
→ Materialbedarf erfassen
→ festlegen, wer beschafft:
  - Ressort / Bauprojekt selbst
  - Material & Logistik
→ bei zentraler Beschaffung: Bedarfe bündeln
→ vorhandene Deckung berücksichtigen
→ offene Menge bestimmen
→ Firmen / Kontakte anfragen
→ Antworten / Offerten erfassen
→ prüfen, ob die angebotene Menge den Bedarf vollständig oder teilweise deckt
→ Zusage auswählen (= Charge)
→ Wareneingang / physische Annahme
→ Bestand beim Grossanlass
→ zuweisen an Bauprojekt / Einsatz
→ Pack / Transport / Selbstabholung / GA-Ort
→ Einsatz / Nutzung vor Ort
→ Rückbau
→ Teilrückgaben / Rücknahme / Abschlusswege
```

Der Ablauf ist ein Grossanlass-Prozess und nicht die normale Activity-Materialpipeline.

---

## 4. Bedarf, Bündelung und Beschaffung

### 4.1 Bedarfserfassung (IST)

Materialwunsch (`activity_grossanlass_wish_line`): Menge, Einheit (`quantity_unit`), Ressort/Bauprojekt, Zeitraum (`valid_from/to`), Ort als Freitext, `self_organized`, `enough_on_hand` (Herkunft `stock` oder `commitment`), Abholung, Zurückbringen. Details: [README §0.4](./README.md#04-wünsche).

**SOLL:** Priorität; GA-Ort statt Freitext-Ort; Eigenbestand als Teilmenge (heute ist `enough_on_hand` ein Boolean für die ganze Menge).

### 4.2 Wer beschafft? (IST)

- Ressort / Bauprojekt selbst: `self_organized` am Wunsch oder Direkt-Bedarf mit `can_procure`
- Material & Logistik: Bedarfsposition (`activity_grossanlass_procurement_line`), Bündelung mehrerer Wünsche

### 4.3 Deckung und offene Menge (IST)

**IST:** «gedeckt» und «offen» pro Wunsch berechnet der Materialfortschritt ([§7.3](#73-mengenbasierter-materialfortschritt-ist)) aus den Chargen der Bedarfsposition (auf die gebündelten Wünsche verteilt) und den Einsätzen auf andere Chargen, ohne Doppelzählung. Zusätzlich setzt der Server, solange die Bedarfsposition im Status `bedarf` ist, ihre Menge auf «Wunschmenge − gebuchte Einsatzmengen», und die Beschaffung summiert Leih- und Kauf-Chargen je Bedarfsposition.

Beispiel:

```
100 m Bauholz benötigt

- 30 m GA-/Eigenbestand
- 20 m von Department A
- 25 m Firma X als Leihe
- 15 m Firma Y als Kauf
- 10 m weiterhin offen
```

Darstellbar als je eine Charge pro Quelle und je ein Einsatz pro Deckung; die offenen 10 m ergeben sich. Einschränkungen heute:

- Eigenbestand als Charge mit Herkunft `own` erfassen ([§11](#11-herkunft-und-eigentum)); der Wunsch selbst kennt Eigenbestand nur als Ganzes (`enough_on_hand`).
- Eine Bedarfsposition hat genau eine gewählte Offerte und eine Bestellung, also höchstens **eine Kaufquelle**. Mehrere Kaufquellen brauchen mehrere Bedarfspositionen.
- Department-Material kommt nur über die Department-Freigabe als Leih-Charge ([§9](#9-department--camp--grossanlass)).
- Nach Status `bedarf` wird die offene Menge nicht mehr automatisch nachgeführt.

**SOLL:** Deckung und offene Menge berechnen, nicht als Status speichern.

### 4.4 Anfrage, Offerte, Zusage, Bestellung (IST)

| Schritt | IST |
| --- | --- |
| Anfrage | `department_grossanlass_inquiry`: Kanal, Positionen, Status, Gmail-Thread, Ort |
| Offerte | `activity_grossanlass_procurement_quote`: Betrag, Liefertermin, Lieferzeit, `inbound_mode` (`pickup` \| `delivery`), `return_needed`, `return_at`, Lieferantenadresse |
| Zusage | Charge ([§2.1](#21-charge-ist)) |
| Bestellung | `activity_grossanlass_procurement_order`: Kosten, Referenz, Liefertermin; eine pro Bedarfsposition; erzeugt die Kauf-Charge |
| Kosten | `department_grossanlass_cost`: `purchase`, `rental`, `loan` (Sachleistung, 0 CHF), `buy_resale`, `ancillary` — [kosten.md](./kosten.md) |

**SOLL:** angebotene Menge an der Offerte; bestellte Menge an der Bestellung (die Kauf-Charge übernimmt heute die Menge der Bedarfsposition); Rückgabepflicht an der Charge statt nur an der Offerte.

---

## 5. Abholung bei Firma / Lieferung (PARTIAL)

**IST:** Aus dem Wareneingang lässt sich pro Charge ein Abhol- oder Liefer-Einsatz anlegen (`frontend/src/views/grossanlass/gaPickupEinsatz.ts`). Er wird an der Charge als `item_details.pickup_einsatz_id` bzw. `delivery_einsatz_id` gespeichert, steht damit im Kalender und unter Planung → Abholen. `inbound_mode` (`pickup` \| `delivery`) ist an Offerte und Charge vorhanden.

**Fehlt:** Dieser Einsatz ist noch kein vollständiger Fahrauftrag:

- Er wird immer als Selbstabholung (`delivery=pickup`) angelegt: kein Chauffeur und keine Fahrt-Frei im Inbound-Ablauf. Fahrt-Frei verlangt heute einen gepackten Pack.
- Kein externer Abholort: Einsatz-Ziele sind nur GA-Orte; die Firmenadresse liegt an Offerte/Anfrage, nicht als Fahrt-Ursprung.
- Keine Inbound-Richtung: Fahrten sind fachlich Materialplatz → GA-Ort.
- Die Ankunft im Materiallager bucht keinen mengenbasierten Wareneingang.
- Inbound-Einsätze sind nicht sauber von normalen Einsätzen getrennt: sie erzeugen einen Pack mit QR. Von der Verfügbarkeitsregel sind sie ausgenommen, sobald die Charge sie über `item_details.pickup_einsatz_id` / `delivery_einsatz_id` referenziert ([§7.4](#74-verfügbarkeit-und-überbuchungsschutz-ist)); beim Anlegen, vor dieser Verknüpfung, werden sie noch wie jeder Einsatz geprüft.

**SOLL:** den bestehenden Einsatz / Pack / Fahrt-Mechanismus um Inbound erweitern (Richtung, externer Ursprung, Ankunft = Wareneingang). Keine zweite Fahrt-Tabelle.

Ein Fahrauftrag braucht fachlich: wo abholen, Kontakt, was, Menge, Zeitfenster, wohin, Fahrzeug, Fahrer, Bezug zur Charge. Wenn die Firma liefert, ist die Übergabe ebenfalls zu dokumentieren.

---

## 6. Wareneingang (IST, mengenbasiert)

Der Wareneingang ist die physische Annahme, nicht die Bestellung oder die Zusage. **Physische Wahrheit ist die Charge-Bewegung `received`** ([§14](#14-charge-bewegungen)).

**IST:**

- Materialübersicht → Wareneingang bucht pro Charge eine Teil- oder Restmenge mit optionalem Annahmeort (Matplatz / Unterlager) und Notiz: `POST …/beschaffung/zusagen/{commitmentId}/movements` (`kind=received`). Person und Zeitpunkt werden gespeichert.
- Erhaltene Menge, Fehlmenge und Stand (`none` \| `partial` \| `complete`) werden berechnet und mit der Charge ausgeliefert (`received_quantity`, `missing_quantity`, `inbound_state`).
- Menge muss > 0 sein. Überlieferung über die Charge-Menge wird abgelehnt (keine stille Mengenkorrektur). Parallele Buchungen sperren die Charge.
- Beschaffung: `POST …/beschaffung/lines/{id}/received` schreibt keine eigene Menge mehr, sondern bucht die Differenz als Bewegung an der Kauf-Charge der Bestellung. Eine Verringerung wird abgelehnt.
- Abgeleitet (Übergangsregel, nicht unabhängig editierbar): `procurement_line_wish.received_quantity` (Verteilung auf die Wünsche), Status der Bedarfsposition `teilweise_erhalten` / `erhalten`, `item_details.inbound_status`.
- Bisherige Eingänge wurden bei der Migration als Bewegungen übernommen (Charge «da» = ganze Menge; Kauf-Charge = erhaltene Menge der Position).

**Offen:** Ankunft einer Inbound-Fahrt bucht noch keinen Eingang ([§5](#5-abholung-bei-firma--lieferung-partial)). Korrektur einer Fehlbuchung gibt es noch nicht (keine negative Bewegung).

Fachliche Regeln:

- Erst bestätigte Ware gilt physisch als beim Grossanlass vorhanden.
- Nicht bestätigte oder unvollständige Mengen bleiben offen.
- Teilannahmen sind zulässig.

---

## 7. Einsatz, Pack, Teilpack, Transport

### 7.1 IST

- Mehrere Packs pro Einsatz; Pack-Zeilen mit `qty_needed` / `qty_packed`; Teilpack mit Warnung.
- Fahrt-Frei erst nach Pack (Teilpack reicht); danach ist der Materialplatz in der Übersicht frei.
- Pack-QR scannen → unterwegs (`in_transit`); Ziel-QR am GA-Ort → `at_place` mit Prüfung des Ziels.
- Selbstabholung ohne Fahrt-Frei.
- Pack-Mengen eines Einsatzes zusammen ≤ Einsatzmenge (Backend): ein neuer Pack bekommt nur die Restmenge; Erhöhen einer Pack-Menge nur bis zur Restmenge, Verringern immer; gepackt ≤ Pack-Menge. Ändert sich die Einsatzmenge, folgt ein einzelner Pack; bei mehreren Packs muss die Summe passen.

Beispiel:

```
Bauprojekt benötigt 20 Stück.

Pack 1: 12 Stück → Fahrt 1
Pack 2: 8 Stück → Fahrt 2
```

### 7.2 Lücken

- Bestehende Packs, die schon vor der Mengenregel über der Einsatzmenge lagen, werden nicht automatisch korrigiert (verringern ist möglich).
- Ausgabe einer Fahrt setzt nur den ersten Pack auf unterwegs.
- Selbstabholung: Ankunft am Ort nur per Pack-QR + Ziel-QR (Backend erlaubt es nach Ausgabe); ohne Scan zählt ausgegebene Menge als unterwegs.
- Ein Pack gehört zu genau einem Einsatz; Pack-Zeilen mit weiteren Chargen lassen sich nicht über die API hinzufügen. Eine gemischte Palette ist im Datenmodell angelegt (`commitment_id` je Zeile), aber nicht bedienbar.

### 7.3 Mengenbasierter Materialfortschritt (IST)

`GrossanlassMaterialProgressService` berechnet pro Materialposition (Materialwunsch bzw. Direkt-Bedarf ohne Wunsch) Mengen — nichts davon wird gespeichert. API: `GET …/grossanlass/uebersicht/progress` (optional `group_id`, inkl. Unterknoten; Summen je Bereich und Einheit). Das Bauprojekt-Briefing liefert dieselben Werte als `material_progress`; die Bauprojekt-Ansicht zeigt sie unter jeder Materialzeile (z. B. «100 benötigt · 100 gedeckt · 80 eingegangen · 60 zugewiesen · 50 gepackt · 20 transportbereit · 20 unterwegs · 10 vor Ort»).

| Menge | Feld | Quelle |
| --- | --- | --- |
| Bedarf | `required` | Wunschmenge (bei Bündelung je Wunsch, nicht die ganze Bedarfsposition) bzw. Direkt-Bedarf |
| gedeckt | `covered` | Chargen der Bedarfsposition, der Reihe nach auf ihre Wünsche verteilt (gebuchte und eingegangene Chargen zuerst) + Einsätze auf andere Chargen; Einsatz auf eine Charge der eigenen Position zählt nicht nochmals. «Selbst organisiert» / «genug vorhanden» = ganz gedeckt |
| offen | `open` | Bedarf − gedeckt |
| eingegangen | `received` | Charge-Bewegungen `received`, je Charge auf die Deckungen verteilt (keine Doppelzählung), höchstens gedeckt |
| zugewiesen | `allocated` | Einsätze `planned` / `pending_approval` / `issued` |
| gepackt | `packed` | Summe `qty_packed` aller Packs dieser Einsätze |
| transportbereit | `ready_for_transport` | gepackte Menge in Packs `trip_released`; Selbstabholung: gepackt + Einsatz frei |
| unterwegs | `in_transit` | gepackte Menge in Packs `in_transit`; Selbstabholung: ausgegeben |
| vor Ort | `at_place` | gepackte Menge in Packs `at_place` (Ziel-Scan) |
| Rückbau offen | `return_open` | ausgegebene Einsätze: unterwegs + vor Ort (ohne gepackte Menge: Einsatzmenge) |
| zurückgenommen | `returned` | Einsätze `returned` (ganze Einsatzmenge; Teilrücknahme fehlt, [§10](#10-rückbau-und-rücknahme-partial)) |
| entsorgt / verbraucht / verloren | `disposed` | Charge-Bewegungen dieser Arten mit Einsatz — heute nicht buchbar, daher 0 |

Mehrere Zustände enthalten gleichzeitig Teilmengen; es gibt keinen Gesamtstatus.

--- | --- | --- |
| Bedarf | ja | Wunsch / Bedarfsposition |
| gedeckt | teilweise | Chargen je Bedarfsposition; Einsätze je Wunsch |
| offen | teilweise | nur im Status `bedarf` nachgeführt |
| eingegangen | ja | Charge-Bewegungen `received` ([§6](#6-wareneingang-ist-mengenbasiert)) |
| reserviert / zugewiesen | ja | Einsätze `planned` / `pending_approval` |
| gepackt | ja | Summe `qty_packed` |
| transportbereit | ja | Pack `trip_released` |
| unterwegs | ja | Pack `in_transit` |
| vor Ort | ja (nur Fahrt) | Pack `at_place` + Ort |
| Rückbau offen | teilweise | Einsatz `issued`, nur ganze Menge |
| zurückgenommen | nein | [§10](#10-rückbau-und-rücknahme-partial) |
| endgültig disponiert | nein | [§10](#10-rückbau-und-rücknahme-partial) |

**SOLL:** Mengen berechnen, keine zusätzlichen Statusfelder. Mehrere Zustände können gleichzeitig Teilmengen enthalten.

### 7.4 Verfügbarkeit und Überbuchungsschutz (IST)

Der Einsatz ist die Reservierung. Der Server entscheidet verbindlich (`GrossanlassAvailabilityService`, Mengenrechnung `GrossanlassAvailability`); das Flag `has_conflict` des Clients wirkt nicht mehr. Keine zweite Reservierungs- oder Bestandstabelle.

**Reservierung.** Einsätze (`kind=einsatz`) mit Status `planned`, `pending_approval` und `issued` belegen ihre Menge im Fenster `[von, bis)`; berührende Fenster überlappen nicht. `pending_approval` blockiert wie `planned`; `returned` zählt nicht. Geprüft wird die **höchste gleichzeitige Menge**, nicht nur Paare: drei Einsätze mit je 4 Stück auf einer Charge von 10 sind überbucht. Die Obergrenze ist die Charge-Menge (erwartete Menge, auch vor dem Wareneingang). Unikate (Menge ≤ 1) und Fahrzeuge werden nie gleichzeitig mehrfach vergeben.

**Ausgabe.** Höchstens die physisch vorhandene Menge: Eigenbestand (`origin=own`) ist vorhanden, alles andere erst nach Wareneingang (Charge-Bewegung `received`, [§6](#6-wareneingang-ist-mengenbasiert)). Bereits ausgegebene, nicht zurückgenommene Einsätze derselben Charge werden einmal abgezogen; erneutes Ausgeben desselben Einsatzes ändert nichts.

**Wo geprüft wird** (immer in einer Transaktion, die Charge ist mit derselben pessimistischen Sperre wie der Wareneingang gesperrt):

| Vorgang | Regel |
| --- | --- |
| `POST …/uebersicht/einsaetze` | Reservierung |
| `PATCH …/uebersicht/einsaetze/{id}` | Reservierung bei Änderung von Charge, Menge, Zeitraum, Art oder Status (auch Freigabe `pending_approval` → `planned`, Wiederöffnen eines zurückgenommenen Einsatzes); Ausgabe bei `status=issued`. Reine Pack-/Fahrt-Änderungen lösen keine Prüfung aus |
| `POST …/uebersicht/einsaetze/{id}/issue` | Ausgabe |
| `POST …/packs/{id}/scan-start` | Ausgabe (der Pack-Scan gibt den Einsatz aus) |
| `PATCH …/beschaffung/zusagen/{id}` | Verringern der Charge-Menge nur bis zur höchsten gleichzeitig reservierten Menge |

**Antwort bei Konflikt:** HTTP 409 mit `error` (verständlicher Text), `code: availability_conflict` und `conflict` (`kind` = `overbooked` \| `unique_overlap` \| `not_on_hand` \| `below_booked`, dazu Charge, Bestand, Spitzenmenge, beteiligte `einsatz_ids` bzw. vorhandene und bereits ausgegebene Menge). Der Server speichert dann nichts.

**Konfliktvorschau.** `GET …/uebersicht` (`conflicts`) nutzt dieselbe Rechnung. Ein Konflikt nennt alle gleichzeitig beteiligten Einsätze (bisher nur Paare); Altbestand, der vor der Sperre überbucht wurde, bleibt sichtbar und lässt sich nicht weiter ändern, bis die Menge wieder passt.

**Nicht Teil der Regel (offen):**

- Reservierungen gegen Material des Gast-Departments (`MaterialItem` im Gast-Department): GuestShare prüft den Bestand dort noch nicht.
- Inbound-Einsätze sind nicht gekennzeichnet; vor der Verknüpfung mit der Charge zählen sie als normale Reservierung.
- Interne Abgleiche der Beschaffung, die die Charge-Menge ausserhalb einer Transaktion ändern, sperren die Charge nicht; die Mengenprüfung beim Verringern gilt auch dort.
- Teilrückgabe: ein zurückgenommener Einsatz gibt immer die ganze Menge frei ([§10](#10-rückbau-und-rücknahme-partial)).

---

## 8. QR, Identität und Etiketten

### 8.1 Identitäten (IST)

| Ebene | Identität | Auflösbar |
| --- | --- | --- |
| Charge | `barcode` `ZS-…` | nein — Text, kein öffentlicher eMatChef-Link |
| Pack / Palette | `/i/k/{packCode}` | ja |
| GA-Ort | `/i/ga/{placeCode}` (Alias `/i/p/`) | ja |
| Lager (Standort/Regal/Fach) | `/i/l/…`, `/i/r/…` auf `app.` | ja — [../qr/storage-location-qr.md](../qr/storage-location-qr.md) |

Regeln:

- Nicht jedes Stück braucht einen eigenen QR.
- Der Pack-/Paletten-QR ist nicht die Materialidentität; die Materialidentität ist die Charge.
- Keine zweite Materialidentität neben der Charge.

Fälle mit dem bestehenden Modell:

| Fall | Abbildung |
| --- | --- |
| 1 Akkuschrauber, einzeln rückverfolgbar | Charge mit Menge 1 (bei mehreren: je eine Charge mit Menge 1 unter einem Artikel) |
| 100 Schrauben | eine Charge mit Menge 100, kein Stück-QR |
| 20 Holzbalken | eine Charge oder eine Charge pro Gebinde |
| Palette mit verschiedenen Materialien | Pack mit mehreren Pack-Zeilen — Modell vorhanden, Bedienung fehlt ([§7.2](#72-lücken)) |

**SOLL:** Charge-Code öffentlich auflösbar machen (Linkschema [../qr/link-schema.md](../qr/link-schema.md)) und als QR/Etikett darstellen.

### 8.2 Etikettendruck

**IST (allgemeine Infrastruktur):** `usePrintJob` / `PrintJobDialog`, Brother QL über WebUSB, `PrintLayout` / `PrintDeviceModel`, Druckwarenkorb, `MaterialQrPdfService` (A4). Im Grossanlass heute für User-Karten genutzt.

**SOLL (GA-Integration):** Charge / Wareneingang → Druckauftrag → Materialetikett, ebenso Pack-Etikett. Die vorhandene Druckinfrastruktur wird wiederverwendet; kein eigener Druckpfad.

---

## 9. Department / Camp ↔ Grossanlass

### 9.1 IST: GuestShare

`department_grossanlass_guest_share` ist die bestehende Übergabe-Schnittstelle:

```
Gast-Department gibt frei   kind=offer, status=offered
                            material_item_id, Menge, Zeitfenster
→ Grossanlass übernimmt     status=accepted
→ Charge entsteht           origin=loan, source=Name des Departments,
                            Menge, Zeitfenster, Bezug guest_share.commitment_id
```

API: Gast `…/grossanlass/hosts/{hostId}/freigaben`, Grossanlass `…/grossanlass/gaeste` (+ `shares/{id}/accept`). Das ist die richtige Grundrichtung: Department → Freigabe → Grossanlass-Charge, ohne gemeinsame Pipeline. Keine weitere Übergabe-Entity daneben.

### 9.2 SOLL

- Eigentümer (Department) als echte Relation statt Freitext in `source`
- tatsächlich übergebene Menge (bestätigt)
- Teilrückgabe und zurückgegebene Menge
- Restmenge beim Grossanlass
- Rückmeldung an das bereitstellende Department: übergeben, zurück, noch beim Grossanlass

Beispiel Zielbild:

```
Department A → 40 Festbankgarnituren an Grossanlass

Grossanlass sieht: Herkunft/Eigentümer Department A, 10 im Zentrallager,
30 beim Bauprojekt, Rückgabe erforderlich.
Department A sieht: 40 übergeben, X zurück, Y noch beim Grossanlass.
```

---

## 10. Rückbau und Rücknahme (PARTIAL)

**IST:**

- Einsatz-Status `returned` existiert im Backend (setzt den Einsatz wieder auf Lager).
- `returned_to_firm` an der Charge; die Retour-Ansicht listet Leih-Chargen mit Fälligkeit und Knopf «zurückgegeben».

**Fehlt:**

- Teilmengen (Rücknahme gilt für die ganze Einsatzmenge bzw. ganze Charge)
- UI-Rücknahme für Einsätze
- Rücktransport vom Bauprojekt (Fahrten gehen nur Richtung GA-Ort)
- Pack-Status nach Rücknahme (bleibt `at_place`)
- Disposition

**SOLL:** Nach physischer Rücknahme Disposition pro Menge; eine Menge darf auf mehrere Wege aufgeteilt werden:

1. wieder einlagern
2. Werkstatt
3. zurück an Firma / Eigentümer
4. zurück an bereitstellendes Department
5. Verkauf an Dritte
6. Entsorgung
7. Verbrauch / Verlust

```
10 Stück zurück:

- 5 einlagern
- 2 Werkstatt
- 3 zurück an Firma
```

### 10.1 Rückgabe an Firma / Department (PARTIAL)

**IST:** nur `returned_to_firm` (Boolean, ganze Charge), Rückgabefenster `return_from/to`.

**SOLL:** Bei Leihe oder Miete ist «zurück am Zentrallager» noch nicht abgeschlossen. Rückfahrt zur Firma über den bestehenden Einsatz/Pack/Fahrt-Mechanismus oder Abholung durch die Firma mit bestätigter Übergabe (Menge, Person, Zeitpunkt). Danach gilt die Menge als an den Eigentümer zurückgegeben. Dasselbe gilt für Material eines Departments. Der Kostenstatus `returned` soll daraus folgen.

### 10.2 Verkauf (Inkonsistenz IST)

**IST:** Weiterverkauf an eine Gast-Abteilung (`guest_share kind=sale`) ist sofort `completed` und schreibt den Erlös; der Kostenstatus wird `for_sale` oder `sold`. Die Kostenübersicht kann `sold` auch direkt setzen. Dabei wird keine physische Übergabe erfasst, die Menge der Charge nicht reduziert und die Charge bleibt buchbar.

Damit gilt heute: **Kostenstatus ≠ physischer Materialstatus.** «Verkauft» heisst nicht, dass die Ware übergeben wurde.

**SOLL:** Verkaufserlös bzw. `sold` mit einer physischen Bewegung `sold_handover` ([§14](#14-charge-bewegungen)) verbinden.

### 10.3 Entsorgung / Verbrauch / Verlust (SOLL)

Nicht gebaut; kein Feld vorhanden. Eigene physische Abschlusswege; nicht als Einlagerung, Firmenretour, Verkauf oder Werkstatt darstellen.

### 10.4 Werkstatt (PARTIAL)

**IST:** eigene Fälle `department_grossanlass_workshop_case` mit Herkunft `own` | `loan` | `buy`, Weg `repair` | `owner`, Status `open` … `done` | `cancelled`. Material und Firma sind Freitext; kein Bezug zu Charge, Einsatz oder Menge.

Werkstatt ist nicht der allgemeine Retourprozess. Sie dient für defekte Gegenstände, Reparatur, Prüfung / Instandhaltung, Parkdienst und beschädigtes Leihmaterial bzw. Klärung mit Eigentümer.

**SOLL:** Bezug zu Charge und Menge; nach Abschluss ein eindeutiger nächster Weg (Lager, Eigentümer / Firma, Department, Entsorgung oder anderer zulässiger Abschluss).

---

## 11. Herkunft und Eigentum

**IST:**

- Herkunft `origin`: `loan`, `buy`, `buy_resale`, `own` (GA-Bestand, ohne Kostenzeile), `donation` (Schenkung / Sponsoring / Sachleistung, Kostenart `loan` = 0 CHF).
- Rückgabepflicht `return_required` getrennt von der Herkunft.
- Eigentümer `owner_kind` getrennt von der Herkunft; bei `department` echte Relation `owner_department_id`. Die übernommene Department-Freigabe setzt sie.
- `source` bleibt Anzeigetext.
- Die Retour-Liste der Materialübersicht zeigt Chargen mit Rückgabepflicht (nicht mehr «alle Leihen»).
- Die Kostenart unterscheidet zusätzlich Miete (`rental`) und Sachleistung (`loan`) — [kosten.md](./kosten.md).

**Offen:** Eigentümer im Zusage-Dialog wählen (heute: Herkunft und Rückgabepflicht; Eigentümer folgt der Herkunft bzw. der Department-Freigabe).

Fachlich zu unterscheiden bleiben: GA-/Eigenbestand, Material eines Departments / Camps, Firma Kauf, Firma Miete / Leihe, Sponsoring / Sachleistung, J+S. Dieselbe physische Menge kann je nach Herkunft und Eigentümer unterschiedliche Rückgabe- und Dispositionsregeln haben.

---

## 12. IST / PARTIAL / SOLL — Übersicht

| Bereich | Status |
| --- | --- |
| Materialwünsche, Bündelung, Bedarfspositionen | IST |
| Anfragen, Offerten, Zusagen (Chargen), Bestellungen | IST |
| Kostenmodell (Kauf, Miete, Leih/Sachleistung, Weiterverkauf) | IST |
| Deckung / offene Menge, Teildeckung aus mehreren Quellen | IST (berechnet, [§7.3](#73-mengenbasierter-materialfortschritt-ist)); Eigenbestand am Wunsch nur ganz |
| Abholung bei Firma / Lieferung | PARTIAL |
| Wareneingang (mengenbasiert an der Charge) | IST |
| Einsatz, Pack, Teilpack, Fahrt-Frei, unterwegs, Ankunft GA-Ort | IST |
| Serverseitige Verfügbarkeit, Überbuchungssperre, Ausgabe nur physisch vorhandener Menge ([§7.4](#74-verfügbarkeit-und-überbuchungsschutz-ist)) | IST |
| Verfügbarkeit gegen Material im Gast-Department | SOLL |
| Pack-QR, GA-Ort-QR, Charge-Barcode `ZS-…` | IST |
| Charge-Code öffentlich auflösbar, Charge-Etikett | SOLL |
| Druckinfrastruktur | IST |
| GA-Charge → Druck | SOLL |
| Department → Grossanlass (GuestShare) | PARTIAL |
| Rückbau / Rücknahme, Firmenretour | PARTIAL |
| Verkauf mit physischer Übergabe | SOLL (heute Inkonsistenz) |
| Entsorgung / Verbrauch / Verlust | SOLL |
| Werkstatt | PARTIAL (eigene Fälle, nicht mit Charge verbunden) |
| mengenbasierter Bauprojekt-Materialfortschritt | IST bis «vor Ort»; Rücknahme / Disposition SOLL |
| Herkunft `own` / `donation`, Eigentümer, Rückgabepflicht an der Charge | IST |
| Charge-Bewegungen ausser `received` | SOLL |

---

## 13. Bewusst nicht

- Keine zweite Fahrt- oder Transporttabelle für Inbound oder Retour.
- Keine weitere Wareneingangslogik neben der Charge-Bewegung `received`.
- Keine weitere Materialfreigabe neben GuestShare und Charge (auch keine `material_usage_grant`).
- Keine zweite Materialidentität neben der Charge.
- Keine direkte Kopplung an die Department-/Activity-Materialpipeline.

---

## 14. Charge-Bewegungen

Physische Mengenbewegungen laufen über **eine gemeinsame, mengenbasierte Ereignisspur an der Charge**: `department_grossanlass_charge_movement`. Keine separaten Tabellen für Wareneingang, Rückgabe, Verkauf und Entsorgung.

Eine Bewegung hat: Art, Menge (> 0), Charge, optional Einsatz, optional Ort, Zeitpunkt (`occurred_at`), ausführende Person (bei Altdaten leer), Notiz.

| Art | Bedeutung | Status |
| --- | --- | --- |
| `received` | Wareneingang | IST ([§6](#6-wareneingang-ist-mengenbasiert)) |
| `returned_to_store` | Rücknahme ins Zentrallager | SOLL — Art vorbereitet, nicht buchbar |
| `to_workshop` | an Werkstatt | SOLL — Art vorbereitet, nicht buchbar |
| `returned_to_owner` | an Firma / Department / Eigentümer zurück | SOLL — Art vorbereitet, nicht buchbar |
| `sold_handover` | physische Übergabe nach Verkauf | SOLL — Art vorbereitet, nicht buchbar |
| `disposed` | entsorgt | SOLL — Art vorbereitet, nicht buchbar |
| `consumed` | verbraucht | SOLL — Art vorbereitet, nicht buchbar |
| `lost` | verloren | SOLL — Art vorbereitet, nicht buchbar |

API: `GET` / `POST …/beschaffung/zusagen/{commitmentId}/movements`. Service: `GrossanlassChargeMovementService`.

Mengen wie eingegangen, zurückgenommen, disponiert oder noch beim Grossanlass werden daraus berechnet. Heute aus den Bewegungen abgeleitet: `inbound_status`, erhaltene Mengen und Status der Bedarfsposition. **SOLL:** ebenso `returned_to_firm` und Kostenstatus `returned` / `sold`.

---

## 15. Kurzfazit

Der Grossanlass-Materialfluss ist ein eigener Prozess. Sein Kern ist heute die Charge (Zusage) mit Einsatz, Pack und Ort darüber. Was fehlt, sind vor allem Mengen über den ganzen Lebensweg (Eingang, Rücknahme, Disposition), Herkunft und Eigentum als echte Daten sowie die Verbindung von Charge zu Fahrt, QR, Druck, Werkstatt und Department-Rückmeldung. Diese Lücken werden durch Erweiterung der bestehenden Strukturen geschlossen.
