<template>
  <div class="storage-location-picker" :class="[`variant-${variant}`]">
    <div v-if="showStorageAddress" class="picker-field">
      <label>{{ storageAddressLabel }}</label>
      <select
        :value="storageAddressId"
        class="form-select"
        :disabled="disabled"
        @change="handleStorageAddressChange"
      >
        <option value="">{{ storageAddressPlaceholder }}</option>
        <option
          v-for="option in storageAddressOptions"
          :key="option.id"
          :value="option.id"
        >
          {{ option.label }}
        </option>
      </select>
    </div>

    <div v-if="showRack" class="picker-field">
      <label>{{ rackLabel }}</label>
      <select
        :value="rackId"
        class="form-select"
        :disabled="disabled"
        @change="handleRackChange"
        @mouseenter="$emit('rackListMouseenter')"
      >
        <option value="">{{ rackPlaceholder }}</option>
        <option
          v-for="rack in sortedRacks"
          :key="rack.id"
          :value="String(rack.id)"
          :title="formatRackOptionTitle(rack)"
        >
          {{ formatRackLabel(rack) }}
        </option>
      </select>
    </div>

    <div v-if="showRack && showSlot" class="picker-field">
      <label>{{ slotLabel }}</label>
      <select
        :value="slotId"
        class="form-select"
        :disabled="disabled || (disableSlotWithoutRack && !rackId)"
        @change="handleSlotChange"
        @mouseenter="$emit('slotListMouseenter')"
      >
        <option value="">{{ slotPlaceholder }}</option>
        <option
          v-for="slot in sortedSlotList"
          :key="slot.id"
          :value="String(slot.id)"
          :title="formatSlotOptionTitle(slot)"
        >
          {{ formatSlotLabel(slot) }}
        </option>
      </select>
      <p v-if="showEmptySlotHint && rackId && slotList.length === 0" class="picker-hint">
        {{ emptySlotHint }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { StorageRack, StorageSlot } from '@/api/storageLocations'
import { sortByNaturalName } from '@/utils/naturalSort'

type StorageAddressOption = {
  id: string
  label: string
}

const props = withDefaults(defineProps<{
  storageAddressId?: string
  storageAddressOptions?: StorageAddressOption[]
  rackId: string
  slotId?: string
  racks: StorageRack[]
  /** Nicht „slots“ nennen – in Vue-Templates kollidiert das mit dem Slot-System (Dropdown blieb leer trotz API-Daten). */
  slotList: StorageSlot[]
  showStorageAddress?: boolean
  showRack?: boolean
  showSlot?: boolean
  disabled?: boolean
  disableSlotWithoutRack?: boolean
  showEmptySlotHint?: boolean
  emptySlotHint?: string
  storageAddressLabel?: string
  rackLabel?: string
  slotLabel?: string
  storageAddressPlaceholder?: string
  rackPlaceholder?: string
  slotPlaceholder?: string
  variant?: 'default' | 'compact'
  /** Optional: z. B. Lagerbestand-Hinweis neben dem Fachnamen */
  slotLabelFormatter?: (slot: StorageSlot) => string
  /** Optional: Kurztext in der Gestell-Zeile (z. B. Inhaltsvorschau) */
  rackLabelFormatter?: (rack: StorageRack) => string
  /** Tooltip pro Gestell-Option (mehrzeilig möglich) */
  rackOptionTitleFormatter?: (rack: StorageRack) => string
  /** Tooltip pro Fach-Option */
  slotOptionTitleFormatter?: (slot: StorageSlot) => string
}>(), {
  storageAddressId: '',
  storageAddressOptions: () => [],
  slotId: '',
  showStorageAddress: false,
  showRack: true,
  slotList: () => [],
  showSlot: true,
  disabled: false,
  disableSlotWithoutRack: true,
  showEmptySlotHint: false,
  emptySlotHint: 'Im gewählten Regal gibt es noch kein Fach.',
  storageAddressLabel: 'Standort',
  rackLabel: 'Gestell',
  slotLabel: 'Fach',
  storageAddressPlaceholder: 'Standort wählen...',
  rackPlaceholder: 'Gestell wählen...',
  slotPlaceholder: 'Fach wählen...',
  variant: 'default',
  slotLabelFormatter: undefined,
  rackLabelFormatter: undefined,
  rackOptionTitleFormatter: undefined,
  slotOptionTitleFormatter: undefined,
})

const sortedRacks = computed(() => sortByNaturalName(props.racks, (rack) => rack.name))
const sortedSlotList = computed(() => sortByNaturalName(props.slotList, (slot) => slot.name))

function formatSlotLabel(slot: StorageSlot): string {
  return props.slotLabelFormatter ? props.slotLabelFormatter(slot) : slot.name
}

function formatRackLabel(rack: StorageRack): string {
  return props.rackLabelFormatter ? props.rackLabelFormatter(rack) : rack.name
}

function formatRackOptionTitle(rack: StorageRack): string {
  return props.rackOptionTitleFormatter ? props.rackOptionTitleFormatter(rack) : ''
}

function formatSlotOptionTitle(slot: StorageSlot): string {
  return props.slotOptionTitleFormatter ? props.slotOptionTitleFormatter(slot) : ''
}

const emit = defineEmits<{
  'update:storageAddressId': [value: string]
  'update:rackId': [value: string]
  'update:slotId': [value: string]
  storageAddressChange: [value: string]
  rackChange: [value: string]
  slotChange: [value: string]
  rackListMouseenter: []
  slotListMouseenter: []
}>()

function handleStorageAddressChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  emit('update:storageAddressId', value)
  emit('storageAddressChange', value)
}

function handleRackChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  emit('update:rackId', value)
  emit('rackChange', value)
}

function handleSlotChange(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  emit('update:slotId', value)
  emit('slotChange', value)
}
</script>

<style scoped>
.storage-location-picker {
  display: grid;
  gap: 12px;
}

.storage-location-picker.variant-default {
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.storage-location-picker.variant-compact {
  grid-template-columns: 1fr;
  gap: 8px;
}

.picker-field {
  min-width: 0;
}

.picker-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
