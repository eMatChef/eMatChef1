<template>
  <g class="print-field-boxes">
    <g
      v-for="field in fields"
      :key="field.id"
      class="field-box"
      :class="{ 'is-on': field.id === selectedId, 'is-overlay': variant === 'overlay' }"
    >
      <rect
        class="field-box__hit"
        :x="box(field).x"
        :y="box(field).y"
        :width="box(field).w"
        :height="box(field).h"
        @pointerdown="onPointerDown($event, field, 'move')"
      />
      <text
        v-if="variant === 'editor'"
        class="field-box__label"
        :x="box(field).x + 1.5"
        :y="box(field).y + Math.min(6, box(field).h - 1)"
      >
        {{ field.type === 'qr' ? 'QR' : field.key }}
      </text>
      <template v-if="editable">
        <rect
          v-for="handle in handles"
          :key="field.id + handle"
          class="field-box__handle"
          :x="handleBox(field, handle).x"
          :y="handleBox(field, handle).y"
          :width="handleBox(field, handle).w"
          :height="handleBox(field, handle).h"
          :data-handle="handle"
          @pointerdown.stop="onPointerDown($event, field, handle)"
        />
      </template>
    </g>
  </g>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PrintLayoutField } from '@/api/printLayouts'
import { moveField, resizeField, type ResizeHandle } from '@/print/layoutFieldGeom'

const props = withDefaults(
  defineProps<{
    fields: PrintLayoutField[]
    cellX?: number
    cellY?: number
    cellW: number
    cellH: number
    editable?: boolean
    selectedId?: string
    variant?: 'editor' | 'overlay'
  }>(),
  {
    cellX: 0,
    cellY: 0,
    editable: false,
    selectedId: '',
    variant: 'editor',
  },
)

const emit = defineEmits<{
  'update:fields': [value: PrintLayoutField[]]
  'update:selectedId': [value: string]
}>()

const handles: ResizeHandle[] = ['nw', 'ne', 'sw', 'se']
const handleMm = computed(() => Math.max(1.8, Math.min(props.cellW, props.cellH) * 0.045))

function box(field: PrintLayoutField) {
  return {
    x: props.cellX + (field.x / 100) * props.cellW,
    y: props.cellY + (field.y / 100) * props.cellH,
    w: (field.w / 100) * props.cellW,
    h: (field.h / 100) * props.cellH,
  }
}

function handleBox(field: PrintLayoutField, handle: ResizeHandle) {
  const b = box(field)
  const s = handleMm.value
  return {
    x: handle.includes('w') ? b.x - s / 2 : b.x + b.w - s / 2,
    y: handle.includes('n') ? b.y - s / 2 : b.y + b.h - s / 2,
    w: s,
    h: s,
  }
}

function clientPercent(event: PointerEvent, svg: SVGSVGElement) {
  const ctm = svg.getScreenCTM()
  if (!ctm) return { x: 0, y: 0 }
  const pt = svg.createSVGPoint()
  pt.x = event.clientX
  pt.y = event.clientY
  const p = pt.matrixTransform(ctm.inverse())
  return {
    x: ((p.x - props.cellX) / props.cellW) * 100,
    y: ((p.y - props.cellY) / props.cellH) * 100,
  }
}

function replaceField(id: string, next: PrintLayoutField) {
  emit(
    'update:fields',
    props.fields.map((item) => (item.id === id ? next : item)),
  )
}

function onPointerDown(event: PointerEvent, field: PrintLayoutField, mode: 'move' | ResizeHandle) {
  if (!props.editable) return
  emit('update:selectedId', field.id)
  const svg = (event.currentTarget as SVGElement).ownerSVGElement
  if (!svg) return
  const start = clientPercent(event, svg)
  const orig = { ...field }
  const onMove = (move: PointerEvent) => {
    const now = clientPercent(move, svg)
    const dx = now.x - start.x
    const dy = now.y - start.y
    const next = mode === 'move' ? moveField(orig, dx, dy) : resizeField(orig, mode, dx, dy)
    replaceField(field.id, next)
  }
  const onUp = () => {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
  }
  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
  event.preventDefault()
  event.stopPropagation()
}
</script>

<style scoped>
.field-box__hit {
  fill: rgba(37, 99, 235, 0.18);
  stroke: #2563eb;
  stroke-width: 0.35;
  cursor: grab;
  touch-action: none;
}
.field-box.is-overlay .field-box__hit {
  fill: rgba(37, 99, 235, 0.04);
}
.field-box.is-on .field-box__hit {
  stroke: #1d4ed8;
  stroke-width: 0.7;
}
.field-box__label {
  font-size: 3.2px;
  fill: #1e3a8a;
  pointer-events: none;
}
.field-box__handle {
  fill: #fff;
  stroke: #1d4ed8;
  stroke-width: 0.35;
  cursor: nwse-resize;
  touch-action: none;
}
.field-box__handle[data-handle='ne'],
.field-box__handle[data-handle='sw'] {
  cursor: nesw-resize;
}
</style>
