# Grossanlass

Aktuelle Ist-Dokumentation für department-übergreifende Grossanlässe (PFF, Kantonslager): **Grossanlass-Department** als Projekt-Container mit Dashboard, Planung, Beschaffung, Materialübersicht und Gast-Teilnehmern.

**Stand:** 1. Oktober 2026. **Diese Datei ist die Quelle für den implementierten Ist-Stand.**

**Leseregel:** [§0](#0-ist-stand) beschreibt, was der Code heute tut. Absätze mit **PLANNED** oder **FUTURE** sind nicht gebaut. [MVP.md](./MVP.md) und [20260823_New_concept.md](./20260823_New_concept.md) sind historische Planungsstände und keine zweite Ist-Quelle. Wo ein älterer Satz in dieser Datei §0 widerspricht, gilt §0.

**Verwandt:** [20260823_New_concept.md](./20260823_New_concept.md) (Partneranfragen, Grob/Fein, Gmail) · [materialfluss.md](./materialfluss.md) (Grossanlass-Materialfluss, Herkunft, Übergabe, Bestand, Rückbau) · [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) (Rollen MW/CMW/OK-L, Postfach `eMatChef`, Fahrten/Pack) · [business-clock.md](./business-clock.md) (Fachzeit, Demo-Zeitreise) · [bauprojekt-ort-helfer.md](./bauprojekt-ort-helfer.md) (Bauprojekt, GA-Ort, Aufgaben & Material) · [kosten.md](./kosten.md) (Kostenübersicht Material & Logistik) · [status.md](../activities/status.md) · [pack-workflow-rules.md](../activities/pack-workflow-rules.md) · [js-material/README.md](../activities/js-material/README.md) · [newUI/SPEC §19.3](../activities/newUI/SPEC.md#193-transport--touren--department-fuhrpark) (Fuhrpark) · [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) · [ui/vuetify-standards.md](../ui/vuetify-standards.md) · [nachrichtenzentrale.md](../nachrichtenzentrale.md)

---

## Kurzüberblick


| Was                             | Entscheidung                                                                                                                                                                                                              |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| UI-Name                         | **Grossanlass**                                                                                                                                                                                                           |
| `activity.type`                 | `'grossanlass'` — mehrere pro Dept mit `**grossanlass_role`**                                                                                                                                                             |
| Department                      | `is_grossanlass = true` — das Projekt («PFF 2027»)                                                                                                                                                                        |
| Anlegen                         | **Verwaltung → Abteilungen → Hinzufügen ▼** → «Grossanlass hinzufügen» (org/sub/sa) → **Dashboard**                                                                                                                       |
| **Kein** Activity-Wizard        | Beim Dept-Create: auto **1× Activity `anlass`**; weitere Phasen bei Bedarf                                                                                                                                                |
| Sidebar                         | **Ist:** [§0.1](#01-navigation). **Kein** `/activities`, **kein** Pfadi-`/accounting` |
| Ressorts                        | `**Group`** im Grossanlass-Dept                                                                                                                                                                                           |
| Struktur & Teilnehmer           | **Dept-weit** (ein Zyklus pro Anlass)                                                                                                                                                                                     |
| Materialien                     | **Ist:** Bestand unter Materialübersicht (Alles, Eigen, Leihweise, Gäste, J+S). Fahrzeuge eigener Menüpunkt `/{deptId}/fahrzeuge` |
| Materialübersicht               | Zuweisung / Ausgabe **pro Ressort & Unterkategorie**; Lager vs. draussen                                                                                                                                                  |
| **Historischer MVP**            | Erster Schnitt, nicht der heutige Umfang — [MVP.md](./MVP.md) |
| Wünsche & Ideen                 | Planung-Tab: Formulare **Material** / **Firmenvorschlag** / **Frei** — [§9](#9-wünsche--ideen-formulare), [Konzept](./20260823_New_concept.md#81-formulare-statt-planungsrunden). Ist: eine «Planungsrunde» = Material. |
| **Entwurf → Freigabe**          | CM plant alles; **Erst bei Freigabe** Einladungen an Gast-Depts                                                                                                                                                           |
| Ressort-Hierarchie              | **Ressort → Unterressort / Bauprojekt** via `group.parent_id` + `group.grossanlass_kind` (CM im MVP; Mitglieder §4.2)                                                                                                     |
| Gast-Pfadi-Dept                 | Weiter `**/activities`** — sichtbar **erst nach Freigabe**                                                                                                                                                                |
| **Keine Doppelspur**            | Bestehende Layout-, UI-, API- und Inbox-Patterns erweitern — [§20](#20-implementierungsprinzipien--keine-doppelspur)                                                                                                      |


Siehe auch: [MVP.md](./MVP.md) — erster Implementierungsschnitt · [20260823_New_concept.md](./20260823_New_concept.md) — Konzept Partneranfragen / Grob–Fein (Aug 2026) · [kosten.md](./kosten.md) — Kostenübersicht Einkauf / Miete / Weiterverkauf.

---

## 0. Ist-Stand

Quelle: Code Stand 1. Oktober 2026 (Entities, Controller, Sidebar, Routen). Kein Anwendungscode in diesem Abschnitt erfunden.

### 0.1 Navigation

Grossanlass-Dept: kein Pfadi-`/activities`, kein Pfadi-`/accounting`. Sichtbarkeit hängt von der Rolle ab ([rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md)).

**Materialwart-Sicht (MW/CMW, Zielbild der Navigation):** gruppierte Sidebar. Die übrigen Rollen behalten vorerst die flache Sidebar unten.

| Gruppe | Menü | Route | Hinweis |
| --- | --- | --- | --- |
| – | Dashboard | `/{deptId}` | Home der Rolle |
| Anlass | Ressorts | `/{deptId}/mein-ressort` | bestehende Seite «Mein Ressort», beim MW alle Ressorts |
| Anlass | Planung | `/{deptId}/planung` | inkl. Transporte (bleibt) |
| Anlass | Aufgaben | `/{deptId}/tasks` | zentrale Ausführungssicht, vorerst für alle Rollen gleich sichtbar (Rollen-Sichten folgen). **UI-Prototyp mit Demo-Daten** (`tasks/aufgaben` MW-Master, `tasks/meine` Helfer mobil). Bestehende Tabs Allgemein, Inventur und Drucken bleiben. Planung zeigt, was wann geplant ist, Aufgaben zeigt, was jemand konkret erledigen muss |
| Material | Beschaffung | `/{deptId}/beschaffung` | |
| Material | Material | `/{deptId}/material` | bisher «Materialübersicht», nur umbenannt |
| Logistik | Logistik | `/{deptId}/logistik/disposition` | **UI-Prototyp mit Demo-Daten:** Leitstand für alle Transportbedarfe (Beschaffung, Planung, Packen, Ressort, Retour, Werkstatt), Disponieren in Touren mit Stopps, Live-Fälle. Keine eigenen Rechte |
| Logistik | Fahrzeuge | `/{deptId}/fahrzeuge` | |
| Betrieb | Werkstatt | `/{deptId}/werkstatt` | eigenständig: betrifft Material und Fahrzeuge |
| Finanzen | Kosten | `/{deptId}/kosten` | |
| Kommunikation | Nachrichten | `/{deptId}/notifications` | |
| unten | Grossanlass verwalten | `/{deptId}/einstellungen` | bisher «Einstellungen» |
| unten | Konfiguration | `/{deptId}/settings` | |
| unten | UI Sandbox | `/{deptId}/dev/ui-playground` | nur Development |

Weitere Rollen (flache Sidebar, Ist):

| Menü | Route | Wer (kurz) |
| --- | --- | --- |
| Dashboard | `/{deptId}` | je Home der Rolle |
| Mein Ressort | `/{deptId}/mein-ressort` | Bereich im Baum |
| Meine Einsätze | `/{deptId}/meine-einsaetze` | Helfer |
| Planung | `/{deptId}/planung` | wer die Anlass-Übersicht sieht |
| Beschaffung | `/{deptId}/beschaffung` | Delegierte mit `can_procure` landen auf Offerten |
| Anfragen | `/{deptId}/beschaffung/anfragen` | Komm/Spon als eigener Sidebar-Punkt |
| Kosten | `/{deptId}/kosten` | Anlass-Übersicht (OK-Leitung) |
| Fahrzeuge | `/{deptId}/fahrzeuge` | Beschaffung oder Bereichsleitung |
| Materialübersicht | `/{deptId}/material` | Material-Übersicht der Rolle |
| Werkstatt | `/{deptId}/werkstatt` | wer Beschaffung verwaltet |

Logistik → Disposition ist der operative Leitstand, Planung → Transporte bleibt die Vorausplanung. Die Gruppierung für die übrigen Rollen folgt in weiteren Schritten. Logistik ist derzeit nur Shell (Rollen wie Übersicht, ohne Basisrollen); Planung → Transporte bleibt unverändert bestehen.

Planung-Tabs: Wünsche, Bauaufträge, Transporte, Belegung, Konflikte. Darunter Stammdaten, Ressorts, Standorte, Kategorien, Anfragen-E-Mail, Struktur, Freigabe.

**Material → Wareneingang und Pack (UI-Stand):** Wareneingang ist ein schneller MW-Ablauf (Ware finden oder scannen, Menge einbuchen, Annahmeort, direkt «Label drucken», Restmenge bleibt sichtbar). Pack geht vom Bedarf pro Ressort/Bauprojekt aus (Packbar, Teilweise packbar, Wartet auf Material, Fertig) und erlaubt Teilpacken als eigene Palette mit Nummer, Ziel, Inhalt, QR-Label und «Fahrauftrag absenden». **UI-Prototyp:** Der Bedarfs-Planer, das Label und «Fahrauftrag absenden» laufen mit lokalen Demo-Daten (`views/grossanlass/packen/`). Der bestehende Pack-Bereich bleibt darunter. Planung → Transporte bleibt für die Vorausplanung, Packen → Fahrauftrag ist für spontane operative Transporte; Logistik übernimmt später Disposition und Durchführung.

**Material → Rückbau (UI-Stand):** Der frühere Tab «Rückgabe Firma» heisst jetzt «Rückbau» (`/{deptId}/material/rueckbau`, `/material/retour` leitet um). Nach dem Einsatz entscheidet der MW pro Position und Bauprojekt den Verbleib: Lager, Weiterverwenden, Weiterverkauf, Rückgabe Firma, Entsorgung oder bei Schäden Werkstatt. Der vorgesehene Verbleib kann später aus Beschaffung/Offerte kommen (Kauf → Lager, Kauf → Weiterverkauf, Leihe → Rückgabe Firma, Verbrauch → Entsorgung). Die bestehende Rückgabe-Firma-Tabelle bleibt im Filter «Rückgabe» als «Laufende Rückgaben». **UI-Prototyp:** Positionen, Rückgabepack, Verkaufsliste, Vorschau für qr.ematchef.ch und Entsorgung laufen mit lokalen Demo-Daten (`views/grossanlass/rueckbau/`), ohne Shop und ohne Backend. Kreislauf: Beschaffung/Offerte → vorgesehener Verbleib → Einsatz → Rückbau → Verbleib → ggf. Disposition → abgeschlossen.

**Material → Weiterverkauf (UI-Stand):** Eigener Tab (`/{deptId}/material/weiterverkauf`) mit Angebote, Anfragen & Reservierungen und Verkauft. Angebote haben Menge, Preis, Zustand, Beschreibung, Bild-Platzhalter, «verfügbar ab», Abholort und Sichtbarkeit (nur eMatChef-Abteilungen oder öffentlich), dazu veröffentlichen und pausieren; pro Angebot stehen verfügbar, reserviert, verkauft und die Herkunft aus dem Rückbau. Anfragen lassen sich ablehnen, reservieren und die Übergabe vorbereiten (Selbstabholung oder Transport an die Disposition). Der Verbleib «Weiterverkauf» im Rückbau legt ein Angebot als Entwurf an. **UI-Prototyp:** Die Demo-Seite im Stil von qr.ematchef.ch ist direkt aufrufbar unter `/qr-demo/material` (Detail `/qr-demo/material/:offerId`), ohne App-Sidebar, mit Suche, Filtern, Auswahl und Interesse-Anmeldung. Beim Reservieren gibt es drei Wege: «Mit eMatChef anmelden» (Demo-Login, eigene Abteilung, normaler Abteilungs-Transfer), «eMatChef für unsere Abteilung einrichten» (Anfrage bleibt bestehen, Demo-Onboarding, danach Verknüpfung mit der neuen Abteilung) und «Ohne eMatChef anfragen» (Formular mit Name, Organisation, E-Mail, optional Telefon, Menge, Bemerkung). Die MW-Verwaltung unterscheidet eMatChef-Abteilung, «eMatChef wird eingerichtet» und externer Interessent. Bei externem Verkauf endet der Ablauf nach bestätigter Übergabe, bei Abteilungen folgt die Materialübernahme beim Käufer-MW (noch nicht gebaut, nur als Hinweis). Für Angebote «nur Abteilungen» ist ein (Demo-)Login oder die Einrichtung nötig. Der Button «Zur Verwaltung» erscheint nur in Test- und Dev-Umgebungen. Es gibt keinen Shop, keine Zahlung und kein Backend (`views/grossanlass/weiterverkauf/`).

**Weiterverkauf ist nicht nur Überschuss:** Material und Geräte dürfen bewusst gekauft, im Anlass benutzt und danach gebraucht weiterverkauft werden (z. B. 4 Akkuschrauber kaufen, alle 4 einsetzen, alle 4 verkaufen). Deshalb wird nicht «gekauft minus benötigt» gerechnet; das Angebot trennt **1. Verwendung während des Anlasses** (gekaufte und verwendete Menge) von **2. geplantem Verbleib nach dem Anlass** (geplante Verkaufsmenge, die der ganzen gekauften Menge entsprechen darf, voraussichtlich verfügbar ab, erwarteter Zustand: neuwertig / gebraucht / Gebrauchsspuren möglich / nach Einsatz zu prüfen, optionaler Preis, frühzeitig veröffentlichen ja/nein). Phasen: geplant → wird eingesetzt → nach Rückbau bestätigt. Vor dem Rückbau zeigt die öffentliche Seite «Wird aktuell eingesetzt», «Voraussichtlich verfügbar ab …», «Gebraucht / Gebrauchsspuren möglich» und «Endgültiger Zustand und Menge werden nach Einsatz bestätigt». Beim Rückbau wird der tatsächliche Zustand je Menge bestätigt (gut, Gebrauchsspuren, beschädigt, nicht mehr verkaufbar, Werkstatt); verkaufbar sind gut und Gebrauchsspuren. Die geplante Menge wird mit der verkaufbaren abgeglichen, bestehende Reservierungen bleiben sichtbar, bei Abweichung oder zu vielen Reservierungen erscheint eine Warnung. Es gibt eine einzige Weiterverkaufslogik (`gaVerkaufMock.ts`), der Rückbau nutzt sie.

**Helferpool (UI-Stand):** Neue Seite `/{deptId}/helferpool` (Gruppe Anlass) mit den Ansichten Helfer, Zeitplan und Fähigkeiten für MW, LW und Bereichsleitung. Sie zeigt pro Person Verfügbarkeit (mehrere Zeitfenster), Fähigkeiten, Führerausweise separat, Bemerkung, Zuteilungen und den Status frei / teilweise belegt / belegt / nicht verfügbar. Filter: Zeitraum, Ressort/Bereich, Fähigkeit, Führerausweis, Verfügbarkeit, Abteilung. Ein Detail-Drawer zeigt Verfügbarkeit, Fähigkeiten, Zuteilungen und freie Zeitfenster. Ein Aufgaben-Bedarf («4 Helfer Holzbau, 04.11. 08:00–12:00») zeigt die fachlich und zeitlich passenden Personen und erlaubt «Helfer zuteilen». **UI-Prototyp:** Zuteilungen kommen aus der Aufgaben-Demo (Verantwortliche) und der Disposition-Demo (Fahrer), «Helfer zuteilen» nutzt dieselbe Aufgaben-Logik, daher erscheint die Zuteilung beim Helfer und beim Auftrag. Keine automatische Optimierung, kein Backend (`views/grossanlass/helferpool/`).

**Material → Ausgabe (UI-Stand):** Die Ausgabe ist der schnelle Lagerschalter mit den Ansichten Ausgabe, Offen bei Personen und Verlauf. Ablauf: Empfänger per Benutzerkarte/QR oder Suche, optional Bauauftrag/Ressort oder «kein Auftrag», Material per Scan oder Suche, Menge mit +/−, bestätigen; danach ist der Schalter sofort bereit für den nächsten Scan. Verbrauchsmaterial wird nur ausgegeben, rückgabepflichtiges Material merkt Person und Auftrag und erscheint unter «Offen bei Personen» (Rückgabe buchen, an andere Person übergeben, anderem Auftrag zuordnen, überfällig markiert). Bei gewähltem Bauauftrag zeigt die Schnellausgabe geplant, ausgegeben und noch benötigt; zusätzliche Artikel bleiben möglich. Warnungen: Bestand nicht ausreichend, bereits gepackt, bereits ausgeliehen, defekt/Werkstatt, Rückgabe überfällig, unbekannter Artikel. Ausgabe ist nicht Packen: gepacktes Material ist nicht frei ausgebbar. **UI-Prototyp:** Der Schalter läuft mit lokalen Demo-Daten (`views/grossanlass/ausgabe/`); die bestehenden geplanten Ausgaben mit den echten Benutzerkarten bleiben darunter erhalten.

**Planung → Aufträge (UI-Stand):** Neuer Tab `/{deptId}/planung/auftraege` mit der gemeinsamen Auftragsansicht Alle | Aufträge | Bauaufträge. Der Typ steht in Listen und Karten als Badge «Auftrag» bzw. «Bauauftrag». «Auftrag erstellen» bietet «Normaler Auftrag» (Titel/Beschreibung, Ressort/Bereich, Zeitraum, Standort optional, Verantwortliche, Helferbedarf mit Fähigkeiten, Materialbedarf, Werkzeug/Fahrzeuge, Transportbedarf, Aufgaben, Status) und «Bauauftrag» mit denselben Feldern plus Bauprojekt, konkretem Bau-/Einsatzort, Aufbauzeitraum, Maschinen/Fahrzeugen, Material- und Baufortschritt sowie Rückbauzeitraum und -informationen. Beide Typen sind in den Demos mit Aufgaben, Helferpool, Pack, Ausgabe und Logistik verknüpfbar; Fortschritt und Helferbestand ergeben sich aus den verknüpften Aufgaben und dem Packen, beim Erstellen entstehen die Aufgaben in der Aufgaben-Demo. Der bestehende Tab «Bauaufträge» (echte Daten) bleibt unverändert. **Zeiten:** frühester Start, Deadline, gewünschter und geplanter Zeitraum; beim Bauauftrag zusätzlich Aufbauzeitfenster, «fertig bis» und Rückbauzeitfenster (mit Hinweis bei Überschreitung). **Ressourcenanforderungen:** pro Auftrag mehrere Bedarfe (Kran, Stapler, Maschine, Fahrzeug/Anhänger, Spezialwerkzeug, Helfer/Fähigkeit) mit Dauer, frühestens ab, spätestens bis, Wunschfenster, flexibel ja/nein, Anzahl, Bemerkung und Status Bedarf / Vorschlag / eingeplant. Ein simulierter Planungsvorschlag (z. B. «Kran bereits bei Bühne Nord 04.11. 08:00–10:00, Vorschlag Bar West 04.11. 10:30–12:30») bietet «Vorschlag übernehmen», «Andere Zeit» (mit Prüfung gegen Fenster, Dauer, Belegung und Abhängigkeit) und «Noch offen lassen»; es gibt keine automatische Optimierung. **Abhängigkeiten:** Aufgaben dürfen optional von anderen abhängen (z. B. Material geliefert → Holzkonstruktion → Kranarbeit → Dachmontage → Elektrik → fertig); die UI zeigt, was auf was wartet. Die Anforderungen liegen in einer gemeinsamen Struktur (`views/grossanlass/ressourcen/`), die die Disposition schon heute schreibgeschützt anzeigt: Planung zeigt, wann die Ressource sinnvoll wäre, die Logistik übernimmt den konkreten Einsatz. **UI-Prototyp:** lokaler Demo-State (`views/grossanlass/auftraege/`). Die Abbildung auf bestehende Backend-Entities wird erst beim API-Abgleich entschieden.

**Früher Weiterverkauf aus Beschaffung (UI-Stand):** Der Verbleib nach dem Anlass wird schon bei Offerte, Absprache oder Kauf festgehalten («Aus Beschaffung planen» im Weiterverkauf): eigenes Lager, anderes Projekt/Weiterverwenden, Weiterverkaufen, Verbrauch/Entsorgung oder noch offen. Bei «Weiterverkaufen» kommen geplante Verkaufsmenge (darf der ganzen Kaufmenge entsprechen), voraussichtlich verfügbar ab, erwarteter Zustand, optionaler Preis, optionaler Abholort und «bereits jetzt veröffentlichen» dazu. Das Angebot unterscheidet geplant, reserviert, nach Rückbau tatsächlich verfügbar und verkauft/übergeben; schon während des Anlasses dürfen andere Departments und Externe reservieren. Beim Rückbau werden Menge und Zustand bestätigt, bereits reserviertes Material wird sichtbar und kann direkt für die Käufer bereitgestellt werden, statt wieder eingelagert zu werden. Es gibt weiterhin nur eine Weiterverkaufslogik (`gaVerkaufMock.ts`).

**Displays und Leitstände (UI-Stand):** Konfiguration unter `/{deptId}/displays` (Name, Typ, Standort, optional Ressort/Bereich/Bauprojekt-Filter, simulierte Rotation), TV-Ansicht unter `/display-demo/:screenId` (ohne App-Sidebar, Vollbild, grosse Schrift). Typen: Material-Leitstand (Übersicht, Packen, Fehlmaterial, Bereit/Transport, Wareneingang; FEHLT ≠ vorhanden nicht gepackt ≠ gepackt wartet auf Transport ≠ vor Ort; Ursache bei Fehlmaterial: bestellt, Abholung geplant, noch nicht beschafft, teilweise, verspätet, Termin unbekannt), Logistik-Leitstand (Transporte, Fahrten, Touren, Fahrer/Fahrzeuge, ETA, Verspätungen, Probleme, Kran/Stapler und andere Ressourcen), Projektkarte (grün/orange/rot je Projekt, Detail mit Fortschritt, Deadline, aktueller Aufgabe, Helfern, Material, Packs, Transporten, Ressourcen, Blockaden) und Gesamt-Leitstand. Alle Displays lesen denselben Demo-State wie die normalen Seiten; Aktionen dort erscheinen sofort im Live-/Aktivitäten-Feed und prominent als kurzes Banner. Fahrten haben drei Zustände: kein Tracking (Status, Startzeit, ETA), ETA-Simulation (geschätzter Fortschritt auf der Route) und Live-GPS (nur als zukünftiger Zustand, nicht implementiert). Es gibt keine WebSockets, kein SSE und kein GPS.

**Grossanlass-Hilfe (IST):** Der globale Hilfe-Button öffnet in einem Grossanlass-Department auf GA-Seiten ein Modal mit Seitenbeschreibung, häufigen Fragen und einem Link (neuer Tab) auf die ausführliche Hilfe `/{deptId}/help/ga/:topic?` (alter Pfad `/ga-hilfe/:topic?` leitet weiter). Die Kapitel hängen an Routennamen in `frontend/src/config/gaHelp.ts` (Registry); GA-Seiten ohne eigenes Kapitel zeigen den Überblick. Die Gast-Sicht-Vorschau hat ein eigenes Gast-Kapitel. Alle anderen Seiten, auch jene in Gast-Departments, behalten die Department-Hilfe (`/{deptId}/help/department/:topic?`, Tours unverändert unter `help/tours`) unverändert; die alten Pfade `help/dokumentation` und `help/overview` leiten weiter. Der Sidebar-Eintrag «Hilfe» führt im Grossanlass-Department zur GA-Hilfe (`/{deptId}/help/ga`); normale und Gast-Departments behalten ihren Hilfe-Eintrag. Die Hauptnavigation scrollt bei niedrigen Fenstern, Zoom oder offenen Untermenüs nur im Bereich `.sidebar-nav`; Logo und Footer bleiben stehen (`styles/sidebar.css`). Hover-Hilfe (Touch: Tippen) an einzelnen Feldern: `GaHelpHint` (Texte `gaHelp.fields.*`); heute bei «Genug vorhanden» sowie Cash und Netto in Kosten. Texte: Namespace `gaHelp.*` in `de.json` (Quelle) und `en.json`; fr/it und Org-Varianten fallen über die bestehende Locale-Kette zurück. Neue GA-Seite mit Kapitel: Eintrag in der Registry und Texte unter `gaHelp.topics.<id>`; der Test `gaHelp.spec.ts` prüft Routennamen und Schlüssel. **PLANNED:** Hilfekontext für geöffnete Formulare (Dialoge), Kapitel für die noch als UI-Prototyp laufenden Seiten.

Beschaffung-Tabs: Bedarf, Anfragen, Offerten, Zusagen, Bestellungen. Der frühere Tab **Erhalten** leitet auf Materialübersicht → **Wareneingang** um.

Materialübersicht: Bestand (Alles, Eigen, Leihweise, Gäste, J+S), Artikel, Wareneingang, Ausgabe, Pack, Retour. Belegung, Konflikte, Bauaufträge und Transporte liegen unter Planung, nicht als eigene Übersicht-Tabs.

### 0.2 Baum: Ressort, Bereich, Bauprojekt

`group.grossanlass_kind`:

| Gespeichert | UI `node_type` | Bedeutung |
| --- | --- | --- |
| `ressort` | `ressort` | Wurzel |
| `bereich` | `unterressort` | mittlere Ebene. Anlegen akzeptiert auch den Alias `unterressort` und speichert `bereich` |
| `teilbereich` | `bauprojekt` | Bauprojekt |

Teilen: ein Bereich kann mit einem anderen Ressort geteilt werden (`POST/DELETE …/groups/{id}/shares`). Erlaubt zwischen Cousin-Ressorts; nicht mit sich selbst und nicht im eigenen Ast.

**Bauvorhaben-Status** an Bereich und Bauprojekt (`build_status`), nicht an der Wurzel. Werte: `planned`, `quoted`, `build`, `use`, `teardown`, `done`, `aborted`. Ein gesetzter Wert gilt. Ohne gesetzten Wert leitet das Frontend ab:

- kein Projektfenster, oder heute vor dem Fenster: `planned`
- heute nach dem Fenster: `done`
- im Fenster und ein aktiver Kalenderzeitraum `abbau` überlappt: `teardown`
- im Fenster und ein aktiver Kalenderzeitraum `grossanlass` überlappt: `use`
- im Fenster **ohne** solchen aktiven Zeitraum: `planned` (auch das Kalenderlabel `aufbau` setzt nicht `build`)
- `procurement_progress` `quoted` oder `build` hebt den abgeleiteten Status an; Meldungen der Kinder können ihn überschreiben

### 0.3 Bauprojekt, Karte, Aufgaben, Fahrzeugwunsch

GA-Ort, QR `/i/ga/` (Alias `/i/p/`), Leaflet-Karte und optionales Geländeplan-Overlay: [bauprojekt-ort-helfer.md](./bauprojekt-ort-helfer.md). Ergänzend im Code:

- **Polygon** am GA-Ort (`polygon`: Liste `lat`/`lng`), pflegbar über den Ort und über den Gruppen-Datensatz.
- **Aufgaben** (`department_grossanlass_task`): Titel, Beschrieb, Start (`starts_at`), Dauer (`duration_minutes`), verantwortliche Person (`assignee_user_id`). Das sind Zeitblöcke an der Aufgabe, keine zweite Aufgabenliste. Nicht im Bedarf-Pool.
- **Fahrzeugwunsch** (`department_grossanlass_vehicle_need`) am Bauprojekt: Bezeichnung, Aufgabe, optionale Kategorie (`category_label`), Start, Dauer, optionale Beschaffungsposition (`procurement_line_id`). API `…/groups/{groupId}/vehicles`.

### 0.4 Wünsche

Material-Wunschzeile zusätzlich zu Ressort, Art, Bezeichnung, Menge, Ort, Zeitraum:

| Feld | Verhalten |
| --- | --- |
| `quantity_unit` | Einheit, Default `Stk` |
| `enough_on_hand` | «genug vorhanden». Herkunft `stock` (Eigenbestand, Detailtext) oder `commitment` (Zusage im selben Dept) |
| `self_organized` | selbst organisiert: fällt aus dem Bedarf-Pool; eine schon angelegte Bedarfszeile dazu wird verworfen |
| `pickup_need`, `pickup_place` | Abholung beim Partner |
| `return_needed` | Zurückbringen |

Dieselben Logistikfelder und die Einheit liegen auch auf der Bedarfsposition. Eine automatisch angelegte Einzelposition ohne Kategorie, ohne angefragte Menge, ohne Offerte und ohne Fahrzeugwunsch löst sich wieder auf; der Wunsch bleibt eingereicht, bis er gebündelt wird. Kategorien, Offerten und Bestellungen sind gebaut ([§3.7](#37-beschaffung--budget--kosten)).

**PLANNED:** eigene `round_type`-Werte `company_tip` und `free` als getrennte Formularzwecke. Ist bleibt der Material-Zweck; Grob/Fein ist Stufe am Wunsch.

### 0.5 Beschaffung, Anfragen, Wareneingang, Kosten

Beschaffung ist implementiert, keine leere Shell: Bedarf bündeln, Kategorien, Offerten, Offerte wählen, Bestellung, Zusagen, Direkt-Bedarf.

Direkt-Beschaffung: `source=direct`, `self_organized` an der Position, Freigabe `group_membership.can_procure` im Ressort-Zweig. Delegierte pflegen Direkt-Bedarf und eigene Offerten; wählen, budgetieren und bestellen bleibt MW/CMW.

Anfrage (`department_grossanlass_inquiry`):

- `asked_via` Kanal (Anruf/Mail), `asked_at`
- `asked_lines` Positionen, die bei diesem Kontakt angefragt wurden
- Bedarfsposition merkt `asked_wish_ids` und `quantity_asked` (welche Wünsche schon in einer Anfrage waren)
- Bedarfsposition merkt Partner oder Kauf; Standard ist offen
- Offerte speichert den Logistikweg (Abholen, Zurückbringen)

Postfach: gemeinsames Konto, Label-Wurzel `eMatChef`, Anbieter-Feld `gmail` (Default) oder `outlook`. Gmail verbinden, Vorlagen, Entwürfe, Sync und Senden sind die produktive Spur ([rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) R3).

**PARTIAL / DEVELOPMENT:** Outlook. Es gibt `GET …/grossanlass/outlook/connect` und einen Button in den Anfragen-E-Mail-Einstellungen mit Dev-Badge. Das ist kein fertiges zweites Postfach.

Mail-Adressen: `user_email_alias` (weitere Adressen am User) und `membership.notification_email` (Empfängeradresse dieser Department-Mitgliedschaft). Die Gmail-Öffnen-URL einer Anfrage bezieht sich auf das Department der Anfrage.

Wareneingang (**IST**, mengenbasiert): Liefertermin an Bestellung und Offerte (Lieferzeit in Tagen). Route `/{deptId}/material/wareneingang` bucht Teil- oder Restmengen pro Charge als Bewegung `received` (Menge, Annahmeort, Notiz, Person, Zeitpunkt); erhaltene Menge und Fehlmenge werden berechnet, Überlieferung wird abgelehnt. `POST …/lines/{id}/received` bucht über dieselbe Charge-Bewegung; erhaltene Mengen je Wunsch und Positionsstatus werden daraus abgeleitet. Abhol-/Liefer-Einsatz an der Charge (`pickup_einsatz_id` / `delivery_einsatz_id`) ist noch kein Fahrauftrag. Details: [materialfluss.md §5–6, §14](./materialfluss.md#6-wareneingang-ist-mengenbasiert).

Kosten-Ledger (Einkauf, Miete, Leih, Weiterverkauf, Zahler): [kosten.md](./kosten.md), Phasen K1–K6 umgesetzt. API `…/beschaffung/costs` und `…/budgets`.

### 0.6 Materialübersicht, Einsätze, Werkstatt

Materialübersicht ist gebaut: Bestand im Lager, Zuordnung, Ausgabe, Pack, Retour, Wareneingang. Einsätze, Konflikte und Belegung: [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md) (Checkbox Fahrt, Pack, GA-Ort-Scan).

Werkstatt: eigene Fälle `department_grossanlass_workshop_case` (nicht das Pfadi-Ticket). Herkunft `own` | `loan` | `buy`, Weg `repair` | `owner`, Status `open` | `in_progress` | `waiting_owner` | `done` | `cancelled`. API `…/grossanlass/workshop-cases`.

### 0.7 Freigabe und Teilnehmer

Implementiert, nicht mehr nur geplant. Planung → Struktur / Freigabe.

`POST /api/departments/{id}/grossanlass/publish` setzt `department_grossanlass_config.status = published`, wenn der Anlass-Zeitraum gesetzt ist. Teilnehmer mit Status `planned` werden `pending` und `syncGrossanlassParticipantInvites()` schreibt die Inbox. Weitere Endpunkte: Teilnehmer suchen, anlegen, ändern, entfernen; Unterlager anlegen, ändern, entfernen; `POST …/invites/{participantId}/respond`.

**PLANNED:** E-Mail oder Push an Gast-Departments (die Inbox-Einladung ist Ist). Rücknahme der Freigabe ist nicht gebaut.

### 0.8 Weiter PLANNED oder FUTURE

Nicht mit dem Ist vermischen:

- Formularzwecke `company_tip` und `free` als eigene Rundentypen
- J+S-Vorgaberunde und automatischer J+S-Versand
- Gast sieht Aufbau/Abbau/Vorevent in der Pfadi-Aktivitätsliste
- Outlook als produktives Postfach (heute nur Dev-Connect)
- Schichten und Volunteer-Dispatch ([rollen-postfach-fahrten.md §12](./rollen-postfach-fahrten.md#12-offene-punkte-nicht-blockierend))

---

## Inhaltsverzeichnis

0. [Ist-Stand](#0-ist-stand)
1. [Zielbild](#1-zielbild)
2. [Grossanlass-Department anlegen](#2-grossanlass-department-anlegen)
3. [Navigation & Dashboard](#3-navigation--dashboard)
  - [3.1 Sidebar (Grossanlass-Dept)](#31-sidebar-grossanlass-dept)
  - [3.4 Hauptmenü — Sortierung & Sichtbarkeit](#34-hauptmenü--sortierung--sichtbarkeit)
  - [3.5 Routen & leere Seiten (Shell-first)](#35-routen--leere-seiten-shell-first)
  - [3.6 Einstellungen (Grossanlass-Dept)](#36-einstellungen-grossanlass-dept)
  - [3.7 Beschaffung — Budget & Kosten](#37-beschaffung--budget--kosten) — Soll-Kosten: [kosten.md](./kosten.md)
4. [Ressorts = Gruppen](#4-ressorts--gruppen)
5. [Struktur & Teilnehmer (Dept-weit)](#5-struktur--teilnehmer-dept-weit)
6. [Activities & Rollen](#6-activities--rollen)
7. [Planung & Entwurfmodus](#7-planung--entwurfmodus)
8. [Teilnehmer, Einladungen & Inbox](#8-teilnehmer-einladungen--inbox)
9. [Wünsche & Ideen (Formulare)](#9-wünsche--ideen-formulare)
10. [Materialien — Zentral erfassen](#10-materialien--zentral-erfassen)
11. [Materialübersicht & Ausgabe](#11-materialübersicht--ausgabe)
12. [J+S](#12-js)
13. [Lebenszyklus & Archivierung](#13-lebenszyklus--archivierung)
14. [Datenmodell](#14-datenmodell)
15. [API](#15-api)
16. [Implementierungsphasen](#16-implementierungsphasen)
17. [Berechtigungs-Matrix](#17-berechtigungs-matrix)
18. [Offene Fragen](#18-offene-fragen)
19. [Out of scope (v1)](#19-out-of-scope-v1)
20. [Implementierungsprinzipien — keine Doppelspur](#20-implementierungsprinzipien--keine-doppelspur)

---

## 1. Zielbild

Grossanlass ist **kein** camp/event und **kein** erweitertes event. Es ist ein **Department-Projekt** mit:

- Zentrallager + Leihgaben + Fuhrpark
- Ressorts (Groups), Unterlager, eingeladene Pfadi-Departments
- Wünsche & Ideen (Formulare) **vor** dem Event — [§9](#9-wünsche--ideen-formulare)
- Mehrere **Activities** (Anlass, Aufbau, Abbau, Sitzungen, …) für Material & Pack pro Zeitraum
- Immer sichtbar: **was im Lager ist vs. zugewiesen vs. draussen** — gesamt und pro Ressort

```
Grossanlass-Dept «PFF 2027»
├── Entwurf (draft)        CM: Ressorts, Mitglieder, Runden, Struktur — keine Einladungen
├── Freigabe (publish)     → Einladungen an Gast-Depts
├── Dashboard              Status, Runden, Lager/draussen
├── Materialien            Eigen | Leihweise | Fahrzeuge (Stammdaten)
├── Planung                Struktur, Ressorts, Runden, Activities
├── Materialübersicht      Ausgabe pro Ressort / Teilbereich
└── Activities             anlass · aufbau · abbau · vorevent · nach_event
```

**Pfadi-Gast:** unverändert unter eigenem `/activities` (ein Grossanlass-Eintrag).

---

## 2. Grossanlass-Department anlegen

### 2.1 Wer darf was (Kurz)


| Aktion                         | GL (org/sub/sa) | CM (Materialwart) | GM (Pfadi) |
| ------------------------------ | --------------- | ----------------- | ---------- |
| Grossanlass-Dept erstellen     | ✓               | —                 | —          |
| MW ernennen                    | ✓               | —                 | —          |
| Betrieb (Material, Planung, …) | ✓               | ✓                 | —          |
| Einladung annehmen             | —               | —                 | ✓          |


Details: [§17](#17-berechtigungs-matrix).

### 2.2 Entry Point

```
Verwaltung → Abteilungen
  [ Hinzufügen ▼ ]                    nur org / sub / sa
    ├ Abteilung hinzufügen            → bestehendes DepartmentModal
    └ Grossanlass hinzufügen          → Wizard §2.3
```

Implementierung: `[DepartmentsSettingsView.vue](../../frontend/src/views/settings/DepartmentsSettingsView.vue)` — Menü «Hinzufügen» mit «Abteilung hinzufügen» und «Grossanlass hinzufügen». «Grossanlass hinzufügen» nur bei Rolle org/sub/sa (nicht nur `departments.edit`).

Im **Pfadi-Dept:** kein Typ `grossanlass` im Aktivitäten-Wizard.

### 2.3 Erstell-Wizard («Grossanlass hinzufügen»)


| #   | Feld                                  | Pflicht   | Speicherung                                                         |
| --- | ------------------------------------- | --------- | ------------------------------------------------------------------- |
| 1   | **Name** des Grossanlasses «PFF 2027» | ja        | `department.name` (= Anlass-Name)                                   |
| 2   | **Anlassdatum von**                   | ja        | §2.4                                                                |
| 3   | **Anlassdatum bis**                   | nein      | §2.4 — wenn leer: gleicher Tag wie «von»                            |
| 4   | **Organisation**                      | ja        | `department.organisation_id` — org/sub: vorausgewählt/eingeschränkt |
| 5   | **Übergeordnetes Department**         | nein      | `department.parent_id` — wie normale Abteilung                      |
| 6   | **Chief-MW (User)**                   | empfohlen | `membership` `role: mw`, `is_primary: true` — Warnung wenn leer     |


**Nicht im Wizard** (Planung nach Create): Ressorts, Planungsrunden, Teilnehmer, Ort, Notizen, Freigabe. Danach folgt die verbindliche Ersteinrichtung ([§2.7](#27-ersteinrichtung-und-freigabe-ist)).

User-Auswahl Chief-MW: gleiches Pattern wie `[DepartmentModal.vue](../../frontend/src/components/DepartmentModal.vue)` (Org-User-Suche).

### 2.4 Anlegen — Backend & Datums-Speicherung

**Nach «Erstellen»:**

```
POST /api/departments/grossanlass
  {
    name, organisation_id, parent_id?,
    planned_event_start, planned_event_end?,
    chief_mw_user_id?
  }

→ department.is_grossanlass = true
→ department_grossanlass_config
     status: draft
     struktur_modus: offen
     planned_event_start, planned_event_end    ← Dept-Anker (Planungsreferenz)
→ activity grossanlass, grossanlass_role: anlass
     name = department.name
     usage_start = planned_event_start         ← Kopie, operativ für Pack/Material
     usage_end   = planned_event_end
     status: draft
→ config.main_activity_id = activity.id
→ membership MW (falls chief_mw_user_id gesetzt)
→ Kostenstellen-Bootstrap wie normales Dept
→ Redirect: /:deptId/dashboard
```

**Datums-Logik:**


| Feld                                            | Zweck                                                                                                         |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `department_grossanlass_config.planned_event_`* | **Dept-Infos** — bleibt als Planungs-Anker, auch wenn Activity später verfeinert wird (Aufbau/Abbau getrennt) |
| `activity.usage_start` / `usage_end`            | **Operativ** — gleiche Werte beim Create; in Planung → Stammdaten verfeinerbar                                |


Validierung: `planned_event_end >= planned_event_start` (falls gesetzt).

**Kein** camp/event-Activity-Wizard, **kein** 4-Schritt-Setup.

### 2.5 Chief-MW — Benachrichtigung & Zugang

Wenn beim Create ein **Chief-MW** (`chief_mw_user_id`) gesetzt wird:


| Kanal      | Inhalt                                                      | Pattern                                                                                                                           |
| ---------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **E-Mail** | «Du wurdest Materialchef von ‹PFF 2027›» + Link             | wie `[DepartmentController::addMember](../../backend/src/Controller/DepartmentController.php)` → `sendDepartmentMemberAddedEmail` |
| **Inbox**  | Nachricht an User mit Deep-Link `/{departmentId}/dashboard` | neue Kategorie z. B. `grossanlass_mw_assigned` in `inbox_message`                                                                 |


**Inbox-Payload (Vorschlag):**

```json
{
  "department_id": "…",
  "department_name": "PFF 2027",
  "role": "mw",
  "is_grossanlass": true,
  "dashboard_url": "/{departmentId}/dashboard",
  "planned_event_start": "…",
  "planned_event_end": "…"
}
```

**Dept-Wechsel:** MW sieht das neue Dept im **Profilmenü → Abteilung wechseln** (`[TopHeader.vue](../../frontend/src/components/layout/TopHeader.vue)` Dropdown), sobald `membership` existiert und `/api/users/{id}/memberships` `department.is_grossanlass` liefert.

Optional im Dropdown: Label «Grossanlass» neben Dept-Name zur Unterscheidung von Pfadi-Depts.

### 2.6 Flag-Verhalten


|                       | Pfadi-Dept | Grossanlass-Dept                    |
| --------------------- | ---------- | ----------------------------------- |
| Sidebar «Aktivitäten» | ja         | **nein**                            |
| Sidebar «Dashboard»   | —          | **ja (Default)**                    |
| Material              | Lager      | Zentrallager + Leihgaben + Fuhrpark |
| Groups                | Truppen    | Ressorts                            |


---

### 2.7 Ersteinrichtung und Freigabe (IST)

Ein neu angelegter Grossanlass ist **nicht freigegeben**. Der persistente Einrichtungsstand liegt an `department_grossanlass_config` (`setup_released_at`, `setup_released_by_user_id`) und ist unabhängig von `status` (draft/published = Einladung der Gast-Departments, [§0.7](#07-freigabe-und-teilnehmer)) und vom Tour-Fortschritt des Benutzers. Die Migration `Version20261011100000` setzt den Stand für **alle bestehenden** Grossanlässe auf freigegeben, damit produktive Anlässe nicht gesperrt werden; nur neue starten offen. Die Einladung der Gast-Departments (`POST …/publish`) verlangt die freigegebene Einrichtung.

**Drei unabhängige Konzepte:** der GA-Typ (`guest_activity_type`: Lager `camp` oder `event`), die teilnehmenden Departments (`has_guest_departments`, Teilnehmer) und die Material verleihenden Departments (Gast-Freigaben, [materialfluss.md §9](./materialfluss.md#9-department--camp--grossanlass)). Teilnahme setzt keine Materialleihe voraus und löst keine aus, und umgekehrt.

**Pflichtbereiche** (`GrossanlassSetupService`, `GET …/grossanlass/setup`):

| # | Bereich | Pflichtdaten |
| --- | --- | --- |
| 1 | Stammdaten | Name, Ort (Text oder Adresse), gültiger GA-Typ, Ende nicht vor Beginn |
| 2 | Ressorts und Verantwortlichkeiten | mindestens ein Ressort, jedes Ressort mit mindestens einer Leitung (`leader`) |
| 3 | Mitglieder und OK-Zuordnung | mindestens ein Mitglied mit Rolle `mw` und eines mit `dc` (OK-Leitung) |

**Rechte** (Rollen aus `membership.role`; globale Admins im Scope des Departments behalten ihre Rechte und unterliegen der Sperre nicht):

| Rolle | Einrichten | Freigeben | Vor der Freigabe |
| --- | --- | --- | --- |
| `mw`, `dc` (OK-Leitung) | ja | ja (`POST …/setup/release`) | Dashboard, Stammdaten, Ressorts, Mitglieder |
| `cmw` | ja | **nein** (403) | wie MW, ohne Freigabe |
| `bl`, `lw`, `clw`, `komm`, `spon`, `u` | nein | nein | kein GA-Zugang |

**Serverseitige Sperre** (`GrossanlassSetupGateSubscriber`, vor der Freigabe): alle `/api/departments/{id}/grossanlass/…`-Endpunkte antworten mit 403 (`code: grossanlass_setup_pending`). Ausgenommen für `mw`/`cmw`/`dc` sind nur `setup`, `setup/release`, `planung` (GET, PATCH = Stammdaten), `groups` (Liste, Anlegen, Ändern, Löschen) und `groups/{id}/members`. Gast-Endpunkte (`…/{gast-department}/grossanlass/hosts/…`) sind nicht betroffen; Nicht-Mitglieder bleiben bei den bisherigen Prüfungen der Endpunkte. Allgemeine Department-Endpunkte (Benutzer, Material, Aktivitäten des Grossanlass-Departments) sind nicht Teil der Sperre.

**Freigabe** nur bei vollständigen Pflichtdaten (sonst 422 `grossanlass_setup_incomplete` mit den offenen Punkten je Bereich), idempotent (Zeitpunkt und Person der ersten Freigabe bleiben). Danach gilt die normale rollenabhängige Navigation.

**Frontend:** Das Dashboard zeigt bis zur Freigabe `GrossanlassSetupPanel` (drei Bereiche, Freigabe-Knopf nur für MW/OK-Leitung, Hinweis für andere Rollen). Die Navigation zeigt nur Dashboard, «Grossanlass verwalten» und die Einstellungen (Router: `isGrossanlassSetupAllowedPath`; nur Navigation, die Sperre erzwingt der Server). Die Tour `ga-setup` (`config/onboardingTours.ts`, Audience `ga-setup`) nutzt die vorhandene Tour-Infrastruktur und hebt echte Elemente hervor (`data-onboarding`: `nav-ga-verwalten`, `ga-setup-stammdaten`, `ga-setup-type`, `ga-setup-tab-ressorts`, `ga-setup-ressorts`, `nav-settings`, `settings-nav-users`, `ga-setup-release`). Sie startet beim ersten Öffnen des offenen Grossanlasses einmal automatisch (Merker in `localStorage` je Profil und Department), ist über den Hilfe-Hub (Hilfe → Touren) und den Knopf im Panel jederzeit erneut startbar und der Fortschritt (`onboarding_tours_<profil>_<dept>`) ist vom Freigabestatus getrennt.

Demo-Zustand und Testablauf: [../demo/SEED-KONZEPT.md §7.12](../demo/SEED-KONZEPT.md#712-ga-typ-und-einrichtungsstand-der-grossanlässe-ist-katalog-2026105).

## 3. Navigation & Dashboard

### 3.0 Phase 1 — Grundgerüst (historisch)

Das Platzhalter-Dashboard aus Phase 1 ist der alte Schnitt ([MVP.md](./MVP.md)). Heute gilt [§0](#0-ist-stand).

Historische Beschreibung:

**Ziel:** Nach Create sieht Ersteller und Chief-MW das **bestehende zentrale Layout** — keine neue App-Hülle ([§20](#20-implementierungsprinzipien--keine-doppelspur)).


| Baustein                 | Verhalten Phase 1                                                                                                              |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------ |
| **Layout**               | `[AppLayout.vue](../../frontend/src/components/layout/AppLayout.vue)` — `TopHeader` + `SidebarNavigation` + `router-view`      |
| **Route**                | `/:departmentId` bzw. `/:departmentId/dashboard` — wie Pfadi-Dept ([Router](../../frontend/src/router/index.ts))               |
| **Redirect nach Create** | org/sub/sa → `/{neueDeptId}/dashboard`                                                                                         |
| **Sidebar**              | Grossanlass-Branch §3.1 / §3.4: **Dashboard** aktiv; **kein** «Aktivitäten»; **Planung** ab PR2a; Aufgaben/Nachrichten ab PR2a |
| **Dashboard-Inhalt**     | **Minimal:** Name, geplantes Datum, Badge «Entwurf», Kurztext — **keine** Widgets (Runden, Lager, Ressorts)                    |
| **MW-Zugang**            | E-Mail + Inbox §2.5; Dept-Wechsel im Profil-Dropdown                                                                           |


```
┌─ TopHeader (bestehend) ────────────────────────────────┐
│ … · Profil ▼ → Abteilung wechseln · PFF 2027 · …      │
└────────────────────────────────────────────────────────┘
┌ Sidebar ─┐ ┌─ /{deptId}/dashboard (Phase 1) ───────────┐
│ Dashboard│ │  PFF 2027                      [Entwurf]   │
│ (kein    │ │  17.07.2027 – 19.07.2027                 │
│ Aktivit.)│ │  Willkommen — Planung folgt in Phase 2.  │
└──────────┘ └───────────────────────────────────────────┘
```

Details DoD: [MVP §Phase 1](./MVP.md#phase-1-grundgerüst).

### 3.1 Sidebar (Grossanlass-Dept)

Gleiche **App-Shell** (`[AppLayout](../../frontend/src/components/layout/AppLayout.vue)`, `[SidebarNavigation](../../frontend/src/components/layout/SidebarNavigation.vue)`) — **conditional** Branch bei `department.is_grossanlass`. Details: [§3.4](#34-hauptmenü--sortierung--sichtbarkeit), [§20](#20-implementierungsprinzipien--keine-doppelspur).

**Kein** separates Grossanlass-Menü. `/activities` ist ausgeblendet. Die heutige Sidebar steht in [§0.1](#01-navigation).


Planung hat **Tabs innen** (§3.5) — **nicht** jedes Tab ein Sidebar-Eintrag.

### 3.2 Dashboard — Widgets

Die Box **Fahraufträge** ist Ist ([rollen-postfach-fahrten.md §7.4](./rollen-postfach-fahrten.md#74-dashboard--box-fahraufträge)). Die übrige Widget-Liste ist das Zielbild und nicht Zeile für Zeile gegen den Code geprüft. Navigation und gebaute Module: [§0](#0-ist-stand).

Zielbild:


| Widget                   | Inhalt                                                              |
| ------------------------ | ------------------------------------------------------------------- |
| **Entwurf-Banner**       | solange `status: draft` — «Grossanlass freigeben» + Checkliste §7.2 |
| **Anlass-Phase**         | Entwurf · Planung · Aufbau · Event · Abbau · Abgeschlossen          |
| **Offene Formulare**     | Wünsche & Ideen: offen / geplant / geschlossen                          |
| **Lager vs. draussen**   | Gesamt: im Lager · zugewiesen · draussen (issued)                   |
| **Pro Ressort (Kurz)**   | Verpflegung: 80/200 draußen · Technik: …                            |
| **Teilnehmer**           | In Entwurf: geplant · Nach Freigabe: pending / accepted             |
| **Nächste Activities**   | Aufbau 15.7., Vorevent Sitzung 12.5., …                             |
| **Checkliste vor Start** | Runden geschlossen? Wünsche offen?                                  |
| **Fahraufträge**         | Anzahl Einsätze mit Checkbox Fahrt; Klick → Liste. Selbstabholung nicht hier. Siehe [rollen-postfach-fahrten.md §7.4](./rollen-postfach-fahrten.md#74-dashboard--box-fahraufträge) |


Klick → Planung, Materialübersicht, Fahrauftrag-Liste oder gefiltertes Ressort.

### 3.3 Dashboard — Layout (Wireframe)

```
┌────────────────────────────────────────────────────────────┐
│  PFF 2027                          Phase: Planung          │
├────────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐  │
│  │ Runden      │ │ Lager 1'240 │ │ Zusagen 8/12        │  │
│  │ 2 offen     │ │ Draußen 156 │ │ pending             │  │
│  └─────────────┘ └─────────────┘ └─────────────────────┘  │
│  ┌─────────────┐                                           │
│  │ Fahraufträge│  3 offen → Klick Liste (nur Checkbox Fahrt)│
│  └─────────────┘                                           │
│  Ressorts                                                    │
│  Verpflegung    Lager 400 │ zugewiesen 120 │ draußen 80    │
│  Technik        …                                          │
│  Nächste Termine                                             │
│  • Aufbau 15.7. · Vorevent Sitzung 12.5.                   │
└────────────────────────────────────────────────────────────┘
```

### 3.4 Hauptmenü — Sortierung & Sichtbarkeit

Implementierung: `[SidebarNavigation.vue](../../frontend/src/components/layout/SidebarNavigation.vue)` — `isGrossanlassDept` + dedizierte `showGrossanlass*`-Flags (nicht alles über `showStandardDeptSidebarLinks` ausblenden).

#### Sortierung (oben → unten)


| #   | Label (i18n)      | Icon (MDI)                   | Route                           | Anzeige                                     |
| --- | ----------------- | ---------------------------- | ------------------------------- | ------------------------------------------- |
| 0   | Logo / Home       | —                            | `/{deptId}`                     | immer                                       |
| 1   | **Dashboard**     | `mdi-view-grid`              | `/{deptId}`                     | immer                                       |
| 2   | **Planung**       | `mdi-clipboard-text-outline` | `/{deptId}/planung`             | ab Phase 2                                  |
| 3   | **Fahrzeuge**     | `mdi-truck-outline`          | `/{deptId}/fahrzeuge`           | **Ist** — kein eigener Punkt «Materialien»; Bestand liegt in der Materialübersicht |
| 4   | Materialübersicht | `mdi-truck-delivery-outline` | `/{deptId}/material` | **Ist** — Bestand, Wareneingang, Ausgabe, Pack, Retour. Einsätze/Konflikte unter Planung |
| 5   | **Beschaffung**   | `mdi-cart-outline`           | `/{deptId}/beschaffung`         | **Ist** — Inhalt [§0.5](#05-beschaffung-anfragen-wareneingang-kosten), keine Shell |
| —   | *Divider*         |                              |                                 |                                             |
| 6   | **Aufgaben**      | `mdi-clipboard-list`         | `/{deptId}/tasks`               | ab Phase 2 (Runden-Inbox, MW-Tasks)         |
| 7   | **Nachrichten**   | `mdi-bell-outline`           | `/{deptId}/notifications`       | ab Phase 2                                  |
| 8   | Kontakte          | `mdi-account-group`          | `/{deptId}/contacts`            | optional (CM; RL vorerst aus)               |
| 9   | **Einstellungen** | `mdi-cog-outline`            | `/{deptId}/settings`            | immer                                       |
| 10  | Hilfe             | `mdi-help-circle-outline`    | `/{deptId}/help/department`       | optional wie Pfadi                          |


#### Bewusst ausgeblendet (Grossanlass-Dept)

Aktivitäten, **Pfadi-Buchhaltung** (`/accounting` — voller Tab-Stack), Werkstatt, Lieferanten-Shop, Statistik. Stattdessen **Beschaffung** §3.7 (eigenes Modul).

#### Darstellung

- Gleiche CSS-Klassen: `nav-item`, `nav-icon--mdi`, `nav-label` — wie Pfadi.
- Aktiv: `route.path.includes('/planung')` bzw. `isDeptSectionNavActive('planung')`.
- Dept-Wechsel: Tag «Grossanlass» in `[TopHeader](../../frontend/src/components/layout/TopHeader.vue)` (bereits Phase 1).

#### Phase 1 vs. Phase 2+ (Ist → Soll)


| Eintrag                      | Phase 1 (Ist)         | Phase 2+ (Soll)                    |
| ---------------------------- | --------------------- | ---------------------------------- |
| Dashboard                    | ✓                     | ✓                                  |
| Planung                      | —                     | ✓                                  |
| Beschaffung, Kosten, Anfragen, Fahrzeuge, Materialübersicht, Werkstatt | — | **Ist** [§0.1](#01-navigation) |
| Aufgaben / Nachrichten       | aus                   | ✓ (Benachrichtigung Runden §9.0.1) |
| Einstellungen                | ✓ (volle Pfadi-Liste) | ✓ gefiltert §3.6                   |


### 3.5 Routen & leere Seiten (Shell-first)

**Prinzip:** Routen und **leere Shells** früh anlegen; Inhalt in PR2–PR4 nachziehen — gleiche Patterns wie Phase-1-Dashboard (`[DashboardView.vue](../../frontend/src/views/DashboardView.vue)` Grossanlass-Branch).

#### Routen (Router unter `/:departmentId`)


| Route                      | View (Vorschlag)             | Phase  | Inhalt initial                            |
| -------------------------- | ---------------------------- | ------ | ----------------------------------------- |
| `/` / `dashboard`          | `DashboardView`              | 1 ✓    | Platzhalter §3.0                          |
| `**/planung`**             | `GrossanlassPlanungView`     | **2a** | Tabs + `EEmptyState`                      |
| `/beschaffung`             | `GrossanlassBeschaffungView` | **Ist** | Tabs Bedarf, Anfragen, Offerten, Zusagen, Bestellungen. Historisch war das eine leere Shell ohne API |
| `/planung/rounds/:roundId` | `GrossanlassRoundDetailView` | 4      | Wunschformular                            |
| `/material`     | `GrossanlassMaterialView` | **Ist** | Bestand, Wareneingang, Ausgabe, Pack, Retour [§0.1](#01-navigation) |
| `/materials`               | Redirect auf Materialübersicht | **Ist** | kein eigener Stammdaten-Menüpunkt |


#### Planung — Layout (Tabs, kein Sidebar-Spam)

Pattern wie `[SettingsView.vue](../../frontend/src/views/SettingsView.vue)`: **Subnav/Tabs + `router-view`**, optional `[SettingsSubnavList](../../frontend/src/components/settings/SettingsSubnavList.vue)` oder `v-tabs`.

```
/:deptId/planung
├── ?tab=ressorts     → GrossanlassRessortsTab.vue      (PR2)
├── ?tab=rounds       → GrossanlassRoundsTab.vue        (PR3)
└── (später: stammdaten, freigabe, …)
```


| Tab                       | Label                             | Phase | Leer-Zustand                         |
| ------------------------- | --------------------------------- | ----- | ------------------------------------ |
| **Ressorts & Mitglieder** | `grossanlass.planung.tabRessorts` | 2     | `EEmptyState` + «Ressort hinzufügen» |
| **Wünsche & Ideen**       | `grossanlass.planung.tabWishes`   | 3     | `EEmptyState` + «Formular anlegen»   |


Wünsche (PR4): **kein** eigener Sidebar-Eintrag — Detail unter `/planung/runden/:id` oder Panel im Tab «Wünsche & Ideen». Ist-Label der Tab-Route: «Planungsrunden» (`GrossanlassRoundsTab`) bis Rename.

#### Wiederverwendbare Bausteine (Pflicht §20)


| Baustein                                                      | Pfad                                                                                                                                                            | Verwendung Grossanlass                                         |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------- |
| `**PageShell`**                                               | `[PageShell.vue](../../frontend/src/components/layout/PageShell.vue)`                                                                                           | Planung, Runden-Detail, Dashboard (wie Phase 1)                |
| `**EEmptyState**`                                             | `[EEmptyState.vue](../../frontend/src/components/layout/EEmptyState.vue)`                                                                                       | leere Ressort-/Runden-Listen                                   |
| `**ELoadingState**`                                           | `[ELoadingState.vue](../../frontend/src/components/layout/ELoadingState.vue)`                                                                                   | API-Laden                                                      |
| `**EButton**`, `**EDialog**`, `**ETextField**`, `**ESelect**` | `components/form/base/`                                                                                                                                         | Formulare — [vuetify-standards.md](../ui/vuetify-standards.md) |
| `**section-card**`                                            | CSS wie Dashboard                                                                                                                                               | Karten-Inhalt                                                  |
| **Ressort-Baum**                                              | Orientierung `[GroupsSettingsView.vue](../../frontend/src/views/settings/GroupsSettingsView.vue)` (`hierarchicalGroups`, Mitglieder-Tabelle)                    | Tab Ressorts — **API** über `/grossanlass/groups`              |
| **User-Suche**                                                | `[DepartmentModal.vue](../../frontend/src/components/DepartmentModal.vue)` / Wizard MW-Suche                                                                    | Mitglieder zu Ressort                                          |
| `**ActivityDateRangeField`**                                  | `[ActivityDateRangeField.vue](../../frontend/src/components/activities/wizard/ActivityDateRangeField.vue)`                                                      | Runde `opens_at`/`closes_at`, Wunsch-Zeitraum                  |
| **Tasks / Inbox**                                             | `[useDepartmentTasks](../../frontend/src/composables/useDepartmentTasks.ts)`, `[NotificationsCenterView](../../frontend/src/views/NotificationsCenterView.vue)` | Runde geöffnet, MW assigned                                    |
| **i18n**                                                      | `de.json` → `grossanlass.planung.`*, `sidebar.planung`                                                                                                          | keine Hardcodes                                                |


Weitere Übersicht: [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md).

#### PR-Schnitt Shell vs. Inhalt


| PR       | Navigation & Shell                                                                                                    | Inhalt                             |
| -------- | --------------------------------------------------------------------------------------------------------------------- | ---------------------------------- |
| **PR2a** | Sidebar Planung; Route `/planung`; Tabs + `EEmptyState`; Aufgaben/Nachrichten wieder ein; **Settings gefiltert** §3.6 | —                                  |
| **PR2b** | —                                                                                                                     | Ressort-Baum + API §4              |
| **PR2c** | Sidebar **Beschaffung**; Route `/beschaffung`; Tab-Shell + `EEmptyState` §3.7                                         | — (**kein** Backend)               |
| **PR3**  | Tab Planungsrunden Shell                                                                                              | Runden CRUD + open/close           |
| **PR4**  | Runden-Detail-Route                                                                                                   | Wunschformular §9.1                |
| **PR5**  | —                                                                                                                     | Beschaffung-Inhalt §3.7 (nach PR4) |


**Nicht** vorzeitig: Routes/Menü für Materialübersicht und Materialien — vermeidet tote Links.

### 3.6 Einstellungen (Grossanlass-Dept)

The **Pfadi-Settings-Subnav** (`[SettingsView.vue](../../frontend/src/views/SettingsView.vue)`) wird bei `department.is_grossanlass` **gefiltert** — ab Phase 2 (PR2a). Kein separates Settings-Layout.

#### Rollen (zwei Ebenen)


| Ebene                           | Rollen                      | Bedeutung                                                        |
| ------------------------------- | --------------------------- | ---------------------------------------------------------------- |
| **Department**                  | **MW**, **DC**, **U**       | Zugang zum Grossanlass-Projekt; CM = MW/DC                       |
| **Ressort** (`GroupMembership`) | **Leader** (RL), **Member** | Zugehörigkeit zu Ressort/Bauprojekt — **nicht** unter «Benutzer» |


**L1–L3** (Pfadi-Leiter) entfallen im Grossanlass-Dept. RL wird in **Planung → Ressorts** vergeben, nicht als Department-Rolle.

#### Sichtbare Menüpunkte (Settings-Subnav)


| Menü                                                                                                                                                                          | Phase | Wer   | Anmerkung                                           |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----- | ----- | --------------------------------------------------- |
| **Mein Department**                                                                                                                                                           | 2+    | alle  | Name, Org, IDs, Gefahrenzone; ggf. Anlass-Metadaten |
| **Benutzer**                                                                                                                                                                  | 2+    | MW/DC | MW/DC/U einladen — **keine** Pfadi-Rollen-Matrix    |
| **Zeit/Ort**                                                                                                                                                                  | 2+    | MW/DC | optional — Locale/Zeitzone wie Pfadi                |
| **Gruppen**                                                                                                                                                                   | —     | —     | **ausblenden** — Ressorts nur in **Planung** §4.4   |
| **Kategorien, Regale, Join-Code, Fixe Daten, Infoscreens, Aktivitäten, Werkstatt, Standorte, Rechnungsadresse, Öffentliche Material-Seite, Vorlagen, Import/Export, Add-ons** | —     | —     | **ausblenden** (Pfadi-Material/Camp)                |


Später (Material-Phase §10): **Kategorien**, **Regale & Fächer**, **Standorte**, **Vorlagen** wieder für CM — Menüpunkt erst mit Feature.

#### Sicht pro Department-Rolle


| Rolle       | Settings                                                                               |
| ----------- | -------------------------------------------------------------------------------------- |
| **MW / DC** | Mein Department, Benutzer (+ später Material-Stammdaten)                               |
| **U**       | nur **Mein Department** (read-only) — analog `USER_ALLOWED_MENU_IDS`, **ohne** Gruppen |
| **RL**      | wie **U** — Ressort-Verwaltung in **Planung**, nicht Settings                          |


Implementierung: `visibleMenuItems` in `SettingsView.vue` — Branch `isGrossanlassDept` + `GROSSANLASS_SETTINGS_MENU_IDS`; i18n-Labels unverändert, ggf. später «Ressorts»-Hinweis in Planung statt «Gruppen».

### 3.7 Beschaffung — Budget & Kosten

**Kein** Pfadi-Modul «Buchhaltung» ([accounting.md](../accounting.md)) — kein Follow-up-Warteschlange, Abschreibung oder Aktivitäts-Verbrauch.

**Kostenübersicht (Soll):** Einkauf / Miete / Weiterverkauf, Zahler ≠ Organisator, eigene Tabellen — **[kosten.md](./kosten.md)** (abarbeiten). Ist-UI Finanzen bleibt Rahmen + Offerten/Bestellungen, bis die Phasen dort umgesetzt sind.

**Ist:** Bedarf, Anfragen, Offerten, Zusagen, Bestellungen sind gebaut. Eingang läuft über **Wareneingang** in der Materialübersicht, nicht über einen Tab «Erhalten». Details [§0.4](#04-wünsche) und [§0.5](#05-beschaffung-anfragen-wareneingang-kosten). Kosten-Ledger: [kosten.md](./kosten.md).

Die folgende Phasen-2-Shell ist **historisch** (der leere Zustand ist ersetzt):

#### Historisch — Phase 2 (PR2c) war nur Shell


| Lieferbar                                                                                             | Nicht in Phase 2                           |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| Sidebar «Beschaffung», Route `/{deptId}/beschaffung`                                                  | API, Datenmodell, echte Budget-Zahlen      |
| View `GrossanlassBeschaffungView` + `[PageShell](../../frontend/src/components/layout/PageShell.vue)` | Wunsch-Aggregation, Offerten, Bestellungen |
| Tabs mit `[EEmptyState](../../frontend/src/components/layout/EEmptyState.vue)` pro Tab                | CSV-Export, Material-Batch bei «erhalten»  |


```
/:deptId/beschaffung
├── ?tab=uebersicht    → EEmptyState «Budget-Übersicht folgt nach Planungsrunden»
├── ?tab=bedarf        → EEmptyState
├── ?tab=offerten      → EEmptyState
├── ?tab=bestellungen  → EEmptyState
└── ?tab=erhalten      → EEmptyState
```

i18n: `sidebar.beschaffung`, `grossanlass.beschaffung.tab*`.

#### Beschaffung — Inhalt (Ist, früher Phase 5)

Abhängigkeit: **PR4** (`activity_grossanlass_wish_line`) → dann Bedarf aus Wünschen aggregieren.


| Tab              | Inhalt                                                                            |
| ---------------- | --------------------------------------------------------------------------------- |
| **Übersicht**    | Soll/Ist gesamt + pro Ressort; offene Offerten; bestellt nicht erhalten           |
| **Bedarf**       | CM bündelt **Material**-Wünsche zu Positionen (merge/split); nicht `company_tip`/`free` roh |
| **Anfragen**     | Ist: Firmen, Kanal, angefragte Positionen, Gmail — [§0.5](#05-beschaffung-anfragen-wareneingang-kosten) |
| **Offerten**     | Ist: 1..n Angebote inkl. Logistikweg |
| **Bestellungen** | Status «bestellt», Betrag, Bestelldatum, Rechnungsreferenz, Liefertermin |
| **Wareneingang** | nicht dieser Tab. Route `material/wareneingang`; API `POST …/lines/{id}/received` |


**Status** pro Position (Kanban): `bedarf` → `offerte_eingeholt` → `budgetiert` → `bestellt` → `teilweise_erhalten` → `erhalten`.

#### Berechtigung (Ziel)

Zwei Spuren in der Beschaffung:

| Spur | Entstehung | Wer pflegt Offerten | Wer wählt / bestellt / erhält |
| ---- | ---------- | ------------------- | ----------------------------- |
| **Zentral (MW)** | Materialwünsche → Bedarf-Pool → Position | MW/DC | MW/DC |
| **Ressort (self-organized)** | Direkt-Bedarf ohne Wunsch (`source=direct`, `self_organized=true`) | MW/DC **oder** freigegebene Ressort-Mitglieder (`group_membership.can_procure`) | nur MW/DC |

Freigabe «Beschaffung» pro Mitglied: MW setzt `can_procure` in Planung → Ressorts → Mitglieder. Gilt für den Ressort-Zweig (Nachfahren inklusive).

| Aktion | MW/DC | Delegierter (`can_procure`) | U |
| ------ | ----- | --------------------------- | - |
| Bedarf aus Wünschen bündeln | ✓ | — | — |
| Direkt-Bedarf anlegen | ✓ | ✓ (eigener Zweig) | — |
| Offerten erfassen / bearbeiten | ✓ (alle Positionen) | ✓ (nur eigene Direkt-Positionen) | — |
| Offerte wählen, budgetieren, bestellen | ✓ | — | — |
| Anfragen / Gmail | ✓ | — | — |
| Wareneingang erfassen | ✓ | — | — |
| `can_procure` setzen | ✓ | — | — |
| Beschaffung lesen (Offerten, eigene Direkt-Lines) | ✓ | ✓ | — |

**Mein Ressort:** Delegierte erfassen Direkt-Bedarf; Link zu Beschaffung → Offerten.

**Einsätze:** Material wird über **Commitment** (`commitment_id`) gebucht, nicht direkt an `procurement_line`. Ist: [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md).

#### Datenmodell (Ist, §14.4)

```
activity_grossanlass_procurement_line   — group_id, qty, status; source: from_wish | direct; self_organized
group_membership.can_procure            — MW-Freigabe für Direkt-Bedarf + Offerten im Zweig
activity_grossanlass_quote              — procurement_line_id, supplier, amount_chf, selected
activity_grossanlass_procurement_order  — bestellt_am, cost_chf, order_ref, received_at?
department_grossanlass_cost             — Ledger (Quelle Übersicht) — [kosten.md](./kosten.md)
department_grossanlass_budget            — Rahmen gesamt + pro Zahler
```

Optional später: `material_batch_id` bei «erhalten» → Zentrallager §10.

#### API (Ist)

```
GET/POST/PUT/DELETE  …/grossanlass/beschaffung/lines
GET/POST/PUT/DELETE  …/grossanlass/beschaffung/lines/{id}/quotes
POST                 …/beschaffung/lines/{id}/order
POST                 …/beschaffung/lines/{id}/received
GET                  …/grossanlass/beschaffung/overview
CRUD                 …/grossanlass/beschaffung/costs
PUT                  …/grossanlass/beschaffung/budgets
```

Intern §20: keine parallele Ledger-UI — Export ans Vereins-Finanztool optional über bestehende Accounting-Entitäten **später**, nicht MVP.

---

## 4. Ressorts = Gruppen

Ressorts = `**Group`** im Grossanlass-Department + `GroupMembership`.


| Ebene                 | Department                                                              | Bedeutung                                                       |
| --------------------- | ----------------------------------------------------------------------- | --------------------------------------------------------------- |
| **Ressort**           | Grossanlass-Dept (`parent_id: null`)                                    | Organisatoren, Material-Wünsche, Ausgabe                        |
| **Unterressort**      | Grossanlass-Dept (`parent_id` gesetzt, `grossanlass_kind: bereich`)     | Mittlere Ebene. UI-Name Unterressort, Speicherwert `bereich` |
| **Bauprojekt**        | Grossanlass-Dept (`parent_id` gesetzt, `grossanlass_kind: teilbereich`) | Feine Untergliederung z. B. Bau → Bühne, Wasserstelle           |
| **Teilnehmer-Gruppe** | Pfadi-Dept (Gast)                                                       | Lokale Stufe bei Annahme (`guest_group_id`)                     |


### 4.1 Hierarchie — Ressort → Unterressort / Bauprojekt

Keine extra Tabelle — `**Group.parent_id`** + `**Group.grossanlass_kind**`:

```
Group «Bau»                         ← Ressort (parent_id: null, kind: ressort)
  Group «Sanitär»                   ← Unterressort (kind: bereich)
    Group «WC-Block»                ← Bauprojekt (kind: teilbereich)
  Group «Bühne»                     ← Bauprojekt (kind: teilbereich)
  Group «Wasserstelle»

Group «Verpflegung»
  Group «Küche Nord»                ← Unterressort oder Bauprojekt (User wählt beim Anlegen)
  Group «Küche Süd»
```

**Speicherung (`group.grossanlass_kind`):** Ist [§0.2](#02-baum-ressort-bereich-bauprojekt).


| Wert          | UI (`node_type`) | Bedeutung |
| ------------- | ---------------- | --------- |
| `ressort`     | `ressort`        | Wurzel |
| `bereich`     | `unterressort`   | mittlere Ebene. Alias beim Anlegen: `unterressort` |
| `teilbereich` | `bauprojekt`     | Bauprojekt |

Teilen und Bauvorhaben-Status: [§0.2](#02-baum-ressort-bereich-bauprojekt). Polygon am GA-Ort: [§0.3](#03-bauprojekt-karte-aufgaben-fahrzeugwunsch).

**Anlegen unter einem Knoten:** Dialog «Art» — **Unterressort** oder **Bauprojekt** (Phase 2b, `[GrossanlassRessortsTab.vue](../../frontend/src/views/grossanlass/GrossanlassRessortsTab.vue)`).

**Tiefe:** max. **10** Ebenen (`parent_id`-Kette) — Validierung beim Anlegen/Verschieben.

**Material & Ausgabe:** Wünsche, Zuweisung und Pack können an **Ressort oder Teilbereich** gebunden werden — feinere Planung und gezielte Ausgabe («Ausgabe Bühne»).

### 4.2 Wer pflegt die Hierarchie?


| Aktion                                             | CM/MW | Mitglied im Ressort (RL/User)                                       |
| -------------------------------------------------- | ----- | ------------------------------------------------------------------- |
| **Ressort** (Wurzel) anlegen                       | ✓     | —                                                                   |
| **Unterressort** anlegen (unter beliebigem Knoten) | ✓     | ✓ — **immer**, auch im Entwurf                                      |
| **Bauprojekt** anlegen (unter beliebigem Knoten)   | ✓     | ✓ — **immer**, auch im Entwurf; **nicht** an Gast-Freigabe gebunden |
| **Mitglieder** zuweisen                            | ✓     | ✓ im **eigenen** Ressort-Baum                                       |
| **Löschen**                                        | ✓     | —                                                                   |


**Unterressorts / Bauprojekte:** jederzeit für berechtigte User — kein Warten auf `published`.

`group.allow_rl_structure` (Default `true`): Mitglieder dürfen Kinder-Groups unter ihrem Knoten anlegen.

### 4.3 Löschen


| Regel                   |                                                                                                                          |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Erlaubt                 | Nur wenn **keine `GroupMembership`** im **gesamten Subtree** (Knoten + alle Kinder bis Tiefe 10)                         |
| Leere Unter-Bauprojekte | **Rekursiv mitlöschen**, wenn nirgends Members                                                                           |
| Blockiert               | Wenn noch `**activity_grossanlass_wish_line.group_id`** auf Knoten im Subtree verweist (Wünsche zuerst löschen/umhängen) |


### 4.4 UI & API (Phase 2)

**Einstieg:** `/:deptId/planung` → Tab **«Ressorts & Mitglieder»** (nicht separates Layout).

Intern weiterhin `Group` + `GroupMembership` — **API-Fassade** unter Grossanlass (keine parallele Gruppen-Logik §20):

```
GET/POST/PUT/DELETE  /api/departments/{id}/grossanlass/groups
POST/DELETE          …/groups/{groupId}/members
```

**Groups — Request/Response (Phase 2b):**


| Feld        | POST/PUT   | Response | Beschreibung                                                                  |
| ----------- | ---------- | -------- | ----------------------------------------------------------------------------- |
| `name`      | ✓          | ✓        | Anzeigename                                                                   |
| `parent_id` | ✓          | ✓        | `null` = Wurzel-Ressort                                                       |
| `kind`      | ✓ (Kinder) | ✓        | `ressort` (Unterressort) | `teilbereich` (Bauprojekt); Wurzel immer `ressort` |
| `node_type` | —          | ✓        | `ressort` | `unterressort` | `bauprojekt` — abgeleitet für UI                 |
| `level`     | —          | ✓        | Tiefe 1…10                                                                    |


Optional Link von **Einstellungen → Gruppen** für Grossanlass-Dept — Haupt-UX bleibt Planung-Tab.

---

## 5. Struktur & Teilnehmer (Dept-weit)

Gilt für **den Anlass-Zyklus** am Department — nicht pro Aufbau-Activity.

`department_grossanlass_config.struktur_modus`: `offen` | `verschachtelt` | `parallel`

### Verschachtelt

```
Group «Verpflegung» → Unterlager «Küche Nord» → Pfadi Winterthur
```

### Parallel

```
Ressorts (Groups)     | Unterlager (frei)      | Teilnehmer (flach)
Verpflegung — Max     | Region Ost — WT, FF    | Pfadi XY pending
```

- Ein Pfadi-Dept **max. einmal** pro Zyklus
- In **Entwurf:** Participant `status: planned` — **keine** Inbox (§7, §8)
- Struktur in **Planung** bearbeiten (CM/GL)

---

## 6. Activities & Rollen

Jede **Activity** ist ein **buchbarer Zeitraum** — Material & Pack wie bei camp/event, aber `type = grossanlass`.

### 6.1 Rollen (`grossanlass_role`)


| Rolle            | Bedeutung      | Beispiel                                  |
| ---------------- | -------------- | ----------------------------------------- |
| `**anlass`**     | Haupt-Event    | PFF 17.–19.7. — **auto beim Dept-Create** |
| `**aufbau`**     | Aufbauphase    | Zeltaufbau 15.–16.7.                      |
| `**abbau**`      | Abbau          | 20.–21.7.                                 |
| `**vorevent**`   | Vor dem Event  | Org-Sitzung, Probe                        |
| `**nach_event**` | Nach dem Event | Wrap-up, Rücktransport                    |


Alle: `activity.type = 'grossanlass'`, `activity.department_id` = Grossanlass-Dept.

### 6.2 Verknüpfung

```
activity.parent_activity_id   → Haupt-anlass (nullable nur für anlass selbst)
```

Dashboard und Materialübersicht aggregieren **alle** Activities; Filter nach Rolle möglich.

### 6.3 Anlegen weiterer Activities

**Planung** oder Dashboard: «+ Aufbau», «+ Vorevent», …

Kurzformular ( **kein** camp/event-Wizard):

- Rolle, Name, Zeitraum (`usage_start` / `usage_end`)
- `POST /api/activities` `{ type: grossanlass, grossanlass_role, parent_activity_id?, name, … }`

Materialbuchung & Pack an **dieser** Activity; Bestand kommt aus **Zentrallager** / Ressort-Zuteilung.

### 6.4 Gast-Sicht

Pfadi-Dept sieht **Haupt-`anlass`** in `/activities`. Aufbau/Abbau/Vorevents: Chief-intern (v1); Gäste nur bei explizitem Bedarf (out of scope v1).

---

## 7. Planung & Entwurfmodus

Route: `/:deptId/planung`

Jeder Anlass-Zyklus startet in `**department_grossanlass_config.status = draft**` (Entwurf). CM (Grossanlass-MW) bereitet **alles** vor; Gast-Depts und RL (operativ) sehen **nichts** bis **Freigabe**.

### 7.1 Entwurfmodus — was CM vorbereitet


| Bereich                        | Im Entwurf (CM/GL)                                                                           |
| ------------------------------ | -------------------------------------------------------------------------------------------- |
| **Stammdaten**                 | Anlass-Zeitraum verfeinern (`activity.usage_`*; Anker: `planned_event_*` §2.4), Ort, Notizen |
| **Ressorts**                   | Groups anlegen, RL-Mitglieder zuweisen                                                       |
| **Teilbereiche / Bauprojekte** | CM im Entwurf (`parent_id`) §4.2                                                             |
| **Teilnehmer-Depts**           | in Struktur eintragen → `planned`, Einladung erst bei Freigabe [§0.7](#07-freigabe-und-teilnehmer) |
| **Unterlager**                 | optional                                                                                     |
| **Planungsrunden**             | anlegen + **öffnen** (MVP: Chief-intern, §9.1)                                               |
| **Activities**                 | Aufbau, Vorevent, … anlegen (optional im Entwurf)                                            |
| **Material**                   | Bestand und Fahrzeuge [§0.1](#01-navigation), [§0.6](#06-materialübersicht-einsätze-werkstatt) |


**Gesperrt im Entwurf (volle Spec):** Inbox an Gäste, Gast-Sicht in Pfadi-`/activities`.  
**MVP Chief-intern:** Planungsrunde `open` + Wünsche §9.1 **ohne** Gast-Freigabe.

### 7.2 Freigabe («Grossanlass freigeben»)

Button in **Dashboard** und **Planung** — nur CM/GL.

**Mindest-Checks (v1):**

- Haupt-`anlass`: Zeitraum gesetzt
- mind. 1 Teilnehmer-Dept in Struktur (empfohlen; konfigurierbar)

**Ist.** Aktion `POST …/grossanlass/publish` (Zeitraum muss gesetzt sein; siehe [§0.7](#07-freigabe-und-teilnehmer)):

```
department_grossanlass_config.status = published
published_at, published_by_user_id

Alle participant: planned → pending
→ syncGrossanlassParticipantInvites()  → Inbox an Gast-MW/DC

Planungsrunden: bleiben scheduled (CM öffnet manuell oder per Auto)

RL: Zugriff auf eigenes Ressort — Teilbereiche anlegen §4.2
```

**UI nach Freigabe:** Dashboard ohne Entwurf-Banner; Widget «Zusagen pending».

**Rücknahme Freigabe:** v1 **nicht** — nur Dept-Archivierung oder manuelles Entfernen einzelner Participants.

### 7.3 Planung — Bereiche (Tabs)


| Tab                       | Inhalt                                                  |
| ------------------------- | ------------------------------------------------------- |
| **Stammdaten**            | §7.1                                                    |
| **Struktur**              | Ressorts, Teilbereiche, Unterlager, Teilnehmer-Depts §5 |
| **Ressorts & Mitglieder** | §4 — Baum (max. 10), Mitglieder, Lösch-Regeln           |
| **Planungsrunden**        | §9 — anlegen, open/close, Auto-Schedule, Wünsche        |
| **Activities**            | §6 — Phasen / Vorevents                                 |
| **Freigabe**              | Checkliste + Button (nur wenn `draft`)                  |


Kein separater `/setup`-Stepper.

---

## 8. Teilnehmer, Einladungen & Inbox

### 8.1 Flow (Entwurf → Freigabe → Annahme)

```
Entwurf:
  CM trägt Pfadi-Dept in Struktur ein
  → activity_grossanlass_participant (status: planned)
  → KEINE Inbox, Gast sieht nichts

Freigabe (§7.2):
  → alle planned → pending
  → syncGrossanlassParticipantInvites()
  → Inbox bei Gast-MW/DC

GM: annehmen → accepted + guest_group_id (lokale Gruppe)
GM: ablehnen → rejected
```

**Wichtig:** Einladung **nicht** beim Struktur-Speichern — **nur** bei Freigabe (gebündelt).

### 8.2 Inbox

**Pattern:** bestehend `CATEGORY_ACTIVITY_DEPT_INVITE` / `activity_department_invite` — `activity_type: grossanlass` im Payload.

**Empfänger:** `RECIPIENT_DEPARTMENT_MW` im Gast-Dept.

**Payload (zusätzlich):**

```json
{
  "activity_type": "grossanlass",
  "participant_id": "…",
  "unterlager_name": "Region Ost",
  "ressort_name": "Verpflegung",
  "source_department_name": "PFF 2027"
}
```

**UI Zeile:**

- Betreff: «PFF 2027 — Einladung Grossanlass»
- Preview: Chief-Dept · Ressort/Unterlager · Zeitraum

**Aktionen:** Ablehnen | Annehmen → Detail (Gast) + **Gruppe wählen** (wie camp/event).

**Chief bei Accept:** Hub-Widget / optional `invite_accepted` Inbox. **Reject:** nur Status in Planung (v1 kein Inbox).

**Sync:** `syncGrossanlassParticipantInvites()` bei `**publish`** und wenn nach Freigabe neue Depts hinzugefügt werden (`planned` → sofort `pending` + Inbox).

**Purge:** bei Activity `completed` / `cancelled` — wie heute.

### 8.3 i18n (Vorschlag)

- `grossanlass.invite.inboxSubject`
- `grossanlass.invite.inboxPreview`
- `grossanlass.invite.acceptedChief`

---

## 9. Wünsche & Ideen (Formulare)

Planung sammelt **Wünsche und Ideen**, nicht einen Projektplan in «Runden». Technisch bleibt der Container `activity_grossanlass_round` (Name, `open`/`closed`, Zeitfenster, Formular-Builder, Inbox beim Öffnen).

**Ist:** Tab **Wünsche** unter Planung. Zusätzliche Felder (genug vorhanden, selbst organisiert, Abholung, Zurückbringen, Einheit): [§0.4](#04-wünsche). Mehrere Firmen für dasselbe Paket liegen unter Beschaffung → Anfragen, nicht im Formular.

**PLANNED:** getrennte Formularzwecke `company_tip` und `free` beim Anlegen.

Am **Haupt-`anlass`**. **Mehrere Formulare** gleichzeitig `open` erlaubt (auch überlappend).

### 9.0 Formular-Typen


| `round_type`            | Phase        | Art     | Landet in                                      | Nicht |
| ----------------------- | ------------ | ------- | ---------------------------------------------- | ----- |
| `**material_wish`**     | Soll; Ist = `ressort_wuensche` | Eingabe | Bedarf / Verfeinern desselben Wunsches [Konzept §9](./20260823_New_concept.md#9-grob--fein-ein-wunsch-zwei-schärfen) | Roh in Partnermails |
| `**company_tip`**       | Soll         | Eingabe | Anfragen-Tab als **Vorschlag** (MW übernimmt)  | Als Beschaffungsposition |
| `**free`**              | Soll         | Eingabe | Ideen-Posteingang; MW übernimmt explizit       | Automatisch bündeln |
| `ressort_wuensche`      | **MVP Ist**  | Eingabe | wie `material_wish` (Alias bis Migration)      | — |
| `detailplanung`         | veraltet als eigener Typ | — | Grob/Fein = Stufe am Wunsch, nicht zweite Typ-Runde | — |
| `js_vorgabe`            | später       | Gast    | J+S                                            | — |
| `eigenmaterial`         | später       | Gast    | eigenes Lager                                  | — |
| `grossanlass_central`   | später       | Steuerung | Zuweisung Lager → Ressorts                   | — |
| `freigabe`              | später       | Steuerung | Freigabe vor Aufbau                          | — |


Nur **drei** Eingabe-Typen für Chief-intern: Material, Firmenvorschlag, Frei. «Frei» deckt Sonderfälle (Umfrage, wilde Idee).

**Material-Kernfelder** (fest, Zusatzfragen erlaubt): wie bisher Google-Form-Mapping §9.1. Grob vs. Fein ist **Hinweis + Stufe am Wunsch**, kein dritter `round_type`.

**Firmenvorschlag-Kernfelder:** Name, optional Kontakt/Mail, Kategorie/Bereich, URL, Notiz — kein Mengenkatalog.

### 9.0.1 Formular anlegen (CM/MW)


| Feld                    | Pflicht | Beschreibung                                                       |
| ----------------------- | ------- | ------------------------------------------------------------------ |
| **Name**                | ja      | z. B. «Material grob», «Wen kennt ihr?», «Ideen Küche»             |
| `**round_type`**        | ja      | Soll: `material_wish` \| `company_tip` \| `free` (Ist: nur `ressort_wuensche`) |
| **Stufe** (nur Material)| nein    | Grob oder Fein — Text an Ausfüllende [Konzept §9](./20260823_New_concept.md#9-grob--fein-ein-wunsch-zwei-schärfen) |
| `**opens_at**`          | nein    | Start (manuell oder Auto)                                          |
| `**closes_at**`         | nein    | Ende (manuell oder Auto)                                           |
| `**use_auto_schedule**` | nein    | MW wählt Auto — System öffnet/schliesst bei `opens_at`/`closes_at` |


**Status:** `scheduled` → `**open`** (manuell oder Auto) → `**closed**`.

**Workflow:**

1. CM/MW legt Formular an (`scheduled`) inkl. Typ
2. **Öffnen** manuell oder per Auto bei `opens_at`
3. Bei `**open`:** Inbox (+ optional E-Mail) an **Mitglieder** der betroffenen Ressorts — Text je Typ (Material einreichen / Firma vorschlagen / Formular ausfüllen)
4. Einreichen nur solange `**open`**; Material-Wünsche **verfeinern** auch nach Grobfenster über denselben Wunsch (Konzept), nicht nur solange diese eine Runde offen ist
5. **Schliessen** manuell oder Auto bei `closes_at`

**Regeln:**

- **Mehrere** Formulare parallel `open` erlaubt
- Überlappung erlaubt — Vereinigung offener Formulare
- Chief-intern — **ohne** Gast-Freigabe (`publish`)
- `free` und `company_tip` **nicht** in den Material-Bedarf-Pool mischen

### 9.1 Bedarfserfassung (`material_wish` / Ist `ressort_wuensche`)

In einer **offenen** Runde trägt RL/User (oder CM) **Wunsch-Zeilen** ein.

**Mapping Google Form → eMatChef:**


| Google Form (PFF 27)                               | Feld `wish_line`                                         |
| -------------------------------------------------- | -------------------------------------------------------- |
| Was brauchst du? (Material, Maschinen, Spezielles) | `label` + `wish_kind`                                    |
| Wie viel brauchst du davon?                        | `quantity`                                               |
| Wo brauchst du es? Ort                             | `**location`**                                           |
| Zeitrahmen / Wann benötigst du das Material        | `valid_from` / `valid_to` (+ optional `timeframe_notes`) |
| Welches Ressort? oder Bereich?                     | `group_id` (Dropdown Baum)                               |



| Feld                     | Typ                                | Pflicht |
| ------------------------ | ---------------------------------- | ------- |
| **Ressort / Bauprojekt** | `group_id`                         | ja      |
| **Art**                  | `material` | `fahrzeug` | `beides` | ja      |
| **Bezeichnung**          | Freitext (`label`)                 | ja      |
| **Anzahl**               | Zahl                               | ja      |
| **Ort**                  | `location`                         | ja      |
| **Zeitraum**             | `valid_from` / `valid_to`          | ja      |
| **Notizen**              | Text                               | nein    |
| **Einheit**              | `quantity_unit`, Default `Stk`     | nein    |
| **Genug vorhanden**      | `enough_on_hand` plus Herkunft `stock` oder `commitment` | nein |
| **Selbst organisiert**   | `self_organized` — nicht im Bedarf-Pool | nein |
| **Abholung / Zurückbringen** | `pickup_need`, `pickup_place`, `return_needed` | nein |
| **Status**               | `requested`, bis gebündelt         | —       |


**Bearbeiten / Löschen (Ist):** nur **Autor**, nur solange dieses Formular `**open`**. **Soll (Material):** Verfeinern am **selben Wunsch** nach Grobfenster — [Konzept §9](./20260823_New_concept.md#9-grob--fein-ein-wunsch-zwei-schärfen). CM sieht alle.

**MVP:** Fahrzeug = Bedarf melden (Freitext) — **kein** Fuhrpark-Stammdaten (§10.3). Material = Freitext — **kein** Katalog-Zwang.

**Berechtigung:** User nur Zeilen im **eigenen Ressort-Baum**; CM/MW alle Ressorts.

**UI Ist:** Planung → Tab «Planungsrunden» → Formular `open`. **UI Soll:** Planung → **Wünsche & Ideen** → Badge Material → einreichen / verfeinern; CM aggregiert pro Ressort.

Siehe §14.4 (`activity_grossanlass_wish_line`).

### 9.2 Grob / Fein (ersetzt `detailplanung` als zweiten Typ)

Kein zweiter `round_type` für Details. Dieselbe Wunsch-Identität, zwei Schärfen; angefragte Menge einfrieren — [Konzept §9](./20260823_New_concept.md#9-grob--fein-ein-wunsch-zwei-schärfen). `detailplanung` in älteren Spec-Stellen = dieses Modell, nicht neu bauen.

Optional später: Formular-Scope `target_group_id` (nur unter einem Ressort).

### 9.3 Firmenvorschlag (`company_tip`) und Frei (`free`)

**Firmenvorschlag:** Antworten sind Prospects, keine Mengen. MW übernimmt in Beschaffung → Anfragen (Kategorie zuordnen) oder verwirft. Gehen **nicht** in den Bedarf-Pool.

**Frei:** Rohantworten in einem Ideen-Eingang. Übernahme nur explizit → Materialwunsch oder Firmenvorschlag. Nie automatisch bündeln.

Details: [Konzept §8.1](./20260823_New_concept.md#81-formulare-statt-planungsrunden).

---

## 10. Materialien — Zentral erfassen

Menü **Materialien** = **Stammdaten**, activity-unabhängig.

### 10.1 Tabs


**Ist:** Der GA-Bestand besteht aus **Chargen** (`department_grossanlass_commitment`), nicht aus `MaterialItem` des Grossanlass-Departments. Kernmodell Artikel → Charge → Einsatz → Pack → Ort: [materialfluss.md §2](./materialfluss.md#2-kernmodell-artikel--charge--einsatz--pack--ort-ist).

| Tab           | Modell                                         | Beschreibung                                                                                           |
| ------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| **Eigen**     | Charge `origin` = `buy` / `buy_resale` / `own` / `donation` | Gekauft, vorhandener GA-Bestand oder Schenkung — bleibt beim Grossanlass                     |
| **Leihweise** | Charge `origin` = `loan`                       | Leihe von Firma oder Department; Zeitfenster an der Charge                                             |
| **Gäste**     | `department_grossanlass_guest_share` → Charge | Freigabe einer Gast-Abteilung, nach Übernahme Leih-Charge (§10.2)                                      |
| **Fahrzeuge** | Charge `family` = `vehicle`                    | Eigener Menüpunkt `/{deptId}/fahrzeuge` (§10.3)                                                        |


### 10.2 Leihweise

**Ist:** Leihmaterial ist eine Charge mit `origin=loan` (Firma: aus Anfrage/Zusage; Department: aus der Gast-Freigabe `department_grossanlass_guest_share` `kind=offer`, nach Übernahme durch den Grossanlass). Details und Lücken (Eigentümer, Mengen, Rückmeldung): [materialfluss.md §9](./materialfluss.md#9-department--camp--grossanlass).

Das frühere Zielmodell `material_usage_grant` ist **nicht gebaut und wird nicht gebaut** — GuestShare und Charge ersetzen es. Keine dritte Materialfreigabe daneben.

### 10.3 Fahrzeuge

**Ist:** Fahrzeuge im Grossanlass sind Chargen mit `family=vehicle` (Menü `/{deptId}/fahrzeuge`), mit Herkunft und Zeitfenster wie Material; Fahrzeugwünsche über `department_grossanlass_vehicle_need`. Der Department-Fuhrpark `department_vehicle` und die Activity-Transporttouren werden im Grossanlass nicht verwendet; es gibt keine Kopplung an die Activity-Transportpipeline.

**SOLL:** Fahrzeug eines anderen Departments leihweise für den Grossanlass bereitstellen, mit Herkunft/Eigentümer und Zeitraum. Das technische Modell (Feldnamen, ggf. Erweiterung bestehender Strukturen) wird erst anhand der GA-Fahrzeug-Chargen und der Department-Übergabe (GuestShare, §10.2) entschieden.

---

## 11. Materialübersicht & Ausgabe

**Ist, nicht mehr «später».** Route: `/:deptId/material`. Tabs [§0.1](#01-navigation). Wareneingang ersetzt den früheren Beschaffung-Tab «Erhalten».

### 11.1 Status pro Position


| Status         | Bedeutung                                          |
| -------------- | -------------------------------------------------- |
| **Im Lager**   | Zentrallager, unzugewiesen                         |
| **Zugewiesen** | Ressort / Unterkategorie, noch nicht physisch raus |
| **Draussen**   | Ausgegeben / im Einsatz (Pack/Move)                |


### 11.2 Ansichten

```
Filter: [ Gesamt ] [ Ressort ▼ ] [ Teilbereich ▼ ] [ Activity ▼ ]

Ressort «Bau»
  Teilbereich «Bühne»
    Gerüst × 4     Lager 0 │ zugewiesen 4 │ draußen 0
  Teilbereich «Wasserstelle»
    …
```

- **CM:** alle Ressorts + Zuweisung aus Lager
- **RL:** nur eigenes Ressort (§17)
- Filter **Activity:** Material für Aufbau vs. Anlass vs. Vorevent
- Partner-Dept-Material: optional aggregierte Spalte (Detail im Gast-Dept)

### 11.3 Workflow

**Ist:**

1. Ressorts / Bauprojekte erfassen Wünsche (Material + Fahrzeuge) über das Material-Formular (§9.1).
2. Zuweisung = **Einsatz** auf eine Charge, pro Ressort/Bauprojekt und Zeitraum → **Zugewiesen** (`place=assigned`). Bereichsleitungen reichen Einsätze ein, MW/CMW/OK-Leitung geben frei.
3. Pack / Fahrt / Selbstabholung → **Draussen** (`place=out`); Einsatz-Status `returned` → **Im Lager** (Backend; keine Rücknahme-UI für Einsätze).

Liste und Zeitachse pro Charge × Ressort/Bauprojekt (Planung → Belegung, Konflikte). Konfliktprüfung und serverseitige Buchungssperre (HTTP 409): Unikate (Menge ≤ 1, Fahrzeuge) bei überlappenden Fenstern, höchste gleichzeitige Menge > Charge-Menge, Ausgabe nur der physisch vorhandenen Menge ([materialfluss.md §7.4](./materialfluss.md#74-verfügbarkeit-und-überbuchungsschutz-ist)); Einsatz ausserhalb des Partnerfensters wird nur angezeigt. Wunsch-Zeitraum ≠ Einsatz, bis gebucht. Details: [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md), [materialfluss.md §2, §7](./materialfluss.md#2-kernmodell-artikel--charge--einsatz--pack--ort-ist).

Die Status in §11.1 gelten pro Einsatz für die ganze Einsatzmenge.

**SOLL:** Mehrbedarf, Verbrauch und Rückgabe als Teilmengen ([Konzept §12.4](./20260823_New_concept.md#124-wer-bucht--und-wenn-mehr--weniger--verbraucht-wird), [materialfluss.md §10, §14](./materialfluss.md#10-rückbau-und-rücknahme-partial)).

---

## 12. J+S

**PLANNED / SOLL** — keine bestehende Implementierung (siehe auch §0.8).

**Ist:** Bestand-Tab J+S und die J+S-Übersicht im Gäste-Bereich zeigen den J+S-Leihkatalog und die J+S-Bestellungen (`activity_js_order`) der Gast-Aktivitäten. Allgemeiner J+S-Prozess: [js-material/README.md](../activities/js-material/README.md).

**SOLL (fachlich):** Der Grossanlass gibt den teilnehmenden Departments eine J+S-Vorgabe; jedes Department meldet seinen J+S-Bedarf; Material & Logistik fasst zusammen und bestellt zentral fürs Zentrallager. Datenmodell, Rundentyp und API sind noch nicht festgelegt.

---

## 13. Lebenszyklus & Archivierung


| Situation                | Verhalten                                                                   |
| ------------------------ | --------------------------------------------------------------------------- |
| Haupt-`anlass` completed | Dashboard «Abgeschlossen»; Hinweis Dept archivieren                         |
| **Archivieren**          | `department.archived_at`, read-only                                         |
| **Weiterführen**         | Neuer Anlass-Zyklus: neuer Haupt-`anlass`, Struktur/Groups wiederverwendbar |


Activities (Aufbau, …) schliessen unabhängig; Dept-Abschluss wenn Haupt-`anlass` completed.

---

## 14. Datenmodell

### 14.1 Department

```
department.is_grossanlass
department.archived_at
```

Membership-API / Session: `department.is_grossanlass` in User-Memberships für Sidebar-Switch und Profil-Dept-Wechsel §2.5.

Inbox (neu): `InboxMessage::CATEGORY_GROSSANLASS_MW_ASSIGNED` (Chief-MW bei Create §2.5); `CATEGORY_GROSSANLASS_ROUND_OPENED` (Runde geöffnet §9.0.1).

### 14.2 Department-Konfiguration (Struktur Dept-weit)

```
department_grossanlass_config
  department_id             FK UNIQUE
  main_activity_id          FK → activity (anlass)
  status                    draft | published
  published_at              nullable
  published_by_user_id      nullable
  struktur_modus            offen | verschachtelt | parallel
  planned_event_start       datetime — Anlassdatum «von» (Wizard + Dept-Anker)
  planned_event_end         datetime nullable — «bis»; Default = Starttag
```

Group-Erweiterung (Ressorts):

```
group.parent_id             nullable → Hierarchie (Wurzel = Ressort)
group.grossanlass_kind      ressort | bereich | teilbereich
                            — ressort → UI ressort
                            — bereich → UI unterressort
                            — teilbereich → UI bauprojekt
group.build_status          planned | quoted | build | use | teardown | done | aborted
                            — nur Bereich und Bauprojekt; Ableitung §0.2
group.allow_rl_structure    boolean DEFAULT true  (geplant; Mitglieder dürfen Kinder anlegen §4.2)
```

Migration `Version20260625120000`: Spalte `grossanlass_kind`.

### 14.3 Activity

```
activity.type = 'grossanlass'
activity.parent_activity_id   nullable → Haupt-anlass
activity_grossanlass_config
  activity_id               FK UNIQUE
  grossanlass_role          anlass | aufbau | abbau | vorevent | nach_event
```

### 14.4 Struktur, Teilnehmer, Runden

```
activity_grossanlass_unterlager
activity_grossanlass_unterlager_department
activity_grossanlass_participant
  status                    planned | pending | accepted | rejected
  group_id                  nullable — Ressort/Teilbereich-Bezug
  unterlager_id, guest_group_id, …
activity_grossanlass_round
  id
  activity_id              FK → Haupt-anlass
  name                     string — Pflicht
  round_type               ressort_wuensche | detailplanung | …
  status                   scheduled | open | closed
  opens_at                 nullable datetime
  closes_at                nullable datetime
  use_auto_schedule        boolean DEFAULT false
  opened_at                nullable — Audit
  closed_at                nullable
  created_by_user_id       FK
  created_at, updated_at
activity_grossanlass_wish_line
  id
  round_id                 FK
  group_id                 FK → Ressort oder Bauprojekt
  wish_kind                material | fahrzeug | beides
  label                    Freitext-Bezeichnung
  quantity                 int
  location                 string — Ort («Wo brauchst du es?»)
  valid_from, valid_to
  timeframe_notes          nullable — Freitext-Zeitraum ergänzend
  notes                    nullable
  status                   requested | assigned | …
  created_by_user_id       FK — Autor (edit/delete §9.1)
  created_at, updated_at
activity_grossanlass_procurement_line   — Ist §0.5; source from_wish | direct; self_organized; asked_wish_ids
  wish_line_ids[], group_id, label, quantity, status
activity_grossanlass_quote
  procurement_line_id, supplier, amount_chf, selected, notes
activity_grossanlass_procurement_order
  procurement_line_id, ordered_at, cost_chf, order_ref, received_at?
department_grossanlass_cost            — Ledger Kostenübersicht [kosten.md](./kosten.md)
department_grossanlass_budget           — Rahmen gesamt (`payer_group_id` NULL) + pro Zahler
activity_grossanlass_ressort_line       — Zuweisung Zentrallager (später)
activity_grossanlass_js_submission
```

Ressorts: `**Group**` + `GroupMembership` — siehe §4, §14.2.

### 14.5 Material

```
department_grossanlass_commitment       — Charge (Eigen: buy/buy_resale, Leihweise: loan;
                                          family material | vehicle)
department_grossanlass_guest_share      — Freigabe Gast-Department → Charge
department_grossanlass_einsatz / _pack  — Einsatz, Pack/Palette
department_grossanlass_vehicle_need     — Fahrzeugwunsch am Bauprojekt
```

---

## 15. API

Präfix `…` = `/api/departments/{departmentId}/grossanlass`. Status gegen Code geprüft (5. Oktober 2026). Bei **PLANNED** ohne beschlossenen Vertrag steht «noch nicht festgelegt» — vor einer Umsetzung zuerst die bestehenden Endpunkte prüfen, keinen zweiten Pfad daneben anlegen.

| Status | Methode | Pfad | Beschreibung |
| --- | --- | --- | --- |
| IST | POST | `/api/departments/grossanlass` | Dept + auto `anlass` §2.3–2.4 |
| IST | POST | `…/publish` | Freigabe §0.7 |
| IST | — | kein eigener Endpunkt | Dashboard §3.2 lädt die bestehenden Endpunkte (`uebersicht`, `planung`, `planung/rounds`, `groups`, `beschaffung/…`, Anfragen) |
| IST | GET/POST · PUT/DELETE | `…/groups` · `…/groups/{groupId}` | Ressort-Baum §4.4 |
| IST | POST · PATCH/DELETE | `…/groups/{groupId}/members` · `…/groups/{groupId}/members/{userId}` | Mitglieder §4 |
| IST | GET/PATCH | `…/planung` | Planung inkl. Struktur, Teilnehmer, Unterlager §0.7 |
| IST | GET/POST · PUT | `…/planung/rounds` · `…/planung/rounds/{roundId}` | Runden §9 |
| IST | POST | `…/planung/rounds/{roundId}/open` · `/close` · `/reopen` | Runde öffnen / schliessen / wieder öffnen |
| IST | GET/POST · PUT/DELETE · POST | `…/planung/rounds/{roundId}/wishes` · `…/wishes/{wishId}` · `…/wishes/{wishId}/accept` | Wunsch-Zeilen §9.1 |
| IST | GET | `…/beschaffung/overview` | Übersicht; Cash/Netto/Zahler: [kosten.md](./kosten.md) |
| IST | GET/POST · PUT/DELETE · … | `…/beschaffung/lines` · `…/lines/{lineId}` (+ `quotes`, `order`, `received`) | Beschaffung §0.5. `received` bucht die Differenz als Bewegung an der Kauf-Charge ([materialfluss.md §6](./materialfluss.md#6-wareneingang-ist-mengenbasiert)) |
| IST | GET/POST · PATCH/DELETE · POST | `…/beschaffung/zusagen` · `…/zusagen/{commitmentId}` · `…/zusagen/from-inquiry/{inquiryId}` | Zusagen / Chargen inkl. Herkunft, Eigentümer, Rückgabepflicht ([materialfluss.md §2.1](./materialfluss.md#21-charge-ist)) |
| IST | GET/POST | `…/beschaffung/zusagen/{commitmentId}/movements` | Charge-Bewegungen; buchbar heute nur `received` ([materialfluss.md §14](./materialfluss.md#14-charge-bewegungen)) |
| IST | GET/POST · PATCH/PUT/DELETE · GET/PUT | `…/beschaffung/costs` · `…/costs/{costId}` · `…/beschaffung/budgets` | Ledger + Rahmen pro Zahler [kosten.md](./kosten.md) |
| IST | POST | `…/planung/activities` | weitere Grossanlass-Activity mit `grossanlass_role` §6 |
| IST | GET · POST · PATCH | `…/uebersicht` · `…/uebersicht/einsaetze` · `…/uebersicht/einsaetze/{einsatzId}` | Materialübersicht, Einsätze §11 ([materialfluss.md](./materialfluss.md)) |
| IST | POST | `…/invites/{participantId}/respond` | Gast-Einladung annehmen / ablehnen §8 |
| IST | PATCH · DELETE | `…/planung/participants/{participantId}` | Teilnehmer ändern (`unterlager_id`) / entfernen |
| PLANNED | — | noch nicht festgelegt | J+S-Vorgabe und -Rückmeldung §12 |


Berechtigungen: [§17](#17-berechtigungs-matrix).

---

## 16. Implementierungsphasen


| Phase  | Inhalt                                                                                                          | DoD                                           |
| ------ | --------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| **0**  | Dokumentation                                                                                                   | reviewed                                      |
| **1**  | **Grundgerüst** §3.0                                                                                            | [MVP Phase 1](./MVP.md#phase-1-grundgerüst)   |
| **2**  | Navigation (Planung, **Beschaffung-Shell**), **Settings gefiltert** §3.6, Ressorts — Planung-Tab, API groups §4 | [MVP Phase 2](./MVP.md#phase-24-nach-phase-1) |
| **3**  | Planungsrunden — Name, Auto-Schedule, open/close, Benachrichtigung §9                                           | PR3                                           |
| **4**  | Wunschformular `ressort_wuensche` §9.1 (Google Form)                                                            | PR4                                           |
| **5**  | **Ist:** Beschaffung — Bedarf, Anfragen, Offerten, Zusagen, Bestellung; Eingang = Wareneingang §0.5           | erledigt                                      |
| **5b** | **Kostenübersicht** — Ledger, Zahler, Cash/Netto [kosten.md](./kosten.md) Phasen K1–K6                        | nach/parallel PR5                            |
| **6**  | **PLANNED:** eigener Rundentyp `detailplanung`. Grob/Fein ist Ist als Stufe am Wunsch §9.2                     | nicht als zweiter Typ                         |
| **7**  | **Ist:** `publish` + Gast-Inbox + accept                                                                        | §0.7, §7.2, §8                                |
| **8**  | **Ist:** Materialübersicht (Bestand, Wareneingang, Ausgabe, Pack, Retour)                                       | §0.6, §11                                     |
| **9**  | **Ist:** Bestand Eigen/Leihweise/Gäste/J+S (Chargen, GuestShare) und Menü Fahrzeuge: Charge `family=vehicle`, Bedarf über `department_grossanlass_vehicle_need`. `department_vehicle` und Activity-Transporttouren gehören nicht zum GA-Fahrzeugmodell. **SOLL:** Fahrzeug leihweise von anderem Department — Modell offen (Bausteine: Fahrzeug-Charge, GuestShare) | §0.1, §10 |
| **10** | Activities Phasen, J+S, Pack                                                                                    | §6, §12                                       |


---

## 17. Berechtigungs-Matrix

**Soll (Aug 2026):** MW, CMW, OK-Leitung (`dc`), Bereichsleitung, Komm/Spon, LW/CLW (nur Rolle, noch keine Rechte), Helfer; Postfach und Fahrten — [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md). Die Tabelle darunter ist der ältere Schnitt (CM = MW/DC).


| Kürzel | Bedeutung                      |
| ------ | ------------------------------ |
| **GL** | org / sub / sa                 |
| **CM** | MW/DC im Grossanlass-Dept      |
| **RL** | Ressort-Lead (GroupMembership) |
| **GM** | MW/DC Gast-Pfadi-Dept          |


### Department & Navigation


| Aktion                                                | GL  | CM  | RL  | GM  |
| ----------------------------------------------------- | --- | --- | --- | --- |
| Grossanlass-Dept anlegen                              | ✓   | —   | —   | —   |
| Dashboard / Planung / Beschaffung / Materialübersicht | ✓   | ✓   | ✓*  | —   |
| Materialien Stammdaten                                | ✓   | ✓   | —   | —   |
| Struktur & Teilnehmer (Entwurf)                       | ✓   | ✓   | —   | —   |
| **Grossanlass freigeben**                             | ✓   | ✓   | —   | —   |
| Ressort / Bauprojekt anlegen                          | ✓   | ✓   | ✓*  | —   |
| Ressort löschen (Subtree-Regel §4.3)                  | ✓   | ✓   | —   | —   |
| Planungsrunde anlegen / open / close                  | ✓   | ✓   | —   | —   |
| Planungsrunde **Wünsche einreichen**                  | —   | ✓   | ✓*  | —   |
| Wunsch **bearbeiten/löschen** (eigene, Runde open)    | —   | ✓   | ✓*  | —   |
| Activity (Phase) anlegen                              | ✓   | ✓   | —   | —   |
| Materialübersicht gesamt                              | ✓   | ✓   | —   | —   |
| Materialübersicht eigenes Ressort                     | —   | ✓   | ✓   | —   |
| Zuweisung Zentrallager → Ressort                      | ✓   | ✓   | —   | —   |
| **Beschaffung** (Bedarf, Offerten, Bestellen)         | ✓   | ✓   | ✓*  | —   |
| Beschaffung **Direkt-Bedarf + Offerten** (Delegierter) | —   | —   | ✓** | —   |
| **Wareneingang** erfassen                             | ✓   | ✓   | —   | —   |
| Beschaffung **Shell** (Phase 2, leer)                 | ✓   | ✓   | —   | —   |
| Settings (Benutzer, Dept)                             | ✓   | ✓   | —   | —   |
| Settings (Pfadi-Material-Tabs)                        | —   | —   | —   | —   |
| Einladung accept/reject                               | —   | —   | —   | ✓   |
| Grossanlass in Pfadi-`/activities`                    | —   | —   | —   | ✓** |


 RL/User: gefilterte Sicht im **eigenen Ressort-Baum**; Bauprojekte **jederzeit** anlegbar §4.2. * + `guest_group_id` für Leiter/User. ** Mit `can_procure` im Ressort-Zweig.

### Backend (Ziel)

`GrossanlassAccessService`: `canUserCreateGrossanlassDepartment`, `canUserManagePlanung`, `canUserViewMaterialScope`, `canUserRespondToParticipantInvite` — Wiederverwendung `canInvitedDepartmentMwAssignGroup`, `canUserSeeInvitedActivityInList`.

---

## 18. Offene Fragen


| #   | Frage                                               | Tendenz                                     |
| --- | --------------------------------------------------- | ------------------------------------------- |
| 1   | `function_label` auf `group_membership`             | optional v1                                 |
| 2   | Struktur-FK: `department_id` vs. `main_activity_id` | beides möglich; Config verlinkt             |
| 3   | ~~RL Teilbereiche erst nach Freigabe?~~             | **Nein** — §4.2: immer für berechtigte User |
| 4   | Neue Depts nach Freigabe                            | sofort `pending` + Inbox                    |
| 5   | Inbox-Kategorie Runde geöffnet                      | `grossanlass_round_opened` (neu)            |


---

## 19. Out of scope (v1)

- Grossanlass ohne eigenes Department
- `/activities`-Liste im Grossanlass-Dept
- Activity-Erstell-Wizard (camp/event-Style)
- Ressorts an `event` anflanschen
- Typ «KALA»
- E-Mail/Push für Einladungen
- Automatischer J+S-Versand
- Pfadi-`/accounting` im Grossanlass; Kosten-Splits, Vereins-Abschreibung — [kosten.md §15](./kosten.md#15-out-of-scope-v1)

---

## 20. Implementierungsprinzipien — keine Doppelspur

Grossanlass ist **Erweiterung** der bestehenden App — **kein** paralleles Produkt mit eigenem Layout, eigenen Formular-Stilen oder eigener Benachrichtigungs-Pipeline.

**Regel:** Vor jeder neuen Komponente/Service prüfen, ob ein zentraler Baustein existiert und nur **gebrancht** oder **konfiguriert** werden muss (`is_grossanlass`, Sidebar-Einträge, Inbox-Kategorie).

### Frontend — zentral nutzen


| Bereich                 | Wiederverwenden                                                                                                                                                                                                                                                                                            | Nicht bauen                                 |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| **Shell**               | `[AppLayout.vue](../../frontend/src/components/layout/AppLayout.vue)`, `[TopHeader.vue](../../frontend/src/components/layout/TopHeader.vue)`, `[SidebarNavigation.vue](../../frontend/src/components/layout/SidebarNavigation.vue)`, `[PageShell.vue](../../frontend/src/components/layout/PageShell.vue)` | eigenes `GrossanlassLayout`, zweite Sidebar |
| **Settings-Subnav**     | `[SettingsView.vue](../../frontend/src/views/SettingsView.vue)` — gefiltert §3.6                                                                                                                                                                                                                           | volle Pfadi-Liste ungefiltert               |
| **Beschaffung**         | `GrossanlassBeschaffungView` mit Inhalt [§0.5](#05-beschaffung-anfragen-wareneingang-kosten)                                                                                                                                                                                                                | Pfadi-`/accounting` einbinden               |
| **Planung / Tabs**      | Pattern `[SettingsView.vue](../../frontend/src/views/SettingsView.vue)` + `[SettingsSubnavList](../../frontend/src/components/settings/SettingsSubnavList.vue)`; Shell-first §3.5                                                                                                                          | jedes Tab als Sidebar-Eintrag               |
| **Ressort-Baum**        | Orientierung `[GroupsSettingsView.vue](../../frontend/src/views/settings/GroupsSettingsView.vue)` — API-Fassade `/grossanlass/groups`                                                                                                                                                                      | parallele Gruppen-UI-Logik                  |
| **Route**               | `/:departmentId/…` wie Pfadi-Dept ([Router](../../frontend/src/router/index.ts))                                                                                                                                                                                                                           | neues URL-Schema `/grossanlass/…`           |
| **Formulare / Dialoge** | `E`*-Bausteine (`EDialog`, `ETextField`, `ESelect`, `EButton`) — [vuetify-standards.md](../ui/vuetify-standards.md)                                                                                                                                                                                        | rohe `V*`-Felder oder Custom-CSS pro View   |
| **Wizard Create**       | Pattern `[DepartmentModal.vue](../../frontend/src/components/DepartmentModal.vue)` (Org, Parent-Baum, User-Suche)                                                                                                                                                                                          | komplett neues Formular-Design              |
| **Menü Hinzufügen ▼**   | `v-menu` wie `[SettingsView.vue](../../frontend/src/views/SettingsView.vue)`                                                                                                                                                                                                                               | zweiter separater Header-Button             |
| **Loading / Empty**     | `ELoadingState`, `EEmptyState`                                                                                                                                                                                                                                                                             | eigene Spinner/Leerseiten                   |
| **Datum Anlass**        | `[ActivityDateRangeField](../../frontend/src/components/activities/wizard/ActivityDateRangeField.vue)` / [activity-datetime-fields.md](../ui/activity-datetime-fields.md)                                                                                                                                  | ad-hoc Date-Inputs                          |
| **Dept-Wechsel**        | bestehendes Profil-Dropdown in `TopHeader`                                                                                                                                                                                                                                                                 | eigener Dept-Switcher                       |
| **i18n**                | `de.json` / bestehende Key-Struktur (`settings.…`, `components.…`)                                                                                                                                                                                                                                         | hardcodierte Strings                        |


Übersicht weiterer Bausteine: [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md). **Planung im Detail:** [§3.5](#35-routen--leere-seiten-shell-first).

### Backend — zentral nutzen


| Bereich                  | Wiederverwenden                                                                                                                                           | Nicht bauen                                        |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| **Department**           | `Department`, `Membership`, Create-Flow `[DepartmentController](../../backend/src/Controller/DepartmentController.php)`                                   | parallele «Grossanlass»-Entität ohne Dept          |
| **Groups / Ressorts**    | bestehendes `Group` + API-Fassade `/grossanlass/groups` §4.4                                                                                              | eigene Ressort-Tabelle                             |
| **Activities**           | `Activity` + `type: grossanlass`                                                                                                                          | camp/event-Wizard duplizieren                      |
| **MW-Zuweisung**         | `addMember`-Logik, `sendDepartmentMemberAddedEmail`                                                                                                       | separater Mail-Weg                                 |
| **Inbox**                | `[InboxMessageService](../../backend/src/Service/InboxMessageService.php)`, Tabelle `inbox_message` — [nachrichtenzentrale.md](../nachrichtenzentrale.md) | zweites Notification-System                        |
| **Beschaffung**          | eigene Entitäten §0.5 / §3.7 — Fassade `/grossanlass/beschaffung/`* (**Ist**)                                                                             | Follow-ups, Abschreibung, Pfadi-Kostenstellen-CRUD |
| **Kosten Ledger**       | `department_grossanlass_cost` / `_budget` — [kosten.md](./kosten.md)                                                                                      | AccountingBooking, Follow-ups, Kostenstellen |
| **Einladungen**          | `syncGrossanlassParticipantInvites()` bei `publish` §0.7                                                                                                  | neuer Invite-Stack; E-Mail/Push an Gäste ist **PLANNED** |
| **Rechte**               | `AdminCapabilityChecker`, Membership-Rollen                                                                                                               | eigene Parallel-Matrix                             |


Neu darf es nur sein, was **domänenspezifisch** ist: `is_grossanlass`, `department_grossanlass_config`, `department_grossanlass_cost` / `_budget`, `activity_grossanlass_`*, Inbox `grossanlass_mw_assigned`, `grossanlass_round_opened`.

### UI-Grossanlass vs. Pfadi — nur Unterschiede


|                | Pfadi-Dept                                                           | Grossanlass-Dept                                                          |
| -------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Sidebar        | Standard                                                             | **Conditional** in `SidebarNavigation` (kein `/activities`, ggf. Planung) |
| Dashboard-View | `DashboardView` oder conditional / eigene View **in gleicher Route** | gleiche Shell                                                             |
| Datenflag      | —                                                                    | `department.is_grossanlass` in Membership-Response                        |


**Phase 1:** kein neues Design-System — Platzhalter-Dashboard mit gleichen Layout-Tokens wie `[DashboardView.vue](../../frontend/src/views/DashboardView.vue)`.

---

## Siehe auch

- [Konzept 2026-08-23](./20260823_New_concept.md) — Anfragen, Grob/Fein, Kontakt erst beim Nehmen
- [Rollen, Postfach, Fahrten](./rollen-postfach-fahrten.md) — MW/CMW/OK-L, Label `eMatChef`, Einsatz mit Checkbox Fahrt = Fahrauftrag, Selbstabholung, Teilpacken, Dashboard-Box
- [Bauprojekt, GA-Ort, Helfer](./bauprojekt-ort-helfer.md) — Projektfenster, Aufgaben + Material=Wunsch, Standort `/i/ga/`, Druck aus denselben Objekten
- [Kostenübersicht](./kosten.md) — Einkauf / Miete / Weiterverkauf, Zahler, Ledger
- [Aktivitäten-Übersicht](../activities/README.md)
- [J+S-Material](../activities/js-material/README.md)
- [newUI / Fuhrpark](../activities/newUI/SPEC.md)

