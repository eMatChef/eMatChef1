import { describe, expect, it, vi } from 'vitest'
import type { GaMaterialProgressQuantities } from '@/api/grossanlassUebersicht'
import {
  findMaterialProgress,
  materialProgressSegments,
  materialProgressText,
} from '@/utils/grossanlassMaterialProgress'

const get = vi.hoisted(() => vi.fn())
vi.mock('@/api/apiClient', () => ({ default: { get } }))

function quantities(patch: Partial<GaMaterialProgressQuantities> = {}): GaMaterialProgressQuantities {
  return {
    required: 0,
    covered: 0,
    open: 0,
    received: 0,
    allocated: 0,
    packed: 0,
    ready_for_transport: 0,
    in_transit: 0,
    at_place: 0,
    return_open: 0,
    returned: 0,
    disposed: 0,
    ...patch,
  }
}

const de: Record<string, string> = {
  required: 'benötigt',
  covered: 'gedeckt',
  open: 'offen',
  received: 'eingegangen',
  allocated: 'zugewiesen',
  packed: 'gepackt',
  ready_for_transport: 'transportbereit',
  in_transit: 'unterwegs',
  at_place: 'vor Ort',
}

describe('materialProgressText', () => {
  it('lists the material path in order and hides empty steps', () => {
    const text = materialProgressText(
      quantities({ required: 100, covered: 80, open: 20, received: 60, packed: 40, in_transit: 20, at_place: 20 }),
      (key, n) => `${n} ${de[key] ?? key}`,
    )

    expect(text).toBe('100 benötigt · 80 gedeckt · 60 eingegangen · 40 gepackt · 20 unterwegs · 20 vor Ort · 20 offen')
  })

  it('always shows need and coverage, open only when something is missing', () => {
    expect(materialProgressSegments(quantities({ required: 5, covered: 5 })).map((s) => s.key))
      .toEqual(['required', 'covered'])
    expect(materialProgressSegments(quantities({ required: 100, covered: 70, open: 30 })).map((s) => [s.key, s.value]))
      .toEqual([['required', 100], ['covered', 70], ['open', 30]])
  })

  it('marks open as warning', () => {
    const open = materialProgressSegments(quantities({ required: 3, open: 3 })).find((s) => s.key === 'open')
    expect(open?.tone).toBe('warn')
  })

  it('finds the row for a material entry by id', () => {
    const rows = [{ id: 'gw1' }, { id: 'gp2' }]
    expect(findMaterialProgress(rows, 'gp2')).toEqual({ id: 'gp2' })
    expect(findMaterialProgress(rows, null)).toBeNull()
    expect(findMaterialProgress(undefined, 'gw1')).toBeNull()
  })
})

describe('getGrossanlassMaterialProgress', () => {
  it('requests the uebersicht progress endpoint with the group filter', async () => {
    const { getGrossanlassMaterialProgress } = await import('@/api/grossanlassUebersicht')
    get.mockResolvedValueOnce({ data: { items: [{ id: 'gw1', ...quantities({ required: 100 }) }], groups: [] } })

    const result = await getGrossanlassMaterialProgress('dept00000001', 'grp000000001')

    expect(get).toHaveBeenCalledWith(
      '/api/departments/dept00000001/grossanlass/uebersicht/progress',
      { params: { group_id: 'grp000000001' } },
    )
    expect(result.items[0].required).toBe(100)
  })

  it('falls back to empty lists', async () => {
    const { getGrossanlassMaterialProgress } = await import('@/api/grossanlassUebersicht')
    get.mockResolvedValueOnce({ data: {} })

    const result = await getGrossanlassMaterialProgress('dept00000001')

    expect(get).toHaveBeenLastCalledWith('/api/departments/dept00000001/grossanlass/uebersicht/progress', { params: undefined })
    expect(result).toEqual({ items: [], groups: [] })
  })
})
