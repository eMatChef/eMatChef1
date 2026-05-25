<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(
  defineProps<{
    size?: number | string
    strokeWidth?: number | string
  }>(),
  { size: 16, strokeWidth: 2.5 },
)
</script>

<template>
  <IconSvg :size="size" :stroke-width="strokeWidth">
    <polyline points="18 15 12 9 6 15" />
  </IconSvg>
</template>
