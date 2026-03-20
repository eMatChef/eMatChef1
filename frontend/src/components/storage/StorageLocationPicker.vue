<template>
  <div class="storage-location-picker" :class="[`variant-${variant}`]">
    <div v-if="showStorageAddress" class="picker-field">
      <label>{{ storageAddressLabel }}</label>
      <select
        :value="storageAddressId"
        class="picker-select"
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

    <div class="picker-field">
      <label>{{ rackLabel }}</label>
      <select
        :value="rackId"
        class="picker-select"
        :disabled="disabled"
        @change="handleRackChange"
      >
        <option value="">{{ rackPlaceholder }}</option>
        <option v-for="rack in racks" :key="rack.id" :value="rack.id">
          {{ rack.name }}
        </option>
      </select>
    </div>

    <div v-if="showSlot" class="picker-field">
      <label>{{ slotLabel }}</label>
      <select
        :value="slotId"
        class="picker-select"
        :disabled="disabled || !rackId || disableSlotWithoutRack"
        @change="handleSlotChange"
      >
        <option value="">{{ slotPlaceholder }}</option>
        <option v-for="slot in slots" :key="slot.id" :value="slot.id">
          {{ slot.name }}
        </option>
      </select>
      <p v-if="showEmptySlotHint && rackId && slots.length === 0" class="picker-hint">
        {{ emptySlotHint }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { StorageRack, StorageSlot } from '@/api/storageLocations'

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
  slots: StorageSlot[]
  showStorageAddress?: boolean
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
}>(), {
  storageAddressId: '',
  storageAddressOptions: () => [],
  slotId: '',
  showStorageAddress: false,
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
})

const emit = defineEmits<{
  'update:storageAddressId': [value: string]
  'update:rackId': [value: string]
  'update:slotId': [value: string]
  storageAddressChange: [value: string]
  rackChange: [value: string]
  slotChange: [value: string]
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
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.picker-field label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.picker-select {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
}

.picker-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
