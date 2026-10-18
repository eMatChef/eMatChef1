import { describe, expect, it } from 'vitest'
import type { LayoutGroupContent, LayoutItem, LayoutPage } from '@/api/displayLayout'
import {
  addRotationPage,
  duplicateRotationPage,
  ensureStepGroup,
  moveRotationPage,
  newRotation,
  stepToken,
  coarsenGroupGrid,
  copyItems,
  groupDepth,
  groupItems,
  hostRatio,
  hostShare,
  isFree,
  pasteItems,
  placeItem,
  planMove,
  planResize,
  refineGroupGrid,
  resolveHost,
  ungroupItem,
} from './studioModel'

const w = (type: string) => ({ type: 'widget' as const, widget: type, widget_version: 1, config: {} })
const item = (id: string, x: number, y: number, ww: number, h: number, widget = 'text'): LayoutItem => ({ id, x, y, w: ww, h, content: w(widget) })
const page = (items: LayoutItem[], rows = 12, columns?: number): LayoutPage => ({ id: 'p', name: 'P', rows, ...(columns ? { columns } : {}), items })

describe('Kollisionen und Raster', () => {
  it('isFree prüft Rand und Überlappung', () => {
    const p = page([item('a', 0, 0, 6, 6)])
    expect(isFree(p, { x: 6, y: 0, w: 6, h: 6 })).toBe(true)
    expect(isFree(p, { x: 5, y: 0, w: 2, h: 2 })).toBe(false)
    expect(isFree(p, { x: 11, y: 0, w: 2, h: 2 })).toBe(false)
    expect(isFree(p, { x: 0, y: 0, w: 6, h: 6 }, new Set(['a']))).toBe(true)
  })

  it('respektiert eine abweichende Spaltenzahl', () => {
    const p = page([], 12, 24)
    expect(isFree(p, { x: 20, y: 0, w: 4, h: 2 })).toBe(true)
    expect(isFree(p, { x: 22, y: 0, w: 4, h: 2 })).toBe(false)
  })

  it('planMove gleitet entlang einer Kante statt zu überlappen', () => {
    const a = item('a', 0, 0, 4, 4)
    const b = item('b', 4, 0, 4, 4)
    const p = page([a, b])
    const origins = new Map([['a', { x: 0, y: 0, w: 4, h: 4 }]])
    // Nach rechts blockiert durch b, nach unten frei: Gleiten in y.
    const slid = planMove(p, origins, 1, 2, { dx: 0, dy: 0 })
    expect(slid).toEqual({ dx: 0, dy: 2, exact: false })
    const blocked = planMove(p, origins, 1, 0, { dx: 0, dy: 0 })
    expect(blocked).toEqual({ dx: 0, dy: 0, exact: false })
  })

  it('planMove verschiebt eine Mehrfachauswahl gemeinsam und lässt Fremde unberührt', () => {
    const a = item('a', 0, 0, 2, 2)
    const b = item('b', 2, 0, 2, 2)
    const c = item('c', 6, 0, 2, 2)
    const p = page([a, b, c])
    const origins = new Map([['a', { x: 0, y: 0, w: 2, h: 2 }], ['b', { x: 2, y: 0, w: 2, h: 2 }]])
    expect(planMove(p, origins, 2, 0, { dx: 0, dy: 0 })).toEqual({ dx: 2, dy: 0, exact: true })
    expect(planMove(p, origins, 3, 0, { dx: 0, dy: 0 })).toEqual({ dx: 0, dy: 0, exact: false })
  })

  it('planResize verweigert Überlappung, Rand und Mindestgrösse', () => {
    const a = item('a', 0, 0, 4, 4)
    const b = item('b', 6, 0, 4, 4)
    const p = page([a, b])
    const o = { x: 0, y: 0, w: 4, h: 4 }
    expect(planResize(p, 'a', o, 'e', 2, 0)).toEqual({ x: 0, y: 0, w: 6, h: 4 })
    expect(planResize(p, 'a', o, 'e', 3, 0)).toBeNull()
    expect(planResize(p, 'a', o, 'w', -1, 0)).toBeNull()
    expect(planResize(p, 'a', o, 'e', -3, 0, 2, 1)).toBeNull()
    expect(planResize(p, 'a', o, 'se', 0, 2)).toEqual({ x: 0, y: 0, w: 4, h: 6 })
  })
})

describe('Einfügen, Kopieren, Löschen', () => {
  it('placeItem nutzt den Wunschort, sonst die erste freie Stelle', () => {
    const p = page([item('a', 0, 0, 6, 6)])
    expect(placeItem(p, w('clock'), [3, 2], { x: 8, y: 0 })).toMatchObject({ x: 8, y: 0, w: 3, h: 2 })
    expect(placeItem(p, w('clock'), [3, 2], { x: 1, y: 1 })).toMatchObject({ x: 6, y: 2 })
  })

  it('placeItem meldet fehlenden Platz', () => {
    const p = page([item('a', 0, 0, 12, 12)])
    expect(placeItem(p, w('clock'), [3, 2])).toBeNull()
  })

  it('copy/paste erhält die relative Lage und vergibt neue IDs', () => {
    const p = page([item('a', 0, 0, 2, 2), item('b', 2, 0, 2, 2)])
    const clip = copyItems(p, new Set(['a', 'b']))!
    const pasted = pasteItems(p, clip)!
    expect(pasted).toHaveLength(2)
    expect(new Set(p.items.map((i) => i.id)).size).toBe(4)
    expect(pasted[1]!.x - pasted[0]!.x).toBe(2)
    expect(p.items.every((i, idx) => p.items.every((o, j) => idx === j || !(i.x < o.x + o.w && o.x < i.x + i.w && i.y < o.y + o.h && o.y < i.y + i.h)))).toBe(true)
  })
})

describe('Gruppen', () => {
  it('groupItems bildet ein eigenes Raster und erhält die relative Lage', () => {
    const p = page([item('a', 2, 3, 3, 2), item('b', 5, 3, 2, 4), item('c', 9, 9, 2, 2)])
    const group = groupItems(p, new Set(['a', 'b']), 'Material')!
    expect(p.items.map((i) => i.id).sort()).toEqual([group.id, 'c'].sort())
    expect(group).toMatchObject({ x: 2, y: 3, w: 5, h: 4 })
    const inner = group.content as LayoutGroupContent
    expect(inner).toMatchObject({ type: 'group', name: 'Material', rows: 4, columns: 5 })
    expect(inner.items.map((i) => [i.id, i.x, i.y, i.w, i.h])).toEqual([['a', 0, 0, 3, 2], ['b', 3, 0, 2, 4]])
  })

  it('ungroupItem stellt die Elemente am selben Ort wieder her', () => {
    const p = page([item('a', 2, 3, 3, 2), item('b', 5, 3, 2, 4)])
    const group = groupItems(p, new Set(['a', 'b']), '')!
    const restored = ungroupItem(p, group.id)!
    expect(restored.map((i) => [i.x, i.y, i.w, i.h])).toEqual([[2, 3, 3, 2], [5, 3, 2, 4]])
    expect(p.items).toHaveLength(2)
  })

  it('ungroupItem skaliert eine vergrösserte Gruppe ohne Verlust', () => {
    const p = page([item('a', 0, 0, 2, 2), item('b', 2, 0, 2, 2)])
    const group = groupItems(p, new Set(['a', 'b']), '')!
    group.w = 8
    group.h = 4
    const restored = ungroupItem(p, group.id)!
    expect(restored.map((i) => [i.x, i.w])).toEqual([[0, 4], [4, 4]])
  })

  it('ungroupItem verweigert, wenn eine verkleinerte Gruppe Elemente verschmelzen würde', () => {
    const p = page([item('a', 0, 0, 1, 1), item('b', 1, 0, 1, 1), item('c', 2, 0, 1, 1), item('d', 3, 0, 1, 1)])
    const group = groupItems(p, new Set(['a', 'b', 'c', 'd']), '')!
    group.w = 2
    expect(ungroupItem(p, group.id)).toBeNull()
    expect(p.items).toHaveLength(1)
  })

  it('refine/coarsen sind umkehrbar', () => {
    const p = page([item('a', 0, 0, 2, 2), item('b', 2, 0, 2, 2)])
    const group = groupItems(p, new Set(['a', 'b']), '')!.content as LayoutGroupContent
    expect(refineGroupGrid(group, 2)).toBe(true)
    expect(group).toMatchObject({ columns: 8, rows: 4 })
    expect(group.items[1]).toMatchObject({ x: 4, w: 4 })
    expect(coarsenGroupGrid(group, 2)).toBe(true)
    expect(group).toMatchObject({ columns: 4, rows: 2 })
    expect(refineGroupGrid(group, 13)).toBe(false)
  })

  it('resolveHost und hostRatio folgen dem Gruppenpfad', () => {
    const p = page([item('a', 0, 0, 6, 6), item('b', 6, 0, 6, 6)])
    const group = groupItems(p, new Set(['a', 'b']), '')!
    const resolved = resolveHost(p, [group.id])!
    expect(resolved.host).toBe(group.content)
    // Die Gruppe belegt die volle Breite, aber nur die halbe Höhe der Seite.
    expect(hostRatio(16 / 9, p, resolved.trail)).toBeCloseTo(32 / 9)
    expect(resolveHost(p, ['missing'])).toBeNull()
    expect(groupDepth(group.content)).toBe(1)
  })
})

describe('Rotation als Layout-Container', () => {
  const rotationItem = () => {
    const rot = newRotation(20, 'Seite 1')
    addRotationPage(rot, 'Seite 2')
    return { rot, item: { id: 'i-r', x: 0, y: 0, w: 6, h: 6, content: rot } as LayoutItem }
  }

  it('löst Rotationsseiten als eigene Rasterflächen auf und rechnet den Anteil richtig', () => {
    const { rot, item } = rotationItem()
    const p = page([item])
    const resolved = resolveHost(p, [stepToken('i-r', 1)])!
    expect(resolved.host).toBe(rot.steps[1]!.content)
    expect(resolved.trail[0]).toMatchObject({ rotationIndex: 1 })
    // Die Rotation belegt die halbe Breite und Höhe der Seite; ihre Seite füllt diese Fläche.
    expect(hostRatio(16 / 9, p, resolved.trail)).toBeCloseTo(16 / 9)
    expect(hostShare(p, resolved.trail)).toEqual({ w: 0.5, h: 0.5 })
    expect(resolveHost(p, [stepToken('i-r', 5)])).toBeNull()
    expect(resolveHost(p, ['i-r'])).toBeNull()
  })

  it('verwaltet Rotationsseiten: sortieren, duplizieren, Widget-Schritte erst auf Wunsch in Seiten wandeln', () => {
    const { rot } = rotationItem()
    expect(moveRotationPage(rot, 1, -1)).toBe(0)
    expect(rot.steps.map((s) => s.name)).toEqual(['Seite 2', 'Seite 1'])
    expect(duplicateRotationPage(rot, 0, 'Kopie')).toBe(1)
    expect(rot.steps).toHaveLength(3)
    expect(rot.steps[1]!.content).not.toBe(rot.steps[0]!.content)
    // Alter Schritt mit einem Widget: bleibt unverändert, bis «Seite gestalten» ihn in eine Gruppe übernimmt.
    rot.steps.push({ duration_sec: 7, content: w('ga_pack_progress') })
    expect(rot.steps[3]!.content.type).toBe('widget')
    const group = ensureStepGroup(rot, 3)!
    expect(group.items[0]).toMatchObject({ x: 0, y: 0, w: 12, h: 12, content: { widget: 'ga_pack_progress' } })
    expect(rot.steps[3]!.duration_sec).toBe(7)
  })
})
