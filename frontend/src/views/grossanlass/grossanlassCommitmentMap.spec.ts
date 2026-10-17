import { describe, expect, it } from 'vitest'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { commitmentTabs, commitmentToPreviewRow } from '@/views/grossanlass/grossanlassCommitmentMap'
import { groupBestandPreviewRows } from '@/views/grossanlass/gaBestandGroup'

const t = (key: string) => key

function commitment(overrides: Partial<GrossanlassCommitment>): GrossanlassCommitment {
  return {
    id: 'zs0000000001',
    inquiry_id: null,
    name: 'Artikel',
    family: 'material',
    origin: 'loan',
    source: 'Firma AG',
    plate: null,
    barcode: null,
    category_id: null,
    released: true,
    present_from: null,
    present_to: null,
    handover_from: null,
    handover_to: null,
    return_from: null,
    return_to: null,
    wish_label: null,
    wish_from: null,
    wish_to: null,
    services: [],
    quantity: 1,
    item_details: {},
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

const lader = commitment({ id: 'zsfa7a9edabd', name: 'Teleskoplader', family: 'vehicle', source: 'Maki Landtechnik', category_id: 'gcHebezeug' })
const bretter = commitment({ id: 'zs30dc5a2512', name: 'Schwartenbretter', origin: 'buy', source: 'Häberli Holz', quantity: 30 })

/** Gleiche Regel wie die Bestandstabelle: Reiter «uebersicht» ohne Fahrzeuge. */
function bestandRows(list: GrossanlassCommitment[]) {
  return list
    .map((row) => commitmentToPreviewRow(row, t))
    .filter((row) => row.tabs.includes('uebersicht') && !row.tabs.includes('fahrzeuge'))
}

/** Gleiche Regel wie die Fahrzeugansicht. */
function fahrzeuge(list: GrossanlassCommitment[]) {
  return list.filter((row) => row.family === 'vehicle')
}

describe('Zusagen: Fahrzeug und Material getrennt', () => {
  it('Fahrzeug erscheint in der Fahrzeugansicht, nicht im Materialbestand', () => {
    expect(commitmentTabs(lader)).toEqual(['fahrzeuge'])
    expect(fahrzeuge([lader, bretter]).map((row) => row.name)).toEqual(['Teleskoplader'])
    expect(bestandRows([lader, bretter]).map((row) => row.name)).toEqual(['Schwartenbretter'])
  })

  it('Kategorie «Hebezeug» ändert die Fahrzeugzuordnung nicht', () => {
    expect(lader.category_id).toBe('gcHebezeug')
    expect(bestandRows([lader])).toEqual([])
  })

  it('Quelle bleibt die Firma, der Name der Artikel (Bestand und Fahrzeug)', () => {
    const [row] = groupBestandPreviewRows(bestandRows([bretter]), [bretter], t)
    expect(row.name).toBe('Schwartenbretter')
    expect(row.source).toBe('Häberli Holz')
    const vehicle = fahrzeuge([lader])[0]
    expect(vehicle.name).toBe('Teleskoplader')
    expect(vehicle.source).toBe('Maki Landtechnik')
    expect(vehicle.name).not.toBe(vehicle.source)
  })
})
