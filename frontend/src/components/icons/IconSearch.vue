<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(defineProps<{ size?: number | string }>(), { size: 20 })
</script>

<template>
  <IconSvg :size="size" stroke-linecap="round">
    <circle cx="11" cy="11" r="8" />
    <path d="m21 21-4.35-4.35" />
  </IconSvg>
</template>
