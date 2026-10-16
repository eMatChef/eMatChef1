import type {
  LayoutContent,
  LayoutDocument,
  LayoutItem,
  LayoutPage,
  LayoutVariant,
  LayoutWidgetContent,
  WidgetDefinition,
} from '@/api/displayLayout'
import { newId } from './layoutModel'

/** Reine Hilfsfunktionen des Layout-Editors (ohne Vue), damit sie einzeln testbar sind. */

export const COLUMNS = 12

export interface Geometry {
  x: number
  y: number
  w: number
  h: number
}

export function overlaps(a: Geometry, b: Geometry): boolean {
  return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h
}

/** Hält ein Element im Raster: mindestens 1×1, nie über den Rand. */
export function clampGeometry(g: Geometry, rows: number, minW = 1, minH = 1): Geometry {
  const w = Math.min(COLUMNS, Math.max(minW, Math.round(g.w)))
  const h = Math.min(rows, Math.max(minH, Math.round(g.h)))
  const x = Math.min(COLUMNS - w, Math.max(0, Math.round(g.x)))
  const y = Math.min(rows - h, Math.max(0, Math.round(g.y)))
  return { x, y, w, h }
}

/** Erste freie Position für ein Element der Grösse w×h (zeilenweise); sonst oben links. */
export function findFreeSpot(items: Geometry[], rows: number, w: number, h: number): Geometry {
  const width = Math.min(COLUMNS, w)
  const height = Math.min(rows, h)
  for (let y = 0; y + height <= rows; y++) {
    for (let x = 0; x + width <= COLUMNS; x++) {
      const candidate = { x, y, w: width, h: height }
      if (!items.some((item) => overlaps(candidate, item))) return candidate
    }
  }
  return { x: 0, y: 0, w: width, h: height }
}

export function defaultConfig(def: WidgetDefinition): Record<string, unknown> {
  const config: Record<string, unknown> = {}
  for (const [key, field] of Object.entries(def.fields)) {
    config[key] = Array.isArray(field.default) ? [...field.default] : field.default
  }
  return config
}

export function widgetContent(def: WidgetDefinition): LayoutWidgetContent {
  return { type: 'widget', widget: def.type, widget_version: def.version, config: defaultConfig(def) }
}

/** Seiten, die (direkt oder indirekt) von `pageId` aus eingebettet werden. */
export function reachablePages(doc: LayoutDocument, pageId: string): Set<string> {
  const pages = new Map(doc.pages.map((p) => [p.id, p]))
  const seen = new Set<string>()
  const visit = (content: LayoutContent) => {
    if (content.type === 'page') {
      if (!seen.has(content.page_id)) {
        seen.add(content.page_id)
        pages.get(content.page_id)?.items.forEach((i) => visit(i.content))
      }
    } else if (content.type === 'group') {
      content.items.forEach((i) => visit(i.content))
    } else if (content.type === 'rotation') {
      content.steps.forEach((s) => visit(s.content))
    }
  }
  pages.get(pageId)?.items.forEach((i) => visit(i.content))
  return seen
}

/** Seite `target` darf in `host` eingebettet werden, wenn dadurch kein Zyklus entsteht. */
export function canEmbed(doc: LayoutDocument, hostPageId: string, targetPageId: string): boolean {
  if (hostPageId === targetPageId) return false
  return !reachablePages(doc, targetPageId).has(hostPageId)
}

/** Seiten, die irgendwo (in Varianten oder anderen Seiten) verwendet werden. */
export function referencedPageIds(doc: LayoutDocument): Set<string> {
  const used = new Set<string>()
  const visit = (content: LayoutContent) => {
    if (content.type === 'page') used.add(content.page_id)
    else if (content.type === 'group') content.items.forEach((i) => visit(i.content))
    else if (content.type === 'rotation') content.steps.forEach((s) => visit(s.content))
  }
  doc.variants.forEach((v) => visit(v.root))
  doc.pages.forEach((p) => p.items.forEach((i) => visit(i.content)))
  return used
}

/** Seiten, die in einer Variante (Wurzel) oder in einer anderen Seite verwendet werden – ohne `exceptPageId` selbst. */
export function isPageReferenced(doc: LayoutDocument, pageId: string): boolean {
  const used = new Set<string>()
  const visit = (content: LayoutContent) => {
    if (content.type === 'page') used.add(content.page_id)
    else if (content.type === 'group') content.items.forEach((i) => visit(i.content))
    else if (content.type === 'rotation') content.steps.forEach((s) => visit(s.content))
  }
  doc.variants.forEach((v) => visit(v.root))
  doc.pages.filter((p) => p.id !== pageId).forEach((p) => p.items.forEach((i) => visit(i.content)))
  return used.has(pageId)
}

export function addPage(doc: LayoutDocument, name: string): LayoutPage {
  const page: LayoutPage = { id: newId('p', doc.pages.map((p) => p.id)), name, rows: 12, items: [] }
  doc.pages.push(page)
  return page
}

export function addItem(page: LayoutPage, content: LayoutContent, size: [number, number]): LayoutItem {
  const spot = findFreeSpot(page.items, page.rows, size[0], size[1])
  const item: LayoutItem = { id: newId('i', page.items.map((i) => i.id)), ...spot, content }
  page.items.push(item)
  return item
}

export function addVariant(doc: LayoutDocument, name: string, aspect: string, fromVariant?: LayoutVariant): LayoutVariant {
  const variant: LayoutVariant = {
    id: newId('v', doc.variants.map((v) => v.id)),
    name,
    aspect_ratio: aspect,
    root: fromVariant ? JSON.parse(JSON.stringify(fromVariant.root)) : { type: 'page', page_id: doc.pages[0]?.id ?? '' },
  }
  doc.variants.push(variant)
  return variant
}

/** Entfernt eine Variante (die letzte bleibt bestehen) und hält die Standardvariante gültig. */
export function removeVariant(doc: LayoutDocument, variantId: string): boolean {
  if (doc.variants.length <= 1) return false
  doc.variants = doc.variants.filter((v) => v.id !== variantId)
  if (doc.default_variant_id === variantId) doc.default_variant_id = doc.variants[0]!.id
  return true
}

/** Die Seiten einer Variante in Anzeigereihenfolge (Wurzel: Seite oder Rotation von Seiten). */
export function variantFlow(variant: LayoutVariant): { rotation: boolean; enabled: boolean; interval: number; pageIds: string[]; durations: (number | null)[]; simple: boolean } {
  const root = variant.root
  if (root.type === 'page') return { rotation: false, enabled: false, interval: 20, pageIds: [root.page_id], durations: [null], simple: true }
  if (root.type === 'rotation' && root.steps.every((s) => s.content.type === 'page')) {
    return {
      rotation: true,
      enabled: root.enabled,
      interval: root.interval_sec,
      pageIds: root.steps.map((s) => (s.content as { page_id: string }).page_id),
      durations: root.steps.map((s) => s.duration_sec),
      simple: true,
    }
  }
  return { rotation: false, enabled: false, interval: 20, pageIds: [], durations: [], simple: false }
}

/** Setzt die Wurzel einer Variante aus einer Seitenfolge (eine Seite = statisch, mehrere = Gesamtrotation). */
export function setVariantFlow(variant: LayoutVariant, pageIds: string[], enabled: boolean, interval: number, durations: (number | null)[] = []): void {
  if (pageIds.length <= 1 && enabled === false && durations.every((d) => d === null)) {
    variant.root = { type: 'page', page_id: pageIds[0] ?? '' }
    return
  }
  variant.root = {
    type: 'rotation',
    enabled,
    interval_sec: interval,
    steps: pageIds.map((id, i) => ({ duration_sec: durations[i] ?? null, content: { type: 'page', page_id: id } })),
  }
}

export interface ItemIssue {
  itemId: string
  code: 'overlap' | 'too_small'
}

/** Hinweise für die Anzeige im Editor (der Server prüft beim Veröffentlichen verbindlich). */
export function pageIssues(page: LayoutPage, widgetDefs: Map<string, WidgetDefinition>): ItemIssue[] {
  const issues: ItemIssue[] = []
  page.items.forEach((item, i) => {
    if (item.content.type === 'widget') {
      const def = widgetDefs.get(item.content.widget)
      if (def && (item.w < def.min_size[0] || item.h < def.min_size[1])) issues.push({ itemId: item.id, code: 'too_small' })
    }
    for (let j = i + 1; j < page.items.length; j++) {
      if (overlaps(item, page.items[j]!)) issues.push({ itemId: page.items[j]!.id, code: 'overlap' })
    }
  })
  return issues
}

// --- Gerätesimulation (spiegelt die serverseitige Variantenwahl für die Editor-Vorschau) -------------------------

export interface SimulatedDevice {
  key: string
  aspect: number
  fontScale: number
  variantId?: string | null
}

export function ratioOf(aspect: string): number {
  const [w, h] = aspect.split(':').map(Number)
  return h ? (w as number) / h : 16 / 9
}

const ASPECT_TOLERANCE = 0.12

/** Passendste Variante nach Seitenverhältnis (Standardvariante gewinnt bei Gleichstand), sonst Standardvariante. */
export function pickVariant(doc: LayoutDocument, device: Pick<SimulatedDevice, 'aspect' | 'variantId'>): string {
  const ids = doc.variants.map((v) => v.id)
  if (device.variantId && ids.includes(device.variantId)) return device.variantId
  const fallback = ids.includes(doc.default_variant_id) ? doc.default_variant_id : (ids[0] ?? '')
  let best = fallback
  let bestDelta = Infinity
  const ordered = [...doc.variants].sort((a, b) => Number(b.id === fallback) - Number(a.id === fallback))
  for (const variant of ordered) {
    const delta = Math.abs(Math.log(device.aspect / ratioOf(variant.aspect_ratio)))
    if (delta < bestDelta) {
      bestDelta = delta
      best = variant.id
    }
  }
  return bestDelta <= ASPECT_TOLERANCE ? best : fallback
}

export interface DeviceProfileLike {
  pixel_width: number | null
  pixel_height: number | null
  width_cm: number | null
  height_cm: number | null
  aspect_ratio: string | null
  viewing_distance_m: number | null
  font_profile: string
  variant_id: string | null
}

/** Seitenverhältnis eines Geräteprofils: ausdrücklich, aus den Pixeln, sonst aus der physischen Grösse. */
export function profileAspect(p: DeviceProfileLike | null): number | null {
  if (!p) return null
  if (p.aspect_ratio) return ratioOf(p.aspect_ratio)
  if (p.pixel_width && p.pixel_height) return p.pixel_width / p.pixel_height
  if (p.width_cm && p.height_cm) return p.width_cm / p.height_cm
  return null
}

export function profileFontScale(p: DeviceProfileLike | null): number {
  const named: Record<string, number> = { small: 0.85, normal: 1, large: 1.25, xlarge: 1.5 }
  if (p && named[p.font_profile]) return named[p.font_profile]!
  const d = p?.viewing_distance_m
  if (!d) return 1
  return d <= 2 ? 1 : d <= 5 ? 1.15 : d <= 10 ? 1.35 : 1.6
}
