import { describe, expect, it } from 'vitest'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  buildMaterialJourneyReturnSummaryRows,
  displayReturnQty,
} from '@/utils/materialJourneyReturnSummary'

describe('materialJourneyReturnSummary', () => {
  it('displayReturnQty prefers physical balance when pipeline overshoots', () => {
    expect(displayReturnQty(10, 10, 0, 0, 5, 0)).toBe(5)
  })

  it('nests crate content under the crate and keeps loose items flat', () => {
    const shell = {
      id: 'pi-shell',
      materialItemId: 'mid-shell',
      materialName: 'Rakokiste 406030',
      quantityIssued: 1,
      quantityReturned: 1,
    } as ActivityPackItem
    const fackeln = {
      id: 'pi-f',
      materialItemId: 'mid-f',
      materialName: 'Fackeln',
      isConsumable: true,
      quantityIssued: 5,
      quantityReturned: 0,
    } as ActivityPackItem
    const blache = {
      id: 'pi-b',
      materialItemId: 'mid-b',
      materialName: 'Blache 64',
      quantityIssued: 10,
      quantityReturned: 10,
    } as ActivityPackItem

    const container = {
      id: 'c1',
      label: 'Rakokiste 406030-012',
      container_material_item_id: 'mid-shell',
      container_batch_id: 'batch-1',
    } as ActivityPackContainer

    shell.linkedContainerBatchId = 'batch-1'

    const rows = buildMaterialJourneyReturnSummaryRows(
      [shell, fackeln, blache],
      [
        {
          id: 'iss-1',
          material_item_id: 'mid-f',
          type: 'consumption',
          quantity: 5,
        } as never,
      ],
      new Set(['mid-f']),
      [container],
      {
        c1: [
          {
            id: 'ci-f',
            material_item_id: 'mid-f',
            material_name: 'Fackeln',
            quantity_packed: 10,
            quantity_issued: 5,
            quantity_returned: 0,
          },
        ],
      },
    )

    expect(rows.map((r) => ({ name: r.name, kind: r.kind, depth: r.depth }))).toEqual([
      { name: 'Rakokiste 406030-012', kind: 'crate', depth: 0 },
      { name: 'Fackeln', kind: 'in_crate', depth: 1 },
      { name: 'Blache 64', kind: 'loose', depth: 0 },
    ])
    expect(rows[1].consumption).toBe(5)
    expect(rows[1].returned).toBe(0)
    expect(rows[1].stored).toBe(0)
    expect(rows[1].replenished).toBe(0)
  })

  it('archive mode adds stored, replenished and orphan nachkauf rows', () => {
    const blache = {
      id: 'pi-b',
      materialItemId: 'mid-b',
      materialName: 'Blache 64',
      quantityIssued: 10,
      quantityReturned: 10,
      quantityStored: 10,
    } as ActivityPackItem

    const rows = buildMaterialJourneyReturnSummaryRows(
      [blache],
      [],
      undefined,
      [],
      {},
      {
        mode: 'archive',
        replenishmentByMaterial: new Map([
          ['mid-b', 2],
          ['mid-orphan', 3],
        ]),
      },
    )

    expect(rows).toHaveLength(2)
    expect(rows[0]).toMatchObject({
      name: 'Blache 64',
      stored: 10,
      replenished: 2,
    })
    expect(rows[1]).toMatchObject({
      kind: 'replenishment',
      materialItemId: 'mid-orphan',
      replenished: 3,
    })
  })
})
