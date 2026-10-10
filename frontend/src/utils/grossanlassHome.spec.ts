import { describe, expect, it } from 'vitest'
import { gaHomeKind, gaHomePath, gaIsRoleHomePath } from '@/utils/grossanlassHome'

describe('gaHomePath', () => {
  it('maps department roles to their default home', () => {
    expect(gaHomeKind('mw')).toBe('dashboard')
    expect(gaHomeKind('cmw')).toBe('dashboard')
    expect(gaHomeKind('lw')).toBe('dashboard')
    expect(gaHomeKind('clw')).toBe('dashboard')
    expect(gaHomeKind('dc')).toBe('dashboard')
    expect(gaHomeKind('komm')).toBe('mailbox')
    expect(gaHomeKind('spon')).toBe('mailbox')
    expect(gaHomeKind('u')).toBe('mein-bereich')
    expect(gaHomeKind('bl')).toBe('dashboard')
    expect(gaHomeKind('u', { isBereichsleitung: true })).toBe('dashboard')
    expect(gaHomePath('dep-1', 'mw')).toBe('/dep-1/ga/dashboard')
    expect(gaHomePath('dep-1', 'cmw')).toBe('/dep-1/ga/dashboard')
    expect(gaHomePath('dep-1', 'dc')).toBe('/dep-1/ga/dashboard')
    expect(gaHomePath('dep-1', 'komm')).toBe('/dep-1/ga/procurement/inquiries')
    expect(gaHomePath('dep-1', 'spon')).toBe('/dep-1/ga/procurement/inquiries')
    expect(gaHomePath('dep-1', 'u')).toBe('/dep-1/ga/my-unit')
    expect(gaHomePath('dep-1', 'bl')).toBe('/dep-1/ga/dashboard')
    expect(gaHomePath('dep-1', 'u', { isBereichsleitung: true })).toBe('/dep-1/ga/dashboard')
  })

  it('treats nested overview/mailbox paths as role home', () => {
    expect(gaIsRoleHomePath('dep-1', 'dc', '/dep-1/ga/dashboard')).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'dc', '/dep-1/ga/material/einsaetze')).toBe(false)
    expect(gaIsRoleHomePath('dep-1', 'komm', '/dep-1/ga/procurement/inquiries')).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'mw', '/dep-1/ga/material')).toBe(false)
    expect(gaIsRoleHomePath('dep-1', 'bl', '/dep-1/ga/dashboard')).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'u', '/dep-1/ga/my-unit')).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'u', '/dep-1/ga/dashboard', { isBereichsleitung: true })).toBe(true)
    expect(gaIsRoleHomePath('dep-1', 'u', '/dep-1/ga/my-unit', { isBereichsleitung: true })).toBe(false)
  })
})
