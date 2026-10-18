<template>
  <div class="ga-map-w">
    <ActivityDualLocationMap
      v-if="!model.empty"
      class="ga-map-w__map"
      height="100%"
      :pins="model.pins"
      :polygons="model.polygons"
      :overlay="model.overlay"
      :base-layer="config.baseLayer"
      :interactive="false"
      :show-layer-control="false"
      :show-location-search="false"
      :hide-pin-labels="!config.showLabels"
      :polygon-labels="config.showLabels"
      :fixed-view="config.fixedView"
      hide-empty-hint
    />
    <div v-else class="ga-map-w__empty" role="status">
      <span class="ga-map-w__empty-icon" aria-hidden="true">⌖</span>
      <span>{{ t('display.layout.ga.noMapData') }}</span>
    </div>
    <h2 v-if="title && !model.empty" class="ga-map-w__title">{{ title }}</h2>
  </div>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import ActivityDualLocationMap from '@/components/activities/ActivityDualLocationMap.vue'
import { buildDisplayMapModel, resolveDisplayMapConfig } from '@/utils/displayMapModel'
import { LAYOUT_MAP_DATA_KEY } from '../layoutContext'

/**
 * Lageplan / Karte des Infoscreens. Zeigt dieselben Orte, Bereiche und denselben Geländeplan wie die GA-Planung
 * (zentrale Karte `ActivityDualLocationMap`), ausschliesslich passiv: keine Bedienelemente, kein Zoom, keine Popups.
 */
const props = defineProps<{ config: Record<string, unknown>; widget: string }>()
const { t } = useI18n()

const mapData = inject(LAYOUT_MAP_DATA_KEY, () => null)
const config = computed(() => resolveDisplayMapConfig(props.config))
const model = computed(() => buildDisplayMapModel(mapData(), config.value))
const title = computed(() => String(props.config.title ?? '').trim())
</script>

<style scoped>
.ga-map-w {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 0.6em;
  background: #111c30;
  pointer-events: none;
}
.ga-map-w :deep(.activity-dual-location-map),
.ga-map-w :deep(.activity-dual-location-map__stage) {
  height: 100%;
}
.ga-map-w :deep(.activity-dual-location-map__canvas) {
  height: 100% !important;
  border: 0;
  border-radius: 0;
}
/* Beschriftungen und Attribution wachsen mit der Anzeigegrösse (--u), Attribution ohne anklickbare Links. */
.ga-map-w :deep(.activity-dual-location-map__tooltip) {
  font-size: calc(var(--u, 18px) * 0.8);
}
.ga-map-w :deep(.leaflet-control-attribution) {
  font-size: calc(var(--u, 18px) * 0.65);
}
.ga-map-w :deep(.leaflet-control-attribution a) {
  pointer-events: none;
  color: inherit;
  text-decoration: none;
}
.ga-map-w__title {
  position: absolute;
  top: 0.5em;
  left: 0.5em;
  margin: 0;
  padding: 0.15em 0.6em;
  border-radius: 0.4em;
  background: rgba(15, 23, 42, 0.78);
  color: #f8fafc;
  font-size: 1em;
  font-weight: 700;
  z-index: 500;
}
.ga-map-w__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.4em;
  width: 100%;
  height: 100%;
  color: #94a3b8;
  font-size: 0.9em;
  text-align: center;
  padding: 1em;
}
.ga-map-w__empty-icon {
  font-size: 2.2em;
  line-height: 1;
}
</style>
