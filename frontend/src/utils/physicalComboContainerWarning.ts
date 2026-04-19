import { getLinkedPhysicalComboForContainerBatch } from '@/api/materials'

/**
 * Lädt verknüpfte physische Kombinationen für die gegebenen Kisten-Chargen (dedupliziert nach Combo-ID).
 */
export async function fetchLinkedCombosForContainerBatchIds(
  containerBatchIds: string[]
): Promise<{ id: string; name: string }[]> {
  const unique = [...new Set(containerBatchIds.map((id) => String(id || '').trim()).filter(Boolean))]
  if (unique.length === 0) return []

  const byComboId = new Map<string, { id: string; name: string }>()
  for (const bid of unique) {
    try {
      const c = await getLinkedPhysicalComboForContainerBatch(bid)
      if (c && !byComboId.has(c.id)) {
        byComboId.set(c.id, { id: c.id, name: c.name })
      }
    } catch {
      // bei API-Fehler: keine Blockade hier
    }
  }
  return [...byComboId.values()]
}
