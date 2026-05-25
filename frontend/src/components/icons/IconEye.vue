<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(defineProps<{ size?: number | string }>(), { size: 20 })
</script>

<template>
  <IconSvg :size="size" stroke-linecap="round" stroke-linejoin="round">
    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
    <circle cx="12" cy="12" r="3" />
  </IconSvg>
</template>
