import { describe, expect, it } from 'vitest'
import { isGrossanlassSetupAllowedPath } from './grossanlassSetupRoutes'

const D = 'dept123'

describe('Grossanlass setup navigation', () => {
  it('lets every member see the dashboard and the help', () => {
    for (const canSetup of [true, false]) {
      expect(isGrossanlassSetupAllowedPath(`/${D}`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/dashboard`, D, canSetup)).toBe(true)
      expect(isGrossanlassSetupAllowedPath(`/${D}/help/dokumentation`, D, canSetup)).toBe(true)
    }
  })

  it('gives MW, Co-MW and OK the three setup areas, settings and help', () => {
    for (const path of ['/einstellungen', '/einstellungen/stammdaten', '/einstellungen/ressorts', '/settings/users', '/settings/my-department']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, true), path).toBe(true)
    }
  })

  it('keeps the operational pages closed before release', () => {
    for (const path of ['/planung', '/material', '/mein-ressort', '/beschaffung', '/einstellungen/standorte', '/einstellungen/freigabe', '/tasks', '/notifications']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, true), path).toBe(false)
    }
  })

  it('gives other roles no setup pages', () => {
    for (const path of ['/einstellungen/stammdaten', '/settings/users', '/mein-ressort', '/material']) {
      expect(isGrossanlassSetupAllowedPath(`/${D}${path}`, D, false), path).toBe(false)
    }
  })

  it('ignores other departments and global pages', () => {
    expect(isGrossanlassSetupAllowedPath('/other/material', D, false)).toBe(true)
    expect(isGrossanlassSetupAllowedPath('/profile', D, false)).toBe(true)
    expect(isGrossanlassSetupAllowedPath(`/${D}x/material`, D, false)).toBe(true)
  })
})
