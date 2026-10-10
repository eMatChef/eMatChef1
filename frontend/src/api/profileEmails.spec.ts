import { beforeEach, describe, expect, it, vi } from 'vitest'

const client = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }))
vi.mock('./apiClient', () => ({ default: client }))

import { getDepartmentEmailAssignments, getProfileEmails } from './profileEmails'

describe('profile email API paths (must match the Symfony routes under /api/profiles/{id}/emails)', () => {
  beforeEach(() => {
    Object.values(client).forEach((f) => f.mockReset())
    client.get.mockResolvedValue({ data: {} })
  })

  it('reads the department email assignments with GET', async () => {
    await getDepartmentEmailAssignments('p1')
    expect(client.get).toHaveBeenCalledWith('/api/profiles/p1/emails/department-assignments')
  })

  it('reads the email list with GET', async () => {
    await getProfileEmails('p1')
    expect(client.get).toHaveBeenCalledWith('/api/profiles/p1/emails')
  })
})
