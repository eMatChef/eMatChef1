import { describe, expect, it } from 'vitest'
import {
  gaCanApproveEinsatz,
  gaCanSeeMaterialUebersicht,
  gaDeptRoleSkipsGroupFlags,
  gaDeptStageBadge,
  gaIsBereichsleitung,
  gaIsChefFromGroups,
  gaIsHelperHomeView,
  GA_MATERIALS_ROUTE_ROLES,
} from '@/utils/grossanlassAccess'

describe('ga Bereichsleitung access', () => {
  it('treats membership role bl as Bereichsleitung', () => {
    expect(gaIsBereichsleitung('bl')).toBe(true)
    expect(gaIsBereichsleitung('u')).toBe(false)
    expect(gaIsBereichsleitung('dc')).toBe(false)
  })

  it('treats a group leader as chef, not as system role', () => {
    expect(
      gaIsChefFromGroups('u1', [
        { members: [{ user_id: 'u1', is_leader: true }] },
      ]),
    ).toBe(true)
    expect(
      gaIsChefFromGroups('u1', [
        { members: [{ user_id: 'u1', is_leader: false }] },
      ]),
    ).toBe(false)
  })

  it('keeps helper home for helfer even with a star', () => {
    expect(gaIsHelperHomeView('u', false)).toBe(true)
    expect(gaIsHelperHomeView('u', true)).toBe(true)
    expect(gaIsHelperHomeView('bl', true)).toBe(false)
    expect(gaIsHelperHomeView('dc', true)).toBe(false)
  })

  it('opens material overview for OK and Bereichsleitung', () => {
    expect(gaCanSeeMaterialUebersicht('dc')).toBe(true)
    expect(gaCanSeeMaterialUebersicht('bl')).toBe(true)
    expect(gaCanSeeMaterialUebersicht('u', true)).toBe(false)
    expect(gaCanSeeMaterialUebersicht('u', false)).toBe(false)
  })

  it('lets OK approve einsätze; BL submits for approval', () => {
    expect(gaCanApproveEinsatz('dc')).toBe(true)
    expect(gaCanApproveEinsatz('bl')).toBe(false)
    expect(gaCanApproveEinsatz('mw')).toBe(true)
  })

  it('opens Stammdaten-Materialien routes for Bereichsleitung', () => {
    expect(GA_MATERIALS_ROUTE_ROLES).toContain('bl')
    expect(GA_MATERIALS_ROUTE_ROLES).not.toContain('u')
  })

  it('skips group flags for top department roles', () => {
    expect(gaDeptRoleSkipsGroupFlags('mw')).toBe(true)
    expect(gaDeptRoleSkipsGroupFlags('cmw')).toBe(true)
    expect(gaDeptRoleSkipsGroupFlags('dc')).toBe(false)
    expect(gaDeptRoleSkipsGroupFlags('bl')).toBe(false)
    expect(gaDeptRoleSkipsGroupFlags('u')).toBe(false)
    expect(gaDeptRoleSkipsGroupFlags('komm')).toBe(false)
  })

  it('maps dept stage badges for grossanlass roles', () => {
    expect(gaDeptStageBadge('mw')).toEqual({ short: 'MW', role: 'mw' })
    expect(gaDeptStageBadge('cmw')).toEqual({ short: 'CMW', role: 'cmw' })
    expect(gaDeptStageBadge('dc')).toEqual({ short: 'OK', role: 'dc' })
    expect(gaDeptStageBadge('bl')).toEqual({ short: 'BL', role: 'bl' })
    expect(gaDeptStageBadge('komm')).toEqual({ short: 'KOM', role: 'komm' })
    expect(gaDeptStageBadge('spon')).toEqual({ short: 'SPON', role: 'spon' })
    expect(gaDeptStageBadge('u')).toEqual({ short: 'H', role: 'u' })
    expect(gaDeptStageBadge('l1')).toBeNull()
  })
})
