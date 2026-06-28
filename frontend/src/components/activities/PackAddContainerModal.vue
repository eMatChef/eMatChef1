<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import { ESelect } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getPublicMaterialBatchByCodes } from '@/api/public/publicLookup'
import { localizedBarcodeScannerError } from '@/utils/barcodeScannerErrors'
import { parseScanInput } from '@/utils/scanParser'

export type StockBatchOption = {
  id: string
  label: string
}

const ADD_COUNTDOWN_SEC = 3

const props = defineProps<{
  open: boolean
  loading: boolean
  batches: StockBatchOption[]
  selectedBatchId: string
  canSubmit: boolean
  submitting: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: []
  'update:selectedBatchId': [value: string]
}>()

const { t } = useI18n()

const scannerOpen = ref(false)
const scanResolving = ref(false)
const scanAddedLabel = ref<string | null>(null)
const scanErrorMessage = ref<string | null>(null)
const addCountdown = ref(0)

let countdownTimer: ReturnType<typeof setInterval> | null = null
let lastScanHandledAt = 0
let lastScanRaw = ''

const showScanButton = computed(
  () => addCountdown.value <= 0 && !scanResolving.value,
)

function clearScanError(): void {
  scanErrorMessage.value = null
}

function setScanError(message: string): void {
  scanErrorMessage.value = message
}

function clearAddCountdown(): void {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
  addCountdown.value = 0
}

function resetScanAddState(): void {
  clearAddCountdown()
  scanAddedLabel.value = null
}

function resetScanState(): void {
  resetScanAddState()
  clearScanError()
  lastScanRaw = ''
  lastScanHandledAt = 0
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      scannerOpen.value = false
      resetScanState()
    }
  },
)

watch(
  () => props.submitting,
  (isSubmitting) => {
    if (isSubmitting) resetScanAddState()
  },
)

onUnmounted(() => {
  clearAddCountdown()
})

function toggleScanner(): void {
  if (props.loading || props.submitting || scanResolving.value || addCountdown.value > 0) return
  clearScanError()
  scannerOpen.value = !scannerOpen.value
}

function onSelectedBatchChange(value: string): void {
  resetScanAddState()
  clearScanError()
  emit('update:selectedBatchId', value)
}

function startAddCountdown(label: string): void {
  clearAddCountdown()
  clearScanError()
  scanAddedLabel.value = label
  scannerOpen.value = false
  addCountdown.value = ADD_COUNTDOWN_SEC
  countdownTimer = setInterval(() => {
    addCountdown.value -= 1
    if (addCountdown.value <= 0) {
      clearAddCountdown()
      emit('submit')
    }
  }, 1000)
}

function submitAddNow(): void {
  if (addCountdown.value <= 0) return
  clearAddCountdown()
  emit('submit')
}

function cancelScanAdd(): void {
  resetScanAddState()
}

async function applyScannedBatch(raw: string): Promise<void> {
  const trimmed = raw.trim()
  if (!trimmed || props.loading || props.submitting || addCountdown.value > 0) return

  const now = Date.now()
  if (scanResolving.value) return
  if (trimmed === lastScanRaw && now - lastScanHandledAt < 2500) return

  scanResolving.value = true
  scannerOpen.value = false
  clearScanError()
  lastScanRaw = trimmed
  lastScanHandledAt = now

  try {
    const parsed = parseScanInput(trimmed)
    if (parsed.type !== 'material_batch') {
      setScanError(t('activities.packList.modalScanInvalid'))
      return
    }

    const lookup = await getPublicMaterialBatchByCodes(parsed.materialCode, parsed.batchCode)
    if (!lookup.batch.is_container && !lookup.material.is_container) {
      setScanError(t('activities.packList.modalScanNotContainer'))
      return
    }

    const batchId = lookup.batch.id
    const match = props.batches.find((b) => b.id === batchId)
    if (!match) {
      setScanError(t('activities.packList.modalScanNotAvailable'))
      return
    }

    emit('update:selectedBatchId', batchId)
    startAddCountdown(match.label)
  } catch (e) {
    setScanError(e instanceof Error ? e.message : String(e))
  } finally {
    scanResolving.value = false
  }
}

function onScanDetected(payload: { text: string; format: string }): void {
  void applyScannedBatch(payload.text)
}

function onScanError(message: string): void {
  if (scanErrorMessage.value === message) return
  setScanError(localizedBarcodeScannerError(message, t))
}

const primaryButtonLabel = computed(() => {
  if (addCountdown.value > 0) {
    return t('activities.packList.modalScanAddButton', { seconds: addCountdown.value })
  }
  return t('common.add')
})

function onPrimaryClick(): void {
  if (addCountdown.value > 0) {
    submitAddNow()
    return
  }
  emit('submit')
}
</script>

<template>
  <PackWorkflowModal :open="open" size="md" @cancel="emit('cancel')">
    <template #title>{{ t('activities.packList.modalAddTitle') }}</template>
    <template #intro>
      <p class="pack-modal-hint pack-modal-hint--sm text-muted" v-html="t('activities.packList.modalAddHint')"></p>
    </template>

    <ELoadingState
      v-if="loading"
      variant="inline"
      class="pack-modal-loading"
      :message="t('activities.packList.modalLoadingBatches')"
    />
    <template v-else>
      <div v-if="batches.length > 0 && addCountdown <= 0" class="pack-add-container-field">
        <button
          v-if="showScanButton"
          type="button"
          class="pack-add-container-scan-btn"
          :aria-label="t('activities.materialJourney.scan.openCamera')"
          :aria-pressed="scannerOpen"
          :disabled="submitting"
          @click="toggleScanner"
        >
          <v-icon icon="mdi-barcode-scan" size="22" />
        </button>
        <ESelect
          :model-value="selectedBatchId"
          :items="batches"
          item-title="label"
          item-value="id"
          :label="t('activities.packList.modalBatchLabel')"
          :placeholder="t('activities.packList.modalSelectPlaceholder')"
          :disabled="submitting || scanResolving || addCountdown > 0"
          clearable
          hide-details
          class="pack-add-container-select"
          @update:model-value="onSelectedBatchChange($event ?? '')"
        />
      </div>
      <p v-else class="pack-modal-empty text-muted">{{ t('activities.packList.modalNoBatch') }}</p>

      <div
        v-if="scanErrorMessage"
        class="pack-add-container-scan-error"
        role="alert"
      >
        {{ scanErrorMessage }}
      </div>

      <div
        v-if="scanAddedLabel && addCountdown > 0"
        class="pack-add-container-scan-banner"
        role="status"
        aria-live="polite"
      >
        <v-icon icon="mdi-barcode-scan" size="20" class="pack-add-container-scan-banner__icon" />
        <div class="pack-add-container-scan-banner__text">
          <strong>{{ t('activities.packList.modalScanAddTitle') }}</strong>
          <span>{{
            t('activities.packList.modalScanAddBanner', {
              label: scanAddedLabel,
              seconds: addCountdown,
            })
          }}</span>
        </div>
        <button type="button" class="pack-add-container-scan-banner__now" @click="submitAddNow">
          {{ t('activities.packList.modalScanAddNow') }}
        </button>
        <button type="button" class="pack-add-container-scan-banner__cancel" @click="cancelScanAdd">
          {{ t('activities.packList.modalScanAddCancel') }}
        </button>
      </div>

      <BarcodeScannerPanel
        v-if="scannerOpen && batches.length > 0"
        class="pack-add-container-scanner"
        :active="open && scannerOpen"
        mode="all"
        :hint="t('activities.packList.modalScanHint')"
        @detected="onScanDetected"
        @error="onScanError"
      />
    </template>

    <template #footer>
      <PackModalFooter
        :primary-label="primaryButtonLabel"
        :primary-disabled="submitting || loading || scanResolving || !canSubmit"
        :cancel-disabled="submitting || scanResolving"
        @cancel="emit('cancel')"
        @primary="onPrimaryClick"
      />
    </template>
  </PackWorkflowModal>
</template>

<style scoped>
.pack-add-container-field {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  width: 100%;
  margin-bottom: 8px;
}

.pack-add-container-select {
  flex: 1 1 auto;
  min-width: 0;
  width: 100%;
  margin-bottom: 0;
}

.pack-add-container-select :deep(.e-form-field),
.pack-add-container-select :deep(.autosave-control),
.pack-add-container-select :deep(.autosave-field-frame),
.pack-add-container-select :deep(.v-input) {
  width: 100%;
}

.pack-add-container-select :deep(.v-select__selection-text) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pack-add-container-scan-btn {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  margin-top: 2px;
  padding: 0;
  border: 0;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
}

.pack-add-container-scan-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pack-add-container-scan-error {
  margin: 8px 0 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  font-size: 13px;
  line-height: 1.4;
}

.pack-add-container-scanner {
  margin-top: 4px;
}

.pack-add-container-scan-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #065f46;
  font-size: 13px;
}

.pack-add-container-scan-banner__icon {
  flex: 0 0 auto;
  color: #059669;
}

.pack-add-container-scan-banner__text {
  flex: 1 1 12rem;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.pack-add-container-scan-banner__text strong {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.pack-add-container-scan-banner__now,
.pack-add-container-scan-banner__cancel {
  flex: 0 0 auto;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.pack-add-container-scan-banner__now {
  border: 0;
  background: #059669;
  color: #fff;
}

.pack-add-container-scan-banner__cancel {
  border: 1px solid #6ee7b7;
  background: transparent;
  color: #047857;
}
</style>
