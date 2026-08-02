
# Material-Journey — Pack-Workflow Todo

**Stand:** Juni 2026  
**Kontext:** [ADR Workflow-Layers](./newUI/ADR-workflow-layers.md) · [pack-workflow-rules.md](./pack-workflow-rules.md) · [material-pipeline.md](./material-pipeline.md)

Ziel: **Journey-UI** nutzt dieselbe Regel-Matrix wie Legacy (`packWorkflowRules.ts` + `packListCtx`) — zwei Flows (Quick/External vs. Logistics), Activity-Status ≠ Material-Pipeline.

---

## Zielbild (kurz)

| Ebene | Speicher |
|-------|----------|
| Planung (`draft` … `approved`) | `activity_item` |
| Ab `packing` | `activity_pack_item` + `activity_pack_container_item` (`quantity_*`) |
| Activity-Status | `activity.status` (Stepper) |

**Kisten:** dieselbe `activity_pack_container` von Packen → Ausgabe → Retour → Einlagern.  
**Anzeige:** voll in Kiste → nur Kiste; teilweise → lose = gesamt − in Kisten.

**Kistencheck-Beine:** `outbound` · `return` · `warehouse_store` (Einlagern = wie retourniert, nicht wie gepackt).

---

## Quick / External

```
packing → packed → [Ausgabe] → at_event → [Retour] → returned → storing → completed
```

- `at_event`: auto wenn alles ausgegeben **oder** «Habe nur das mitgenommen»
- Retour-Status-Button erst **nach** `at_event` + Retour-Arbeit fertig
- Aktiver Checkpoint ab `at_event`: Retour (`resolveEffectiveActiveJourneyStep`)

## Logistics (camp / event)

```
packing → packed → transport_out → at_event → transport_back → returned → storing → completed
```

- `at_event` explizit; **kein** Quick-Auto-Advance
- Bei `at_event`: aktiver Stepper = `issue` (nicht Retour)

---

## Erledigt

- [x] Badge aus DB, nicht URL `packStep`
- [x] Redirect bei Zukunfts-`packStep`
- [x] Retour bearbeitbar ab `at_event` (`resolveEffectiveActiveJourneyStep` in Tasks)
- [x] Verbrauch-Buchhaltung erst ab Retour

---

## Phase A — Journey an zentrale Regeln (Prio 1)

- [x] **A1** Verbrauchs-Logik in `materialJourneyPackContextState` (keine Stubs)
- [x] **A2** `shouldShowContainerOnStageLeft` für `at_event_returned` + Einlagern
- [x] **A3** Journey-Tasks: Kisten auf Retour/Einlagern (`shouldShowPackContainerInJourneyList`)
- [x] **A4** Doppelzeilen: lose Zeile ausblenden wenn alles in Kiste (via `shouldIncludePackItemOnStageLeft` + voller `packListCtx`)
- [x] **A5** `containerReturnedAsWhole` + Store auf `quantity_returned`

## Phase B — Quick Status-UX (Prio 2)

- [x] **B1** Reihenfolge `packed` → `at_event` → Retour → `returned` sichtbar
- [x] **B2** «Retour bringen» = Navigation; Status-Button nur auf Retour
- [x] **B3** «Habe nur das mitgenommen» in Journey
- [x] **B4** Zähler-Hinweis (Kisten / Verbrauch / lose)

## Phase C — Logistics Regression (Prio 2)

- [x] **C1** `at_event` / Transport-Transitions
- [x] **C2** Kein Quick-Sprung auf Retour bei Logistics
- [x] **C3** Kistencheck `return` auf Transport-Stufen

## Phase D — Abnahme

- [x] **D1** Rakokiste + Fackeln Verbrauch + lose Artikel — [`journeyPackWorkflowAbnahme.spec.ts`](../frontend/src/utils/journeyPackWorkflowAbnahme.spec.ts)
- [x] **D2** Teilmenge in Kiste (7+3) — spec
- [x] **D3** Quick Teilausgabe → `at_event` — spec
- [x] **D4** Camp/Event Transport-Kette — spec
- [x] **D5** Verbrauch ohne MW-Auftrag vor Retour — spec + PHPUnit

Details: [journey-pack-workflow-abnahme.md](./journey-pack-workflow-abnahme.md)

---

## Implementierungsreihenfolge

```
A2 → A1 → A3 → A4 → A5 → B* → C* → D*
```

## Nicht mischen

- Activity-Status ≠ `quantity_*`
- Quick-Handoff ≠ Logistics-Transport
- Retour-Check ≠ Outbound ≠ Einlagern
- `activity_item` nur Planung; ab `packing` nur `pack_item` / `container_item`
