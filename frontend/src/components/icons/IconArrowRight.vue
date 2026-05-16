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
    <path d="M5 12h14" />
    <polyline points="12 5 19 12 12 19" />
  </IconSvg>
</template>
