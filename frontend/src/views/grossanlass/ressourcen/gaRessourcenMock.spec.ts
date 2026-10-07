import { beforeEach, describe, expect, it } from 'vitest'
import { anlassAt, resetGaAufgabenDemo } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import {
  GA_LOGISTICS_RESOURCE_TYPES,
  acceptProposal,
  addNeed,
  emptyResourceDraft,
  leaveOpen,
  needById,
  needsOfOrder,
  planAtOtherTime,
  slotIssues,
  statusCountsFor,
  resetGaRessourcenDemo,
  useGaRessourcenMock,
} from './gaRessourcenMock'

describe('gaRessourcenMock', () => {
  beforeEach(() => {
    resetGaRessourcenDemo()
    resetGaAufgabenDemo()
  })

  it('holds the Bar West crane example with a demo proposal', () => {
    const kran = needById('rn-kran')!
    expect(kran.durationH).toBe(2)
    expect(kran.flexible).toBe(true)
    expect(kran.status).toBe('proposal')
    expect(kran.proposal?.reason).toContain('Bühne Nord')
    expect(kran.proposal?.from.getHours()).toBe(10)
    expect(statusCountsFor('ao-bar-west')).toEqual({ need: 3, proposal: 1, planned: 1 })
  })

  it('accepts the proposal and plans the resource', () => {
    expect(acceptProposal('rn-kran')).toBe(true)
    const kran = needById('rn-kran')!
    expect(kran.status).toBe('planned')
    expect(kran.planned?.from.getMinutes()).toBe(30)
    expect(kran.proposal).toBeNull()
    expect(acceptProposal('rn-kran')).toBe(false)
  })

  it('validates another time against window, duration and known bookings', () => {
    const kran = needById('rn-kran')!
    const bad = slotIssues(kran, { from: anlassAt(4, 9), to: anlassAt(4, 11) })
    expect(bad.map((entry) => entry.issue)).toEqual(['conflict'])
    expect(slotIssues(kran, { from: anlassAt(2, 9), to: anlassAt(2, 11) }).map((entry) => entry.issue)).toContain('outsideWindow')
    expect(slotIssues(kran, { from: anlassAt(4, 13), to: anlassAt(4, 14) }).map((entry) => entry.issue)).toContain('tooShort')
    expect(slotIssues(kran, { from: anlassAt(4, 10), to: anlassAt(4, 12) }, anlassAt(4, 11)).map((entry) => entry.issue)).toContain('beforeDependency')
    expect(planAtOtherTime('rn-kran', anlassAt(4, 9)).ok).toBe(false)
    const ok = planAtOtherTime('rn-kran', anlassAt(4, 14))
    expect(ok.ok).toBe(true)
    expect(kran.status).toBe('planned')
    expect(kran.planned?.to.getHours()).toBe(16)
  })

  it('can leave a need open again and adds new needs for the logistics view', () => {
    expect(leaveOpen('rn-stapler')).toBe(true)
    expect(needById('rn-stapler')!.status).toBe('need')
    expect(leaveOpen('rn-stapler')).toBe(false)
    const added = addNeed('ao-pagode', { ...emptyResourceDraft(anlassAt(2, 8), anlassAt(2, 16)), type: 'trailer', label: 'Anhänger 4' })
    expect(needsOfOrder('ao-pagode').map((entry) => entry.id)).toContain(added.id)
    const forLogistics = useGaRessourcenMock().needs.value.filter((entry) => GA_LOGISTICS_RESOURCE_TYPES.includes(entry.type))
    expect(forLogistics.some((entry) => entry.type === 'helper')).toBe(false)
    expect(forLogistics.length).toBeGreaterThan(3)
  })
})
