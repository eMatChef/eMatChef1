<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(defineProps<{ size?: number | string }>(), { size: 20 })
</script>

<template>
  <IconSvg :size="size">
    <line x1="12" y1="5" x2="12" y2="19" />
    <line x1="5" y1="12" x2="19" y2="12" />
  </IconSvg>
</template>
