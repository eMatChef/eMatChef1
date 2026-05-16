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
    <path d="M19 12H5" />
    <polyline points="12 19 5 12 12 5" />
  </IconSvg>
</template>
