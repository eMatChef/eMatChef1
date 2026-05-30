<template>
  <div class="step-section creation-mode-step" data-step="creation_mode">
    <div class="step-header step-header--clickable" @click="$emit('toggle')">
      <span class="step-title">{{ t('components.materialCreateWizard.creationPrompt') }}</span>
      <span class="step-chevron" :class="{ open: isOpen }">▾</span>
    </div>
    <div v-show="isOpen" class="step-content">
      <div class="creation-mode-cards">
        <div class="creation-mode-card" @click="$emit('select', 'individual')">
          <div class="mode-card-icon individual">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
          </div>
          <div class="mode-card-content">
            <div class="mode-card-head">
              <span class="mode-card-title">{{ t('components.materialCreateWizard.modeIndividualTitle') }}</span>
              <span class="mode-card-badge mode-card-badge--individual">{{ t('components.materialCreateWizard.badgeIndividual') }}</span>
            </div>
            <span class="mode-card-desc">{{ t('components.materialCreateWizard.modeIndividualDesc') }}</span>
            <span class="mode-card-when">{{ t('components.materialCreateWizard.modeIndividualWhen') }}</span>
          </div>
        </div>
        <div class="creation-mode-card" @click="$emit('select', 'physical_combo')">
          <div class="mode-card-icon physical">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <path d="M3 9h18M9 21V9"/>
            </svg>
          </div>
          <div class="mode-card-content">
            <div class="mode-card-head">
              <span class="mode-card-title">{{ t('components.materialCreateWizard.modePhysicalComboTitle') }}</span>
              <span class="mode-card-badge mode-card-badge--physical">{{ t('components.materialCreateWizard.badgePhysicalCombo') }}</span>
            </div>
            <span class="mode-card-desc">{{ t('components.materialCreateWizard.modePhysicalComboDesc') }}</span>
            <span class="mode-card-when">{{ t('components.materialCreateWizard.modePhysicalComboWhen') }}</span>
          </div>
        </div>
        <div class="creation-mode-card" @click="$emit('select', 'virtual_combo')">
          <div class="mode-card-icon virtual">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <path d="M12 6v6l4 2"/>
            </svg>
          </div>
          <div class="mode-card-content">
            <div class="mode-card-head">
              <span class="mode-card-title">{{ t('components.materialCreateWizard.modeVirtualComboTitle') }}</span>
              <span class="mode-card-badge mode-card-badge--virtual">{{ t('components.materialCreateWizard.badgeVirtualCombo') }}</span>
            </div>
            <span class="mode-card-desc">{{ t('components.materialCreateWizard.modeVirtualComboDesc') }}</span>
            <span class="mode-card-when">{{ t('components.materialCreateWizard.modeVirtualComboWhen') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  isOpen: boolean
}>()
defineEmits<{
  select: [mode: 'individual' | 'physical_combo' | 'virtual_combo']
  toggle: []
}>()
</script>
