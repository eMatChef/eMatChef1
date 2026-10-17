<template>
  <!-- Passive Anzeige: nur Darstellung, keine Bedienelemente, keine Links. -->
  <div ref="rootEl" class="dl" :style="{ '--u': `${unit}px` }" role="presentation">
    <LayoutContent v-if="variant" :content="variant.root" :depth="0" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue'
import type { LayoutDocument } from '@/api/displayLayout'
import LayoutContent from './LayoutContent.vue'
import { LAYOUT_PAGES_KEY } from './layoutContext'

/**
 * Rendert eine Layoutvariante in den verfügbaren Platz. Die Grundgrösse (`--u`) folgt der Höhe der Fläche und dem
 * Lesbarkeitsfaktor des Geräts, damit Schrift auf TV, LED-Wall und Vorschau im gleichen Verhältnis skaliert.
 */
const props = withDefaults(defineProps<{
  layout: LayoutDocument
  variantId?: string
  fontScale?: number
}>(), { variantId: '', fontScale: 1 })

provide(LAYOUT_PAGES_KEY, () => props.layout.pages)

const rootEl = ref<HTMLElement | null>(null)
const height = ref(720)
let observer: ResizeObserver | null = null

const variant = computed(() =>
  props.layout.variants.find((v) => v.id === props.variantId)
  ?? props.layout.variants.find((v) => v.id === props.layout.default_variant_id)
  ?? props.layout.variants[0]
  ?? null,
)

// 1/60 der Höhe = 18 px bei 1080p; nie kleiner als 6 px (Miniaturen), grössere Faktoren für weite Betrachtungsabstände.
const unit = computed(() => Math.max(6, (height.value / 60) * props.fontScale))

onMounted(() => {
  const el = rootEl.value
  if (!el) return
  height.value = el.clientHeight || height.value
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(() => {
      height.value = el.clientHeight || height.value
    })
    observer.observe(el)
  }
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<style scoped>
.dl {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #0b1220;
  color: #f8fafc;
  font-size: var(--u);
  line-height: 1.25;
  user-select: none;
  cursor: none;
}
</style>
