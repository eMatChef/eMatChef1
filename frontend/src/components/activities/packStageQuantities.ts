import type { ActivityPackItem, PackMoveStage } from '@/api/activityPackItems'

export type PackStage = 'confirmed_packed' | 'packed_issued' | 'issued_returned'

export const PACK_STAGE_KEYS: PackStage[] = ['confirmed_packed', 'packed_issued', 'issued_returned']

export function autoPackStageForStatus(status: string): PackStage {
  if (status === 'packed') return 'packed_issued'
  if (status === 'issued' || status === 'returned') return 'issued_returned'
  return 'confirmed_packed'
}

export function getStageLeftQty(item: ActivityPackItem, stage: PackStage): number {
  switch (stage) {
    case 'confirmed_packed':
      return item.quantityOrdered - item.quantityPacked
    case 'packed_issued':
      return item.quantityPacked - item.quantityIssued
    case 'issued_returned':
      return item.quantityIssued - item.quantityReturned
    default:
      return 0
  }
}

export function getStageRightQty(item: ActivityPackItem, stage: PackStage): number {
  switch (stage) {
    case 'confirmed_packed':
      return item.quantityPacked
    case 'packed_issued':
      return item.quantityIssued
    case 'issued_returned':
      return item.quantityReturned
    default:
      return 0
  }
}

export function getStageTotalQty(item: ActivityPackItem, stage: PackStage): number {
  switch (stage) {
    case 'confirmed_packed':
      return item.quantityOrdered
    case 'packed_issued':
      return item.quantityPacked
    case 'issued_returned':
      return item.quantityIssued
    default:
      return 0
  }
}

export function getBackendStage(stage: PackStage): PackMoveStage {
  switch (stage) {
    case 'confirmed_packed':
      return 'packed'
    case 'packed_issued':
      return 'issued'
    case 'issued_returned':
      return 'returned'
    default:
      return 'packed'
  }
}

export function workflowTargetStatusForStage(
  stage: PackStage,
  activityStatus: string,
): string | null {
  if (stage === 'confirmed_packed') return 'packed'
  if (stage === 'packed_issued') return 'issued'
  if (stage === 'issued_returned') {
    if (activityStatus === 'issued') return 'returned'
    if (activityStatus === 'returned') return 'completed'
  }
  return null
}

export function groupActivityPackItemsByCategory(
  items: ActivityPackItem[],
  otherCategoryLabel: string,
): { categoryName: string; items: ActivityPackItem[] }[] {
  const groups: Record<string, ActivityPackItem[]> = {}
  for (const item of items) {
    const cat = item.categoryName || otherCategoryLabel
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(item)
  }
  return Object.entries(groups).map(([name, groupItems]) => ({
    categoryName: name,
    items: groupItems,
  }))
}
