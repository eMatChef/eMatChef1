import type { PackStage } from '@/components/activities/packStageQuantities'
import {
  autoPackStageForStatus,
  packStageKeysForProfile,
} from '@/components/activities/packStageQuantities'

export type PackWorkflowProfile = 'quick' | 'external' | 'logistics'

/** activity = schnell; external = 3 Tabs; camp/event = volle Pipeline */
export function packWorkflowProfileForActivityType(activityType: string): PackWorkflowProfile {
  if (activityType === 'activity') return 'quick'
  if (activityType === 'external') return 'external'
  return 'logistics'
}

export { packStageKeysForProfile }

export function showPackContainersForProfile(profile: PackWorkflowProfile, stage: PackStage): boolean {
  if (profile === 'quick') return false
  if (profile === 'external') {
    return stage === 'confirmed_packed' || stage === 'packed_at_event'
  }
  return stage === 'confirmed_packed' || stage === 'packed_transport_to'
}

export function autoPackStageForProfile(profile: PackWorkflowProfile, status: string): PackStage {
  return autoPackStageForStatus(status, profile)
}
