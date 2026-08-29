# Grossanlass — Konzept 2026-08-23

Ideen und Soll-Ablauf aus der Produktklärung (Material & Logistik am Grossanlass). **Ergänzt** [README.md](./README.md) — Formular-Typen sind in **[README §9](./README.md#9-wünsche--ideen-formulare)** aufgenommen. Wo dieses Dokument und ältere README-Stellen (nur `ressort_wuensche`) divergieren, gilt für **Partneranfragen, Grob/Fein, Formular-Zweck und Kontakt-Schnitt** dieses Konzept plus README §9 Soll.

**Stand:** 23. August 2026 · **Status:** Konzept (noch nicht implementiert, ausser wo «Ist» markiert)

**Kontext:** Fragerunde → Wunschliste → Beschaffung → Leihgabe/Ausgabe → Rückgabe an Firmen. Gilt für jeden Grossanlass (Folk-Fest, Kantonslager, …), nicht für einen einzelnen benannten Event. Live-Schnitt App ~Ende September 2026 (Planung/Bedarf); Ausgabe und Rückgabe an Geber vor der jeweiligen Anlasswoche.

---

## 1. Satz Zielbild

Grossanlass ist die Kommandozentrale **vor und während** dem Event: Ressorts sagen, was sie brauchen; das OK bündelt zu Paketen, fragt **viele** Regionalpartner um Leihgabe/Sachleistung/Kondition, **nimmt wenige**, inventarisiert mit Zeitraum, gibt an Ressorts aus und gibt den Firmen ihr Material zurück.

Das ist **kein** camp/event und **keine** SES-Massenmail von `ematchef.ch`. Anschreiben läuft über **das OK-Postfach** (Gmail/Workspace). eMatChef hält Bedarf, Anfragen-Index, später Leih-Objekte.

---

## 2. Ist vs. Soll (kurz)

| Thema | Ist (Code, Aug 2026) | Soll (dieses Konzept) |
| --- | --- | --- |
| Runden + Wunschformular | Ja, ein Formular **pro Runde**, Typ nur `ressort_wuensche` | Wünsche & Ideen: `material_wish` / `company_tip` / `free`; **derselbe Wunsch** verfeinern (README §9) |
| Bedarf bündeln | Ja; **ein Pool aller** ungebündelten Wünsche aller Runden | Pool nur unverknüpfte **Grob**-Einträge; Fein = Update + Delta |
| Merge in Position | Nur solange Status `bedarf` | Grob-Position nach Anfrage-Start **einfrieren**; Fein nicht in dieselbe Firmenmenge |
| Beschaffung | Übersicht, Bedarf, Offerten (PDF/CHF), Bestellungen, Erhalten | Nach Bedarf: Tab **Anfragen**; Offerte = Antwort/Kondition |
| Kategorien am Bedarf | Ja | = Mail-Pakete (Fahrzeuge, Holz, Festzelt, …) |
| 300 Partner anschreiben | Nein | Modul **Anfragen** + Gmail-OAuth-Entwürfe |
| Kontakte | Lieferanten-Address bei Offerte möglich | Kontakt **erst beim Nehmen** |
| Materialübersicht / Leihweise / Ausgabe | Spec README §10–11, nicht gebaut | Nach Zusage: Herkunft, von–bis, Ressort holt, retour Firma |
| README «Umsetzung offen» | Veraltet | Planung + Beschaffung-Kern sind da; Lücken = dieses Dokument |

---

## 3. Partneranfrage, nicht klassische Offerte

Die Mail an Firmen ist eine **Anfrage um Zusammenarbeit** (Leihgabe, Sachleistung, vergünstigte Kondition), kein Ausschreibungs-PDF als Erstes.

Kernbotschaft (Vorlage, Platzhalter):

- Anlass: Name, Datum, Ort, Grössenordnung (aus Grossanlass-Dept / Fixe Daten)
- Absender: Material & Logistik des Anlasses, Bevorzugung regionaler Partner
- `{{MATERIALLISTE}}` = **kurze Pakete**, keine 40 Excel-Zeilen
- `{{ZEITRAUMTEXT}}` = Aufbau / Anlass / Rückbau / Rückgabe an die Firma
- Anhang: kurze Anlass-Info + nur bei Bedarf Übersicht zum Paket

Tab «Offerten» mental: **Partnerkondition** (0 CHF bei Leihgabe ist richtig). PDF = Bestätigung, nicht Pflicht am Anfang.

---

## 4. Pakete statt Rohlisten (Excel / Mail)

Quellen (Drive, nicht 1:1 Lager importieren):

- Firmenliste: Name, Ort, Website, **Bereich**, Status — **E-Mail oft noch fehlend**
- Grobübersicht Material & Fahrzeuge: Bedarf; Blatt «idee 2» = interne Werkzeugkiste, **nicht** 300 Firmen anschreiben

Mail-Pakete (Beispiele):

| Paket | Inhalt grob | Nicht in die Mail |
| --- | --- | --- |
| Fahrzeuge / Maschinen | Gator, Teleskoplader, Bagger, Hebebühne, … | Gemeinde-Kehrmaschine eigener Kanal |
| Festzelt / Mobiliar | Zelte mit Ort + grober Grösse | — |
| Bauholz | Kantholz, Masten, Schalung — Mengen folgen | jede Latten-Dimension |
| Wasser / Sanitär | PE, Hydrant, Fittinge | jede Schlüsselgrösse |
| Befestigung | Schrauben/Gewindestangen Bau | TX20 vs TX25 |
| Elektrowerkzeug | Akkuschrauber, Säge, Kettensäge | 80 Zeilen «idee 2» |

Pro Firma nur **ihr** Bereich. Eine Firma × mehrere Pakete möglich (z. B. Generalist).

**Welle 1** darf die MW-Grobübersicht sein, **ohne** dass alle Ressort-Runden geschlossen sind (lange Vorläufe: Zelte, Maschinen).

**Welle 2** nach Feinplanung: nur **Delta** (mehr/weniger/Löcher).

---

## 5. Kanäle: was wo lebt

```
eMatChef     Wünsche, Bedarf/Pakete, Anfragen-Datensätze, später Leih + Ausgabe
Gmail        Posteingang, Entwürfe, Threads (Workspace-/OK-Postfach des Anlasses)
Sheets       Übergang: Merge/Entwürfe, solange Anfragen nicht in der App sind
SES          nur Transaktionsmail der App — nicht 300 Partnermails
```

**Heute:** 300 Entwürfe über Sheet + Script/Merge ist der tragfähige Weg.

**Soll:** eMatChef füttert (Pakete + Anfragezeilen) → **Gmail OAuth** `drafts.create` → Mensch prüft und sendet. Sheet entfällt, sobald Anfragen in der App die Quelle sind.

Kein Live-Sync Sheets↔API ohne eigenen Export/Token. Zwischenlösung: CSV «für Anfragen» vor einer Versandwelle.

Antworten: Thread zuordnen, nicht Inbox-KI. Optional Antwort-Formular. Auto-Status höchstens «Antwort da» + Snippet.

---

## 6. Anfragen vs. Kontakte

~300 Anschreiben, wenige Zusagen. **Nicht** 300 Lieferanten in Kontakte.

| | Anfragen (Kampagne) | Kontakte (`Address` Lieferant) |
| --- | --- | --- |
| Wer | alle, die eine Mail kriegen | nur wer genommen wird |
| Daten | Name, Mail, Paket(e), Status, Gmail-Thread | Adresse, Tel, Leih-Herkunft |
| Anlegen | vor dem Entwurf | bei **Nehmen** / erster Zusammenarbeit |

Fünf Firmen für dasselbe Paket = fünf **Anfragen**, eine Bedarfs-/Kategoriezeile. Zwei genommen = zwei Kontakte. Absagen bleiben Anfragen.

### 6.1 Wo die Firmenliste entsteht und Kategorien zugeordnet werden

**Ist:** Nirgends als Kampagne. Kategorien leben unter **Beschaffung → Bedarf** (Positionen und Kategorie-Baum). Firmen tauchen nur auf, wenn jemand unter **Offerten** einen Lieferanten (Kontakt) anlegt — ungeeignet für ~300 Prospects.

**Soll — ein Ort:** **Beschaffung → Anfragen** (neuer Tab, nach Bedarf). Nicht Planung, nicht Kontakte, nicht Settings → Benutzer.

Dort zwei zusammengehörige Sichten (gleiche Daten):

| Sicht | Tun |
| --- | --- |
| **Firmen** | Liste anlegen (Name, E-Mail, Ort, Website, Bereich-Freitext). Import aus Sheet/CSV. Pro Firma **eine oder mehrere Beschaffungs-Kategorien** anhaken = welche Pakete in der Mail. |
| **Nach Kategorie** | z. B. Fahrzeuge: alle Firmen, die dieses Paket bekommen. Von der Kategorie aus Firmen hinzufügen. |

Zuordnung ist **n:n**: eine Firma (Generalist) → Fahrzeuge + Holz; eine Kategorie → viele Firmen. Speichern als Anfragezeilen (Firma × Kategorie) oder Firma mit Kategorie-Set — beim Entwurf wird `{{MATERIALLISTE}}` aus den angehakten Kategorien gebaut (nur Grob-Pakete, nicht alle Bedarfszeilen).

Ablauf zum Arbeiten:

1. **Bedarf:** Kategorien anlegen (Fahrzeuge, Festzelt, …) und Grob-Positionen zuordnen.  
2. **Anfragen:** Firmen erfassen/importieren, Kategorien setzen.  
3. Filter «bereit / fehlt E-Mail / Kategorie X», dann Entwürfe nur für diese Menge.

Ressort-Tipps («kennt ihr eine Sägerei?») landen höchstens als **Vorschlag** auf dieser Liste, nicht automatisch als 300 Kontakte.

**Bis der Tab existiert:** dieselbe Matrix in der Tabelle (Spalten = Kategorien oder eine Spalte «Pakete»). In eMatChef nur die Kategorien unter Bedarf pflegen, damit die Namen später 1:1 übernommen werden können.

---


## 7. IDs, Betreff, Fusszeile, Thread

- ID wie der Rest der App: **12-stellig hex, zufällig** (`IdGenerator`), Prefix analog anderer Entitäten — **keine** laufende `anfrage_0012`.
- **Betreff** menschlich, ohne sichtbare Ticketnummer (z. B. «{Anlassname} – Anfrage Material & Logistik»).
- **Gmail Thread-ID + Message-ID** in eMatChef speichern — Hauptanker für Antworten.
- Optional Header `X-eMatChef-Anfrage: {id}` (Empfänger sieht das nicht).
- **Fusszeile** klein: `Referenz {id}` — Backup bei Weiterleitung, nicht unsichtbares Weiss/HTML-Kommentar.

### 7.1 Gmail-Labels: abgleichen und ergänzen

Gmail-Labels (verschachtelt) sind die **Inbox-Sicht** derselben Matrix wie in der App — nicht eine zweite Wahrheit.

Typische Nesting (Namen kommen aus dem Grossanlass, nicht fest verdrahtet):

```
{Anlass}
  Firmenanfragen          ← eine Label-Ebene pro Beschaffungs-Kategorie
    Bauholz
    Baumaschinen
    …
  Status
    Wartet auf Antwort
    Antwort erhalten
    Nachfassen
    Zusage
    Teilzusage
    Absage
    Erledigt
```

**Abgleichen**

- Kategorie-Labels **1:1** zu Bedarf-Kategorien (gleicher Anzeigename). Beim ersten OAuth: bestehende Labels unter `{Anlass}/Firmenanfragen/…` per Name zuordnen, nicht blind neu anlegen.
- Status-Labels **1:1** zu Anfrage-Status in eMatChef. Ein Thread: **ein** Status-Label (wechseln, nicht fünf Status gleichzeitig).
- Kategorie: **mehrere** erlaubt (Generalist → Fahrzeuge + Werkzeug), analog n:n in der App.
- Beim `drafts.create` / nach Senden: App setzt Anlass + Kategorie(n) + Status «Wartet auf Antwort».
- Thread bleibt der Anker; Labels sind Filter in Gmail.

**Ergänzen**

- In Gmail **zusätzlich** labeln (z. B. «Nachfassen», «Teilzusage») darf die App **einlesen**, wenn das Label auf eine bekannte Status-Liste gemappt ist — dann Status in eMatChef nachziehen.
- Neue Kategorie zuerst in **Beschaffung → Bedarf**, dann Label anlegen oder von der App spiegeln. Nur in Gmail eine Kategorie erfinden ohne App-Kategorie: Ergänzung in eine Richtung, Mapping «unbekannt» bis ihr sie in eMatChef anlegt.
- Freie Gmail-Labels ausserhalb Status/Kategorie (persönlich, «telefoniert») **nicht** zwingend syncen — Ergänzung nur im Postfach.

**Quelle der Wahrheit:** Status und Kategorie-Zuordnung der Firma leben in eMatChef. Gmail-Labels sind Spiegel + manuelle Feinarbeit im Posteingang. Konflikt (App sagt Zusage, Gmail sagt Absage): zuletzt vom Menschen gesetztes Label oder Bestätigung in der App — nicht still überschreiben.

**Bis OAuth existiert:** dieselben Label-Namen von Hand; Filter z. B. Betreff/Fusszeile. Später kann die App dieselben Nested Labels wiederverwenden.

### 7.2 Gmail verbinden, Texte erfassen, Vorschau

Drei Dinge, die **in eMatChef** liegen müssen — nicht nur in Gmail:

1. **Konto verknüpfen** (OAuth, OK-Postfach des Anlasses)  
2. **Vorlagen** (Betreff + Body + Platzhalter)  
3. **Vorschau**, bevor Entwürfe in Gmail landen  

**Nicht** unter Superadmin → E-Mail (das ist SES/`ematchef.ch`). **Nicht** in Kontakte.

#### Wo in der App

| Was | Ort | Wer |
| --- | --- | --- |
| Gmail verbinden / trennen, welches Konto, Label-Wurzel `{Anlass}` mappen | **Einstellungen → Anfragen & E-Mail** (neuer Grossanlass-Settings-Punkt, nur MW/DC) | einmal pro Dept |
| Vorlagen anlegen und bearbeiten | dieselbe Settings-Seite, Tabs oder Liste **Vorlagen** | MW/DC |
| Firmenliste, Kategorien, Status, «Entwürfe erzeugen» | **Beschaffung → Anfragen** | MW/DC |
| Vorschau **einer** Mail (diese Firma, diese Kategorien) | Anfragen: Zeile oder Dialog **Vorschau** | vor dem Erzeugen |
| Vorschau **der Vorlage** mit Beispieldaten | Settings bei der Vorlage: **Vorschau** (Demo-Firma, Demo-Paket, Anlassname aus Dept) | beim Editieren |

Einstellungen = Anschluss und Texte. Anfragen-Tab = Betrieb und Merge-Ergebnis an echten Zeilen.

#### Gmail-Verknüpfung

- Button «Google-Konto verbinden» → OAuth (Gmail `compose` / Drafts, Labels lesen+setzen; Inbox lesen für «Antwort da»).  
- Angezeigt: verbundene Adresse, verbunden am, «trennen».  
- Ein Postfach pro Grossanlass-Dept (das, von dem die Firmen eine Antwort erwarten).  
- Label-Baum: bestehende Nested Labels zuordnen oder anlegen (siehe §7.1).  
- Ohne Verknüpfung: Vorlagen und Vorschau trotzdem nutzbar (Text in der App); Entwürfe in Gmail erst nach Connect.

#### Vorlagen (mindestens)

Platzhalter wie `{{ANREDE}}`, `{{FIRMA}}`, `{{ANLASS}}`, `{{ORT}}`, `{{ZEITRAUMTEXT}}`, `{{MATERIALLISTE}}`, `{{ABSENDER}}`, Fusszeile mit ID setzt die App.

| Vorlage | Wann |
| --- | --- |
| **Anfrage** | erste Mail / Entwurf an Prospects |
| **Dank Absage** | Firma hat abgelehnt — Dank im selben Thread |
| **Bestätigung Zusage** | Firma hat zugesagt — Dank + was ihr verstanden habt |
| **Nicht genommen** | Firma hatte zugesagt, ihr nehmt eine **andere** — kurze Absage der Zusammenarbeit, höflich, anderer Text als «Dank Absage» |
| optional **Nehmen** | nur an die Genommenen (wir rechnen mit euch / nächste Schritte) |

**Gesendet festziehen** heisst: In der App gibt es einen klaren Status **Entwurf** vs. **Gesendet** — nicht vermischen.

- «Entwürfe erzeugen» legt nur Gmail-Drafts an. Die Anfrage bleibt `entwurf`, bis die Mail wirklich raus ist.
- **Gesendet** wird, wenn Gmail den Draft nicht mehr als Draft führt (API) **oder** MW «als gesendet markieren» (falls sie aus Gmail ohne Sync senden).
- Erst `gesendet` startet «Wartet auf Antwort», Nachfassen-Frist, «nicht nochmal anschreiben». Solange nur Entwurf: jederzeit Text ändern, Entwurf ersetzen, nicht als Kampagne zählen.

**Nicht-genommen-Mail festziehen** heisst: Das ist eine **eigene Vorlage**, Pflicht wenn MW bei Zuteilung «nicht nehmen» klickt, obwohl Status Zusage war. Nicht denselben Text wie Dank-Absage (die Firma hat ja ja gesagt). Entwurf im selben Thread, prüfen, senden, dann Gespräch Erledigt. Ohne diese Mail bleibt die Firma im Glauben, ihr kommt.

Absage und Zusage (und Nicht-genommen) lösen in der App die Entscheidung aus → passender Entwurf, Labels spiegeln. Entscheidung in der App, nicht nur Gmail-Label.

#### Vorschau (was die App baut)

Zwei Stufen, beide **in der App**, bevor Gmail einen Entwurf hat:

1. **Vorlagen-Vorschau** (Settings): Dummy-Merge, damit MW sieht, ob Paketliste, Zeitraum und Anrede stimmen.  
2. **Zeilen-Vorschau** (Anfragen): echte Firma, echte angehakte Kategorien, echte Grob-Pakettexte, echte ID-Fusszeile. Unausgefüllte Pflichtfelder (keine E-Mail, kein Paket) rot, kein Entwurf.

Sammelaktion «Entwürfe für Auswahl»: vorher Liste **Betreff + Empfänger + Kurz-Body**; einzeln aufklappbar. Nichts still in Gmail schreiben ohne diese Kontrolle.

Nach dem Erzeugen: Link «in Gmail öffnen» (Entwurf). Endkontrolle und Senden bleiben Gmail — die App zeigt denselben Merge nochmal zum Abgleich.

---



## 8. Soll-Ablauf in der App

Planung ist **Wünsche & Ideen sammeln** (nicht Projekt-«Runden»). Formulare sagen **was** / optional **wen kennt ihr**; Beschaffung sagt **wen das OK anschreibt** (n Firmen, gleiches Paket).

```
Planung → Wünsche & Ideen
            Materialwunsch (Grob/Fein am selben Wunsch)
            Firmenvorschlag
            Frei (nur nach Review)
        ↓
Beschaffung
  1 Bedarf        nur aus Materialwünschen
  2 Anfragen      n Firmen × Paket
  3 Rückmeldungen inkl. Dank / Nicht-genommen
  4 Zuteilung     m von n nehmen
  5 Erhalten      Kontakt + Objekt (Leih/Kauf/Weiterverkauf)
        ↓
Lager
  Ausgabe (Fahrer-Kontrolle) → Retour → Geber oder Verkauf
```

In der **Haupt-Spec:** [README §9](./README.md#9-wünsche--ideen-formulare).

«Bestellen» bleibt für Kauf; Leihgabe oft: Zusage → Erhalten.

### 8.1 Formulare statt «Planungsrunden»

**Ist:** Tab «Planungsrunden», jeder Eintrag `round_type: ressort_wuensche`, ein frei gebautes Formular, alle Antworten → Wunschzeilen → **ein** Bedarf-Pool.

**Soll:** Derselbe Container (offen/geschlossen, Zeitfenster, Formular-Builder), aber **Zweck wählen** beim Anlegen — und Antworten **getrennt zuordnen**:

| Typ | Formular | Landet in | Nicht tun |
| --- | --- | --- | --- |
| **Materialwunsch** | Festes Kernset: Was, Menge, Ort, Zeitraum, Ressort; Grob vs. Fein als Stufe am **Wunsch** (§9). Zusatzfragen erlaubt. | Bedarf-Pool / Verfeinern | Roh in 300 Mails |
| **Firmenvorschlag** | Firma, Kontakt, Bereich/Kategorie, optional URL, Notiz. Kein Mengenkatalog. | Anfragen-Tab als **Vorschlag** (MW übernimmt oder verwirft) | Als Beschaffungsposition zählen |
| **Frei** | Leerer Builder (wie heute). Für Umfragen, Ideen, «was fehlt uns noch». | Ideen-Posteingang; MW **übernimmt** explizit als Materialwunsch, Firmenvorschlag oder verwirft | Automatisch bündeln |

Nicht mehr Typen. «Frei» deckt Sonderfälle; sonst zerfasert der Pool wieder.

UI-Vorschlag: Planung-Tab umbenennen **«Wünsche & Ideen»** (Sidebar «Planung» kann bleiben als Bereich: Ressorts + diese Seite). Liste der Formulare mit Badge Material / Firma / Frei, Status offen/zu. Mitglieder sehen nur **offene** Formulare plus «meine Einträge verfeinern» bei Material.

Zeitfenster auf/zu bleibt sinnvoll («jetzt könnt ihr einreichen») — das ist kein Widerspruch zum neuen Namen.

---


## 9. Grob / Fein: ein Wunsch, zwei Schärfen

**Problem Ist:** Jede Runde erzeugt neue Wunschzeilen. Der Bedarf-Pool ist die Vereinigung aller ungebündelten Zeilen. Nach Runden-Schluss kann der Autor die Zeile nicht mehr editieren. Fein + Grob zur gleichen Sache **addieren** sich, wenn man unbedacht bündelt. Anfragen auf Basis Grob laufen dann gegen eine falsche Summe.

**Soll:** Der Wunsch ist das Objekt (Ressort/Bauprojekt + Identität). Runden sind Schärfestufen.

```
Wunsch «Gator»
  Grob     3×, Kategorie Fahrzeuge, Anlasswoche
  Fein     2×, Bühne, Fr–So, Allrad
  Anfrage  eingefroren 3×     (Welle 1)
  Delta    aktuell 2 vs. angefragt 3  →  −1
```

User: grob erfassen, später **dieselbe** Zeile verfeinern (kein zweites «noch ein Gator», ausser wirklich neu).

Beim **Formular/Runde anlegen** Hinweis an MW und an ausfüllende User:

| Runde | Hinweis |
| --- | --- |
| Grob | Nur Wesentliches: was, ungefähr wie viel. Details später. Lieber grob als 20 Schraubentypen. |
| Fein | Das sind eure Grobeinträge — jetzt präzisieren. Keine Duplikate, ausser **zusätzlich** nötig. |

Mehrere Materialformulare **nebeneinander** (Bau vs. Küche): nach Kategorie bündeln — gewollt.

Mehrere Formulare **nacheinander zur gleichen Sache**: nicht addieren. Grob = Deckel für Anfragen; Fein = Verteilung + Delta.

Firmen-Tipps: eigener Formular-Typ `company_tip` (§8.1 / README §9.3), **nicht** im Material-Bedarf-Pool.

### 9.1 Pool und Zuordnung

Bedarf-Pool = Warteschlange **unverknüpfter Grobeinträge**, nicht Halde aller Formulare.

- Neu aus Grob → bündeln / Kategorie / Anfrage starten
- Fein am bestehenden Wunsch → kein neuer Pool-Eintrag, Update + Abweichung
- Bewusst neu in der Feinrunde → neuer Wunsch (Nachbedarf / neue Anfrage)

Zuordenbar: Runde, Ressort, Kategorie, Beschaffungsposition, Anfrage-Paket als Filter.

### 9.2 Anfrage einfrieren, Abweichung zeigen

Sobald die erste Mail zu einem Paket/einer Position rausgeht:

| Feld | Verhalten |
| --- | --- |
| Angefragt | bleibt |
| Aktuell (Fein) | bewegt sich mit Verfeinerung |
| Abweichung | z. B. `−1` oder `+2 aus Feinrunde …` |
| Aktion | intern umverteilen, Nach-Anfrage, oder nicht nehmen |

Nicht die Firmenmails umschreiben. Abweichung = Auftrag Welle 2.

Merge «weitere Wünsche in dieselbe Position» nur, solange **noch keine Anfrage** hängt. Danach: Kind/Nachbedarf oder nur Zuteilungsnotiz.

---

## 10. Fünf anfragen, zwei nehmen

Zuteilung, nicht «eine Offerte gewinnt».

Beispiel: Bedarf 3 Gators, Kategorie Fahrzeuge, fünf Anfragen.

- Firma A Zusage 2 → **nehmen** 2  
- Firma B Zusage 1 → **nehmen** 1  
- Firma C Zusage 3 → nicht nehmen  
- D/E Absage  

Bedarf gedeckt, drei Anfragen dokumentiert, **zwei Kontakte**. UI: Filter nach Kategorie **und** nach Bedarfszeile.

Dieselbe Firma in zwei Paketen: eine Anfrage mit zwei Paketen oder zwei Anfragen, **ein Kontakt** beim ersten Nehmen.

---

## 11. Mein Ressort und MW-Sicht

«Wer hat was» (Verteilung) und Grob/Fein (Planung) sind zwei Sichten.

**Ressort:** Liste der eigenen Wünsche mit Stufe grob/fein, Status (offen / angefragt / zugeteilt), **Verfeinern** auf der Zeile.

**MW:** dieselbe Zeile plus angefragt vs. aktuell, Abweichung, Anfragen (n Firmen, m genommen).

Ohne Wunsch als Anker zerfallen Paket-Mails und Ressort-Details.

---

## 12. Nach der Zusage (Spec README §10–11)

«Erhalten» in der Beschaffung erzeugt **heute kein** inventarisierbares Stück. Soll-Brücke:

1. Quelle: Eigenkauf vs. Firma X (Leih) vs. Gemeinde  
2. Nutzungsfenster = Leihfenster aus der Mail  
3. Lager → Ausgabe an Ressort (wer, wann)  
4. Überbezug sichtbar (Wunsch 2, Abholung 3)  
5. Retour Ressort → Lager → **Retour Firma** vor `valid_to` (Leih) bzw. einlagern / **zu verkaufen** (Kauf mit Weiterverkauf)

Ohne das: am Sonntag unklar, ob der Bagger noch bei der Bühne oder schon beim Geber sein sollte.

### 12.1 Ausgabe: Schlüssel, Ausweis, «darf fahren»

Bei **Fahrzeugen** (und analog wo es passt: Maschinen, Anhänger): Ausgabe ist erst erlaubt, wenn Kontrolle sitzt — nicht nur «Ressort hat gewünscht».

Am **Benutzer** (Grossanlass-Mitglied, der abholt/fährt), nicht nur an der Firma:

| Checkbox / Nachweis | Zweck |
| --- | --- |
| **Darf dieses Fahrzeug / diese Klasse fahren** | MW hinterlegt (Kategorie B, Stapler, …), gültig bis optional |
| **Ausweis / Führerausweis gesehen** (oder Scan hinterlegt) | Kontrolle vor Ort, Datum wer geprüft hat |
| **Schlüssel ausgegeben** | welcher Schlüssel/Code, an wen, Zeit — Retour: Schlüssel zurück |

An der **Ausgabe** (Materialübersicht): Block oder Warnung, wenn «darf fahren» fehlt oder Ausweis nicht bestätigt. MW kann nicht «aus Versehen» den Gator an jemanden ohne Recht geben. Gilt für Eigen-, Leih- und gekaufte Fahrzeuge gleich — das Recht hängt am **Fahrer**, die Herkunft am Objekt.

Weitere Maschinen (Hebebühne, Bagger): gleiches Muster, andere «Klasse».

### 12.2 Herkunft: Leihen, kaufen, kaufen und wieder verkaufen

Pro Beschaffungsposition / Leih-Objekt eine **Art**, nicht alles «Offerte»:

| Art | Bedeutung | Nach dem Anlass |
| --- | --- | --- |
| **Leihgabe / Sachleistung** | bleibt Eigentum der Firma, Nutzungsfenster, Rückgabe §12 | retour Geber, Kontakt = Verleiher |
| **Kauf** | geht in Eigenbestand des Grossanlass-Dept (oder Verein) | bleibt oder wird eingelagert; klassische Beschaffung |
| **Kauf mit Weiterverkauf** | Anschaffung für den Anlass, danach Verkauf (Markt/Inserat), **kleine Abschreibung** = Differenz Kauf vs. Verkauf (Verschleiss, nicht Pfadi-Buchhaltungs-Modul 1:1) | Status «zu verkaufen» nach Retour intern; Ist-Kosten = Kauf − Verkaufserlös |

Die Art steht schon bei der **Zuteilung / Nehmen** (nicht erst bei Erhalten), damit Anfragen und Dank-Mails nicht «Leihgabe» versprechen, wenn ihr kauft. Geld fliesst nur bei Kauf / Weiterverkauf — Leih oft 0 CHF.

**Miete** (bezahlt) von **Sachleistung** trennen. Zahler kann ein anderes Ressort sein als Logistik (Organisator). Ledger und Rahmen: **[kosten.md](./kosten.md)**.

Kein volles Pfadi-`/accounting` im Grossanlass; grobe Soll/Ist-Spur in der Beschaffungs-Übersicht reicht v1. Abschreibungs-Kalkulator des Vereins später optional anbinden.

### 12.3 Einsatzliste: Ressort / Bauprojekt, keine Doppelbuchung

Nach «wir haben das Material» kommt **Einsatz planen** — nicht nur ein Haufen im Zentrallager. Eine Liste (und Kalender) über **jedes Stück / jede Menge** in Abhängigkeit von **Ressort und Bauprojekt** plus **Zeitraum**.

**Einheit:** ein **Einsatz** (Reservation)

| Feld | Bedeutung |
| --- | --- |
| Objekt | konkretes Fahrzeug (1 Stück) oder Materialposition + Menge |
| Von / bis | überschneidet sich mit anderen Einsätzen desselben Objekts? |
| Wohin | Ressort und optional Bauprojekt (Bühne vs. Wasserstelle) |
| Status | geplant → ausgegeben → retour |
| Wer | Abholer; bei Fahrzeugen §12.1 |

**Keine Doppelbuchung**

- **Unikat** (Gator, Bagger, ein Zelt): überlappende Zeitfenster **blockieren**. Zweite Buchung nur nach Ende der ersten oder MW löst Konflikt (kürzen, verschieben, anderes Objekt).
- **Menge** (Schrauben, 10 Gerüstböcke): Summe der überlappenden Einsätze ≤ verfügbare Menge (im Lager + noch nicht ausgegeben). Überschreiten = Warnung/Block.
- Verfügbarkeit ist **min(Leihfenster Geber, Anlass-Zeitraum, Objekt da)**. Einsatz ausserhalb `valid_from`/`valid_to` der Leihgabe unmöglich.

Wunsch-Zeitraum aus der Feinplanung ist **Bedarf**, kein Einsatz. MW (oder RL im eigenen Baum) wandelt zu Einsätzen um — der **Wunsch ist die Vorlage** (Objekt/Bezeichnung, Menge, Zeitraum, Ressort/Bauprojekt vorausgefüllt). Zwei Ressorts wollen denselben Gator am Samstag 10–14 → sichtbar **Konflikt**, nicht still 2× zuweisen.


**Sichten**

| Sicht | Frage |
| --- | --- |
| **Nach Objekt** | Gator 1: Fr Aufbau Bau/Bühne, Sa Anlass Sicherheit, So retour Firma |
| **Nach Ressort / Bauprojekt** | Bühne: welche Fahrzeuge/Material wann — für die Einsatzplanung vor Ort |
| **Konflikte** | nur überlappende Unikate / überbuchte Mengen |

Ausgabe (§11 README, §12.1) nur, wenn ein Einsatz für genau dieses Objekt+Zeit+Gruppe existiert (oder MW ad hoc anlegt mit Konfliktcheck). Sonst holt Bau den Gator, den Sicherheit schon «hat».

Ist README §11: Lager / zugewiesen / draussen **ohne** Zeitachse. Soll: zugewiesen = geplante Einsätze; draussen = aktiver Einsatz.

#### Navigation: Tab unter Materialübersicht, nicht drittes Hauptmenü

**Nicht** eigener Sidebar-Punkt «Einsätze» neben Materialien **und** Materialübersicht — drei Material-Einträge. **Nicht** Tab unter **Materialien** (das sind Stammdaten: Eigen / Leih / Fahrzeuge).

**Soll:** ein operativer Menüpunkt **Materialübersicht** (`/material-uebersicht`), innen Tabs wie Planung/Beschaffung:

| Tab | Inhalt |
| --- | --- |
| **Bestand** | Lager / zugewiesen / draussen, Filter Ressort |
| **Einsätze** | Liste + Zeitachse, nach Objekt oder Bauprojekt (§12.3) |
| **Konflikte** | nur Überbuchungen; leer = gut |

Optional Label in der Sidebar später **«Material & Einsatz»**, wenn «Übersicht» zu blass ist — weiterhin **ein** Nav-Eintrag.

Mitglieder/RL: dieselben Tabs, gefiltert auf den eigenen Baum. Dashboard-Klick «draußen» / Konflikt-Zahl → Tab Einsätze bzw. Konflikte.

Während der Anlasswoche Default-Tab **Einsätze** (wenn Phase Event/Aufbau), sonst Bestand.

Stammdaten bleiben **Materialien** (Menü erst wenn §10 live), getrennt von der Einsatzplanung.

### 12.4 Wer bucht — und wenn mehr / weniger / verbraucht wird

Ja: dasselbe Tool wie §12.3 (Tab **Einsätze** in der Materialübersicht, plus Einstieg **Mein Ressort**). Kein drittes Menü. Vor dem Anlass und **währenddessen** derselbe Dialog; währenddessen nur kürzere Fenster und «ab jetzt».

#### Fahrzeug / Material für einen Zeitraum

| Wer | Darf |
| --- | --- |
| **Mitglied / RL** | Im **eigenen** Ressort/Bauprojekt buchen, wenn frei. Kalender zeigt frei/belegt (belegt reicht als Block, ohne fremde Interna). Fahrzeuge: nur wenn §12.1 «darf fahren» (sonst nur **anfragen**). |
| **Konflikt / Sperre** | Kein stilles Überschreiben. Status **Anfrage an MW** (Inbox), MW weist anderes Objekt, kürzt, oder lehnt ab. |
| **MW** | Alles; Konflikt lösen; ad hoc «jetzt ausgeben». |

Ablauf User: **Wunsch als Vorlage** («Buchen» übernimmt Felder) oder leer «Einsatz buchen» → von/bis → Bauprojekt → Konfliktcheck → **geplant**. Abholung = Ausgabe auf diesen Einsatz. Vorher = Planung, währenddessen = oft sofort Ausgabe wenn das Stück im Lager liegt.

**Ist:** Materialübersicht Einsätze ist **Layout-Vorschau** (Demo-Daten, Banner). Kein Speichern. Button «Einsatz buchen» / «Aus Wunsch» nur Anschauung.

#### Mehr brauchen / Verbrauch / Rückgabe

Drei verschiedene Aktionen, nicht eine:

| Aktion | Wann | Was passiert |
| --- | --- | --- |
| **Rückgabe** | Einsatz endet oder früher fertig | Menge/Unikat wieder **verfügbar**; andere dürfen buchen. Schlüssel §12.1 zurück. |
| **Verbrauch** | Schrauben, Diesel, Einweg — weg | Menge vom Einsatz/Bestand **abschreiben**, keine Rückgabe an den Pool. Restbestand sichtbar. |
| **Mehr brauchen** | Einsatz läuft, zu wenig da | 1) Rest im Lager **nachbuchen** (neuer Einsatz oder Menge erhöhen, Konfliktcheck). 2) Lager leer → **MW-Anfrage** (anderes Ressort abgeben, Reserve). 3) Nichts da → **Nachbedarf** in Beschaffung (Notfall-Position), nicht heimlich 2× denselben Gator. |

Überbezug (mehr geholt als Einsatz): Warnung an MW, analog «zu viel geholt» §12.

Kein zweites Formular «Planungsrunde» für Samstag-Nachmittag-Schrauben — der Einsatz-Dialog und «+ Menge / verbrauchen / zurück» am laufenden Einsatz. Nur wenn wirklich **neues** Gerät/Firma nötig ist, greift Beschaffung.

**Ist:** weder User-Buchung noch Verbrauch/Nachlieferung im Grossanlass. Pfadi-Aktivitäten haben Nachlieferung auf der Journey — Muster, nicht 1:1 kopieren (hier: Einsatz + Pool, nicht Activity-Pack-Tab).

---




## 13. Bewusst nicht hier

- Massenversand über SES / `ematchef.ch`
- Alle Prospects als `SupplierCompany` (B2B-Portal)
- Antworten vollautomatisch als «2 Gators zugesagt» parsen
- Grob- und Feinmengen still addieren
- `publish` / Gast-Pfadi-Depts, J+S (bleibt README)
- Sheet als Dauer-Master, sobald Anfragen in eMatChef leben

---

## 14. Offene Punkte

- Runden-Zweck explizit: **Material** / **Firmenvorschlag** / **Frei** (§8.1) vs. nur Name+Hinweis
- Fein-UI: Prefill aus Grob vs. «Verfeinern» in Mein Ressort
- Sidebar-Label «Planung» vs. Seite «Wünsche & Ideen»
- Eine Anfrage: ein Paket vs. mehrere Kategorien in einer Mail
- Gmail-Watch vs. periodisches Thread-Polling
- Ob Tab «Offerten» umbenannt oder in Anfragen-Antworten aufgeht
- Export CSV als Zwischenstufe vor OAuth
- Gmail-OAuth-Scopes minimal halten; wer darf ausser MW das Postfach sehen (nur Entwurfs-Status vs. Mail-Body)
- Vorlagen: HTML vs. reiner Text; Anhänge (Anlass-PDF) aus Drive oder Dept-Dateien

---

## 15. Umsetzungsreihenfolge (Vorschlag)

1. **Betrieb jetzt:** Pakete kürzen, E-Mail in der Firmenliste, Sheet-Entwürfe, Welle 1 aus Grobübersicht; eMatChef für Runden + Bedarf wie Ist.  
2. **App-Klarheit Grob/Fein:** Runden-Hinweis, Pool nach Runde, Wunsch-Identität + Delta, Position nach Anfrage einfrieren (auch ohne Gmail).  
3. **Anfragen-Tab** + Kontakt erst bei Nehmen + Zuteilung n→m.  
4. **Gmail OAuth** Entwürfe + Thread-Index.  
5. **Leih-Objekt + Materialübersicht + Einsatzliste ohne Doppelbuchung + Rückgabe Geber**; Ausgabe-Kontrolle Fahrzeuge; Herkunft Leih/Kauf/Weiterverkauf — vor Aufbau / Anlasswoche.

---

## 16. Ablauf: was noch fehlt oder nur am Rand steht

Der Kern (Pakete, Anfragen-Tab, Gmail-Entwürfe, Thread, Labels, Dank bei Absage/Zusage, Grob/Fein, Kontakt beim Nehmen) ist spezifiziert. Für einen vollständigen Betrieb fehlen vor allem:

### Nachfassen und Zeit

- **Erinnerung:** nach n Tagen ohne Antwort → Status Nachfassen + optional Entwurf (nicht auto-senden).
- **OOO / Bounce / Unzustellbar:** nicht als «Antwort erhalten» werten; E-Mail ungültig markieren.
- **Gesendet erkennen:** Entwurf in Gmail ≠ gesendet. App braucht «als gesendet markieren» oder Erkennung, sonst bleibt alles auf Entwurf.
- **Nicht nochmal anschreiben:** Firma mit Absage/Erledigt aus Welle 2 ausschliessen, ausser MW setzt «nochmals» (anderes Paket).

### Wer ausser Mail

- **Telefon / Besuch:** Status und Kurznotiz ohne Thread; keine Pflicht-Dank-Mail, optional trotzdem.
- **Nicht kontaktieren:** Sperre (bereits Partner, Beschwerde, Doppel in der Liste).
- **Doppelte Firmen / gleiche Mail:** beim Import erkennen.

### Mail-Inhalt und Versandpraxis

- **Anhang:** welche Datei (Anlass-PDF) an welcher Vorlage; zu grosse Merge-Liste.
- **Signatur / Absendername** vs. Google-Konto; Reply-To.
- **Sprache:** DE default; FR/IT-Vorlage später.
- **Gmail-Limits:** Entwürfe in Batches (nicht 300 auf einmal), Quota.
- **Token abgelaufen / MW wechselt:** OAuth erneuern, Postfach bleibt das Dept-Konto.
- **Auto-Reply** nicht als menschliche Antwort.

### Zuteilung ehrlich abschliessen

- **Zusage, aber nicht genommen:** eigene kurze Mail («wir sind anderweitig gedeckt, danke») — sonst nur Absage-Dank für echte Absagen. Sonst stehen Zusagen ohne Abschluss.
- **Teilzusage** pro Kategorie, nicht nur global.
- **Nach dem Nehmen:** verbindliche Eckdaten (Abholung/Lieferung, Ort, Ansprechperson, Versicherung/Haftung) — oft eigenes PDF/Gespräch, nicht nur Bestätigungsmail.

### Material nach «Erhalten» (siehe §12, §12.1, §12.2)

- Übergabeprotokoll (was, Zustand, Foto optional).
- Schaden während des Anlasses → Geber (bei Leih).
- **Rückgabe-Termin** und Checkliste «alles wieder beim Geber» (nur Leih).
- Fahrzeuge: Ausgabe-Kontrolle §12.1 (darf fahren, Ausweis, Schlüssel) — spezifiziert, noch nicht gebaut.
- Herkunft §12.2: Leih / Kauf / Kauf+Weiterverkauf mit kleiner Abschreibung.

Das gehört nicht alles in den ersten Bau; **Gesendet vs. Entwurf** und **Nicht-genommen-Mail** sind in §7.2 festgehalten — ohne sie reisst die Mail-Kette. Ohne §12.1/§12.2 reisst Ausgabe und Kostenwahrheit.

### Recht / Höflichkeit (kurz, kein Jurist)

- Vereinsabsender, keine irreführende «Bestellung».
- Keine Weitergabe der 300er-Liste. Löschen von Prospects nach dem Anlass (Aufbewahrung Zusagen/Leih).
- Kein Newsletter-Charakter; Anfrage ist konkret zum Anlass.

### Dashboard

- Zähler: Entwürfe / gesendet / wartet / Antwort / Zusage / genommen / Delta Fein.
- Ohne das steuert ihr nur über Gmail-Labels.

---

## Siehe auch

- [README.md](./README.md) — Gesamt-Spec (Department, Ressorts, §3.7 Beschaffung, §9 Runden, §10 Leihweise, §11 Übersicht)
- [kosten.md](./kosten.md) — Kostenübersicht Material & Logistik (Einkauf / Miete / Weiterverkauf, Zahler)
- [MVP.md](./MVP.md) — ursprünglicher Schnitt Phase 1–4; Beschaffung-Inhalt war Phase 5 und ist im Code weiter als die MVP-Datei
- Drive/Excel: Firmen (Bereich), Grobübersicht Material & Fahrzeuge; eine Logistik-Sheet-Vorlage (Anfragen / Kommunikation / Zusagen) als CRM-Vorbild ausserhalb der App, solange der Anfrage-Tab fehlt
