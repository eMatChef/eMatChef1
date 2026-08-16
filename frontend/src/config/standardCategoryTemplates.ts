import {
  createCategory,
  type Category,
} from '@/api/categories'

/** Standard-Kategorienbaum für Pfadi/Jugendabteilungen (Onboarding-Vorschlag). */
export const STANDARD_CATEGORY_TREE: Array<{ name: string; children?: string[] }> = [
  { name: 'Pionier', children: ['Werkzeug', 'Seil', 'Kisten', 'Zeltbau', 'Diverses'] },
  { name: 'Küche', children: ['Ausrüstung', 'Essen', 'Kochbuch', 'Abwasch', 'Diverses'] },
  { name: 'Verkleidung' },
  { name: 'Zelt' },
  { name: 'Bastelmat' },
  { name: 'Spiele' },
  { name: 'Verpackung' },
  { name: 'Diverses' },
  { name: 'Werbematerial' },
]

export type CategoryTemplateSelection = {
  main: Record<string, boolean>
  sub: Record<string, Record<string, boolean>>
}

export function createDefaultCategoryTemplateSelection(): CategoryTemplateSelection {
  const main: Record<string, boolean> = {}
  const sub: Record<string, Record<string, boolean>> = {}
  for (const item of STANDARD_CATEGORY_TREE) {
    main[item.name] = true
    if (item.children?.length) {
      sub[item.name] = {}
      for (const child of item.children) {
        sub[item.name][child] = true
      }
    }
  }
  return { main, sub }
}

function normalizeKey(value: string): string {
  return value.trim().toLowerCase()
}

function findCategoryByName(
  categories: Category[],
  name: string,
  parentId: string | null
): Category | undefined {
  const targetName = normalizeKey(name)
  return categories.find(
    (category) =>
      normalizeKey(category.name) === targetName && (category.parent_id || null) === parentId
  )
}

/** Ob die Hauptkategorie aus der Vorlage schon existiert. */
export function isTemplateMainExisting(categories: Category[], mainName: string): boolean {
  return Boolean(findCategoryByName(categories, mainName, null))
}

/** Ob die Unterkategorie (unter Vorlagen-Hauptname) schon existiert. */
export function isTemplateSubExisting(
  categories: Category[],
  mainName: string,
  childName: string
): boolean {
  const main = findCategoryByName(categories, mainName, null)
  if (!main) return false
  return Boolean(findCategoryByName(categories, childName, main.id))
}

/**
 * Standard-Auswahl, aber bereits vorhandene Namen abgewählt
 * (UI: durchgestrichen / disabled).
 */
export function createCategoryTemplateSelectionSkippingExisting(
  existingCategories: Category[]
): CategoryTemplateSelection {
  const selection = createDefaultCategoryTemplateSelection()
  for (const item of STANDARD_CATEGORY_TREE) {
    if (isTemplateMainExisting(existingCategories, item.name)) {
      selection.main[item.name] = false
    }
    if (!item.children?.length) continue
    for (const child of item.children) {
      if (isTemplateSubExisting(existingCategories, item.name, child)) {
        selection.sub[item.name]![child] = false
      }
    }
  }
  return selection
}

export function hasAnyCategoryTemplateSelected(selection: CategoryTemplateSelection): boolean {
  for (const item of STANDARD_CATEGORY_TREE) {
    if (selection.main[item.name]) return true
    const subs = selection.sub[item.name]
    if (subs && Object.values(subs).some(Boolean)) return true
  }
  return false
}

/**
 * Erstellt fehlende Kategorien aus der Vorlage. Bereits vorhandene (gleicher Name/Parent) bleiben.
 * @returns Anzahl neu angelegter Kategorien
 */
export async function applyStandardCategoryTemplates(
  departmentId: string,
  existingCategories: Category[],
  selection: CategoryTemplateSelection = createDefaultCategoryTemplateSelection()
): Promise<{ createdCount: number; categories: Category[] }> {
  const categories = [...existingCategories]
  let createdCount = 0

  for (const item of STANDARD_CATEGORY_TREE) {
    const mainSelected = selection.main[item.name]
    const subs = selection.sub[item.name]
    const anySubSelected = Boolean(subs && Object.values(subs).some(Boolean))
    if (!mainSelected && !anySubSelected) continue

    let mainCategory = findCategoryByName(categories, item.name, null)
    if (!mainCategory && (mainSelected || anySubSelected)) {
      mainCategory = await createCategory({
        department_id: departmentId,
        name: item.name,
        parent_id: null,
      })
      createdCount += 1
      categories.push(mainCategory)
    }

    if (!mainCategory) continue

    for (const childName of item.children || []) {
      if (!subs?.[childName]) continue
      if (findCategoryByName(categories, childName, mainCategory.id)) continue

      const createdChild = await createCategory({
        department_id: departmentId,
        name: childName,
        parent_id: mainCategory.id,
      })
      createdCount += 1
      categories.push(createdChild)
    }
  }

  return { createdCount, categories }
}
