<template>
  <div class="repair-sheet-diagram">
    <svg
      class="diagram-svg"
      :viewBox="viewBox"
      role="img"
      :aria-label="t('workshop.repairSheet.diagramAria')"
    >
      <rect x="8" y="18" width="384" height="224" rx="12" class="diagram-bg" />
      <polygon
        points="200,34 340,110 340,220 60,220 60,110"
        class="diagram-tent"
      />
      <line x1="200" y1="34" x2="200" y2="220" class="diagram-ridge" />
      <line x1="60" y1="110" x2="340" y2="110" class="diagram-eave" />

      <g v-for="marker in markers" :key="marker.id">
        <circle
          :cx="marker.x"
          :cy="marker.y"
          r="14"
          class="diagram-marker-hit"
          :class="{
            active: marker.id === activeMarkerId || marker.section_key === activeSectionKey,
            selected: selectedMarkerIds.includes(marker.id),
            readonly: readonly,
          }"
          @click="onMarkerClick(marker)"
        />
        <circle
          :cx="marker.x"
          :cy="marker.y"
          r="8"
          class="diagram-marker-dot"
          :class="markerColorClass(marker)"
          pointer-events="none"
        />
        <text
          :x="marker.x"
          :y="marker.y - 18"
          text-anchor="middle"
          class="diagram-marker-label"
        >
          {{ marker.label || marker.id }}
        </text>
      </g>
    </svg>
    <p v-if="markers.length === 0" class="diagram-empty">
      {{ t('workshop.repairSheet.diagramEmpty') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { RepairDiagramJson, RepairDiagramMarker } from '@/types/repairChecklist'
import { parseDiagramJson } from '@/types/repairChecklist'

const props = withDefaults(
  defineProps<{
    diagram?: RepairDiagramJson | Record<string, unknown> | null
    activeSectionKey?: string | null
    selectedMarkerIds?: string[]
    readonly?: boolean
  }>(),
  {
    activeSectionKey: null,
    selectedMarkerIds: () => [],
    readonly: false,
  }
)

const emit = defineEmits<{
  'section-select': [sectionKey: string]
  'marker-toggle': [markerId: string, sectionKey: string]
}>()

const { t } = useI18n()

const parsed = computed(() => parseDiagramJson(props.diagram))
const viewBox = computed(() => parsed.value?.viewBox ?? '0 0 400 260')
const markers = computed(() => parsed.value?.markers ?? [])

const activeMarkerId = computed(() => {
  const selected = props.selectedMarkerIds ?? []
  if (selected.length > 0) return selected[selected.length - 1]
  return null
})

function markerColorClass(marker: RepairDiagramMarker): string {
  if (props.selectedMarkerIds?.includes(marker.id)) return 'selected'
  if (marker.section_key === props.activeSectionKey) return 'active'
  return marker.color ? `custom-${marker.color}` : 'default'
}

function onMarkerClick(marker: RepairDiagramMarker) {
  if (props.readonly) {
    emit('section-select', marker.section_key)
    return
  }
  emit('marker-toggle', marker.id, marker.section_key)
  emit('section-select', marker.section_key)
}
</script>

<style scoped>
.repair-sheet-diagram {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #f8fafc;
  padding: 12px;
}

.diagram-svg {
  width: 100%;
  height: auto;
  display: block;
}

.diagram-bg {
  fill: #eef2ff;
}

.diagram-tent {
  fill: #dbeafe;
  stroke: #93c5fd;
  stroke-width: 2;
}

.diagram-ridge,
.diagram-eave {
  stroke: #60a5fa;
  stroke-width: 1.5;
  stroke-dasharray: 4 3;
}

.diagram-marker-hit {
  fill: transparent;
  cursor: pointer;
}

.diagram-marker-hit.readonly {
  cursor: default;
}

.diagram-marker-hit.active,
.diagram-marker-hit.selected {
  fill: rgba(124, 58, 237, 0.12);
}

.diagram-marker-dot {
  stroke: #fff;
  stroke-width: 2;
}

.diagram-marker-dot.default {
  fill: #64748b;
}

.diagram-marker-dot.active {
  fill: #2563eb;
}

.diagram-marker-dot.selected {
  fill: #7c3aed;
}

.diagram-marker-label {
  font-size: 10px;
  fill: #475569;
  pointer-events: none;
}

.diagram-empty {
  margin: 8px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
