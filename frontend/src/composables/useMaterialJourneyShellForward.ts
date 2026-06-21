import { computed, ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityHistory } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import {
  issueAllPackContainerItems,
  returnAllPackContainerItems,
} from '@/api/activityContainers'
import {
  getPackCrateCheckLooseStock,
  postPackCrateCheck,
  type PackCrateCheckRequest,
} from '@/api/activityPackCrateCheck'
import { postMovePackItem, type ActivityPackItem } from '@/api/activityPackItems'
import { getComboComponents } from '@/api/materials'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  crateCheckSnapshotKey,
  packCrateCheckLegForStage,
} from '@/components/activities/packCrateCheckLeg'
import {
  buildGroupPrefillLineReviewsFromSnapshot,
  formatGroupCrateCheckPrefillHint,
} from '@/components/activities/packCrateCheckPrefill'
import {
  indexLatestCrateCheckByPackItemAndLeg,
  type CrateCheckSnapshot,
} from '@/components/activities/packCrateCheckReality'
import {
  defaultLineReview,
  shellForwardExpectedQty,
  shellForwardLineKey,
  type ShellForwardCheckLine,
  type ShellForwardLineReview,
} from '@/components/activities/packCrateForwardCheck'
import { isJourneyReturnStep, isJourneyStoreStep, type JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  crateShellForwardPeekSections,
  packShellContainerForPackItem,
} from '@/components/activities/packShellCrateHelpers'
import { getBackendStage, isPackConfirmedStage, type PackStage } from '@/components/activities/packStageQuantities'
import {
  materialJourneyPeekSectionTitles,
} from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { emptyMaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import {
  packCrateCheckRequestLightweight,
  packWorkflowRole,
} from '@/components/activities/packWorkflowRules'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

export type ShellForwardPendingAction =
  | { kind: 'pack_move' }
  | { kind: 'issue_container'; containerId: string }
  | { kind: 'return_container'; containerId: string }
  | { kind: 'check_only' }

function shellCheckLinesFromSections(sections: PackCrateShellPeekSection[]): ShellForwardCheckLine[] {
  const out: ShellForwardCheckLine[] = []
  for (const sec of sections) {
    const isExtra = sec.subsectionKey === 'extra'
    for (const line of sec.lines) {
      out.push({
        key: shellForwardLineKey(sec.subsectionKey, line.id),
        subsectionKey: sec.subsectionKey,
        materialName: line.materialName,
        quantity: shellForwardExpectedQty(isExtra, line.quantity),
        materialItemId: (line.materialItemId ?? '').trim() || null,
        isExtra,
        serialHint: (line.serialHint ?? '').trim() || null,
      })
    }
  }
  return out
}

export function useMaterialJourneyShellForward(options: {
  activityId: Ref<string>
  departmentId: Ref<string>
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  cratePeekMaps: Ref<MaterialJourneyCratePeekMaps>
  journeyStep: Ref<JourneyStep>
  packStage: Ref<PackStage>
  canManageMaterials: Ref<boolean>
  applyUpdatedItem: (item: ActivityPackItem) => void
  packMoveQtyCap?: (item: ActivityPackItem) => number
}) {
  const { t } = useI18n()
  const toast = useToast()
  const authStore = useAuthStore()

  const modalOpen = ref(false)
  const packItem = ref<ActivityPackItem | null>(null)
  const moveQty = ref(1)
  const pendingAction = ref<ShellForwardPendingAction>({ kind: 'pack_move' })
  const sections = ref<PackCrateShellPeekSection[]>([])
  const looseStockByMid = ref<Record<string, number>>({})
  const stockLoading = ref(false)
  const historyReplenishByKey = ref<Record<string, boolean>>({})
  const historyPrefillHint = ref<string | null>(null)
  const initialLineReviews = ref<Record<string, ShellForwardLineReview> | null>(null)
  const submitError = ref<string | null>(null)
  const submitting = ref(false)
  const crateCheckSnapshots = ref<Record<string, CrateCheckSnapshot>>({})

  const groupMode = computed(() => !options.canManageMaterials.value)

  const label = computed(() => {
    const pi = packItem.value
    if (!pi) return ''
    const c = packShellContainerForPackItem(pi, options.packContainers.value)
    const crateLabel = (c?.label ?? pi.linkedContainerLabel ?? '').trim()
    return crateLabel && crateLabel !== pi.materialName
      ? `${crateLabel} – ${pi.materialName}`
      : pi.materialName
  })

  const containerBatchId = computed(() => {
    const pi = packItem.value
    if (!pi) return null
    const c = packShellContainerForPackItem(pi, options.packContainers.value)
    return (c?.container_batch_id ?? pi.linkedContainerBatchId ?? null) || null
  })

  const checkOnly = computed(() => pendingAction.value.kind === 'check_only')

  const emptyHint = computed(() => {
    const pi = packItem.value
    if (!pi) return ''
    if (packShellContainerForPackItem(pi, options.packContainers.value)) {
      return t('activities.packList.cratePeekEmptyLinkedCrate')
    }
    return t('activities.packList.cratePeekNoShellYet')
  })

  function peekMaps(): MaterialJourneyCratePeekMaps {
    return options.cratePeekMaps.value ?? emptyMaterialJourneyCratePeekMaps()
  }

  function sectionsForItem(item: ActivityPackItem): PackCrateShellPeekSection[] {
    const maps = peekMaps()
    const shellC = packShellContainerForPackItem(item, options.packContainers.value)
    let combo = maps.comboComponentsByMaterialId[item.materialItemId] ?? []
    return crateShellForwardPeekSections(
      item,
      options.packContainers.value,
      options.containerItemsByContainerId.value,
      shellC ? maps.containerWarehouseTemplateByContainerId[shellC.id] : undefined,
      shellC ? maps.containerWarehouseContentsByContainerId[shellC.id] : undefined,
      combo,
      materialJourneyPeekSectionTitles(t),
      t('common.material'),
    )
  }

  function mapSubmitError(raw: string | undefined): string {
    const msg = (raw ?? '').trim()
    if (!msg) return t('activities.packList.shellForwardCheckFailed')
    return t('activities.packList.shellForwardCheckFailed')
  }

  async function executeAfterCheck(
    item: ActivityPackItem,
    pending: ShellForwardPendingAction,
    qty: number,
  ): Promise<ActivityPackItem | null> {
    if (pending.kind === 'check_only') return item

    if (pending.kind === 'pack_move') {
      const maxQty = options.packMoveQtyCap?.(item) ?? item.quantityOrdered - item.quantityPacked
      const moveQty = Math.min(qty, Math.max(0, maxQty))
      if (moveQty < 1) {
        return item
      }
      const updated = await postMovePackItem(options.activityId.value, item.id, {
        stage: getBackendStage(options.packStage.value),
        quantity: moveQty,
        source: 'tap',
      })
      options.applyUpdatedItem(updated)
      return updated
    }

    if (pending.kind === 'issue_container') {
      await issueAllPackContainerItems(
        options.activityId.value,
        pending.containerId,
        getBackendStage(options.packStage.value),
        'bulk',
      )
      return null
    }

    if (pending.kind === 'return_container') {
      await returnAllPackContainerItems(
        options.activityId.value,
        pending.containerId,
        'bulk',
      )
      return null
    }

    return null
  }

  async function openForPackItem(
    item: ActivityPackItem,
    qty: number,
    pending: ShellForwardPendingAction = { kind: 'pack_move' },
  ): Promise<void> {
    packItem.value = item
    moveQty.value = Math.max(1, qty)
    pendingAction.value = pending
    submitError.value = null
    historyReplenishByKey.value = {}
    historyPrefillHint.value = null
    initialLineReviews.value = null
    looseStockByMid.value = {}

    const maps = peekMaps()
    if (
      item.materialType === 'physical_combo' &&
      !(maps.comboComponentsByMaterialId[item.materialItemId] ?? []).length
    ) {
      try {
        const list = await getComboComponents(item.materialItemId)
        options.cratePeekMaps.value = {
          ...maps,
          comboComponentsByMaterialId: {
            ...maps.comboComponentsByMaterialId,
            [item.materialItemId]: list,
          },
        }
      } catch {
        /* ignore */
      }
    }

    sections.value = sectionsForItem(item)

    const mids = new Set<string>()
    for (const sec of sections.value) {
      for (const line of sec.lines) {
        const mid = (line.materialItemId ?? '').trim()
        if (mid) mids.add(mid)
      }
    }

    stockLoading.value = true
    try {
      const [stock, history] = await Promise.all([
        mids.size > 0
          ? getPackCrateCheckLooseStock(options.activityId.value, item.id, [...mids])
          : Promise.resolve({} as Record<string, number>),
        getActivityHistory(options.activityId.value).catch(() => []),
      ])
      looseStockByMid.value = stock
      const userId = (authStore.userId ?? '').trim()
      const snaps = indexLatestCrateCheckByPackItemAndLeg(history, { userId })
      crateCheckSnapshots.value = snaps

      const leg = packCrateCheckLegForStage(options.packStage.value) ?? 'outbound'
      const snap = snaps[crateCheckSnapshotKey(item.id, leg)]
      const checkLines = shellCheckLinesFromSections(sections.value)
      if (snap && checkLines.length > 0) {
        const { reviews, replenishByKey } = buildGroupPrefillLineReviewsFromSnapshot(checkLines, snap)
        initialLineReviews.value = reviews
        historyReplenishByKey.value = replenishByKey
        historyPrefillHint.value = formatGroupCrateCheckPrefillHint(snap, t)
      } else if (checkLines.length > 0) {
        const reviews: Record<string, ShellForwardLineReview> = {}
        for (const line of checkLines) {
          reviews[line.key] = defaultLineReview(line.quantity)
        }
        initialLineReviews.value = reviews
      }
    } catch {
      looseStockByMid.value = {}
    } finally {
      stockLoading.value = false
    }

    modalOpen.value = true
  }

  function close(): void {
    modalOpen.value = false
    packItem.value = null
    submitError.value = null
  }

  async function submit(payload: PackCrateCheckRequest): Promise<ActivityPackItem | null> {
    const item = packItem.value
    const qty = moveQty.value
    if (!item || qty < 1) return null

    submitting.value = true
    submitError.value = null
    try {
      const leg = packCrateCheckLegForStage(options.packStage.value) ?? 'outbound'
      const lightweight = packCrateCheckRequestLightweight(
        packWorkflowRole(options.canManageMaterials.value),
        leg,
      )
      const res = await postPackCrateCheck(options.activityId.value, item.id, {
        ...payload,
        check_leg: payload.check_leg ?? leg,
        lightweight: lightweight || undefined,
      })
      if (!res.ok) {
        submitError.value = mapSubmitError((res as { error?: string }).error)
        return null
      }

      const wasCheckOnly = pendingAction.value.kind === 'check_only'
      let updated: ActivityPackItem | null = item
      if (!wasCheckOnly) {
        const moved = await executeAfterCheck(item, pendingAction.value, qty)
        if (pendingAction.value.kind === 'pack_move' && !moved) {
          submitError.value = t('activities.packList.shellForwardMoveAfterCheckFailed')
          return null
        }
        if (moved) updated = moved
      }

      close()
      if (wasCheckOnly) {
        toast.success(t('activities.packList.shellForwardCheckSavedToast'))
      } else if (payload.result === 'ok') {
        toast.success(t('activities.packList.shellForwardCheckOkToast'))
      } else if (groupMode.value) {
        toast.info(t('activities.packList.shellForwardIncompleteToastGroup'))
      } else {
        toast.info(t('activities.packList.shellForwardIncompleteToastMw'))
      }

      return updated
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } }; message?: string }
      submitError.value = mapSubmitError(e.response?.data?.error || e.message)
      return null
    } finally {
      submitting.value = false
    }
  }

  async function openForContainerShell(
    container: ActivityPackContainer,
    shellPackItem: ActivityPackItem,
    qty: number,
  ): Promise<void> {
    const step = options.journeyStep.value
    if (isJourneyReturnStep(step)) {
      await openForPackItem(shellPackItem, qty, {
        kind: 'return_container',
        containerId: container.id,
      })
      return
    }
    if (isJourneyStoreStep(step)) {
      await openForPackItem(shellPackItem, qty, { kind: 'check_only' })
      return
    }
    if (isPackConfirmedStage(options.packStage.value)) {
      await openForPackItem(shellPackItem, qty, { kind: 'pack_move' })
      return
    }
    await openForPackItem(shellPackItem, qty, {
      kind: 'issue_container',
      containerId: container.id,
    })
  }

  return {
    modalOpen,
    packItem,
    moveQty,
    sections,
    label,
    containerBatchId,
    looseStockByMid,
    stockLoading,
    historyReplenishByKey,
    historyPrefillHint,
    initialLineReviews,
    submitError,
    submitting,
    groupMode,
    checkOnly,
    emptyHint,
    openForPackItem,
    openForContainerShell,
    close,
    submit,
  }
}
