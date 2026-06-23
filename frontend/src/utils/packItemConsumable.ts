import type { ActivityPackItem } from '@/api/activityPackItems'

/** Verbrauchsmaterial: Pack-Zeile (inkl. Esswaren via API) oder Activity-Item-Flag (Fallback). */
export function isConsumablePackItem(
  pi: ActivityPackItem,
  consumableMaterialItemIds?: ReadonlySet<string> | null,
): boolean {
  if (pi.isConsumable) return true
  const mid = pi.materialItemId?.trim()
  if (!mid || !consumableMaterialItemIds?.size) return false
  return consumableMaterialItemIds.has(mid)
}
