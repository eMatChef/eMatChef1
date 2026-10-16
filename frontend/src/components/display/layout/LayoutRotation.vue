<template>
  <div class="dl-rotation">
    <Transition name="dl-fade" mode="out-in">
      <div :key="index" class="dl-rotation__step">
        <LayoutContent v-if="current" :content="current" :depth="depth" />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { LayoutRotationContent } from '@/api/displayLayout'
import { LAYOUT_FREEZE_KEY, stepSeconds } from './layoutContext'

/** Rotation: zeigt die Schritte nacheinander; ist sie deaktiviert, bleibt der erste Schritt stehen. */
const props = defineProps<{ content: LayoutRotationContent; depth: number }>()

const LayoutContent = defineAsyncComponent(() => import('./LayoutContent.vue'))

const frozen = inject(LAYOUT_FREEZE_KEY, false)
const index = ref(0)
let timer: ReturnType<typeof setTimeout> | null = null

const current = computed(() => props.content.steps[index.value]?.content ?? props.content.steps[0]?.content)

function schedule() {
  if (timer) clearTimeout(timer)
  timer = null
  if (frozen || !props.content.enabled || props.content.steps.length < 2) return
  timer = setTimeout(() => {
    index.value = (index.value + 1) % props.content.steps.length
    schedule()
  }, stepSeconds(props.content, index.value) * 1000)
}

onMounted(schedule)
onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})
watch(
  () => [props.content.enabled, props.content.interval_sec, props.content.steps.length],
  () => {
    if (index.value >= props.content.steps.length) index.value = 0
    schedule()
  },
)
</script>

<style scoped>
.dl-rotation,
.dl-rotation__step {
  width: 100%;
  height: 100%;
  min-width: 0;
  min-height: 0;
}
.dl-fade-enter-active,
.dl-fade-leave-active {
  transition: opacity 0.35s ease;
}
.dl-fade-enter-from,
.dl-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .dl-fade-enter-active,
  .dl-fade-leave-active {
    transition: none;
  }
}
</style>
