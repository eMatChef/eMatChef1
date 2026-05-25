<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    size?: number | string
    strokeWidth?: number | string
    viewBox?: string
    ariaHidden?: boolean
    strokeLinecap?: 'round' | 'butt' | 'square'
    strokeLinejoin?: 'round' | 'miter' | 'bevel'
  }>(),
  {
    size: 20,
    strokeWidth: 2,
    viewBox: '0 0 24 24',
    ariaHidden: true,
    strokeLinecap: undefined,
    strokeLinejoin: undefined,
  },
)

const sizeStyle = computed(() => {
  const s = props.size
  const px = typeof s === 'number' ? `${s}px` : s
  return { width: px, height: px }
})
</script>

<template>
  <svg
    class="emc-icon"
    :style="sizeStyle"
    :viewBox="viewBox"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    :stroke-linecap="strokeLinecap"
    :stroke-linejoin="strokeLinejoin"
    :aria-hidden="ariaHidden ? 'true' : undefined"
  >
    <slot />
  </svg>
</template>

<style scoped>
.emc-icon {
  display: block;
  flex-shrink: 0;
}
</style>
