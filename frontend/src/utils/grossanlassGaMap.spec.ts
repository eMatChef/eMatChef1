import { describe, expect, it } from 'vitest'
import {
  boundsFromCornerDragWithAspect,
  boundsWithAspectRatio,
  clampMapAxis,
  gaMapOverlayBounds,
  GA_PLACE_CREATE_KINDS,
  gaMapOverlayOpacity,
  gaPlaceColor,
  gaPlaceCreateKinds,
  gaPlaceKind,
  mapPointFromClick,
  overlayImageAspectRatio,
} from '@/utils/grossanlassGaMap'
import type { GaMap } from '@/api/grossanlassLogistics'

describe('grossanlassGaMap', () => {
  it('keeps pins on the board', () => {
    expect(clampMapAxis(-1)).toBe(0)
    expect(clampMapAxis(0.4)).toBe(0.4)
    expect(clampMapAxis(2)).toBe(1)
  })

  it('maps a click to 0–1 coordinates', () => {
    expect(mapPointFromClick({ left: 10, top: 20, width: 100, height: 50 }, 60, 45)).toEqual({
      x: 0.5,
      y: 0.5,
    })
  })

  it('builds bounds that match the image aspect ratio', () => {
    const bounds = boundsWithAspectRatio(47.05, 8.3, 1600, 900)
    expect(bounds).not.toBeNull()
    const latSpan = bounds!.north - bounds!.south
    const lngSpan = bounds!.east - bounds!.west
    const cosLat = Math.cos((47.05 * Math.PI) / 180)
    expect((lngSpan * cosLat) / latSpan).toBeCloseTo(1600 / 900, 5)
  })

  it('preserves aspect ratio when dragging a corner', () => {
    const current = { north: 47.1, south: 47.08, east: 8.32, west: 8.28 }
    const next = boundsFromCornerDragWithAspect('nw', 47.12, 8.27, current, 800, 600)
    expect(next).not.toBeNull()
    const latSpan = next!.north - next!.south
    const lngSpan = next!.east - next!.west
    const cosLat = Math.cos(((next!.north + next!.south) / 2) * (Math.PI / 180))
    expect((lngSpan * cosLat) / latSpan).toBeCloseTo(800 / 600, 5)
  })

  it('exposes overlay bounds only when the plan is georeferenced', () => {
    const base: GaMap = {
      id: 'map1',
      name: 'Gelände',
      image_url: '/media/plan.jpg',
      image_width: 800,
      image_height: 600,
      places: [],
    }
    expect(gaMapOverlayBounds(base)).toBeNull()
    expect(
      gaMapOverlayBounds({
        ...base,
        bounds_north: 47.4,
        bounds_south: 47.3,
        bounds_east: 8.6,
        bounds_west: 8.5,
      }),
    ).toEqual({ north: 47.4, south: 47.3, east: 8.6, west: 8.5 })
  })

  it('colors GA kinds without colliding with the venue pin', () => {
    expect(gaPlaceKind('anfahrt')).toBe('anfahrt')
    expect(gaPlaceColor('anfahrt')).toBe('#0ea5e9')
    expect(gaPlaceColor('bauprojekt')).toBe('#d97706')
  })

  it('does not offer matplatz as a new GA place kind', () => {
    expect(GA_PLACE_CREATE_KINDS).not.toContain('matplatz')
    expect(gaPlaceCreateKinds()).toEqual(['bauprojekt', 'anfahrt', 'unterlager', 'poi'])
    expect(gaPlaceCreateKinds('matplatz')).toContain('matplatz')
  })

  it('derives overlay opacity for view and edit modes', () => {
    expect(gaMapOverlayOpacity(0.92, false)).toBe(0.92)
    expect(gaMapOverlayOpacity(0.92, true)).toBeCloseTo(0.78)
    expect(gaMapOverlayOpacity(null, false)).toBe(0.92)
    expect(gaMapOverlayOpacity(0.35, false)).toBe(0.35)
  })
})
