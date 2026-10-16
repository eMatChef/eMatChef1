<template>
  <div class="w-progress">
    <div class="w-progress__head">
      <span>{{ label }}</span>
      <strong>{{ value }}%</strong>
    </div>
    <div class="w-progress__bar" role="progressbar" :aria-valuenow="value" aria-valuemin="0" aria-valuemax="100">
      <span :style="{ width: `${value}%` }" />
    </div>
    <span v-if="caption" class="w-progress__caption">{{ caption }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const label = computed(() => String(props.config.label ?? ''))
const caption = computed(() => String(props.config.caption ?? ''))
const value = computed(() => Math.min(100, Math.max(0, Number(props.config.value) || 0)))
</script>

<style scoped>
.w-progress {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.35em;
  width: 100%;
  height: 100%;
  padding: 0.6em 0.9em;
  box-sizing: border-box;
  border-radius: 0.6em;
  background: #111c33;
  overflow: hidden;
}
.w-progress__head { display: flex; justify-content: space-between; gap: 1em; font-size: 1.2em; }
.w-progress__head strong { font-variant-numeric: tabular-nums; }
.w-progress__bar { height: 0.9em; border-radius: 999px; background: #1e293b; overflow: hidden; }
.w-progress__bar span { display: block; height: 100%; border-radius: 999px; background: #22c55e; }
.w-progress__caption { font-size: 0.9em; color: #94a3b8; }
</style>
