import type { PackStage } from '@/components/activities/packStageQuantities'
import {
  autoPackStageForStatus,
  packStageKeysForProfile,
  packStageKeysForProfileAndRole,
} from '@/components/activities/packStageQuantities'

export type PackWorkflowProfile = 'quick' | 'external' | 'logistics'

/** activity = schnell (ohne Transport-Hin/Rück); external = 3 Tabs; camp/event = volle Pipeline inkl. Transport */
export function packWorkflowProfileForActivityType(activityType: string): PackWorkflowProfile {
  if (activityType === 'activity') return 'quick'
  if (activityType === 'external') return 'external'
  return 'logistics'
}

export { packStageKeysForProfile, packStageKeysForProfileAndRole }

export function showPackContainersForProfile(profile: PackWorkflowProfile, stage: PackStage): boolean {
  if (profile === 'quick') {
    return (
      stage === 'confirmed_packed' ||
      stage === 'packed_at_event' ||
      stage === 'at_event_returned' ||
      stage === 'returned_unpack'
    )
  }
  if (profile === 'external') {
    return (
      stage === 'confirmed_packed' ||
      stage === 'packed_at_event' ||
      stage === 'at_event_returned'
    )
  }
  return (
    stage === 'confirmed_packed' ||
    stage === 'packed_transport_to' ||
    stage === 'transport_to_at_event' ||
    stage === 'at_event_transport_back' ||
    stage === 'transport_back_returned'
  )
}

export function autoPackStageForProfile(
  profile: PackWorkflowProfile,
  status: string,
  canManageMaterials = false,
): PackStage {
  return autoPackStageForStatus(status, profile, canManageMaterials)
}
