# Smoke-E2E (Playwright)

Kurze Browser-Tests gegen die **laufende Develop-App** (nicht gegen einen Preview-Host).

## Was wird geprüft?

1. Login-Seite lädt (`/login`, Brand + Felder)
2. Optional: Login mit Test-User → URL verlässt `/login`

## Dedizierter Smoke-User

Nicht `support@…` oder Prod-Accounts verwenden.

1. Auf dem **Develop-API-Droplet** (Repo-Pfad, Backend-Container):

```bash
cd /opt/ematchef/develop   # bzw. DEVELOP_DEPLOY_PATH
# Passwort selbst wählen (stark, nur für E2E), nicht committen:
E2E_PW='…dein-geheimes-passwort…'
docker compose exec -T backend php bin/console app:ensure-e2e-user \
  --email=e2e-smoke@ematchef.ch \
  --password="$E2E_PW"
unset E2E_PW
```

(Befehl legt User an oder aktualisiert: aktiv, E-Mail verifiziert, **standardmäßig ohne Department** — erscheint nicht in der User-Suche. Optional `--department-id=…` für Membership.)

2. GitHub → Settings → Secrets and variables → Actions:

| Secret | Wert |
|--------|------|
| `E2E_BASE_URL` | `https://dev.ematchef.ch` |
| `E2E_USER_EMAIL` | `e2e-smoke@ematchef.ch` |
| `E2E_USER_PASSWORD` | dasselbe Passwort wie oben |

## Lokal

```bash
cd frontend
npm ci
npx playwright install chromium
# WSL/Ubuntu: Systembibliotheken (sonst: libasound.so.2 missing)
sudo npx playwright install-deps chromium
# oder: sudo apt-get install -y libasound2t64
export E2E_BASE_URL=https://dev.ematchef.ch
export E2E_USER_EMAIL='e2e-smoke@ematchef.ch'
export E2E_USER_PASSWORD='…'   # nie in Chat/Git pasten
npm run test:e2e
```

**Sicherheit:** E2E-Passwort nur als Env/GitHub-Secret — nie in Issues, Chat oder Git.

## Demo «Grossanlass Event 2027» (isolierte Umgebung)

`frontend/e2e/ga-event-demo.spec.ts` prüft Anlasszeitraum, Stammdaten-Zeiten, Navigation unter «Beschaffung», Budget Material & Logistik, die Schnellwahl der Demo-Uhr und dass ein Zeitsprung keine Budgetzahlen ändert. Er läuft **nur gegen eine isolierte, frisch mit `app:demo:sync --all` befüllte Umgebung** (nie gegen eine Datenbank mit von Hand veränderten Demo-Daten; die Demo-Uhr ist Datenbankzustand und der Test verstellt sie vorübergehend), aktiv nur mit `E2E_GA_DEMO=1` und dem öffentlichen Demo-Konto `ga-mw`.

Lokal ohne Eingriff in `mvdb`: Wegwerf-PostgreSQL (eigener Container, eigene Datenbank, migriert und befüllt), zweiter Backend-Container desselben Images mit `DATABASE_URL` auf diese Datenbank (Port 8083), Vite mit `VITE_API_PROXY_TARGET=http://127.0.0.1:8083` und den Hosts `app.ematchef.test` auf Port 5199; Chromium bildet die Hosts über `E2E_HOST_RESOLVER='MAP *.ematchef.test 127.0.0.1, MAP ematchef.test 127.0.0.1'` auf 127.0.0.1 ab (Playwright-Config, kein Eintrag in `/etc/hosts` nötig):

```bash
E2E_GA_DEMO=1 E2E_BASE_URL=http://app.ematchef.test:5199 \
E2E_HOST_RESOLVER='MAP *.ematchef.test 127.0.0.1, MAP ematchef.test 127.0.0.1' \
E2E_USER_EMAIL=ga-mw@demo.ematchef.ch E2E_USER_PASSWORD='<Demo-Passwort aus demo-accounts.json>' \
npx playwright test e2e/ga-event-demo.spec.ts
```

**Achtung:** Ein Neustart des Backend-Containers der Entwicklungsumgebung führt `doctrine:migrations:migrate` gegen `mvdb` aus (Entrypoint) und würde damit neue Migrationen anwenden.

`frontend/e2e/ga-order-dialog.spec.ts` prüft den `GrossanlassOrderDialog` (`ga-mw`, Bauauftrag «Bar-Tresen West»): offene Dropdowns schliessen bei Klick auf Dialogtitel, Rand und Aktionsleiste (Handler in `EDialog`), Tastatur (Pfeil/Escape) und Mausauswahl bleiben nutzbar, ein anderer `EDialog` (Wunsch bearbeiten im Bedarf-Pool) bleibt unbeeinträchtigt, und das Helferbedarf-Layout hat bei 420 px Breite keine abgeschnittenen Felder. Aktiv mit `E2E_GA_ORDERDIALOG=1`; der Test ist **rein lesend** (Aufgabenblock-Entwurf ohne Titel wird nicht gespeichert, Wunsch-Dialog wird mit «Abbrechen» verlassen). Lokal über HTTPS (`https://app.ematchef.test`, `ignoreHTTPSErrors` ist im Spec gesetzt) mit `E2E_HOST_RESOLVER` wie oben.

`frontend/e2e/ga-tasks.spec.ts` prüft die GA-Aufgaben (`/ga/tasks`, `/ga/tasks/mine`): echte API-Daten, keine Konsolen-/API-Fehler, Aufgabe anlegen, zuweisen, starten, fertig melden, abnehmen. Aktiv mit `E2E_GA_TASKS=1` und Demo-Konto `ga-mw` (optional `E2E_DEPARTMENT_ID`); der Test legt eine Demo-Aufgabe an und löscht sie am Ende wieder. Lokal über `https://app.ematchef.test` mit `E2E_HOST_RESOLVER` wie oben; der Vite-Dev-Server liefert gelegentlich einzelne Module nicht aus, der Test wiederholt sich daher bis zu dreimal.

`frontend/e2e/ga-task-checklist.spec.ts` prüft Phase 2.1 der GA-Aufgaben (`ga-mw`): Checkliste anlegen, umsortieren und abhaken, Aufgabe mit Begründung blockieren (Starten, Fertig melden und Abhaken gesperrt), Blockierung aufheben, fertig melden, mit Begründung zurückgeben, erneut melden und abnehmen. Aktiv mit `E2E_GA_TASKS=1` (wie `ga-tasks.spec.ts`); der Test legt eine Demo-Aufgabe an und löscht sie am Ende wieder. Die dabei erzeugten Nachrichten (Fertigmeldung/Abnahme) haben den Betreff «… E2E …» und lassen sich nicht per API löschen. Setzt man `E2E_CLEANUP_INBOX_CMD`, führen `ga-tasks`, `ga-task-checklist` und `ga-task-dependencies` den Befehl nach dem Lauf aus (`frontend/e2e/helpers/inboxCleanup.ts`), z. B. `docker compose exec -T db psql -U mvuser mvdb -c "DELETE FROM inbox_message WHERE subject LIKE '%E2E %'"`; ohne die Variable bleibt die Bereinigung manuell.

`frontend/e2e/ga-task-dependencies.spec.ts` prüft Phase 2.3 (`ga-mw`): Abhängigkeit zwischen zwei Aufgaben anlegen, «Wartet auf …», Start/Fertig melden gesperrt, vorzeitige Freigabe mit Begründung; der Server lehnt Start, Zyklus und Löschen mit Abhängigkeit ab. Aktiv mit `E2E_GA_TASKS=1`; die zwei Demo-Aufgaben samt Abhängigkeiten werden am Ende entfernt

`frontend/e2e/ga-task-dependency-modes.spec.ts` prüft die Art «gleichzeitig» (`ga-mw`): keine Startsperre, Zeitkonflikt (`schedule.conflict`), Anzeige an beiden Aufgaben (`incoming`), Art-Wechsel inkl. Zyklusprüfung und keine Freigabe bei «gleichzeitig», sowie im Bauauftrag-Dialog den Button «Abhängigkeit hinzufügen» mit Aufgabenwahl über Bauaufträge hinweg. Aktiv mit `E2E_GA_TASKS=1`; die zwei Demo-Bauaufträge samt Aufgaben und Verknüpfungen werden am Ende gelöscht (Verknüpfungen bleiben als Protokollzeile mit `removed_at`).

`frontend/e2e/ga-order-acceptance.spec.ts` prüft Phase 2.4 (`ga-mw`): Gesamtabnahme eines Bauauftrags im Bauauftrag-Dialog (melden, mit Begründung zurückweisen – Einzelaufgaben bleiben erledigt –, erneut melden, abnehmen, Schutz der Aufgaben, Widerruf). Aktiv mit `E2E_GA_TASKS=1`; das Spec legt einen eigenen Demo-Auftrag mit zwei Aufgaben unter «Bauten» an (`E2E_ORDER_PARENT_ID` überschreibbar) und löscht ihn am Ende wieder.

`frontend/e2e/ga-order-archive.spec.ts` prüft das Archivieren von Bauaufträgen (`ga-mw`): ein Bauauftrag ohne Arbeit lässt sich löschen, mit Arbeit nicht (409); archivieren mit Begründung, aus der Auftragsliste ausgeblendet, über «Archivierte anzeigen» auffindbar, nicht mehr bearbeitbar, wiederherstellen. Aktiv mit `E2E_GA_TASKS=1`; die Demo-Aufträge werden am Ende gelöscht, bleiben sonst archiviert.

`frontend/e2e/ga-procurement-categorize.spec.ts` prüft den Bedarf-Pool (`/ga/procurement/demand`): eine einzige Liste, Auswahl über mehrere Suchen, «Auswahl anzeigen», Massenkategorisierung inkl. ausgefilterter Wünsche und Überschreibungsbestätigung. Aktiv mit `E2E_GA_CATEGORIZE=1` und dem Demo-Konto `ga-mw`; der Test **setzt Kategorien** der Materialwünsche, sie müssen danach zurückgesetzt werden (`category_id` auf NULL), daher nur gegen eine Wegwerf-Umgebung. Lokal über HTTPS (`https://ematchef.test` via nginx; über HTTP wird das Auth-Cookie nicht übernommen) mit `ignoreHTTPSErrors`.

## CI

Workflow **E2E Smoke** (`.github/workflows/e2e-smoke.yml`):

- Läuft auf Push/PR zu `develop` / `prod`
- Installiert Chromium und führt `npm run test:e2e` aus
- Fehlt Login-Secret → nur öffentlicher Login-Seiten-Test
- Auf **Staging** und **Prod** ist der Playwright-Job in `ci.yml` **Pflicht** für «CI ok».
- Auf **Develop** ist er nur Signal (`continue-on-error`): ein hängender Droplet darf Merges nicht blockieren. CD aktualisiert Develop unabhängig.

## Preview-Deploy

Pro-PR-URLs (`pr-N.…`) sind **noch nicht** eingerichtet (braucht Host/DNS). Smokes laufen gegen Develop.

## Infoscreen-Layouts (Editor, Veröffentlichung, Kopplung)

`frontend/e2e/display-layout.spec.ts` prüft den Weg Infoscreen ohne Gerät erstellen → Vorlage/leeres Layout im Editor ändern (Drag-and-drop, Skalieren) → Vorschau für TV und LED-Wall → Entwurf speichern (Gerät unverändert) → veröffentlichen (Gerät übernimmt die Revision) → «Bildschirm hinzufügen» mit Display-Adresse und Anleitung → passive Anzeige ohne Bedienelemente und Zonenrotation; zusätzlich, dass normale Departments die bisherige Konfiguration behalten. Er legt Infoscreens und ein Gerät an und läuft deshalb **nur gegen eine isolierte, frisch migrierte Datenbank** (nie `mvdb`), aktiv mit `E2E_DISPLAY_LAYOUT=1`. Benötigt ein Grossanlass-Department (`E2E_GA_DEPT`), optional ein normales (`E2E_NORMAL_DEPT`) und ein fremdes ohne Mitgliedschaft des Users (`E2E_FOREIGN_DEPT`, prüft die Department-Isolation der API), einen Verwaltungs-User (`app:ensure-e2e-user` mit anschliessend Rolle `dc`, `E2E_USER_EMAIL`/`E2E_USER_PASSWORD`), die App-URL als `E2E_BASE_URL` und die Display-URL als `E2E_DISPLAY_URL`; die Hosts werden wie oben über `E2E_HOST_RESOLVER` abgebildet.
