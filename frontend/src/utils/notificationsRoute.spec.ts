import { describe, expect, it } from 'vitest'
import { notificationsCenterPath } from '@/utils/notificationsRoute'

describe('notificationsCenterPath', () => {
  it('uses /dept for a normal department', () => {
    expect(notificationsCenterPath('d1', false)).toBe('/d1/dept/notifications')
  })

  it('uses /ga for a Grossanlass', () => {
    expect(notificationsCenterPath('d1', true)).toBe('/d1/ga/notifications')
  })
})
