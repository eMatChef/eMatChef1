<template>
  <span class="ga-help-hint">
    <button
      type="button"
      class="ga-help-hint__btn"
      :aria-label="label ? `${t('gaHelp.hint.aria')}: ${label}` : t('gaHelp.hint.aria')"
      @click.prevent
      @focus="onFocus"
      @pointerleave="onPointerLeave"
      @blur="onBlur"
    >
      <v-icon icon="mdi-help-circle-outline" size="16" aria-hidden="true" />
    </button>
    <v-tooltip
      v-model="open"
      activator="parent"
      location="top"
      max-width="320"
      :open-on-hover="canHover"
      :open-on-focus="canHover"
      :open-on-click="!canHover"
    >
      {{ t(gaHelpKey.field(field)) }}
    </v-tooltip>
  </span>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { gaHelpKey } from '@/config/gaHelp'

defineOptions({ name: 'GaHelpHint' })

/**
 * Kleines Hilfesymbol an komplexen Feldern. Erklärung unter `gaHelp.fields.<field>`.
 * Maus: Hover und Tastaturfokus. Touch (kein Hover): Tippen öffnet und schliesst, ein Tipp daneben schliesst ebenfalls.
 * Das Symbol ist ein eigener Button neben dem Feld (nie im Label) und blockiert die Standardaktion eines Klicks.
 */
defineProps<{
  field: string
  /** Name des Felds für Screenreader («Erklärung anzeigen: …»). */
  label?: string
}>()

const { t } = useI18n()

const canHover =
  typeof window === 'undefined' || !window.matchMedia || !window.matchMedia('(hover: none)').matches

const open = ref(false)

// Tastatur: Vuetifys Fokus-Öffnen greift am Button nicht zuverlässig, daher explizit (nur bei sichtbarem Fokus, nicht bei Mausklick).
// Beim Schliessen setzt Vuetify den Fokus programmatisch auf den Button zurück; nach Maus-Austritt darf das den Tooltip nicht wieder öffnen.
let lastPointerLeave = 0

function onPointerLeave() {
  lastPointerLeave = performance.now()
}

function onFocus(event: FocusEvent) {
  if (performance.now() - lastPointerLeave < 300) return
  if (canHover && (event.target as HTMLElement).matches(':focus-visible')) open.value = true
}

function onBlur() {
  if (canHover) open.value = false
}
</script>

<style scoped>
.ga-help-hint {
  display: inline-flex;
  vertical-align: middle;
}

.ga-help-hint__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: #64748b;
  cursor: help;
}

.ga-help-hint__btn:hover {
  color: #0284c7;
}

.ga-help-hint__btn:focus-visible {
  outline: 2px solid #0284c7;
  outline-offset: 1px;
}
</style>
