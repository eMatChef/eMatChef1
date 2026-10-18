import type { LayoutContent, LayoutGroupContent, LayoutItem, LayoutPage, LayoutRotationContent } from '@/api/displayLayout'
import { COLUMNS, findFreeSpot, overlaps, type Geometry } from './layoutEditing'
import { cloneLayout, newId } from './layoutModel'

/**
 * Reines Rastermodell des Infoscreen-Studios (ohne Vue): Seiten und Gruppen sind gleichartige Rasterflächen
 * (`rows`, optional `columns`, `items`). Alle Operationen ändern nur die übergebene Fläche und verändern nie
 * stillschweigend andere Elemente: Ist eine Aktion wegen Überlappung oder Rand nicht möglich, wird sie verweigert.
 */
export interface GridHost {
  rows: number
  columns?: number
  items: LayoutItem[]
}

export const MAX_GRID = 48

export function colsOf(host: GridHost): number {
  return host.columns ?? COLUMNS
}

/** Eine Rasterfläche ist eine Seite oder der Inhalt einer Gruppe. */
export function hostOfGroup(group: LayoutGroupContent): GridHost {
  return group
}

export function inBounds(host: GridHost, g: Geometry): boolean {
  return g.w >= 1 && g.h >= 1 && g.x >= 0 && g.y >= 0 && g.x + g.w <= colsOf(host) && g.y + g.h <= host.rows
}

/** Position `g` ist frei, wenn sie im Raster liegt und keines der nicht ignorierten Elemente schneidet. */
export function isFree(host: GridHost, g: Geometry, ignore: ReadonlySet<string> = new Set()): boolean {
  if (!inBounds(host, g)) return false
  return !host.items.some((item) => !ignore.has(item.id) && overlaps(g, item))
}

export interface MoveResult {
  /** Tatsächlich angewendetes Delta (bei Gleiten entlang einer Kante oder Blockade weicht es vom Wunsch ab). */
  dx: number
  dy: number
  /** Das Wunsch-Delta wurde vollständig übernommen. */
  exact: boolean
}

/**
 * Verschiebt die Elemente `origins` (Ausgangsgeometrie) um (dx, dy). Ist die volle Verschiebung blockiert, wird entlang
 * einer Achse gleitend versucht; sonst bleibt `fallback` (letzter gültiger Stand) bestehen. Andere Elemente bewegen sich nie.
 */
export function planMove(host: GridHost, origins: ReadonlyMap<string, Geometry>, dx: number, dy: number, fallback: { dx: number; dy: number }): MoveResult {
  const ids = new Set(origins.keys())
  const fits = (ddx: number, ddy: number): boolean =>
    [...origins.values()].every((o) => isFree(host, { x: o.x + ddx, y: o.y + ddy, w: o.w, h: o.h }, ids))
  const tries: [number, number][] = [[dx, dy], [dx, fallback.dy], [fallback.dx, dy]]
  for (const [tx, ty] of tries) {
    if (fits(tx, ty)) return { dx: tx, dy: ty, exact: tx === dx && ty === dy }
  }
  return { dx: fallback.dx, dy: fallback.dy, exact: false }
}

export type ResizeEdge = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw'

/** Neue Geometrie beim Skalieren über einen Griff (Delta in Rasterzellen); `null` bei Überlappung/Rand/Mindestgrösse. */
export function planResize(host: GridHost, id: string, origin: Geometry, edge: ResizeEdge, dx: number, dy: number, minW = 1, minH = 1): Geometry | null {
  let { x, y, w, h } = origin
  if (edge.includes('e')) w = origin.w + dx
  if (edge.includes('w')) {
    x = origin.x + dx
    w = origin.w - dx
  }
  if (edge.includes('s')) h = origin.h + dy
  if (edge.includes('n')) {
    y = origin.y + dy
    h = origin.h - dy
  }
  if (w < Math.max(1, minW) || h < Math.max(1, minH)) return null
  const next = { x, y, w, h }
  return isFree(host, next, new Set([id])) ? next : null
}

export function boundingBox(items: readonly Geometry[]): Geometry | null {
  if (!items.length) return null
  const x1 = Math.min(...items.map((i) => i.x))
  const y1 = Math.min(...items.map((i) => i.y))
  const x2 = Math.max(...items.map((i) => i.x + i.w))
  const y2 = Math.max(...items.map((i) => i.y + i.h))
  return { x: x1, y: y1, w: x2 - x1, h: y2 - y1 }
}

/** Tiefe Kopie eines Inhalts als unabhängige Instanz (Bausteine, Zwischenablage). */
export function instantiate<T extends LayoutContent>(content: T): T {
  return cloneLayout(content)
}

export function itemLabel(content: LayoutContent): string {
  return content.type === 'widget' ? content.widget : content.type
}

/** Ein Element an die gewünschte Stelle setzen, sonst an die erste freie Stelle; `null`, wenn kein Platz ist. */
export function placeItem(host: GridHost, content: LayoutContent, size: [number, number], at?: { x: number; y: number }): LayoutItem | null {
  const cols = colsOf(host)
  const w = Math.min(cols, Math.max(1, size[0]))
  const h = Math.min(host.rows, Math.max(1, size[1]))
  let spot: Geometry | null = null
  if (at) {
    const wanted = { x: Math.max(0, Math.min(cols - w, at.x)), y: Math.max(0, Math.min(host.rows - h, at.y)), w, h }
    if (isFree(host, wanted)) spot = wanted
  }
  if (!spot) {
    const candidate = findFreeSpot(host.items, host.rows, w, h, cols)
    if (isFree(host, candidate)) spot = candidate
  }
  if (!spot) return null
  const item: LayoutItem = { id: newId('i', host.items.map((i) => i.id)), ...spot, content }
  host.items.push(item)
  return item
}

export interface ClipboardEntry {
  items: LayoutItem[]
}

/** Kopie der gewählten Elemente für die Zwischenablage (relativ zur linken oberen Ecke der Auswahl). */
export function copyItems(host: GridHost, ids: ReadonlySet<string>): ClipboardEntry | null {
  const picked = host.items.filter((i) => ids.has(i.id))
  const box = boundingBox(picked)
  if (!picked.length || !box) return null
  return { items: picked.map((i) => ({ ...cloneLayout(i), x: i.x - box.x, y: i.y - box.y })) }
}

/**
 * Fügt die Zwischenablage als Block an der gewünschten Stelle oder der ersten freien Stelle ein.
 * Gibt die neuen Elemente zurück oder `null`, wenn der Block nirgends hinpasst.
 */
export function pasteItems(host: GridHost, entry: ClipboardEntry, at?: { x: number; y: number }): LayoutItem[] | null {
  const box = boundingBox(entry.items)
  if (!box) return null
  if (box.w > colsOf(host) || box.h > host.rows) return null
  let origin: { x: number; y: number } | null = null
  const fits = (ox: number, oy: number) => entry.items.every((i) => isFree(host, { x: ox + i.x, y: oy + i.y, w: i.w, h: i.h }))
  if (at && fits(at.x, at.y)) origin = at
  if (!origin) {
    outer: for (let y = 0; y + box.h <= host.rows; y++) {
      for (let x = 0; x + box.w <= colsOf(host); x++) {
        if (fits(x, y)) {
          origin = { x, y }
          break outer
        }
      }
    }
  }
  if (!origin) return null
  const created: LayoutItem[] = []
  for (const source of entry.items) {
    const copy = cloneLayout(source)
    copy.id = newId('i', [...host.items.map((i) => i.id), ...created.map((i) => i.id)])
    copy.x = origin.x + source.x
    copy.y = origin.y + source.y
    created.push(copy)
  }
  host.items.push(...created)
  return created
}

export function removeItems(host: GridHost, ids: ReadonlySet<string>): void {
  for (let i = host.items.length - 1; i >= 0; i--) {
    if (ids.has(host.items[i]!.id)) host.items.splice(i, 1)
  }
}

/**
 * Fasst die gewählten Elemente zu einer Gruppe mit eigenem Raster zusammen. Das Raster der Gruppe entspricht der
 * Umrandung der Auswahl (1 Zelle = 1 Zelle des Elternrasters), die Elemente behalten ihre relative Lage exakt.
 */
export function groupItems(host: GridHost, ids: ReadonlySet<string>, name: string): LayoutItem | null {
  const picked = host.items.filter((i) => ids.has(i.id))
  const box = boundingBox(picked)
  if (!picked.length || !box) return null
  const group: LayoutGroupContent = {
    type: 'group',
    ...(name ? { name } : {}),
    rows: box.h,
    ...(box.w !== COLUMNS ? { columns: box.w } : {}),
    items: picked.map((i) => ({ ...i, x: i.x - box.x, y: i.y - box.y })),
  }
  removeItems(host, ids)
  const item: LayoutItem = { id: newId('i', host.items.map((i) => i.id)), ...box, content: group }
  host.items.push(item)
  return item
}

/**
 * Löst eine Gruppe auf. Die Elemente werden auf die Fläche der Gruppe im Elternraster abgebildet (Kanten werden gerundet,
 * dadurch bleiben getrennte Elemente getrennt). Passt das nicht verlustfrei (Gruppe kleiner als ihr Raster), wird nichts
 * verändert und `null` zurückgegeben.
 */
export function ungroupItem(host: GridHost, itemId: string): LayoutItem[] | null {
  const index = host.items.findIndex((i) => i.id === itemId)
  const group = host.items[index]
  if (!group || group.content.type !== 'group') return null
  const inner = group.content
  const sx = group.w / (inner.columns ?? COLUMNS)
  const sy = group.h / inner.rows
  const mapped: LayoutItem[] = []
  for (const child of inner.items) {
    const x1 = group.x + Math.round(child.x * sx)
    const x2 = group.x + Math.round((child.x + child.w) * sx)
    const y1 = group.y + Math.round(child.y * sy)
    const y2 = group.y + Math.round((child.y + child.h) * sy)
    mapped.push({ ...child, x: x1, y: y1, w: x2 - x1, h: y2 - y1 })
  }
  if (mapped.some((m) => m.w < 1 || m.h < 1)) return null
  for (let i = 0; i < mapped.length; i++) {
    for (let j = i + 1; j < mapped.length; j++) {
      if (overlaps(mapped[i]!, mapped[j]!)) return null
    }
  }
  const taken = host.items.filter((i) => i.id !== itemId).map((i) => i.id)
  const result: LayoutItem[] = mapped.map((m) => {
    const id = taken.includes(m.id) ? newId('i', [...taken, ...mapped.map((x) => x.id)]) : m.id
    taken.push(id)
    return { ...m, id }
  })
  host.items.splice(index, 1, ...result)
  return result
}

/** Pfadschritt in eine Rotationsseite: `<Element-ID>#<Index>` (Rotationsseiten sind Gruppen mit eigenem Raster). */
export function stepToken(itemId: string, index: number): string {
  return `${itemId}#${index}`
}

export interface TrailStep {
  /** Pfadschritt (Element-ID einer Gruppe oder `Element-ID#Index` einer Rotationsseite). */
  itemId: string
  item: LayoutItem
  /** Rasterfläche dieses Schritts (Gruppe bzw. Rotationsseite). */
  group: LayoutGroupContent
  rotationIndex?: number
  rotation?: LayoutRotationContent
}

/**
 * Pfad ab einer Seite zur Rasterfläche; Schritte sind Gruppen-Elemente oder Rotationsseiten (`id#n`).
 * `null`, wenn der Pfad nicht mehr existiert (z. B. nach Undo).
 */
export function resolveHost(page: LayoutPage, path: readonly string[]): { host: GridHost; trail: TrailStep[] } | null {
  let host: GridHost = page
  const trail: TrailStep[] = []
  for (const token of path) {
    const [id, idx] = token.split('#')
    const item = host.items.find((i) => i.id === id)
    if (!item) return null
    if (idx === undefined) {
      if (item.content.type !== 'group') return null
      trail.push({ itemId: token, item, group: item.content })
      host = item.content
    } else {
      const content = item.content
      const step = content.type === 'rotation' ? content.steps[Number(idx)] : undefined
      if (content.type !== 'rotation' || !step || step.content.type !== 'group') return null
      trail.push({ itemId: token, item, group: step.content, rotationIndex: Number(idx), rotation: content })
      host = step.content
    }
  }
  return { host, trail }
}

/**
 * Seitenverhältnis (Breite/Höhe) der Fläche am Ende des Pfades, ausgehend vom Seitenverhältnis der Seite: jede Gruppe
 * bzw. Rotation belegt nur einen Teil des Elternrasters.
 */
export function hostRatio(pageRatio: number, page: LayoutPage, trail: readonly { item: LayoutItem; group?: GridHost }[]): number {
  let ratio = pageRatio
  let parent: GridHost = page
  for (const step of trail) {
    ratio = ratio * (step.item.w / colsOf(parent)) / (step.item.h / parent.rows)
    parent = step.group ?? (step.item.content as LayoutGroupContent)
  }
  return ratio
}

/** Gesamtanteil der Fläche am Ende des Pfades an der Gesamtanzeige (0–1), für die massstabsgetreue Schriftgrösse. */
export function hostShare(page: LayoutPage, trail: readonly { item: LayoutItem; group?: GridHost }[]): { w: number; h: number } {
  let w = 1
  let h = 1
  let parent: GridHost = page
  for (const step of trail) {
    w *= step.item.w / colsOf(parent)
    h *= step.item.h / parent.rows
    parent = step.group ?? (step.item.content as LayoutGroupContent)
  }
  return { w, h }
}

// --- Rotation als Layout-Container ----------------------------------------------------------------------------

export function emptyRotationPage(): LayoutGroupContent {
  return { type: 'group', rows: 12, items: [] }
}

/** Neue Rotation mit einer leeren, frei gestaltbaren Seite. */
export function newRotation(intervalSec: number, firstName: string): LayoutRotationContent {
  return { type: 'rotation', enabled: true, interval_sec: intervalSec, steps: [{ name: firstName, duration_sec: null, content: emptyRotationPage() }] }
}

/**
 * Macht aus einem bestehenden Schritt eine gestaltbare Seite: ein Widget (oder eine eingebettete Seite) wird unverändert als
 * einziges, rasterfüllendes Element in eine Gruppe übernommen. Geschieht nur auf ausdrückliche Aktion («Seite gestalten»),
 * nie beim blossen Öffnen.
 */
export function ensureStepGroup(rotation: LayoutRotationContent, index: number): LayoutGroupContent | null {
  const step = rotation.steps[index]
  if (!step) return null
  if (step.content.type === 'group') return step.content
  if (step.content.type === 'rotation') return null
  const group: LayoutGroupContent = { type: 'group', rows: 12, items: [{ id: 'i-1', x: 0, y: 0, w: 12, h: 12, content: step.content }] }
  step.content = group
  return group
}

export function addRotationPage(rotation: LayoutRotationContent, name: string): number {
  rotation.steps.push({ name, duration_sec: null, content: emptyRotationPage() })
  return rotation.steps.length - 1
}

export function duplicateRotationPage(rotation: LayoutRotationContent, index: number, name: string): number {
  const step = rotation.steps[index]
  if (!step) return -1
  const copy = cloneLayout(step)
  copy.name = name
  rotation.steps.splice(index + 1, 0, copy)
  return index + 1
}

export function moveRotationPage(rotation: LayoutRotationContent, index: number, delta: number): number {
  const j = index + delta
  if (j < 0 || j >= rotation.steps.length) return index
  const [step] = rotation.steps.splice(index, 1)
  rotation.steps.splice(j, 0, step!)
  return j
}

/** Raster der Gruppe um den ganzzahligen Faktor verfeinern (alle Elemente behalten ihre Lage). */
export function refineGroupGrid(group: LayoutGroupContent, factor: number): boolean {
  const cols = (group.columns ?? COLUMNS) * factor
  const rows = group.rows * factor
  if (!Number.isInteger(factor) || factor < 2 || cols > MAX_GRID || rows > MAX_GRID) return false
  group.columns = cols
  group.rows = rows
  for (const item of group.items) {
    item.x *= factor
    item.y *= factor
    item.w *= factor
    item.h *= factor
  }
  return true
}

/** Raster der Gruppe vergröbern, wenn alle Elemente und das Raster durch den Faktor teilbar sind. */
export function coarsenGroupGrid(group: LayoutGroupContent, factor: number): boolean {
  const cols = group.columns ?? COLUMNS
  if (!Number.isInteger(factor) || factor < 2 || cols % factor !== 0 || group.rows % factor !== 0) return false
  if (group.items.some((i) => i.x % factor || i.y % factor || i.w % factor || i.h % factor)) return false
  group.columns = cols / factor
  group.rows = group.rows / factor
  if (group.columns === COLUMNS) delete group.columns
  for (const item of group.items) {
    item.x /= factor
    item.y /= factor
    item.w /= factor
    item.h /= factor
  }
  return true
}

/** Höchste Gruppen-Verschachtelung im Studio; das Backend begrenzt zusätzlich die Gesamttiefe (MAX_DEPTH) verbindlich. */
export const MAX_GROUP_NESTING = 4

/** Anzahl ineinander liegender Gruppenebenen unterhalb (und inklusive) des Inhalts. */
export function groupDepth(content: LayoutContent): number {
  if (content.type === 'group') return 1 + Math.max(0, ...content.items.map((i) => groupDepth(i.content)))
  // Eine Rotation zählt als eine Ebene (ihre Seiten sind Gruppen mit eigenem Raster).
  if (content.type === 'rotation') return 1 + Math.max(0, ...content.steps.map((s) => groupDepth(s.content)))
  return 0
}

/** Die Auswahl als Gruppeninhalt (ohne das Dokument zu verändern), z. B. um sie als eigenen Baustein zu speichern. */
export function selectionAsGroup(host: GridHost, ids: ReadonlySet<string>): { content: LayoutGroupContent; w: number; h: number } | null {
  const picked = host.items.filter((i) => ids.has(i.id))
  const box = boundingBox(picked)
  if (!picked.length || !box) return null
  const content: LayoutGroupContent = {
    type: 'group',
    rows: box.h,
    ...(box.w !== COLUMNS ? { columns: box.w } : {}),
    items: picked.map((i) => ({ ...cloneLayout(i), x: i.x - box.x, y: i.y - box.y })),
  }
  return { content, w: box.w, h: box.h }
}
