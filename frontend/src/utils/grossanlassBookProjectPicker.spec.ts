import { describe, expect, it } from 'vitest'
import {
  BOOK_PROJECT_UNASSIGNED,
  buildBookProjectPickerItems,
  type BookProjectPickerGroup,
} from '@/utils/grossanlassBookProjectPicker'

function group(
  id: string,
  name: string,
  parentId: string | null,
  nodeType: string,
  sort = 0,
): BookProjectPickerGroup {
  return { id, name, parent_id: parentId, node_type: nodeType, sort_order: sort }
}

const tree = [
  group('infra', 'Infrastruktur', null, 'ressort', 10),
  group('wasser', 'BL Wasser', 'infra', 'unterressort', 10),
  group('a2', 'Wasserstelle A2', 'wasser', 'bauprojekt', 10),
  group('test', 'Wasserstellen Test', 'wasser', 'bauprojekt', 20),
  group('logistik', 'Material & Logistik', null, 'ressort', 20),
  group('empty', 'Leeres Ressort', null, 'ressort', 30),
]

describe('buildBookProjectPickerItems', () => {
  it('nests groups and counts direct wishes vs descendants', () => {
    const items = buildBookProjectPickerItems(tree, [
      { groupId: 'infra' },
      { groupId: 'infra' },
      { groupId: 'a2' },
      { groupId: 'a2' },
      { groupId: 'a2' },
      { groupId: 'logistik' },
    ])

    expect(items.map((row) => row.name)).toEqual([
      'Infrastruktur',
      'BL Wasser',
      'Wasserstelle A2',
      'Material & Logistik',
    ])
    expect(items.find((row) => row.value === 'infra')).toMatchObject({
      depth: 0,
      wishCount: 2,
      belowCount: 3,
      title: 'Infrastruktur',
    })
    expect(items.find((row) => row.value === 'wasser')).toMatchObject({
      depth: 1,
      wishCount: 0,
      belowCount: 3,
      props: { disabled: true },
      title: 'Infrastruktur › BL Wasser',
    })
    expect(items.find((row) => row.value === 'a2')).toMatchObject({
      depth: 2,
      wishCount: 3,
      belowCount: 0,
      title: 'Infrastruktur › BL Wasser › Wasserstelle A2',
    })
    expect(items.find((row) => row.value === 'logistik')?.wishCount).toBe(1)
    expect(items.some((row) => row.value === 'empty')).toBe(false)
    expect(items.some((row) => row.value === 'test')).toBe(false)
  })

  it('keeps a ressort selectable when wishes sit on the ressort itself', () => {
    const items = buildBookProjectPickerItems(tree, [{ groupId: 'infra' }])
    const infra = items.find((row) => row.value === 'infra')
    expect(infra?.wishCount).toBe(1)
    expect(infra?.props).toBeUndefined()
    expect(items).toHaveLength(1)
  })

  it('falls back to the wish label when groups are missing', () => {
    const items = buildBookProjectPickerItems([], [
      { groupId: 'a2', ressort: 'Wasserstelle A2' },
      { groupId: 'a2', ressort: 'Wasserstelle A2' },
    ])
    expect(items).toEqual([
      expect.objectContaining({
        value: 'a2',
        name: 'Wasserstelle A2',
        wishCount: 2,
        depth: 0,
      }),
    ])
  })

  it('collects wishes without a group', () => {
    const items = buildBookProjectPickerItems(
      tree,
      [{ groupId: 'logistik' }, { groupId: null }, { ressort: 'x' }],
      'Ohne Zuordnung',
    )
    expect(items.at(-1)).toMatchObject({
      value: BOOK_PROJECT_UNASSIGNED,
      name: 'Ohne Zuordnung',
      wishCount: 2,
    })
  })
})
