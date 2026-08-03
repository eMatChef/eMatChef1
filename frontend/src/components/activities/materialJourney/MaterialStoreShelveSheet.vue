<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { getStorageOverview, type StorageOverviewRack } from '@/api/storageLocations'
import { lookupStorageQr } from '@/api/storageQr'
import { packRackLabel } from '@/components/activities/packMaterialDisplay'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import EButton from '@/components/form/base/EButton.vue'
import { useMaterialJourneySheetDialog } from '@/composables/useMaterialJourneySheetDialog'
import { canRequestCamera } from '@/utils/cameraAccess'
import { localizedBarcodeScannerError } from '@/utils/barcodeScannerErrors'
import { parseScanInput } from '@/utils/scanParser'
import {
  parseStorageLookupData,
  resolveStoreLocationScanLevel,
  type StorageLookupResult,
} from '@/utils/packStorageLocationMatch'

type SheetPhase = 'scan' | 'confirm'

const SCANNER_COUNTDOWN_SEC = 5
const CONFIRM_COUNTDOWN_SEC = 5

const props = defineProps<{
  open: boolean
  packItem: ActivityPackItem | null
  maxQty: number
  qty: number
  departmentId: string
  submitting: boolean
  openedFromScan?: boolean
  /** Kisten-Label inkl. Seriennummer (statt Materialstamm-Name) */
  storeDisplayName?: string | null
  storeRackName?: string | null
  storeSlotName?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:qty': [value: number]
  confirm: []
}>()

const { t } = useI18n()
const { sheetFullscreen, sheetMaxWidth } = useMaterialJourneySheetDialog({ maxWidth: 520 })

const phase = ref<SheetPhase>('scan')
const locationScanLoading = ref(false)
const locationScanError = ref<string | null>(null)
const scannedLookup = ref<StorageLookupResult | null>(null)
const rackOnlyHint = ref(false)
const scannerCountdown = ref(SCANNER_COUNTDOWN_SEC)
const inlineScannerActive = ref(false)
const scanCooldown = ref(false)
const confirmCountdown = ref(CONFIRM_COUNTDOWN_SEC)
const racks = ref<StorageOverviewRack[]>([])

let countdownTimer: ReturnType<typeof setInterval> | null = null

const targetAddressLabel = computed(() => props.packItem?.storageAddressName?.trim() ?? '')

/** Nur echtes Regal — nicht Lagerort-Adresse als Regal-Fallback. */
const targetRackLabel = computed(() => {
  const fromContainer = props.storeRackName?.trim()
  if (fromContainer) return fromContainer
  const pi = props.packItem
  if (!pi) return ''
  return packRackLabel(pi)
})

/** Nur echtes Fach — kein Regal-/Adress-Fallback (sonst «Hauptlager» doppelt). */
const targetSlotLabel = computed(() => {
  const fromContainer = props.storeSlotName?.trim()
  if (fromContainer) return fromContainer
  return props.packItem?.storageSlotName?.trim() ?? ''
})

const materialDisplayName = computed(
  () => props.storeDisplayName?.trim() || props.packItem?.materialName || '',
)

const hasTargetLocation = computed(
  () =>
    targetAddressLabel.value.length > 0 ||
    targetRackLabel.value.length > 0 ||
    targetSlotLabel.value.length > 0,
)

/** Hero-Hauptzeile: möglichst spezifisch (Fach → Regal → Lagerort). */
const heroPrimaryLabel = computed(() => {
  if (targetSlotLabel.value) return targetSlotLabel.value
  if (targetRackLabel.value) return targetRackLabel.value
  return targetAddressLabel.value
})

/** Hero-Unterzeile: übergeordnete Ebene. */
const heroSecondaryLabel = computed(() => {
  if (targetSlotLabel.value && targetRackLabel.value) return targetRackLabel.value
  if (targetSlotLabel.value && targetAddressLabel.value) return targetAddressLabel.value
  if (targetRackLabel.value && targetAddressLabel.value) return targetAddressLabel.value
  return ''
})

const scannedLocationLabel = computed(() => scannedLookup.value?.label ?? '')

const confirmLocationLabel = computed(() => {
  if (scannedLocationLabel.value) return scannedLocationLabel.value
  const parts: string[] = []
  if (targetAddressLabel.value) parts.push(targetAddressLabel.value)
  if (targetRackLabel.value) parts.push(targetRackLabel.value)
  if (targetSlotLabel.value) parts.push(targetSlotLabel.value)
  return parts.join(' · ')
})

const manualStoreButtonLabel = computed(() => {
  const location = confirmLocationLabel.value
  return t('activities.materialJourney.storeSheet.manualStoreButton', {
    location: location || t('activities.materialJourney.storeSheet.noLocationShort'),
    count: props.qty,
  })
})

const canManualConfirm = computed(
  () => hasTargetLocation.value && props.qty >= 1 && !props.submitting,
)

function onQtyInput(event: Event): void {
  const el = event.target as HTMLInputElement
  const raw = parseInt(el.value, 10)
  let next = Number.isFinite(raw) ? raw : props.qty
  if (next < 1) next = 1
  const maxVal = Math.max(1, Math.floor(props.maxQty))
  if (next > maxVal) next = maxVal
  emit('update:qty', next)
}

function onManualConfirm(): void {
  if (!canManualConfirm.value) return
  startConfirmCountdown()
}

function clearCountdown(): void {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
}

function resetState(): void {
  clearCountdown()
  phase.value = 'scan'
  scannedLookup.value = null
  rackOnlyHint.value = false
  locationScanError.value = null
  scannerCountdown.value = SCANNER_COUNTDOWN_SEC
  inlineScannerActive.value = false
  scanCooldown.value = false
  confirmCountdown.value = CONFIRM_COUNTDOWN_SEC
}

function setLocationScanError(message: string): void {
  locationScanError.value = message
  rackOnlyHint.value = false
}

function close(): void {
  emit('update:open', false)
}

async function loadRacks(): Promise<void> {
  if (!props.departmentId) return
  try {
    const overview = await getStorageOverview(props.departmentId)
    racks.value = overview.racks.filter((r) => r.id)
  } catch {
    racks.value = []
  }
}

function activateInlineScanner(): void {
  if (!canRequestCamera()) {
    setLocationScanError(t('components.barcodeScanner.errorSecureContext'))
    return
  }
  locationScanError.value = null
  inlineScannerActive.value = true
}

function startLocationScannerCountdown(): void {
  clearCountdown()
  inlineScannerActive.value = false
  scannerCountdown.value = SCANNER_COUNTDOWN_SEC
  countdownTimer = setInterval(() => {
    scannerCountdown.value -= 1
    if (scannerCountdown.value <= 0) {
      clearCountdown()
      activateInlineScanner()
    }
  }, 1000)
}

function skipScannerCountdown(): void {
  clearCountdown()
  activateInlineScanner()
}

function startConfirmCountdown(): void {
  clearCountdown()
  inlineScannerActive.value = false
  phase.value = 'confirm'
  confirmCountdown.value = CONFIRM_COUNTDOWN_SEC
  countdownTimer = setInterval(() => {
    confirmCountdown.value -= 1
    if (confirmCountdown.value <= 0) {
      clearCountdown()
      onConfirm()
    }
  }, 1000)
}

function cancelConfirm(): void {
  clearCountdown()
  scannedLookup.value = null
  rackOnlyHint.value = false
  phase.value = 'scan'
  startLocationScannerCountdown()
}

function onInlineScanError(message: string): void {
  setLocationScanError(localizedBarcodeScannerError(message, t))
}

function onInlineScanDetected(payload: { text: string }): void {
  if (scanCooldown.value || locationScanLoading.value || props.submitting) return
  const text = payload.text.trim()
  if (!text) return

  locationScanError.value = null
  scanCooldown.value = true
  void onLocationScanSubmit(text).finally(() => {
    window.setTimeout(() => {
      scanCooldown.value = false
    }, 1200)
  })
}

async function onLocationScanSubmit(scannedText?: string): Promise<void> {
  const trimmed = scannedText?.trim() ?? ''
  if (!trimmed || !props.packItem || props.submitting) return

  locationScanLoading.value = true
  rackOnlyHint.value = false
  locationScanError.value = null

  try {
    const parsed = parseScanInput(trimmed)
    if (
      parsed.type !== 'storage_address' &&
      parsed.type !== 'storage_rack' &&
      parsed.type !== 'storage_slot'
    ) {
      setLocationScanError(t('activities.materialJourney.storeSheet.scanNotStorage'))
      return
    }

    const kind =
      parsed.type === 'storage_address' ? 'l' : parsed.type === 'storage_rack' ? 'r' : 's'
    const code =
      parsed.type === 'storage_address'
        ? parsed.locationCode
        : parsed.type === 'storage_rack'
          ? parsed.rackCode
          : parsed.slotCode

    const rawLookup = await lookupStorageQr(props.departmentId, kind, code)
    const lookup = parseStorageLookupData(rawLookup)
    if (!lookup) {
      setLocationScanError(t('activities.materialJourney.storeSheet.scanNotFound'))
      return
    }

    const level = resolveStoreLocationScanLevel(props.packItem, lookup)
    if (level === 'wrong') {
      setLocationScanError(t('activities.materialJourney.storeSheet.scanWrongLocation'))
      return
    }

    scannedLookup.value = lookup
    locationScanError.value = null

    if (level === 'rack_only') {
      rackOnlyHint.value = true
      return
    }

    startConfirmCountdown()
  } catch (e) {
    setLocationScanError(e instanceof Error ? e.message : String(e))
  } finally {
    locationScanLoading.value = false
  }
}

function onConfirm(): void {
  if (props.submitting || props.qty < 1) return
  clearCountdown()
  emit('confirm')
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      resetState()
      return
    }
    resetState()
    void loadRacks()
    void nextTick(() => startLocationScannerCountdown())
  },
)

onUnmounted(() => {
  clearCountdown()
})
</script>

<template>
  <v-dialog
    :model-value="open"
    :fullscreen="sheetFullscreen"
    :max-width="sheetMaxWidth"
    class="material-journey-sheet-dialog material-store-shelve-sheet-dialog"
    transition="dialog-bottom-transition"
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="packItem" class="material-journey-sheet material-store-shelve-sheet">
      <header class="material-journey-sheet__header material-store-shelve-sheet__header">
        <EButton variant="secondary" size="small" @click="close">
          {{ t('common.close') }}
        </EButton>
        <div class="material-journey-sheet__headline">
          <h2 v-if="phase !== 'scan'" class="material-journey-sheet__title">{{ materialDisplayName }}</h2>
          <p v-if="phase === 'scan'" class="material-journey-sheet__subtitle text-muted">
            {{ t('activities.materialJourney.storeSheet.scanPhaseSubtitle') }}
          </p>
        </div>
      </header>

      <div class="material-journey-sheet__body">
        <div
          v-if="hasTargetLocation && phase !== 'scan'"
          class="material-store-shelve-sheet__target-hero"
          :class="{ 'material-store-shelve-sheet__target-hero--compact': phase === 'confirm' }"
        >
          <span class="material-store-shelve-sheet__target-kicker">
            {{ t('activities.materialJourney.storeSheet.bringTo') }}
          </span>
          <p
            v-if="heroSecondaryLabel"
            class="material-store-shelve-sheet__target-rack"
          >
            {{ heroSecondaryLabel }}
          </p>
          <p class="material-store-shelve-sheet__target-slot">
            {{ heroPrimaryLabel }}
          </p>
        </div>
        <div v-else-if="!hasTargetLocation && phase !== 'scan'" class="material-store-shelve-sheet__no-target text-muted">
          {{ t('activities.materialJourney.storeSheet.noLocation') }}
        </div>

        <template v-if="phase === 'scan'">
          <div class="material-store-shelve-sheet__scan-phase">
            <section class="material-store-shelve-sheet__scan-context" aria-label="Einlager-Kontext">
            <div class="material-store-shelve-sheet__scan-context-row">
              <span class="material-store-shelve-sheet__scan-context-label">
                {{ t('common.material') }}
              </span>
              <span class="material-store-shelve-sheet__scan-context-value material-store-shelve-sheet__scan-context-value--article">
                {{ materialDisplayName }}
              </span>
            </div>
            <div class="material-store-shelve-sheet__scan-context-row material-store-shelve-sheet__scan-context-row--qty">
              <span class="material-store-shelve-sheet__scan-context-label">
                {{ t('activities.materialJourney.storeSheet.qtyLabel') }}
              </span>
              <div class="material-store-shelve-sheet__qty-wrap">
                <input
                  :value="qty"
                  type="number"
                  min="1"
                  :max="maxQty"
                  class="material-store-shelve-sheet__qty-input"
                  :disabled="submitting"
                  inputmode="numeric"
                  @input="onQtyInput"
                />
                <span class="material-store-shelve-sheet__qty-unit">
                  {{ t('activities.materialJourney.storeSheet.qtyUnit') }}
                </span>
              </div>
            </div>
            <div
              v-if="targetAddressLabel"
              class="material-store-shelve-sheet__scan-context-row"
            >
              <span class="material-store-shelve-sheet__scan-context-label">
                {{ t('activities.materialJourney.storeSheet.addressShort') }}
              </span>
              <span class="material-store-shelve-sheet__scan-context-value">
                {{ targetAddressLabel }}
              </span>
            </div>
            <div
              v-if="targetRackLabel"
              class="material-store-shelve-sheet__scan-context-row"
            >
              <span class="material-store-shelve-sheet__scan-context-label">
                {{ t('activities.materialJourney.storeSheet.rackShort') }}
              </span>
              <span class="material-store-shelve-sheet__scan-context-value">
                {{ targetRackLabel }}
              </span>
            </div>
            <div
              v-if="targetSlotLabel || targetRackLabel"
              class="material-store-shelve-sheet__scan-context-row material-store-shelve-sheet__scan-context-row--slot"
            >
              <span class="material-store-shelve-sheet__scan-context-label">
                {{ t('activities.materialJourney.storeSheet.slotShort') }}
              </span>
              <span class="material-store-shelve-sheet__scan-context-value material-store-shelve-sheet__scan-context-value--slot">
                {{ targetSlotLabel || '–' }}
              </span>
            </div>
            <div
              v-else-if="!targetAddressLabel && !targetRackLabel && !targetSlotLabel"
              class="material-store-shelve-sheet__scan-context-row material-store-shelve-sheet__scan-context-row--slot"
            >
              <span class="material-store-shelve-sheet__scan-context-label">
                {{ t('activities.materialJourney.storeSheet.slotShort') }}
              </span>
              <span class="material-store-shelve-sheet__scan-context-value material-store-shelve-sheet__scan-context-value--slot">
                {{ t('activities.materialJourney.storeSheet.noLocationShort') }}
              </span>
            </div>
          </section>

          <section class="material-store-shelve-sheet__location-scan" aria-label="Standort scannen">
            <p class="material-store-shelve-sheet__location-scan-label">
              {{ t('activities.materialJourney.storeSheet.scanLocationLabel') }}
            </p>

            <p v-if="inlineScannerActive" class="material-store-shelve-sheet__scan-hint">
              {{ t('activities.materialJourney.scan.cameraHint') }}
            </p>

            <p
              v-if="locationScanError"
              class="material-store-shelve-sheet__scan-error"
              role="alert"
            >
              {{ locationScanError }}
            </p>

            <p v-else-if="rackOnlyHint" class="material-store-shelve-sheet__rack-only-hint">
              {{
                t('activities.materialJourney.storeSheet.rackOnlyHint', {
                  rack: scannedLocationLabel || targetRackLabel,
                  slot: targetSlotLabel,
                })
              }}
            </p>

            <div
              v-if="!inlineScannerActive"
              class="material-store-shelve-sheet__scanner-wait"
            >
              <div class="material-store-shelve-sheet__scanner-wait-icon" aria-hidden="true">
                <v-icon icon="mdi-barcode-scan" size="28" />
              </div>
              <p class="material-store-shelve-sheet__scanner-wait-text">
                {{ t('activities.materialJourney.storeSheet.scanCountdown', { seconds: scannerCountdown }) }}
              </p>
              <EButton variant="primary" size="small" @click="skipScannerCountdown">
                {{ t('activities.materialJourney.storeSheet.scanCountdownNow') }}
              </EButton>
            </div>

            <BarcodeScannerPanel
              v-else
              class="material-store-shelve-sheet__inline-scanner"
              :active="phase === 'scan' && inlineScannerActive"
              mode="all"
              :hint="''"
              @detected="onInlineScanDetected"
              @error="onInlineScanError"
            />
          </section>
          </div>
        </template>

        <div v-else-if="phase === 'confirm'" class="material-store-shelve-sheet__confirm-block">
          <p class="material-store-shelve-sheet__confirm-ok">
            {{ t('activities.materialJourney.storeSheet.scanMatched', { location: confirmLocationLabel }) }}
          </p>
        </div>
      </div>

      <footer v-if="phase === 'scan' && hasTargetLocation" class="material-journey-sheet__footer">
        <EButton
          variant="primary"
          class="material-store-shelve-sheet__manual-primary"
          :disabled="!canManualConfirm"
          :loading="submitting"
          @click="onManualConfirm"
        >
          {{ manualStoreButtonLabel }}
        </EButton>
      </footer>

      <footer v-if="phase === 'confirm'" class="material-journey-sheet__footer">
        <div class="material-store-shelve-sheet__confirm-actions">
          <EButton
            variant="primary"
            class="material-store-shelve-sheet__confirm-primary"
            :loading="submitting"
            @click="onConfirm"
          >
            {{
              t('activities.materialJourney.storeSheet.confirmHere', {
                count: qty,
                seconds: confirmCountdown,
              })
            }}
          </EButton>
          <EButton variant="secondary" :disabled="submitting" @click="cancelConfirm">
            {{ t('common.cancel') }}
          </EButton>
        </div>
      </footer>
    </div>
  </v-dialog>
</template>

<style src="@/styles/views/activities/material-journey-sheet.css"></style>
<style scoped>
.material-store-shelve-sheet {
  display: flex;
  flex-direction: column;
  max-height: 100dvh;
  overflow: hidden;
}

.material-store-shelve-sheet .material-journey-sheet__body {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
}

.material-store-shelve-sheet__scan-phase {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.material-store-shelve-sheet__header {
  flex: 0 0 auto;
  border-bottom: none;
  padding: 8px 12px 4px;
}

.material-store-shelve-sheet__header .material-journey-sheet__subtitle {
  margin-top: 2px;
  font-size: 0.8125rem;
  line-height: 1.3;
}

.material-store-shelve-sheet__target-hero {
  margin-bottom: 20px;
  padding: 20px 16px;
  text-align: center;
  border-radius: 12px;
  border: 1px solid var(--color-primary-muted-border);
  background: var(--color-primary-muted-bg);
}

.material-store-shelve-sheet__target-hero--compact {
  margin-bottom: 12px;
  padding: 12px;
}

.material-store-shelve-sheet__target-kicker {
  display: block;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-primary-dark);
}

.material-store-shelve-sheet__target-rack {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text);
}

.material-store-shelve-sheet__target-slot {
  margin: 6px 0 0;
  font-size: clamp(2rem, 8vw, 2.75rem);
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.02em;
  color: var(--color-primary-dark);
}

.material-store-shelve-sheet__no-target {
  margin-bottom: 16px;
  font-size: 14px;
}

.material-store-shelve-sheet__rack-only-hint {
  flex: 0 0 auto;
  margin: 0;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid var(--color-primary-muted-border);
  background: var(--color-primary-muted-bg);
  font-size: 13px;
  line-height: 1.35;
  color: var(--color-primary-dark);
}

.material-store-shelve-sheet__scan-error {
  flex: 0 0 auto;
  margin: 0;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  font-size: 13px;
  line-height: 1.35;
  color: #b91c1c;
}

.material-store-shelve-sheet__location-scan {
  flex: 1 1 auto;
  min-height: 0;
  margin-top: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.material-store-shelve-sheet__location-scan-label {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-muted);
  flex: 0 0 auto;
}

.material-store-shelve-sheet__scan-hint {
  margin: 0;
  font-size: 12px;
  line-height: 1.35;
  color: var(--color-text-muted);
  text-align: center;
  flex: 0 0 auto;
}

.material-store-shelve-sheet__scanner-wait {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border: 2px solid var(--color-primary-muted-border);
  border-radius: 12px;
  background: #fff;
  text-align: center;
}

.material-store-shelve-sheet__scanner-wait-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: var(--color-primary-muted-bg);
  color: var(--color-primary);
}

.material-store-shelve-sheet__scanner-wait-text {
  margin: 0;
  font-size: 14px;
  color: var(--color-text-muted);
}

.material-store-shelve-sheet__inline-scanner {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.material-store-shelve-sheet__inline-scanner :deep(.scanner-panel) {
  flex: 1 1 auto;
  min-height: 0;
  margin-top: 0;
  padding: 6px;
  border: 2px solid var(--color-primary-muted-border);
  border-radius: 12px;
  background: #fff;
  display: flex;
  flex-direction: column;
}

.material-store-shelve-sheet__inline-scanner :deep(.hint) {
  display: none;
}

.material-store-shelve-sheet__inline-scanner :deep(.scanner-video) {
  flex: 1 1 auto;
  min-height: 80px;
  max-width: none;
  width: 100%;
  height: 100%;
  aspect-ratio: unset;
  object-fit: cover;
  border-radius: 8px;
  background: #111827;
}

.material-store-shelve-sheet__scan-context {
  flex: 0 0 auto;
  margin-bottom: 0;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--color-primary-muted-border);
  background: var(--color-primary-muted-bg);
}

.material-store-shelve-sheet__scan-context-row {
  display: grid;
  grid-template-columns: 4.75rem 1fr;
  align-items: baseline;
  gap: 6px 10px;
  padding: 4px 0;
}

.material-store-shelve-sheet__scan-context-row + .material-store-shelve-sheet__scan-context-row {
  border-top: 1px solid color-mix(in srgb, var(--color-primary-muted-border) 65%, transparent);
}

.material-store-shelve-sheet__scan-context-row--slot {
  align-items: center;
  padding-top: 4px;
}

.material-store-shelve-sheet__scan-context-label {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--color-primary-dark);
}

.material-store-shelve-sheet__scan-context-value {
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--color-text);
  min-width: 0;
}

.material-store-shelve-sheet__scan-context-value--article {
  font-size: 1.15rem;
}

.material-store-shelve-sheet__scan-context-value--slot {
  font-size: clamp(1.35rem, 5vw, 1.85rem);
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.02em;
  color: var(--color-primary-dark);
}

.material-store-shelve-sheet__scan-context-row--qty {
  align-items: center;
}

.material-store-shelve-sheet__qty-wrap {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}

.material-store-shelve-sheet__qty-input {
  width: 3.75rem;
  min-height: 40px;
  padding: 4px 8px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: #fff;
  font-size: clamp(1.35rem, 5vw, 1.75rem);
  font-weight: 800;
  line-height: 1.1;
  color: var(--color-primary-dark);
  text-align: center;
  -moz-appearance: textfield;
}

.material-store-shelve-sheet__qty-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px var(--color-primary-ring);
}

.material-store-shelve-sheet__qty-input:disabled {
  opacity: 0.65;
}

.material-store-shelve-sheet__qty-input::-webkit-outer-spin-button,
.material-store-shelve-sheet__qty-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.material-store-shelve-sheet__qty-unit {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.material-store-shelve-sheet__confirm-block {
  margin-top: 8px;
}

.material-store-shelve-sheet__confirm-ok {
  margin: 0;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--color-primary-muted-bg);
  border: 1px solid var(--color-primary-muted-border);
  font-size: 15px;
  color: var(--color-primary-dark);
  text-align: center;
}

.material-store-shelve-sheet__confirm-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.material-store-shelve-sheet__confirm-primary,
.material-store-shelve-sheet__manual-primary {
  width: 100%;
  min-height: 44px;
  font-size: 0.9375rem;
  line-height: 1.25;
  white-space: normal;
  text-align: center;
}

.material-store-shelve-sheet .material-journey-sheet__footer {
  flex: 0 0 auto;
  padding: 8px 12px calc(8px + env(safe-area-inset-bottom, 0px));
}
</style>

<style>
.material-journey-sheet-dialog.material-store-shelve-sheet-dialog:not(.v-dialog--fullscreen) .material-store-shelve-sheet {
  max-height: min(92dvh, 640px);
}

.material-journey-sheet-dialog.material-store-shelve-sheet-dialog.v-dialog--fullscreen .material-store-shelve-sheet {
  max-height: 100dvh;
}
</style>
