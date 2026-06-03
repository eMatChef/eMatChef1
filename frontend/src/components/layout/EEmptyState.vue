<template>
  <div class="e-empty-state" :class="rootClasses">
    <div v-if="$slots.illustration" class="e-empty-state__illustration">
      <slot name="illustration" />
    </div>
    <div v-else-if="resolvedIcon" class="e-empty-state__icon" aria-hidden="true">
      <v-icon :icon="resolvedIcon" :size="iconSize" />
    </div>

    <component :is="headingTag" v-if="title || $slots.title" class="e-empty-state__title">
      <slot name="title">{{ title }}</slot>
    </component>

    <p v-if="description || $slots.description" class="e-empty-state__description">
      <slot name="description">{{ description }}</slot>
    </p>

    <div v-if="$slots.default" class="e-empty-state__body">
      <slot />
    </div>

    <div v-if="$slots.actions" class="e-empty-state__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'EEmptyState' })

export type EEmptyStateVariant = 'generic' | 'create' | 'search'

const props = withDefaults(
  defineProps<{
    title?: string
    description?: string
    /** MDI-Icon — überschreibt variant-Default */
    icon?: string
    /** Vordefinierte Icons für typische Listen-Zustände */
    variant?: EEmptyStateVariant
    compact?: boolean
    headingLevel?: 2 | 3
    iconSize?: number | string
  }>(),
  {
    variant: 'generic',
    compact: false,
    headingLevel: 2,
    iconSize: 56,
  },
)

const VARIANT_ICONS: Record<EEmptyStateVariant, string> = {
  generic: 'mdi-inbox-outline',
  create: 'mdi-account-plus-outline',
  search: 'mdi-magnify-close',
}

const resolvedIcon = computed(() => props.icon ?? VARIANT_ICONS[props.variant])

const headingTag = computed(() => (props.headingLevel === 3 ? 'h3' : 'h2'))

const rootClasses = computed(() => [
  `e-empty-state--${props.variant}`,
  { 'e-empty-state--compact': props.compact },
])
</script>

<style src="@/styles/components/e-empty-state.css"></style>
