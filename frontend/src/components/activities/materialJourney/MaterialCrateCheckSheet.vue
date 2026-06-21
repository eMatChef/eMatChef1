<script setup lang="ts">
import { computed, ref, toRef, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  issueAllPackContainerItems,
  returnAllPackContainerItems,
} from '@/api/activityContainers'
import PackCrateShellForwardModal from '@/components/activities/PackCrateShellForwardModal.vue'
import PackCrateShellInlinePanel from '@/components/activities/PackCrateShellInlinePanel.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyReturnStep, isJourneyStoreStep } from '@/components/activities/materialJourneySteps'
import { getBackendStage, type PackStage } from '@/components/activities/packStageQuantities'
import { packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
import EButton from '@/components/form/base/EButton.vue'
import { useMaterialJourneySheetDialog } from '@/composables/useMaterialJourneySheetDialog'
import { peekSectionsForJourneyContainer } from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { emptyMaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { useMaterialJourneyShellForward } from '@/composables/useMaterialJourneyShellForward'

const props = defineProps<{
  open: boolean
  container: ActivityPackContainer | null
  shellPackItem: ActivityPackItem | null
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  cratePeekMaps?: MaterialJourneyCratePeekMaps
  journeyStep: JourneyStep
  packStage: PackStage
  activityId: string
  departmentId: string
  canManageMaterials: boolean
  canSubmit: boolean
  canDelete?: boolean
  issueableUnits: number
  deleting?: boolean
  applyUpdatedItem: (item: ActivityPackItem) => void
  packMoveQtyCap?: (item: ActivityPackItem) => number
  /** Kistencheck mit Fokus «lose mitnehmen» (Transport … Retour). */
  focusLooseIssue?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  completed: []
  delete: []
}>()

const { t } = useI18n()
const { sheetFullscreen, sheetMaxWidth } = useMaterialJourneySheetDialog()
const submitting = ref(false)

const usesShellCheck = computed(() => {
  if (props.focusLooseIssue) return false
  const pi = props.shellPackItem
  if (!pi) return false
  if (pi.materialType === 'physical_combo') return true
  return packShellContainerForPackItem(pi, props.packContainers) != null
})

const {
  modalOpen: shellModalOpen,
  label: shellLabel,
  moveQty: shellMoveQty,
  sections: shellSections,
  containerBatchId: shellContainerBatchId,
  looseStockByMid: shellLooseStockByMid,
  stockLoading: shellStockLoading,
  historyReplenishByKey: shellHistoryReplenishByKey,
  historyPrefillHint: shellHistoryPrefillHint,
  groupMode: shellGroupMode,
  checkOnly: shellCheckOnly,
  submitError: shellSubmitError,
  submitting: shellSubmitting,
  emptyHint: shellEmptyHint,
  initialLineReviews: shellInitialLineReviews,
  close: closeShellForward,
  openForContainerShell,
  submit: submitShellForward,
} = useMaterialJourneyShellForward({
  activityId: toRef(props, 'activityId'),
  departmentId: toRef(props, 'departmentId'),
  packItems: toRef(props, 'packItems'),
  packContainers: toRef(props, 'packContainers'),
  containerItemsByContainerId: toRef(props, 'containerItemsByContainerId'),
  cratePeekMaps: toRef(props, 'cratePeekMaps') as unknown as Ref<MaterialJourneyCratePeekMaps>,
  journeyStep: toRef(props, 'journeyStep'),
  packStage: toRef(props, 'packStage'),
  canManageMaterials: toRef(props, 'canManageMaterials'),
  applyUpdatedItem: (item) => props.applyUpdatedItem(item),
  packMoveQtyCap: props.packMoveQtyCap ? (item) => props.packMoveQtyCap!(item) : undefined,
})

watch(
  () => [props.open, props.container?.id, props.shellPackItem?.id, props.issueableUnits, props.focusLooseIssue] as const,
  async ([isOpen, containerId, shellId, units, looseFocus]) => {
    if (looseFocus || !isOpen || !usesShellCheck.value || !props.container || !props.shellPackItem || !containerId || !shellId) {
      closeShellForward()
      return
    }
    await openForContainerShell(props.container, props.shellPackItem, Math.max(1, units))
  },
)

const sections = computed(() => {
  if (!props.container) return []
  const peekMaps = props.cratePeekMaps ?? emptyMaterialJourneyCratePeekMaps()
  return peekSectionsForJourneyContainer(
    props.container,
    {
      containerItemsByContainerId: props.containerItemsByContainerId,
      ...peekMaps,
    },
    props.shellPackItem,
    t,
    props.packItems,
    props.packContainers,
  )
})

const emptyHint = computed(() => {
  if (props.shellPackItem) {
    return t('activities.packList.cratePeekEmptyLinkedCrate')
  }
  return t('activities.packList.cratePeekNoShellYet')
})

const primaryLabel = computed(() => {
  if (props.journeyStep === 'pack') {
    return t('activities.materialJourney.crateSheet.primaryPack')
  }
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.primaryReturn')
  }
  if (isJourneyStoreStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.primaryStore')
  }
  return t('activities.materialJourney.crateSheet.primaryIssue')
})

const subtitle = computed(() => {
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.subtitleReturn')
  }
  if (isJourneyStoreStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.subtitleStore')
  }
  return t('activities.materialJourney.crateSheet.subtitle')
})

const canPrimary = computed(
  () => props.canSubmit && props.issueableUnits > 0 && !submitting.value,
)

function close(): void {
  emit('update:open', false)
}

async function onPrimary(): Promise<void> {
  if (!props.container || !canPrimary.value) return
  submitting.value = true
  try {
    if (isJourneyReturnStep(props.journeyStep)) {
      await returnAllPackContainerItems(props.activityId, props.container.id, 'bulk')
    } else {
      await issueAllPackContainerItems(
        props.activityId,
        props.container.id,
        getBackendStage(props.packStage),
        'bulk',
      )
    }
    emit('completed')
    close()
  } finally {
    submitting.value = false
  }
}

function onShellCancel(): void {
  closeShellForward()
  close()
}

async function onShellSubmit(payload: Parameters<typeof submitShellForward>[0]): Promise<void> {
  const updated = await submitShellForward(payload)
  if (updated) {
    emit('completed')
    close()
  }
}
</script>

<template>
  <PackCrateShellForwardModal
    v-if="usesShellCheck"
    :open="open && shellModalOpen"
    :label="shellLabel"
    :move-qty="shellMoveQty"
    :sections="shellSections"
    :department-id="departmentId"
    :container-batch-id="shellContainerBatchId"
    :loose-stock-by-mid="shellLooseStockByMid"
    :stock-loading="shellStockLoading"
    :history-replenish-by-key="shellHistoryReplenishByKey"
    :history-prefill-hint="shellHistoryPrefillHint"
    :can-report-issues="false"
    :group-mode="shellGroupMode"
    :check-only="shellCheckOnly"
    :submit-error="shellSubmitError"
    :submitting="shellSubmitting"
    :empty-hint="shellEmptyHint"
    :embedded-issues-by-line-key="{}"
    :repack-issue-reviews="{}"
    :orphan-issues="[]"
    :initial-line-reviews="shellInitialLineReviews"
    :pack-item-id="shellPackItem?.id ?? null"
    @cancel="onShellCancel"
    @submit="onShellSubmit"
  />

  <v-dialog
    v-else
    :model-value="open"
    :fullscreen="sheetFullscreen"
    :max-width="sheetMaxWidth"
    scrollable
    class="material-journey-sheet-dialog"
    transition="dialog-bottom-transition"
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="container" class="material-journey-sheet">
      <header class="material-journey-sheet__header">
        <EButton variant="secondary" size="small" @click="close">
          {{ t('common.close') }}
        </EButton>
        <div class="material-journey-sheet__headline">
          <h2 class="material-journey-sheet__title">{{ container.label }}</h2>
          <p class="material-journey-sheet__subtitle text-muted">
            {{ subtitle }}
          </p>
        </div>
      </header>

      <div class="material-journey-sheet__body material-journey-sheet__body--crate">
        <PackCrateShellInlinePanel
          :sections="sections"
          :empty-hint="emptyHint"
          :check-pack-item="shellPackItem"
          :separate-section-rows="true"
          :default-expanded="true"
          :parent-expanded="true"
          :use-reality-view="false"
          :show-template-toggle="false"
          :loose-issue-container-id="focusLooseIssue ? container?.id ?? null : null"
          :loose-issue-crate-label="focusLooseIssue ? container?.label ?? null : null"
        />
      </div>

      <footer class="material-journey-sheet__footer">
        <EButton
          v-if="canDelete"
          variant="danger"
          class="material-journey-sheet__delete"
          :disabled="deleting || submitting"
          :loading="deleting"
          @click="emit('delete')"
        >
          {{ t('activities.packList.deleteContainer') }}
        </EButton>
        <EButton
          variant="primary"
          class="material-journey-sheet__primary"
          :disabled="!canPrimary"
          :loading="submitting"
          @click="onPrimary"
        >
          {{ primaryLabel }}
        </EButton>
      </footer>
    </div>
  </v-dialog>
</template>

<style src="@/styles/views/activities/material-journey-sheet.css"></style>
