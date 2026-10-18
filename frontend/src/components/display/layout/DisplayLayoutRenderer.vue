<template>
  <!-- Passive Anzeige: nur Darstellung, keine Bedienelemente, keine Links. -->
  <div ref="rootEl" class="dl" :style="{ '--u': `${unit}px` }" role="presentation">
    <LayoutContent v-if="variant" :content="variant.root" :depth="0" />
    <!-- Ausdrücklich veröffentlichter Demomodus: die ganze Anzeige trägt eine eindeutige Kennzeichnung. -->
    <div v-if="effectiveMode === 'demo' && showModeBadge" class="dl-demo-badge" data-testid="display-demo-badge">{{ t('display.studio.demoBadge') }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DisplayMapData, GaLiveData, LayoutDocument } from '@/api/displayLayout'
import LayoutContent from './LayoutContent.vue'
import { LAYOUT_DATA_MODE_KEY, LAYOUT_FREEZE_KEY, LAYOUT_LIVE_DATA_KEY, LAYOUT_MAP_DATA_KEY, LAYOUT_PAGES_KEY, type LayoutDataMode } from './layoutContext'

/**
 * Rendert eine Layoutvariante in den verfügbaren Platz. Die Grundgrösse (`--u`) folgt der Höhe der Fläche und dem
 * Lesbarkeitsfaktor des Geräts, damit Schrift auf TV, LED-Wall und Vorschau im gleichen Verhältnis skaliert.
 */
const props = withDefaults(defineProps<{
  layout: LayoutDocument
  variantId?: string
  fontScale?: number
  /** Kartendaten für «ga_site_map»; null/fehlend = Leerzustand. */
  mapData?: DisplayMapData | null
  /** Live-Daten der GA-Fachwidgets (nur im Livemodus); null/fehlend = «Daten werden geladen». */
  liveData?: GaLiveData | null
  /** Datenmodus der Darstellung; ohne Angabe gilt der veröffentlichte Modus des Layouts (Standard «live»). */
  dataMode?: LayoutDataMode
  /** Kennzeichnung «Demo-Daten» einblenden (veröffentlichte Anzeige); im Editor übernimmt das die Studio-Leiste. */
  showModeBadge?: boolean
}>(), { variantId: '', fontScale: 1, mapData: null, liveData: null, dataMode: undefined, showModeBadge: true })

const { t } = useI18n()
const effectiveMode = computed<LayoutDataMode>(() => props.dataMode ?? (props.layout.data_mode === 'demo' ? 'demo' : 'live'))
provide(LAYOUT_DATA_MODE_KEY, () => effectiveMode.value)
// Die Anzeige/Vorschau rotiert immer; das Einfrieren gilt nur für die Studio-Arbeitsfläche (auch wenn die Vorschau darunter liegt).
provide(LAYOUT_FREEZE_KEY, false)
provide(LAYOUT_LIVE_DATA_KEY, () => props.liveData)
provide(LAYOUT_PAGES_KEY, () => props.layout.pages)
provide(LAYOUT_MAP_DATA_KEY, () => props.mapData)

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
.dl-demo-badge {
  position: absolute;
  top: calc(var(--u) * 0.6);
  right: calc(var(--u) * 0.8);
  z-index: 20;
  padding: calc(var(--u) * 0.15) calc(var(--u) * 0.7);
  border-radius: 999px;
  background: #f59e0b;
  color: #111827;
  font-size: calc(var(--u) * 0.8);
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  pointer-events: none;
}
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
