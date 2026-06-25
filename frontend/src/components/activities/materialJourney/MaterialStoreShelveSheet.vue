<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { getStorageOverview, type StorageOverviewRack } from '@/api/storageLocations'
import { packRackLabel } from '@/components/activities/packMaterialDisplay'
import EButton from '@/components/form/base/EButton.vue'
import { useMaterialJourneySheetDialog } from '@/composables/useMaterialJourneySheetDialog'

const props = defineProps<{
  open: boolean
  packItem: ActivityPackItem | null
  maxQty: number
  qty: number
  departmentId: string
  submitting: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:qty': [value: number]
  confirm: []
}>()

const { t } = useI18n()
const { sheetFullscreen, sheetMaxWidth } = useMaterialJourneySheetDialog({ maxWidth: 480 })

const racksLoading = ref(false)
const racks = ref<StorageOverviewRack[]>([])
const selectedRackId = ref('')
const selectedSlotId = ref('')

const slotsForRack = computed(() => {
  const rack = racks.value.find((r) => r.id === selectedRackId.value)
  return rack?.slots ?? []
})

const suggestedLocation = computed(() => {
  const pi = props.packItem
  if (!pi) return ''
  const parts: string[] = []
  const rack = packRackLabel(pi)
  if (rack) parts.push(rack)
  if (pi.storageSlotName?.trim()) parts.push(pi.storageSlotName.trim())
  return parts.join(' · ')
})

const selectedLocationLabel = computed(() => {
  const rack = racks.value.find((r) => r.id === selectedRackId.value)
  const slot = slotsForRack.value.find((s) => String(s.id) === selectedSlotId.value)
  if (!rack) return suggestedLocation.value || t('activities.materialJourney.storeSheet.noLocation')
  if (!slot?.name) return rack.name
  return `${rack.name} · ${slot.name}`
})

function clampQty(raw: number): number {
  let qty = Math.floor(Number(raw)) || 1
  if (qty < 1) qty = 1
  const max = Math.floor(props.maxQty)
  if (max > 0 && qty > max) qty = max
  return qty
}

function setQty(next: number): void {
  emit('update:qty', clampQty(next))
}

function close(): void {
  emit('update:open', false)
}

async function loadRacks(): Promise<void> {
  if (!props.departmentId) return
  racksLoading.value = true
  try {
    const overview = await getStorageOverview(props.departmentId)
    racks.value = overview.racks.filter((r) => r.id)
    preselectRackAndSlot()
  } catch {
    racks.value = []
  } finally {
    racksLoading.value = false
  }
}

function preselectRackAndSlot(): void {
  const pi = props.packItem
  if (!pi || racks.value.length === 0) {
    selectedRackId.value = ''
    selectedSlotId.value = ''
    return
  }

  const rackName = packRackLabel(pi).toLowerCase()
  const slotName = pi.storageSlotName?.trim().toLowerCase() ?? ''

  const rack =
    racks.value.find((r) => r.name.trim().toLowerCase() === rackName) ??
    racks.value.find((r) => rackName && r.name.toLowerCase().includes(rackName)) ??
    racks.value[0]

  selectedRackId.value = rack?.id ?? ''
  const slot =
    rack?.slots.find((s) => s.name.trim().toLowerCase() === slotName) ??
    rack?.slots.find((s) => slotName && s.name.toLowerCase().includes(slotName)) ??
    rack?.slots[0]
  selectedSlotId.value = slot?.id ? String(slot.id) : ''
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    void loadRacks()
  },
)

watch(selectedRackId, () => {
  const first = slotsForRack.value[0]
  selectedSlotId.value = first?.id ? String(first.id) : ''
})

function onConfirm(): void {
  if (props.submitting || props.maxQty < 1) return
  emit('confirm')
}
</script>

<template>
  <v-dialog
    :model-value="open"
    :fullscreen="sheetFullscreen"
    :max-width="sheetMaxWidth"
    scrollable
    class="material-journey-sheet-dialog"
    transition="dialog-bottom-transition"
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="packItem" class="material-journey-sheet material-store-shelve-sheet">
      <header class="material-journey-sheet__header">
        <EButton variant="secondary" size="small" @click="close">
          {{ t('common.close') }}
        </EButton>
        <div class="material-journey-sheet__headline">
          <h2 class="material-journey-sheet__title">{{ packItem.materialName }}</h2>
          <p class="material-journey-sheet__subtitle text-muted">
            {{ t('activities.materialJourney.storeSheet.subtitle') }}
          </p>
        </div>
      </header>

      <div class="material-journey-sheet__body">
        <div class="material-store-shelve-sheet__field">
          <span class="material-store-shelve-sheet__label">
            {{ t('activities.materialJourney.storeSheet.suggestedLocation') }}
          </span>
          <p class="material-store-shelve-sheet__value">
            {{ suggestedLocation || t('activities.materialJourney.storeSheet.noLocation') }}
          </p>
        </div>

        <div class="material-store-shelve-sheet__field">
          <label class="material-store-shelve-sheet__label" for="store-shelve-rack">
            {{ t('activities.materialJourney.storeSheet.rackLabel') }}
          </label>
          <select
            id="store-shelve-rack"
            v-model="selectedRackId"
            class="material-store-shelve-sheet__select"
            :disabled="racksLoading || submitting"
          >
            <option v-if="!racks.length" value="">
              {{ racksLoading ? t('common.loading') : t('activities.materialJourney.storeSheet.noRacks') }}
            </option>
            <option v-for="rack in racks" :key="rack.id" :value="rack.id">
              {{ rack.name }}
            </option>
          </select>
        </div>

        <div class="material-store-shelve-sheet__field">
          <label class="material-store-shelve-sheet__label" for="store-shelve-slot">
            {{ t('activities.materialJourney.storeSheet.slotLabel') }}
          </label>
          <select
            id="store-shelve-slot"
            v-model="selectedSlotId"
            class="material-store-shelve-sheet__select"
            :disabled="!selectedRackId || submitting"
          >
            <option v-if="!slotsForRack.length" value="">
              {{ t('activities.materialJourney.storeSheet.noSlots') }}
            </option>
            <option v-for="slot in slotsForRack" :key="String(slot.id)" :value="String(slot.id)">
              {{ slot.name }}
            </option>
          </select>
        </div>

        <div class="material-store-shelve-sheet__field">
          <label class="material-store-shelve-sheet__label" for="store-shelve-qty">
            {{ t('activities.materialJourney.storeSheet.qtyLabel') }}
          </label>
          <div class="material-store-shelve-sheet__qty-row">
            <input
              id="store-shelve-qty"
              :value="qty"
              type="number"
              min="1"
              :max="maxQty"
              class="material-store-shelve-sheet__qty-input"
              :disabled="submitting"
              @input="setQty(parseInt(($event.target as HTMLInputElement).value, 10) || 1)"
            />
            <span class="text-muted">{{ t('activities.materialJourney.storeSheet.qtyMax', { max: maxQty }) }}</span>
          </div>
        </div>

        <p class="material-store-shelve-sheet__target text-muted">
          {{ t('activities.materialJourney.storeSheet.targetHint', { location: selectedLocationLabel }) }}
        </p>
        <p class="material-store-shelve-sheet__workflow-hint text-muted">
          {{ t('activities.materialJourney.storeSheet.workflowHint') }}
        </p>
      </div>

      <footer class="material-journey-sheet__footer">
        <EButton
          variant="primary"
          class="material-journey-sheet__primary"
          :disabled="submitting || maxQty < 1"
          :loading="submitting"
          @click="onConfirm"
        >
          {{ t('activities.materialJourney.storeSheet.confirm') }}
        </EButton>
      </footer>
    </div>
  </v-dialog>
</template>

<style src="@/styles/views/activities/material-journey-sheet.css"></style>
<style scoped>
.material-store-shelve-sheet__field {
  margin-bottom: 16px;
}

.material-store-shelve-sheet__label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.material-store-shelve-sheet__value {
  margin: 0;
  font-size: 15px;
}

.material-store-shelve-sheet__select,
.material-store-shelve-sheet__qty-input {
  width: 100%;
  min-height: 44px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font: inherit;
}

.material-store-shelve-sheet__qty-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.material-store-shelve-sheet__qty-input {
  max-width: 120px;
}

.material-store-shelve-sheet__target {
  margin: 0;
  font-size: 13px;
}

.material-store-shelve-sheet__workflow-hint {
  margin: 12px 0 0;
  font-size: 12px;
  line-height: 1.45;
}
</style>
