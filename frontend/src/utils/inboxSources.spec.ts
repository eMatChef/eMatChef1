import { beforeEach, describe, expect, it, vi } from 'vitest'

const api = vi.hoisted(() => ({
  getReceivedDepartmentInvites: vi.fn(),
  getPendingDepartmentActivityInvites: vi.fn(),
  getInviteNotifications: vi.fn(),
  getPublicFoundMessages: vi.fn(),
  getActivityMwNotifications: vi.fn(),
  getUserActivityStatusNotifications: vi.fn(),
  getUserDirectMessages: vi.fn(),
}))

vi.mock('@/api/joinRequests', () => ({
  getReceivedDepartmentInvites: api.getReceivedDepartmentInvites,
  getPendingDepartmentActivityInvites: api.getPendingDepartmentActivityInvites,
  getInviteNotifications: api.getInviteNotifications,
}))
vi.mock('@/api/publicFoundMessages', () => ({ getPublicFoundMessages: api.getPublicFoundMessages }))
vi.mock('@/api/activityNotifications', () => ({ getActivityMwNotifications: api.getActivityMwNotifications }))
vi.mock('@/api/activityUserNotifications', () => ({
  getUserActivityStatusNotifications: api.getUserActivityStatusNotifications,
}))
vi.mock('@/api/inboxMessages', () => ({ getUserDirectMessages: api.getUserDirectMessages }))

import { loadInboxSources, type InboxSourceOptions } from '@/utils/inboxSources'

const base: InboxSourceOptions = {
  bucket: 'all',
  limit: 200,
  qrBucket: 'all',
  includeQr: true,
  includeActivityMw: true,
  includeInviteAccepted: true,
  includeCampInvites: true,
}

describe('loadInboxSources', () => {
  beforeEach(() => {
    Object.values(api).forEach((fn) => fn.mockReset())
    api.getReceivedDepartmentInvites.mockResolvedValue({
      count: 3,
      unread_count: 2,
      items: [
        { id: 'a', type: 'department_invite' },
        { id: 'b', type: 'grossanlass_mw_assigned' },
        { id: 'c', type: 'grossanlass_round_opened' },
      ],
    })
    api.getUserDirectMessages.mockResolvedValue({ unread_count: 1, items: [{ id: 'm1' }] })
    api.getPendingDepartmentActivityInvites.mockResolvedValue({ count: 1, items: [{ id: 'camp' }] })
    api.getPublicFoundMessages.mockResolvedValue({ unread_count: 4, items: [{ id: 'qr' }] })
    api.getActivityMwNotifications.mockResolvedValue({ unread_count: 5, items: [{ id: 'mw' }] })
    api.getUserActivityStatusNotifications.mockResolvedValue({ unread_count: 6, items: [{ id: 'us' }] })
    api.getInviteNotifications.mockResolvedValue([{ id: 'acc' }])
  })

  it('splits received notifications by type, GA messages included', async () => {
    const s = await loadInboxSources('d1', base)
    expect(s.departmentInvites.map((i) => i.id)).toEqual(['a'])
    expect(s.grossanlassMwAssigned.map((i) => i.id)).toEqual(['b'])
    expect(s.grossanlassRoundOpened.map((i) => i.id)).toEqual(['c'])
    expect(s.receivedInvites.unreadCount).toBe(2)
    expect(s.userMessages.items).toHaveLength(1)
    expect(s.found.unreadCount).toBe(4)
  })

  it('passes bucket and limit through and skips sources the role may not see', async () => {
    const s = await loadInboxSources('d1', {
      ...base,
      bucket: 'unread',
      limit: 5,
      qrBucket: 'open',
      includeQr: false,
      includeActivityMw: false,
      includeInviteAccepted: false,
      includeCampInvites: false,
    })
    expect(api.getReceivedDepartmentInvites).toHaveBeenCalledWith({ bucket: 'unread', limit: 5 })
    expect(api.getUserDirectMessages).toHaveBeenCalledWith('d1', { bucket: 'unread', limit: 5 })
    expect(api.getPublicFoundMessages).not.toHaveBeenCalled()
    expect(api.getActivityMwNotifications).not.toHaveBeenCalled()
    expect(api.getInviteNotifications).not.toHaveBeenCalled()
    expect(api.getPendingDepartmentActivityInvites).not.toHaveBeenCalled()
    expect(s.found.items).toEqual([])
    expect(s.activityMw.items).toEqual([])
    expect(s.inviteAccepted).toEqual([])
    expect(s.campInvites).toEqual([])
  })

  it('uses the QR status bucket for QR messages', async () => {
    await loadInboxSources('d1', { ...base, qrBucket: 'open' })
    expect(api.getPublicFoundMessages).toHaveBeenCalledWith('d1', { bucket: 'open', limit: 200 })
  })

  it('degrades a failing source to empty without failing the others', async () => {
    api.getUserDirectMessages.mockRejectedValue(new Error('500'))
    api.getActivityMwNotifications.mockRejectedValue(new Error('403'))
    const s = await loadInboxSources('d1', base)
    expect(s.userMessages.items).toEqual([])
    expect(s.activityMw.items).toEqual([])
    expect(s.grossanlassMwAssigned).toHaveLength(1)
    expect(s.activityUser.items).toHaveLength(1)
  })
})
