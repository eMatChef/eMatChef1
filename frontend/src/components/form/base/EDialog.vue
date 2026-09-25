<template>
  <v-dialog
    :model-value="model"
    :max-width="maxWidth"
    :persistent="persistent || highlightOutside"
    :no-click-animation="highlightOutside"
    :scrollable="scrollable"
    :retain-focus="retainFocus"
    :z-index="zIndex"
    @update:model-value="onUpdate"
    @click:outside="onOutside"
  >
    <ECard
      :variant="cardVariant"
      :card-class="mergedCardClass"
      :data-onboarding="dataOnboarding || undefined"
    >
      <v-card-title v-if="title || $slots.title" class="e-dialog__title">
        <slot name="title">{{ title }}</slot>
      </v-card-title>
      <v-card-text class="e-dialog__body">
        <slot />
      </v-card-text>
      <v-card-actions v-if="$slots.actions" class="e-dialog__actions">
        <slot name="actions" />
      </v-card-actions>
    </ECard>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import ECard from './ECard.vue'

defineOptions({ name: 'EDialog' })

const props = withDefaults(
  defineProps<{
    title?: string
    maxWidth?: number | string
    persistent?: boolean
    /** Klick ausserhalb schliesst nicht, der Dialog leuchtet in der Systemfarbe auf. */
    highlightOutside?: boolean
    scrollable?: boolean
    /** false bei Autocomplete/Teleport-Dropdowns im Dialog */
    retainFocus?: boolean
    cardVariant?: 'elevated' | 'flat' | 'tonal' | 'outlined' | 'text' | 'plain'
    /** Zusätzliche Klassen auf der inneren ECard (z. B. Confirm-Rahmen) */
    cardClass?: string | string[] | Record<string, boolean>
    /** Tour-Spotlight: Target auf der ganzen Dialog-Karte (inkl. Actions) */
    dataOnboarding?: string
    zIndex?: number
  }>(),
  {
    maxWidth: 560,
    persistent: false,
    highlightOutside: false,
    scrollable: true,
    retainFocus: true,
    cardVariant: 'elevated',
  }
)

const model = defineModel<boolean>({ default: false })
const pulsing = ref(false)
const closeHot = ref(false)
const pulseTimers: number[] = []

const mergedCardClass = computed(() => [
  props.cardClass,
  pulsing.value ? 'e-dialog--outside-pulse' : null,
  closeHot.value ? 'e-dialog--close-hot' : null,
])

function clearPulseTimers() {
  pulseTimers.splice(0).forEach((timer) => window.clearTimeout(timer))
}

function onUpdate(value: boolean) {
  model.value = value
}

function onOutside() {
  if (!props.highlightOutside) return
  clearPulseTimers()
  pulsing.value = false
  closeHot.value = false
  requestAnimationFrame(() => {
    pulsing.value = true
    closeHot.value = true
    pulseTimers.push(window.setTimeout(() => { closeHot.value = false }, 180))
    pulseTimers.push(window.setTimeout(() => { closeHot.value = true }, 320))
    pulseTimers.push(window.setTimeout(() => { closeHot.value = false }, 560))
    pulseTimers.push(window.setTimeout(() => { pulsing.value = false }, 820))
  })
}

onBeforeUnmount(clearPulseTimers)
</script>

<style scoped>
.e-dialog__title {
  font-weight: 700;
  line-height: 1.3;
  white-space: normal;
}

.e-dialog__body {
  padding-top: 12px;
}

/* Direkt gestapelte E*-Felder (z. B. Gruppen-Modal) */
.e-dialog__body:has(> .e-form-field) {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.e-dialog__body:has(> .e-form-field) > .e-form-field {
  margin-bottom: 0;
}

.e-dialog__actions {
  padding: 8px 16px 16px;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
</style>

<style>
.e-card.e-dialog--outside-pulse {
  animation: e-dialog-outside-pulse 0.55s ease;
}

.e-card.e-dialog--close-hot .e-dialog__actions .e-button.v-btn {
  background: #ec4899 !important;
  background-color: #ec4899 !important;
  border-color: #ec4899 !important;
  color: #fff !important;
  box-shadow: 0 0 0 6px rgba(236, 72, 153, 0.45) !important;
}

.e-card.e-dialog--close-hot .e-dialog__actions .e-button.v-btn .v-btn__overlay {
  opacity: 1 !important;
  background: #ec4899 !important;
}

.e-card.e-dialog--close-hot .e-dialog__actions .e-button.v-btn .v-btn__underlay {
  opacity: 0 !important;
}

.e-card.e-dialog--close-hot .e-dialog__actions .e-button.v-btn .v-btn__content {
  color: #fff !important;
  z-index: 1;
}

@keyframes e-dialog-outside-pulse {
  0% {
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary, #059669) 0%, transparent);
  }
  40% {
    box-shadow:
      0 0 0 2px var(--color-primary, #059669),
      0 0 0 10px color-mix(in srgb, var(--color-primary, #059669) 32%, transparent);
  }
  100% {
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary, #059669) 0%, transparent);
  }
}

</style>
