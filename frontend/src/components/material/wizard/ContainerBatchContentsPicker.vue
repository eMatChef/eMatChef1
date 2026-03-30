<template>
  <div class="rack-contents-picker">
    <label class="rack-contents-label">Aus Kiste übernehmen</label>
    <div class="rack-contents-row">
      <select
        :value="containerBatchId"
        class="form-select rack-select"
        :disabled="isLoading"
        @change="onBatchChange"
      >
        <option value="">Kiste wählen…</option>
        <option
          v-for="cb in containerBatches"
          :key="cb.id"
          :value="cb.id"
          :title="formatContainerBatchOptionFullLabel(cb)"
        >
          {{ formatContainerBatchOptionFullLabel(cb) }}
        </option>
      </select>
      <button
        v-if="containerBatchId"
        type="button"
        class="btn-outline-small"
        :disabled="isLoading"
        :title="selectedContents ? 'Kisteninhalt erneut vom Server laden' : undefined"
        @click="emit('load')"
      >
        {{ isLoading ? 'Laden…' : selectedContents ? 'Aktualisieren' : 'Inhalt übernehmen' }}
      </button>
    </div>
    <p v-if="selectedContents && selectedContents.contents.length" class="rack-contents-hint">
      {{ selectedContents.contents.length }} Artikel aus Kiste „{{ selectedContents.container_label }}“ übernommen
    </p>
    <p v-else-if="selectedContents && !selectedContents.contents.length" class="rack-contents-hint rack-contents-hint--empty">
      Diese Kiste enthält aktuell keine zugeordneten Materialien.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { ContainerBatch, ContainerBatchContentsResponse } from '@/api/storageLocations'
import { formatContainerBatchOptionFullLabel } from '@/utils/containerBatchLabel'

defineProps<{
  containerBatchId: string
  containerBatches: ContainerBatch[]
  isLoading: boolean
  selectedContents: ContainerBatchContentsResponse | null
}>()

const emit = defineEmits<{
  'update:containerBatchId': [value: string]
  load: []
}>()

function onBatchChange(e: Event) {
  const el = e.target as HTMLSelectElement
  emit('update:containerBatchId', el.value)
}
</script>
