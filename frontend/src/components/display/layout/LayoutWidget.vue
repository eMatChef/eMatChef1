<template>
  <component :is="renderer" v-if="renderer" :config="content.config" :widget="content.widget" />
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import type { LayoutWidgetContent } from '@/api/displayLayout'
import WidgetClock from './widgets/WidgetClock.vue'
import WidgetDivider from './widgets/WidgetDivider.vue'
import WidgetHeader from './widgets/WidgetHeader.vue'
import WidgetImage from './widgets/WidgetImage.vue'
import WidgetKpi from './widgets/WidgetKpi.vue'
import WidgetList from './widgets/WidgetList.vue'
import WidgetNotice from './widgets/WidgetNotice.vue'
import WidgetProgress from './widgets/WidgetProgress.vue'
import WidgetText from './widgets/WidgetText.vue'

/**
 * Zuordnung Widget-Typ → Renderer. Unbekannte Typen werden nicht dargestellt (leere Fläche), nie als HTML interpretiert.
 * Die GA-Fachwidgets werden erst bei Bedarf geladen (sie ziehen die GA-Demo-Ansichten nach).
 */
const props = defineProps<{ content: LayoutWidgetContent }>()

const GrossanlassWidget = defineAsyncComponent(() => import('./widgets/GrossanlassWidget.vue'))
// Echte Karte (Leaflet) getrennt laden: die übrigen GA-Widgets brauchen sie nicht.
const GrossanlassMapWidget = defineAsyncComponent(() => import('./widgets/GrossanlassMapWidget.vue'))


const GENERAL: Record<string, Component> = {
  header: WidgetHeader,
  text: WidgetText,
  clock: WidgetClock,
  image: WidgetImage,
  notice: WidgetNotice,
  divider: WidgetDivider,
  kpi: WidgetKpi,
  list: WidgetList,
  progress: WidgetProgress,
}

const renderer = computed<Component | null>(() => {
  const type = props.content.widget
  if (GENERAL[type]) return GENERAL[type]
  if (type === 'ga_site_map') return GrossanlassMapWidget
  if (type.startsWith('ga_')) return GrossanlassWidget
  return null
})
</script>
