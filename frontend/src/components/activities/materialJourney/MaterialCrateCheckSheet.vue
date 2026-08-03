<script setup lang="ts">
import { computed, ref, toRef, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getActivityHistory } from '@/api/activities'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  issueAllPackContainerItems,
  returnAllPackContainerItems,
} from '@/api/activityContainers'
import { postPackCrateCheck, type PackCrateCheckRequest } from '@/api/activityPackCrateCheck'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'
import PackCrateShellForwardModal from '@/components/activities/PackCrateShellForwardModal.vue'
import PackCrateShellInlinePanel from '@/components/activities/PackCrateShellInlinePanel.vue'
import PackPhysComboStoreChecklistModal from '@/components/activities/PackPhysComboStoreChecklistModal.vue'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  shellForwardExpectedQty,
  shellForwardLineKey,
} from '@/components/activities/packCrateForwardCheck'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  isJourneyLogisticsReturnCrateCheckStep,
  isJourneyReturnStep,
  isJourneyStoreStep,
} from '@/components/activities/materialJourneySteps'
import { indexLatestCrateCheckByPackItemAndLeg } from '@/components/activities/packCrateCheckReality'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { getBackendStage, type PackStage } from '@/components/activities/packStageQuantities'
import EButton from '@/components/form/base/EButton.vue'
import { useMaterialJourneySheetDialog } from '@/composables/useMaterialJourneySheetDialog'
import { peekSectionsForJourneyContainer } from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { emptyMaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { useMaterialJourneyShellForward } from '@/composables/useMaterialJourneyShellForward'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { needsShellCratePresenceConfirm } from '@/utils/materialJourneyCrateCheckGate'

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
  profile: PackWorkflowProfile
  activityId: string
  departmentId: string
  canManageMaterials: boolean
  canSubmit: boolean
  canDelete?: boolean
  issueableUnits: number
  deleting?: boolean
  applyUpdatedItem: (item: ActivityPackItem) => void
  packMoveQtyCap?: (item: ActivityPackItem) => number
  containerLineRemainingStore?: (ci: ActivityPackContainerItem) => number
  containerInnerPendingStoreUnits?: (containerId: string) => number
  containerShellPendingStoreQty?: (containerId: string) => number
  containerShellOnlyPendingUnpack?: (containerId: string) => boolean
  focusLooseIssue?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  completed: []
  delete: []
  'store-line': [containerId: string, ci: ActivityPackContainerItem, pi: ActivityPackItem, qty: number]
  'store-shell': [containerId: string, pi: ActivityPackItem, qty: number]
}>()

const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()
const { sheetFullscreen, sheetMaxWidth } = useMaterialJourneySheetDialog()
const submitting = ref(false)
const useShellForwardModal = ref(false)
const physComboStoreOpen = ref(false)
const physComboStoreSections = ref<PackCrateShellPeekSection[]>([])
const physComboStoreSubmitting = ref(false)
const physComboStoreCheckDone = ref(false)

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

const isStoreStep = computed(() => isJourneyStoreStep(props.journeyStep))
const isPhysCombo = computed(
  () => Boolean(props.shellPackItem && isPhysicalComboPackItem(props.shellPackItem)),
)

watch(
  () =>
    [
      props.open,
      props.container?.id,
      props.shellPackItem?.id,
      props.issueableUnits,
      props.focusLooseIssue,
      props.packStage,
      props.journeyStep,
    ] as const,
  async ([isOpen, containerId, shellId, units, looseFocus]) => {
    useShellForwardModal.value = false
    physComboStoreOpen.value = false
    physComboStoreCheckDone.value = false
    if (looseFocus || !isOpen || !props.container || !props.shellPackItem || !containerId || !shellId) {
      closeShellForward()
      return
    }

    if (isStoreStep.value && isPhysCombo.value && props.canManageMaterials) {
      physComboStoreSections.value = sections.value
      let snapshots = {}
      try {
        const history = await getActivityHistory(props.activityId)
        snapshots = indexLatestCrateCheckByPackItemAndLeg(history, {
          userId: (authStore.userId ?? '').trim() || undefined,
        })
      } catch {
        snapshots = {}
      }
      const needsCheck = needsShellCratePresenceConfirm(
        props.shellPackItem,
        props.packStage,
        props.packContainers,
        snapshots,
        authStore.userId,
      )
      if (needsCheck) {
        physComboStoreOpen.value = true
      } else {
        physComboStoreCheckDone.value = true
      }
      return
    }

    if (isStoreStep.value) {
      let snapshots = {}
      try {
        const history = await getActivityHistory(props.activityId)
        snapshots = indexLatestCrateCheckByPackItemAndLeg(history, {
          userId: (authStore.userId ?? '').trim() || undefined,
        })
      } catch {
        snapshots = {}
      }
      const needsCheck = needsShellCratePresenceConfirm(
        props.shellPackItem,
        props.packStage,
        props.packContainers,
        snapshots,
        authStore.userId,
      )
      if (needsCheck) {
        useShellForwardModal.value = true
        await openForContainerShell(props.container, props.shellPackItem, Math.max(1, units))
      }
      return
    }

    let snapshots = {}
    try {
      const history = await getActivityHistory(props.activityId)
      snapshots = indexLatestCrateCheckByPackItemAndLeg(history, {
        userId: (authStore.userId ?? '').trim() || undefined,
      })
    } catch {
      snapshots = {}
    }

    const needsCheck = needsShellCratePresenceConfirm(
      props.shellPackItem,
      props.packStage,
      props.packContainers,
      snapshots,
      authStore.userId,
    )

    if (needsCheck) {
      useShellForwardModal.value = true
      await openForContainerShell(props.container, props.shellPackItem, Math.max(1, units))
      return
    }

    closeShellForward()
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

type StoreCrateLine = {
  ci: ActivityPackContainerItem
  pi: ActivityPackItem
  max: number
  name: string
}

const storeLines = computed((): StoreCrateLine[] => {
  if (!props.container || !isStoreStep.value) return []
  const lines: StoreCrateLine[] = []
  for (const ci of props.containerItemsByContainerId[props.container.id] ?? []) {
    const max = props.containerLineRemainingStore?.(ci) ?? 0
    if (max < 1) continue
    const pi = props.packItems.find((p) => p.materialItemId === ci.material_item_id)
    if (!pi) continue
    lines.push({
      ci,
      pi,
      max,
      name: (ci.material_name ?? pi.materialName ?? '').trim() || t('common.material'),
    })
  }
  return lines
})

const shellStoreQty = computed(() => {
  if (!props.container) return 0
  return props.containerShellPendingStoreQty?.(props.container.id) ?? 0
})

const showShellStore = computed(() => {
  if (!props.container || !isStoreStep.value || !props.shellPackItem) return false
  if (props.containerShellOnlyPendingUnpack) {
    return props.containerShellOnlyPendingUnpack(props.container.id)
  }
  const inner = props.containerInnerPendingStoreUnits?.(props.container.id) ?? 0
  return inner <= 0 && shellStoreQty.value > 0
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
  if (isStoreStep.value) {
    return t('activities.materialJourney.crateSheet.primaryStore')
  }
  return t('activities.materialJourney.crateSheet.primaryIssue')
})

const subtitle = computed(() => {
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.subtitleReturn')
  }
  if (isJourneyLogisticsReturnCrateCheckStep(props.journeyStep, props.profile)) {
    return t('activities.materialJourney.crateSheet.subtitleReturnCheck')
  }
  if (isStoreStep.value) {
    return t('activities.materialJourney.crateSheet.subtitleStoreLines')
  }
  return t('activities.materialJourney.crateSheet.subtitle')
})

const canPrimary = computed(
  () => !isStoreStep.value && props.canSubmit && props.issueableUnits > 0 && !submitting.value,
)

const physComboStoreLabel = computed(() => {
  if (!props.container) return ''
  return (props.container.label ?? props.shellPackItem?.materialName ?? '').trim() || t('common.material')
})

function close(): void {
  emit('update:open', false)
}

async function onPrimary(): Promise<void> {
  if (!props.container || !canPrimary.value || isStoreStep.value) return
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
  if (isStoreStep.value && shellCheckOnly.value) {
    closeShellForward()
    useShellForwardModal.value = false
    return
  }
  if (updated || !shellCheckOnly.value) {
    emit('completed')
    close()
  }
}

function onStoreLine(line: StoreCrateLine): void {
  if (!props.container || !props.canSubmit) return
  emit('store-line', props.container.id, line.ci, line.pi, line.max)
}

function onStoreShell(): void {
  if (!props.container || !props.shellPackItem || shellStoreQty.value < 1 || !props.canSubmit) return
  emit('store-shell', props.container.id, props.shellPackItem, shellStoreQty.value)
}

function closePhysComboStoreModal(): void {
  physComboStoreOpen.value = false
  close()
}

async function onPhysComboStoreConfirm(): Promise<void> {
  const container = props.container
  const shell = props.shellPackItem
  if (!container || !shell) return
  physComboStoreSubmitting.value = true
  try {
    const lines: PackCrateCheckRequest['lines'] = []
    for (const sec of physComboStoreSections.value) {
      const isExtra = sec.subsectionKey === 'extra'
      for (const line of sec.lines) {
        const qty = shellForwardExpectedQty(isExtra, line.quantity)
        lines.push({
          line_key: shellForwardLineKey(sec.subsectionKey, line.id),
          material_item_id: (line.materialItemId ?? '').trim() || null,
          material_name: line.materialName,
          expected_qty: qty,
          counted_qty: qty,
          status: 'ok',
        })
      }
    }
    const batchId = (container.container_batch_id ?? shell.linkedContainerBatchId ?? '').trim() || null
    const checkRes = await postPackCrateCheck(props.activityId, shell.id, {
      container_batch_id: batchId,
      check_leg: 'warehouse_store',
      result: 'ok',
      lines,
    })
    if (!checkRes.ok) {
      toast.error(t('activities.packList.physComboStoreCheckFailed'))
      return
    }
    physComboStoreOpen.value = false
    physComboStoreCheckDone.value = true
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.physComboStoreCheckFailed'))
  } finally {
    physComboStoreSubmitting.value = false
  }
}
</script>

<template>
  <PackPhysComboStoreChecklistModal
    v-if="physComboStoreOpen"
    :open="physComboStoreOpen"
    :label="physComboStoreLabel"
    :sections="physComboStoreSections"
    :open-issue-labels="[]"
    :submitting="physComboStoreSubmitting"
    @cancel="closePhysComboStoreModal"
    @confirm="onPhysComboStoreConfirm"
  />

  <PackCrateShellForwardModal
    v-if="useShellForwardModal"
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
    v-else-if="open && !useShellForwardModal && !physComboStoreOpen"
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
        <template v-if="isStoreStep">
          <p v-if="storeLines.length === 0 && !showShellStore" class="text-muted">
            {{ t('activities.materialJourney.crateSheet.empty') }}
          </p>
          <ul v-else class="material-journey-store-crate-lines">
            <li v-for="line in storeLines" :key="line.ci.id" class="material-journey-store-crate-line">
              <div class="material-journey-store-crate-line__copy">
                <span class="material-journey-store-crate-line__name">{{ line.name }}</span>
                <span class="material-journey-store-crate-line__qty text-muted">{{ line.max }} Stk.</span>
              </div>
              <EButton
                variant="primary"
                size="small"
                :disabled="!canSubmit"
                @click="onStoreLine(line)"
              >
                {{ t('activities.packList.storeLineTitle', { count: line.max }) }}
              </EButton>
            </li>
          </ul>
          <div v-if="showShellStore && shellPackItem" class="material-journey-store-crate-shell">
            <p class="material-journey-store-crate-shell__hint text-muted">
              {{ t('activities.materialJourney.crateSheet.shellAfterContent') }}
            </p>
            <EButton
              variant="primary"
              :disabled="!canSubmit"
              @click="onStoreShell"
            >
              {{ t('activities.materialJourney.crateSheet.primaryStoreShell', { label: container.label }) }}
            </EButton>
          </div>
        </template>
        <PackCrateShellInlinePanel
          v-else
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

      <footer v-if="!isStoreStep" class="material-journey-sheet__footer">
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
<style scoped>
.material-journey-store-crate-lines {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.material-journey-store-crate-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: #fff;
}

.material-journey-store-crate-line__copy {
  min-width: 0;
}

.material-journey-store-crate-line__name {
  display: block;
  font-weight: 600;
}

.material-journey-store-crate-shell {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px dashed var(--color-border);
}
</style>
