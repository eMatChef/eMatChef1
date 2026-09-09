import { computed, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { gaCanSeeAnlassOverview } from '@/utils/grossanlassAccess'

function collectBranchIds(rootId: string, groups: GrossanlassGroup[]): Set<string> {
  const ids = new Set<string>()
  const queue = [rootId]
  while (queue.length > 0) {
    const id = queue.shift()!
    if (ids.has(id)) continue
    ids.add(id)
    for (const g of groups) {
      if (g.parent_id === id) {
        queue.push(g.id)
      }
    }
  }
  return ids
}

function findRootRessortId(group: GrossanlassGroup, groups: GrossanlassGroup[]): string {
  let current: GrossanlassGroup | undefined = group
  const seen = new Set<string>()
  while (current?.parent_id) {
    if (seen.has(current.id)) break
    seen.add(current.id)
    current = groups.find((g) => g.id === current!.parent_id)
  }
  return current?.id ?? group.id
}

/** MW/CMW/OK-Leitung: volle Verwaltung; Bereichsleitung: Kinder am eigenen Knoten. */
export function useGrossanlassRessortScope(groups: Ref<GrossanlassGroup[]>) {
  const authStore = useAuthStore()

  const canFullyManage = computed(() => gaCanSeeAnlassOverview(authStore.currentDepartmentRole))

  function isLeaderOfGroup(group: GrossanlassGroup): boolean {
    const userId = authStore.userId
    if (!userId) return false
    return group.members?.some((m) => m.user_id === userId && m.is_leader) ?? false
  }

  function isMemberInRessortBranch(group: GrossanlassGroup): boolean {
    const userId = authStore.userId
    if (!userId) return false
    const rootId = findRootRessortId(group, groups.value)
    const branchIds = collectBranchIds(rootId, groups.value)
    return groups.value.some(
      (g) => branchIds.has(g.id) && g.members.some((m) => m.user_id === userId),
    )
  }

  /** Direkte Zuordnung + Nachfahren — für «Mein Ressort», ohne Geschwister-Ressorts. */
  function isInAssignedRessortBranch(group: GrossanlassGroup): boolean {
    const userId = authStore.userId
    if (!userId) return false
    const assignedRootIds = groups.value
      .filter((g) => g.members?.some((m) => m.user_id === userId))
      .map((g) => g.id)
    for (const rootId of assignedRootIds) {
      if (collectBranchIds(rootId, groups.value).has(group.id)) {
        return true
      }
    }
    return false
  }

  function canCreateRoot(): boolean {
    return canFullyManage.value
  }

  function canCreateChild(parent: GrossanlassGroup): boolean {
    return canFullyManage.value || isLeaderOfGroup(parent)
  }

  function canEditGroup(): boolean {
    return canFullyManage.value
  }

  function canDeleteGroup(): boolean {
    return canFullyManage.value
  }

  function canManageMembersForGroup(group: GrossanlassGroup): boolean {
    return canFullyManage.value || isLeaderOfGroup(group)
  }

  const isRessortMemberSomewhere = computed(() =>
    groups.value.some((g) => isMemberInRessortBranch(g) || isLeaderOfGroup(g)),
  )

  const showManagementActions = computed(
    () => canFullyManage.value || isRessortMemberSomewhere.value,
  )

  return {
    canFullyManage,
    isLeaderOfGroup,
    isMemberInRessortBranch,
    isInAssignedRessortBranch,
    canCreateRoot,
    canCreateChild,
    canEditGroup,
    canDeleteGroup,
    canManageMembersForGroup,
    isRessortMemberSomewhere,
    showManagementActions,
  }
}
