import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const apiClient = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  put: vi.fn(),
}))

vi.mock('./apiClient', () => ({ default: apiClient }))

import { getDashboardData } from './dashboard'
import {
  assignUnassignedUser,
  dismissUnassignedUser,
  getPendingAdminJoinRequests,
  type PendingAdminJoinRequest,
} from './joinRequests'

const adminRequest: PendingAdminJoinRequest = {
  id: 'ajr000000001',
  request_kind: 'admin',
  user_id: 'user00000001',
  name: 'Anna Antrag',
  requested_department_name: 'Pfadi Neu',
  created_at: '2026-10-01T10:00:00+00:00',
}

const unassignedUser: PendingAdminJoinRequest = {
  id: null,
  request_kind: 'unassigned_user',
  user_id: 'user00000002',
  name: 'Otto Ohne',
  requested_department_name: null,
  status: 'unassigned',
  created_at: '2026-10-02T10:00:00+00:00',
}

describe('Support-Queue API', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(globalThis, 'fetch')
  })

  afterEach(() => {
    // Alle Requests laufen über den apiClient (Axios), nie über fetch().
    expect(globalThis.fetch).not.toHaveBeenCalled()
    vi.restoreAllMocks()
  })

  it('liest offene Anfragen und Einträge „Benutzer ohne Zuordnung“ nur per GET', async () => {
    apiClient.get.mockResolvedValue({ data: [adminRequest, unassignedUser] })

    const result = await getPendingAdminJoinRequests('')

    expect(apiClient.get).toHaveBeenCalledWith('/api/join-requests/admin-request/pending', {
      params: { department_id: '' },
    })
    expect(apiClient.post).not.toHaveBeenCalled()
    expect(apiClient.patch).not.toHaveBeenCalled()
    expect(result.map((r) => r.request_kind)).toEqual(['admin', 'unassigned_user'])
    expect(result[1].id).toBeNull()
  })

  it('ordnet einen Benutzer ohne Zuordnung ausdrücklich per POST zu', async () => {
    apiClient.post.mockResolvedValue({ data: { success: true, status: 'assigned', assigned_role: 'u' } })

    await assignUnassignedUser('user/00000002', 'dept00000051')

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/join-requests/unassigned-users/user%2F00000002/assign',
      { target_department_id: 'dept00000051', target_role: 'u' },
    )
  })

  it('blendet einen Benutzer ohne Zuordnung ausdrücklich per POST aus', async () => {
    apiClient.post.mockResolvedValue({ data: { success: true, status: 'rejected' } })

    await dismissUnassignedUser('user00000002')

    expect(apiClient.post).toHaveBeenCalledWith('/api/join-requests/unassigned-users/user00000002/dismiss')
  })

  it('zählt im Dashboard echte Anfragen und Queue-Einträge, ohne zu schreiben', async () => {
    apiClient.get.mockImplementation((url: string) => {
      if (url === '/api/join-requests/admin-request/pending') {
        return Promise.resolve({ data: [adminRequest, unassignedUser] })
      }
      return Promise.resolve({ data: [] })
    })

    const data = await getDashboardData('dept00000051', { includeAdminJoinRequests: true })

    expect(data.pendingAdminJoinRequests).toHaveLength(2)
    expect(apiClient.post).not.toHaveBeenCalled()
    expect(apiClient.patch).not.toHaveBeenCalled()
  })
})
