<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(defineProps<{ size?: number | string; strokeWidth?: number | string }>(), {
  size: 16,
  strokeWidth: 2,
})
</script>

<template>
  <IconSvg :size="size" :stroke-width="strokeWidth">
    <path d="M18 6L6 18M6 6l12 12" />
  </IconSvg>
</template>
