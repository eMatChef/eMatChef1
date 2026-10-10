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
      expect(isGrossanlassSetupAllowedPath(`/${D}/ga-hilfe`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/ga-hilfe/freigabe`, D, canSetup)).toBe(true)
    }
  })

  it('gives MW, Co-MW and OK the three setup areas, settings and help', () => {
    for (const path of ['/ga/einstellungen', '/ga/einstellungen/stammdaten', '/ga/einstellungen/ressorts', '/dept/settings/users', '/dept/settings/my-department']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, true), path).toBe(true)
    }
  })

  it('keeps the operational pages closed before release', () => {
    for (const path of ['/ga/planung', '/ga/material', '/ga/mein-ressort', '/ga/beschaffung', '/ga/einstellungen/standorte', '/ga/einstellungen/freigabe', '/dept/tasks', '/dept/notifications']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, true), path).toBe(false)
    }
  })

  it('gives other roles no setup pages', () => {
    for (const path of ['/ga/einstellungen/stammdaten', '/dept/settings/users', '/ga/mein-ressort', '/ga/material']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, false), path).toBe(false)
    }
  })

  it('ignores other departments and global pages', () => {
    expect(isGrossanlassSetupAllowedPath('/other/ga/material', D, false)).toBe(true)
    expect(isGrossanlassSetupAllowedPath('/profile', D, false)).toBe(true)
    expect(isGrossanlassSetupAllowedPath(`/${D}x/ga/material`, D, false)).toBe(true)
  })
})
