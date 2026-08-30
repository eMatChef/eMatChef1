import { describe, expect, it } from 'vitest'
import { gaHomeKind, gaHomePath, gaIsRoleHomePath } from '@/utils/grossanlassHome'

describe('gaHomePath', () => {
  it('maps department roles to their default home', () => {
    expect(gaHomeKind('mw')).toBe('dashboard')
    expect(gaHomeKind('cmw')).toBe('dashboard')
    expect(gaHomeKind('dc')).toBe('uebersicht')
    expect(gaHomeKind('komm')).toBe('mailbox')
    expect(gaHomeKind('spon')).toBe('mailbox')
    expect(gaHomeKind('u')).toBe('mein-bereich')
    expect(gaHomePath('dep-1', 'mw')).toBe('/dep-1')
    expect(gaHomePath('dep-1', 'cmw')).toBe('/dep-1')
    expect(gaHomePath('dep-1', 'dc')).toBe('/dep-1/material-uebersicht')
    expect(gaHomePath('dep-1', 'komm')).toBe('/dep-1/beschaffung/anfragen')
    expect(gaHomePath('dep-1', 'spon')).toBe('/dep-1/beschaffung/anfragen')
    expect(gaHomePath('dep-1', 'u')).toBe('/dep-1/mein-ressort')
  })

  it('treats nested overview/mailbox paths as role home', () => {
    expect(gaIsRoleHomePath('dep-1', 'dc', '/dep-1/material-uebersicht/einsaetze')).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'komm', '/dep-1/beschaffung/anfragen')).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'mw', '/dep-1/material-uebersicht')).toBe(false)
    expect(gaIsRoleHomePath('dep-1', 'u', '/dep-1/mein-ressort')).toBe(true)
  })
})
