<template>
  <div
    ref="viewportEl"
    class="viewport"
    :class="{ 'viewport--panning': panning, 'viewport--space': spaceDown }"
    data-testid="studio-viewport"
    @wheel.prevent="onWheel"
    @pointerdown="onViewportDown"
    @dragover.prevent
    @drop.prevent="onDrop"
  >
    <div
      ref="worldEl"
      class="world"
      :class="{ 'world--nested': nested }"
      :style="worldStyle"
      data-testid="layout-canvas"
    >
      <div v-if="showGrid" class="world__grid" :style="gridStyle" aria-hidden="true" data-testid="studio-gridlines" />
      <div
        v-for="item in host.items"
        :key="item.id"
        class="item"
        :class="{
          'item--selected': selectedSet.has(item.id),
          'item--issue': issueIds.has(item.id),
          'item--group': item.content.type === 'group',
          'item--multi': selectedSet.size > 1,
          [`item--tag-${tagSide(item)}`]: true,
        }"
        :style="{ gridColumn: `${item.x + 1} / span ${item.w}`, gridRow: `${item.y + 1} / span ${item.h}` }"
        :data-testid="`layout-item-${item.id}`"
        tabindex="-1"
        role="button"
        :aria-label="labelFor(item)"
        :aria-pressed="selectedSet.has(item.id)"
        @pointerdown.stop="onItemDown($event, item)"
        @dblclick.stop="emit('open', item.id)"
      >
        <div class="item__content"><LayoutContent :content="item.content" :depth="1" /></div>
        <!-- Editor-Beschriftung: ausserhalb der Widgetfläche, nur bei Hover bzw. Auswahl, nie über dem Inhalt -->
        <span class="item__tag" aria-hidden="true">{{ labelFor(item) }}</span>
        <template v-if="!locked && selectedSet.size === 1 && selectedSet.has(item.id)">
          <span
            v-for="edge in EDGES"
            :key="edge"
            class="handle"
            :class="`handle--${edge}`"
            role="presentation"
            :data-testid="edge === 'se' ? 'layout-resize-handle' : `layout-resize-${edge}`"
            @pointerdown.stop.prevent="onResizeDown($event, item, edge)"
          />
        </template>
      </div>
      <div v-if="ghost" class="ghost" :class="{ 'ghost--bad': !ghost.valid }" :style="ghostStyle" aria-hidden="true" />
      <div v-if="marquee" class="marquee" :style="marqueeStyle" aria-hidden="true" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { LayoutItem, WidgetDefinition } from '@/api/displayLayout'
import LayoutContent from '../LayoutContent.vue'
import { pageIssues, type Geometry } from '../layoutEditing'
import { colsOf, planMove, planResize, type GridHost, type ResizeEdge } from '../studioModel'

/**
 * Arbeitsfläche des Infoscreen-Studios. Die Fläche wird in der logischen Auflösung der Anzeige gerendert (gleiche
 * Grundgrösse `--u` und gleiche Rasterabstände wie der Renderer) und nur per CSS-Transform gezoomt – Zoom und Verschieben
 * verändern also nie die gespeicherte Geometrie. Elemente rasten am Raster ein, überlappen nie und lassen andere
 * Elemente unberührt; eine blockierte Aktion bleibt auf dem letzten gültigen Stand (rot markierter Zielort).
 */
const props = defineProps<{
  host: GridHost
  /** Logische Grösse der Fläche in Pixeln (Auflösung der Anzeige × Anteil der Fläche). */
  logicalWidth: number
  logicalHeight: number
  /** Grundgrösse der Anzeige in Pixeln (`--u`). */
  unit: number
  /** Gruppen-/Zonenfläche statt Wurzelseite: ohne Seitenrand, engere Zwischenräume (wie im Renderer). */
  nested: boolean
  selectedIds: string[]
  widgetDefs: Map<string, WidgetDefinition>
  showGrid: boolean
  labelFor: (item: LayoutItem) => string
  /** Ändert sich der Schlüssel, wird die Fläche neu eingepasst (Seite, Gruppe, Variante). */
  fitKey: string
  /** Einzelbearbeitung: Geometrie ist fest, nur Auswahl und Eigenschaften sind möglich. */
  locked?: boolean
}>()

const emit = defineEmits<{
  select: [ids: string[]]
  /** Vor der ersten Änderung einer Aktion: Schnappschuss für Undo. */
  checkpoint: []
  changed: []
  open: [id: string]
  drop: [payload: { data: string; x: number; y: number }]
  zoom: [value: number]
  blocked: []
}>()

type DragMode =
  | { kind: 'move'; origins: Map<string, Geometry>; startX: number; startY: number; applied: { dx: number; dy: number }; started: boolean; clickId: string | null }
  | { kind: 'resize'; id: string; edge: ResizeEdge; origin: Geometry; startX: number; startY: number; applied: Geometry; started: boolean; minW: number; minH: number }
  | { kind: 'marquee'; startX: number; startY: number; additive: boolean }
  | { kind: 'pan'; startX: number; startY: number; panX: number; panY: number }

const EDGES: ResizeEdge[] = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw']
const MIN_ZOOM = 0.05
const MAX_ZOOM = 6
const MARGIN = 28

const viewportEl = ref<HTMLElement | null>(null)
const worldEl = ref<HTMLElement | null>(null)
const view = ref({ x: 0, y: 0, z: 1 })
const autoFit = ref(true)
const spaceDown = ref(false)
const panning = ref(false)
const ghost = ref<{ geo: Geometry; valid: boolean } | null>(null)
const marquee = ref<{ x1: number; y1: number; x2: number; y2: number } | null>(null)
let drag: DragMode | null = null
let resizeObserver: ResizeObserver | null = null

const selectedSet = computed(() => new Set(props.selectedIds))
const issueIds = computed(() => new Set(pageIssues(props.host, props.widgetDefs).map((i) => i.itemId)))

// Abstände wie im Renderer (LayoutGrid): Wurzelseite mit Rand und weiter Lücke, verschachtelt ohne Rand.
const padX = computed(() => (props.nested ? 0 : props.unit * 1.5))
const padY = computed(() => (props.nested ? 0 : props.unit * 1.2))
const gap = computed(() => props.unit * (props.nested ? 0.6 : 1.2))
const cols = computed(() => colsOf(props.host))
const cellW = computed(() => (props.logicalWidth - 2 * padX.value - gap.value * (cols.value - 1)) / cols.value)
const cellH = computed(() => (props.logicalHeight - 2 * padY.value - gap.value * (props.host.rows - 1)) / props.host.rows)
const stepX = computed(() => cellW.value + gap.value)
const stepY = computed(() => cellH.value + gap.value)

const worldStyle = computed(() => ({
  width: `${props.logicalWidth}px`,
  height: `${props.logicalHeight}px`,
  '--u': `${props.unit}px`,
  '--zinv': `${1 / view.value.z}`,
  fontSize: `${props.unit}px`,
  gridTemplateColumns: `repeat(${cols.value}, minmax(0, 1fr))`,
  gridTemplateRows: `repeat(${props.host.rows}, minmax(0, 1fr))`,
  gap: `${gap.value}px`,
  padding: `${padY.value}px ${padX.value}px`,
  transform: `translate(${view.value.x}px, ${view.value.y}px) scale(${view.value.z})`,
}))

const gridStyle = computed(() => ({
  backgroundImage: [
    `repeating-linear-gradient(to right, transparent 0, transparent ${cellW.value}px, rgba(148,163,184,0.16) ${cellW.value}px, rgba(148,163,184,0.16) ${stepX.value}px)`,
    `repeating-linear-gradient(to bottom, transparent 0, transparent ${cellH.value}px, rgba(148,163,184,0.16) ${cellH.value}px, rgba(148,163,184,0.16) ${stepY.value}px)`,
  ].join(', '),
  backgroundSize: `${stepX.value * cols.value}px 100%, 100% ${stepY.value * props.host.rows}px`,
  backgroundPosition: `${padX.value}px 0, 0 ${padY.value}px`,
  backgroundRepeat: 'no-repeat',
}))

function geoStyle(g: Geometry) {
  return {
    left: `${padX.value + g.x * stepX.value}px`,
    top: `${padY.value + g.y * stepY.value}px`,
    width: `${g.w * stepX.value - gap.value}px`,
    height: `${g.h * stepY.value - gap.value}px`,
  }
}
/**
 * Seite, an der die Editor-Beschriftung eines Elements ausserhalb seiner Fläche Platz hat: bevorzugt oberhalb, sonst
 * unterhalb, rechts oder links (jeweils nur, wenn die Lücke zum Nachbarn gross genug ist). Rein darstellend.
 */
function tagSide(item: LayoutItem): 'above' | 'below' | 'right' | 'left' {
  const z = view.value.z
  const needH = 20 / z
  const needW = 170 / z
  const overlapsX = (o: LayoutItem) => o.x < item.x + item.w && item.x < o.x + o.w
  const overlapsY = (o: LayoutItem) => o.y < item.y + item.h && item.y < o.y + o.h
  const others = props.host.items.filter((o) => o.id !== item.id)
  const above = others.filter((o) => overlapsX(o) && o.y + o.h <= item.y)
  const below = others.filter((o) => overlapsX(o) && o.y >= item.y + item.h)
  const spaceAbove = item.y === 0 ? Infinity : gap.value + (item.y - Math.max(0, ...above.map((o) => o.y + o.h))) * stepY.value
  const spaceBelow = item.y + item.h >= props.host.rows ? Infinity : gap.value + (Math.min(props.host.rows, ...below.map((o) => o.y)) - item.y - item.h) * stepY.value
  if (above.length === 0 && item.y > 0 || spaceAbove >= needH) return 'above'
  if (spaceBelow >= needH) return 'below'
  const right = others.filter((o) => overlapsY(o) && o.x >= item.x + item.w)
  const left = others.filter((o) => overlapsY(o) && o.x + o.w <= item.x)
  const spaceRight = item.x + item.w >= cols.value ? Infinity : gap.value + (Math.min(cols.value, ...right.map((o) => o.x)) - item.x - item.w) * stepX.value
  const spaceLeft = item.x === 0 ? Infinity : gap.value + (item.x - Math.max(0, ...left.map((o) => o.x + o.w))) * stepX.value
  if (spaceRight >= needW) return 'right'
  if (spaceLeft >= needW) return 'left'
  return 'above'
}

const ghostStyle = computed(() => (ghost.value ? geoStyle(ghost.value.geo) : {}))
const marqueeStyle = computed(() => {
  const m = marquee.value
  if (!m) return {}
  return { left: `${Math.min(m.x1, m.x2)}px`, top: `${Math.min(m.y1, m.y2)}px`, width: `${Math.abs(m.x2 - m.x1)}px`, height: `${Math.abs(m.y2 - m.y1)}px` }
})

// --- Zoom und Verschieben -------------------------------------------------------------------------------------

function viewportSize(): { w: number; h: number } {
  const el = viewportEl.value
  return { w: el?.clientWidth || 800, h: el?.clientHeight || 500 }
}

function clampZoom(z: number): number {
  return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, z))
}

function fit(): void {
  const { w, h } = viewportSize()
  let z = clampZoom(Math.min((w - 2 * MARGIN) / props.logicalWidth, (h - 2 * MARGIN) / props.logicalHeight))
  // Einzelbearbeitung sehr breiter oder flacher Bausteine (z. B. Kopfzeile): Mindestdarstellung, auch wenn dafür ein Stück
  // über den Rand hinausragt (Verschieben mit Leertaste/Ziehen).
  if (props.locked) {
    const short = Math.min(props.logicalWidth, props.logicalHeight)
    if (short * z < 120) z = clampZoom(Math.min(120 / short, z * 3))
  }
  view.value = { z, x: (w - props.logicalWidth * z) / 2, y: (h - props.logicalHeight * z) / 2 }
  autoFit.value = true
}

function zoomAt(factor: number, cx?: number, cy?: number): void {
  const { w, h } = viewportSize()
  const px = cx ?? w / 2
  const py = cy ?? h / 2
  const z = clampZoom(view.value.z * factor)
  const k = z / view.value.z
  view.value = { z, x: px - (px - view.value.x) * k, y: py - (py - view.value.y) * k }
  autoFit.value = false
}

function setZoom(z: number): void {
  zoomAt(clampZoom(z) / view.value.z)
}

function onWheel(event: WheelEvent): void {
  const rect = viewportEl.value?.getBoundingClientRect()
  if (!rect) return
  if (event.shiftKey) {
    view.value = { ...view.value, x: view.value.x - event.deltaY }
    autoFit.value = false
    return
  }
  zoomAt(Math.exp(-event.deltaY * 0.0015), event.clientX - rect.left, event.clientY - rect.top)
}

watch(() => view.value.z, (z) => emit('zoom', z), { immediate: true })
// Beim Wechsel der Fläche (Gruppe, Rotationsseite, Einzelbearbeitung) wird die letzte Ansicht der Zielfläche wiederhergestellt,
// sonst neu eingepasst; beim Zurückkehren erscheint so die vorherige Arbeitsansicht.
const viewMemory = new Map<string, { x: number; y: number; z: number; auto: boolean }>()
watch(() => props.fitKey, (key, old) => {
  if (old) viewMemory.set(old, { ...view.value, auto: autoFit.value })
  const saved = viewMemory.get(key)
  requestAnimationFrame(() => {
    if (saved && !saved.auto) {
      view.value = { x: saved.x, y: saved.y, z: saved.z }
      autoFit.value = false
    } else {
      fit()
    }
  })
})
watch(() => [props.logicalWidth, props.logicalHeight], () => {
  if (autoFit.value) requestAnimationFrame(fit)
})

onMounted(() => {
  fit()
  if (typeof ResizeObserver !== 'undefined' && viewportEl.value) {
    resizeObserver = new ResizeObserver(() => {
      if (autoFit.value) fit()
    })
    resizeObserver.observe(viewportEl.value)
  }
  window.addEventListener('keydown', onKeyDown)
  window.addEventListener('keyup', onKeyUp)
})
onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  window.removeEventListener('keydown', onKeyDown)
  window.removeEventListener('keyup', onKeyUp)
  stopListening()
})

function isTyping(target: EventTarget | null): boolean {
  const el = target as HTMLElement | null
  return !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName))
}
function onKeyDown(event: KeyboardEvent): void {
  if (event.code === 'Space' && !isTyping(event.target)) {
    spaceDown.value = true
    event.preventDefault()
  }
}
function onKeyUp(event: KeyboardEvent): void {
  if (event.code === 'Space') spaceDown.value = false
}

// --- Zeigerlogik -----------------------------------------------------------------------------------------------

/** Bildschirmpunkt → logische Pixel der Fläche (unabhängig vom Zoom). */
function toLogical(clientX: number, clientY: number): { x: number; y: number } {
  const rect = worldEl.value?.getBoundingClientRect()
  if (!rect) return { x: 0, y: 0 }
  return { x: (clientX - rect.left) / view.value.z, y: (clientY - rect.top) / view.value.z }
}

function cellAt(clientX: number, clientY: number): { x: number; y: number } {
  const p = toLogical(clientX, clientY)
  return {
    x: Math.max(0, Math.min(cols.value - 1, Math.floor((p.x - padX.value) / stepX.value))),
    y: Math.max(0, Math.min(props.host.rows - 1, Math.floor((p.y - padY.value) / stepY.value))),
  }
}

function listen(): void {
  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp, { once: true })
  window.addEventListener('pointercancel', onUp, { once: true })
}
function stopListening(): void {
  window.removeEventListener('pointermove', onMove)
  window.removeEventListener('pointerup', onUp)
  window.removeEventListener('pointercancel', onUp)
}

function startPan(event: PointerEvent): void {
  drag = { kind: 'pan', startX: event.clientX, startY: event.clientY, panX: view.value.x, panY: view.value.y }
  panning.value = true
  listen()
}

function onViewportDown(event: PointerEvent): void {
  if (event.button === 1 || (event.button === 0 && spaceDown.value)) {
    event.preventDefault()
    startPan(event)
    return
  }
  if (event.button !== 0) return
  const insideWorld = worldEl.value?.contains(event.target as Node)
  if (props.locked && insideWorld) return
  if (!insideWorld) {
    // Ausserhalb der Fläche: Verschieben der Ansicht und Auswahl aufheben.
    emit('select', [])
    startPan(event)
    return
  }
  // Leere Stelle der Fläche: Rahmen aufziehen.
  const p = toLogical(event.clientX, event.clientY)
  drag = { kind: 'marquee', startX: p.x, startY: p.y, additive: event.shiftKey || event.ctrlKey || event.metaKey }
  marquee.value = { x1: p.x, y1: p.y, x2: p.x, y2: p.y }
  listen()
}

function onItemDown(event: PointerEvent, item: LayoutItem): void {
  if (event.button === 1 || (event.button === 0 && spaceDown.value)) {
    event.preventDefault()
    startPan(event)
    return
  }
  if (event.button !== 0) return
  if (props.locked) {
    emit('select', [item.id])
    return
  }
  const toggle = event.shiftKey || event.ctrlKey || event.metaKey
  let ids = props.selectedIds
  if (toggle) {
    ids = selectedSet.value.has(item.id) ? ids.filter((id) => id !== item.id) : [...ids, item.id]
    emit('select', ids)
    return
  }
  if (!selectedSet.value.has(item.id)) {
    ids = [item.id]
    emit('select', ids)
  }
  const origins = new Map<string, Geometry>()
  for (const id of ids) {
    const it = props.host.items.find((i) => i.id === id)
    if (it) origins.set(id, { x: it.x, y: it.y, w: it.w, h: it.h })
  }
  // Ein Klick ohne Ziehen auf ein Element einer Mehrfachauswahl wählt nur dieses Element.
  drag = { kind: 'move', origins, startX: event.clientX, startY: event.clientY, applied: { dx: 0, dy: 0 }, started: false, clickId: ids.length > 1 ? item.id : null }
  listen()
}

function onResizeDown(event: PointerEvent, item: LayoutItem, edge: ResizeEdge): void {
  if (event.button !== 0) return
  const origin = { x: item.x, y: item.y, w: item.w, h: item.h }
  drag = {
    kind: 'resize', id: item.id, edge, origin, startX: event.clientX, startY: event.clientY,
    applied: origin, started: false, minW: 1, minH: 1,
  }
  // Die Mindestgrösse eines Widgets ist ein Hinweis (roter Rahmen), beim Veröffentlichen prüft sie der Server.
  listen()
}

function onMove(event: PointerEvent): void {
  if (!drag) return
  if (drag.kind === 'pan') {
    view.value = { ...view.value, x: drag.panX + event.clientX - drag.startX, y: drag.panY + event.clientY - drag.startY }
    autoFit.value = false
    return
  }
  if (drag.kind === 'marquee') {
    const p = toLogical(event.clientX, event.clientY)
    marquee.value = { x1: drag.startX, y1: drag.startY, x2: p.x, y2: p.y }
    return
  }
  const dx = Math.round((event.clientX - drag.startX) / view.value.z / stepX.value)
  const dy = Math.round((event.clientY - drag.startY) / view.value.z / stepY.value)
  if (drag.kind === 'move') {
    const result = planMove(props.host, drag.origins, dx, dy, drag.applied)
    if (result.dx !== drag.applied.dx || result.dy !== drag.applied.dy) {
      if (!drag.started) {
        emit('checkpoint')
        drag.started = true
      }
      drag.applied = { dx: result.dx, dy: result.dy }
      for (const [id, o] of drag.origins) {
        const it = props.host.items.find((i) => i.id === id)
        if (it) Object.assign(it, { x: o.x + result.dx, y: o.y + result.dy })
      }
      emit('changed')
    }
    if (!result.exact && (dx !== 0 || dy !== 0)) {
      const first = [...drag.origins.values()][0]!
      ghost.value = { geo: { x: first.x + dx, y: first.y + dy, w: first.w, h: first.h }, valid: false }
    } else {
      ghost.value = null
    }
    return
  }
  const resizeId = drag.id
  const next = planResize(props.host, resizeId, drag.origin, drag.edge, dx, dy, drag.minW, drag.minH)
  if (next) {
    ghost.value = null
    if (next.x !== drag.applied.x || next.y !== drag.applied.y || next.w !== drag.applied.w || next.h !== drag.applied.h) {
      if (!drag.started) {
        emit('checkpoint')
        drag.started = true
      }
      drag.applied = next
      const it = props.host.items.find((i) => i.id === resizeId)
      if (it) Object.assign(it, next)
      emit('changed')
    }
  } else {
    // Ziel unzulässig (Überlappung, Rand, zu klein): bleibt beim letzten gültigen Stand.
    let wanted = { ...drag.origin }
    if (drag.edge.includes('e')) wanted.w += dx
    if (drag.edge.includes('w')) { wanted.x += dx; wanted.w -= dx }
    if (drag.edge.includes('s')) wanted.h += dy
    if (drag.edge.includes('n')) { wanted.y += dy; wanted.h -= dy }
    wanted = { x: wanted.x, y: wanted.y, w: Math.max(1, wanted.w), h: Math.max(1, wanted.h) }
    ghost.value = { geo: wanted, valid: false }
  }
}

function onUp(): void {
  stopListening()
  const current = drag
  drag = null
  panning.value = false
  if (!current) return
  if (current.kind === 'marquee' && marquee.value) {
    const m = marquee.value
    const left = Math.min(m.x1, m.x2)
    const right = Math.max(m.x1, m.x2)
    const top = Math.min(m.y1, m.y2)
    const bottom = Math.max(m.y1, m.y2)
    const hits = props.host.items
      .filter((i) => {
        const r = {
          l: padX.value + i.x * stepX.value, t: padY.value + i.y * stepY.value,
          r: padX.value + (i.x + i.w) * stepX.value - gap.value, b: padY.value + (i.y + i.h) * stepY.value - gap.value,
        }
        return r.l < right && r.r > left && r.t < bottom && r.b > top
      })
      .map((i) => i.id)
    const moved = right - left > 4 || bottom - top > 4
    if (!moved) emit('select', current.additive ? props.selectedIds : [])
    else emit('select', current.additive ? [...new Set([...props.selectedIds, ...hits])] : hits)
    marquee.value = null
  }
  if (current.kind === 'move' && current.clickId && !current.started) emit('select', [current.clickId])
  if (ghost.value && !ghost.value.valid) emit('blocked')
  ghost.value = null
}

function onDrop(event: DragEvent): void {
  const data = event.dataTransfer?.getData('application/x-emc-block')
  if (!data) return
  const cell = cellAt(event.clientX, event.clientY)
  emit('drop', { data, x: cell.x, y: cell.y })
}

defineExpose({ fit, zoomIn: () => zoomAt(1.25), zoomOut: () => zoomAt(0.8), setZoom, zoom: computed(() => view.value.z) })
</script>

<style scoped>
.viewport {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background:
    radial-gradient(circle at 1px 1px, rgba(100, 116, 139, 0.35) 1px, transparent 0) 0 0 / 22px 22px,
    #1e293b;
  touch-action: none;
  cursor: default;
}
.viewport--space { cursor: grab; }
.viewport--panning { cursor: grabbing; }
.world {
  position: absolute;
  left: 0;
  top: 0;
  display: grid;
  box-sizing: border-box;
  transform-origin: 0 0;
  background: #0b1220;
  color: #f8fafc;
  line-height: 1.25;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.45);
  user-select: none;
}
.world__grid {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}
.item {
  position: relative;
  min-width: 0;
  min-height: 0;
  cursor: grab;
  outline: 1px dashed rgba(148, 163, 184, 0.5);
  outline-offset: -1px;
  z-index: 1;
}
.item:active { cursor: grabbing; }
.item__content {
  width: 100%;
  height: 100%;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  pointer-events: none;
}
.item__tag {
  position: absolute;
  left: 0;
  bottom: 100%;
  max-width: 60ch;
  padding: 2px 8px;
  border-radius: 4px 4px 0 0;
  background: rgba(15, 23, 42, 0.95);
  color: #e2e8f0;
  font-size: 11px;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  pointer-events: none;
  opacity: 0;
  z-index: 7;
  transform: scale(var(--zinv));
  transform-origin: 0 100%;
}
.item--tag-below .item__tag { bottom: auto; top: 100%; border-radius: 0 0 4px 4px; transform-origin: 0 0; }
.item--tag-right .item__tag { bottom: auto; top: 0; left: 100%; border-radius: 0 4px 4px 0; transform-origin: 0 0; }
.item--tag-left .item__tag { bottom: auto; top: 0; left: auto; right: 100%; border-radius: 4px 0 0 4px; transform-origin: 100% 0; }
.item:hover .item__tag,
.item--selected .item__tag { opacity: 1; }
.item--multi .item__tag { display: none; }
.item--group { outline-color: rgba(45, 212, 191, 0.7); }
.item--selected {
  outline: 2px solid #60a5fa;
  outline-offset: -2px;
  z-index: 3;
}
.item--issue { outline: 2px solid #f87171; }
.handle {
  position: absolute;
  width: 12px;
  height: 12px;
  margin: -6px 0 0 -6px;
  border-radius: 3px;
  background: #60a5fa;
  border: 1px solid #0b1220;
  transform: scale(var(--zinv));
  z-index: 4;
}
.handle--n { left: 50%; top: 0; cursor: ns-resize; }
.handle--s { left: 50%; top: 100%; cursor: ns-resize; }
.handle--e { left: 100%; top: 50%; cursor: ew-resize; }
.handle--w { left: 0; top: 50%; cursor: ew-resize; }
.handle--ne { left: 100%; top: 0; cursor: nesw-resize; }
.handle--nw { left: 0; top: 0; cursor: nwse-resize; }
.handle--se { left: 100%; top: 100%; cursor: nwse-resize; }
.handle--sw { left: 0; top: 100%; cursor: nesw-resize; }
.ghost {
  position: absolute;
  z-index: 5;
  border: 2px dashed #60a5fa;
  background: rgba(96, 165, 250, 0.12);
  pointer-events: none;
}
.ghost--bad {
  border-color: #f87171;
  background: rgba(248, 113, 113, 0.18);
}
.marquee {
  position: absolute;
  z-index: 6;
  border: 1px solid #60a5fa;
  background: rgba(96, 165, 250, 0.12);
  pointer-events: none;
}
</style>
