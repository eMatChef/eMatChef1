import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'

export type PhysicalComboIssueLine = {
  lineId: string
  materialItemId: string
  materialName: string
  maxQty: number
  serialHint: string | null
  sectionTitle: string
}

export type PhysicalComboIssueSelection = {
  materialItemId: string
  quantity: number
}

export type PackIssueWizardEmitPayload =
  | { materialItemId: string; issueType: 'loss' | 'repair' | 'damage'; quantity?: number }
  | {
      items: Array<{
        materialItemId: string
        issueType: 'loss' | 'repair' | 'damage'
        quantity?: number
      }>
    }

/** Pack-Zeile der Phys.-Kombi-Shell (nicht Stücklisten-Komponente). */
export function findPhysicalComboShellPackItem(
  materialItemId: string,
  packItems: ActivityPackItem[],
): ActivityPackItem | undefined {
  const mid = materialItemId.trim()
  if (!mid) return undefined
  return packItems.find((pi) => isPhysicalComboPackItem(pi) && pi.materialItemId === mid)
}

export function physicalComboHasSelectableIssueComponents(
  sections: PackCrateShellPeekSection[],
): boolean {
  return flattenPhysicalComboIssueLines(sections).length > 0
}

export function flattenPhysicalComboIssueLines(
  sections: PackCrateShellPeekSection[],
): PhysicalComboIssueLine[] {
  const out: PhysicalComboIssueLine[] = []
  for (const section of sections) {
    for (const line of section.lines) {
      const materialItemId = (line.materialItemId ?? '').trim()
      if (!materialItemId) continue
      out.push({
        lineId: line.id,
        materialItemId,
        materialName: line.materialName,
        maxQty: Math.max(1, Math.floor(Number(line.quantity)) || 1),
        serialHint: line.serialHint ?? null,
        sectionTitle: section.title,
      })
    }
  }
  return out
}
