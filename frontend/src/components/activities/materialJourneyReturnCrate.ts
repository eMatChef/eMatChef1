import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  consumedQtyForMaterial,
  lossQtyForMaterial,
  repairQtyForMaterial,
} from '@/components/activities/packNotTakenHelpers'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import { isNonActionableContainerLine } from '@/components/activities/packShellCrateHelpers'
import type { PackQuantityContext } from '@/components/activities/packStageQuantityLayer'
import { computeContainerLineRemainingReturn } from '@/components/activities/packStageQuantityLayer'
import {
  packReturnCrateMode,
  packWorkflowRole,
} from '@/components/activities/packWorkflowRules'
import { returnCrateLineMissingQty, returnCrateLineSurplusQty } from '@/utils/materialJourneyReturnCrateLineMeta'

/**
 * Legacy-Packliste: volles Retour-Modal für MW; Gruppe nur bei Verbrauch in der Kiste.
 * Journey nutzt immer {@link MaterialReturnCrateSheet} (☑ pro Inhaltszeile).
 */
export function shouldOpenMaterialJourneyReturnCrateModal(
  containerId: string,
  options: {
    canManageMaterials: boolean
    packItems: ActivityPackItem[]
    containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
    packQuantityCtx: PackQuantityContext
    shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  },
): boolean {
  if (packReturnCrateMode(packWorkflowRole(options.canManageMaterials)) === 'full') {
    return true
  }
  for (const ci of options.containerItemsByContainerId[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    const rem = computeContainerLineRemainingReturn(ci, options.packQuantityCtx, containerId)
    if (rem < 1) continue
    const pi = options.packItems.find((p) => p.materialItemId === ci.material_item_id)
    if (pi?.isConsumable) return true
  }
  const shell = options.shellPackItemForContainer(containerId)
  if (shell?.isConsumable) return true
  return false
}

function issueCountsForMaterial(
  materialItemId: string | null,
  issues: ActivityIssueReportRow[],
): { consumed: number; loss: number; repair: number } {
  if (!materialItemId) return { consumed: 0, loss: 0, repair: 0 }
  return {
    consumed: consumedQtyForMaterial(materialItemId, issues),
    loss: lossQtyForMaterial(materialItemId, issues),
    repair: repairQtyForMaterial(materialItemId, issues),
  }
}

export function buildMaterialJourneyReturnCrateLines(
  container: ActivityPackContainer,
  options: {
    packItems: ActivityPackItem[]
    containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
    packQuantityCtx: PackQuantityContext
    shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
    materialFallbackLabel: string
    issues?: ActivityIssueReportRow[]
  },
): ReturnCrateLineEdit[] {
  const issues = options.issues ?? []
  const containerId = container.id
  const lines: ReturnCrateLineEdit[] = []

  for (const ci of options.containerItemsByContainerId[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    const max = computeContainerLineRemainingReturn(ci, options.packQuantityCtx, containerId)
    const returnedAlready = ci.quantity_returned ?? 0
    if (max < 1 && returnedAlready < 1) continue
    const materialItemId = ci.material_item_id ?? null
    const pi = materialItemId
      ? options.packItems.find((p) => p.materialItemId === materialItemId)
      : undefined
    const isConsumable = Boolean(pi?.isConsumable)
    const isDone = max < 1 && returnedAlready > 0
    const counts = issueCountsForMaterial(materialItemId, issues)
    const ordered = Math.max(
      pi?.quantityOrdered ?? 0,
      ci.quantity_packed ?? 0,
      ci.quantity_issued ?? 0,
    )
    lines.push({
      id: ci.id,
      kind: 'line',
      placement: 'in_crate',
      containerItemId: ci.id,
      materialItemId,
      materialName: ci.material_name ?? pi?.materialName ?? options.materialFallbackLabel,
      expectedQty: Math.max(ci.quantity_packed ?? 0, ci.quantity_issued ?? 0),
      ordered,
      consumed: counts.consumed,
      loss: counts.loss,
      repair: counts.repair,
      max,
      issued: ci.quantity_issued ?? 0,
      returnedAlready,
      included: !isDone,
      qty: isDone ? 0 : max,
      isExtra: false,
      isConsumable,
      consumptionDone: true,
      consumptionOpen: 0,
      isDone,
    })
  }

  const shell = options.shellPackItemForContainer(containerId)
  if (shell) {
    const shellMax = Math.max(0, (shell.quantityIssued ?? 0) - (shell.quantityReturned ?? 0))
    if (shellMax > 0) {
      const isConsumable = Boolean(shell.isConsumable)
      const counts = issueCountsForMaterial(shell.materialItemId, issues)
      lines.push({
        id: 'shell',
        kind: 'shell',
        placement: 'shell',
        materialItemId: shell.materialItemId,
        materialName: shell.materialName,
        expectedQty: 1,
        ordered: Math.max(shell.quantityOrdered ?? 0, shellMax),
        consumed: counts.consumed,
        loss: counts.loss,
        repair: counts.repair,
        max: shellMax,
        issued: shellMax,
        returnedAlready: shell.quantityReturned ?? 0,
        included: true,
        qty: shellMax,
        isExtra: false,
        isConsumable,
        consumptionDone: true,
        consumptionOpen: 0,
        isDone: false,
      })
    }
  }

  return lines
}

export function materialJourneyReturnCrateCanCompleteWithoutMoves(
  lines: ReturnCrateLineEdit[],
): boolean {
  if (lines.length < 1) return false
  const hasUnresolvedVariance = lines.some((line) => {
    if (line.isDone) return false
    return (
      returnCrateLineMissingQty(line.included, line.max, line.qty) > 0 ||
      returnCrateLineSurplusQty(line.included, line.max, line.qty) > 0
    )
  })
  if (hasUnresolvedVariance) return false
  return !lines.some((line) => !line.isDone && line.max > 0)
}

export function materialJourneyReturnCrateSubmitDisabled(lines: ReturnCrateLineEdit[]): boolean {
  const hasUnresolvedVariance = lines.some((line) => {
    if (line.isDone) return false
    return (
      returnCrateLineMissingQty(line.included, line.max, line.qty) > 0 ||
      returnCrateLineSurplusQty(line.included, line.max, line.qty) > 0
    )
  })
  if (hasUnresolvedVariance) return true

  const returnable = lines.filter((line) => !line.isDone && line.max > 0)
  if (returnable.length < 1) return false

  const hasReturnSelection = returnable.some((line) => line.included && line.qty > 0)
  return !hasReturnSelection
}

export type MaterialJourneyReturnCrateBatchStep =
  | { kind: 'shell'; qty: number }
  | { kind: 'loose'; materialItemId?: string; qty: number }
  | { kind: 'line'; containerItemId?: string; qty: number }

/** Inhalt noch offen → Hülle erst nach vollständigem Inhalts-Retour mitbuchen. */
export function materialJourneyReturnCrateContentStillOpen(lines: ReturnCrateLineEdit[]): boolean {
  return lines.some(
    (line) =>
      line.kind === 'line' &&
      !line.isDone &&
      line.max > 0 &&
      (!line.included || line.qty < line.max),
  )
}

export function materialJourneyReturnCrateBatchSteps(
  lines: ReturnCrateLineEdit[],
): MaterialJourneyReturnCrateBatchStep[] {
  const contentStillOpen = materialJourneyReturnCrateContentStillOpen(lines)
  return lines
    .filter((line) => {
      if (line.isDone || !line.included || line.qty <= 0) return false
      if (line.kind === 'shell' && contentStillOpen) return false
      return true
    })
    .map((line): MaterialJourneyReturnCrateBatchStep => {
      if (line.kind === 'shell') {
        return { kind: 'shell', qty: line.qty }
      }
      return { kind: 'line', containerItemId: line.containerItemId, qty: line.qty }
    })
    .sort((a, b) => {
      const rank = (k: MaterialJourneyReturnCrateBatchStep['kind']) => (k === 'shell' ? 2 : k === 'loose' ? 1 : 0)
      return rank(a.kind) - rank(b.kind)
    })
}
