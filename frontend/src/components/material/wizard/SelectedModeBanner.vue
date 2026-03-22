<template>
  <div class="selected-mode-banner">
    <div class="banner-content">
      <div class="banner-icon" :class="'mode-icon--' + creationMode">
        <svg v-if="creationMode === 'individual'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <svg v-else-if="creationMode === 'physical_combo'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <path d="M3 9h18M9 21V9"/>
        </svg>
        <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <path d="M12 6v6l4 2"/>
        </svg>
      </div>
      <div class="banner-info">
        <span class="banner-name">
          {{ modeLabel }}
        </span>
        <span v-if="templateName" class="banner-details">
          Vorlage: {{ templateName }}
          <span v-if="templateManufacturer"> &bull; {{ templateManufacturer }}</span>
        </span>
        <span v-else-if="inventorySourceLabel" class="banner-details">
          Aus Kiste: {{ inventorySourceLabel }}
        </span>
      </div>
      <button type="button" class="banner-close" @click="$emit('reset')" title="Modus wechseln (Formular zurücksetzen)">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  creationMode: 'individual' | 'physical_combo' | 'virtual_combo'
  templateName?: string | null
  templateManufacturer?: string | null
  /** z. B. Kisten-Bezeichnung bei „Inhalt aus Kiste übernommen“ */
  inventorySourceLabel?: string | null
}>()

defineEmits<{
  reset: []
}>()

const modeLabel = computed(() => {
  const m = props.creationMode
  return m === 'individual' ? 'Einzelartikel' : m === 'physical_combo' ? 'Physische Kombination' : 'Virtuelle Kombination'
})
</script>
