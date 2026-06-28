import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  crateCheckSnapshotKey,
  isPackCrateCheckStage,
  packCrateCheckLegForStage,
  type PackCrateCheckLeg,
} from '@/components/activities/packCrateCheckLeg'
import type { CrateCheckSnapshot } from '@/components/activities/packCrateCheckReality'
import { packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
import type { PackStage } from '@/components/activities/packStageQuantities'

export function isShellCrateCheckEligible(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
): boolean {
  if (pi.materialType === 'physical_combo') return true
  return packShellContainerForPackItem(pi, packContainers) != null
}

export function shellCrateCheckDoneForPackItem(
  pi: ActivityPackItem,
  packStage: PackStage,
  snapshots: Record<string, CrateCheckSnapshot>,
  userId: string | null | undefined,
): boolean {
  const leg: PackCrateCheckLeg | null = packCrateCheckLegForStage(packStage)
  if (!leg || !(userId ?? '').trim()) return false
  return Boolean(snapshots[crateCheckSnapshotKey(pi.id, leg)])
}

/** Wie Packliste: Kistencheck-Modal nur solange für diese Etappe noch nicht erledigt. */
export function needsShellCratePresenceConfirm(
  pi: ActivityPackItem,
  packStage: PackStage,
  packContainers: ActivityPackContainer[],
  snapshots: Record<string, CrateCheckSnapshot>,
  userId: string | null | undefined,
): boolean {
  if (!isPackCrateCheckStage(packStage)) return false
  if (!isShellCrateCheckEligible(pi, packContainers)) return false
  if (shellCrateCheckDoneForPackItem(pi, packStage, snapshots, userId)) return false
  return true
}
