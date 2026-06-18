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
export {
  packWorkflowTabs,
  packWorkflowCanEdit,
  packWorkflowRole,
  packShowContainersUi as showPackContainersForProfile,
} from '@/components/activities/packWorkflowRules'

export function autoPackStageForProfile(
  profile: PackWorkflowProfile,
  status: string,
  canManageMaterials = false,
): PackStage {
  return autoPackStageForStatus(status, profile, canManageMaterials)
}
