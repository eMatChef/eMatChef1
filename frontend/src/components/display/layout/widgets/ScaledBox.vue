<template>
  <div ref="outer" class="sb">
    <div ref="innerEl" class="sb__inner" :style="{ width: `${innerW}px`, height: `${innerH}px`, transform: `scale(${scale})` }">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Stellt Inhalte, die für Full HD entworfen sind (z. B. die GA-Anzeigebeispiele), massstabsgetreu dar: Bei 1080p ist der
 * Massstab 1, sonst folgt er der Grundgrösse des Renderers (`--u`, 18 px bei 1080p, inklusive Lesbarkeitsfaktor). Die
 * virtuelle Fläche füllt die Zone, es wird nichts verzerrt oder abgeschnitten.
 */
const outer = ref<HTMLElement | null>(null)
const w = ref(0)
const h = ref(0)
const baseScale = ref(1)
const fit = ref(1)
const innerEl = ref<HTMLElement | null>(null)
const scale = computed(() => baseScale.value * fit.value)
let fitTimer: ReturnType<typeof setInterval> | undefined
let observer: ResizeObserver | null = null

const innerW = computed(() => (scale.value > 0 ? w.value / scale.value : 0))
const innerH = computed(() => (scale.value > 0 ? h.value / scale.value : 0))

onMounted(() => {
  const el = outer.value
  if (!el) return
  const measure = () => {
    w.value = el.clientWidth
    h.value = el.clientHeight
    const u = parseFloat(getComputedStyle(el).getPropertyValue('--u'))
    baseScale.value = Number.isFinite(u) && u > 0 ? Math.max(0.3, u / 18) : 1
    fit.value = 1
  }
  measure()
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(measure)
    observer.observe(el)
  }
})

/** Zu hoher Inhalt wird verkleinert, bis er in die Zone passt (nichts wird abgeschnitten); nie unter 45 %. */
function fitContent() {
  const inner = innerEl.value
  if (!inner || inner.clientHeight === 0) return
  const overflow = inner.scrollHeight - inner.clientHeight
  if (overflow > 2 && fit.value > 0.45) {
    fit.value = Math.max(0.45, fit.value * (inner.clientHeight / inner.scrollHeight) * 0.98)
  }
}
onMounted(() => {
  fitTimer = setInterval(fitContent, 400)
})
onBeforeUnmount(() => {
  observer?.disconnect()
  if (fitTimer) clearInterval(fitTimer)
})
</script>

<style scoped>
.sb {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
}
.sb__inner {
  position: absolute;
  top: 0;
  left: 0;
  transform-origin: top left;
  overflow: hidden;
  font-size: 16px;
  line-height: 1.5;
  font-family: inherit;
  color: #f8fafc;
}
</style>
