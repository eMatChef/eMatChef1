import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isNonActionableContainerLine } from '@/components/activities/packShellCrateHelpers'
import {
  consumedQtyForMaterial,
  lossQtyForMaterial,
  notTakenToEventQtyForMaterial,
  packRetourAccountingSnapshot,
  qtyAttributedToIssuedLine,
  repairQtyForMaterial,
  type PackRetourAccounting,
} from '@/components/activities/packNotTakenHelpers'
import { computeLooseQtyForPackItem, type PackQuantityContext } from '@/components/activities/packStageQuantityLayer'

export type MaterialJourneyUnpackAccountingInput = {
  packItems: ActivityPackItem[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  issues: ActivityIssueReportRow[]
  packQuantityCtx: PackQuantityContext
}

function packItemForMaterial(
  materialItemId: string | null | undefined,
  packItems: ActivityPackItem[],
): ActivityPackItem | undefined {
  if (!materialItemId) return undefined
  return packItems.find((p) => p.materialItemId === materialItemId)
}

function loosePackedNeverIssuedQty(pi: ActivityPackItem, ctx: PackQuantityContext): number {
  const loosePacked = computeLooseQtyForPackItem(pi, ctx)
  const issued = pi.quantityIssued ?? 0
  return Math.max(0, loosePacked - issued)
}

export function retourAccountingForUnpackLoose(
  pi: ActivityPackItem,
  input: MaterialJourneyUnpackAccountingInput,
): PackRetourAccounting {
  const returned = pi.quantityReturned ?? 0
  const neverIssuedLoose = loosePackedNeverIssuedQty(pi, input.packQuantityCtx)
  const notTakenFromIssues = notTakenToEventQtyForMaterial(pi.materialItemId, input.issues)
  const consumed = consumedQtyForMaterial(pi.materialItemId, input.issues)
  const loss = lossQtyForMaterial(pi.materialItemId, input.issues)
  const repair = repairQtyForMaterial(pi.materialItemId, input.issues)
  const replenishment = Math.max(0, (pi.quantityOrdered ?? 0) - (pi.quantityPacked ?? 0))
  return packRetourAccountingSnapshot({
    quantityPacked: pi.quantityPacked ?? 0,
    quantityOrdered: pi.quantityOrdered ?? 0,
    quantityIssued: pi.quantityIssued ?? 0,
    returned,
    neverIssuedLoose,
    notTakenFromIssues,
    consumed,
    loss,
    repair,
    replenishment,
  })
}

/**
 * Physisch noch retournierbare Stück einer Kistenzeile:
 * expectedReturn (issued − Verbrauch/Verlust/Reparatur/nicht mitgenommen) − bereits retourniert.
 */
export function containerLinePhysicalReturnRemaining(
  ci: ActivityPackContainerItem,
  input: MaterialJourneyUnpackAccountingInput,
): number {
  const acct = retourAccountingForContainerLine(ci, input)
  return Math.max(0, acct.expectedReturn - acct.returnedBooked)
}

export function retourAccountingForContainerLine(
  ci: ActivityPackContainerItem,
  input: MaterialJourneyUnpackAccountingInput,
): PackRetourAccounting {
  const returned = ci.quantity_returned ?? 0
  const linePacked = ci.quantity_packed ?? 0
  let lineIssued = ci.quantity_issued ?? 0
  const pi = packItemForMaterial(ci.material_item_id, input.packItems)
  if (lineIssued <= 0 && linePacked > 0 && pi && (pi.quantityIssued ?? 0) > 0) {
    const totalPacked = Math.max(0, pi.quantityPacked ?? 0)
    if (totalPacked > 0) {
      lineIssued = Math.min(linePacked, Math.round(((pi.quantityIssued ?? 0) * linePacked) / totalPacked))
    } else {
      lineIssued = Math.min(linePacked, pi.quantityIssued ?? 0)
    }
  }
  const lineNeverIssued = Math.max(0, linePacked - lineIssued)
  const totalIssued = Math.max(lineIssued, pi?.quantityIssued ?? 0)
  const materialId = ci.material_item_id ?? ''
  const totalReplenishment = Math.max(0, (pi?.quantityOrdered ?? linePacked) - (pi?.quantityPacked ?? linePacked))
  const notTakenFromIssues = qtyAttributedToIssuedLine(
    lineIssued,
    totalIssued,
    notTakenToEventQtyForMaterial(materialId, input.issues),
  )
  const totalConsumed = consumedQtyForMaterial(materialId, input.issues)
  const consumed = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalConsumed)
  const totalLoss = lossQtyForMaterial(materialId, input.issues)
  const loss = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalLoss)
  const totalRepair = repairQtyForMaterial(materialId, input.issues)
  const repair = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalRepair)
  const replenishment = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalReplenishment)
  return packRetourAccountingSnapshot({
    quantityPacked: linePacked,
    quantityOrdered: pi?.quantityOrdered ?? linePacked,
    quantityIssued: lineIssued,
    returned,
    neverIssuedLoose: lineNeverIssued,
    notTakenFromIssues,
    consumed,
    loss,
    repair,
    replenishment,
  })
}

/**
 * Einlager-Maximum pro Kistenzeile — spiegelt PackPipelineService::maxStored (Container-Ebene).
 * Retournierte Restmenge plus nie ans Event ausgegebene, in der Kiste gepackte Stücke.
 */
export function containerLineForwardStoreMax(ci: ActivityPackContainerItem): number {
  const packed = ci.quantity_packed ?? 0
  const issued = ci.quantity_issued ?? 0
  const returned = ci.quantity_returned ?? 0
  const stored = ci.quantity_stored ?? 0
  const wet = ci.quantity_wet ?? 0
  const returnedPending = Math.max(0, returned - stored - wet)
  const extraReturned = Math.max(0, returned - issued)
  const neverIssuedOutstanding = Math.max(0, packed - issued - extraReturned)
  return returnedPending + neverIssuedOutstanding
}

/** Einlager-Maximum auf Pack-Position — spiegelt PackPipelineService::maxStored. */
export function packItemForwardStoreMax(pi: ActivityPackItem): number {
  const packed = pi.quantityPacked ?? 0
  const issued = pi.quantityIssued ?? 0
  const returned = pi.quantityReturned ?? 0
  const stored = pi.quantityStored ?? 0
  const wet = pi.quantityWet ?? 0
  const returnedPending = Math.max(0, returned - stored - wet)
  const extraReturned = Math.max(0, returned - issued)
  const neverIssuedOutstanding = Math.max(0, packed - issued - extraReturned)
  return returnedPending + neverIssuedOutstanding
}

function pendingStoreInContainersForwardMax(
  materialItemId: string,
  containerIds: string[],
  input: MaterialJourneyUnpackAccountingInput,
): number {
  let sum = 0
  for (const containerId of containerIds) {
    for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
      if (ci.material_item_id !== materialItemId) continue
      if (isNonActionableContainerLine(ci)) continue
      sum += containerLineForwardStoreMax(ci)
    }
  }
  return sum
}

export function containerLineRemainingStore(
  ci: ActivityPackContainerItem,
  input: MaterialJourneyUnpackAccountingInput,
): number {
  return containerLineForwardStoreMax(ci)
}

export function containerShellPendingStoreQty(
  containerId: string,
  shell: ActivityPackItem | undefined,
  input: MaterialJourneyUnpackAccountingInput,
  packContainers: ActivityPackContainer[],
): number {
  if (!shell) return 0
  if (containerInnerPendingStoreUnits(containerId, input) > 0) return 0

  const container = packContainers.find((c) => c.id === containerId)
  const batchId = (container?.container_batch_id ?? '').trim()
  if (batchId) {
    const shellMid = (container?.container_material_item_id ?? shell.materialItemId).trim()
    const readySiblings = packContainers
      .filter((c) => {
        const cBatch = (c.container_batch_id ?? '').trim()
        if (!cBatch) return false
        const cMid = (c.container_material_item_id ?? '').trim()
        return cMid === shellMid || cMid === shell.materialItemId
      })
      .filter((c) => containerInnerPendingStoreUnits(c.id, input) <= 0)
      .sort((a, b) => a.id.localeCompare(b.id))

    const readyIndex = readySiblings.findIndex((c) => c.id === containerId)
    if (readyIndex < 0) return 0
    const shellsStored = shell.quantityStored ?? 0
    return readyIndex === shellsStored ? 1 : 0
  }

  const acct = retourAccountingForUnpackLoose(shell, input)
  return Math.max(0, acct.retourTotal - (shell.quantityStored ?? 0) - (shell.quantityWet ?? 0))
}

export function pendingStoreInContainersForMaterial(
  materialItemId: string,
  containerIds: string[],
  input: MaterialJourneyUnpackAccountingInput,
): number {
  return pendingStoreInContainersForwardMax(materialItemId, containerIds, input)
}

export function pendingStoreLooseQtyForPackItem(
  pi: ActivityPackItem,
  containerIds: string[],
  input: MaterialJourneyUnpackAccountingInput,
): number {
  let base = packItemForwardStoreMax(pi)
  const consumed = consumedQtyForMaterial(pi.materialItemId, input.issues)
  const loss = lossQtyForMaterial(pi.materialItemId, input.issues)
  const repair = repairQtyForMaterial(pi.materialItemId, input.issues)
  if (consumed > 0 && pi.isConsumable) {
    const consumableCap = Math.max(
      0,
      (pi.quantityOrdered ?? 0) - consumed - (pi.quantityStored ?? 0),
    )
    // Wie PackPipelineService::maxStoredForItem — Verbrauch begrenzt, erhöht nicht.
    base = base <= 0 ? consumableCap : Math.min(base, consumableCap)
  }
  const goneExtra = loss + repair
  if (goneExtra > 0) {
    base = Math.max(0, base - goneExtra)
  }
  if (base <= 0) return 0
  return Math.max(0, base - pendingStoreInContainersForwardMax(pi.materialItemId, containerIds, input))
}

export function containerInnerPendingStoreUnits(
  containerId: string,
  input: MaterialJourneyUnpackAccountingInput,
): number {
  let sum = 0
  for (const ci of input.containerItemsByContainerId[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    sum += containerLineRemainingStore(ci, input)
  }
  return sum
}

export function containerShellOnlyPendingUnpack(
  containerId: string,
  shell: ActivityPackItem | undefined,
  input: MaterialJourneyUnpackAccountingInput,
): boolean {
  return containerInnerPendingStoreUnits(containerId, input) <= 0 && containerShellPendingStoreQty(containerId, shell, input, input.packQuantityCtx.packContainers ?? []) > 0
}
