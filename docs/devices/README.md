# Lager-Geräte (`devices.ematchef.ch`)

Handheld (**Zebra TC700H**), Pistolen-Scanner (**Datalogic PowerScan 8530** am PC) und optional Smartphones für Packen, Live-Fortschritt und später Inventur.

**QR-URLs** (Etiketten, PDF, Display): [docs/qr/](../qr/)

**Stand:** Mai 2026 · **Status:** D1–D3 im Frontend umgesetzt · D4 (Retour) offen

---

## Dokumentation

| Datei | Inhalt |
|--------|--------|
| **[concept.md](./concept.md)** | Gesamtkonzept: Subdomain, zwei UI-Modi, Scan-Parser, Hin/Retour, Live-Status, Offline, Smartphones |
| **[rollout-plan.md](./rollout-plan.md)** | Phasen D0–D8, MVP, Deploy, Checklisten |
| **[pack-workflow.md](./pack-workflow.md)** | Ablauf Scan → Buchung, APIs, Kisten-Optionen |
| **[local-dev-handheld.md](./local-dev-handheld.md)** | TC70/PC im WLAN testen, DNS, WSL, Offline-Verhalten |
| **[zebra-tc700h.md](./zebra-tc700h.md)** | Handheld, Enterprise Browser, DataWedge |
| **[datalogic-powerscan.md](./datalogic-powerscan.md)** | Pistole am PC, HID-Wedge |

---

## Kurzüberblick

| Thema | Ort |
|--------|-----|
| Etikett-QR (Material + Charge) | `qr.ematchef.ch/i/m/{mat}/b/{batch}` |
| Anlass-QR | `qr.ematchef.ch/i/a/{activityCode}` |
| **Lager-Packen** | `devices.ematchef.ch/{dept}/pack/{activityId}` |
| Scanner tippt URL | Parser auf `devices.` — **kein** Wechsel zu `qr.` |
| Infoscreen (Kiosk) | `app.ematchef.ch/display/{publicId}` |
| Volle Verwaltung | `app.ematchef.ch` |

---

## Zwei Geräte, eine App

| Gerät | UI-Modus |
|--------|-----------|
| Zebra TC700H | **Handheld** — minimal, Touch |
| PC/Laptop + PowerScan | **Desktop Lager** — mehr Infos, Tabelle, Scan-Log |

Login einmal pro Schicht; **ein Gerät = eine Abteilung**.

---

## Zwei Flows

1. **Hin** — Materiallager → Aktivität  
2. **Retour** — Aktivität → Materiallager  

Umschalter in der Pack-Session; gleiche APIs wie `ActivityPackListTab` in der App.

---

## Lokal testen (D1–D2)

1. `hosts`: `127.0.0.1 devices.ematchef.test app.ematchef.test`
2. Frontend: `http://devices.ematchef.test:5173` (oder Nginx-Port 80)
3. Einloggen → Abteilung wählen/pinnen → Anlass scannen oder Liste «Packen»

Siehe [local-dev-handheld.md](./local-dev-handheld.md).

## Nächster Schritt (Entwicklung)

Rollout **Phase D4**: Retour-Flow — siehe [rollout-plan.md](./rollout-plan.md).

Abhängigkeit: [QR-URL-Umbau](../work/qr-url-umbau-plan.md) Phase 1–7 abgeschlossen.
