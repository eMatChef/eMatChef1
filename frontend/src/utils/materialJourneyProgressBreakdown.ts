import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'

export type MaterialJourneyProgressBreakdown = {
  crates: number
  consumables: number
  loose: number
}

/** Aufschlüsselung Checklisten-Zeilen (Kisten / Verbrauch / lose) für Toolbar/Fussnote. */
export function computeMaterialJourneyProgressBreakdown(
  rows: MaterialJourneyTaskRow[],
  mode: 'open' | 'done',
): MaterialJourneyProgressBreakdown {
  const breakdown: MaterialJourneyProgressBreakdown = { crates: 0, consumables: 0, loose: 0 }
  for (const row of rows) {
    const matches = mode === 'open' ? row.isOpen : row.isDone
    if (!matches) continue
    if (isMaterialJourneyCrateKind(row.kind)) {
      breakdown.crates++
    } else if (row.badges.includes('consumable')) {
      breakdown.consumables++
    } else {
      breakdown.loose++
    }
  }
  return breakdown
}

export function materialJourneyProgressBreakdownHasContent(
  breakdown: MaterialJourneyProgressBreakdown,
): boolean {
  return breakdown.crates + breakdown.consumables + breakdown.loose > 0
}
