import { computed, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { gaCanManagePlanung, gaCanManageStruktur, gaIsBereichsleitung } from '@/utils/grossanlassAccess'

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

/** MW/CMW: volle Verwaltung; OK-Leitung: Struktur anlassweit; Bereichsleitung: eigener Zweig. */
export function useGrossanlassRessortScope(groups: Ref<GrossanlassGroup[]>) {
  const authStore = useAuthStore()

  const canFullyManage = computed(() => gaCanManagePlanung(authStore.currentDepartmentRole))
  const canManageStruktur = computed(() => gaCanManageStruktur(authStore.currentDepartmentRole))

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

  function assignedVisibleIds(): Set<string> {
    const userId = authStore.userId
    const ids = new Set<string>()
    if (!userId) return ids
    for (const root of groups.value.filter((g) => g.members?.some((m) => m.user_id === userId))) {
      for (const id of collectBranchIds(root.id, groups.value)) ids.add(id)
    }
    return ids
  }

  function sharedIntoVisibleIds(): Set<string> {
    const mine = assignedVisibleIds()
    const extra = new Set<string>()
    for (const source of groups.value) {
      for (const share of source.shared_with ?? []) {
        if (!share.target_group_id || !mine.has(share.target_group_id)) continue
        for (const id of collectBranchIds(source.id, groups.value)) extra.add(id)
      }
    }
    return extra
  }

  /** Direkte Zuordnung + Nachfahren — für «Mein Ressort», ohne Geschwister-Ressorts. */
  function isInAssignedRessortBranch(group: GrossanlassGroup): boolean {
    if (canManageStruktur.value) return true
    if (assignedVisibleIds().has(group.id)) return true
    return sharedIntoVisibleIds().has(group.id)
  }

  function isSharedIntoGroup(child: GrossanlassGroup, host: GrossanlassGroup): boolean {
    return (child.shared_with ?? []).some((share) => share.target_group_id === host.id)
  }

  function canShareGroup(group?: GrossanlassGroup | null): boolean {
    if (canManageStruktur.value) return true
    if (!group) return false
    return canActOnOwnBranch(group) || isLeaderOfGroupOrAncestor(group)
  }

  function isLeaderOfGroupOrAncestor(group: GrossanlassGroup): boolean {
    let current: GrossanlassGroup | undefined = group
    const seen = new Set<string>()
    while (current) {
      if (isLeaderOfGroup(current)) return true
      if (!current.parent_id || seen.has(current.id)) break
      seen.add(current.id)
      current = groups.value.find((g) => g.id === current!.parent_id)
    }
    return false
  }

  const isBereichsleitung = computed(() => gaIsBereichsleitung(authStore.currentDepartmentRole))

  function canActOnOwnBranch(group: GrossanlassGroup): boolean {
    return isBereichsleitung.value && assignedVisibleIds().has(group.id)
  }

  function canCreateRoot(): boolean {
    return canManageStruktur.value
  }

  function canCreateChild(parent: GrossanlassGroup): boolean {
    if (parent.node_type === 'bauprojekt') return false
    return canManageStruktur.value || canActOnOwnBranch(parent) || isLeaderOfGroupOrAncestor(parent)
  }

  function canEditGroup(group?: GrossanlassGroup | null): boolean {
    if (canManageStruktur.value) return true
    if (!group) return false
    return canActOnOwnBranch(group) || isLeaderOfGroupOrAncestor(group)
  }

  function canDeleteGroup(group?: GrossanlassGroup | null): boolean {
    if (canManageStruktur.value) return true
    if (!group || !group.parent_id) return false
    return canActOnOwnBranch(group) || isLeaderOfGroupOrAncestor(group)
  }

  function canManageMembersForGroup(group: GrossanlassGroup): boolean {
    return canManageStruktur.value || canActOnOwnBranch(group) || isLeaderOfGroup(group)
  }

  const isRessortMemberSomewhere = computed(() =>
    groups.value.some((g) => isMemberInRessortBranch(g) || isLeaderOfGroup(g)),
  )

  const showManagementActions = computed(
    () => canManageStruktur.value || isRessortMemberSomewhere.value,
  )

  return {
    canFullyManage,
    canManageStruktur,
    isLeaderOfGroup,
    isLeaderOfGroupOrAncestor,
    isMemberInRessortBranch,
    isInAssignedRessortBranch,
    isSharedIntoGroup,
    canShareGroup,
    isBereichsleitung,
    canCreateRoot,
    canCreateChild,
    canEditGroup,
    canDeleteGroup,
    canManageMembersForGroup,
    isRessortMemberSomewhere,
    showManagementActions,
  }
}
