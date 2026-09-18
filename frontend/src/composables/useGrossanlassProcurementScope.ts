import { computed, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GrossanlassProcurementLine } from '@/api/grossanlassProcurement'

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

function procurementGroupIds(groups: GrossanlassGroup[], userId: string): Set<string> {
  const visible = new Set<string>()
  for (const group of groups) {
    if (group.members?.some((m) => m.user_id === userId && m.can_procure)) {
      for (const id of collectBranchIds(group.id, groups)) {
        visible.add(id)
      }
    }
  }
  return visible
}

const EDITABLE_QUOTE_STATUSES = ['bedarf', 'offerte_eingeholt', 'budgetiert']

/** MW/DC: volle Beschaffung; Delegierte (`can_procure`): Direkt-Bedarf + Offerten im eigenen Zweig. */
export function useGrossanlassProcurementScope(groups: Ref<GrossanlassGroup[]>) {
  const authStore = useAuthStore()
  const { isUserRole } = useDepartmentMemberRole()

  const canManageProcurement = computed(() => !isUserRole.value)

  const delegateGroupIds = computed(() => {
    const userId = authStore.userId
    if (!userId || canManageProcurement.value) return new Set<string>()
    return procurementGroupIds(groups.value, userId)
  })

  const hasProcurementDelegate = computed(() => {
    if (canManageProcurement.value) return true
    return delegateGroupIds.value.size > 0
  })

  function userCanProcureInGroup(groupId: string): boolean {
    if (canManageProcurement.value) return true
    return delegateGroupIds.value.has(groupId)
  }

  function canEditQuotesForLine(line: GrossanlassProcurementLine): boolean {
    if (!EDITABLE_QUOTE_STATUSES.includes(line.status)) return false
    if (canManageProcurement.value) return true
    if (line.source !== 'direct' || !line.self_organized) return false
    return userCanProcureInGroup(line.group_id)
  }

  function canSelectQuoteForLine(_line: GrossanlassProcurementLine): boolean {
    return canManageProcurement.value
  }

  return {
    canManageProcurement,
    hasProcurementDelegate,
    userCanProcureInGroup,
    canEditQuotesForLine,
    canSelectQuoteForLine,
  }
}
