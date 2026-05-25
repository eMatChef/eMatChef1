import type { ActivityPackItem } from '@/api/activityPackItems'

export function packRackLabel(pi: ActivityPackItem): string {
  return pi.storageRackName?.trim() || ''
}

/** Phys.-Kombi-Badge — auch wenn material_type in älteren Daten noch «physical» ist */
export function isPhysicalComboPackItem(item: ActivityPackItem): boolean {
  if (item.materialType === 'physical_combo') return true
  if (item.materialType === 'virtual_combo') return false
  return /\bphys\.?\s*kombi\b/i.test(item.materialName ?? '')
}

export function isVirtualComboPackItem(item: ActivityPackItem): boolean {
  if (item.materialType === 'virtual_combo') return true
  if (item.materialType === 'physical_combo') return false
  return /\bvirt(?:ual)?\.?\s*kombi\b/i.test(item.materialName ?? '')
}

/** Anzeigename ohne «Phys. Kombi»/«Virt. Kombi» im Text — Badge zeigt den Typ */
export function packMaterialDisplayName(item: ActivityPackItem): string {
  const raw = (item.materialName ?? '').trim()
  if (!isPhysicalComboPackItem(item) && !isVirtualComboPackItem(item)) return raw
  const stripped = raw
    .replace(/\s*[(\[]?\s*Phys\.?\s*Kombi\s*[)\]]?\s*$/i, '')
    .replace(/\s*[(\[]?\s*Virt(?:ual)?\.?\s*Kombi\s*[)\]]?\s*$/i, '')
    .trim()
  return stripped || raw
}
