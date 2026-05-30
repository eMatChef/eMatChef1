<template>
  <div v-if="open" class="import-storage-modal-overlay" @click.self="emit('close')">
    <div class="import-storage-modal" role="dialog" aria-modal="true">
      <h3 class="import-storage-modal__title">{{ t('settings.materialImport.storageModalTitle') }}</h3>
      <p v-if="materialName" class="import-storage-modal__subtitle">{{ materialName }}</p>

      <div class="import-storage-modal__mode">
        <button
          type="button"
          class="mode-btn"
          :class="{ active: draft.stock_location_mode === 'slot' }"
          @click="setMode('slot')"
        >
          {{ t('settings.materialImport.stockModeSlot') }}
        </button>
        <button
          type="button"
          class="mode-btn"
          :class="{ active: draft.stock_location_mode === 'kiste' }"
          @click="setMode('kiste')"
        >
          {{ t('settings.materialImport.stockModeKiste') }}
        </button>
      </div>

      <label class="import-storage-modal__label">{{ t('settings.materialImport.mappingField.storage') }}</label>
      <select v-model="draft.storage_address_id" class="form-select-sm" @change="onStorageAddressChange">
        <option value="">{{ t('settings.materialImport.storageModalSelectStorage') }}</option>
        <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
          {{ addr.name || addr.company || addr.id }}
        </option>
      </select>
      <p v-if="storageAddresses.length === 0" class="import-storage-modal__hint">
        {{ t('settings.materialImport.storageModalNoStorage') }}
      </p>

      <template v-if="draft.stock_location_mode === 'slot'">
        <StorageLocationPicker
          class="import-storage-modal__picker"
          variant="compact"
          :storage-address-id="draft.storage_address_id"
          :storage-address-options="storageAddressOptions"
          :show-storage-address="false"
          :rack-id="draft.rack_id"
          :slot-id="draft.slot_id"
          :racks="filteredRacks"
          :slot-list="slotsForRack"
          :rack-label="t('settings.materialImport.mappingField.rack')"
          :slot-label="t('settings.materialImport.mappingField.slot')"
          :rack-placeholder="t('settings.materialImport.storageModalSelectRack')"
          :slot-placeholder="t('settings.materialImport.storageModalSelectSlot')"
          :show-empty-slot-hint="true"
          :empty-slot-hint="t('settings.materialImport.storageModalNoSlots')"
          @update:rackId="onRackIdUpdate"
          @update:slotId="onSlotIdUpdate"
        />
      </template>

      <template v-else-if="draft.stock_location_mode === 'kiste'">
        <label class="import-storage-modal__label">{{ t('settings.materialImport.mappingField.container') }}</label>
        <select v-model="draft.container_batch_id" class="form-select-sm">
          <option value="">{{ t('settings.materialImport.storageModalSelectContainer') }}</option>
          <option v-for="cb in containerBatches" :key="cb.id" :value="cb.id">
            {{ containerOptionLabel(cb) }}
          </option>
        </select>
        <p v-if="containerBatches.length === 0" class="import-storage-modal__hint">
          {{ t('settings.materialImport.storageModalNoContainers') }}
        </p>
      </template>

      <div class="import-storage-modal__actions">
        <button type="button" class="btn-secondary btn-sm" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
        <button type="button" class="btn-primary btn-sm" :disabled="!canApply" @click="apply">
          {{ t('settings.materialImport.storageModalApply') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'
import type { Address } from '@/api/addresses'
import {
  getStorageRacks,
  getStorageSlots,
  getContainerBatches,
  type StorageRack,
  type StorageSlot,
  type ContainerBatch,
} from '@/api/storageLocations'
import type { MaterialImportRow } from '@/utils/materialImportParse'

const props = defineProps<{
  open: boolean
  departmentId: string
  row: MaterialImportRow | null
  storageAddresses: Address[]
}>()

const emit = defineEmits<{
  close: []
  apply: [patch: Partial<MaterialImportRow>]
}>()

const { t } = useI18n()

const draft = ref<Partial<MaterialImportRow>>({})
const allRacks = ref<StorageRack[]>([])
const slotsForRack = ref<StorageSlot[]>([])
const containerBatches = ref<ContainerBatch[]>([])

const materialName = computed(() => props.row?.name?.trim() || '')

const storageAddressOptions = computed(() =>
  props.storageAddresses.map((a) => ({
    id: a.id,
    label: (a.name || a.company || a.id) as string,
  })),
)

const filteredRacks = computed(() => {
  const sid = draft.value.storage_address_id
  if (!sid) return allRacks.value
  return allRacks.value.filter((r) => r.storage_address_id === sid)
})

const canApply = computed(() => {
  if (!draft.value.stock_location_mode) return false
  if (draft.value.stock_location_mode === 'kiste') {
    return !!draft.value.container_batch_id
  }
  return !!draft.value.rack_id && !!draft.value.slot_id
})

function containerOptionLabel(cb: ContainerBatch): string {
  return cb.display_label || cb.label || cb.material_name || cb.serial_number || cb.id
}

function setMode(mode: 'slot' | 'kiste') {
  draft.value.stock_location_mode = mode
  if (mode === 'kiste') {
    draft.value.rack_id = ''
    draft.value.slot_id = ''
    draft.value.rack_name = ''
    draft.value.slot_name = ''
  } else {
    draft.value.container_batch_id = ''
    draft.value.container_name = ''
  }
}

function onStorageAddressChange() {
  const addr = props.storageAddresses.find((a) => a.id === draft.value.storage_address_id)
  draft.value.storage_name = (addr?.name || addr?.company || '').trim()
  draft.value.rack_id = ''
  draft.value.slot_id = ''
  draft.value.rack_name = ''
  draft.value.slot_name = ''
  slotsForRack.value = []
}

async function onRackIdUpdate(rackId: string) {
  draft.value.rack_id = rackId
  draft.value.slot_id = ''
  const rack = allRacks.value.find((r) => r.id === rackId)
  draft.value.rack_name = rack?.name || ''
  draft.value.slot_name = ''
  if (rackId) {
    slotsForRack.value = await getStorageSlots(rackId).catch(() => [])
  } else {
    slotsForRack.value = []
  }
}

function onSlotIdUpdate(slotId: string) {
  draft.value.slot_id = slotId
  const slot = slotsForRack.value.find((s) => s.id === slotId)
  draft.value.slot_name = slot?.name || ''
}

async function loadPickerData() {
  allRacks.value = await getStorageRacks(props.departmentId).catch(() => [])
  containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
  if (draft.value.rack_id) {
    slotsForRack.value = await getStorageSlots(draft.value.rack_id).catch(() => [])
  }
}

function initDraftFromRow() {
  if (!props.row) {
    draft.value = { stock_location_mode: 'slot', storage_address_id: '', storage_name: '' }
    return
  }
  draft.value = {
    storage_name: props.row.storage_name,
    storage_address_id: props.row.storage_address_id,
    stock_location_mode: props.row.stock_location_mode || 'slot',
    rack_id: props.row.rack_id,
    rack_name: props.row.rack_name,
    slot_id: props.row.slot_id,
    slot_name: props.row.slot_name,
    container_batch_id: props.row.container_batch_id,
    container_name: props.row.container_name,
  }
  if (!draft.value.stock_location_mode) {
    draft.value.stock_location_mode = props.row.container_name || props.row.container_batch_id ? 'kiste' : 'slot'
  }
}

function apply() {
  const patch: Partial<MaterialImportRow> = {
    storage_name: draft.value.storage_name || '',
    storage_address_id: draft.value.storage_address_id || '',
    stock_location_mode: draft.value.stock_location_mode || '',
    rack_id: '',
    rack_name: '',
    slot_id: '',
    slot_name: '',
    container_batch_id: '',
    container_name: '',
  }
  if (draft.value.stock_location_mode === 'kiste') {
    const cb = containerBatches.value.find((c) => c.id === draft.value.container_batch_id)
    patch.container_batch_id = draft.value.container_batch_id || ''
    patch.container_name = cb ? containerOptionLabel(cb) : draft.value.container_name || ''
  } else {
    patch.rack_id = draft.value.rack_id || ''
    patch.rack_name = draft.value.rack_name || ''
    patch.slot_id = draft.value.slot_id || ''
    patch.slot_name = draft.value.slot_name || ''
  }
  emit('apply', patch)
  emit('close')
}

watch(
  () => [props.open, props.row] as const,
  async ([isOpen]) => {
    if (!isOpen) return
    initDraftFromRow()
    await loadPickerData()
    if (!draft.value.storage_address_id && props.storageAddresses.length === 1) {
      const a = props.storageAddresses[0]
      draft.value.storage_address_id = a.id
      draft.value.storage_name = (a.name || a.company || '').trim()
    }
  },
  { immediate: true },
)

watch(
  () => draft.value.container_batch_id,
  (id) => {
    if (!id) return
    const cb = containerBatches.value.find((c) => c.id === id)
    if (cb) draft.value.container_name = containerOptionLabel(cb)
  },
)
</script>

<style scoped>
.import-storage-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.45);
  padding: 1rem;
}

.import-storage-modal {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
  padding: 1rem 1.1rem;
  width: 100%;
  max-width: 420px;
  max-height: 90vh;
  overflow-y: auto;
}

.import-storage-modal__title {
  margin: 0 0 0.25rem;
  font-size: 1rem;
}

.import-storage-modal__subtitle {
  margin: 0 0 0.75rem;
  font-size: 0.8rem;
  color: #64748b;
}

.import-storage-modal__mode {
  display: flex;
  gap: 0.35rem;
  margin-bottom: 0.75rem;
}

.mode-btn {
  flex: 1;
  padding: 0.4rem 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 0.75rem;
  cursor: pointer;
}

.mode-btn.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}

.import-storage-modal__label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
  margin: 0.5rem 0 0.2rem;
}

.import-storage-modal__picker {
  margin-top: 0.35rem;
}

.import-storage-modal__hint {
  margin: 0.35rem 0 0;
  font-size: 0.7rem;
  color: #b45309;
}

.import-storage-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}
</style>
