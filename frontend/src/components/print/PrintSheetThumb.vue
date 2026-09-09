<template>
  <div class="sheet-thumb" :style="{ aspectRatio }">
    <svg
      class="sheet-thumb__svg"
      :viewBox="`0 0 ${spec.sheet_width_mm} ${spec.sheet_height_mm}`"
      role="img"
      :aria-label="ariaLabel"
    >
      <rect
        class="sheet-thumb__page"
        x="0"
        y="0"
        :width="spec.sheet_width_mm"
        :height="spec.sheet_height_mm"
      />
      <rect
        v-for="cell in cells"
        :key="cell.index"
        class="sheet-thumb__cell"
        :x="cell.x"
        :y="cell.y"
        :width="cell.w"
        :height="cell.h"
        :rx="spec.shape === 'round' ? cell.w / 2 : Math.min(1.2, cell.w / 8)"
      />
    </svg>
    <span class="sheet-thumb__count">{{ count }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PrintMedia } from '@/api/printCatalog'
import type { PrintSheetCell, PrintSheetSpec } from '@/api/printLayouts'
import { cellsFromSpec, labelsPerSheet, specFromMedia } from '@/print/sheetGeometry'

const props = defineProps<{
  media: PrintMedia
  spec?: PrintSheetSpec
  cells?: PrintSheetCell[]
  cutLengthMm?: number | null
  ariaLabel?: string
}>()

const spec = computed(() => props.spec || specFromMedia(props.media, props.cutLengthMm))
const cells = computed(() => props.cells || cellsFromSpec(spec.value))
const count = computed(() => labelsPerSheet(props.media, spec.value))
const aspectRatio = computed(() => `${spec.value.sheet_width_mm} / ${spec.value.sheet_height_mm}`)
</script>

<style scoped>
.sheet-thumb {
  position: relative;
  width: 100%;
  background: #eff6ff;
  border-radius: 6px;
  overflow: hidden;
}
.sheet-thumb__svg {
  display: block;
  width: 100%;
  height: auto;
}
.sheet-thumb__page {
  fill: #fff;
  stroke: #93c5fd;
  stroke-width: 0.8;
}
.sheet-thumb__cell {
  fill: #dbeafe;
  stroke: #2563eb;
  stroke-width: 0.45;
}
.sheet-thumb__count {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: #1d4ed8;
  text-shadow: 0 0 8px #fff, 0 0 8px #fff;
  pointer-events: none;
}
</style>
