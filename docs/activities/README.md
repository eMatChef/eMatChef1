# Aktivitäten (Anlässe / Events)

Dokumentation zum **Aktivitäts-Lebenszyklus** in eMatChef: Status, Packliste, Retour, Rollen.

**Stand:** Juni 2026

---

## Dokumentation

| Datei | Inhalt |
|--------|--------|
| **[status.md](./status.md)** | Alle Aktivitäts-Status, Übergänge, Rollen |
| **[material-pipeline.md](./material-pipeline.md)** | Bestellung vs. Pack-Pipeline, Quick vs. Logistics, Zuordnung zu Aktivitäts-Status |
| **[pack-step-ui.md](./pack-step-ui.md)** | Zentrale Pack-Step-UI (Dual-Panel, Kisten, Spiegel-Ansicht, Workflow-Confirm) |
| **[pack-workflow-rules.md](./pack-workflow-rules.md)** | **Ziel-Spezifikation** Pack-Regeln — Code: `packWorkflowRules.ts` |
| **[newUI/](./newUI/)** | **Material-Journey UI** (neu): Checkliste, Stepper, Scan — parallel zu Legacy-Packliste, Route `pack-journey` |
| **[js-material/](./js-material/)** | J+S-Leihmaterial: Flag, Bestellformular, Dotation, Pack-Reiter, Check-Flow |
| **[kala/](./kala/)** | KALA / Grossanlass: `is_grossanlass`-Department, Setup-Seite, Unterlager, Ressorts, Planungsrunden |
| **[Virtuelle Kombo (Pack)](../material/combos/virtual-combo-activities.md)** | `pack_mode`, logische Packkiste, `self_provided`-Bestätigung |

---

## Kurzüberblick Status

| Status | DE |
|--------|-----|
| `draft` | Entwurf |
| `submitted` | Eingereicht |
| `approved` | Bestätigt |
| `packing` | Wird gepackt |
| `packed` | Gepackt |
| `at_event` | Am Event |
| `returned` | Retour |
| `completed` | Abgeschlossen |
| `cancelled` | Storniert |

Vollständige Beschreibung: [status.md](./status.md)

---

## Verwandte Docs

| Thema | Ort |
|--------|-----|
| Packen / Scannen (Handheld) | [docs/devices/](../devices/) |
| QR / Anlass-Link | [docs/qr/](../qr/) |
| Inbox bei Statuswechsel | [nachrichtenzentrale.md](../nachrichtenzentrale.md) |
