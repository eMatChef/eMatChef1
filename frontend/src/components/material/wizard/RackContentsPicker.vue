<template>
  <div class="rack-contents-picker">
    <label class="rack-contents-label">Aus Lagerplatz übernehmen</label>
    <div class="rack-contents-row">
      <select
        :model-value="rackId"
        class="form-select rack-select"
        :disabled="isLoading"
        @change="(e) => $emit('update:rackId', (e.target as HTMLSelectElement).value)"
      >
        <option value="">Lagerplatz wählen...</option>
        <option v-for="r in storageRacks" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
      <button
        v-if="rackId"
        type="button"
        class="btn-outline-small"
        :disabled="isLoading"
        @click="$emit('load')"
      >
        {{ isLoading ? 'Laden...' : 'Inhalt übernehmen' }}
      </button>
    </div>
    <p v-if="selectedContents" class="rack-contents-hint">
      {{ selectedContents.contents.length }} Artikel aus „{{ selectedContents.rack_name }}“ übernommen
    </p>
  </div>
</template>

<script setup lang="ts">
import type { StorageRack } from '@/api/storageLocations'
import type { RackContentsResponse } from '@/api/storageLocations'

defineProps<{
  rackId: string
  storageRacks: StorageRack[]
  isLoading: boolean
  selectedContents: RackContentsResponse | null
}>()
defineEmits<{
  'update:rackId': [value: string]
  load: []
}>()
</script>
