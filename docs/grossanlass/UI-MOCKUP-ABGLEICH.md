# Grossanlass: UI-Mockup-Abgleich

**Stand:** 11. Oktober 2026, Branch `feat/ga-event-demo-seed`. Arbeitsgrundlage für die gemeinsame Bearbeitung der GA-Menüpunkte, einer nach dem anderen. Die fachliche Ist-Dokumentation bleibt [README.md](./README.md) (§0); dieses Dokument verknüpft sie mit den Mockups und hält offene Punkte fest.

**Leseregel:** «UI-Stand» heisst *vorhanden* (angebunden an Backend/API), *Prototyp* (Oberfläche steht, Demo-Daten im Frontend, mit Badge «Prototyp · Demo-Daten» gekennzeichnet) oder *teilweise*. Nichts davon ist als fertig zu lesen, was als Prototyp geführt wird.

---

## 1. Wo die Mockups liegen

| Quelle | Befund |
| --- | --- |
| Branch `feat/grossanlass-menu-ui` | Nur **lokal** vorhanden, kein `origin/feat/grossanlass-menu-ui`. Basis `89b66360`, drei Commits `80ba7ef1` (Planung/Material/Logistik-UI, 125 Dateien), `59e69d92` (Beschaffungskontext), `896f79cc` (Node-Heap für `vue-tsc`). |
| Squash-Merge `e662d217` «Feat/grossanlass menu UI (#298)» | Der Inhalt des Branches ist **bereits in HEAD**. Alle Mockup-Verzeichnisse (`aufgaben`, `auftraege`, `ausgabe`, `displays`, `helferpool`, `live`, `logistik`, `packen`, `ressourcen`, `rueckbau`, `wareneingang`, `weiterverkauf`, `public/QrMaterialDemoView.vue`) liegen in `frontend/src/views/grossanlass/` bzw. `views/public/`. |
| Unterschied Branch ↔ HEAD (Mockup-Dateien) | Nur 12 Dateien, ausschliesslich **veraltete Pfade** im Branch (`/tasks/aufgaben`, `/helferpool`, `/material/pack` statt `/dept/tasks`, `/ga/helper-pool`, `/ga/material/pack`). HEAD ist die neuere, kanonische Fassung. **Es wurde nichts vom Branch übernommen** (kein Merge, kein Cherry-Pick). |
| Zusätzliche Konzepte | [README.md](./README.md), [20260823_New_concept.md](./20260823_New_concept.md) (historisch), [MVP.md](./MVP.md) (historisch), [materialfluss.md](./materialfluss.md), [rollen-postfach-fahrten.md](./rollen-postfach-fahrten.md), [bauprojekt-ort-helfer.md](./bauprojekt-ort-helfer.md), [kosten.md](./kosten.md), [business-clock.md](./business-clock.md), [../demo/scenarios/](../demo/scenarios/). Es gibt **keine** Screenshot-/Bilddateien der Mockups im Repository; die Mockups *sind* die Vue-Prototypen mit Demo-Daten (`ga*Mock.ts`). |

Folge: Ein «Mockup-Vergleich» heisst hier *Prototyp-Seite ↔ später angebundene Seite*. Die Prototypen sind die gestalterische Referenz und bleiben unverändert, bis der jeweilige Menüpunkt besprochen ist.

---

## 2. Navigation (Ist)

Eine einzige Sidebar (`components/layout/SidebarNavigation.vue`). MW/CMW sehen die **gruppierte** Sicht (Zielbild), die übrigen GA-Rollen die flache Sicht mit denselben Zielrouten. Berechtigungsabhängige Sichtbarkeit, aktive Markierung, Scroll-Verhalten der Sidebar und die kontextabhängige GA-Hilfe (`/ga/help/ga`) sind unverändert.

| Gruppe | Menüpunkt | Kanonische Route | Icon |
| --- | --- | --- | --- |
| – | Dashboard | `/{deptId}` | – |
| Anlass | Ressorts / Mein Ressort | `/{deptId}/ga/my-unit` | `mdi-home-group` |
| Anlass | Planung | `/{deptId}/ga/planning` → `requests` | `mdi-calendar-clock` |
| Anlass | Helferpool | `/{deptId}/ga/helper-pool` | `mdi-account-group-outline` |
| Anlass | Aufgaben | `/{deptId}/dept/tasks` | `mdi-clipboard-list` |
| Material | Beschaffung | `/{deptId}/ga/procurement` | `mdi-cart-outline` |
| Material | Material | `/{deptId}/ga/material` → `stock` | `mdi-package-variant` |
| Logistik | Logistik | `/{deptId}/ga/logistics` → `dispatch` | `mdi-truck-fast-outline` |
| Betrieb | Werkstatt | `/{deptId}/ga/workshop` | `mdi-wrench` |
| Finanzen | Kosten | `/{deptId}/ga/costs` | `mdi-cash-multiple` |
| Kommunikation | Nachrichten | `/{deptId}/ga/notifications` | `mdi-bell-outline` |
| Kommunikation | Displays | `/{deptId}/ga/displays` | `mdi-television` |
| unten | Grossanlass verwalten | `/{deptId}/ga/activity-settings` | `mdi-tune` |
| unten | Konfiguration | `/{deptId}/ga/settings` | – |
| unten | Hilfe | `/{deptId}/ga/help/ga` | – |

**Kompakte Hauptnavigation:** Unter «Material» stehen nur «Beschaffung» und «Material», unter «Logistik» nur «Logistik». Anfragen & Offerten, Zusagen, Bestellungen und Budget sind Tabs innerhalb von «Beschaffung» (`…/procurement/*`); Fahrzeuge ist ein Tab innerhalb von «Logistik» und bleibt unter `/{deptId}/ga/vehicles` erreichbar. Nur die Sidebar-Einträge sind entfallen, Seiten und Routen bestehen unverändert. Die flache Sicht der übrigen Rollen (z. B. Bereichsleitung) behält ihren Fahrzeuge-Eintrag, weil diese Rollen keinen Logistik-Zugang haben.

**Tabs innerhalb der Menüpunkte** (kein eigener Sidebar-Eintrag, damit keine doppelten Seiten entstehen):

| Menüpunkt | Tabs (Route-Suffix) |
| --- | --- |
| Planung | Wünsche `requests`, Aufträge inkl. Bauaufträge `jobs`, Transporte `transports`, Belegung `occupancy`, Konflikte `conflicts` |
| Beschaffung | Bedarf `demand`, Anfragen `inquiries`, Offerten `offers`, Zusagen `commitments`, Bestellungen `orders`, Budget `budget` |
| Material | Bestand `stock`, Wareneingang `goods-receipt`, Ausgabe `issue`, Packen `pack`, Rückbau `teardown`, Weiterverkauf `resale` |
| Fahrzeuge | Wünsche `requests`, Fuhrpark `fleet` |
| Grossanlass verwalten | Stammdaten `general`, Ressorts `units`, Standorte `locations`, Kategorien `categories`, Anfragen-E-Mail `inquiry-email`, Teilnehmer `participants`, Freigabe `approval`, Benutzer `users` |

### Konsolidierung in diesem Schritt

- **Toter Tab «Erhalten» entfernt.** `GrossanlassBeschaffungView` bot den Tab `received`, der auf `/ga/procurement/received` zeigte. Diese Route existiert nicht (Commit `d66cffaa` «Erhalten weg»; der Wareneingang liegt am Material-Stamm). Der Tab führte ins Leere. Die Datei `GrossanlassBeschaffungErhaltenView.vue` ist weiterhin **ungenutzt** (nicht gelöscht, Entscheid beim Menüpunkt Wareneingang).
- Der Routentest `src/router/gaRoutes.spec.ts` prüft jetzt, dass jedes Menü- und Tab-Ziel eine echte Route ist und dass `procurement/received` nicht existiert.
- Sonst keine strukturelle Abweichung gefunden: keine Legacy-Aliasse, keine doppelten Seiten, englische Pfadsegmente durchgängig (Test vorhanden).

### Offene Begriffsfrage (nur dokumentiert, Datenmodell unverändert)

«**Zusagen**» (UI, Route `commitments`, Entity/Service `Commitment`) versus «**Absprachen**» (Begriff aus Konzept und Commit `d66cffaa`). Entscheid beim Menüpunkt Zusagen. Bis dahin bleibt «Zusagen».

---

## 3. Menüpunkte

Kurzformen: *Route* = unter `/{deptId}/`; *Komp.* = Vue-Datei unter `frontend/src/views/grossanlass/` (sofern nicht anders angegeben). Backend-Angaben nennen die vorhandene Controller-/API-Schicht; wo «–» steht, gibt es heute keine Anbindung.

### 3.1 Anlass

#### Dashboard
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Rollen-Home (`grossanlassHome.ts`), kein eigener Prototyp |
| Route / Komp. | `/{deptId}` · `components/grossanlass/GrossanlassDashboardPanel.vue` (+ `GrossanlassOkLagePanel`, `GrossanlassBauprojektPanel`) |
| Backend | `grossanlassPlanung`, `grossanlassProcurement`, `grossanlassRounds`, `grossanlassUebersicht`, `grossanlassInquiries`, `grossanlassGroups` |
| UI-Stand | vorhanden |
| Abweichungen | Anlasszeitraum wird als gespeicherte Wandzeit angezeigt (Fix `3d6bdfc6`). Kein Abgleich mit einem Dashboard-Mockup möglich (keines vorhanden) |
| Fachfragen | Welche Kennzahlen je Rolle; Verhältnis zu «Live» |
| Abhängigkeiten | Planung, Beschaffung, Budget |
| Nächster Schritt | Beim Durchgehen der Rollen-Homes |

#### Ressorts / Mein Ressort
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Menü-UI #298 (Gruppe «Anlass») |
| Route / Komp. | `ga/my-unit` · `GrossanlassMeinRessortView.vue`; Verwaltung `ga/activity-settings/units` · `GrossanlassRessortsTab.vue` |
| Backend | `GrossanlassGroupController`, `GrossanlassWishController`, `GrossanlassLogisticsController`, `GrossanlassUserCardController` |
| UI-Stand | vorhanden |
| Abweichungen | Beim MW alle Ressorts, sonst eigener Bereich: Mockup und Ist stimmen strukturell überein |
| Fachfragen | Sichtbarkeit Unterressorts/Bauprojekte je Rolle |
| Abhängigkeiten | Planung, Wünsche, Helferpool |
| Nächster Schritt | Mit Planung zusammen besprechen |

#### Planung (Wünsche, Aufträge, Bauaufträge, Transporte, Belegung, Konflikte)
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298: `auftraege/` (Prototyp), Bauaufträge, Transporte |
| Route / Komp. | `ga/planning/{requests,jobs,transports,occupancy,conflicts}` · `GrossanlassPlanungView` (Shell), `GrossanlassPlanungRoundsPane`, `auftraege/GrossanlassAuftraegeView`, `GrossanlassBauauftraegeView`, `GrossanlassFahrauftraegeView`, `GrossanlassMaterialEinsaetzeView`, `GrossanlassMaterialKonflikteView` |
| Backend | `GrossanlassPlanungController`, `GrossanlassPlanningRoundController`, `GrossanlassRoundFormController`, `GrossanlassWishController`, `GrossanlassUebersichtController` |
| UI-Stand | angebunden: Wünsche, Aufträge/Bauaufträge (eine Ansicht, Groups-API), Transporte, Belegung, Konflikte |
| Abweichungen | Auftrag und Bauauftrag sind ein Modell (Blatt `auftrag` bzw. `bauprojekt`); Material-/Baufortschritt sind aus Beschaffungsstand bzw. Bau-Status abgeleitet; Wunsch-Formular wählt Phase über Textauswahl (siehe §5) |
| Fachfragen | Verhältnis zu Aufgaben; Helfer-/Material-Fortschritt pro Karte erst mit einem Sammel-Endpunkt exakt |
| Abhängigkeiten | Aufgaben, Helferpool, Material, Logistik |
| Nächster Schritt | Planung-Tab für Tab, Wünsche zuerst |

#### Helferpool
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `helferpool/` |
| Route / Komp. | `ga/helper-pool` · `helferpool/GrossanlassHelferpoolView.vue`, `GrossanlassHelferDrawer.vue` |
| Backend | – (Demo-Daten `gaHelferMock.ts`) |
| UI-Stand | **Prototyp** |
| Abweichungen | Persönliche Einsätze (`ga/my-assignments`, `GrossanlassMeineEinsaetzeView`) sind angebunden, der Pool nicht |
| Fachfragen | Datenquelle der Helfer (Teilnehmer? Benutzerkarten?), Verfügbarkeit |
| Abhängigkeiten | Aufgaben, Aufträge, Ressorts |
| Nächster Schritt | Datenmodell klären, dann anbinden |

#### Aufgaben
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `aufgaben/` |
| Route / Komp. | `dept/tasks/{aufgaben,meine}` (Tabs Allgemein/Inventur/Drucken bestehen) · `aufgaben/GrossanlassAufgabenMasterView.vue`, `GrossanlassMeineAufgabenView.vue`, `views/TasksShellView.vue` |
| Backend | – (Demo-Daten `gaAufgabenMock.ts`) |
| UI-Stand | **Prototyp** |
| Abweichungen | Route liegt unter `dept/tasks`, nicht `ga/` (bewusst: gemeinsame Aufgaben-Shell aller Departments) |
| Fachfragen | Aufgabe vs. Auftrag vs. Fahrauftrag; Rollen-Sichten |
| Abhängigkeiten | Aufträge, Helferpool, Packen, Logistik |
| Nächster Schritt | Nach Planung/Aufträge |

### 3.2 Material

#### Beschaffung: Bedarf
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Konzept [20260823_New_concept.md](./20260823_New_concept.md) |
| Route / Komp. | `ga/procurement/demand` · `GrossanlassBeschaffungBedarfView.vue` |
| Backend | `GrossanlassProcurementController`, `GrossanlassWishController` |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | später, beim Menüpunkt |
| Abhängigkeiten | Wünsche, Anfragen |
| Nächster Schritt | Beim Menüpunkt Beschaffung |

#### Beschaffung: Anfragen & Offerten
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Konzept (Partneranfragen, Gmail) |
| Route / Komp. | `ga/procurement/inquiries`, `offers` · `GrossanlassBeschaffungAnfragenView.vue`, `GrossanlassBeschaffungOffertenView.vue`; Mail-Einstellungen `ga/activity-settings/inquiry-email` |
| Backend | `GrossanlassInquiryController`, `GrossanlassGmailController`, `GrossanlassOutlookController`, `GrossanlassProcurementController`, `GrossanlassMailMergeService` |
| UI-Stand | vorhanden |
| Abweichungen | Neuer Platzhalter `{{ANLASSZEITRAUM}}` (siehe §5.3) |
| Fachfragen | Später |
| Abhängigkeiten | Bedarf, Zusagen |
| Nächster Schritt | Beim Menüpunkt |

#### Beschaffung: Zusagen
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Konzept; #298 passt Kontext an (`59e69d92`) |
| Route / Komp. | `ga/procurement/commitments` · `GrossanlassBeschaffungZusagenView.vue` |
| Backend | `GrossanlassCommitmentController`, `GrossanlassCommitmentService` |
| UI-Stand | vorhanden |
| Abweichungen | Begriff «Zusagen» vs. «Absprachen» (siehe §2) |
| Fachfragen | Begriff; Verhältnis zu Bestellung |
| Abhängigkeiten | Anfragen, Bestellungen, Budget (Stufe «zugesagt») |
| Nächster Schritt | Beim Menüpunkt |

#### Beschaffung: Bestellungen
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Konzept |
| Route / Komp. | `ga/procurement/orders` · `GrossanlassBeschaffungBestellungenView.vue` |
| Backend | `GrossanlassProcurementController` |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | Später |
| Abhängigkeiten | Zusagen, Wareneingang, Budget (Stufe «bestellt») |
| Nächster Schritt | Beim Menüpunkt |

#### Beschaffung: Budget Material & Logistik
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | [kosten.md](./kosten.md) |
| Route / Komp. | `ga/procurement/budget` · `GrossanlassBeschaffungBudgetView.vue` (Commit `a9486bc7`) |
| Backend | `GrossanlassProcurementController`, `GrossanlassBudgetAreaService`, Entity `DepartmentGrossanlassBudgetArea` |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | **Nicht jetzt**: Kostenstufen, Zuordnung der Position zum Bereich, Wareneingang ≠ Rechnung. Entschieden sind nur die fünf Stufen (geplant, zugesagt/budgetiert, bestellt, geliefert, tatsächlich) ohne Mehrfachaddition |
| Abhängigkeiten | Zusagen, Bestellungen, Wareneingang, Kosten |
| Nächster Schritt | Eigener Termin; keine zusätzlichen Felder auf Verdacht |

#### Material: Bestand
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 (Umbenennung «Materialübersicht» → «Material») |
| Route / Komp. | `ga/material/stock` (+ `own`, `loaned`, `guests`, `js`, `items/:itemId`) · `GrossanlassMaterialView` (Shell), `GrossanlassMaterialBestandView`, `…BestandAllesView`, `GrossanlassMaterialsTab`, `…GaesteView`, `…JsView`, `…PreviewDetail` |
| Backend | Bestehende Department-Materialverwaltung (Artikel, Chargen, Lager), `GrossanlassUebersichtController` |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | Später (siehe §7) |
| Abhängigkeiten | Wareneingang, Ausgabe, Packen |
| Nächster Schritt | Beim Menüpunkt |

#### Material: Wareneingang
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `wareneingang/` |
| Route / Komp. | `ga/material/goods-receipt` · `GrossanlassMaterialWareneingangView` → `wareneingang/GrossanlassWareneingangBestehend.vue`, `GrossanlassWareneingangNeu.vue` |
| Backend | `grossanlassProcurement`, `grossanlassGroups`, Medien-API |
| UI-Stand | teilweise (angebunden, Mockup-Dialoge) |
| Abweichungen | `GrossanlassBeschaffungErhaltenView.vue` ungenutzt; Tab «Erhalten» entfernt |
| Fachfragen | Wareneingang bucht weder Rechnung noch Zahlung |
| Abhängigkeiten | Bestellungen, Bestand, Budget |
| Nächster Schritt | Beim Menüpunkt |

#### Material: Ausgabe
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `ausgabe/` |
| Route / Komp. | `ga/material/issue` · `GrossanlassMaterialAusgabeView` (Shell), `ausgabe/GrossanlassAusgabe{Geplant,Offen,Schalter,Verlauf}.vue` |
| Backend | – (`gaAusgabeMock.ts`) |
| UI-Stand | **Prototyp** |
| Abweichungen | Tab nur für Rollen mit Ausgabe-Recht (`gaCanOperateAusgabe`) |
| Fachfragen | Später |
| Abhängigkeiten | Packen, Aufgaben, QR-Identitäten |
| Nächster Schritt | Beim Menüpunkt |

#### Material: Packen
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `packen/` |
| Route / Komp. | `ga/material/pack` · `GrossanlassMaterialPackView` mit `packen/GrossanlassPackenPlanner.vue` (Prototyp) über der bestehenden Packliste; `GrossanlassFahrerDemo`, `GrossanlassLabelPrintDialog` |
| Backend | `GrossanlassLogisticsController` (bestehende Packliste); Planner ohne API |
| UI-Stand | teilweise (Planner = **Prototyp**, Packliste darunter angebunden) |
| Abweichungen | Prototyp und Altliste stehen untereinander (Übergang) |
| Fachfragen | Später; [../activities/pack-workflow-rules.md](../activities/pack-workflow-rules.md) |
| Abhängigkeiten | Ausgabe, Logistik, Aufgaben |
| Nächster Schritt | Beim Menüpunkt |

#### Material: Rückbau
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `rueckbau/` |
| Route / Komp. | `ga/material/teardown` · `GrossanlassMaterialRueckbauView` mit `rueckbau/Grossanlass{Rueckgabe,Verkauf,Entsorgung}Panel`, `…RueckbauDecideDialog` |
| Backend | – (`gaRueckbauMock.ts`) |
| UI-Stand | **Prototyp** |
| Abweichungen | `GrossanlassMaterialRetourView.vue` (Altansicht) bleibt separat vorhanden |
| Fachfragen | Später |
| Abhängigkeiten | Weiterverkauf, Bestand, Logistik |
| Nächster Schritt | Beim Menüpunkt |

#### Material: Weiterverkauf
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `weiterverkauf/` |
| Route / Komp. | `ga/material/resale` · `weiterverkauf/GrossanlassMaterialWeiterverkaufView.vue`, `GrossanlassVerbleibDialog`, `GrossanlassVerkaufOfferDialog` |
| Backend | – (`gaVerkaufMock.ts`) |
| UI-Stand | **Prototyp** |
| Abweichungen | – |
| Fachfragen | Später; [kosten.md](./kosten.md) (Weiterverkauf) |
| Abhängigkeiten | Rückbau, Budget |
| Nächster Schritt | Beim Menüpunkt |

#### Ressourcen
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `ressourcen/gaRessourcenMock.ts` |
| Route / Komp. | Kein eigener Menüpunkt; Ressourcen-Block im Auftragsdetail `auftraege/GrossanlassAuftragRessourcen.vue` |
| Backend | – |
| UI-Stand | **Prototyp**, nur in Aufträgen eingebettet |
| Abweichungen | Im Auftrag aufgeführter Menüpunkt «Material und Ressourcen» hat keine eigene Seite |
| Fachfragen | Eigene Seite oder nur Auftragsbezug? |
| Abhängigkeiten | Aufträge, Bestand |
| Nächster Schritt | Bei Aufträgen entscheiden |

### 3.3 Logistik

#### Logistik (Disposition)
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `logistik/` |
| Route / Komp. | `ga/logistics/dispatch` · `GrossanlassLogistikView` (Shell), `logistik/GrossanlassDispositionView.vue`, `…DispoTourDialog`, `…DispoBedarfDialog` |
| Backend | – für Disposition (`gaDispoMock.ts`); Transporte unter Planung angebunden |
| UI-Stand | **Prototyp** |
| Abweichungen | – |
| Fachfragen | Später; keine eigenen Rechte vorgesehen |
| Abhängigkeiten | Transporte, Packen, Fahrzeuge, Budget (Logistik) |
| Nächster Schritt | Beim Menüpunkt |

#### Fahrzeuge
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Bestehender Fuhrpark ([../activities/newUI/SPEC.md §19.3](../activities/newUI/SPEC.md#193-transport--touren--department-fuhrpark)) |
| Route / Komp. | `ga/vehicles/{requests,fleet}` · `GrossanlassFahrzeugeShell`, `GrossanlassFahrzeugWuenscheView`, `GrossanlassFahrzeugeView` |
| Backend | Bestehender Fuhrpark, `GrossanlassLogisticsController` |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | Später |
| Abhängigkeiten | Logistik, Planung |
| Nächster Schritt | Beim Menüpunkt |

### 3.4 Betrieb, Finanzen, Kommunikation

#### Werkstatt
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | – |
| Route / Komp. | `ga/workshop` · `GrossanlassWerkstattView.vue` |
| Backend | `GrossanlassWorkshopCaseController` |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | Später |
| Abhängigkeiten | Material, Fahrzeuge |
| Nächster Schritt | Beim Menüpunkt |

#### Kosten
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | [kosten.md](./kosten.md) |
| Route / Komp. | `ga/costs` · `GrossanlassBeschaffungFinanzenView.vue` |
| Backend | `GrossanlassProcurementController`, `GrossanlassCostCalculator` |
| UI-Stand | vorhanden |
| Abweichungen | Verhältnis Kosten ↔ Budget (`procurement/budget`) noch zu klären |
| Fachfragen | Zusammenlegung oder klare Abgrenzung zu Budget |
| Abhängigkeiten | Budget, Bestellungen |
| Nächster Schritt | Zusammen mit Budget |

#### Displays / Infoscreen
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `displays/` |
| Route / Komp. | `ga/displays` (echte Geräteverwaltung) · `views/settings/MyDepartmentDisplayScreensView.vue`; Prototyp `ga/displays/demo` · `displays/GrossanlassDisplaysView.vue` mit `Display{Gesamt,Material,Logistik,Projekt,Feed}` |
| Backend | `DepartmentDisplayDeviceController`, `DepartmentDisplayScreenController`, `PublicDisplay*Controller` |
| UI-Stand | teilweise: Geräte angebunden, Inhalte der Anzeigen = **Prototyp** |
| Abweichungen | Zwei Routen (`displays`, `displays/demo`) bewusst getrennt; Prototyp nicht verlinkt als fertig |
| Fachfragen | Welche Inhalte, aus welchen Daten |
| Abhängigkeiten | Live-Ansichten, Logistik, Material |
| Nächster Schritt | Beim Menüpunkt |

#### Live-Ansichten
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | #298 `live/gaLiveModel.ts`, `gaLiveEvents.ts`, `gaSitePlaces.ts`; `GaSiteMap` |
| Route / Komp. | Kein Sidebar-Punkt. Öffentliche Ortsseite `/i/ga/:placeCode` · `views/public/PublicGrossanlassPlaceView.vue`; Live-Modell speist die Display-Prototypen |
| Backend | QR-/Ortscodes (`GrossanlassPlaceCodes`), `PublicLookup` |
| UI-Stand | offen als eigener Menüpunkt; Modell nur in Display-Prototypen |
| Abweichungen | Mockup nennt «Live-Ansichten» als Bereich, Ist hat keinen Menüpunkt |
| Fachfragen | Eigener Menüpunkt oder Teil von Displays/Dashboard? |
| Abhängigkeiten | Displays, BusinessClock |
| Nächster Schritt | Zusammen mit Displays |

#### Nachrichten
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | [../nachrichtenzentrale.md](../nachrichtenzentrale.md) |
| Route / Komp. | `ga/notifications` (Department: `dept/notifications`) · `views/NotificationsCenterView.vue` |
| Backend | Bestehende Nachrichtenzentrale |
| UI-Stand | vorhanden (geteilte Department-Seite) |
| Abweichungen | – |
| Fachfragen | – |
| Abhängigkeiten | Postfach, Rollen |
| Nächster Schritt | Kein GA-spezifischer Bedarf bekannt |

### 3.5 Verwaltung und Hilfe

#### Grossanlass verwalten (Stammdaten, Standorte, Teilnehmer, Freigabe, Benutzer, Kategorien)
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | README §2 (Setup), Menü-UI #298 |
| Route / Komp. | `ga/activity-settings/*` · `GrossanlassEinstellungenView` (Shell), `GrossanlassPlanungStammdatenView`, `GrossanlassEinstellungenStandorteView`, `GrossanlassPlanungStrukturView`, `GrossanlassPlanungFreigabeView`, `views/settings/UsersSettingsView`, `GrossanlassEinstellungenKategorienView`; fixe Daten: `components/settings/DepartmentFixedDatesManager.vue`, `components/grossanlass/GrossanlassKeyDatesPanel.vue` |
| Backend | `GrossanlassSetupController`, `GrossanlassPlanungController`, `DepartmentCalendarPeriodController`, `GrossanlassLogisticsController` |
| UI-Stand | vorhanden |
| Abweichungen | Anlasszeitraum, Phasen, Karte siehe §5 und §6 |
| Fachfragen | swisstopo-Rechte/Layer (offen) |
| Abhängigkeiten | alle |
| Nächster Schritt | – |

#### Hilfe
| Feld | Inhalt |
| --- | --- |
| Mockup-Quelle | Help-Modal/FAQ (#301) |
| Route / Komp. | `ga/help/ga/:topic?` · `GrossanlassHilfeView.vue`; `components/grossanlass/GaHelp{Modal,Faq,Hint}.vue`, `composables/useGaHelp.ts` |
| Backend | – (Texte i18n) |
| UI-Stand | vorhanden |
| Abweichungen | – |
| Fachfragen | – |
| Abhängigkeiten | alle (kontextabhängig) |
| Nächster Schritt | Hilfe-Texte jeweils beim bearbeiteten Menüpunkt nachführen |

#### Rückbau/Weiterverkauf ausserhalb Material
Es gibt keine eigenen Sidebar-Punkte, nur die Material-Tabs (siehe oben).

---

## 4. Vorgeschlagene Reihenfolge

1. **Anlass-Stammdaten und fixe Daten** (Basis für alles; weitgehend erledigt, siehe §5).
2. **Planung → Wünsche** (Phasenauswahl, Bedarf), danach **Aufträge / Bauaufträge** klären (ein oder zwei Konzepte).
3. **Aufgaben** und **Helferpool** (hängen an Aufträgen).
4. **Beschaffung**: Bedarf → Anfragen & Offerten → Zusagen (inkl. Begriff) → Bestellungen.
5. **Wareneingang** → **Bestand** (Department-Materialverwaltung wiederverwenden).
6. **Budget Material & Logistik** und **Kosten** gemeinsam.
7. **Packen → Ausgabe**.
8. **Logistik (Disposition)** und **Fahrzeuge**.
9. **Rückbau → Weiterverkauf**.
10. **Displays** und **Live-Ansichten**.
11. **Werkstatt**, **Dashboard je Rolle**, **Hilfe-Nachführung**.

Je Menüpunkt: Mockup ansehen → aktuelle Oberfläche ansehen → vorhandene Funktionen prüfen → Anforderungen gemeinsam entscheiden → erst dann implementieren und testen.

---

## 5. Anlasszeitraum, fixe Daten, Materialwunsch, Mail

### 5.1 Zeitmodell (Ist)

| Begriff | Speicherort | Bedeutung |
| --- | --- | --- |
| **Anlasszeitraum** | `planned_event_start` / `planned_event_end` der Grossanlass-Konfiguration | Gesamter organisatorischer Zeitraum inkl. Aufbau und Abbau. **Nicht** die Durchführung |
| **Fixe Daten** | `department_calendar_period`, Label `aufbau`, `grossanlass`, `abbau` (je mit Datum und Uhrzeit) | Drei eigenständige Phasen |
| Kalenderperiode `grossanlass` | wie oben | **Technisch weiter die Durchführung**; Wunsch- und Bedarfsfunktionen lesen sie so. Nicht umgedeutet |
| Fachzeitzone | Europe/Zurich | Zeiten sind Wandzeit ohne Zeitzonenumrechnung |

`GrossanlassPlanungService::syncEventCalendarPeriod` lässt `grossanlass` dem Anlasszeitraum folgen, **solange keine Perioden `aufbau`/`abbau` existieren**. Mit eigenen Phasen bleibt `grossanlass` unabhängig gepflegt. Andere Grossanlässe ohne Phasen verhalten sich unverändert.

Demo Grossanlass Event (`2d42e87c5197`), Seed `backend/data/seeds/dev-demo/organisations.json`:

| | Beginn | Ende |
| --- | --- | --- |
| Anlasszeitraum | 19.05.2027 13:00 | 23.06.2027 12:00 |
| Aufbau | 19.05.2027 13:00 | 04.06.2027 16:00 |
| Event-Durchführung (`grossanlass`) | 04.06.2027 16:00 | 06.06.2027 16:00 |
| Abbau | 06.06.2027 17:00 | 23.06.2027 12:00 |

Die Lücke 16:00–17:00 zwischen Durchführung und Abbau ist beabsichtigt. Der Seed-Katalog (`DemoOrganisationCatalog`) lehnt Phasen ausserhalb des Anlasszeitraums ab.

### 5.2 Fixe Daten beim Materialwunsch

**Bestehender Mechanismus (unverändert):** Das Wunschformular enthält ein Auswahlfeld «Zeitraum» (Standardwahl `Aufbau`, `Während Event`, `Abbau`, Mehrfachauswahl; `GrossanlassFormFieldCatalog`). `GrossanlassWishDynamicForm` ordnet die Auswahl über den Text der Option der Kalenderperiode zu (`mapWishPhaseChoiceToCalendarLabel`), zeigt den tatsächlich konfigurierten Zeitraum an der Option und übernimmt ihn als `valid_from`/`valid_to` des Wunsches. Ist keine Periode konfiguriert, zeigt die Option «Fixe Daten fehlen». Es gibt zusätzlich die Möglichkeit, einen eigenen Zeitraum zu setzen.

**Neu in diesem Schritt:** Es werden nur fixe Daten **innerhalb des Anlasszeitraums** angeboten (`periodsWithinEvent` in `utils/grossanlassWishPeriod.ts`; Quelle des Anlasszeitraums: `grossanlass_config` des Departments). Ohne bekannten Anlasszeitraum bleibt die Liste unverändert. Bestehende Wünsche werden nicht verändert; Bestände, Bestellungen und Materialbewegungen werden nicht berührt.

**Dauerhafte Verknüpfung (Befund):** Der Wunsch speichert **nicht** die Phase als Referenz, sondern (a) den gewählten Optionstext in `custom_values` und (b) den daraus übernommenen Zeitraum `valid_from`/`valid_to` als Kopie. Wird die fixe Phase später verschoben, ändert sich der gespeicherte Wunsch-Zeitraum nicht; `resolveWishNeedPeriod` leitet im Bedarf den Zeitraum aus dem Optionstext neu ab, sofern der gespeicherte Bereich unplausibel ist. Eine echte Referenz (Fremdschlüssel auf die Periode) fehlt und ist als Folgearbeit beim Menüpunkt «Planung → Wünsche» zu entscheiden.

**Einschränkung:** Die Zuordnung hängt am Optionstext (`aufbau`/`abbau`/`event`/`durchführung`). Eine umbenannte Option ohne diese Stichworte wird nicht als Phase erkannt. Hat ein älterer Grossanlass einen Anlasszeitraum, der nur die Durchführung umfasst, aber Aufbau/Abbau-Perioden ausserhalb, werden diese Phasen jetzt **nicht mehr angeboten** (so verlangt, aber zu prüfen, siehe Abschlussbericht).

### 5.3 Mail-Platzhalter

| Platzhalter | Quelle | Bedeutung |
| --- | --- | --- |
| `{{ZEITRAUMTEXT}}` | Freitext aus den Mail-Einstellungen (`grossanlass.mail.zeitraum_text`); Vorschlag-Button erzeugt Zeilen «Aufbau/Anlass/Abbau: Datum–Datum» aus den fixen Daten | **Unverändert.** Verbraucher: alle Standardvorlagen. Nicht umgedeutet |
| `{{ANLASSZEITRAUM}}` | **neu**, `planned_event_start`…`planned_event_end` | Vollständiger Anlasszeitraum, z. B. «19. Mai bis 23. Juni 2027». Nur additiv; keine Standardvorlage verwendet ihn automatisch |

Sonderfälle: gleicher Tag → «4. Juni 2027»; Jahreswechsel → beide Jahre; ohne Anlasszeitraum → leer. Die Vorlagenvorschau zeigt den echten Wert, sonst einen Beispielwert.

---

## 6. Stammdaten und Standorte

Der korrigierte Seed-Stand wurde nicht verändert, nur geprüft (Katalog-Test, Integrationstest gegen isolierte Datenbank, siehe Abschlussbericht).

| Ort | Quelle im Seed |
| --- | --- |
| Eventplatz | Legacy-Koordinaten (`grossanlass.venue`) |
| Zustellpunkt | Ettiswilerstrasse 3, 6242 Wauwil (`grossanlass.delivery`); liegt ausserhalb des Kartenausschnitts und wird **nicht** auf die Karte verschoben |
| Lagerplatz Gelände | `grossanlass.storage` |
| Lieferantenstandort Häberli Holz | Legacy-Event |
| 20 Bauprojekt-Orte | mit Kartenpositionen aus dem Legacy-Event |
| Geländekarte | Fixture `5d46cbb4` mit Bounds |

**Offen:** Rechte und Layer-Angaben der swisstopo-Karte (gesonderter Punkt, keine Änderung hier).

---

## 7. Bewusst verschobene Themen

| Thema | Entscheidung / Stand |
| --- | --- |
| **BusinessClock / Ereignishistorie** | Architektur beschlossen: Der historische Verlauf beruht langfristig auf einer Ereignishistorie (Jan Bedarf, Mär Bestellung, Mai Lieferung, Jun Einsatz, Aug Abschluss); bei Uhr = Januar erscheint die Märzbestellung in der historischen Sicht nicht als ausgelöst. Ein Zeitsprung erstellt nichts, bucht nichts, ändert keinen Bestand. **Nicht gebaut**, nichts im Frontend simuliert. Details: [business-clock.md](./business-clock.md), [../demo/BUSINESS-CLOCK.md](../demo/BUSINESS-CLOCK.md), [../demo/scenarios/GROSSANLASS-EVENT-ENTSCHEIDUNGEN.md](../demo/scenarios/GROSSANLASS-EVENT-ENTSCHEIDUNGEN.md). Die sechs Schnellwahl-Zeitpunkte bleiben |
| **Department-Materialverwaltung als Grundlage** | Wiederzuverwenden: Materialartikel, Stück/Anzahl pro Stück, Verpackungs- und Bestandeseinheiten, Bestände, Eigentum und Chargen (`GrossanlassArticleChargesPanel`, `gaCharge.ts`), Lagerstruktur, QR-Identitäten (nicht jedes Objekt braucht einen eigenen Code; individuelle Kennungen für Einheiten/Gebinde wo sinnvoll). Nichts neu entwickelt |
| **Budget** | Material und Logistik getrennt; fünf Kostenstufen verbindlich und nie als Gesamtkosten addiert; Wareneingang ≠ Rechnung/Zahlung. Details beim Menüpunkt Budget |
| **Event-Reset** | Ziel beschlossen: vollständiger Demo-Reset inkl. Prozessdaten auf den Seed-Ausgangszustand, mit Schutz anderer Departments, des Legacy-Events, von Benutzern/Identitäten, stabilen QR-Identitäten, eindeutiger Erkennung manueller Daten sowie Änderungsvorschau und Bestätigung. Der bisherige nicht löschende Reset bleibt; **kein neuer Löschmechanismus**, Umsetzung beim passenden Modulschritt |
| **Zusagen vs. Absprachen** | Begriffsfrage offen (§2) |
| **Aufträge vs. Bauaufträge** | entschieden: ein Modell (Group-Blatt), eine Ansicht |
