import { describe, expect, it } from 'vitest'
import {
  STANDARD_CATEGORY_TREE,
  createDefaultCategoryTemplateSelection,
  hasAnyCategoryTemplateSelected,
} from '@/config/standardCategoryTemplates'

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
})
