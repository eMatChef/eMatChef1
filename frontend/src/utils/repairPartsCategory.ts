/** System-Kategorie pro Department (Werkstatt) — nicht im Onboarding-Kategoriebaum. */
export const REPAIR_PARTS_CATEGORY_NAME = 'Repair-Parts'

export function isRepairPartsCategoryName(name: string | null | undefined): boolean {
  return (name || '').trim().toLowerCase() === REPAIR_PARTS_CATEGORY_NAME.toLowerCase()
}

export function isRepairPartsCategory(cat: { name?: string | null }): boolean {
  return isRepairPartsCategoryName(cat.name)
}

export function filterUserSelectableCategories<T extends { name?: string | null }>(
  categories: T[],
): T[] {
  return categories.filter((c) => !isRepairPartsCategory(c))
}
