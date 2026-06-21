import { computed, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'

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

/** MW/DC: volle Verwaltung; Ressort-Mitglieder: Bauprojekte + Mitglieder im eigenen Baum. */
export function useGrossanlassRessortScope(groups: Ref<GrossanlassGroup[]>) {
  const authStore = useAuthStore()
  const { isUserRole } = useDepartmentMemberRole()

  const canFullyManage = computed(() => !isUserRole.value)

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

  function canCreateRoot(): boolean {
    return canFullyManage.value
  }

  function canCreateChild(parent: GrossanlassGroup): boolean {
    return canFullyManage.value || isMemberInRessortBranch(parent)
  }

  function canEditGroup(): boolean {
    return canFullyManage.value
  }

  function canDeleteGroup(): boolean {
    return canFullyManage.value
  }

  function canManageMembersForGroup(group: GrossanlassGroup): boolean {
    return canFullyManage.value || isLeaderOfGroup(group) || isMemberInRessortBranch(group)
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
    canCreateRoot,
    canCreateChild,
    canEditGroup,
    canDeleteGroup,
    canManageMembersForGroup,
    isRessortMemberSomewhere,
    showManagementActions,
  }
}
