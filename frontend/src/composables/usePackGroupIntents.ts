import { computed, ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getPackGroupIntents,
  postPackGroupIntent,
  postResolvePackGroupIntent,
  type ActivityPackGroupIntent,
} from '@/api/activityPackGroupIntents'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { useToast } from '@/composables/useToast'

export function usePackGroupIntents(options: {
  activityId: Ref<string>
  packItems: Ref<ActivityPackItem[]>
  enabled: Ref<boolean>
  reload: () => Promise<void>
}) {
  const { t } = useI18n()
  const toast = useToast()

  const intents = ref<ActivityPackGroupIntent[]>([])
  const selectedPackItemIds = ref<Set<string>>(new Set())
  const grouping = ref(false)

  const openIntents = computed(() => intents.value.filter((i) => !i.resolvedAt))

  const intentMemberCountById = computed(() => {
    const map = new Map<string, number>()
    for (const intent of openIntents.value) {
      map.set(intent.id, intent.memberCount)
    }
    for (const pi of options.packItems.value) {
      if (!pi.intentId) continue
      map.set(pi.intentId, (map.get(pi.intentId) ?? 0) + 1)
    }
    return map
  })

  const selectedCount = computed(() => selectedPackItemIds.value.size)
  const canGroup = computed(() => selectedCount.value >= 2 && !grouping.value)

  function isSelected(packItemId: string): boolean {
    return selectedPackItemIds.value.has(packItemId)
  }

  function toggleSelection(packItemId: string): void {
    const next = new Set(selectedPackItemIds.value)
    if (next.has(packItemId)) next.delete(packItemId)
    else next.add(packItemId)
    selectedPackItemIds.value = next
  }

  function clearSelection(): void {
    selectedPackItemIds.value = new Set()
  }

  async function loadIntents(): Promise<void> {
    if (!options.enabled.value || !options.activityId.value) {
      intents.value = []
      return
    }
    try {
      intents.value = await getPackGroupIntents(options.activityId.value)
    } catch {
      intents.value = []
    }
  }

  async function groupSelected(): Promise<void> {
    if (!options.activityId.value || !canGroup.value) return
    grouping.value = true
    try {
      const created = await postPackGroupIntent(options.activityId.value, {
        pack_item_ids: [...selectedPackItemIds.value],
      })
      intents.value = [created, ...intents.value.filter((i) => i.id !== created.id)]
      clearSelection()
      await options.reload()
      toast.success(t('activities.materialJourney.packGroup.toastCreated'))
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      grouping.value = false
    }
  }

  async function resolveOldestIntentForContainer(container: ActivityPackContainer): Promise<void> {
    if (!options.activityId.value) return
    const oldest = openIntents.value[0]
    if (!oldest) return
    try {
      await postResolvePackGroupIntent(options.activityId.value, oldest.id, {
        container_id: container.id,
      })
      await loadIntents()
      await options.reload()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    }
  }

  function memberCountForIntent(intentId: string | null | undefined): number {
    if (!intentId) return 0
    return intentMemberCountById.value.get(intentId) ?? 0
  }

  return {
    intents,
    openIntents,
    selectedPackItemIds,
    selectedCount,
    canGroup,
    grouping,
    isSelected,
    toggleSelection,
    clearSelection,
    loadIntents,
    groupSelected,
    resolveOldestIntentForContainer,
    memberCountForIntent,
  }
}
