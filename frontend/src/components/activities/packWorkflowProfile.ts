import type { PackStage } from '@/components/activities/packStageQuantities'
import {
  autoPackStageForStatus,
  packStageKeysForProfile,
  packStageKeysForProfileAndRole,
} from '@/components/activities/packStageQuantities'

export type PackWorkflowProfile = 'quick' | 'external' | 'logistics'

/** activity = schnell (Gruppe ab «gepackt»); external = gleiche Pipeline, nur MW */
export function packWorkflowProfileForActivityType(activityType: string): PackWorkflowProfile {
  if (activityType === 'activity') return 'quick'
  if (activityType === 'external') return 'external'
  return 'logistics'
}

export { packStageKeysForProfile, packStageKeysForProfileAndRole }

export function showPackContainersForProfile(profile: PackWorkflowProfile, stage: PackStage): boolean {
  if (profile === 'quick' || profile === 'external') {
    return (
      stage === 'confirmed_packed' ||
      stage === 'packed_at_event' ||
      stage === 'at_event_returned' ||
      stage === 'returned_unpack'
    )
  }
  return (
    stage === 'confirmed_packed' ||
    stage === 'packed_transport_to' ||
    stage === 'transport_to_at_event' ||
    stage === 'at_event_transport_back' ||
    stage === 'transport_back_returned' ||
    stage === 'returned_unpack'
  )
}

export function autoPackStageForProfile(
  profile: PackWorkflowProfile,
  status: string,
  canManageMaterials = false,
): PackStage {
  return autoPackStageForStatus(status, profile, canManageMaterials)
}
