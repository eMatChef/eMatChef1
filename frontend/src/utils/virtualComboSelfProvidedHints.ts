import type { ActivityItemRow } from '@/api/activities'

export type VirtualComboSelfProvidedHint = {
  activityItemId: string
  comboName: string
  items: Array<{ name: string; total_qty: number }>
  bookingAcknowledged: boolean
}

/** Virt. Kombo-Eltern mit self_provided — analog ActivityDetailView.virtualComboSelfProvidedHints. */
export function buildVirtualComboSelfProvidedHints(
  activityItems: ActivityItemRow[],
): VirtualComboSelfProvidedHint[] {
  const out: VirtualComboSelfProvidedHint[] = []
  for (const r of activityItems) {
    if (r.material_type !== 'virtual_combo' || r.parent_activity_item_id) continue
    const sp = r.config_snapshot?.self_provided ?? []
    if (sp.length === 0) continue
    out.push({
      activityItemId: r.id,
      comboName: r.material_name,
      items: sp.map((x) => ({ name: x.name, total_qty: x.total_qty })),
      bookingAcknowledged: Boolean(r.config_snapshot?.self_provided_acknowledged),
    })
  }
  return out
}
