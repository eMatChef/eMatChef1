<script setup lang="ts">
import { computed, toRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import MaterialJourneyStepper from '@/components/activities/materialJourney/MaterialJourneyStepper.vue'
import MaterialJourneyToolbar from '@/components/activities/materialJourney/MaterialJourneyToolbar.vue'
import MaterialJourneyTaskList from '@/components/activities/materialJourney/MaterialJourneyTaskList.vue'
import MaterialJourneyStepFooter from '@/components/activities/materialJourney/MaterialJourneyStepFooter.vue'
import MaterialJourneyLegacyLink from '@/components/activities/materialJourney/MaterialJourneyLegacyLink.vue'
import MaterialJourneyJsBanner from '@/components/activities/materialJourney/MaterialJourneyJsBanner.vue'
import MaterialCrateCheckSheet from '@/components/activities/materialJourney/MaterialCrateCheckSheet.vue'
import MaterialComboCheckSheet from '@/components/activities/materialJourney/MaterialComboCheckSheet.vue'
import MaterialReturnCrateSheet from '@/components/activities/materialJourney/MaterialReturnCrateSheet.vue'
import MaterialStoreShelveSheet from '@/components/activities/materialJourney/MaterialStoreShelveSheet.vue'
import type { ReturnCrateLineEdit } from '@/components/activities/PackReturnCrateModal.vue'
import MaterialJourneyScanBar from '@/components/activities/materialJourney/MaterialJourneyScanBar.vue'
import MaterialScanResultCard from '@/components/activities/materialJourney/MaterialScanResultCard.vue'
import { useMaterialJourneyScan } from '@/composables/useMaterialJourneyScan'
import { groupMaterialJourneyTasksByShelf } from '@/components/activities/materialJourneyRegalGroups'
import MaterialJourneyTransportTours from '@/components/activities/materialJourney/MaterialJourneyTransportTours.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  isJourneyTransportBackStep,
  isJourneyTransportOutStep,
} from '@/components/activities/materialJourneySteps'
import { useMaterialJourneyData } from '@/composables/useMaterialJourneyData'
import { useMaterialJourneyTasks } from '@/composables/useMaterialJourneyTasks'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import {
  computeMaterialJourneyJsSummary,
  showMaterialJourneyJsBanner,
} from '@/components/activities/materialJourneyJsSummary'

const props = defineProps<{
  departmentId: string
  activityId: string
}>()

const route = useRoute()
const router = useRouter()
const { t, te } = useI18n()

const stepParam = computed(() => {
  const raw = route.params.step
  if (Array.isArray(raw)) return raw[0]
  return raw ? String(raw) : undefined
})

const {
  activity,
  packItems,
  packContainers,
  containerItemsByContainerId,
  loading,
  error,
  profile,
  steps,
  resolvedStep,
  needsStepRedirect,
  positionCount,
  isEarlyPackPreview,
  canManageMaterials,
  reload,
} = useMaterialJourneyData(
  toRef(props, 'departmentId'),
  toRef(props, 'activityId'),
  stepParam,
)

const {
  filterTab,
  movingId,
  packStage,
  listEditable,
  isFutureStep,
  visibleTasks,
  showByShelfFilter,
  progress,
  activateTaskRow,
  crateSheetOpen,
  comboSheetOpen,
  activeCrate,
  activeCombo,
  activeCrateShellPackItem,
  activeCrateIssueableUnits,
  activeComboMaxForwardQty,
  onCrateSheetCompleted,
  onComboSheetCompleted,
  openCrateContainer,
  openComboPackItem,
  activateLoosePackItem,
  taskRowForScanResult,
  packListCtx,
  returnCrate,
  storeShelveOpen,
  activeStoreItem,
  activeStoreMaxQty,
  storeShelveQty,
  storeShelveSubmitting,
  storeShelveFeedback,
  submitStoreShelve,
  onStoreShelveNext,
  onStoreShelveStay,
  allTasks,
} = useMaterialJourneyTasks({
  activity,
  packItems,
  packContainers,
  containerItemsByContainerId,
  journeyStep: resolvedStep,
  profile,
  canManageMaterials,
  isEarlyPackPreview,
  reload,
})

const scan = useMaterialJourneyScan({
  activityId: toRef(props, 'activityId'),
  journeyStep: resolvedStep,
  listCtx: packListCtx,
  packItems,
  packContainers,
  containerItemsByContainerId,
  listEditable,
})

const {
  query: scanQuery,
  resolving: scanResolving,
  activeResult: scanResult,
  bulkConfirmed: scanBulkConfirmed,
  sessionLog: scanSessionLog,
  submitQuery,
  dismissResult,
  confirmBulkBatch,
  filterTasks,
  primaryActionEnabled,
  primaryActionLabel,
  messageForResult,
  clearQuery,
} = scan

const {
  open: returnCrateOpen,
  container: returnCrateContainer,
  lines: returnCrateLines,
  partition: returnCratePartition,
  submitting: returnCrateSubmitting,
  submitDisabled: returnCrateSubmitDisabled,
  submit: submitReturnCrate,
} = returnCrate

function onReturnCrateLinesUpdate(lines: ReturnCrateLineEdit[]): void {
  returnCrateLines.value = lines
}

const displayedTasks = computed(() => filterTasks(visibleTasks.value))

const displayedRegalGroups = computed(() => {
  if (filterTab.value !== 'byShelf') return []
  return groupMaterialJourneyTasksByShelf(
    displayedTasks.value,
    t('activities.materialJourney.regalGroup.noShelf'),
  )
})

async function onScanSubmit(): Promise<void> {
  await submitQuery(scanQuery.value)
}

function onScanClear(): void {
  clearQuery()
}

function onScanPrimary(): void {
  const result = scanResult.value
  if (!result || !primaryActionEnabled(result)) return

  if (result.container) {
    const row = taskRowForScanResult(result)
    if (row) {
      activateTaskRow(row, 'scan')
    } else {
      openCrateContainer(result.container)
    }
  } else if (result.packItem) {
    if (result.type === 'combo_check' || result.detail === 'text_combo' || result.type === 'in_virtual_crate') {
      openComboPackItem(result.packItem)
    } else {
      const row = taskRowForScanResult(result)
      if (row) {
        activateTaskRow(row, 'scan')
      } else {
        activateLoosePackItem(result.packItem, 'scan')
      }
    }
  }

  dismissResult()
  clearQuery()
}

watch(resolvedStep, () => {
  filterTab.value = 'open'
  dismissResult()
})

const activityStatusLabel = computed(() => {
  const status = activity.value?.status
  if (!status) return ''
  const key = `activities.status.${activityStatusI18nKey(status)}` as const
  return te(key) ? t(key) : status
})

const activityStatusCss = computed(() =>
  activity.value ? activityStatusClass(activity.value.status ?? '') : '',
)

const showJsBanner = computed(() => showMaterialJourneyJsBanner(activity.value))

const jsSummary = computed(() => computeMaterialJourneyJsSummary(packItems.value))

const showTransportTours = computed(
  () =>
    isJourneyTransportOutStep(resolvedStep.value) ||
    isJourneyTransportBackStep(resolvedStep.value),
)

const transportAssignableTasks = computed(() =>
  allTasks.value.filter((row) => row.isOpen && (row.kind === 'crate' || row.kind === 'loose')),
)

function journeyRouteForStep(step: JourneyStep) {
  return {
    name: 'ActivityPackJourney' as const,
    params: {
      departmentId: props.departmentId,
      activityId: props.activityId,
      step,
    },
  }
}

watch(
  [needsStepRedirect, resolvedStep, loading],
  ([redirect, step, isLoading]) => {
    if (isLoading || !activity.value) return
    if (!redirect) return
    void router.replace(journeyRouteForStep(step))
  },
  { immediate: true },
)

function onStepChange(step: JourneyStep): void {
  void router.push(journeyRouteForStep(step))
}

function goBackToActivity(): void {
  void router.push({
    name: 'ActivityDetail',
    params: {
      departmentId: props.departmentId,
      activityId: props.activityId,
    },
    query: { tab: 'packs' },
  })
}
</script>

<template>
  <div class="activity-material-journey-view">
    <header class="material-journey-header">
      <EButton variant="secondary" size="small" class="material-journey-header__back" @click="goBackToActivity">
        <v-icon icon="mdi-arrow-left" start size="20" />
        {{ t('activities.detail.backToList') }}
      </EButton>
      <div v-if="activity" class="material-journey-header__title">
        <h1 class="material-journey-header__name">{{ activity.name }}</h1>
        <span class="material-journey-header__status status-label" :class="activityStatusCss">
          {{ activityStatusLabel }}
        </span>
      </div>
      <span v-if="canManageMaterials" class="material-journey-header__beta">Beta</span>
    </header>

    <ELoadingState
      v-if="loading"
      variant="page"
      class="material-journey-loading"
      :message="t('activities.materialJourney.loading')"
    />

    <div v-else-if="error" class="material-journey-error section-card">
      <p>{{ error }}</p>
      <EButton variant="primary" size="small" @click="reload">{{ t('common.retry') }}</EButton>
    </div>

    <template v-else-if="activity">
      <MaterialJourneyStepper
        :steps="steps"
        :current-step="resolvedStep"
        :profile="profile"
        @update:current-step="onStepChange"
      />

      <p v-if="isFutureStep" class="material-journey-readonly-banner section-card text-muted">
        {{ t('activities.materialJourney.readonlyFutureStep') }}
      </p>

      <MaterialJourneyJsBanner
        v-if="showJsBanner"
        :department-id="departmentId"
        :activity-id="activityId"
        :summary="jsSummary"
      />

      <MaterialJourneyTransportTours
        v-if="showTransportTours && !isEarlyPackPreview"
        :activity-id="activityId"
        :department-id="departmentId"
        :journey-step="resolvedStep"
        :list-editable="listEditable"
        :assignable-tasks="transportAssignableTasks"
      />

      <div v-if="!isEarlyPackPreview" class="material-journey-scan-wrap">
        <MaterialJourneyScanBar
          v-model="scanQuery"
          :loading="scanResolving"
          :session-log="scanSessionLog"
          @submit="onScanSubmit"
          @clear="onScanClear"
        />

        <MaterialScanResultCard
          v-if="scanResult"
          :result="scanResult"
          :message="messageForResult(scanResult)"
          :primary-label="primaryActionLabel(scanResult)"
          :primary-enabled="primaryActionEnabled(scanResult)"
          :show-bulk-confirm="Boolean(scanResult.needsBulkConfirm)"
          :bulk-confirmed="scanBulkConfirmed"
          @primary="onScanPrimary"
          @confirm-bulk="confirmBulkBatch()"
          @dismiss="dismissResult()"
        />
      </div>

      <MaterialJourneyToolbar
        v-if="!isEarlyPackPreview"
        v-model:filter-tab="filterTab"
        :done-count="progress.done"
        :total-count="progress.total"
        :show-by-shelf-filter="showByShelfFilter"
      />

      <MaterialJourneyTaskList
        :tasks="displayedTasks"
        :regal-groups="displayedRegalGroups"
        :filter-tab="filterTab"
        :is-early-pack-preview="isEarlyPackPreview"
        :position-count="positionCount"
        :list-editable="listEditable"
        :moving-id="movingId"
        @activate="activateTaskRow"
      />

      <MaterialCrateCheckSheet
        v-model:open="crateSheetOpen"
        :container="activeCrate"
        :shell-pack-item="activeCrateShellPackItem"
        :container-items-by-container-id="containerItemsByContainerId"
        :journey-step="resolvedStep"
        :pack-stage="packStage"
        :activity-id="activityId"
        :can-submit="listEditable"
        :issueable-units="activeCrateIssueableUnits"
        @completed="onCrateSheetCompleted"
      />

      <MaterialComboCheckSheet
        v-model:open="comboSheetOpen"
        :pack-item="activeCombo"
        :pack-containers="packContainers"
        :container-items-by-container-id="containerItemsByContainerId"
        :journey-step="resolvedStep"
        :pack-stage="packStage"
        :activity-id="activityId"
        :can-submit="listEditable"
        :max-forward-qty="activeComboMaxForwardQty"
        @completed="onComboSheetCompleted"
      />

      <MaterialReturnCrateSheet
        v-model:open="returnCrateOpen"
        :container-label="returnCrateContainer?.label ?? ''"
        :partition="returnCratePartition"
        :lines="returnCrateLines"
        :submitting="returnCrateSubmitting"
        :submit-disabled="returnCrateSubmitDisabled"
        @update:lines="onReturnCrateLinesUpdate"
        @submit="submitReturnCrate()"
      />

      <MaterialStoreShelveSheet
        v-model:open="storeShelveOpen"
        v-model:qty="storeShelveQty"
        :pack-item="activeStoreItem"
        :max-qty="activeStoreMaxQty"
        :department-id="departmentId"
        :submitting="storeShelveSubmitting"
        :feedback-visible="storeShelveFeedback"
        @confirm="submitStoreShelve()"
        @next="onStoreShelveNext()"
        @stay="onStoreShelveStay()"
      />

      <MaterialJourneyStepFooter
        v-if="!isEarlyPackPreview"
        :journey-step="resolvedStep"
        :done-count="progress.done"
        :total-count="progress.total"
        :open-count="progress.open"
      />

      <MaterialJourneyLegacyLink
        :department-id="departmentId"
        :activity-id="activityId"
        :show-legacy-link="canManageMaterials"
      />
    </template>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
