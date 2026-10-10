# Infoscreen (zentrales Modul)

Ein Infoscreen zeigt ohne Benutzerlogin Anlässe, Werkstatt und Statistik eines Departments. Gleiche Basis für normale Departments und Grossanlässe.

## Gemeinsame Basis

- Datenmodell: `DepartmentDisplayScreen` (`dsp…` intern, `dsi…` öffentlich, 8-stelliger Zugangscode, nur als Hash gespeichert, `code_version`).
- Ein Grossanlass ist technisch ein Department (`Department.isGrossanlass`). Screens hängen über `department_id` am Department bzw. GA; es gibt keine GA-eigenen IDs, Entities oder Sitzungen.
- Verwaltung: eine Komponente, `MyDepartmentDisplayScreensView.vue`, erreichbar unter
  - `/{departmentId}/dept/settings/my-department/display-screens`
  - `/{departmentId}/ga/displays` (die alten GA-Demo-Displays mit Mock-Daten liegen weiter unter `/{departmentId}/ga/displays/demo`)
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
3. TV pollt alle 2 s `POST /api/public/display-pairing/{requestId}/poll` mit dem `poll_secret`. Nach der Freigabe antwortet derselbe Aufruf einmalig mit `public_id` und setzt das Display-Cookie. Danach startet `/display/{publicId}` ohne Eingabe.
4. Nach Ablauf oder bei `revoked` startet der TV selbständig eine neue Anfrage.

Sicherheitsmodell:

- QR-Token und `poll_secret` sind 256 Bit zufällig und liegen nur als SHA-256-Hash in `display_pairing_request`. Der QR allein gibt keinen Display-Zugriff, er ist nur für einen angemeldeten, berechtigten User nutzbar.
- Nur der TV mit dem `poll_secret` kann die Sitzung abholen; fremde Secrets und unbekannte IDs liefern 404 (ohne Hinweis auf die Existenz).
- Freigabe und Abholen sind atomare UPDATEs (`pending → approved → consumed`): einmalig verwendbar.
- Berechtigung wird beim Bestätigen serverseitig geprüft (`canManageDepartment` des gewählten Screens); gesperrte Screens sind nicht koppelbar und werden bei Widerruf nach der Freigabe nicht mehr ausgestellt.
- Der Prüfcode auf TV und Handy schützt gegen untergeschobene QR-Codes fremder Bildschirme.
- Vor der Freigabe liefert der Status nur `pending|expired|revoked`. Es werden nie User-JWTs oder Zugangscodes an den TV übertragen.
- Rate-Limits: 20 neue Anfragen / 10 min je IP, 20 fehlgeschlagene Polls / 15 min je IP.

## Display-Sitzung (90 Tage)

- Cookie `EMC_DISPLAY_{publicId}`: signiert (HMAC), HttpOnly, SameSite=Lax, 90 Tage, **hostgebunden** (ohne Domain) und auf den Pfad `/api/public/display/{publicId}` begrenzt. Es gelangt weder an die normale API noch an andere Subdomains und ist von der Benutzeranmeldung getrennt.
- Mehrere Screens im selben Browser überschreiben sich nicht (eigener Name pro Screen).
- Widerruf (`revoked_at`) und Code-Rotation/Reaktivierung (`code_version`) machen bestehende Cookies sofort ungültig.
- Das frühere Sammel-Cookie `EMC_DISPLAY_SESSION` wird übergangsweise noch gelesen.
- Phase 5.2: Erinnerung vor Ablauf und erneute Freigabe für weitere 90 Tage. Die Sitzung trägt dafür `exp`, `v` und die Screen-ID.

## Vorschau

„Vorschau öffnen“ in der Verwaltung öffnet `/display-preview/{departmentId}/{screenId}` in einem neuen Tab. Es ist dieselbe `DepartmentDisplayView` mit den gespeicherten Einstellungen; die Daten kommen von `GET /api/departments/{id}/display-screens/{screenId}/preview-data` (normaler User-Login, nur Verwalter). Es wird weder Cookie noch Display-Sitzung ausgestellt. Beim Zurückkehren in den Tab wird neu geladen, gespeicherte Änderungen sind sofort sichtbar.

## Lokal einrichten (manuell)

- Hosts-Eintrag (Windows, als Administrator, `C:\Windows\System32\drivers\etc\hosts`): `127.0.0.1 display.ematchef.test`
- Das mkcert-Zertifikat enthält `*.ematchef.test` und deckt die Subdomain ab. Bei einem älteren Zertifikat `scripts/generate-local-https-certs.sh` neu ausführen.
- Nginx: `display.ematchef.test` steht in `docker/nginx/*.conf`; Konfiguration neu laden (`docker exec ematchef-nginx-1 nginx -s reload`), wenn der Stack aus einem anderen Worktree gemountet ist.
- Frontend-Container neu starten, damit `VITE_DISPLAY_HOST` und `allowedHosts` greifen.
