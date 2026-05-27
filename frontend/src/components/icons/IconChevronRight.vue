<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(
  defineProps<{
    size?: number | string
    strokeWidth?: number | string
  }>(),
  { size: 12, strokeWidth: 2.3 },
)
</script>

<template>
  <IconSvg :size="size" :stroke-width="strokeWidth" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="9 18 15 12 9 6" />
  </IconSvg>
</template>
