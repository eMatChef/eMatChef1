# Logistik-Disposition (`/{deptId}/ga/logistics/dispatch`)

Zentrale und einzige Transport-Disposition des Grossanlasses. Planung → Transporte (`/ga/planning/transports`) bleibt Status- und Planungsübersicht, `/ga/tasks` zeigt nur freigegebene Arbeiten und Fahrten.

## Datenmodell

| Begriff | Quelle | Bemerkung |
| --- | --- | --- |
| Transportbedarf | Einsatz mit `delivery = trip` (`DepartmentGrossanlassEinsatz`) oder Fahrzeugwunsch (`DepartmentGrossanlassVehicleNeed`) | werden nie kopiert; Pack, Standort, Material bleiben an ihren Datensätzen |
| Tour | `department_grossanlass_tour` | organisatorische Zusammenfassung; `status` gespeichert nur `draft`/`confirmed`, `underway`/`done` ergeben sich aus den Fahraufträgen |
| Tourbedarf | `department_grossanlass_tour_item` | genau ein Einsatz **oder** ein Fahrzeugwunsch, je höchstens eine Tour (UNIQUE) |
| Stopp | `department_grossanlass_tour_stop` | Reihenfolge, Art (`load`, `pickup`, `unload`), Standort, geplante Zeit |
| Protokoll | `department_grossanlass_tour_event` | wer hat wann erstellt, geändert, bestätigt oder zurückgenommen |

`ActivityTransportTour` (Pfadi-Materialreise) ist unabhängig und unverändert.

**Fahrzeugwunsch ↔ Fahrauftrag:** Ein Fahrzeugwunsch gilt als abgedeckt, wenn ein Fahrauftrag über dieselbe Beschaffungslinie existiert (bestehend, indirekt). Ein Bedarf wird nie zweimal erzeugt: Die Tour verknüpft Fahrzeugwünsche direkt über `tour_item.vehicle_need_id`; Fahraufträge werden dabei nicht neu angelegt. Überführung in einen echten Fahrauftrag: siehe «Fahrzeugwunsch → Fahrauftrag» unten.

## Zustände und Freigabe

* **Tour bestätigt** (Disposition steht, verbindlich) ist nicht **Fahrauftrag zur Ausführung freigegeben** (`tripReleasedAt`, Pack-Status). Das Bestätigen ändert weder Einsatz noch Pack und setzt keine Freigabe; `/ga/tasks` bleibt unverändert.
* Entwurf → bestätigt: nur wenn Fahrzeug und Chauffeur (gültige Fahrkarte) gesetzt, mindestens ein Bedarf enthalten und kein Konflikt besteht.
* Jede Änderung an einer bestätigten Tour setzt sie auf Entwurf zurück (neue Bestätigung nötig).
* Sobald ein Fahrauftrag der Tour unterwegs oder erledigt ist, ist die Tour gesperrt.
* Bedarf in einer Tour zählt im Leitstand als «disponiert»; er erscheint nicht mehr unter «Offen».

## Konflikte

Zeitfenster einer Tour = Vereinigung der Bedarfsfenster und der geplanten Stoppzeiten. Konflikt bei Überschneidung mit einer anderen (nicht erledigten) Tour oder einem Fahrauftrag ausserhalb der Tour, der dasselbe Fahrzeug oder denselben Chauffeur bindet. Mehrere Wünsche mit überlappenden Fenstern sind zulässig. Konflikte werden angezeigt und blockieren die Bestätigung.

## Vorschläge

`suggestions` im Dispatch-Lesemodell: offene Bedarfe ohne Tour am selben Tag mit gleichem Ziel (`sameTarget`), sonst mit höchstens zwei Stunden Abstand (`sameWindow`). Reine Hinweise: Übernehmen legt nur einen Entwurf an.

## Berechtigungen

Lesen: mw, cmw, dc (`canSeeAnlassOverview`) und Ausgabe-Rollen. Schreiben (Tour anlegen, ändern, bestätigen, löschen): nur `canReleaseTrip` (mw, cmw). `can_manage` im Lesemodell steuert die Oberfläche; der Server prüft jede Schreibaktion selbst.

## API (`/api/departments/{departmentId}/grossanlass/logistics`)

| Methode | Pfad | Zweck |
| --- | --- | --- |
| GET | `/dispatch` | Bedarfe, Touren, Vorschläge, Chauffeure, Fahrzeuge, `can_manage`, `now` (BusinessClock) |
| POST | `/tours` | Tourentwurf aus `need_ids` (optional `name`, `vehicle_id`, `chauffeur_user_id`, `trailer`) |
| PATCH | `/tours/{id}` | Name, Fahrzeug, Chauffeur, Anhänger |
| DELETE | `/tours/{id}` | Tour löschen (Bedarfe werden wieder offen) |
| POST | `/tours/{id}/needs` | Bedarf hinzufügen (`need_id`) |
| DELETE | `/tours/{id}/needs/{needId}` | Bedarf entfernen (letzter Bedarf löscht die Tour) |
| PUT | `/tours/{id}/stops` | Stopps ersetzen: Reihenfolge, Art, Standort, `planned_at`; Aufladen/Abholen muss vor Abladen liegen |
| POST | `/tours/{id}/confirm`, `/unconfirm` | Bestätigen bzw. Bestätigung zurücknehmen |
| POST | `/needs/{vn:id}/convert` | Fahrzeugwunsch in einen Fahrauftrag überführen (nur Logistik, 201) |

Fehler: 400 (fachlich, z. B. Konflikt), 403 (Rolle). Code: `GrossanlassDispatchService` (Lesemodell, Befehle, Vorschläge), `GrossanlassTourService` (Tour, Stopps, Konflikte). Frontend: `views/grossanlass/logistik/useGaDispo.ts` (ein Datenbestand, Neuladen nach jeder Aktion). `gaDispoMock.ts` speist nur noch Live-Ansicht und Aufträge-Demo.

## Freigabe, Auslieferung, Abschluss (Phase 3)

**Freigabe** (nur `canReleaseTrip`, mw/cmw) liegt zentral in der Disposition und nutzt die bestehende Einsatzregel (`trip_released`, nur gepackt, Pack `trip_released`). Liegt der Fahrauftrag in einer Tour, muss sie bestätigt sein; Chauffeur und Abladeort der Tour werden dann in den Fahrauftrag übernommen, damit `/ga/tasks` («Meine Einsätze»: Chauffeur = angemeldeter Benutzer; «Alle Aufgaben»: alle Freigegebenen) und der QR-Ablauf dieselben Daten sehen. Fahrzeugwünsche ohne Fahrauftrag sind nicht freigebbar. Die Freigabe lässt sich zurücknehmen, solange das Material nicht unterwegs ist (Pack wieder `staging`). Auf Planung → Transporte gibt es keine Freigabeaktion mehr; die Kacheln zeigen Status, Tour und einen Link in die Disposition.

**Fahrzeugwunsch → Fahrauftrag** (`POST …/logistics/needs/vn:{id}/convert`, nur `canReleaseTrip`): Der Wunsch wird zum Einsatz `delivery = trip`, direkt verknüpft über `department_grossanlass_einsatz.vehicle_need_id` (partieller Unique-Index: höchstens ein Fahrauftrag je Wunsch; erneutes Überführen → 400). Überführbar ist nur ein Wunsch mit `purpose = transport` (Fahrzeug soll etwas transportieren; Schalter im Wunschformular). `purpose = site_use` (Standard, auch für alle bestehenden Wünsche: Fahrzeug wird auf der Baustelle gebraucht) wird nie zum Fahrauftrag, auch nicht mit Fahrzeug an der Linie. Objekt ist das **eine** Fahrzeug (`family = vehicle`) an der Beschaffungslinie des Wunsches, Menge 1, Zeitfenster und Zielort aus Wunsch bzw. Ressort; Verfügbarkeit wird wie bei jedem Einsatz geprüft, ein Standard-Pack wird angelegt. Der neue Fahrauftrag ist **nicht freigegeben** und hat keinen Chauffeur. Liegt der Wunsch bereits in einer Tour, wird der Tour-Eintrag auf den Fahrauftrag umgehängt (Stopps, Reihenfolge bleiben). Beschreibt der Wunsch nur einen Fahrzeugbedarf (keine Linie, kein oder mehrere Fahrzeuge), wird nichts erfunden: 400 mit Begründung. Im Bedarfsdialog der Disposition erscheint «In Fahrauftrag überführen» für Fahrzeugwünsche.

**«Meine Einsätze» für Chauffeure ohne Materialübersicht:** `GET …/uebersicht` liefert Mitgliedern ohne Recht auf die Materialübersicht (z. B. Logistikwart `lw`) nur die eigenen, zur Ausführung freigegebenen Fahraufträge (`chauffeur_user_id` = Benutzer, `released`); alles Übrige bleibt leer. Vorher antwortete der Endpunkt mit 403, die Ansicht blieb leer und eine zugeteilte, freigegebene Fahrt erschien nicht. Die Freigaberegel ist unverändert; «Alle Aufgaben» bleibt davon getrennt.

**Planung vs. Disposition:** Planung → Transporte erfasst nur Transportanforderungen (Zeit, Objekt, Ziel; das Erfassungsformular hat keine Chauffeur-Auswahl, Payload `chauffeur_deferred`). Der Chauffeur kommt ausschliesslich über die Tour der Disposition; die Freigabe ohne Tour verlangt einen bereits gesetzten Chauffeur.

**Berechtigung der Scans:** `scan-start`, `scan-arrive` und `arrive-manual` verlangen serverseitig, dass der Benutzer der dem Fahrauftrag zugeteilte Chauffeur ist oder `canReleaseTrip` (Logistik) hat; andere Mitglieder erhalten 403 und verändern nichts. Pack-, Einsatz- und Freigabestatus gelten unverändert (Start nur bei «Fahrt frei», Ankunft nur unterwegs).

**Übernahme und Ablieferung** bleiben der bestehende Ablauf `staging → trip_released → in_transit → at_place` (Pack-QR starten, Ziel-QR scannen). Neu ist nur die **begründete Ausnahme**: `POST …/packs/{id}/arrive-manual` (Begründung ab 5 Zeichen, nur Chauffeur des Fahrauftrags oder Logistik, nur bei Pack unterwegs) wirkt wie der Ziel-Scan und wird in `department_grossanlass_dispatch_event` protokolliert. Erreichbar auf der Pack-Seite des Chauffeurs und im Bedarfsdialog der Disposition.

**Tourabschluss** (`POST …/tours/{id}/close`): nur bestätigte Touren. Ausgelieferte Fahrten bleiben als `delivered` dokumentiert, nicht gestartete werden `not_executed`, verlieren die Freigabe (Pack wieder bereitgestellt) und den von der Tour gesetzten Chauffeur und sind wieder offener Bedarf. Fahrten, deren Material unterwegs ist, müssen zuerst abgeliefert werden. Der Fahrauftrag, sein Pack und die Historie bleiben; die Tour-Zuordnung wird durch `tour_item.outcome` gelöst (Unique-Index nur für aktive Zuordnungen), es entstehen weder neue Bedarfe noch Packs.

**Änderungswünsche der Ressorts** (`department_grossanlass_change_request`): `POST …/needs/{id}/change-requests` (Ressortleitung, Bereichsleitung oder Logistik) mit Hinweis, neuem Zeitraum und/oder anderem Ziel-Ort. Die bestehende Planung bleibt gültig. Die Logistik entscheidet über `POST …/change-requests/{id}/decision` (`accept`, `adjust`, `reject` mit Begründung); Übernahme ändert den Einsatz über die bestehende Einsatz-Aktualisierung (inkl. Verfügbarkeitsprüfung) und setzt eine bestätigte Tour wieder auf Entwurf. Wünsche und Entscheide sind protokolliert.

**Leitstände:** `logistikBoard` (Demo-Anzeige `/display-demo`) liest nach dem Laden der Disposition ausschliesslich echte Bedarfe, Touren und Chauffeure; ohne Daten (Tests, Demo ohne Backend) bleibt der Demo-State die Quelle, nie beides nebeneinander. `gaAuftraegeMock.transportStatus` verweist auf Mock-Aufträge mit Mock-IDs und hat keine echte Entsprechung, deshalb unverändert.

## Demo-Seed «Grossanlass Event» (Logistik)

Der Seed (`DemoGrossanlassEventJobsSeedService`, `DemoGrossanlassEventProcessSeedService`, idempotent über den Seed-Ledger, feste Termine 2027, BusinessClock unberührt) enthält je eine Stufe: Fahrzeugwunsch «Podest-Elemente entladen» (`site_use`, kein Fahrauftrag), Transportwunsch «Zeltmaterial abholen» (`transport`, an die Linie «7,5-t-Lastwagen» mit zugesagter, unentgeltlicher Leihe gebunden, noch nicht überführt; die Wünsche «Tresen» und «Rampenhölzer» hängen an Linien, die schon Fahraufträge haben), «Abholung Holz» (bestätigte Tour «Abholung Holz», nicht freigegeben, Pack in Bereitstellung) «Anlieferung Teleskoplader» (bestätigte Tour, gepackt, freigegeben; die Mietware ist erst nach dem Wareneingang ausgebbar, der Pack-Scan liefert vorher 409 «nicht physisch verfügbar») und «Bauzaunelemente zum Perimeter» (Eigenbestand, gepackt, freigegeben, Chauffeur ga-lw, ohne Tour: sofort per Pack-QR übernehmen und am Ziel «Bauzaun Perimeter» abliefern). Die übrigen Fahrzeugwünsche sind `transport` ohne Fahrzeug an der Linie und lassen sich bewusst nicht überführen. Ablauf als Test: `scripts/e2e-ga-logistics.sh`.

**Bekannte Testfehler ausserhalb der Logistik (nicht hier behoben):** `DemoSetupResetIntegrationTest::testEveryDepartmentScopedTableIsClassified` meldet `department_display_block` und die `department_grossanlass_ext_*`-Tabellen des Import-Strangs als nicht klassifiziert.

## Nicht Teil der Disposition

Material- und Packverwaltung (bleibt in Packen/Material), Routenoptimierung, Live-GPS.
