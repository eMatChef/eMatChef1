# Ideen-Backlog (Web)

**Status:** Ideensammlung / Priorisierung — Quelle ist die Google-Tabelle *ToDo Web*.  
**Stand:** August 2026  
**Quelle:** [ToDo Web (Google Sheet)](https://docs.google.com/spreadsheets/d/1w9QoH88Lr6Y_MEqNGMMB8unHlu-SUvopL8gRfFXqdXY/edit?usp=sharing)

Sammlung offener Produkt- und Technik-Ideen für die Web-App. Die **Top 10** priorisiert nach **Impact** (Nutzer-/Betriebsnutzen) und **Aufwand** (Umsetzungsaufwand). Bestehende Specs in `docs/` sind verlinkt — dort weiterarbeiten statt neu erfinden.

**Verwandt:** [devices/](../devices/) · [activities/](../activities/) · [grossanlass/](../grossanlass/) · [workshop/](../workshop/) · [accounting.md](../accounting.md) · [media/](../media/) · [onboarding/](../onboarding/) · [label-fulfillment.md](./label-fulfillment.md)

---

## Top 10 (Impact × Aufwand)

Bewertung: Impact und Aufwand je **1 (niedrig) … 5 (hoch)**. Priorität ≈ hoher Impact bei möglichst niedrigem Aufwand; bei Gleichstand gewinnt der Kern-Workflow (Packen / Scannen / MW).

| # | Thema | Impact | Aufwand | Warum jetzt | Anknüpfung |
|---|--------|--------|---------|-------------|------------|
| 1 | **Pack-/Bestell-Steps** — **Kern + 1d + 1c erledigt** · Abschluss-Entkopplung via **#7** | 5 | 3→1 | Step-Fluss + Kombo + Buchhaltung grün | **[pack-steps-spezifikation.md](../activities/pack-steps-spezifikation.md)** · **[#7 accounting](../accounting.md#zwei-abschlüsse-kernmodell)** |
| 2 | **Scanseite verbessern + offline** | 5 | 4 | MW-Alltag; ohne Scan/Offline stockt Lagerbetrieb | [devices/concept](../devices/concept.md), [local-dev-handheld](../devices/local-dev-handheld.md) |
| 3 | **Handheld-/Geräte-Flow** (Scanseite anbieten, Multilogin 2 Geräte, PIN/QR-Freigabe) | 5 | 3 | Devices-MVP läuft; Lücke zu Feld-tauglich | [devices/](../devices/), [device-pairing](../devices/device-pairing-and-sessions.md), [rollout](../devices/rollout-plan.md) |
| 4 | **Hilfe + Einführungsrunde MW/Leiter finalisieren** | 4 | 2 | Adoption-Blocker, schneller sichtbarer Nutzen | [onboarding/](../onboarding/README.md) |
| 5 | **Verbrauchsmat vereinfachen** (Eingabe/Löschung wenn aufgebraucht; kein QR; Barcode/Internet) | 4 | 2 | Hohe Reibung, begrenzter Scope | Material / QR |
| 6 | **PDF-Import Materialeinkauf** (Artikel/Batch + Lieferantendaten vorausfüllen) | 4 | 3 | Weniger Tipparbeit, bessere Stammdaten | [pdf-purchase-import](../material/pdf-purchase-import.md), [supplier/](../supplier/plan.md) |
| 7 | **Abrechnung in Aktivität** (Bar / Rechnung / bezahlt) + Buchhaltung aufräumen | 4 | 3 | **Phasen 1–6 erledigt** (Aug 2026) | [accounting.md — Zwei Abschlüsse](../accounting.md#zwei-abschlüsse-kernmodell) |
| 8 | **Defekt melden visuell** (Fragenkatalog / Tabs aussen–innen–Zubehör; auf Bild zeichnen) | 4 | 3 | Werkstatt-Differenzierung, weniger Rückfragen | [workshop/](../workshop/README.md) |
| 9 | **Offline-MW + Bestellstopp** wenn eingeloggter MW offline | 4 | 3 | Verhindert Chaos bei Lager-Offline; hängt an #2 | [devices/concept](../devices/concept.md) (Offline) |
| 10 | **Grossanlass überarbeiten** (Hauptmat-/Dep-Liste, Filter/Ressorts, Kontaktfelder) | 5 | 5 | Event-kritisch, hoher Aufwand — nach Kern-Packen | [grossanlass/](../grossanlass/README.md), [MVP](../grossanlass/MVP.md) |

### Bewusst nicht in Top 10 (trotzdem wichtig)

| Thema | Hinweis |
|-------|---------|
| **Komponenten-Zerlegung der Seite** | Impact 5 / Aufwand 5 — parallel laufend, kein einzelnes Feature-Release |
| **Midata Login/Register** | Hoher strategischer Impact, aber grosser Integrationsaufwand |
| **Systemüberwachung Org/Sysadmin** | Ops-wichtig, nach Stabilität von Scan/Pack |
| **J&S Tab / Entwürfe** | Schon eigener Bereich — [js-material/](../activities/js-material/README.md) |
| **Bexio / OMC Schnittstellen** | Abhängig von klaren Datenmodellen (Abrechnung, Lieferanten) |

---

## Erledigt (aus Sheet)

| Bereich | Task |
|---------|------|
| Übersetzung | Übersetzungstool eigene Seite |
| Übersetzung | Translate-Tool mit GitHub spiegeln |
| Aktivität | Materialsteps untereinander — offene Positionen offen behalten |

---

## Vollständige Ideensammlung (offen)

### Material, Packen, Scan, Offline

- PDF-Import Einkauf → Artikel erstellen oder Batch hinzufügen; PDF lesen und Adressen/Lieferant vorausfüllen → [pdf-purchase-import](../material/pdf-purchase-import.md)
- Aktivität-PDF A4 (Scan + Materialliste) mit Vorschau
- Drucken Aktivitäten/Barcodes: grosse Etikettenauswahl + Vorschau der Einstellungen
- Scanseite verbessern, offline verfügbar
- Offline-Funktion für MW; wenn MW offline → andere User dürfen in der Zeit nicht bestellen
- Steps: bestellt → gepackt → mitgenommen → am Event → zurückgebracht → retour im Lager
- Packen nach Gestell-Kategorien
- Inventur
- Fremde Barcodes scannen / suchen ob existent (z. B. Phönix)
- EMC-ID und Barcode trennen (oder in Batch, nicht zentral am Mat)
- Handheld erkennen → Scanseite anbieten; Multilogin; PIN-Login wenn freigegeben

### Verbrauchsmat, Barcodes, QR

- Bestehende Artikel-Barcodes nutzen / im Internet suchen
- Verbrauchsmat/Lebensmittel: Barcodes + Internet-Abgleich
- Vereinfachte Löschung/Eingabe wenn aufgebraucht; kein QR generieren
- QR-Druckstatus: erstellt / gedruckt / angebracht
- Druckerservice: Abteilungen melden, dass sie für andere drucken können → [label-fulfillment.md](./label-fulfillment.md)

### Defekte, Werkstatt, Medien

- Defekt melden Zelt: Fragenkatalog oder 3 Tabs (aussen / Innenzelt / Zubehör) mit visueller Markierung
- Auf Reparatur-Bildern zeichnen und speichern
- Bilder für Material; Medienordner pro Dep; Bilder/Dokumente komprimieren
- Bilder als zentrale Vorlage (nicht pro Dep kopieren) → [media/](../media/), [mediathek-zukunft](../media/mediathek-zukunft.md)
- Fahrzeugliste mit Bildern / Ausweis

### Aktivitäten, J&S, Grossanlass, Abrechnung

- Abrechnung in Aktivität (Einnahme-Vermerk Bar/Rechnung) + Buchhaltung entkoppelt — Spec: [accounting.md](../accounting.md#zwei-abschlüsse-kernmodell)
- J&S eigener Tab in Aktivität; Lager + Materialbestellung als Entwurf für J&S
- Grossanlass-Funktion überarbeiten; Hauptmat- + Dep-Mat-Liste filterbar / Ressorts
- Grossanlass-Material: selbst / zentral / vorhanden; Kontaktfelder erweitern
- Zelthangar Hajk einbeziehen
- Öffentliche Materialseite der Abteilung

### Rechte, User, Departments, Verlauf

- Rechte pro User (Org / Suborg)
- L1 / L2 / L3 pro Dep benennbar inkl. Rechte und Anzeige
- «Mein Dep» überarbeiten; Dep-Zuweisung vereinfachen (Struktur-Vorschläge)
- Neue Deps: User mit `@abteilungsname.ch` zuordnen bzw. Dep anlegen
- Avatare der User nutzen
- History/Verlauf besser aufzeichnen (wer/was/wann: User, Dep, Passwort, …)
- Gerätewechsel erkennen / protokollieren
- Massen-Import Usernamen/Listen
- Midata-Login; Register mit Midata-Daten (Adresse, Dep) ohne manuelle Org-Freigabe

### UX, Hilfe, Suche, Feedback

- Hilfeseite fertig (ggf. eigener Tab / Wegleitung mit Ausgrauen)
- CTRL/STRG Hover-Tips; Icons überarbeiten
- Suchfeld: 10 Vorschläge + letzte 5 Suchen speichern
- Feedback in der Seite (Ideen/Bugs)
- Version auf der Seite anzeigen
- Sandbox erweitern um Tools zu sehen
- Mobile-Version kontrollieren
- Einführungsrunde MW/Leiter finalisieren
- Statistiken

### Lieferanten, Import/Export, Integrationen

- Lieferantenseite: Import/Export, Rep-Aufträge, Vorlagen, Materialstore → [supplier/](../supplier/)
- Materiallisten Import/Export → [templates-import-export](../material/templates-import-export.md)
- Schnittstellen Bexio / OMC
- Mailversand 1×/Tag wenn Nachricht intern ungelesen; Mailversand testen → [mail/](../mail/), [nachrichtenzentrale](../nachrichtenzentrale.md)
- Cevimat anschreiben bei Finalversion
- Integrität für Externe; Google SEO verbessern

### Technik, Betrieb, Docs

- Bausteine der Seite als Komponenten (Wartbarkeit) → [wiederverwendbare-komponenten](../wiederverwendbare-komponenten.md), [ui/](../ui/)
- Systemüberwachung Fehler (Org / Sysadmin)
- Statusseite Ausfälle/Updates
- Dev → GitHub → Prod Zwischenschritt; test. auto bei Branch-Update aktualisieren
- Workflow-Dokument und Userrechte-Dokument erstellen

---

## Pflege

1. Neue Ideen zuerst in der [Google-Tabelle](https://docs.google.com/spreadsheets/d/1w9QoH88Lr6Y_MEqNGMMB8unHlu-SUvopL8gRfFXqdXY/edit?usp=sharing) erfassen.
2. Wenn eine Idee spezifiziert oder umgesetzt wird: hier auf die Spec verlinken bzw. streichen und unter **Erledigt** führen.
3. Top 10 bei grösseren Releases neu bewerten (Impact/Aufwand ändern sich mit dem Ist-Stand).
