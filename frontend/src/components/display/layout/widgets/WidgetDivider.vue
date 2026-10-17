<template>
  <div class="w-divider" :class="`w-divider--${orientation}`"><span :style="{ '--w': weight }" /></div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const orientation = computed(() => (props.config.orientation === 'vertical' ? 'vertical' : 'horizontal'))
const weight = computed(() => ({ thin: '1px', thick: '0.4em' } as Record<string, string>)[String(props.config.weight)] ?? '0.15em')
</script>

<style scoped>
.w-divider {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}
.w-divider span {
  display: block;
  background: #475569;
  border-radius: 999px;
}
.w-divider--horizontal span { width: 100%; height: var(--w); }
.w-divider--vertical span { height: 100%; width: var(--w); }
</style>
