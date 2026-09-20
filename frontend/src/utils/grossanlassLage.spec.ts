import { describe, expect, it } from 'vitest'
import { gaLageIsOpen, gaLageRowsOfKind, gaLageTaskKind } from '@/utils/grossanlassLage'

describe('gaLageTaskKind', () => {
  const groups = [
    { id: 'g-bau', node_type: 'bauprojekt' as const },
    { id: 'g-res', node_type: 'ressort' as const },
  ]

  it('treats trips as fahrauftrag first', () => {
    expect(gaLageTaskKind({ delivery: 'trip', group_id: 'g-bau' }, groups)).toBe('fahrauftrag')
  })

  it('maps bauprojekt groups to bauauftrag', () => {
    expect(gaLageTaskKind({ delivery: 'pickup', group_id: 'g-bau' }, groups)).toBe('bauauftrag')
  })

  it('maps destination places of kind bauprojekt to bauauftrag', () => {
    expect(
      gaLageTaskKind(
        { delivery: 'pickup', group_id: 'g-res', destination_place_id: 'p1' },
        groups,
        [{ id: 'p1', kind: 'bauprojekt', group_id: 'g-bau' }],
      ),
    ).toBe('bauauftrag')
  })

  it('maps other bookings to einsatz', () => {
    expect(gaLageTaskKind({ delivery: 'pickup', group_id: 'g-res' }, groups)).toBe('einsatz')
  })
})

describe('gaLageRowsOfKind', () => {
  const groups = [
    { id: 'g-bau', node_type: 'bauprojekt' as const },
    { id: 'g-res', node_type: 'ressort' as const },
  ]

  function row(
    partial: Partial<{
      id: string
      delivery: 'trip' | 'pickup'
      group_id: string
      status: 'planned' | 'returned'
      from: string
    }>,
  ) {
    return {
      id: 'e1',
      delivery: 'pickup' as const,
      group_id: 'g-res',
      status: 'planned' as const,
      from: '2026-11-13T08:00:00',
      to: '2026-11-13T18:00:00',
      ...partial,
    }
  }

  it('lists bauauftraege and skips returned', () => {
    const rows = gaLageRowsOfKind(
      [
        row({ id: 'bau', group_id: 'g-bau' }),
        row({ id: 'trip', delivery: 'trip', group_id: 'g-bau' }),
        row({ id: 'done', group_id: 'g-bau', status: 'returned' }),
        row({ id: 'einsatz' }),
      ],
      'bauauftrag',
      groups,
    )
    expect(rows.map((item) => item.id)).toEqual(['bau'])
  })

  it('lists fahrauftraege first by trip checkbox', () => {
    const rows = gaLageRowsOfKind(
      [row({ id: 'trip', delivery: 'trip', group_id: 'g-bau' }), row({ id: 'bau', group_id: 'g-bau' })],
      'fahrauftrag',
      groups,
    )
    expect(rows.map((item) => item.id)).toEqual(['trip'])
  })
})

describe('gaLageIsOpen', () => {
  it('hides returned rows', () => {
    expect(gaLageIsOpen({ status: 'returned', to: '2099-01-01T00:00:00' })).toBe(false)
  })

  it('keeps current and future rows', () => {
    expect(gaLageIsOpen({ status: 'planned', to: '2099-01-01T00:00:00' })).toBe(true)
  })
})
