# Datalogic PowerScan 8530 (Pistole am PC)

Kurzdoku für den Einsatz am **Laptop/PC** im Lager — Bluetooth, Dock mit USB (Tastatur-Wedge).

**Stand:** Mai 2026

---

## Rolle

| Ja | Nein |
|----|------|
| Schnelles Scannen von QR-**URLs** (+ Enter) | Primär am TC700H — dort **kein** typischer PowerScan-Einsatz |
| Modus **Desktop Lager** auf `devices.` | Gleiche reduzierte UI wie TC70 (optional etwas mehr Infos) |
| Dock = USB HID (CH-Tastaturlayout) | — |

---

## Verhalten

Die Pistole verhält sich wie eine **Tastatur**:

1. Scan liest QR (meist volle URL `https://qr.ematchef.ch/i/m/…/b/…` oder `…/i/a/…`)
2. Zeichen werden „getippt“
3. Meist **Enter** am Ende

Die Pack-App auf `devices.`:

- Fängt Input in **verstecktem Scan-Feld** (oder globalem Listener) ab
- **Parst** die URL — öffnet **nicht** `qr.ematchef.ch` im Browser

---

## Einrichtung (Richtlinien)

| Punkt | Empfehlung |
|--------|------------|
| Modus | HID Keyboard Wedge |
| Suffix | Enter (CR/LF) |
| Prefix | keiner |
| CH-Layout | Tastatur Schweiz/DE am PC passend zum Scanner |
| Bluetooth | Gepairt mit PC; Dock nur Laden + USB falls gewünscht |

Konkrete Schritte: Datalogic-Konfigurationssoftware / Handbuch zum 8530.

---

## Browser am PC

- `https://devices.ematchef.ch/{dept}/pack/{activityId}` — Session offen lassen
- Fenster fokussiert; Scan-Feld aktiv
- Optional: sichtbares Log der letzten 10 Scans (Desktop-Modus)

---

## Fehlerbilder

| Symptom | Ursache | Fix |
|---------|---------|-----|
| Browser springt zu `qr.` | Scan landet in Adresszeile | Fokus auf Pack-App; DataWedge-Profil |
| Falsche Zeichen in URL | Layout CH/DE | Scanner-Layout anpassen |
| Doppel-Scan | Enter doppelt | Entprellen in App |
| Nichts passiert | Falsche Session / offline | Netz + Aktivität prüfen |

---

## Siehe auch

- [concept.md](./concept.md)
- [pack-workflow.md](./pack-workflow.md)
- [local-dev-handheld.md](./local-dev-handheld.md) — PC lokal testen
