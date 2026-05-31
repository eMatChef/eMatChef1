# Lokales Testen: Handheld & Lager-Geräte

Wie du `app.ematchef.test` (und später `devices.ematchef.test`) am **Zebra TC700H** oder im WLAN testest — inkl. Hinweise zu **Offline**.

**Stand:** Mai 2026

---

## Grundsatz

Das Projekt nutzt lokal **keine** rohe `localhost`-Cookie-Domain für Subdomains, sondern:

| Host | Zweck |
|------|--------|
| `app.ematchef.test` | App / später Devices-UI |
| `qr.ematchef.test` | Öffentliche QR-Seiten |
| `ematchef.test` | Marketing |

Cookies: `AUTH_COOKIE_DOMAIN=.ematchef.test` (Docker-Compose) bzw. in `backend/.env` ggf. `.localhost` — **Host im Browser muss zur Domain passen**.

Vite: `host: 0.0.0.0`, Port **5173** → vom LAN erreichbar (`frontend/vite.config.ts`).

---

## Am PC (einfach)

1. `docker compose up` (oder Backend 8081 + `npm run dev` im `frontend/`)
2. **Hosts-Datei** (unter WSL/Windows die **Windows**-Datei bearbeiten, als Admin):

   `C:\Windows\System32\drivers\etc\hosts`

   ```text
   127.0.0.1 ematchef.test app.ematchef.test qr.ematchef.test devices.ematchef.test
   ```

   Ohne diesen Eintrag: `DNS_PROBE_FINISHED_NXDOMAIN` (Browser findet den Namen nicht).

   Nach dem Speichern: Browser-Tab schliessen und neu öffnen, oder `ipconfig /flushdns` in einer Admin-CMD.

3. Browser: **`https://app.ematchef.test`** (über Nginx) oder `https://app.ematchef.test:5173` nur bei direktem Vite ohne TLS
4. Packliste testen: Aktivität öffnen → Tab Packliste

**QR-Scanner / Kamera:** Browser erlauben `getUserMedia` nur in **Secure Contexts** (HTTPS oder `localhost`). Auf `http://app.ematchef.test` erscheint **kein** Berechtigungsdialog — die Kamera ist blockiert.

Lokales HTTPS (Standard für `*.ematchef.test`):

1. `./scripts/generate-local-https-certs.sh` (einmalig, [mkcert](https://github.com/FiloSottile/mkcert))
2. `cp docker-compose.override.https.example.yml docker-compose.override.yml`
3. `docker compose up -d nginx frontend backend`
4. **`https://app.ematchef.test`** — `http://` leitet per Nginx auf HTTPS um

`VITE_APP_ORIGIN` / `APP_FRONTEND_URL` sind auf `https://` gesetzt; Login-Links von der Marketing-Seite bleiben damit auf HTTPS.

Alternativ nur zum Testen: `http://localhost:5173` (Kamera ok, Cookies/Subdomains können abweichen).

**PowerScan am Laptop:** Gleiche URL; Scanner tippt QR-URL + Enter (wenn Devices-Scan-Feld existiert, sonst Adresszeile vermeiden — Profil auf „kein URL-Open“).

---

## Handheld im gleichen WLAN (TC700H)

### Problem

- Das Handy kennt `app.ematchef.test` nicht ohne DNS.
- `http://192.168.x.x:5173` bricht oft **Cookies** (Domain passt nicht zu `.ematchef.test`).
- **WSL2:** `127.0.0.1` am PC ist vom Handy nicht erreichbar — Windows-LAN-IP + Portforward nötig.

### Empfohlene Lösung: DNS im WLAN

1. **Windows-IP** im LAN ermitteln (z. B. `192.168.1.42`).
2. Port **5173** auf Windows nach WSL durchreichen (Firewall freigeben).
3. **DNS** (eine Option):
   - Router: `app.ematchef.test` → `192.168.1.42`
   - oder **dnsmasq** auf dem Dev-PC
4. Am **PC** `hosts` anpassen — gleiche IP wie fürs Handy:

   ```text
   192.168.1.42 ematchef.test app.ematchef.test qr.ematchef.test devices.ematchef.test
   ```

5. Am TC70 Browser: **`https://app.ematchef.test`** (mkcert-CA auf dem Gerät ggf. nicht vertrauenswürdig — dann Tunnel oder IP-Workaround; später `devices.ematchef.test`)

### Alternative (schnell, aber hackig)

- Nur UI testen über IP — Login/CORS kann scheitern.
- CORS in `CORS_ALLOW_ORIGIN` um `http://192.168.x.x:5173` erweitern.
- Cookie-Domain nur für diesen Test anpassen — **nicht** committen.

### Tunnel (von überall)

Cloudflare Tunnel / ngrok mit HTTPS + Subdomain — wenig DNS-Gefummel, gut für Demo.

---

## QR-Etiketten lokal

Gedruckte/angezeigte URLs zeigen auf `https://qr.ematchef.test/...` — das Handy braucht **dieselbe DNS-Auflösung** wie für `app.`, sonst öffnet der Scanner die falsche Hostname.

Nach QR-Umbau: nur noch `…/i/m/{mat}/b/{batch}` und `…/i/a/{code}`.

---

## Offline {#offline}

### Heute (Ist-Stand Codebase)

- **Kein** Service Worker, **keine** Offline-Queue für Packen.
- Jeder Scan/Buchung = sofortiger API-Call.
- **Kein Netz** → Fehler → **nichts** wird auf dem Server gespeichert.
- **Kein** automatisches Nachreichen nach Wiederverbindung.

**Praktisch:** Scan bei wieder da sein **wiederholen**; in der App ggf. manuell nachbuchen.

### Geplant auf `devices.` (Rollout)

| Version | Verhalten |
|---------|-----------|
| **v1** | Grosse Meldung: „Offline — Scan nicht gespeichert“; letzter Scan im UI |
| **D8** (optional) | IndexedDB-Queue, Sync bei `online`, Konfliktanzeige |

Kurze Funklöcher im Lager: meist reicht v1 + Retry. Längere Ausfälle: erst mit D8 sinnvoll abgedeckt.

---

## Checkliste Handheld-Test

- [ ] Gleiches WLAN wie Dev-PC
- [ ] `app.ematchef.test` löst zur PC-IP auf (vom TC70 ping/bar im Browser)
- [ ] `:5173` erreichbar
- [ ] Login funktioniert (Cookie gesetzt)
- [ ] Aktivitäts-URL / Packliste lädt
- [ ] QR mit `qr.ematchef.test` erreichbar (für Scan-Tests)

---

## Siehe auch

- [deploy/CROSS-SUBDOMAIN-LOGIN.md](../../deploy/CROSS-SUBDOMAIN-LOGIN.md)
- [rollout-plan.md](./rollout-plan.md) Phase D1
- [zebra-tc700h.md](./zebra-tc700h.md)
