import type { AccountingAcquisitionFollowUp } from '@/api/accountingAcquisitionFollowups'
import type { AccountingCostCenter } from '@/api/accountingCostCenters'

const KEYWORD_GROUPS: Record<string, string[]> = {
  consumption: ['verbrauch', 'consumption', 'verkauf'],
  replenishment: ['einkauf', 'purchase', 'beschaffung', 'nachliefer'],
  rental: ['miete', 'rental', 'vermiet', 'ausleihe', 'external'],
  workshop_repair: ['reparatur', 'repair', 'werkstatt', 'instand'],
  workshop_writeoff: ['abschreib', 'writeoff', 'verlust', 'amort'],
  material_dept: ['material', 'lager', 'bestand', 'inventar'],
  external_customer: ['miete', 'rental', 'kunde', 'extern', 'vermiet', 'forderung'],
}

function scoreName(name: string, keywords: string[]): number {
  const n = name.toLowerCase()
  let score = 0
  for (const kw of keywords) {
    if (n.includes(kw)) {
      score += kw.length
    }
  }
  return score
}

function keywordsForFollowUp(p: AccountingAcquisitionFollowUp): string[] {
  const sk = p.source_kind || ''
  const charge = p.charge_target || ''
  const keys: string[] = []

  if (sk === 'activity_consumption') {
    keys.push(...KEYWORD_GROUPS.consumption)
  } else if (sk === 'activity_replenishment') {
    keys.push(...KEYWORD_GROUPS.replenishment, ...KEYWORD_GROUPS.material_dept)
  } else if (sk === 'activity_rental') {
    keys.push(...KEYWORD_GROUPS.rental, ...KEYWORD_GROUPS.external_customer)
  } else if (sk === 'activity_workshop') {
    keys.push(...KEYWORD_GROUPS.workshop_repair)
    if (p.activity_type === 'external' || charge === 'external_customer') {
      keys.push(...KEYWORD_GROUPS.external_customer)
    } else {
      keys.push(...KEYWORD_GROUPS.material_dept)
    }
  }

  if (charge === 'department') {
    keys.push(...KEYWORD_GROUPS.material_dept)
  }
  if (charge === 'external_customer') {
    keys.push(...KEYWORD_GROUPS.external_customer)
  }

  return keys.length > 0 ? keys : KEYWORD_GROUPS.material_dept
}

/**
 * Schlägt eine Kostenstelle anhand des Namens vor (kein harter Code — passt zu dep.-spezifischen Stammdaten).
 */
export function suggestCostCenterId(
  followUp: AccountingAcquisitionFollowUp,
  centers: AccountingCostCenter[],
): string {
  if (centers.length === 0) return ''
  const keywords = keywordsForFollowUp(followUp)

  let bestId = ''
  let bestScore = 0
  for (const c of centers) {
    const s = scoreName(c.name, keywords)
    if (s > bestScore) {
      bestScore = s
      bestId = c.id
    }
  }

  return bestScore > 0 ? bestId : centers[0]?.id || ''
}

export function suggestPaymentMethodForFollowUp(p: AccountingAcquisitionFollowUp): string {
  if (p.charge_target === 'external_customer' || p.activity_type === 'external') {
    return 'supplier_invoice'
  }
  if (p.charge_target === 'group') {
    return 'cash_group'
  }
  return ''
}
