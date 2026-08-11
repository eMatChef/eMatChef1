/** Esswaren-Verfall: Sortierung / Filter / Anzeige. */

export type FoodExpiryFilter = 'all' | 'soon' | 'expired' | 'none'
export type FoodSortKey = 'expiry' | 'name' | 'stock'

export const FOOD_SOON_DAY_OPTIONS = [7, 14, 30] as const
export const FOOD_SOON_DEFAULT_DAYS = 30

/** Kalendertage bis Ablauf (Mitternacht lokal). Negativ = abgelaufen. */
export function daysUntilExpiry(expiryDate: string | null | undefined, today = new Date()): number | null {
  if (!expiryDate || !/^\d{4}-\d{2}-\d{2}/.test(expiryDate)) return null
  const [y, m, d] = expiryDate.slice(0, 10).split('-').map((x) => Number(x))
  if (!y || !m || !d) return null
  const exp = new Date(y, m - 1, d)
  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  return Math.round((exp.getTime() - start.getTime()) / 86400000)
}

export function formatExpiryDate(expiryDate: string | null | undefined): string {
  if (!expiryDate) return '–'
  const [y, m, d] = expiryDate.slice(0, 10).split('-')
  if (!y || !m || !d) return expiryDate
  return `${d}.${m}.${y}`
}

export function matchesFoodExpiryFilter(
  nearestExpiry: string | null | undefined,
  filter: FoodExpiryFilter,
  soonDays: number,
  today = new Date(),
): boolean {
  const days = daysUntilExpiry(nearestExpiry, today)
  if (filter === 'all') return true
  if (filter === 'none') return days === null
  if (filter === 'expired') return days !== null && days < 0
  if (filter === 'soon') return days !== null && days >= 0 && days <= soonDays
  return true
}

/** Sortierschlüssel: ohne Datum ans Ende; sonst früheste zuerst. */
export function expirySortValue(nearestExpiry: string | null | undefined): number {
  if (!nearestExpiry) return Number.POSITIVE_INFINITY
  const days = daysUntilExpiry(nearestExpiry)
  return days === null ? Number.POSITIVE_INFINITY : days
}

export function compareFoodMaterials<T extends {
  name: string
  total_stock: number
  nearest_expiry_date?: string | null
}>(a: T, b: T, sortKey: FoodSortKey): number {
  if (sortKey === 'name') {
    return a.name.localeCompare(b.name, 'de')
  }
  if (sortKey === 'stock') {
    return (b.total_stock ?? 0) - (a.total_stock ?? 0) || a.name.localeCompare(b.name, 'de')
  }
  const ea = expirySortValue(a.nearest_expiry_date)
  const eb = expirySortValue(b.nearest_expiry_date)
  if (ea !== eb) return ea - eb
  return a.name.localeCompare(b.name, 'de')
}

export function expiryToneClass(days: number | null): string {
  if (days === null) return 'expiry-none'
  if (days < 0) return 'expiry-expired'
  if (days <= 7) return 'expiry-critical'
  if (days <= 30) return 'expiry-soon'
  return 'expiry-ok'
}
