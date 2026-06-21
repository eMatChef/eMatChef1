import { computed, ref, type ComputedRef, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { getPublicMaterialBatchByCodes } from '@/api/public/publicLookup'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  isJourneyReturnStep,
  isJourneyStoreStep,
  isJourneyTransportBackStep,
  isJourneyTransportOutStep,
} from '@/components/activities/materialJourneySteps'
import { isPhysicalComboPackItem, packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import type { PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import {
  resolveMaterialBatchScan,
  resolveMaterialTextSearch,
  resolveStorageLocationScan,
  type MaterialScanResolveResult,
  type MaterialScanTone,
} from '@/composables/materialScanResolve'
import { lookupStorageQr } from '@/api/storageQr'
import { parseStorageLookupData, type StorageLookupResult } from '@/utils/packStorageLocationMatch'
import { parseScanInput, isScanLikeInput } from '@/utils/scanParser'
import { useToast } from '@/composables/useToast'

export type MaterialScanSessionEntry = {
  id: string
  at: Date
  label: string
  tone: MaterialScanTone
}

const SESSION_LOG_MAX = 3
const SESSION_LOG_LABEL_MAX = 36

function truncateSessionLogLabel(label: string): string {
  const trimmed = label.trim()
  if (trimmed.length <= SESSION_LOG_LABEL_MAX) return trimmed
  return `${trimmed.slice(0, SESSION_LOG_LABEL_MAX - 1)}…`
}

export function useMaterialJourneyScan(options: {
  departmentId: Ref<string>
  activityId: Ref<string>
  journeyStep: Ref<JourneyStep>
  listCtx: Ref<PackWorkflowListContext>
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  listEditable: Ref<boolean>
  selectedPackCrateId?: Ref<string | null>
}) {
  const { t } = useI18n()
  const toast = useToast()

  const query = ref('')
  const resolving = ref(false)
  const activeResult = ref<MaterialScanResolveResult | null>(null)
  const shelfSession = ref<StorageLookupResult | null>(null)
  const bulkConfirmed = ref(false)
  const sessionLog = ref<MaterialScanSessionEntry[]>([])
  let entrySeq = 0

  const searchFilter = computed(() => query.value.trim().toLowerCase())

  const resolveCtx = computed(() => ({
    activityId: options.activityId.value,
    journeyStep: options.journeyStep.value,
    listCtx: options.listCtx.value,
    packItems: options.packItems.value,
    packContainers: options.packContainers.value,
    containerItemsByContainerId: options.containerItemsByContainerId.value,
    listEditable: options.listEditable.value,
  }))

  const activeShelfResult: ComputedRef<MaterialScanResolveResult | null> = computed(() => {
    if (!shelfSession.value) return null
    return resolveStorageLocationScan(shelfSession.value, resolveCtx.value)
  })

  const hasShelfSession = computed(() => shelfSession.value != null)

  function pushSession(label: string, tone: MaterialScanTone): void {
    entrySeq += 1
    const entry: MaterialScanSessionEntry = {
      id: `scan-${entrySeq}`,
      at: new Date(),
      label: truncateSessionLogLabel(label),
      tone,
    }
    sessionLog.value = [entry, ...sessionLog.value].slice(0, SESSION_LOG_MAX)
  }

  function resultLabel(result: MaterialScanResolveResult): string {
    return truncateSessionLogLabel(result.title)
  }

  function dismissResult(): void {
    activeResult.value = null
    bulkConfirmed.value = false
  }

  function dismissShelfSession(): void {
    shelfSession.value = null
    dismissResult()
  }

  function clearScanInput(): void {
    query.value = ''
  }

  async function submitQuery(raw: string): Promise<MaterialScanResolveResult | null> {
    const trimmed = raw.trim()
    if (!trimmed) {
      dismissResult()
      return null
    }

    query.value = trimmed
    bulkConfirmed.value = false
    resolving.value = true

    try {
      const parsed = parseScanInput(trimmed)

      if (parsed.type === 'material_batch') {
        const lookup = await getPublicMaterialBatchByCodes(parsed.materialCode, parsed.batchCode)
        const result = resolveMaterialBatchScan(lookup, resolveCtx.value)
        activeResult.value = result
        pushSession(resultLabel(result), result.tone)
        return result
      }

      if (
        parsed.type === 'storage_address' ||
        parsed.type === 'storage_rack' ||
        parsed.type === 'storage_slot'
      ) {
        const kind =
          parsed.type === 'storage_address' ? 'l' : parsed.type === 'storage_rack' ? 'r' : 's'
        const code =
          parsed.type === 'storage_address'
            ? parsed.locationCode
            : parsed.type === 'storage_rack'
              ? parsed.rackCode
              : parsed.slotCode
        const raw = await lookupStorageQr(options.departmentId.value, kind, code)
        const lookup = parseStorageLookupData(raw)
        if (!lookup) {
          const fail: MaterialScanResolveResult = {
            type: 'unknown',
            tone: 'error',
            title: trimmed.slice(0, 80),
            detail: 'storage_not_found',
            canAct: false,
          }
          activeResult.value = fail
          pushSession(trimmed.slice(0, 40), 'error')
          return fail
        }
        const result = resolveStorageLocationScan(lookup, resolveCtx.value)
        shelfSession.value = lookup
        activeResult.value = null
        pushSession(resultLabel(result), result.tone)
        return result
      }

      if (parsed.type === 'unknown') {
        const textResult = resolveMaterialTextSearch(trimmed, resolveCtx.value)
        if (textResult) {
          activeResult.value = textResult
          pushSession(resultLabel(textResult), textResult.tone)
          return textResult
        }
        const fail: MaterialScanResolveResult = {
          type: 'unknown',
          tone: 'error',
          title: trimmed.slice(0, 80),
          detail: 'unknown',
          canAct: false,
        }
        activeResult.value = fail
        pushSession(trimmed.slice(0, 40), 'error')
        return fail
      }

      const fail: MaterialScanResolveResult = {
        type: 'unknown',
        tone: 'muted',
        title: trimmed.slice(0, 80),
        detail: 'unsupported_scan',
        canAct: false,
      }
      activeResult.value = fail
      pushSession(trimmed.slice(0, 40), 'muted')
      return fail
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return null
    } finally {
      resolving.value = false
    }
  }

  function confirmBulkBatch(): void {
    bulkConfirmed.value = true
  }

  function filterTasks<T extends { title: string; subtitle: string | null; categoryName: string | null }>(
    rows: T[],
  ): T[] {
    const q = searchFilter.value
    if (q.length < 2) return rows
    if (isScanLikeInput(query.value)) return rows
    return rows.filter(
      (row) =>
        row.title.toLowerCase().includes(q) ||
        (row.subtitle?.toLowerCase().includes(q) ?? false) ||
        (row.categoryName?.toLowerCase().includes(q) ?? false),
    )
  }

  const listTextFilterActive = computed(() => {
    const trimmed = query.value.trim()
    if (trimmed.length < 2) return false
    return !isScanLikeInput(trimmed)
  })

  function primaryActionEnabled(result: MaterialScanResolveResult): boolean {
    if (!result.canAct) return false
    if (result.needsBulkConfirm && !bulkConfirmed.value) return false
    return true
  }

  function showInCrateAction(result: MaterialScanResolveResult): boolean {
    if (options.selectedPackCrateId?.value) return false
    if (!options.listEditable.value) return false
    if (options.journeyStep.value !== 'pack') return false
    if (!result.canAct || !result.packItem) return false
    if (result.type !== 'loose_ready' && result.type !== 'bulk_wrong_batch') return false
    if (isPhysicalComboPackItem(result.packItem)) return false
    if (result.needsBulkConfirm && !bulkConfirmed.value) return false
    return true
  }

  function inCrateActionLabel(): string {
    return t('activities.materialJourney.scan.actionInCrate')
  }

  function primaryActionLabel(result: MaterialScanResolveResult): string {
    if (
      options.selectedPackCrateId?.value &&
      (result.type === 'loose_ready' || result.type === 'bulk_wrong_batch')
    ) {
      return t('activities.materialJourney.scan.actionInCrate')
    }
    if (result.type === 'unknown_crate') {
      return t('activities.materialJourney.scan.actionUseAsPackCrate')
    }
    if (result.type === 'crate_shell' || result.type === 'in_crate') {
      if (options.journeyStep.value === 'pack') {
        return t('activities.materialJourney.scan.actionOpenCratePack')
      }
      if (isJourneyReturnStep(options.journeyStep.value)) {
        return t('activities.materialJourney.scan.actionOpenCrateReturn')
      }
      if (isJourneyStoreStep(options.journeyStep.value)) {
        return t('activities.materialJourney.scan.actionOpenCrateStore')
      }
      return t('activities.materialJourney.scan.actionOpenCrateIssue')
    }
    if (result.type === 'combo_check' || result.detail === 'text_combo') {
      return t('activities.materialJourney.scan.actionOpenCombo')
    }
    if (result.type === 'in_virtual_crate') {
      return t('activities.materialJourney.scan.actionOpenCombo')
    }
    if (result.type === 'loose_ready' || result.type === 'bulk_wrong_batch') {
      if (options.journeyStep.value === 'pack') {
        return t('activities.materialJourney.scan.actionBookPack')
      }
      if (isJourneyReturnStep(options.journeyStep.value)) {
        return t('activities.materialJourney.scan.actionBookReturn')
      }
      if (isJourneyStoreStep(options.journeyStep.value)) {
        return t('activities.materialJourney.scan.actionShelve')
      }
      if (isJourneyTransportOutStep(options.journeyStep.value)) {
        return t('activities.materialJourney.scan.actionBookTransportOut')
      }
      if (isJourneyTransportBackStep(options.journeyStep.value)) {
        return t('activities.materialJourney.scan.actionBookTransportBack')
      }
      return t('activities.materialJourney.scan.actionBookIssue')
    }
    return t('activities.materialJourney.scan.actionShow')
  }

  function messageForResult(result: MaterialScanResolveResult): string {
    const key = `activities.materialJourney.scan.result.${result.detail ?? result.type}` as const
    if (result.type === 'in_virtual_crate' && result.parentCombo) {
      return t(key, { name: packMaterialDisplayName(result.parentCombo) })
    }
    return t(key)
  }

  function dismissLabelForResult(result: MaterialScanResolveResult): string {
    if (result.type === 'unknown_crate') {
      return t('activities.materialJourney.scan.actionDeclinePackCrate')
    }
    return t('common.close')
  }

  function messageForShelfResult(result: MaterialScanResolveResult): string {
    if (
      result.detail === 'shelf_open' &&
      result.shelfOpenCount != null &&
      result.shelfTotalCount != null &&
      result.shelfOpenCount > 0
    ) {
      const done = result.shelfTotalCount - result.shelfOpenCount
      const parts = [
        t('activities.materialJourney.scan.result.shelf_session_progress', {
          done,
          total: result.shelfTotalCount,
        }),
      ]
      if (options.selectedPackCrateId?.value) {
        parts.push(t('activities.materialJourney.scan.result.shelf_session_crate_hint'))
      } else if (
        result.storageLookup?.entity_type === 'storage_rack' ||
        result.storageLookup?.entity_type === 'storage_address'
      ) {
        parts.push(t('activities.materialJourney.scan.result.shelf_session_hint_grouped'))
      } else {
        parts.push(t('activities.materialJourney.scan.result.shelf_session_hint'))
      }
      return parts.join(' ')
    }
    return messageForResult(result)
  }

  function clearQuery(): void {
    clearScanInput()
    dismissResult()
  }

  return {
    query,
    resolving,
    activeResult,
    activeShelfResult,
    hasShelfSession,
    bulkConfirmed,
    sessionLog,
    searchFilter,
    submitQuery,
    dismissResult,
    dismissShelfSession,
    clearScanInput,
    confirmBulkBatch,
    filterTasks,
    listTextFilterActive,
    primaryActionEnabled,
    primaryActionLabel,
    showInCrateAction,
    inCrateActionLabel,
    messageForResult,
    messageForShelfResult,
    dismissLabelForResult,
    clearQuery,
  }
}
