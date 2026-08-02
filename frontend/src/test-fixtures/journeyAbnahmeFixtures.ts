import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ActivityIssueReportRow } from '@/api/activities'

function pi(
  id: string,
  materialItemId: string,
  materialName: string,
  overrides: Partial<ActivityPackItem> = {},
): ActivityPackItem {
  return {
    id,
    activityId: 'act-abnahme',
    materialItemId,
    materialName,
    categoryName: 'Test',
    categoryId: 'cat-1',
    packSize: null,
    packUnit: null,
    quantityOrdered: 1,
    quantityPacked: 1,
    quantityTransportTo: 0,
    quantityIssued: 0,
    quantityTransportBack: 0,
    quantityReturned: 0,
    quantityStored: 0,
    conditionOut: null,
    notes: null,
    isFullyPacked: false,
    isFullyIssued: false,
    isFullyReturned: false,
    isFullyStored: false,
    packDifference: null,
    issueDifference: null,
    returnDifference: null,
    packedAt: null,
    isConsumable: false,
    isJsMaterial: false,
    externalSource: null,
    storageRackName: null,
    storageSlotName: null,
    storageAddressName: null,
    materialType: 'physical',
    linkedContainerLabel: null,
    linkedContainerBatchId: null,
    ...overrides,
  }
}

/** D1 — Rakokiste + Fackeln (Verbrauch in Kiste) + Statikseil + Blache, alles ausgegeben. */
export function muusliIssuedFixtures(): {
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  issueReports: ActivityIssueReportRow[]
} {
  const crateId = 'crate-rako'
  const packItems: ActivityPackItem[] = [
    pi('pi-rako-shell', 'mat-rako', 'Rakokiste', {
      quantityOrdered: 1,
      quantityPacked: 1,
      quantityIssued: 1,
      linkedContainerLabel: 'Rakokiste',
      linkedContainerBatchId: 'batch-rako',
    }),
    pi('pi-fackeln', 'mat-fackeln', 'Fackeln Verbrauch', {
      quantityOrdered: 5,
      quantityPacked: 5,
      quantityIssued: 0,
      isConsumable: true,
    }),
    pi('pi-statik', 'mat-statik', 'Statikseil', {
      quantityOrdered: 1,
      quantityPacked: 1,
      quantityIssued: 1,
    }),
    pi('pi-blache', 'mat-blache', 'Blache', {
      quantityOrdered: 1,
      quantityPacked: 1,
      quantityIssued: 1,
    }),
  ]

  const packContainers: ActivityPackContainer[] = [
    {
      id: crateId,
      label: 'Rakokiste',
      container_batch_id: 'batch-rako',
      container_material_item_id: 'mat-rako',
      sort_order: 0,
    },
  ]

  const containerItemsByContainerId: Record<string, ActivityPackContainerItem[]> = {
    [crateId]: [
      {
        id: 'ci-fackeln',
        container_id: crateId,
        material_item_id: 'mat-fackeln',
        material_name: 'Fackeln Verbrauch',
        quantity_packed: 5,
        quantity_issued: 5,
        quantity_transport_to: 0,
        quantity_transport_back: 0,
        quantity_returned: 0,
        quantity_stored: 0,
        sort_order: 0,
      },
    ],
  }

  return { packItems, packContainers, containerItemsByContainerId, issueReports: [] }
}

/** D2 — 10 Stück: 7 in Kiste, 3 lose. */
export function partialCrateSevenThreeFixtures(): {
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
} {
  const crateId = 'crate-partial'
  const packItems: ActivityPackItem[] = [
    pi('pi-kiste', 'mat-kiste', 'Transportkiste', {
      quantityOrdered: 1,
      quantityPacked: 1,
      quantityIssued: 0,
      linkedContainerBatchId: 'batch-partial',
    }),
    pi('pi-artikel', 'mat-artikel', 'Testartikel', {
      quantityOrdered: 10,
      quantityPacked: 10,
      quantityIssued: 0,
    }),
  ]

  const packContainers: ActivityPackContainer[] = [
    {
      id: crateId,
      label: 'Transportkiste',
      container_batch_id: 'batch-partial',
      container_material_item_id: 'mat-kiste',
      sort_order: 0,
    },
  ]

  const containerItemsByContainerId: Record<string, ActivityPackContainerItem[]> = {
    [crateId]: [
      {
        id: 'ci-artikel',
        container_id: crateId,
        material_item_id: 'mat-artikel',
        material_name: 'Testartikel',
        quantity_packed: 7,
        quantity_issued: 0,
        quantity_transport_to: 0,
        quantity_transport_back: 0,
        quantity_returned: 0,
        quantity_stored: 0,
        sort_order: 0,
      },
    ],
  }

  return { packItems, packContainers, containerItemsByContainerId }
}

/** D4 — Logistics: Material auf Hintransport, am Anlass angekommen. */
export function logisticsAtEventFixtures(): {
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
} {
  const crateId = 'crate-log'
  return {
    packItems: [
      pi('pi-log-shell', 'mat-kiste', 'Eventkiste', {
        quantityPacked: 1,
        quantityTransportTo: 1,
        quantityIssued: 1,
        linkedContainerBatchId: 'batch-log',
      }),
      pi('pi-log-loose', 'mat-hammer', 'Hammer', {
        quantityPacked: 2,
        quantityTransportTo: 2,
        quantityIssued: 2,
      }),
    ],
    packContainers: [
      {
        id: crateId,
        label: 'Eventkiste',
        container_batch_id: 'batch-log',
        container_material_item_id: 'mat-kiste',
        sort_order: 0,
      },
    ],
    containerItemsByContainerId: {
      [crateId]: [
        {
          id: 'ci-hammer',
          container_id: crateId,
          material_item_id: 'mat-hammer',
          material_name: 'Hammer',
          quantity_packed: 1,
          quantity_issued: 1,
          quantity_transport_to: 1,
          quantity_transport_back: 0,
          quantity_returned: 0,
          quantity_stored: 0,
          sort_order: 0,
        },
      ],
    },
  }
}

/** D4 — Transport zurück: Kiste unterwegs, Retour offen. */
export function logisticsTransportBackFixtures(): {
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
} {
  const base = logisticsAtEventFixtures()
  const crateId = 'crate-log'
  return {
    ...base,
    packItems: base.packItems.map((p) =>
      p.id === 'pi-log-shell'
        ? { ...p, quantityTransportBack: 1 }
        : { ...p, quantityTransportBack: 1, quantityIssued: 1 },
    ),
    containerItemsByContainerId: {
      [crateId]: [
        {
          ...base.containerItemsByContainerId[crateId]![0]!,
          quantity_transport_back: 1,
        },
      ],
    },
  }
}
