<template>
  <div class="canvas-wrap">
    <div
      ref="canvasEl"
      class="canvas"
      :style="{ aspectRatio: aspectCss, width: `min(100%, calc(66vh * ${ratio}))`, '--u': `${unit}px`, '--cols': COLUMNS, '--rows': page.rows }"
      data-testid="layout-canvas"
      @pointerdown.self="emit('select', null)"
      @dragover.prevent
      @drop.prevent="onDrop"
    >
      <div class="canvas__gridlines" aria-hidden="true" />
      <div
        v-for="item in page.items"
        :key="item.id"
        class="item"
        :class="{ 'item--selected': item.id === selectedId, 'item--issue': issueIds.has(item.id) }"
        :style="boxStyle(item)"
        :data-testid="`layout-item-${item.id}`"
        tabindex="0"
        role="button"
        :aria-label="itemLabel(item)"
        @pointerdown.stop="startDrag($event, item, 'move')"
        @keydown="onKey($event, item)"
      >
        <div class="item__content">
          <LayoutContent :content="item.content" :depth="1" />
        </div>
        <span class="item__tag">{{ itemLabel(item) }}</span>
        <span
          v-if="item.id === selectedId"
          class="item__handle"
          role="presentation"
          data-testid="layout-resize-handle"
          @pointerdown.stop="startDrag($event, item, 'resize')"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LayoutDocument, LayoutItem, LayoutPage, WidgetDefinition } from '@/api/displayLayout'
import LayoutContent from '../LayoutContent.vue'
import { LAYOUT_FREEZE_KEY, LAYOUT_PAGES_KEY } from '../layoutContext'
import { COLUMNS, clampGeometry, pageIssues, ratioOf } from '../layoutEditing'

/**
 * Rasterfläche einer Seite: Elemente per Drag-and-drop verschieben, über den Griff skalieren, Widgets aus der
 * Bibliothek ablegen. Pfeiltasten verschieben, Umschalt+Pfeiltasten skalieren (Tastaturbedienung).
 */
const props = defineProps<{
  doc: LayoutDocument
  page: LayoutPage
  selectedId: string | null
  aspectRatio: string
  fontScale: number
  widgetDefs: Map<string, WidgetDefinition>
}>()

const emit = defineEmits<{
  select: [id: string | null]
  change: []
  drop: [payload: { widget: string; x: number; y: number }]
}>()

const { t } = useI18n()
provide(LAYOUT_PAGES_KEY, () => props.doc.pages)
provide(LAYOUT_FREEZE_KEY, true)

const canvasEl = ref<HTMLElement | null>(null)
const height = ref(540)
let observer: ResizeObserver | null = null

const aspectCss = computed(() => props.aspectRatio.replace(':', ' / '))
const ratio = computed(() => ratioOf(props.aspectRatio))
const unit = computed(() => Math.max(6, (height.value / 60) * props.fontScale))
const issueIds = computed(() => new Set(pageIssues(props.page, props.widgetDefs).map((i) => i.itemId)))

onMounted(() => {
  const el = canvasEl.value
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

function boxStyle(item: LayoutItem) {
  return {
    left: `${(item.x / COLUMNS) * 100}%`,
    top: `${(item.y / props.page.rows) * 100}%`,
    width: `${(item.w / COLUMNS) * 100}%`,
    height: `${(item.h / props.page.rows) * 100}%`,
  }
}

function itemLabel(item: LayoutItem): string {
  const c = item.content
  if (c.type === 'widget') return t(`display.layout.widget.${c.widget}`)
  if (c.type === 'page') return t('display.layout.content.page', { name: props.doc.pages.find((p) => p.id === c.page_id)?.name ?? '' })
  return t(`display.layout.content.${c.type}`)
}

function minSize(item: LayoutItem): [number, number] {
  if (item.content.type !== 'widget') return [1, 1]
  return props.widgetDefs.get(item.content.widget)?.min_size ?? [1, 1]
}

let drag: { item: LayoutItem; mode: 'move' | 'resize'; startX: number; startY: number; origin: { x: number; y: number; w: number; h: number } } | null = null

function startDrag(event: PointerEvent, item: LayoutItem, mode: 'move' | 'resize') {
  if (event.button !== 0) return
  emit('select', item.id)
  drag = { item, mode, startX: event.clientX, startY: event.clientY, origin: { x: item.x, y: item.y, w: item.w, h: item.h } }
  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', endDrag, { once: true })
}

function onMove(event: PointerEvent) {
  if (!drag || !canvasEl.value) return
  const rect = canvasEl.value.getBoundingClientRect()
  const dx = Math.round(((event.clientX - drag.startX) / rect.width) * COLUMNS)
  const dy = Math.round(((event.clientY - drag.startY) / rect.height) * props.page.rows)
  const o = drag.origin
  const next = drag.mode === 'move'
    ? clampGeometry({ x: o.x + dx, y: o.y + dy, w: o.w, h: o.h }, props.page.rows)
    : clampGeometry({ x: o.x, y: o.y, w: o.w + dx, h: o.h + dy }, props.page.rows)
  if (drag.mode === 'resize') {
    // Skalieren verschiebt nie die Oberkante und bleibt im Raster.
    next.w = Math.min(next.w, COLUMNS - o.x)
    next.h = Math.min(next.h, props.page.rows - o.y)
  }
  const item = drag.item
  if (item.x !== next.x || item.y !== next.y || item.w !== next.w || item.h !== next.h) {
    Object.assign(item, next)
    emit('change')
  }
}

function endDrag() {
  window.removeEventListener('pointermove', onMove)
  drag = null
}

onBeforeUnmount(() => window.removeEventListener('pointermove', onMove))

function onKey(event: KeyboardEvent, item: LayoutItem) {
  const delta: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }
  const step = delta[event.key]
  if (step) {
    event.preventDefault()
    const [minW, minH] = minSize(item)
    const next = event.shiftKey
      ? clampGeometry({ x: item.x, y: item.y, w: item.w + step[0], h: item.h + step[1] }, props.page.rows, minW > 1 ? 1 : 1, minH > 1 ? 1 : 1)
      : clampGeometry({ x: item.x + step[0], y: item.y + step[1], w: item.w, h: item.h }, props.page.rows)
    Object.assign(item, next)
    emit('change')
  } else if (event.key === 'Escape') {
    emit('select', null)
  }
}

function onDrop(event: DragEvent) {
  const widget = event.dataTransfer?.getData('application/x-emc-widget')
  if (!widget || !canvasEl.value) return
  const rect = canvasEl.value.getBoundingClientRect()
  const x = Math.floor(((event.clientX - rect.left) / rect.width) * COLUMNS)
  const y = Math.floor(((event.clientY - rect.top) / rect.height) * props.page.rows)
  emit('drop', { widget, x, y })
}
</script>

<style scoped>
.canvas-wrap {
  width: 100%;
  display: flex;
  justify-content: center;
}
.canvas {
  position: relative;
  background: #0b1220;
  color: #f8fafc;
  font-size: var(--u);
  line-height: 1.25;
  border-radius: 8px;
  overflow: hidden;
  touch-action: none;
}
.canvas__gridlines {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background-image:
    linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
  background-size: calc(100% / var(--cols)) calc(100% / var(--rows));
}
.item {
  position: absolute;
  box-sizing: border-box;
  padding: calc(var(--u) * 0.3);
  outline: 1px dashed rgba(148, 163, 184, 0.55);
  cursor: grab;
}
.item:active { cursor: grabbing; }
.item__content {
  width: 100%;
  height: 100%;
  overflow: hidden;
  pointer-events: none;
}
.item__tag {
  position: absolute;
  top: 2px;
  left: 2px;
  max-width: calc(100% - 4px);
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(15, 23, 42, 0.85);
  color: #e2e8f0;
  font-size: 11px;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  pointer-events: none;
}
.item--selected {
  outline: 2px solid #60a5fa;
  z-index: 2;
}
.item--issue { outline: 2px solid #f87171; }
.item__handle {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 16px;
  height: 16px;
  background: #60a5fa;
  border-radius: 3px 0 0 0;
  cursor: nwse-resize;
}
</style>
