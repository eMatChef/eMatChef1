<template>
  <div
    class="e-loading-state"
    :class="rootClasses"
    role="status"
    :aria-live="variant === 'inline' ? 'polite' : 'assertive'"
    :aria-busy="true"
    :aria-label="resolvedAriaLabel"
  >
    <template v-if="variant === 'inline'">
      <v-progress-circular
        indeterminate
        :size="inlineSize"
        :width="inlineWidth"
        color="primary"
        class="e-loading-state__inline-spinner"
      />
      <span v-if="message" class="e-loading-state__message e-loading-state__message--inline">
        {{ message }}
      </span>
    </template>

    <template v-else-if="variant === 'page'">
      <v-progress-circular
        indeterminate
        size="48"
        width="4"
        color="primary"
        class="e-loading-state__spinner"
      />
      <p v-if="message" class="e-loading-state__message">{{ message }}</p>
    </template>

    <template v-else-if="variant === 'table'">
      <v-skeleton-loader
        :type="tableSkeletonType"
        class="e-loading-state__skeleton e-loading-state__skeleton--table"
      />
      <p v-if="message" class="e-loading-state__message e-loading-state__message--below">
        {{ message }}
      </p>
    </template>

    <template v-else-if="variant === 'list'">
      <v-skeleton-loader
        v-for="rowIndex in rows"
        :key="rowIndex"
        type="list-item-avatar-three-line"
        class="e-loading-state__skeleton e-loading-state__skeleton--list"
      />
      <p v-if="message" class="e-loading-state__message e-loading-state__message--below">
        {{ message }}
      </p>
    </template>

    <template v-else-if="variant === 'card'">
      <v-skeleton-loader
        type="article, paragraph@2"
        class="e-loading-state__skeleton e-loading-state__skeleton--card"
      />
      <p v-if="message" class="e-loading-state__message e-loading-state__message--below">
        {{ message }}
      </p>
    </template>

    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'ELoadingState' })

export type ELoadingStateVariant = 'page' | 'table' | 'list' | 'card' | 'inline'

const props = withDefaults(
  defineProps<{
    /** page = Spinner; table/list/card = v-skeleton-loader; inline = kleiner Spinner */
    variant?: ELoadingStateVariant
    message?: string
    /** Zeilen für table/list */
    rows?: number
    compact?: boolean
    ariaLabel?: string
    inlineSize?: number | string
    inlineWidth?: number | string
  }>(),
  {
    variant: 'page',
    rows: 6,
    compact: false,
    inlineSize: 20,
    inlineWidth: 2,
  },
)

const tableSkeletonType = computed(() => `table-row-divider@${Math.max(1, props.rows)}`)

const resolvedAriaLabel = computed(() => props.ariaLabel ?? props.message ?? 'Loading')

const rootClasses = computed(() => [
  `e-loading-state--${props.variant}`,
  { 'e-loading-state--compact': props.compact },
])
</script>

<style src="@/styles/components/e-loading-state.css"></style>
