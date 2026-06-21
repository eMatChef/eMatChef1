import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PublicLookupBatchResponse } from '@/api/public/publicLookup'
import {
  isPhysicalComboPackItem,
  isVirtualComboPackItem,
  packMaterialDisplayName,
} from '@/components/activities/packMaterialDisplay'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyForwardChecklistStep } from '@/components/activities/materialJourneySteps'
import { isCrateShellPackItem } from '@/components/activities/packShellCrateHelpers'
import type { PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { shouldIncludePackItemOnStageLeft } from '@/components/activities/packWorkflowRules'
import {
  packItemMatchesStorageLookup,
  type StorageLookupResult,
} from '@/utils/packStorageLocationMatch'

export type MaterialScanResultType =
  | 'not_on_list'
  | 'unknown_crate'
  | 'not_ready'
  | 'wrong_batch'
  | 'serialized_mismatch'
  | 'bulk_wrong_batch'
  | 'in_repair'
  | 'crate_shell'
  | 'in_crate'
  | 'loose_ready'
  | 'combo_check'
  | 'already_done'
  | 'in_virtual_crate'
  | 'text_match'
  | 'js_material'
  | 'shelf_location'
  | 'unknown'

export type MaterialScanTone = 'success' | 'error' | 'warning' | 'info' | 'muted'

export type MaterialScanShelfLine = {
  packItem: ActivityPackItem
  moveQty: number
  doneQty: number
  totalQty: number
}

export type MaterialScanResolveResult = {
  type: MaterialScanResultType
  tone: MaterialScanTone
  title: string
  detail?: string
  materialName?: string
  packItem?: ActivityPackItem
  container?: ActivityPackContainer
  parentCombo?: ActivityPackItem
  canAct: boolean
  needsBulkConfirm?: boolean
  scannedBatchId?: string
  scannedBatchLabel?: string
  shelfLines?: MaterialScanShelfLine[]
  storageLookup?: StorageLookupResult
  shelfOpenCount?: number
  shelfTotalCount?: number
}

export type MaterialScanResolveContext = {
  activityId: string
  journeyStep: JourneyStep
  listCtx: PackWorkflowListContext
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  listEditable: boolean
}

function jsMaterialScanResult(materialName: string): MaterialScanResolveResult {
  return {
    type: 'js_material',
    tone: 'info',
    title: materialName,
    detail: 'js_material',
    materialName,
    canAct: false,
  }
}

function packItemsForMaterial(
  materialId: string,
  packItems: ActivityPackItem[],
): ActivityPackItem[] {
  return packItems.filter((pi) => pi.materialItemId === materialId)
}

function containerForBatchId(
  batchId: string,
  packContainers: ActivityPackContainer[],
): ActivityPackContainer | undefined {
  return packContainers.find((c) => (c.container_batch_id ?? '').trim() === batchId)
}

function containerHoldingMaterial(
  materialId: string,
  ctx: MaterialScanResolveContext,
): { container: ActivityPackContainer; item: ActivityPackContainerItem } | null {
  for (const container of ctx.packContainers) {
    for (const ci of ctx.containerItemsByContainerId[container.id] ?? []) {
      if (ci.material_item_id === materialId && (ci.quantity_packed ?? 0) > 0) {
        return { container, item: ci }
      }
    }
  }
  return null
}

function virtualComboParentForMaterial(
  materialId: string,
  ctx: MaterialScanResolveContext,
): ActivityPackItem | null {
  for (const pi of ctx.packItems) {
    if (!isVirtualComboPackItem(pi)) continue
    const container = ctx.packContainers.find(
      (c) => (c.source_activity_item_id ?? '').trim() === pi.id,
    )
    if (!container) continue
    for (const ci of ctx.containerItemsByContainerId[container.id] ?? []) {
      if (ci.material_item_id === materialId) return pi
    }
  }
  return null
}

function pickPrimaryPackItem(items: ActivityPackItem[], ctx: MaterialScanResolveContext): ActivityPackItem | undefined {
  const open = items.filter((pi) => shouldIncludePackItemOnStageLeft(pi, ctx.listCtx))
  if (open.length > 0) return open[0]
  return items[0]
}

function isBatchInRepair(lookup: PublicLookupBatchResponse): boolean {
  const status = (lookup.batch.status ?? '').trim().toLowerCase()
  return status === 'in_repair' || status === 'repair'
}

function isContainerBatchLookup(lookup: PublicLookupBatchResponse): boolean {
  return Boolean(lookup.batch.is_container || lookup.material.is_container)
}

function scannedBatchDisplayLabel(lookup: PublicLookupBatchResponse, materialName: string): string {
  const serial = (lookup.batch.serial_number ?? '').trim()
  const batchLabel = (lookup.batch.label ?? '').trim()
  const parts = [serial, batchLabel || materialName].filter(Boolean)
  return parts.join(' – ') || materialName
}

function isNotReadyForJourneyStep(
  pi: ActivityPackItem,
  step: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  if (step === 'pack') return false
  if (step === 'transport_out') {
    return (pi.quantityPacked ?? 0) <= 0
  }
  if (step === 'issue') {
    if (profile === 'logistics') {
      return (pi.quantityTransportTo ?? 0) <= 0
    }
    return (pi.quantityPacked ?? 0) <= 0
  }
  if (step === 'transport_back') {
    return (pi.quantityIssued ?? 0) <= 0
  }
  if (pi.quantityOrdered > 0 && (pi.quantityPacked ?? 0) <= 0) {
    return true
  }
  return false
}

function batchMismatch(
  pi: ActivityPackItem,
  batchId: string,
  lookup: PublicLookupBatchResponse,
): 'serialized_mismatch' | 'bulk_wrong_batch' | null {
  const linked = (pi.linkedContainerBatchId ?? '').trim()
  if (!linked || linked === batchId) return null
  const serial = (lookup.batch.serial_number ?? '').trim()
  if (serial) return 'serialized_mismatch'
  return 'bulk_wrong_batch'
}

export function resolveMaterialBatchScan(
  lookup: PublicLookupBatchResponse,
  ctx: MaterialScanResolveContext,
): MaterialScanResolveResult {
  const materialId = lookup.material.id
  const batchId = lookup.batch.id
  const materialName = lookup.material.name

  if (isBatchInRepair(lookup)) {
    return {
      type: 'in_repair',
      tone: 'warning',
      title: materialName,
      detail: 'in_repair',
      materialName,
      canAct: false,
    }
  }

  const shellContainer = containerForBatchId(batchId, ctx.packContainers)
  if (shellContainer) {
    const shellPi =
      ctx.packItems.find(
        (p) =>
          (p.linkedContainerBatchId ?? '').trim() === batchId ||
          p.materialItemId === (shellContainer.container_material_item_id ?? '').trim(),
      ) ??
      ctx.packItems.find((p) =>
        isCrateShellPackItem(p, ctx.packContainers) &&
        ctx.packContainers.some((c) => c.id === shellContainer.id),
      )

    if (shellPi && isPhysicalComboPackItem(shellPi)) {
      const open = shouldIncludePackItemOnStageLeft(shellPi, ctx.listCtx)
      return {
        type: 'combo_check',
        tone: open ? 'warning' : 'muted',
        title: packMaterialDisplayName(shellPi),
        detail: open ? 'combo_open' : 'already_done',
        materialName: packMaterialDisplayName(shellPi),
        packItem: shellPi,
        canAct: open && ctx.listEditable,
      }
    }

    return {
      type: 'crate_shell',
      tone: 'info',
      title: shellContainer.label,
      detail: 'crate_shell',
      materialName,
      container: shellContainer,
      packItem: shellPi,
      canAct: ctx.listEditable,
    }
  }

  const linkedPi = ctx.packItems.find((p) => (p.linkedContainerBatchId ?? '').trim() === batchId)
  if (linkedPi && isPhysicalComboPackItem(linkedPi)) {
    const open = shouldIncludePackItemOnStageLeft(linkedPi, ctx.listCtx)
    return {
      type: 'combo_check',
      tone: open ? 'warning' : 'muted',
      title: packMaterialDisplayName(linkedPi),
      detail: open ? 'combo_open' : 'already_done',
      materialName: packMaterialDisplayName(linkedPi),
      packItem: linkedPi,
      canAct: open && ctx.listEditable,
    }
  }

  const matches = packItemsForMaterial(materialId, ctx.packItems)
  if (matches.length > 0 && matches.every((pi) => pi.isJsMaterial)) {
    return jsMaterialScanResult(materialName)
  }

  if (matches.length === 0) {
    const inCrate = containerHoldingMaterial(materialId, ctx)
    if (inCrate && isJourneyForwardChecklistStep(ctx.journeyStep)) {
      return {
        type: 'in_crate',
        tone: 'info',
        title: inCrate.item.material_name ?? materialName,
        detail: 'in_crate',
        materialName: inCrate.item.material_name ?? materialName,
        container: inCrate.container,
        canAct: ctx.listEditable,
      }
    }
    if (isContainerBatchLookup(lookup) && ctx.journeyStep === 'pack') {
      return {
        type: 'unknown_crate',
        tone: 'info',
        title: materialName,
        detail: 'unknown_crate',
        materialName,
        scannedBatchId: batchId,
        scannedBatchLabel: scannedBatchDisplayLabel(lookup, materialName),
        canAct: ctx.listEditable,
      }
    }
    return {
      type: 'not_on_list',
      tone: 'error',
      title: materialName,
      detail: 'not_on_list',
      materialName,
      canAct: false,
    }
  }

  const virtualParent = virtualComboParentForMaterial(materialId, ctx)
  if (virtualParent) {
    return {
      type: 'in_virtual_crate',
      tone: 'info',
      title: materialName,
      detail: 'in_virtual_crate',
      materialName,
      parentCombo: virtualParent,
      packItem: virtualParent,
      canAct: ctx.listEditable,
    }
  }

  const pi = pickPrimaryPackItem(matches, ctx)!
  const mismatch = batchMismatch(pi, batchId, lookup)

  if (mismatch === 'serialized_mismatch') {
    return {
      type: 'serialized_mismatch',
      tone: 'error',
      title: packMaterialDisplayName(pi),
      detail: 'serialized_mismatch',
      materialName: packMaterialDisplayName(pi),
      packItem: pi,
      canAct: false,
    }
  }

  if (isNotReadyForJourneyStep(pi, ctx.journeyStep, ctx.listCtx.profile)) {
    return {
      type: 'not_ready',
      tone: 'muted',
      title: packMaterialDisplayName(pi),
      detail: 'not_ready',
      materialName: packMaterialDisplayName(pi),
      packItem: pi,
      canAct: false,
    }
  }

  const isOpen = shouldIncludePackItemOnStageLeft(pi, ctx.listCtx)
  const doneQty = ctx.listCtx.getStageRightQty(pi)

  if (!isOpen && doneQty > 0) {
    return {
      type: 'already_done',
      tone: 'success',
      title: packMaterialDisplayName(pi),
      detail: 'already_done',
      materialName: packMaterialDisplayName(pi),
      packItem: pi,
      canAct: false,
    }
  }

  if (isPhysicalComboPackItem(pi)) {
    return {
      type: 'combo_check',
      tone: isOpen ? 'warning' : 'muted',
      title: packMaterialDisplayName(pi),
      detail: isOpen ? 'combo_open' : 'already_done',
      materialName: packMaterialDisplayName(pi),
      packItem: pi,
      canAct: isOpen && ctx.listEditable,
    }
  }

  const inCrate = containerHoldingMaterial(materialId, ctx)
  if (inCrate && isJourneyForwardChecklistStep(ctx.journeyStep) && !isOpen) {
    return {
      type: 'in_crate',
      tone: 'info',
      title: packMaterialDisplayName(pi),
      detail: 'in_crate',
      materialName: packMaterialDisplayName(pi),
      container: inCrate.container,
      packItem: pi,
      canAct: ctx.listEditable,
    }
  }

  if (mismatch === 'bulk_wrong_batch') {
    return {
      type: 'bulk_wrong_batch',
      tone: 'warning',
      title: packMaterialDisplayName(pi),
      detail: 'bulk_wrong_batch',
      materialName: packMaterialDisplayName(pi),
      packItem: pi,
      canAct: isOpen && ctx.listEditable,
      needsBulkConfirm: true,
    }
  }

  if (isOpen) {
    return {
      type: 'loose_ready',
      tone: 'success',
      title: packMaterialDisplayName(pi),
      detail: 'loose_ready',
      materialName: packMaterialDisplayName(pi),
      packItem: pi,
      canAct: ctx.listEditable,
    }
  }

  return {
    type: 'not_on_list',
    tone: 'error',
    title: materialName,
    detail: 'not_on_list',
    materialName,
    canAct: false,
  }
}

function sortShelfLinesByName(lines: MaterialScanShelfLine[]): MaterialScanShelfLine[] {
  return [...lines].sort((a, b) =>
    packMaterialDisplayName(a.packItem).localeCompare(
      packMaterialDisplayName(b.packItem),
      undefined,
      { sensitivity: 'base' },
    ),
  )
}

export function resolveStorageLocationScan(
  lookup: StorageLookupResult,
  ctx: MaterialScanResolveContext,
): MaterialScanResolveResult {
  const matching = ctx.packItems.filter(
    (pi) => !pi.isJsMaterial && packItemMatchesStorageLookup(pi, lookup),
  )

  const openLines: MaterialScanShelfLine[] = []
  const doneLines: MaterialScanShelfLine[] = []
  let shelfOpenCount = 0
  let shelfTotalCount = 0

  for (const pi of matching) {
    const moveQty = Math.max(0, ctx.listCtx.effectiveStageLeftQty(pi))
    const doneQty = Math.max(0, ctx.listCtx.getStageRightQty(pi))
    const totalQty = moveQty + doneQty
    const line: MaterialScanShelfLine = { packItem: pi, moveQty, doneQty, totalQty }
    const onStage = shouldIncludePackItemOnStageLeft(pi, ctx.listCtx) || doneQty > 0

    if (onStage) shelfTotalCount += 1
    if (shouldIncludePackItemOnStageLeft(pi, ctx.listCtx) && moveQty > 0) {
      openLines.push(line)
      shelfOpenCount += 1
    } else if (doneQty > 0) {
      doneLines.push(line)
    }
  }

  const base = {
    type: 'shelf_location' as const,
    title: lookup.label,
    canAct: false,
    storageLookup: lookup,
    shelfOpenCount,
    shelfTotalCount,
  }

  if (matching.length === 0) {
    return {
      ...base,
      tone: 'muted',
      detail: 'shelf_not_on_list',
      shelfLines: [],
    }
  }

  if (openLines.length === 0) {
    return {
      ...base,
      tone: 'success',
      detail: 'shelf_all_done',
      shelfLines: sortShelfLinesByName(doneLines),
    }
  }

  return {
    ...base,
    tone: 'info',
    detail: 'shelf_open',
    shelfLines: sortShelfLinesByName(openLines),
  }
}

export function resolveMaterialTextSearch(
  query: string,
  ctx: MaterialScanResolveContext,
): MaterialScanResolveResult | null {
  const q = query.trim().toLowerCase()
  if (q.length < 2) return null

  const container = ctx.packContainers.find((c) => c.label.toLowerCase().includes(q))
  if (container) {
    return {
      type: 'text_match',
      tone: 'info',
      title: container.label,
      detail: 'text_crate',
      container,
      canAct: ctx.listEditable,
    }
  }

  const pi = ctx.packItems.find(
    (p) =>
      packMaterialDisplayName(p).toLowerCase().includes(q) ||
      (p.categoryName ?? '').toLowerCase().includes(q) ||
      (p.storageRackName ?? '').toLowerCase().includes(q) ||
      (p.storageSlotName ?? '').toLowerCase().includes(q),
  )
  if (!pi) return null

  if (pi.isJsMaterial) {
    return jsMaterialScanResult(packMaterialDisplayName(pi))
  }

  if (isPhysicalComboPackItem(pi)) {
    return {
      type: 'text_match',
      tone: 'info',
      title: packMaterialDisplayName(pi),
      detail: 'text_combo',
      packItem: pi,
      canAct: ctx.listEditable,
    }
  }

  return {
    type: 'text_match',
    tone: 'info',
    title: packMaterialDisplayName(pi),
    detail: 'text_loose',
    packItem: pi,
    canAct: ctx.listEditable,
  }
}

export function toneForScanResult(type: MaterialScanResultType): MaterialScanTone {
  switch (type) {
    case 'loose_ready':
    case 'already_done':
      return type === 'already_done' ? 'success' : 'success'
    case 'not_on_list':
    case 'serialized_mismatch':
      return 'error'
    case 'bulk_wrong_batch':
    case 'combo_check':
    case 'in_repair':
      return 'warning'
    case 'unknown_crate':
    case 'crate_shell':
    case 'in_crate':
    case 'in_virtual_crate':
    case 'text_match':
    case 'js_material':
    case 'shelf_location':
      return 'info'
    case 'not_ready':
      return 'muted'
    default:
      return 'muted'
  }
}
