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
          <span class="banner-badge" :class="'banner-badge--' + creationMode">{{ modeBadge }}</span>
        </span>
        <span v-if="templateName" class="banner-details">
          {{ t('components.materialCreateWizard.bannerTemplatePrefix') }} {{ templateName }}<span v-if="templateManufacturer"> · {{ templateManufacturer }}</span>
        </span>
        <span v-else-if="inventorySourceLabel" class="banner-details">
          {{ t('components.materialCreateWizard.bannerFromBoxPrefix') }} {{ inventorySourceLabel }}
        </span>
      </div>
      <button
        type="button"
        class="banner-close"
        @click="$emit('reset')"
        :title="t('components.materialCreateWizard.titleResetCreationMode')"
      >
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
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
  if (m === 'individual') return t('components.materialCreateWizard.modeIndividualTitle')
  if (m === 'physical_combo') return t('components.materialCreateWizard.modePhysicalComboTitle')
  return t('components.materialCreateWizard.modeVirtualComboTitle')
})

const modeBadge = computed(() => {
  const m = props.creationMode
  if (m === 'individual') return t('components.materialCreateWizard.badgeIndividual')
  if (m === 'physical_combo') return t('components.materialCreateWizard.badgePhysicalCombo')
  return t('components.materialCreateWizard.badgeVirtualCombo')
})
</script>

<style scoped>
.banner-badge {
  display: inline-block;
  margin-left: 8px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.5;
  vertical-align: middle;
}
.banner-badge--individual {
  background: #e2e8f0;
  color: #334155;
}
.banner-badge--physical_combo {
  background: #dbeafe;
  color: #1d4ed8;
}
.banner-badge--virtual_combo {
  background: #ede9fe;
  color: #6d28d9;
}
</style>
