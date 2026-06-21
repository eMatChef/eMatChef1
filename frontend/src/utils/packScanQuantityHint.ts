import type { ActivityPackItem } from '@/api/activityPackItems'
import { isPackagingUnit } from '@/utils/materialStockUnit'

type TranslateFn = (key: string, params?: Record<string, unknown>) => string

export function formatPackScanQuantityHint(
  pi: ActivityPackItem,
  moveQty: number,
  t: TranslateFn,
): string {
  const qty = Math.max(0, Math.floor(Number(moveQty) || 0))
  if (qty <= 0) return ''

  const ps = pi.packSize
  const pu = (pi.packUnit || '').trim()

  if (ps != null && ps >= 2 && isPackagingUnit(pu)) {
    const fullPacks = Math.floor(qty / ps)
    const loose = qty % ps
    if (fullPacks > 0 && loose === 0) {
      return t('activities.materialJourney.scan.quantityHintPacksOnly', {
        packs: fullPacks,
        unit: pu,
        per: ps,
        total: qty,
      })
    }
    if (fullPacks > 0 && loose > 0) {
      return t('activities.materialJourney.scan.quantityHintPacksPlusLoose', {
        packs: fullPacks,
        unit: pu,
        per: ps,
        loose,
        total: qty,
      })
    }
  }

  const unit =
    pu && !isPackagingUnit(pu) && pu !== 'Stk'
      ? pu
      : t('activities.materialJourney.scan.quantityUnitStk')
  return t('activities.materialJourney.scan.quantityHintPieces', { count: qty, unit })
}

export function formatPackScanProgressHint(
  doneQty: number,
  totalQty: number,
  t: TranslateFn,
): string {
  const done = Math.max(0, Math.floor(Number(doneQty) || 0))
  const total = Math.max(0, Math.floor(Number(totalQty) || 0))
  if (total <= 0) return ''
  return t('activities.materialJourney.scan.quantityProgress', { done, total })
}
