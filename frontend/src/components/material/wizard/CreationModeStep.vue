<template>
  <div class="step-section creation-mode-step" data-step="creation_mode">
    <div class="step-header step-header--clickable" @click="$emit('toggle')">
      <span class="step-title">Was möchtest du erstellen?</span>
      <span class="step-chevron" :class="{ open: isOpen }">▾</span>
    </div>
    <div v-show="isOpen" class="step-content">
      <div class="creation-mode-cards">
        <div class="creation-mode-card" @click="$emit('select', 'individual')">
          <div class="mode-card-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
          </div>
          <div class="mode-card-content">
            <span class="mode-card-title">Einzelartikel erstellen</span>
            <span class="mode-card-desc">
              Eigenständiges Material anlegen oder aus Vorlage erstellen.
            </span>
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
            <span class="mode-card-title">Physische Kombination</span>
            <span class="mode-card-desc">
              Feste Einheit (mit oder ohne Vorlage, z.B. "Zelt Braun").
              Wird als Ganzes gelagert und vermietet.
            </span>
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
            <span class="mode-card-title">Virtuelle Kombination</span>
            <span class="mode-card-desc">
              Planungsgruppe für Reservationen.
              Teile bleiben einzeln verfügbar.
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  isOpen: boolean
}>()
defineEmits<{
  select: [mode: 'individual' | 'physical_combo' | 'virtual_combo']
  toggle: []
}>()
</script>
