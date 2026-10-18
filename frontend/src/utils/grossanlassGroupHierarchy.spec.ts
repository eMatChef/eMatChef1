import { describe, expect, it } from 'vitest'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { assigneesFor } from './grossanlassGroupHierarchy'

const group = (id: string, parent_id: string | null, members: { user_id: string; name: string }[] = []) =>
  ({ id, name: id, parent_id, members, leaders: [] }) as unknown as GrossanlassGroup

describe('assigneesFor', () => {
  it('löst den Zuständigen über die Mitglieder auf', () => {
    const groups = [group('p', null, [{ user_id: 'u1', name: 'Lea' }])]
    expect(assigneesFor('p', groups)).toEqual([{ value: 'u1', title: 'Lea' }])
  })

  it('nimmt die Mitglieder der Eltern dazu, dedupliziert und sortiert nach Name', () => {
    const groups = [
      group('r', null, [
        { user_id: 'u2', name: 'Zoe' },
        { user_id: 'u1', name: 'Lea' },
      ]),
      group('b', 'r', [{ user_id: 'u1', name: 'Lea (Bühne)' }]),
      group('p', 'b', [{ user_id: 'u3', name: 'Anna' }]),
    ]
    expect(assigneesFor('p', groups)).toEqual([
      { value: 'u3', title: 'Anna' },
      { value: 'u1', title: 'Lea (Bühne)' },
      { value: 'u2', title: 'Zoe' },
    ])
  })

  it('liefert für eine unbekannte Gruppe nichts und bricht bei zyklischen Eltern ab', () => {
    expect(assigneesFor('x', [group('p', null, [{ user_id: 'u1', name: 'Lea' }])])).toEqual([])
    const loop = [group('a', 'b', [{ user_id: 'u1', name: 'Lea' }]), group('b', 'a')]
    expect(assigneesFor('a', loop)).toEqual([{ value: 'u1', title: 'Lea' }])
  })
})
