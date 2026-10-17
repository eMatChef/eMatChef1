import { beforeEach, describe, expect, it, vi } from 'vitest'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('./apiClient', () => ({ default: http }))

import {
  addGaHelperAvailability,
  assignGaHelper,
  cancelGaAssignment,
  createGaGroupStaffingNeed,
  getGaStaffingConflicts,
  getGaStaffingOverview,
  getGaStaffingSchedule,
  listGaHelpers,
  staffingConflictOf,
  updateGaAssignment,
} from './grossanlassStaffing'

const base = '/api/departments/d1/grossanlass'

describe('grossanlassStaffing api', () => {
  beforeEach(() => {
    for (const fn of Object.values(http)) fn.mockReset().mockResolvedValue({ data: {} })
  })

  it('uses the grossanlass department endpoints', async () => {
    await listGaHelpers('d1', { status: 'archived' })
    expect(http.get).toHaveBeenCalledWith(`${base}/helpers`, { params: { status: 'archived' } })
    await addGaHelperAvailability('d1', 'h1', '2027-06-01T08:00:00', '2027-06-01T12:00:00')
    expect(http.post).toHaveBeenCalledWith(`${base}/helpers/h1/availability`, { starts_at: '2027-06-01T08:00:00', ends_at: '2027-06-01T12:00:00' })
    await getGaStaffingOverview('d1', { group_ids: 'g1' })
    expect(http.get).toHaveBeenCalledWith(`${base}/staffing/overview`, { params: { group_ids: 'g1' } })
    await getGaStaffingSchedule('d1', 'a', 'b')
    expect(http.get).toHaveBeenCalledWith(`${base}/staffing/schedule`, { params: { from: 'a', to: 'b' } })
    await getGaStaffingConflicts('d1')
    expect(http.get).toHaveBeenCalledWith(`${base}/staffing/conflicts`)
    await createGaGroupStaffingNeed('d1', 'g1', { required_count: 2 })
    expect(http.post).toHaveBeenCalledWith(`${base}/groups/g1/staffing-needs`, { required_count: 2 })
  })

  it('assigns, changes and cancels with the assignment endpoints', async () => {
    await assignGaHelper('d1', { need_id: 'n1', helper_id: 'h1', override_reason: 'Pendelt' })
    expect(http.post).toHaveBeenCalledWith(`${base}/staffing/assignments`, { need_id: 'n1', helper_id: 'h1', override_reason: 'Pendelt' })
    await updateGaAssignment('d1', 'a1', { starts_at: 'x', ends_at: 'y', version: 3 })
    expect(http.patch).toHaveBeenCalledWith(`${base}/staffing/assignments/a1`, { starts_at: 'x', ends_at: 'y', version: 3 })
    await cancelGaAssignment('d1', 'a1')
    expect(http.delete).toHaveBeenCalledWith(`${base}/staffing/assignments/a1`)
  })

  it('recognises a staffing conflict (409) and nothing else', () => {
    const conflict = { error: 'x', code: 'staffing_conflict', conflict: { kind: 'double_booking', override_possible: true } }
    expect(staffingConflictOf({ response: { status: 409, data: conflict } })?.conflict.kind).toBe('double_booking')
    expect(staffingConflictOf({ response: { status: 400, data: conflict } })).toBeNull()
    expect(staffingConflictOf({ response: { status: 409, data: { code: 'availability_conflict' } } })).toBeNull()
    expect(staffingConflictOf(new Error('network'))).toBeNull()
    expect(staffingConflictOf(null)).toBeNull()
  })
})
