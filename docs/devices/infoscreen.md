# Infoscreen (zentrales Modul)

Ein Infoscreen zeigt ohne Benutzerlogin Anlässe, Werkstatt und Statistik eines Departments. Gleiche Basis für normale Departments und Grossanlässe.

## Gemeinsame Basis

- Datenmodell: `DepartmentDisplayScreen` (`dsp…` intern, `dsi…` öffentlich, 8-stelliger Zugangscode, nur als Hash gespeichert, `code_version`).
- Ein Grossanlass ist technisch ein Department (`Department.isGrossanlass`). Screens hängen über `department_id` am Department bzw. GA; es gibt keine GA-eigenen IDs, Entities oder Sitzungen.
- Verwaltung: eine Komponente, `MyDepartmentDisplayScreensView.vue`, erreichbar unter
  - `/{departmentId}/dept/settings/my-department/display-screens`
  - `/{departmentId}/ga/displays` (die GA-Anzeigebeispiele mit Mock-Daten liegen weiter unter `/{departmentId}/ga/displays/demo`)
- Das Department kommt ausschliesslich aus der Route; es gibt auf der Seite keine Department-Auswahl.
- Ablauf „Infoscreen hinzufügen“: Fernseher öffnet die Display-Adresse (QR) → in der Verwaltung „Infoscreen hinzufügen“ → der vorhandene QR-Scanner (`BarcodeScannerPanel`, Modus `qr`; Fallback: Link einfügen) liest den Kopplungs-QR → der Prüfcode wird angezeigt → Name vergeben → „Verbinden“. Der Scan allein gibt nichts frei. Dialog: `components/display/DisplayPairDialog.vue`, Token-Erkennung `utils/displayPairingLink.ts`.
- Neuer Screen und Kopplung in einem Schritt: `POST /api/departments/{id}/display-screens/pairing` (`token`, `name`). Serverseitig Verwaltungsrecht, alles oder nichts (bei unbrauchbarem Token bleibt kein Screen zurück). Vorhandene Screens werden über „Bildschirm verbinden“ (`POST /api/display-pairing/requests/{token}/approve`) mit einem weiteren Fernseher gekoppelt.
- „Manueller Zugang“ zeigt Adresse und Einstieg für die manuelle ID-/Code-Eingabe. Klartext-Codes gibt es nur bei Erstellung (Weg „Ohne Scanner anlegen“) oder Code-Erneuerung, einmalig.
- Lebenszyklus: aktiv → widerrufen (Sitzungen sofort ungültig, keine Kopplung, Reaktivierung möglich) → endgültig löschen (`DELETE /api/departments/{id}/display-screens/{screenId}`, nur widerrufene Screens, Bestätigung mit Namen im UI). Zugehörige Kopplungsanfragen werden gezielt gelöscht; `dsp…`/`dsi…`-IDs bleiben in `display_deleted_id` gesperrt und werden nie wieder vergeben.
- Rechte: Rollen mw, dc, org, sub, sa (`DepartmentDisplayScreenService::MANAGER_ROLES`) und Superadmin.
- Anzeige-Engine: `DepartmentDisplayView.vue` (Daten: `DepartmentDisplayDataService`). Das Payload enthält `scope` (`department` | `grossanlass`) als Anknüpfungspunkt für GA-Inhaltsmodule (Phase 5.2).

## Display-Domain

`display.ematchef.ch` (lokal `display.ematchef.test`) nutzt den normalen Frontend-Build. Host-Erkennung: `utils/displayHost.ts` (`VITE_DISPLAY_HOST` oder Präfix `display.`).

- Auf dem Host leitet der Router-Guard jeden Pfad ausser `/display…` auf `/display/connect` um (`DisplayHomeView.vue`). Keine Sidebar, kein Login, kein Dashboard.
- Dort wird automatisch ein Kopplungs-QR angezeigt; ein Link führt zur manuellen Eingabe (`/display`, ID + Code).
- Auf dem Host wird nie eine Benutzer-Session geprüft und nie zum Login umgeleitet (`isDisplayKioskPath`).
- Backend: `APP_DISPLAY_URL` bestimmt, auf welcher Origin `display_url` der Screens erzeugt wird (leer = `APP_FRONTEND_URL`). CORS (`CORS_ALLOW_ORIGIN`) und Nginx/Caddy müssen den Host kennen.

## QR-Kopplung

1. TV: `POST /api/public/display-pairing` → `request_id`, `pair_url` (QR), `user_code` (4 Zeichen Prüfcode), `poll_secret` (bleibt im TV). Gültig 5 Minuten.
2. Handy scannt `https://app…/connect-display/{token}` (normale App, `requiresAuth`). Anzeige des Prüfcodes, Auswahl eines Screens aus `GET /api/display-pairing/screens` (nur Screens, die der User verwalten darf), Bestätigung mit `POST /api/display-pairing/requests/{token}/approve`.
3. TV pollt alle 2 s `POST /api/public/display-pairing/{requestId}/poll` mit dem `poll_secret`. Nach der Freigabe antwortet derselbe Aufruf einmalig mit `status: approved` und setzt das Geräte-Cookie (nie im JSON). Dabei entsteht atomar das Gerät mit eigener Identität und 90 Tagen Freigabe. Danach startet `/display/device` ohne Eingabe.
4. Nach Ablauf oder bei `revoked` startet der TV selbständig eine neue Anfrage.

Sicherheitsmodell:

- QR-Token und `poll_secret` sind 256 Bit zufällig und liegen nur als SHA-256-Hash in `display_pairing_request`. Der QR allein gibt keinen Display-Zugriff, er ist nur für einen angemeldeten, berechtigten User nutzbar.
- Nur der TV mit dem `poll_secret` kann die Sitzung abholen; fremde Secrets und unbekannte IDs liefern 404 (ohne Hinweis auf die Existenz).
- Freigabe und Abholen sind atomare UPDATEs (`pending → approved → consumed`): einmalig verwendbar.
- Berechtigung wird beim Bestätigen serverseitig geprüft (`canManageDepartment` des gewählten Screens); gesperrte Screens sind nicht koppelbar und werden bei Widerruf nach der Freigabe nicht mehr ausgestellt.
- Der Prüfcode auf TV und Handy schützt gegen untergeschobene QR-Codes fremder Bildschirme.
- Vor der Freigabe liefert der Status nur `pending|expired|revoked`. Es werden nie User-JWTs oder Zugangscodes an den TV übertragen.
- Rate-Limits: 20 neue Anfragen / 10 min je IP, 20 fehlgeschlagene Polls / 15 min je IP.

## Geräte, Identität und 90-Tage-Freigabe (Phase 5.2)

**Datenmodell:** `department_display_device` (`DepartmentDisplayDevice`, ID `ddv…`). Ein Infoscreen (`dsp…`/`dsi…`, unverändert) hat beliebig viele Geräte, ein Gerät zeigt genau einen Infoscreen. Felder: Name (frei änderbar), `credential_hash` (+ `previous_credential_hash` für die Rotation), `approval_expires_at`, `approved_at`, `last_contact_at`, `revoked_at`, `created_via` (`pairing`, `manual`, `migrated`), Erinnerungsmarken `reminder14_for`/`reminder3_for`. Gelöschte Geräte-, Screen- und Public-IDs stehen in `display_deleted_id` und werden nie wieder vergeben. Es gibt keine GA-eigene Geräteentität.

**Geräteidentität ≠ Freigabe.** Die Identität ist ein zufälliges 256-Bit-Credential im Cookie `EMC_DISPLAY_DEVICE` (`{deviceId}.{secret}`): `Secure`, `HttpOnly`, `SameSite=Strict`, hostgebunden (ohne Domain), Pfad `/api/public/display-device`; serverseitig nur als SHA-256-Hash. Es steht nie in URLs, Logs oder Frontend-Speicher und gelangt nicht an die normale API. Die Freigabe (`approval_expires_at`) gilt 90 Tage ab Kopplung bzw. ab Verlängerung und wird ausschliesslich serverseitig von Verwaltern gesetzt; das Gerät kann sich nicht selbst verlängern.

- Bei jedem geschützten Abruf (`GET /api/public/display-device/session|data`) prüft der Server Credential, Widerruf des Geräts und des Screens sowie Freigabe. Ohne gültige Freigabe werden keine Anzeigedaten geliefert (`401` kein/widerrufenes Gerät, `403` Freigabe abgelaufen).
- Rotation: Das Credential wird nach 7 Tagen erneuert. Das vorherige bleibt gültig, bis das neue erstmals benutzt wurde (kein Aussperren bei Verbindungsabbruch); danach ist das alte ungültig (Replay-Schutz). Restrisiko: Wer ein Cookie kopiert und vor dem Original benutzt, wird nicht erkannt.
- Rate-Limit: fehlgeschlagene Geräteabrufe pro IP (60 / 10 min), Kopplung wie in Phase 5.1.
- Verlängern setzt die Freigabe auf 90 Tage ab jetzt. Das laufende Gerät übernimmt sie beim nächsten Kontakt, die Anzeige wird nicht unterbrochen. Ein abgelaufenes, nicht widerrufenes Gerät behält seine Identität und kann administrativ wieder freigegeben werden („Wieder freigeben“, dasselbe `extend`). Ein widerrufenes Gerät lässt sich nie verlängern (409), eine neue QR-Kopplung ist nötig.
- Widerruf eines Screens widerruft alle seine Geräte (nach Reaktivierung neu koppeln). Code-Erneuerung widerruft nur Geräte, die aus dem Zugangscode entstanden sind (`manual`, `migrated`), gekoppelte bleiben. Endgültiges Löschen eines Screens löscht seine Geräte und Kopplungsanfragen und sperrt alle IDs.
- Online-Status: `last_contact_at` wird höchstens einmal pro Minute geschrieben; „online“ = Kontakt innerhalb von 5 Minuten, unabhängig vom Freigabestatus.

## Geräteverwaltung

In der gemeinsamen Verwaltung (Department und GA) pro Infoscreen: Geräteliste mit Name, Freigabestatus, Online/Offline, letztem Kontakt und Ablaufdatum; Umbenennen, Verlängern/Wieder freigeben, einem anderen Infoscreen desselben Departments zuweisen, Widerrufen, Löschen (nur widerrufene). API: `/api/departments/{id}/display-devices` (`GET`, `PATCH /{deviceId}` für `name`/`screen_id`, `POST /{deviceId}/extend`, `POST /{deviceId}/revoke`, `DELETE /{deviceId}`). Jede Aktion prüft die Verwaltungsrechte des Departments und dass das Gerät zu diesem Department gehört. Es gibt bewusst keine Funktion „URL kopieren“.

## Autostart, Warteseite, Offline-Betrieb

`display.ematchef.ch` ermittelt beim Öffnen den Gerätezustand serverseitig: aktiv oder abgelaufen → `/display/device` (kein QR, keine Eingabe), sonst QR-Kopplung. `/display/device` lädt den zugewiesenen Infoscreen (Fernumschaltung wirkt beim nächsten Abruf, alle 60 s). Abgelaufene Freigabe: Warteseite ohne Daten, Prüfung alle 30 s, Start nach Wiederfreigabe automatisch. Kein/widerrufenes Gerät: zurück zur QR-Kopplung. Betriebssystem-/TV-Autostart des Browsers ist nicht Teil von eMatChef.

Offline: Die zuletzt geladene Anzeige bleibt unbegrenzt sichtbar, mit dauerhaftem Hinweis „Offline – Daten möglicherweise veraltet“ und Zeitpunkt der letzten Aktualisierung. Wiederverbindung automatisch mit Backoff (15 s bis 5 min) und sofort beim Browser-Ereignis `online`; der erste Abruf prüft Credential, Widerruf, Freigabe und Zuordnung und liefert erst dann Daten. Die Daten liegen nur im Arbeitsspeicher der Seite (kein LocalStorage/IndexedDB/Cache); bei Widerruf oder Ablauf werden sie sofort verworfen.

## Erinnerungen

`php bin/console app:display:expiry-reminders` (täglich per Cron, siehe `deploy/SERVER-UPDATE.md`): 14 und 3 Tage vor Ablauf je eine Inbox-Nachricht (bestehende Inbox, `type = display_approval_expiry`) an die Verwalter des Departments (Rollen `MANAGER_ROLES`) und eine E-Mail an deren Benachrichtigungsadresse der Mitgliedschaft (Sprache de/en nach Profil). Pro Gerät, Ablaufdatum und Stufe genau einmal; eine Verlängerung ändert das Ablaufdatum und startet einen neuen Zyklus. Widerrufene Geräte/Screens und bereits abgelaufene Freigaben werden nicht erinnert.

## Department und Grossanlass

Die Geräteverwaltung ist identisch. Fachlich: Normale Departments wählen die Anzeigebereiche (Anlässe, Werkstatt, Statistik, Filter) per Checkbox und können den Namen ändern. Grossanlässe (`Department.isGrossanlass`) haben diese Bereiche nicht: Im UI werden sie nicht angeboten, das Backend lehnt sie in `PATCH …/display-screens/{id}` mit 400 ab; neue GA-Screens starten ohne Bereiche, die Anzeige zeigt „Anzeigevorlage folgt“. Name und Untertitel bleiben änderbar. Die GA-Anzeigebeispiele (Mock) unter `/{departmentId}/ga/displays/demo` sind unverändert; vorlagenbasierte GA-Inhalte (Layout, Veröffentlichung) werden später am Screen ergänzt (`scope` im Anzeige-Payload).

## Migration aus Phase 5.1

Alte Sitzungen waren ein signiertes Cookie pro Browser und Screen (`EMC_DISPLAY_{publicId}`, Pfad `/api/public/display/{publicId}`) bzw. das frühere domainweite `EMC_DISPLAY_SESSION`. Sie enthalten keine Geräte-ID. Beim Aufruf von `/display/{publicId}` (Lesezeichen, bisherige TV-Adresse) wird ein gültiges Alt-Cookie **dieses Browsers** einmalig in ein eigenes Gerät (`migrated`, Name „Migriertes Gerät …“) überführt, höchstens bis zum bisherigen Ablauf (nie länger als 90 Tage), und die Alt-Cookies werden gelöscht. Jeder Browser bekommt eine eigene Identität; es werden keine Geräte zusammengeführt und keine Rechte ausgeweitet. Widerrufene oder gelöschte Screens bleiben gesperrt. Geräte ohne gültiges Alt-Cookie (oder die `display.`-Startseite öffnen) koppeln einmalig neu per QR. Die manuelle ID-/Code-Anmeldung erzeugt ebenfalls ein Gerät (`manual`). Der Migrationspfad kann entfernt werden, sobald alle Alt-Cookies abgelaufen sind (spätestens 90 Tage nach dem Wechsel).

## Vorschau

„Vorschau öffnen“ in der Verwaltung öffnet `/display-preview/{departmentId}/{screenId}` in einem neuen Tab. Es ist dieselbe `DepartmentDisplayView` mit den gespeicherten Einstellungen; die Daten kommen von `GET /api/departments/{id}/display-screens/{screenId}/preview-data` (normaler User-Login, nur Verwalter). Es wird weder Cookie noch Display-Sitzung ausgestellt. Beim Zurückkehren in den Tab wird neu geladen, gespeicherte Änderungen sind sofort sichtbar.

## Lokal einrichten (manuell)

- Hosts-Eintrag (Windows, als Administrator, `C:\Windows\System32\drivers\etc\hosts`): `127.0.0.1 display.ematchef.test`
- Das mkcert-Zertifikat enthält `*.ematchef.test` und deckt die Subdomain ab. Bei einem älteren Zertifikat `scripts/generate-local-https-certs.sh` neu ausführen.
- Nginx: `display.ematchef.test` steht in `docker/nginx/*.conf`. Mountet der laufende Nginx die Konfiguration eines anderen Worktrees, der den Host nicht kennt, landet die Domain im Default-Virtual-Host (Frontend wird zwar ausgeliefert, aber nicht deterministisch). Lokale Abhilfe ohne Änderung im anderen Worktree: eigener Virtual Host `docker/nginx/display.local.conf` (nicht eingecheckt, über `.git/info/exclude` ausgeblendet) plus Compose-Override `docker-compose.display.local.yml` mit zusätzlichem Mount nach `/etc/nginx/conf.d/display.conf`. Prüfen mit `docker exec ematchef-nginx-1 nginx -t`, dann `nginx -s reload`. Dauerhaft (auch nach Container-Recreate): `scripts/dev-nginx-display.sh` legt nur den Nginx-Container mit dem zusätzlichen Mount neu an (liest die Compose-Dateien des aktiven Stacks, ändert dort nichts, Volumes und DB bleiben unberührt).
- Backend: `APP_DISPLAY_URL=https://display.ematchef.test` (lokales `docker-compose.override.yml`), Container neu anlegen, damit neue `display_url`-Werte die Domain verwenden.
- Frontend-Container neu starten, damit `VITE_DISPLAY_HOST` und `allowedHosts` greifen.
