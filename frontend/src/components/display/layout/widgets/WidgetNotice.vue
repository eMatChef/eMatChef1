<template>
  <div class="w-notice" :class="`w-notice--${severity}`" role="note">
    <strong v-if="title">{{ title }}</strong>
    <span v-if="text">{{ text }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const title = computed(() => String(props.config.title ?? ''))
const text = computed(() => String(props.config.text ?? ''))
const severity = computed(() => String(props.config.severity ?? 'info'))
</script>

<style scoped>
.w-notice {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.25em;
  width: 100%;
  height: 100%;
  padding: 0.6em 0.9em;
  box-sizing: border-box;
  border-left: 0.35em solid currentColor;
  border-radius: 0.5em;
  overflow: hidden;
  overflow-wrap: anywhere;
  white-space: pre-line;
  color: #f8fafc;
}
.w-notice strong { font-size: 1.3em; }
.w-notice span { font-size: 1.05em; }
.w-notice--info { background: #1e3a8a; border-color: #60a5fa; }
.w-notice--warning { background: #78350f; border-color: #fbbf24; }
.w-notice--critical { background: #7f1d1d; border-color: #f87171; }
</style>
