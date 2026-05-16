<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(defineProps<{ size?: number | string; strokeWidth?: number | string }>(), {
  size: 18,
  strokeWidth: 3,
})
</script>

<template>
  <IconSvg :size="size" :stroke-width="strokeWidth" stroke-linecap="round">
    <path d="M8 8l8 8M16 8l-8 8" />
  </IconSvg>
</template>
