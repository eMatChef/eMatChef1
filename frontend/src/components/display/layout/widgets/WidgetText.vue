<template>
  <div class="w-text" :class="[`w-text--${level}`, `w-text--${align}`]">{{ text }}</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const text = computed(() => String(props.config.text ?? ''))
const level = computed(() => String(props.config.level ?? 'body'))
const align = computed(() => String(props.config.align ?? 'left'))
</script>

<style scoped>
.w-text {
  display: flex;
  align-items: center;
  width: 100%;
  height: 100%;
  overflow: hidden;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.w-text--left { justify-content: flex-start; text-align: left; }
.w-text--center { justify-content: center; text-align: center; }
.w-text--right { justify-content: flex-end; text-align: right; }
.w-text--title { font-size: 2.4em; font-weight: 700; line-height: 1.1; }
.w-text--heading { font-size: 1.6em; font-weight: 600; }
.w-text--body { font-size: 1.15em; }
.w-text--caption { font-size: 0.9em; color: #cbd5e1; }
</style>
