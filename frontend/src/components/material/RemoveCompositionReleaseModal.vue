<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="520"
    :title="t('components.removeCompositionRelease.title')"
    scrollable
    persistent
    card-class="move-quantity-modal-card"
  >
    <div class="batch-modal-body batch-modal-body--dialog">
          <p class="move-intro">
            {{
              t('components.removeCompositionRelease.intro', {
                name: componentName,
                qty,
                source: sourceContainerLabel,
              })
            }}
          </p>

          <div v-if="suggestionsLoading" class="composition-stock-preview">
            <p class="batch-field-hint">{{ t('components.removeCompositionRelease.suggestionsLoading') }}</p>
          </div>
          <div v-else-if="locationSuggestions.length > 0" class="composition-stock-preview">
            <p class="composition-stock-preview-title">{{ t('components.removeCompositionRelease.suggestionsTitle') }}</p>
            <div class="composition-suggestion-chips">
              <button
                v-for="s in locationSuggestions"
                :key="s.id"
                type="button"
                class="composition-suggestion-chip"
                :class="{ active: activeSuggestionId === s.id }"
                :title="s.hint"
                @click="applySuggestion(s)"
              >
                <span class="composition-suggestion-chip-label">{{ s.label }}</span>
                <span class="composition-suggestion-chip-hint">{{ s.hint }}</span>
              </button>
            </div>
          </div>

          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>{{ t('components.moveQuantityModal.labelTargetType') }}</label>
              <div class="move-target-mode">
                <button
                  type="button"
                  class="move-target-btn"
                  :class="{ active: form.target_mode === 'slot' }"
                  @click="setTargetMode('slot')"
                >
                  {{ t('components.moveQuantityModal.targetSlot') }}
                </button>
                <button
                  type="button"
                  class="move-target-btn"
                  :class="{ active: form.target_mode === 'kiste' }"
                  @click="setTargetMode('kiste')"
                >
                  {{ t('components.moveQuantityModal.targetContainer') }}
                </button>
              </div>
            </div>
          </div>

          <template v-if="form.target_mode === 'slot'">
            <div class="batch-form-row">
              <div class="batch-form-group full-width">
                <StorageLocationPicker
                  :storage-address-id="form.to_location_id"
                  :storage-address-options="targetLocationOptions"
                  :rack-id="form.to_rack_id"
                  :slot-id="form.to_slot_id"
                  :racks="targetRacks"
                  :slot-list="slotsForTargetRack"
                  :show-storage-address="true"
                  :show-empty-slot-hint="true"
                  :empty-slot-hint="t('components.moveQuantityModal.emptySlotHint')"
                  :storage-address-label="t('components.moveQuantityModal.labelTargetLocation')"
                  :rack-label="t('components.moveQuantityModal.labelTargetRack')"
                  :slot-label="t('components.moveQuantityModal.labelTargetSlot')"
                  :storage-address-placeholder="t('components.moveQuantityModal.selectPlaceholder')"
                  :rack-placeholder="t('components.moveQuantityModal.selectPlaceholder')"
                  :slot-placeholder="t('components.moveQuantityModal.selectPlaceholder')"
                  @update:storageAddressId="form.to_location_id = $event"
                  @storageAddressChange="onLocationChange"
                  @update:rackId="form.to_rack_id = $event"
                  @rackChange="onRackChange"
                  @update:slotId="form.to_slot_id = $event"
                />
              </div>
            </div>
          </template>

          <div v-else class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>{{ t('components.moveQuantityModal.labelTargetBin') }}</label>
              <select v-model="form.to_container_batch_id" class="batch-form-input" required>
                <option value="">{{ t('components.moveQuantityModal.selectPlaceholder') }}</option>
                <option
                  v-for="cb in targetContainerBatches"
                  :key="cb.id"
                  :value="cb.id"
                  :title="formatContainerBatchOptionFullLabel(cb)"
                >
                  {{ formatContainerBatchOptionFullLabel(cb) }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="errorMsg" class="batch-error">{{ errorMsg }}</div>
    </div>

    <template #actions>
      <EButton variant="secondary" size="small" @click="closeDialog">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="danger"
        size="small"
        :disabled="!canSubmit || submitting"
        :loading="submitting"
        @click="handleSubmit"
      >
        {{ submitting ? t('components.removeCompositionRelease.removing') : t('components.removeCompositionRelease.submit') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getContainerBatches } from '@/api/storageLocations'
import { formatContainerBatchOptionFullLabel } from '@/utils/containerBatchLabel'
import { usePhysicalComboWarningStore } from '@/stores/physicalComboWarning'
import { useStorageStructure } from '@/composables/useStorageStructure'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'
import type { DeleteComboComponentRequest } from '@/api/materials'
import { loadStorageTargetSuggestions, type StorageTargetSuggestion } from '@/utils/compositionStockLocations'
import { EButton, EDialog } from '@/components/form/base'

interface Props {
  departmentId: string
  componentMaterialId: string
  componentName: string
  qty: number
  sourceContainerBatchId: string
  sourceContainerLabel: string
  submitting?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  cancel: []
  confirm: [payload: DeleteComboComponentRequest]
}>()

const dialogOpen = ref(true)

watch(dialogOpen, (open) => {
  if (!open) emit('cancel')
})

function closeDialog() {
  dialogOpen.value = false
}

const { t } = useI18n()
const physicalComboWarningStore = usePhysicalComboWarningStore()
const { racks, slotsByRackId, loadRacks: loadStorageRacks, loadSlots } = useStorageStructure(() => props.departmentId)
const containerBatches = ref<import('@/api/storageLocations').ContainerBatch[]>([])
const locationSuggestions = ref<StorageTargetSuggestion[]>([])
const suggestionsLoading = ref(false)
const activeSuggestionId = ref<string | null>(null)
const errorMsg = ref('')

const form = reactive({
  target_mode: 'slot' as 'slot' | 'kiste',
  to_location_id: '',
  to_rack_id: '',
  to_slot_id: '',
  to_container_batch_id: '',
})

const targetLocations = computed(() => {
  const map = new Map<string, string>()
  for (const rack of racks.value) {
    const locationId = rack.storage_address_id || '__unknown__'
    const locationName = rack.storage_address_name?.trim() || t('settings.storage.overviewNoStorageAddress')
    if (!map.has(locationId)) map.set(locationId, locationName)
  }
  return Array.from(map.entries()).map(([id, name]) => ({ id, name }))
})

const targetLocationOptions = computed(() =>
  targetLocations.value.map((loc) => ({ value: loc.id, label: loc.name })),
)

const targetRacks = computed(() => {
  if (!form.to_location_id) return racks.value
  return racks.value.filter((r) => (r.storage_address_id || '__unknown__') === form.to_location_id)
})

const slotsForTargetRack = computed(() => {
  if (!form.to_rack_id) return []
  return slotsByRackId.value[form.to_rack_id] || []
})

const targetContainerBatches = computed(() =>
  containerBatches.value.filter((cb) => cb.id !== props.sourceContainerBatchId),
)

const canSubmit = computed(() => {
  if (form.target_mode === 'slot') {
    return !!(form.to_location_id && form.to_rack_id && form.to_slot_id)
  }
  return !!form.to_container_batch_id
})

function onRackChange() {
  form.to_slot_id = ''
}

function onLocationChange() {
  form.to_rack_id = ''
  form.to_slot_id = ''
}

function setTargetMode(mode: 'slot' | 'kiste') {
  form.target_mode = mode
  form.to_container_batch_id = ''
  form.to_location_id = ''
  form.to_rack_id = ''
  form.to_slot_id = ''
  if (mode === 'slot') {
    applyDefaultSlotTarget()
  }
}

function applyDefaultSlotTarget() {
  const source = containerBatches.value.find((cb) => cb.id === props.sourceContainerBatchId)
  if (!source?.rack?.id) {
    if (targetLocations.value.length > 0) {
      form.to_location_id = targetLocations.value[0].id
    }
    return
  }
  const rack = racks.value.find((r) => r.id === source.rack?.id)
  form.to_location_id = rack?.storage_address_id || '__unknown__'
  form.to_rack_id = source.rack.id
  form.to_slot_id = source.slot?.id || ''
}

function applySuggestion(s: StorageTargetSuggestion) {
  activeSuggestionId.value = s.id
  if (s.targetMode === 'kiste' && s.containerBatchId) {
    form.target_mode = 'kiste'
    form.to_container_batch_id = s.containerBatchId
    form.to_location_id = ''
    form.to_rack_id = ''
    form.to_slot_id = ''
    return
  }
  form.target_mode = 'slot'
  form.to_container_batch_id = ''
  form.to_location_id = s.storageAddressId || targetLocations.value[0]?.id || '__unknown__'
  form.to_rack_id = s.rackId || ''
  form.to_slot_id = s.slotId || ''
  if (form.to_rack_id && !slotsByRackId.value[form.to_rack_id]) {
    void loadSlots(form.to_rack_id)
  }
}

async function loadSuggestions() {
  suggestionsLoading.value = true
  try {
    locationSuggestions.value = await loadStorageTargetSuggestions({
      departmentId: props.departmentId,
      materialId: props.componentMaterialId,
      materialName: props.componentName,
      excludeContainerBatchId: props.sourceContainerBatchId,
      containerBatches: containerBatches.value,
      racks: racks.value,
    })
    const first = locationSuggestions.value[0]
    if (first) {
      applySuggestion(first)
    } else {
      applyDefaultSlotTarget()
    }
  } catch (e) {
    console.error('RemoveCompositionReleaseModal loadSuggestions', e)
    applyDefaultSlotTarget()
  } finally {
    suggestionsLoading.value = false
  }
}

async function loadRacks() {
  try {
    racks.value = await loadStorageRacks()
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await loadSuggestions()
  } catch (e) {
    console.error('RemoveCompositionReleaseModal loadRacks', e)
  }
}

watch(
  () => form.to_rack_id,
  (rackId) => {
    if (rackId && !slotsByRackId.value[rackId]) {
      loadSlots(rackId)
    }
  },
)

watch(
  () => form.to_location_id,
  () => {
    const stillValid = targetRacks.value.some((rack) => rack.id === form.to_rack_id)
    if (!stillValid) {
      form.to_rack_id = ''
      form.to_slot_id = ''
    }
  },
)

async function handleSubmit() {
  errorMsg.value = ''
  if (!canSubmit.value) return

  if (
    form.target_mode === 'kiste' &&
    form.to_container_batch_id &&
    !(await physicalComboWarningStore.confirmContainerMove([form.to_container_batch_id]))
  ) {
    return
  }

  const payload: DeleteComboComponentRequest =
    form.target_mode === 'kiste'
      ? { release_to_container_batch_id: form.to_container_batch_id }
      : {
          release_to_rack_id: form.to_rack_id,
          release_to_slot_id: form.to_slot_id || null,
        }
  emit('confirm', payload)
}

onMounted(() => {
  loadRacks()
})
</script>

<style scoped>
.batch-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.batch-modal {
  background: white;
  border-radius: 12px;
  width: 520px;
  max-width: 95vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}
.batch-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}
.batch-modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}
.batch-modal-close {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
}
.batch-modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}
.batch-form-row {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}
.batch-form-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.batch-form-group.full-width {
  flex: 1 1 100%;
}
.batch-form-group label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}
.batch-form-input {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
}
.batch-error {
  margin-top: 12px;
  padding: 12px;
  background: #fef2f2;
  color: #dc2626;
  border-radius: 8px;
  font-size: 14px;
}
.batch-modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
}
.batch-footer-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}
.move-modal {
  max-width: 480px;
}
.move-intro {
  margin-bottom: 16px;
  color: #6b7280;
  font-size: 14px;
}
.move-target-mode {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 4px;
  width: 100%;
}
.move-target-btn {
  flex: 1;
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
}
.move-target-btn.active {
  background: #fff;
  color: #111827;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.composition-stock-preview {
  margin-bottom: 16px;
  padding: 12px 14px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.composition-stock-preview-title {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}
.composition-suggestion-chips {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.composition-suggestion-chip {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  text-align: left;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.composition-suggestion-chip:hover,
.composition-suggestion-chip.active {
  border-color: #059669;
  background: #ecfdf5;
}
.composition-suggestion-chip-label {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
.composition-suggestion-chip-hint {
  font-size: 11px;
  color: #6b7280;
}
</style>
