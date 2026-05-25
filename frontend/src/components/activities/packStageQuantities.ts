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

/** Aktivität: schnell raus / zurück (+ MW: Bestätigt→Gepackt, Retour→Auspacken) */
export type PackStageQuick =
  | PackStageConfirmed
  | 'packed_at_event'
  | 'at_event_returned'
  | 'returned_unpack'

export type PackStage = PackStageLogistics | PackStageExternal | PackStageQuick

export const PACK_STAGE_KEYS_LOGISTICS: PackStageLogistics[] = [
  'confirmed_packed',
  'packed_transport_to',
  'transport_to_at_event',
  'at_event_transport_back',
  'transport_back_returned',
]

/** Gruppe/User Camp/Event: ab «gepackt» alle Transport-Stufen bis Retour (ohne MW-Packen / Einlagern). */
export const PACK_STAGE_KEYS_LOGISTICS_MEMBER: PackStageLogistics[] = [
  'packed_transport_to',
  'transport_to_at_event',
  'at_event_transport_back',
  'transport_back_returned',
]

/** MW Camp/Event: volle Pipeline inkl. Einlagern. */
export const PACK_STAGE_KEYS_LOGISTICS_MW: PackStage[] = [
  ...PACK_STAGE_KEYS_LOGISTICS,
  'returned_unpack',
]

export const PACK_STAGE_KEYS_EXTERNAL: PackStageExternal[] = [
  'confirmed_packed',
  'packed_at_event',
  'at_event_returned',
]

/** Gruppenmitglied / User: nur Material am Event bewegen */
export const PACK_STAGE_KEYS_QUICK_MEMBER: PackStageQuick[] = ['packed_at_event', 'at_event_returned']

/** MW: volle Aktivitäten-Pipeline in der Packliste */
export const PACK_STAGE_KEYS_QUICK_MW: PackStageQuick[] = [
  'confirmed_packed',
  'packed_at_event',
  'at_event_returned',
  'returned_unpack',
]

export function packStageKeysForProfile(profile: PackWorkflowProfile): PackStage[] {
  if (profile === 'quick') return PACK_STAGE_KEYS_QUICK_MEMBER
  /** Extern: gleiche MW-Pipeline wie «Aktivität» (Pack → Event → Retour → Ausgepackt), nur MW bearbeitet. */
  if (profile === 'external') return PACK_STAGE_KEYS_QUICK_MW
  if (profile === 'logistics') return PACK_STAGE_KEYS_LOGISTICS_MEMBER
  return []
}

export function packStageKeysForProfileAndRole(
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
): PackStage[] {
  if (profile === 'quick' && canManageMaterials) {
    return PACK_STAGE_KEYS_QUICK_MW
  }
  if (profile === 'logistics' && canManageMaterials) {
    return PACK_STAGE_KEYS_LOGISTICS_MW
  }
  return packStageKeysForProfile(profile)
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

/**
 * Lagerort / Regal / Fach:
 * - «Bestätigt → Gepackt»: links (Material noch im Lager)
 * - «Retour → Ausgepackt»: links und rechts (Ziel-Lagerort beim Einräumen)
 */
export function showPackStorageLocation(stage: PackStage, side: 'left' | 'right'): boolean {
  if (isPackUnpackStage(stage)) return true
  if (isPackConfirmedStage(stage)) return side === 'left'
  return false
}

export function autoPackStageForStatus(
  status: string,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): PackStage {
  const s = status
  if (s === 'completed') {
    if (profile === 'quick' || profile === 'external') {
      return canManageMaterials ? 'returned_unpack' : 'at_event_returned'
    }
    if (profile === 'logistics') {
      return canManageMaterials ? 'returned_unpack' : 'transport_back_returned'
    }
    return 'transport_back_returned'
  }
  if (profile === 'quick' || profile === 'external') {
    if (s === 'returned') {
      return canManageMaterials ? 'returned_unpack' : 'at_event_returned'
    }
    if (s === 'at_event') return 'at_event_returned'
    if (s === 'packing') {
      return canManageMaterials ? 'confirmed_packed' : 'packed_at_event'
    }
    if (s === 'packed') {
      return 'packed_at_event'
    }
    if (canManageMaterials && (s === 'approved' || s === 'submitted')) {
      return 'confirmed_packed'
    }
    return 'packed_at_event'
  }
  if (s === 'packed') return 'packed_transport_to'
  if (s === 'at_event') return 'at_event_transport_back'
  if (s === 'returned') {
    return canManageMaterials ? 'returned_unpack' : 'transport_back_returned'
  }
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
      if (profile === 'quick') {
        return Math.max(0, item.quantityPacked - item.quantityIssued)
      }
      return item.quantityPacked - item.quantityIssued
    case 'at_event_transport_back':
      return item.quantityIssued - item.quantityTransportBack
    case 'at_event_returned':
    case 'transport_back_returned':
      return item.quantityIssued - item.quantityReturned
    case 'returned_unpack':
      return Math.max(0, item.quantityReturned - item.quantityStored)
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
    case 'returned_unpack':
      return item.quantityStored
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
      return profile === 'quick' ? item.quantityPacked : item.quantityPacked
    case 'at_event_transport_back':
      return item.quantityIssued
    case 'at_event_returned':
    case 'transport_back_returned':
      return item.quantityIssued
    case 'returned_unpack':
      return item.quantityReturned
    default:
      return 0
  }
}

export function isPackUnpackStage(stage: PackStage): boolean {
  return stage === 'returned_unpack'
}

/** Retour erfassen (Gruppe) oder Ausgepackt (MW: wieder ins Lager) */
export function isPackReturnOrUnpackWarehouseStage(stage: PackStage): boolean {
  return isPackReturnStage(stage) || isPackUnpackStage(stage)
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
    case 'returned_unpack':
      return 'stored'
    default:
      return 'packed'
  }
}

export function workflowTargetStatusForStage(
  stage: PackStage,
  activityStatus: string,
): string | null {
  const s = activityStatus
  if (stage === 'confirmed_packed') return 'packed'
  if (stage === 'returned_unpack' && s === 'returned') return 'completed'
  if (isPackForwardToEventStage(stage)) return 'at_event'
  if (isPackReturnStage(stage)) {
    if (s === 'at_event') return 'returned'
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
