<script setup lang="ts">
import IconSvg from './IconSvg.vue'

withDefaults(defineProps<{ size?: number | string }>(), { size: 20 })
</script>

<template>
  <IconSvg :size="size" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 20h9" />
    <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
  </IconSvg>
</template>
