import type { ActivityPackItem } from '@/api/activityPackItems'

/** Signatur für Live-Sync: erkennt Mengenänderungen ohne Deep-Compare. */
export function packItemsLiveSyncSignature(items: ActivityPackItem[]): string {
  return items
    .map(
      (i) =>
        `${i.id}:${i.quantityPacked}:${i.quantityTransportTo}:${i.quantityIssued}:${i.quantityTransportBack}:${i.quantityReturned}:${i.quantityStored}:${i.quantityWet ?? 0}`,
    )
    .join('|')
}
