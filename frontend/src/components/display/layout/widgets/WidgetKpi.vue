<template>
  <div class="w-kpi">
    <span class="w-kpi__label">{{ label }}</span>
    <span class="w-kpi__value">{{ value }}<small v-if="unit">{{ unit }}</small></span>
    <span v-if="caption" class="w-kpi__caption">{{ caption }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const label = computed(() => String(props.config.label ?? ''))
const value = computed(() => String(props.config.value ?? ''))
const unit = computed(() => String(props.config.unit ?? ''))
const caption = computed(() => String(props.config.caption ?? ''))
</script>

<style scoped>
.w-kpi {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.2em;
  width: 100%;
  height: 100%;
  padding: 0.6em 0.9em;
  box-sizing: border-box;
  border-radius: 0.6em;
  background: #111c33;
  overflow: hidden;
}
.w-kpi__label { font-size: 1em; color: #cbd5e1; }
.w-kpi__value { font-size: 3em; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.w-kpi__value small { margin-left: 0.25em; font-size: 0.4em; font-weight: 500; color: #cbd5e1; }
.w-kpi__caption { font-size: 0.9em; color: #94a3b8; }
</style>
