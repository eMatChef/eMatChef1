# Pack-Step-UI (zentrale Schicht)

Zentrale Bausteine für die **Dual-Panel-Packliste** in `ActivityPackListTab.vue`: linkes/rechtes Panel, Kisten-Abschnitte, Spiegel-Ansicht («Bereits …») und gemeinsame Confirm-Logik beim Statuswechsel.

**Design-Vorlage:** Stufe **Gepackt → Am Event** — alle weiteren Material-Schritte (Retour, später Ausgepackt) sollen dasselbe Layout und dieselben Wrapper nutzen.

**Stand:** Mai 2026

---

## Problem / Ziel

Die Packliste hatte pro Stufe duplizierte Template-Blöcke (Kisten links/rechts, «Bereits ans Event», Retour-Modals). Fehler (z. B. kaputtes Markup, fehlende Computeds) entstanden, weil Retour separat gebaut wurde statt den funktionierenden Ausgabe-Schritt zu spiegeln.

**Lösung:** Presets + schlanke Wrapper-Komponenten. Die eigentliche Kisten-Logik bleibt in den bestehenden Karten (`PackWarehouseIssueContainerCard`, `PackEventReturnContainerCard`); die Schicht darüber steuert nur Layout, i18n-Keys und Card-Modus.

---

## Architektur (Kurz)

```
ActivityPackListTab.vue
├── packStepUi.ts              Presets (Titel, Hint, Aria, cardMode)
├── PackStepCrateSection.vue   Linkes Kisten-Panel
├── PackStepMirrorSection.vue  Rechtes «Bereits …»-Panel
├── PackStepContainerCard.vue  Router → Issue- oder Return-Karte
└── usePackWorkflowConfirm.ts  Confirm vor at_event / returned
```

**Inject-Kontext:** Kisten-Karten lesen weiterhin `PACK_WAREHOUSE_ISSUE_INJECT_KEY` aus `ActivityPackListTab` — die zentrale Schicht ersetzt das nicht.

---

## Dateien

| Baustein | Pfad | Rolle |
|----------|------|--------|
| **packStepUi** | `frontend/src/components/activities/packStepUi.ts` | Presets, Card-Modi, Confirm-Config |
| **PackStepCrateSection** | `…/PackStepCrateSection.vue` | Abschnitt «Kisten» links (Titel, optional Hint, Aria, Slot für Karten) |
| **PackStepMirrorSection** | `…/PackStepMirrorSection.vue` | Abschnitt «Bereits …» rechts (Slots `#crates`, `#loose`) |
| **PackStepContainerCard** | `…/PackStepContainerCard.vue` | Wählt Issue- oder Return-Karte nach `mode` |
| **usePackWorkflowConfirm** | `…/usePackWorkflowConfirm.ts` | `confirmWorkflowStatusTransition()` für Workflow-Buttons |

Verwandt (nicht Teil der Schicht, aber von Karten genutzt):

| Baustein | Pfad |
|----------|------|
| Stufen-Quantities | `packStageQuantities.ts` |
| Issue-Kiste (Referenz-Design) | `PackWarehouseIssueContainerCard.vue` |
| Retour-Kiste | `PackEventReturnContainerCard.vue` |
| Gepackt-Ziel-Kiste | `PackConfirmedPackedContainerCard.vue` |
| Inject-Key | `packWarehouseIssueInjectKey.ts` |

---

## Presets (`packStepUi.ts`)

### Linkes Kisten-Panel

| Preset-Konstante | Pack-Stufe | Panel |
|------------------|------------|--------|
| `PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT` | Gepackt → Am Event | Links: Lager-Kisten zur Ausgabe |
| `PACK_CRATE_SECTION_RETURN_AT_EVENT_LEFT` | Am Event → Retour | Links: Kisten mit offenem Retour-Bestand |

### Rechtes Kisten-Panel

| Preset-Konstante | Pack-Stufe | Panel |
|------------------|------------|--------|
| `PACK_CRATE_SECTION_CONFIRMED_PACKED_RIGHT` | Bestätigt → Gepackt | Rechts: Kisten als Einpack-Ziel |

### Rechtes Spiegel- / Lose-Panel

| Preset-Konstante | Pack-Stufe | Panel |
|------------------|------------|--------|
| `PACK_MIRROR_SECTION_CONFIRMED_PACKED_LOOSE` | Bestätigt → Gepackt | Rechts: lose gepackte Mengen |
| `PACK_MIRROR_SECTION_FORWARD_AT_EVENT` | Gepackt → Am Event | Rechts: «Bereits ans Event» + lose am Event |
| `PACK_MIRROR_SECTION_RETURN_DONE` | Am Event → Retour | Rechts: «Bereits retourniert» + lose Retour |

Hilfsfunktionen: `packCrateSectionPresetForLeft(stage)`, `packCrateSectionPresetForRight(stage)`, `packMirrorSectionPresetForRight(stage)`.

### Karten-Modi (`PackContainerCardMode`)

| Modus | Karte | Verwendung |
|-------|-------|------------|
| `confirmed_packed_target` | `PackConfirmedPackedContainerCard` | Rechts, Einpack-Ziel (Bestätigt → Gepackt) |
| `warehouse_issue` | `PackWarehouseIssueContainerCard` | Links, Ausgabe ans Event |
| `warehouse_issue_mirror` | dieselbe, mit `container-dom-id-prefix` | Rechts, «Bereits ans Event» |
| `at_event_return` | `PackEventReturnContainerCard` | Links, Retour |
| `at_event_return_mirror` | dieselbe | Rechts, «Bereits retourniert» |

---

## Panel-Logik (Bestätigt → Gepackt)

| Seite | Inhalt |
|-------|--------|
| **Links** | Noch zu packende Positionen (lose, aus Lager) |
| **Rechts — Kisten** | `packContainersForConfirmedPackedRight` — wählbare Einpack-Kisten |
| **Rechts — Lose** | Gepackte lose Mengen (`ohneBehaelterGroups`, `loosePackItemsPartial`) |

Hint «Kiste wählen …» nur bei `showPackOperateControls` (`showHint` an `PackStepCrateSection`).

---

## Panel-Logik (Am Event → Retour)

Spiegelung von **Gepackt → Am Event**:

| Seite | Inhalt |
|-------|--------|
| **Links** | Lose Positionen am Event + Kisten mit `containerReturnableUnits > 0` |
| **Rechts** | «Bereits retourniert»: Kisten **als Ganzes** (Behälter + Packinhalt retourniert, Phys.-Kombi: Behälter retourniert) als Kistenkarte; nur Behälter lose oder nur Inhalt ohne Ganzes → **Lose**-Spalte |

Computeds in `ActivityPackListTab.vue`: `packContainersAtEventForReturnLeft`, `packContainersReturnedForReturnRight`.

---

## Verwendung im Template (Beispiel)

```vue
<script setup lang="ts">
import PackStepCrateSection from '@/components/activities/PackStepCrateSection.vue'
import PackStepMirrorSection from '@/components/activities/PackStepMirrorSection.vue'
import PackStepContainerCard from '@/components/activities/PackStepContainerCard.vue'
import {
  PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT,
  PACK_MIRROR_SECTION_FORWARD_AT_EVENT,
} from '@/components/activities/packStepUi'
</script>

<template>
  <PackStepCrateSection
    v-if="showLeftCrates"
    :preset="PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT"
    :show-empty-hint="packContainers.length === 0"
  >
    <PackStepContainerCard
      v-for="c in warehouseCrates"
      :key="c.id"
      :container="c"
      mode="warehouse_issue"
      :stage-right-label="rightLabel"
    />
  </PackStepCrateSection>

  <PackStepMirrorSection
    v-if="showAtEventMirror"
    :preset="PACK_MIRROR_SECTION_FORWARD_AT_EVENT"
  >
    <template #crates><!-- Kisten mit mode warehouse_issue_mirror --></template>
    <template #loose><!-- lose Gruppen --></template>
  </PackStepMirrorSection>
</template>
```

---

## Workflow-Confirm

Status-Button in der Kopfzeile (`ActivityDetailView`) ruft `confirmBeforeWorkflowTransition` in der Packliste auf. Für `at_event` und `returned` läuft das über:

```ts
import { confirmWorkflowStatusTransition } from '@/components/activities/usePackWorkflowConfirm'

await confirmWorkflowStatusTransition({
  kind: 'at_event', // oder 'returned'
  stageProgress: stageProgress.value,
  getPendingMessage: (variant) => stageProgressPendingConfirmMessage(variant),
  hasMinimum: () => hasAnythingIssuedAtEvent.value,
  confirmMwHandoff: confirmMwHandoffWorkflowToEvent,
  t,
  confirmDialog,
  toast,
})
```

i18n-Keys pro `kind` stehen in `WORKFLOW_STATUS_CONFIRM_CONFIG` (`packStepUi.ts`).

---

## Neue Pack-Stufe anbinden

1. **Preset** in `packStepUi.ts` anlegen (linkes Kisten-Panel und/oder rechtes Spiegel-Panel).
2. **Card-Modus** ergänzen, falls eine neue Karten-Variante nötig ist — bevorzugt bestehende Karte erweitern statt neue Duplikate.
3. **Computeds** in `ActivityPackListTab.vue` für Container-Listen der Stufe.
4. **Template:** `PackStepCrateSection` / `PackStepMirrorSection` statt copy-paste `<motion.div class="pack-workflow-section">`.
5. **i18n:** Titel, Hint und Aria-Label in `de.json` / `en.json`.
6. **Dokumentation:** Eintrag in [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) und ggf. diese Datei.

**Offen:** Stufe **Retour → Ausgepackt** (`returned_unpack`) — gleiches Muster wie oben.

---

## Retour-Kistenmodal (`PackReturnCrateModal.vue`)

Beim Retour-Check einer Kiste (aus `ActivityPackListTab`) gilt getrennte UI pro Zeilentyp:

| Zeilentyp | UI | Buchung |
|-----------|-----|---------|
| **Verbrauchsmaterial** | Materialzeile, Hinweis «Verbrauch offen» / «Verbrauch erfasst», Button «Verbrauch buchen» — **keine** Checkbox, **kein** Retour-+/− | `ActivityConsumptionModal` via `emitConsumptionForMaterialId`; nach Erfolg `reloadToken` → `syncReturnCrateModalLines()` (Modal bleibt offen) |
| **Übriges Material** | Checkbox, Retour-Menge (+/−), bei Differenz Verlust/Reparatur (Wizard mit Vorfüll-Menge) | Retour über Primary «Retour buchen» |

**Submit-Regel:** «Retour buchen» ist deaktiviert, solange Verbrauchszeilen mit offenem Verbrauch (`consumptionOpen > 0`) existieren. Nicht-Verbrauch und Shell-Retour unverändert über `onReturnCrateModalSubmit` / `continueReturnCrateBatch`.

State-Hilfen in `ActivityPackListTab.vue`: `returnCrateConsumableState`, `buildReturnCrateModalLines`, `syncReturnCrateModalLines`, `returnCrateModalSubmitDisabled`. Kiste nur mit Verbrauch: «Retour buchen» schliesst die Prüfung ohne physische Retour-Schritte (`toastReturnCrateCheckComplete`).

**Gruppe nach «Retour gemeldet»:** Packliste bleibt sichtbar (Ansicht), Steuerung aus (`canUserEditPackList` / `memberReturnHandoffComplete`), dezentes Grau (`pack-workflow--readonly`). MW/DC bearbeiten weiter (Einlagern).

**Retour → Ausgepackt (MW):** Spiegelung der anderen Stufen — links alles mit offenem Einlagern (`returned - stored`), rechts nur «Bereits ausgepackt» (`PackStepMirrorSection` + `PACK_MIRROR_SECTION_UNPACK_STORED`). Kisten links via `PackStepCrateSection` + `PackUnpackWarehouseContainerCard` (`variant=pending`); rechts `variant=stored`. Mengenabweichung Retour vs. ausgegeben: Hinweis in `PackMaterialRowDetail` / Kistenzeile.

---

## Verwandte Docs

| Thema | Datei |
|--------|--------|
| Material-Pipeline / Pack-Stufen | [material-pipeline.md](./material-pipeline.md) |
| Aktivitäts-Status | [status.md](./status.md) |
| Wiederverwendbare Bausteine | [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) |
