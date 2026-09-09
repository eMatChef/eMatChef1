import { describe, expect, it } from 'vitest'
import {
  buildFixedDateCalendarRing,
  buildOrgCalendarRings,
  enrichEinsatzFromGroups,
  type GaEinsatzOrgGroup,
  type GaEinsatzResource,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'

function t(key: string): string {
  const labels: Record<string, string> = {
    'grossanlass.materialUebersicht.bookProjectUnassigned': 'Ohne Zuordnung',
    'grossanlass.materialUebersicht.orgNoProject': 'Ohne Bauprojekt',
    'grossanlass.materialUebersicht.ringFixed': 'Fixe Termine',
  }
  return labels[key] || key
}

function booking(partial: Partial<GaPreviewEinsatz> & Pick<GaPreviewEinsatz, 'id' | 'objectId'>): GaPreviewEinsatz {
  return {
    objectName: partial.objectName || partial.objectId,
    kind: 'quantity',
    qty: 1,
    stock: 10,
    fromIso: '2027-09-01T08:00:00',
    toIso: '2027-09-03T18:00:00',
    fromLabel: '1.9.',
    toLabel: '3.9.',
    ressort: '',
    status: 'planned',
    who: 'Anna',
    ...partial,
  }
}

const hose: GaEinsatzResource = {
  id: 'hose',
  name: 'Wasserschlauch 32mm',
  family: 'material',
  stayMode: 'stay',
  categoryId: 'infra',
  kind: 'quantity',
  stock: 265,
}

const groups: GaEinsatzOrgGroup[] = [
  { id: 'infra', name: 'Infrastruktur', parent_id: null, node_type: 'ressort' },
  { id: 'wasser', name: 'BL Wasser', parent_id: 'infra', node_type: 'unterressort' },
  { id: 'a2', name: 'Wasserstelle A2', parent_id: 'wasser', node_type: 'bauprojekt' },
]

describe('enrichEinsatzFromGroups', () => {
  it('maps a bauprojekt booking to root ressort and project name', () => {
    const row = enrichEinsatzFromGroups(
      booking({ id: 'e1', objectId: 'hose', groupId: 'a2', ressort: 'Wasserstelle A2' }),
      groups,
    )
    expect(row.ressort).toBe('Infrastruktur')
    expect(row.bauprojekt).toBe('Wasserstelle A2')
  })

  it('keeps a ressort booking without a project accordion', () => {
    const row = enrichEinsatzFromGroups(
      booking({ id: 'e2', objectId: 'hose', groupId: 'infra', ressort: 'Infrastruktur' }),
      groups,
    )
    expect(row.ressort).toBe('Infrastruktur')
    expect(row.bauprojekt).toBeUndefined()
  })
})

describe('buildOrgCalendarRings', () => {
  it('makes one ressort accordion and a bauprojekt category', () => {
    const rings = buildOrgCalendarRings(
      [hose],
      [
        booking({
          id: 'e1',
          objectId: 'hose',
          objectName: hose.name,
          ressort: 'Infrastruktur',
          bauprojekt: 'Wasserstelle A2',
        }),
      ],
      t,
    )
    expect(rings).toHaveLength(1)
    expect(rings[0].id).toBe('org:Infrastruktur')
    expect(rings[0].skipCategory).toBe(false)
    expect(rings[0].blocks).toHaveLength(1)
    expect(rings[0].blocks[0].label).toBe('Wasserstelle A2')
    expect(rings[0].blocks[0].resources[0].name).toBe(hose.name)
  })

  it('skips the inner category when a ressort has no bauprojekt', () => {
    const rings = buildOrgCalendarRings(
      [hose],
      [booking({ id: 'e1', objectId: 'hose', ressort: 'Logistik' })],
      t,
    )
    expect(rings[0].skipCategory).toBe(true)
    expect(rings[0].blocks[0].label).toBe('Logistik')
  })

  it('ignores occupancy bars so partner names do not become ressorts', () => {
    const rings = buildOrgCalendarRings(
      [hose],
      [
        booking({
          id: 'occ',
          objectId: 'hose',
          ressort: 'Leihfirma AG',
          barRole: 'handover',
        }),
      ],
      t,
    )
    expect(rings).toHaveLength(0)
  })
})

describe('buildFixedDateCalendarRing', () => {
  it('puts each fixed date on its own row under one accordion', () => {
    const ring = buildFixedDateCalendarRing(
      [
        {
          id: 'p1',
          typeLabel: 'Aufbau',
          name: '',
          fromIso: '2027-09-01T08:00:00',
          toIso: '2027-09-07T18:00:00',
          fromLabel: '1.9. 08:00',
          toLabel: '7.9. 18:00',
        },
      ],
      t,
    )
    expect(ring?.id).toBe('fixed')
    expect(ring?.skipCategory).toBe(true)
    expect(ring?.blocks[0].resources).toHaveLength(1)
    expect(ring?.blocks[0].resources[0].bookings[0].barRole).toBe('fixed')
  })
})
