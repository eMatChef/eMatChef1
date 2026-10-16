<template>
  <div ref="outer" class="sb">
    <div class="sb__inner" :style="{ width: `${innerW}px`, height: `${innerH}px`, transform: `scale(${scale})` }">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Stellt Inhalte mit fester Entwurfsbreite (z. B. die GA-Anzeigebeispiele) auf einer virtuellen Fläche dar und skaliert
 * sie auf die Zone. So bleibt die Schrift in jeder Zonengrösse im gleichen Verhältnis lesbar, ohne dass der Inhalt abgeschnitten wird.
 */
const props = withDefaults(defineProps<{ baseWidth?: number; fontScale?: number }>(), { baseWidth: 960, fontScale: 1 })

const outer = ref<HTMLElement | null>(null)
const w = ref(0)
const h = ref(0)
let observer: ResizeObserver | null = null

const scale = computed(() => (w.value > 0 ? Math.max(0.2, w.value / (props.baseWidth / props.fontScale)) : 1))
const innerW = computed(() => (scale.value > 0 ? w.value / scale.value : 0))
const innerH = computed(() => (scale.value > 0 ? h.value / scale.value : 0))

onMounted(() => {
  const el = outer.value
  if (!el) return
  const measure = () => {
    w.value = el.clientWidth
    h.value = el.clientHeight
  }
  measure()
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(measure)
    observer.observe(el)
  }
})
onBeforeUnmount(() => observer?.disconnect())
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
  font-family: inherit;
  color: #f8fafc;
}
</style>
