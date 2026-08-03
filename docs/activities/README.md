# Aktivitäten (Anlässe / Events)

Dokumentation zum **Aktivitäts-Lebenszyklus** in eMatChef: Status, Packliste, Retour, Rollen.

**Stand:** Juni 2026

---

## Dokumentation

| Datei | Inhalt |
|--------|--------|
| **[pack-steps-spezifikation.md](./pack-steps-spezifikation.md)** | **Top-10 #1:** Kern + 1d + 1c erledigt · Rest → **#7** Ph. 3–6 |
| **[Kombos — Verbesserungen](../material/combos/verbesserungen.md)** | C1–C12 erledigt · Slice **#1d** geschlossen |
| **[status.md](./status.md)** | Alle Aktivitäts-Status, Übergänge, Rollen (inkl. `transport_out`, `storing`) |
| **[material-pipeline.md](./material-pipeline.md)** | Bestellung vs. Pack-Pipeline (`quantity_*`), Quick vs. Logistics |
| **[newUI/ADR-workflow-layers.md](./newUI/ADR-workflow-layers.md)** | **Entscheidung:** Stepper = Activity-Status; Material unabhängig |
| **[pack-step-ui.md](./pack-step-ui.md)** | Zentrale Pack-Step-UI (Dual-Panel, Kisten, Spiegel-Ansicht, Workflow-Confirm) |
| **[pack-workflow-rules.md](./pack-workflow-rules.md)** | **Ziel-Spezifikation** Pack-Regeln — Code: `packWorkflowRules.ts` |
| **[journey-pack-workflow-todo.md](./journey-pack-workflow-todo.md)** | **Todo:** Journey ↔ Legacy-Regeln, Kisten, Quick/Logistics |
| **[journey-pack-workflow-abnahme.md](./journey-pack-workflow-abnahme.md)** | **Abnahme Phase D:** Specs + manuelle Checkliste |
| **[newUI/](./newUI/)** | **Material-Journey UI** (neu): Checkliste, Stepper, Scan — parallel zu Legacy-Packliste, Route `pack-journey` |
| **[js-material/](./js-material/)** | J+S-Leihmaterial: Flag, Bestellformular, Dotation, Pack-Reiter, Check-Flow |
| **[grossanlass/](../grossanlass/)** | Grossanlass (eigenes Produkt): Wizard, Ressorts, Planungsrunde Bedarf — [README](../grossanlass/README.md), [MVP](../grossanlass/MVP.md) |
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
| `transport_out` | Transport hin *(Logistics)* |
| `at_event` | Am Anlass |
| `transport_back` | Transport zurück *(Logistics)* |
| `returned` | Retour |
| `storing` | Einlagern |
| `completed` | Abgeschlossen |
| `cancelled` | Storniert |

Vollständige Beschreibung: [status.md](./status.md) · Architektur: [ADR](./newUI/ADR-workflow-layers.md)

**Material-Pipeline** (pro Menge, `quantity_*`): `ordered` → `packed` → `transport_out` → `at_event` → … → `stored` — [material-pipeline.md](./material-pipeline.md)

---

## Verwandte Docs

| Thema | Ort |
|--------|-----|
| Packen / Scannen (Handheld) | [docs/devices/](../devices/) |
| QR / Anlass-Link | [docs/qr/](../qr/) |
| Inbox bei Statuswechsel | [nachrichtenzentrale.md](../nachrichtenzentrale.md) |
