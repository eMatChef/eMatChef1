import { ref } from 'vue'
import { getComboComponents, type ComboComponent } from '@/api/materials'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import { peekSectionsForJourneyCombo } from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import {
  physicalComboHasSelectableIssueComponents,
  type PhysicalComboIssueSelection,
  type PackIssueWizardEmitPayload,
} from '@/components/activities/physicalComboIssueFlow'
import type { ComposerTranslation } from 'vue-i18n'

export function usePhysicalComboIssuePicker(options: {
  packContainers: () => ActivityPackContainer[]
  containerItemsByContainerId: () => Record<string, import('@/api/activityContainers').ActivityPackContainerItem[]>
  cratePeekMaps: () => MaterialJourneyCratePeekMaps
  t: ComposerTranslation
  emitIssueWizard: (payload: PackIssueWizardEmitPayload) => void
}) {
  const open = ref(false)
  const loading = ref(false)
  const shellPackItem = ref<ActivityPackItem | null>(null)
  const issueType = ref<'loss' | 'repair'>('loss')
  const sections = ref<PackCrateShellPeekSection[]>([])
  const comboComponentsByMaterialId = ref<Record<string, ComboComponent[]>>({})

  function close(): void {
    open.value = false
    shellPackItem.value = null
    sections.value = []
  }

  function emitSelections(selections: PhysicalComboIssueSelection[], type: 'loss' | 'repair'): void {
    if (selections.length === 0) return
    if (selections.length === 1) {
      options.emitIssueWizard({
        materialItemId: selections[0].materialItemId,
        issueType: type,
        quantity: selections[0].quantity,
      })
      return
    }
    options.emitIssueWizard({
      items: selections.map((s) => ({
        materialItemId: s.materialItemId,
        issueType: type,
        quantity: s.quantity,
      })),
    })
  }

  function peekSectionsForPackItem(pi: ActivityPackItem): PackCrateShellPeekSection[] {
    const maps = options.cratePeekMaps()
    return peekSectionsForJourneyCombo(pi, options.packContainers(), {
      containerItemsByContainerId: options.containerItemsByContainerId(),
      containerWarehouseTemplateByContainerId: maps.containerWarehouseTemplateByContainerId,
      containerWarehouseContentsByContainerId: maps.containerWarehouseContentsByContainerId,
      comboComponentsByMaterialId: {
        ...comboComponentsByMaterialId.value,
        ...maps.comboComponentsByMaterialId,
      },
      comboMaterialIdByContainerId: maps.comboMaterialIdByContainerId,
    }, options.t)
  }

  async function openPicker(pi: ActivityPackItem, type: 'loss' | 'repair'): Promise<void> {
    shellPackItem.value = pi
    issueType.value = type
    open.value = true
    loading.value = true
    sections.value = []
    try {
      let combo = options.cratePeekMaps().comboComponentsByMaterialId[pi.materialItemId] ?? []
      if (combo.length === 0) {
        combo = await getComboComponents(pi.materialItemId)
        comboComponentsByMaterialId.value = {
          ...comboComponentsByMaterialId.value,
          [pi.materialItemId]: combo,
        }
      }
      sections.value = peekSectionsForPackItem(pi)
      if (!physicalComboHasSelectableIssueComponents(sections.value)) {
        close()
        options.emitIssueWizard({ materialItemId: pi.materialItemId, issueType: type })
      }
    } finally {
      loading.value = false
    }
  }

  async function tryOpenPicker(
    pi: ActivityPackItem,
    type: 'loss' | 'repair',
    quantity?: number,
  ): Promise<boolean> {
    if (quantity != null && quantity > 0) {
      options.emitIssueWizard({ materialItemId: pi.materialItemId, issueType: type, quantity })
      return true
    }
    const cached = {
      ...comboComponentsByMaterialId.value,
      ...options.cratePeekMaps().comboComponentsByMaterialId,
    }[pi.materialItemId] ?? []
    let peekSections = cached.length > 0 ? peekSectionsForPackItem(pi) : []
    if (!physicalComboHasSelectableIssueComponents(peekSections) && cached.length === 0) {
      await openPicker(pi, type)
      return true
    }
    if (!physicalComboHasSelectableIssueComponents(peekSections)) {
      return false
    }
    await openPicker(pi, type)
    return true
  }

  function onConfirm(selections: PhysicalComboIssueSelection[]): void {
    const type = issueType.value
    close()
    emitSelections(selections, type)
  }

  return {
    open,
    loading,
    shellPackItem,
    issueType,
    sections,
    close,
    tryOpenPicker,
    onConfirm,
  }
}
