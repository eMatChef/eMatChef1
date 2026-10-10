import { describe, expect, it } from 'vitest'
import { apiErrorMessage, isAvailabilityConflict } from './apiErrorMessage'

const conflict = {
  response: {
    status: 409,
    data: { error: 'Festbank: im gewählten Zeitraum nicht genug Menge — Bestand 10, mit dieser Buchung 12 gleichzeitig benötigt.', code: 'availability_conflict' },
  },
}

describe('availability conflicts (HTTP 409)', () => {
  it('is recognised and keeps the readable server text', () => {
    expect(isAvailabilityConflict(conflict)).toBe(true)
    expect(apiErrorMessage(conflict, 'Fehler')).toContain('nicht genug Menge')
  })

  it('does not treat other 409s or errors as availability conflicts', () => {
    expect(isAvailabilityConflict({ response: { status: 409, data: { error: 'Duplikat' } } })).toBe(false)
    expect(isAvailabilityConflict({ response: { status: 403, data: { code: 'availability_conflict' } } })).toBe(false)
    expect(isAvailabilityConflict(new Error('x'))).toBe(false)
    expect(isAvailabilityConflict(null)).toBe(false)
  })
})
