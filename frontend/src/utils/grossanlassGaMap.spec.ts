import { describe, expect, it } from 'vitest'
import {
  clampMapAxis,
  gaMapOverlayBounds,
  gaPlaceColor,
  gaPlaceKind,
  mapPointFromClick,
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
})
