import { ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityIssues, type ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isConsumablePackItem } from '@/utils/packItemConsumable'
import {
  atEventQtySummary,
  atEventQtySummaryForCrateContainer,
  crateShellIssuedAtEvent,
  formatAtEventQtyLabel,
  issuedQtyForAccordionLineAtEvent,
} from '@/utils/materialJourneyAtEventInventoryQty'

export function useMaterialJourneyAtEventInventoryIssues(options: {
  activityId: Ref<string>
  active: Ref<boolean>
  packItems: Ref<ActivityPackItem[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  consumableMaterialItemIds?: Ref<ReadonlySet<string>>
  shellPackItemForContainer?: (containerId: string) => ActivityPackItem | undefined
}) {
  const { t } = useI18n()
  const issues = ref<ActivityIssueReportRow[]>([])

  async function reloadIssues(): Promise<void> {
    if (!options.active.value) {
      issues.value = []
      return
    }
    issues.value = await getActivityIssues(options.activityId.value).catch(() => [])
  }

  watch([options.active, options.activityId], () => void reloadIssues(), { immediate: true })
  watch(options.packItems, () => {
    if (options.active.value) void reloadIssues()
  })

  function packItemForMaterialId(materialItemId: string): ActivityPackItem | undefined {
    return options.packItems.value.find((pi) => pi.materialItemId === materialItemId)
  }

  function atEventQtyLabelForRow(
    row: MaterialJourneyTaskRow,
    previewLines: MaterialJourneyAccordionLine[] = [],
  ): string | null {
    if (!options.active.value) return null

    if (row.kind === 'loose' || row.kind === 'combo') {
      const issued = row.packItem?.quantityIssued ?? row.doneQty
      if (issued <= 0) return null
      const summary = atEventQtySummary(
        issued,
        row.packItem?.materialItemId,
        issues.value,
      )
      return formatAtEventQtyLabel(summary, t)
    }

    if (row.kind === 'crate' && row.container) {
      const shellIssued = crateShellIssuedAtEvent(row, options.shellPackItemForContainer)
      const summary = atEventQtySummaryForCrateContainer(
        row.container.id,
        options.containerItemsByContainerId.value,
        issues.value,
        previewLines,
        { shellIssuedAtEvent: shellIssued },
      )
      if (summary.total <= 0) return t('activities.materialJourney.row.atEventTotal', { count: 0 })
      return formatAtEventQtyLabel(summary, t)
    }

    return null
  }

  function atEventQtyLabelForLine(
    line: MaterialJourneyAccordionLine,
    row: MaterialJourneyTaskRow,
  ): string | null {
    if (!options.active.value || !line.materialItemId) return null
    const shellIssued = crateShellIssuedAtEvent(row, options.shellPackItemForContainer)
    const issued = issuedQtyForAccordionLineAtEvent(line, row, { crateShellIssued: shellIssued })
    if (issued <= 0) return null
    const summary = atEventQtySummary(issued, line.materialItemId, issues.value)
    return formatAtEventQtyLabel(summary, t)
  }

  function isConsumableForMaterialId(materialItemId: string | null | undefined): boolean {
    if (!materialItemId) return false
    const pi = packItemForMaterialId(materialItemId)
    if (!pi) return options.consumableMaterialItemIds?.value?.has(materialItemId) === true
    return isConsumablePackItem(pi, options.consumableMaterialItemIds?.value)
  }

  return {
    issues,
    reloadIssues,
    atEventQtyLabelForRow,
    atEventQtyLabelForLine,
    issuedQtyForAccordionLineAtEvent,
    packItemForMaterialId,
    isConsumableForMaterialId,
  }
}
