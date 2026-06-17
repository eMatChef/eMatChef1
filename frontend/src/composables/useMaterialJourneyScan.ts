import { computed, ref, type Ref } from 'vue'
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
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import type { PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import {
  resolveMaterialBatchScan,
  resolveMaterialTextSearch,
  type MaterialScanResolveResult,
  type MaterialScanTone,
} from '@/composables/materialScanResolve'
import { parseScanInput } from '@/utils/scanParser'
import { useToast } from '@/composables/useToast'

export type MaterialScanSessionEntry = {
  id: string
  at: Date
  label: string
  tone: MaterialScanTone
}

const SESSION_LOG_MAX = 5

export function useMaterialJourneyScan(options: {
  activityId: Ref<string>
  journeyStep: Ref<JourneyStep>
  listCtx: Ref<PackWorkflowListContext>
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  listEditable: Ref<boolean>
}) {
  const { t } = useI18n()
  const toast = useToast()

  const query = ref('')
  const resolving = ref(false)
  const activeResult = ref<MaterialScanResolveResult | null>(null)
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

  function pushSession(label: string, tone: MaterialScanTone): void {
    entrySeq += 1
    const entry: MaterialScanSessionEntry = {
      id: `scan-${entrySeq}`,
      at: new Date(),
      label,
      tone,
    }
    sessionLog.value = [entry, ...sessionLog.value].slice(0, SESSION_LOG_MAX)
  }

  function dismissResult(): void {
    activeResult.value = null
    bulkConfirmed.value = false
  }

  function resultLabel(result: MaterialScanResolveResult): string {
    return result.title
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
    return rows.filter(
      (row) =>
        row.title.toLowerCase().includes(q) ||
        (row.subtitle?.toLowerCase().includes(q) ?? false) ||
        (row.categoryName?.toLowerCase().includes(q) ?? false),
    )
  }

  function primaryActionEnabled(result: MaterialScanResolveResult): boolean {
    if (!result.canAct) return false
    if (result.needsBulkConfirm && !bulkConfirmed.value) return false
    return true
  }

  function primaryActionLabel(result: MaterialScanResolveResult): string {
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

  function clearQuery(): void {
    query.value = ''
    dismissResult()
  }

  return {
    query,
    resolving,
    activeResult,
    bulkConfirmed,
    sessionLog,
    searchFilter,
    submitQuery,
    dismissResult,
    confirmBulkBatch,
    filterTasks,
    primaryActionEnabled,
    primaryActionLabel,
    messageForResult,
    clearQuery,
  }
}
