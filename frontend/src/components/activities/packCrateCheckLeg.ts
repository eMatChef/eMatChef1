import type { PackStage } from '@/components/activities/packStageQuantities'
import {
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackUnpackStage,
} from '@/components/activities/packStageQuantities'

/** Kistencheck-Etappe für Historie / «schon geprüft?». */
export type PackCrateCheckLeg = 'outbound' | 'return' | 'warehouse_store'

/** Rückweg: Event→Retour (quick/external) oder Event→Transport zurück / Transport→Retour (logistics). */
export function isPackReturnCrateCheckStage(stage: PackStage): boolean {
  return isPackReturnStage(stage) || stage === 'at_event_transport_back'
}

export function packCrateCheckLegForStage(stage: PackStage): PackCrateCheckLeg | null {
  if (isPackConfirmedStage(stage) || isPackForwardToEventStage(stage)) {
    return 'outbound'
  }
  if (isPackReturnCrateCheckStage(stage)) {
    return 'return'
  }
  if (isPackUnpackStage(stage)) {
    return 'warehouse_store'
  }
  return null
}

export function isPackCrateCheckStage(stage: PackStage): boolean {
  return packCrateCheckLegForStage(stage) != null
}

export function crateCheckSnapshotKey(packItemId: string, leg: PackCrateCheckLeg): string {
  return `${packItemId}:${leg}`
}
