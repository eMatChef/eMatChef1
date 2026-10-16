<template>
  <div class="w-clock" :class="`w-clock--${align}`">
    <time :datetime="iso">{{ label }}</time>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ config: Record<string, unknown> }>()
const { locale } = useI18n()

const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | undefined
const format = computed(() => String(props.config.format ?? 'time'))
const align = computed(() => String(props.config.align ?? 'right'))
const iso = computed(() => now.value.toISOString())
const label = computed(() => {
  const d = now.value
  switch (format.value) {
    case 'time_seconds':
      return d.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    case 'date':
      return d.toLocaleDateString(locale.value, { weekday: 'long', day: 'numeric', month: 'long' })
    case 'datetime':
      return d.toLocaleString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
    default:
      return d.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' })
  }
})

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 1000)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.w-clock {
  display: flex;
  align-items: center;
  width: 100%;
  height: 100%;
  font-size: 2em;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  overflow: hidden;
}
.w-clock--left { justify-content: flex-start; }
.w-clock--center { justify-content: center; }
.w-clock--right { justify-content: flex-end; }
</style>
