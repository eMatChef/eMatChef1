import type { ActivityPackItem, PackMoveStage } from '@/api/activityPackItems'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'

/** Bestätigt → Gepackt */
export type PackStageConfirmed = 'confirmed_packed'

/** Volle Lagerlogistik (Camp/Event) */
export type PackStageLogistics =
  | PackStageConfirmed
  | 'packed_transport_to'
  | 'transport_to_at_event'
  | 'at_event_transport_back'
  | 'transport_back_returned'

/** Extern: Pack → Event → Retour (Transport serverseitig) */
export type PackStageExternal =
  | PackStageConfirmed
  | 'packed_at_event'
  | 'at_event_returned'

/** Aktivität: schnell raus / zurück */
export type PackStageQuick = 'packed_at_event' | 'at_event_returned'

export type PackStage = PackStageLogistics | PackStageExternal | PackStageQuick

export const PACK_STAGE_KEYS_LOGISTICS: PackStageLogistics[] = [
  'confirmed_packed',
  'packed_transport_to',
  'transport_to_at_event',
  'at_event_transport_back',
  'transport_back_returned',
]

export const PACK_STAGE_KEYS_EXTERNAL: PackStageExternal[] = [
  'confirmed_packed',
  'packed_at_event',
  'at_event_returned',
]

export const PACK_STAGE_KEYS_QUICK: PackStageQuick[] = ['packed_at_event', 'at_event_returned']

export function packStageKeysForProfile(profile: PackWorkflowProfile): PackStage[] {
  if (profile === 'quick') return PACK_STAGE_KEYS_QUICK
  if (profile === 'external') return PACK_STAGE_KEYS_EXTERNAL
  return PACK_STAGE_KEYS_LOGISTICS
}

export function isPackConfirmedStage(stage: PackStage): boolean {
  return stage === 'confirmed_packed'
}

/** Material Richtung Event (inkl. Transport-Stufen) */
export function isPackForwardToEventStage(stage: PackStage): boolean {
  return (
    stage === 'packed_at_event' ||
    stage === 'packed_transport_to' ||
    stage === 'transport_to_at_event'
  )
}

/** Retour-Stufe (Event → Lager) */
export function isPackReturnStage(stage: PackStage): boolean {
  return stage === 'at_event_returned' || stage === 'transport_back_returned'
}

export function isPackCrateCheckStage(stage: PackStage): boolean {
  return isPackConfirmedStage(stage) || isPackForwardToEventStage(stage)
}

export function autoPackStageForStatus(status: string, profile: PackWorkflowProfile): PackStage {
  const s = status === 'issued' ? 'at_event' : status
  if (profile === 'quick') {
    if (s === 'at_event' || s === 'returned') return 'at_event_returned'
    return 'packed_at_event'
  }
  if (profile === 'external') {
    if (s === 'packed') return 'packed_at_event'
    if (s === 'at_event' || s === 'returned') return 'at_event_returned'
    return 'confirmed_packed'
  }
  if (s === 'packed') return 'packed_transport_to'
  if (s === 'at_event') return 'at_event_transport_back'
  if (s === 'returned') return 'transport_back_returned'
  return 'confirmed_packed'
}

export function getStageLeftQty(
  item: ActivityPackItem,
  stage: PackStage,
  profile: PackWorkflowProfile,
): number {
  switch (stage) {
    case 'confirmed_packed':
      return item.quantityOrdered - item.quantityPacked
    case 'packed_transport_to':
      return item.quantityPacked - item.quantityTransportTo
    case 'transport_to_at_event':
      return item.quantityTransportTo - item.quantityIssued
    case 'packed_at_event':
      if (profile === 'quick') return item.quantityOrdered - item.quantityIssued
      return item.quantityPacked - item.quantityIssued
    case 'at_event_transport_back':
      return item.quantityIssued - item.quantityTransportBack
    case 'at_event_returned':
    case 'transport_back_returned':
      return item.quantityIssued - item.quantityReturned
    default:
      return 0
  }
}

export function getStageRightQty(
  item: ActivityPackItem,
  stage: PackStage,
  profile: PackWorkflowProfile,
): number {
  switch (stage) {
    case 'confirmed_packed':
      return item.quantityPacked
    case 'packed_transport_to':
      return item.quantityTransportTo
    case 'transport_to_at_event':
      return item.quantityIssued
    case 'packed_at_event':
      return item.quantityIssued
    case 'at_event_transport_back':
      return item.quantityTransportBack
    case 'at_event_returned':
    case 'transport_back_returned':
      return item.quantityReturned
    default:
      return 0
  }
}

export function getStageTotalQty(item: ActivityPackItem, stage: PackStage, profile: PackWorkflowProfile): number {
  switch (stage) {
    case 'confirmed_packed':
      return item.quantityOrdered
    case 'packed_transport_to':
      return item.quantityPacked
    case 'transport_to_at_event':
      return item.quantityTransportTo
    case 'packed_at_event':
      return profile === 'quick' ? item.quantityOrdered : item.quantityPacked
    case 'at_event_transport_back':
      return item.quantityIssued
    case 'at_event_returned':
    case 'transport_back_returned':
      return item.quantityIssued
    default:
      return 0
  }
}

export function getBackendStage(stage: PackStage): PackMoveStage {
  switch (stage) {
    case 'confirmed_packed':
      return 'packed'
    case 'packed_transport_to':
      return 'transport_to'
    case 'transport_to_at_event':
    case 'packed_at_event':
      return 'at_event'
    case 'at_event_transport_back':
      return 'transport_back'
    case 'at_event_returned':
    case 'transport_back_returned':
      return 'returned'
    default:
      return 'packed'
  }
}

export function workflowTargetStatusForStage(
  stage: PackStage,
  activityStatus: string,
): string | null {
  const s = activityStatus === 'issued' ? 'at_event' : activityStatus
  if (stage === 'confirmed_packed') return 'packed'
  if (isPackForwardToEventStage(stage)) return 'at_event'
  if (isPackReturnStage(stage)) {
    if (s === 'at_event') return 'returned'
    if (s === 'returned') return 'completed'
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
