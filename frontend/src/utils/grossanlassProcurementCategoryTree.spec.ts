import { describe, expect, it } from 'vitest'
import type { GrossanlassProcurementCategory } from '@/api/grossanlassProcurement'
import {
  isProcurementCategoryFullySelected,
  isProcurementCategoryIndeterminate,
  leafIdsOfProcurementCategorySelection,
  normalizeProcurementCategorySelection,
  selectionMatchesProcurementCategoryFilter,
  toggleProcurementCategorySelection,
  defaultCollapsedArticleParentIds,
  visibleProcurementCategoryTreeRows,
} from '@/utils/grossanlassProcurementCategoryTree'

function cat(
  id: string,
  name: string,
  parentId: string | null = null,
  sort = 0,
  kind: 'package' | 'item' = 'package',
): GrossanlassProcurementCategory {
  return {
    id,
    department_id: 'dept',
    parent_id: parentId,
    parent_name: null,
    name,
    sort_order: sort,
    rahmen_chf: null,
    system_key: null,
    kind,
  }
}

const tree = [
  cat('werkzeuge', 'Werkzeuge', null, 10),
  cat('elektro', 'Elektrowerkzeuge', 'werkzeuge', 10),
  cat('akku', 'Akkuschrauber', 'elektro', 10, 'item'),
  cat('bohrer', 'Bohrmaschinen', 'elektro', 20, 'item'),
  cat('hand', 'Handwerkzeuge', 'werkzeuge', 20),
  cat('hammer', 'Hämmer', 'hand', 10, 'item'),
]

describe('toggleProcurementCategorySelection', () => {
  it('checks a parent and all descendants', () => {
    const next = toggleProcurementCategorySelection(tree, [], 'elektro', true)
    expect(next.sort()).toEqual(['akku', 'bohrer', 'elektro'].sort())
  })

  it('unchecks a parent and all descendants', () => {
    const all = toggleProcurementCategorySelection(tree, [], 'werkzeuge', true)
    const next = toggleProcurementCategorySelection(tree, all, 'elektro', false)
    expect(next.includes('elektro')).toBe(false)
    expect(next.includes('akku')).toBe(false)
    expect(next.includes('hammer')).toBe(true)
    expect(next.includes('werkzeuge')).toBe(false)
  })

  it('marks the parent fully selected when the last child is checked', () => {
    const one = toggleProcurementCategorySelection(tree, [], 'akku', true)
    const next = toggleProcurementCategorySelection(tree, one, 'bohrer', true)
    expect(next.includes('elektro')).toBe(true)
  })
})

describe('leaf and filter helpers', () => {
  it('lists only leaves for the mail, expanding a parent with no child ticks', () => {
    expect(leafIdsOfProcurementCategorySelection(tree, ['elektro'])).toEqual(['akku', 'bohrer'])
    expect(leafIdsOfProcurementCategorySelection(tree, ['akku', 'elektro', 'bohrer'])).toEqual([
      'akku',
      'bohrer',
    ])
    expect(leafIdsOfProcurementCategorySelection(tree, ['akku'])).toEqual(['akku'])
  })

  it('lists package leaves when there are no articles', () => {
    const packagesOnly = [
      cat('werkzeuge', 'Werkzeuge', null, 10),
      cat('elektro', 'Elektrowerkzeuge', 'werkzeuge', 10),
      cat('hand', 'Handwerkzeuge', 'werkzeuge', 20),
    ]
    expect(leafIdsOfProcurementCategorySelection(packagesOnly, ['werkzeuge'])).toEqual([
      'elektro',
      'hand',
    ])
    expect(leafIdsOfProcurementCategorySelection(packagesOnly, ['hand'])).toEqual(['hand'])
  })

  it('expands a parent-only selection so checkboxes can show as fully ticked', () => {
    const next = normalizeProcurementCategorySelection(tree, ['elektro'])
    expect(next.includes('akku')).toBe(true)
    expect(next.includes('bohrer')).toBe(true)
    expect(next.includes('elektro')).toBe(true)
  })

  it('treats a parent as indeterminate when some leaves are selected', () => {
    expect(isProcurementCategoryIndeterminate(tree, ['akku'], 'elektro')).toBe(true)
    expect(isProcurementCategoryFullySelected(tree, ['akku', 'bohrer'], 'elektro')).toBe(true)
  })

  it('matches a filter on an ancestor when only a leaf is selected', () => {
    expect(selectionMatchesProcurementCategoryFilter(tree, ['akku'], 'werkzeuge')).toBe(true)
    expect(selectionMatchesProcurementCategoryFilter(tree, ['akku'], 'hand')).toBe(false)
  })
})

describe('visibleProcurementCategoryTreeRows', () => {
  it('shows the coarse tree when article parents start collapsed', () => {
    const collapsed = defaultCollapsedArticleParentIds(tree)
    expect(collapsed.sort()).toEqual(['elektro', 'hand'])
    const names = visibleProcurementCategoryTreeRows(tree, collapsed).map((row) => row.category.name)
    expect(names).toEqual(['Werkzeuge', 'Elektrowerkzeuge', 'Handwerkzeuge'])
  })

  it('reveals articles when a subcategory is expanded', () => {
    const names = visibleProcurementCategoryTreeRows(tree, ['hand']).map((row) => row.category.name)
    expect(names).toEqual([
      'Werkzeuge',
      'Elektrowerkzeuge',
      'Akkuschrauber',
      'Bohrmaschinen',
      'Handwerkzeuge',
    ])
  })
})
