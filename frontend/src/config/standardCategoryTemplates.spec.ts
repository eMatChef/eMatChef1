import { describe, expect, it } from 'vitest'
import {
  STANDARD_CATEGORY_TREE,
  createDefaultCategoryTemplateSelection,
  createCategoryTemplateSelectionSkippingExisting,
  hasAnyCategoryTemplateSelected,
  isTemplateMainExisting,
  isTemplateSubExisting,
} from '@/config/standardCategoryTemplates'
import type { Category } from '@/api/categories'

function cat(partial: Partial<Category> & Pick<Category, 'id' | 'name'>): Category {
  return {
    description: null,
    parent_id: null,
    sort_order: 0,
    material_count: 0,
    ...partial,
  }
}

describe('standardCategoryTemplates', () => {
  it('has pioneer and kitchen with children', () => {
    expect(STANDARD_CATEGORY_TREE.some((c) => c.name === 'Pionier' && (c.children?.length ?? 0) > 0)).toBe(
      true
    )
    expect(STANDARD_CATEGORY_TREE.some((c) => c.name === 'Küche')).toBe(true)
  })

  it('defaults to all selected', () => {
    const selection = createDefaultCategoryTemplateSelection()
    expect(hasAnyCategoryTemplateSelected(selection)).toBe(true)
    expect(selection.main['Pionier']).toBe(true)
    expect(selection.sub['Pionier']?.['Werkzeug']).toBe(true)
  })

  it('detects empty selection', () => {
    const selection = createDefaultCategoryTemplateSelection()
    for (const key of Object.keys(selection.main)) selection.main[key] = false
    for (const parent of Object.keys(selection.sub)) {
      for (const child of Object.keys(selection.sub[parent]!)) {
        selection.sub[parent]![child] = false
      }
    }
    expect(hasAnyCategoryTemplateSelected(selection)).toBe(false)
  })

  it('skips existing names in selection and detects them', () => {
    const existing: Category[] = [
      cat({ id: 'main1', name: 'Pionier', parent_id: null }),
      cat({ id: 'sub1', name: 'Werkzeug', parent_id: 'main1' }),
    ]
    expect(isTemplateMainExisting(existing, 'Pionier')).toBe(true)
    expect(isTemplateSubExisting(existing, 'Pionier', 'Werkzeug')).toBe(true)
    expect(isTemplateSubExisting(existing, 'Pionier', 'Seil')).toBe(false)

    const selection = createCategoryTemplateSelectionSkippingExisting(existing)
    expect(selection.main['Pionier']).toBe(false)
    expect(selection.sub['Pionier']?.['Werkzeug']).toBe(false)
    expect(selection.sub['Pionier']?.['Seil']).toBe(true)
    expect(selection.main['Küche']).toBe(true)
  })
})
