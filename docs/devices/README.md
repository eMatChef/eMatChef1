# Lager-Geräte (`devices.ematchef.ch`)

Handheld (z. B. Zebra TC700H) und Pistolen-Scanner (z. B. Datalogic PowerScan) für Packen, Scan und später Inventur.

**QR-URLs und öffentliche Seiten** sind zentral dokumentiert unter:

- [docs/qr/link-schema.md](../qr/link-schema.md) — URL-Regeln, `public_code`, kurze `/i/b/`-Form
- [docs/qr/qr-public-pages.md](../qr/qr-public-pages.md) — was auf `qr.ematchef.ch` öffentlich angezeigt wird

---

## Kurzüberblick

| Thema | Ort |
|--------|-----|
| Etikett-QR (Material + Charge) | `qr.ematchef.ch/i/m/{mat}/b/{batch}` — siehe QR-Doku |
| Scanner tippt URL + Enter | `devices.ematchef.ch` — Wedge/DataWedge, kein Seitenwechsel zu `qr.` |
| Packen | `devices…/{dept}/pack/{activity}` (geplant) |
| Infoscreen mit QR zum Abscannen | `app.ematchef.ch/display/{publicId}` (PIN + Cookie) |

---

## Geplante Dokumentation (Erweiterung)

- `zebra-tc70.md` — DataWedge, Enterprise Browser
- `datalogic-powerscan.md` — Bluetooth HID, Dock, CH-Tastatur
- `pack-workflow.md` — Scan → Pack-Position

Hardware-Setup und Implementierung folgen der QR-Spezifikation in `docs/qr/`.
