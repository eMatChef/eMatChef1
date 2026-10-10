import { describe, expect, it } from 'vitest'
import { isGrossanlassSetupAllowedPath } from './grossanlassSetupRoutes'

const D = 'dept123'

describe('Grossanlass setup navigation', () => {
  it('lets every member see the dashboard and the help', () => {
    for (const canSetup of [true, false]) {
      expect(isGrossanlassSetupAllowedPath(`/${D}`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/ga/dashboard`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/dept/help/department`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/dept/dashboard`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/ga/help/ga`, D, canSetup)).toBe(true)
    }
  })

  it('gives MW, Co-MW and OK the three setup areas', () => {
    for (const path of ['/ga/activity-settings', '/ga/activity-settings/general', '/ga/activity-settings/units', '/ga/activity-settings/users']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, true), path).toBe(true)
    }
  })

  it('keeps the operational pages closed before release', () => {
    for (const path of ['/ga/planning', '/ga/material', '/ga/my-unit', '/ga/procurement', '/ga/activity-settings/locations', '/ga/activity-settings/approval', '/ga/settings', '/ga/settings/my-department', '/ga/settings/user-karten', '/dept/settings', '/dept/settings/users', '/dept/tasks', '/dept/notifications']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, true), path).toBe(false)
    }
  })

  it('keeps pages outside the department (profile) reachable', () => {
    for (const canSetup of [true, false]) {
      expect(isGrossanlassSetupAllowedPath('/profile', D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath('/profile/security', D, canSetup)).toBe(true)
    }
  })

  it('gives other roles no setup pages', () => {
    for (const path of ['/ga/activity-settings/general', '/dept/settings/users', '/ga/my-unit', '/ga/material']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, false), path).toBe(false)
    }
  })

  it('ignores other departments and global pages', () => {
    expect(isGrossanlassSetupAllowedPath('/other/ga/material', D, false)).toBe(true)
    expect(isGrossanlassSetupAllowedPath('/profile', D, false)).toBe(true)
    expect(isGrossanlassSetupAllowedPath(`/${D}x/ga/material`, D, false)).toBe(true)
  })
})
