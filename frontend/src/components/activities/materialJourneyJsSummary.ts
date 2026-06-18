import type { ActivityDetail } from '@/api/activities'
import type { ActivityPackItem } from '@/api/activityPackItems'

export type MaterialJourneyJsSummary = {
  items: number
  received: number
  returned: number
}

export function computeMaterialJourneyJsSummary(
  packItems: ActivityPackItem[],
): MaterialJourneyJsSummary {
  const js = packItems.filter((pi) => pi.isJsMaterial)
  return {
    items: js.length,
    received: js.reduce((sum, pi) => sum + (pi.quantityIssued || 0), 0),
    returned: js.reduce((sum, pi) => sum + (pi.quantityReturned || 0), 0),
  }
}

export function showMaterialJourneyJsBanner(activity: ActivityDetail | null): boolean {
  if (!activity) return false
  if (activity.wants_js_material !== true) return false
  return activity.type === 'camp' || activity.type === 'event'
}

export function packItemsForMaterialJourney(packItems: ActivityPackItem[]): ActivityPackItem[] {
  return packItems.filter((pi) => !pi.isJsMaterial)
}
