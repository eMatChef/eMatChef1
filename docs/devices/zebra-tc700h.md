# Zebra TC700H (Handheld)

Kurzdoku für den Einsatz als **Lager-Handheld** mit `devices.ematchef.ch` (bzw. lokal `devices.ematchef.test`).

**Stand:** Mai 2026

---

## Rolle

| Ja | Nein |
|----|------|
| Packen per Touch + optional Kamera-QR | PowerScan-Pistole (HID) — die läuft am **PC** |
| Modus **Handheld** (reduzierte UI) | Volle App-Sidebar |
| WLAN im Lager | Offline-Queue (v1) |

---

## Browser

- **Zebra Enterprise Browser** oder aktueller **Chrome/WebView**
- Start-URL als Bookmark / Kiosk-Start:
  - Prod: `https://devices.ematchef.ch`
  - Lokal: `http://app.ematchef.test:5173` (bis Devices-Subdomain live)

---

## Empfohlene Einstellungen

| Einstellung | Wert |
|-------------|------|
| Bildschirm | An lassen während Schicht (oder kurzes Timeout + Wake on Scan) |
| URL beim Scan | **Nicht** automatisch QR-URL im Browser öffnen, wenn DataWedge aktiv — siehe Pack-App |
| Rotation | Portrait für eine Hand |
| Zoom | 100 % — UI ist für grosse Touch-Targets designed |

---

## DataWedge (falls Kamera-Scan auf TC70)

Wenn ihr Barcodes mit der **eingebauten Kamera** lest (nicht Pistole):

- Profil für Enterprise Browser
- Intent oder Keyboard-Output in die **Pack-App Scan-Input** — nicht in die Adresszeile
- Suffix **Enter** senden (wie Pistole)

Details profilspezifisch — beim Rollout mit Zebra-Doku abstimmen.

---

## Netz

- Stabiles **WLAN** im Lager; gleiche SSID wie Dev beim Testen
- DNS für `*.ematchef.test` / `*.ematchef.ch` wie in [local-dev-handheld.md](./local-dev-handheld.md)

---

## Siehe auch

- [concept.md](./concept.md) — Handheld vs. Desktop
- [pack-workflow.md](./pack-workflow.md)
