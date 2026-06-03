import type { AccountingAcquisitionFollowUp } from '@/api/accountingAcquisitionFollowups'
import type { AccountingCostCenter } from '@/api/accountingCostCenters'
import type { AccountingCostCenterRule } from '@/api/accountingCostCenterRules'

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
 * Schlägt eine Kostenstelle vor: zuerst konfigurierte Regeln, sonst Keyword-Heuristik.
 */
export function suggestCostCenterId(
  followUp: AccountingAcquisitionFollowUp,
  centers: AccountingCostCenter[],
  rules: AccountingCostCenterRule[] = [],
): string {
  if (centers.length === 0) return ''

  const sk = followUp.source_kind || (followUp.material_batch_id ? 'batch' : '')
  const rule = rules.find((r) => r.source_kind === sk)
  if (rule?.cost_center_id) {
    return rule.cost_center_id
  }

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

export function suggestEntryTypeForFollowUp(
  followUp: AccountingAcquisitionFollowUp,
  rules: AccountingCostCenterRule[] = [],
): string {
  const sk = followUp.source_kind || (followUp.material_batch_id ? 'batch' : '')
  const rule = rules.find((r) => r.source_kind === sk)
  if (rule?.default_entry_type) {
    return rule.default_entry_type
  }
  if (sk === 'activity_replenishment' || sk === 'batch') return 'purchase'
  if (sk === 'activity_workshop') {
    return followUp.activity_type === 'external' ? 'repair_external' : 'repair_internal'
  }
  return 'other'
}

export function suggestPaymentMethodForFollowUp(
  p: AccountingAcquisitionFollowUp,
  rules: AccountingCostCenterRule[] = [],
): string {
  const sk = p.source_kind || (p.material_batch_id ? 'batch' : '')
  const rule = rules.find((r) => r.source_kind === sk)
  if (rule?.default_payment_method) {
    return rule.default_payment_method
  }
  if (p.charge_target === 'external_customer' || p.activity_type === 'external') {
    return 'supplier_invoice'
  }
  if (p.charge_target === 'group') {
    return 'cash_group'
  }
  return ''
}
