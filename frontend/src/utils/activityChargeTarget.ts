import type { ActivityApiType } from '@/api/activities'

export type ActivityChargeTargetKind = 'group' | 'external_customer' | 'unknown'

export type ActivityChargeTarget = {
  kind: ActivityChargeTargetKind
  /** i18n-fertiger Anzeigename (Gruppe / Kunde) */
  label: string | null
}

/**
 * Primäres Verrechnungsziel für MW-Kostenfreigabe (Verbrauch intern → Gruppe, extern → Kunde).
 * Werkstatt/Nachkauf bleiben Detailzeilen im Kosten-Tab (Material-Dep.).
 */
export function resolveActivityPrimaryChargeTarget(input: {
  activityType: ActivityApiType | string
  groupName?: string | null
  externalCustomerLabel?: string | null
  activityName?: string | null
}): ActivityChargeTarget {
  if (input.activityType === 'external') {
    const label =
      (input.externalCustomerLabel ?? '').trim() ||
      (input.activityName ?? '').trim() ||
      null
    return { kind: 'external_customer', label }
  }
  const group = (input.groupName ?? '').trim()
  if (group) {
    return { kind: 'group', label: group }
  }
  return { kind: 'unknown', label: null }
}
