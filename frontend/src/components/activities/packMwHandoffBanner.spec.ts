import { describe, expect, it } from 'vitest'
import {
  packMwHandoffBannerVisible,
  packMwIsActivityCreator,
} from '@/components/activities/packWorkflowRules'

describe('MW Handoff Banner §19.1', () => {
  it('MW = Ersteller → kein Handoff-Banner', () => {
    expect(packMwIsActivityCreator(true, 'user-1', 'user-1')).toBe(true)
    expect(packMwHandoffBannerVisible(true, true, 'packed', true)).toBe(false)
    expect(packMwHandoffBannerVisible(true, true, 'at_event', true)).toBe(false)
  })

  it('MW ≠ Ersteller + packed/at_event → Banner-Kandidat', () => {
    expect(packMwIsActivityCreator(true, 'creator', 'mw-user')).toBe(false)
    expect(packMwHandoffBannerVisible(true, true, 'packed', false)).toBe(true)
    expect(packMwHandoffBannerVisible(true, true, 'at_event', false)).toBe(true)
  })

  it('external / Gruppe ohne MW → kein Banner', () => {
    expect(packMwHandoffBannerVisible(true, false, 'packed', false)).toBe(false)
    expect(packMwHandoffBannerVisible(false, true, 'packed', false)).toBe(false)
  })

  it('returned → Emergency aus (Banner nur packed|at_event in UI)', () => {
    expect(packMwHandoffBannerVisible(true, true, 'returned', false)).toBe(false)
  })
})
