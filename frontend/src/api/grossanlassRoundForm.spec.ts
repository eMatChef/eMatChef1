import { describe, expect, it } from 'vitest'
import {
  applyFormBuilderFieldOrder,
  availableFormBuilderAddOptions,
  createFormBuilderField,
  listFormBuilderInputFields,
  orderFormFieldsForRound,
  type GrossanlassRoundFormField,
} from '@/api/grossanlassRoundForm'

function customText(partial: Partial<GrossanlassRoundFormField> & { id: string }): GrossanlassRoundFormField {
  return {
    role: 'input',
    system_key: null,
    custom_type: 'text',
    label: 'Textfrage',
    help_text: null,
    required: false,
    enabled: true,
    sort_order: 20,
    options: null,
    config: null,
    ...partial,
  }
}

describe('Form-Builder Feldlisten', () => {
  it('listFormBuilderInputFields behält Objektidentität für v-model', () => {
    const note = customText({ id: 'f-note', label: 'Notiz', sort_order: 70 })
    const fields = [
      createFormBuilderField({ kind: 'system', system_key: 'ressort_wahl' }, 10),
      note,
    ]

    const listed = listFormBuilderInputFields(fields)
    const editable = listed.find((f) => f.id === 'f-note')
    expect(editable).toBe(note)

    editable!.label = 'Neue Notiz'
    expect(note.label).toBe('Neue Notiz')
  })

  it('orderFormFieldsForRound klont Felder und ist daher nicht für Beschriftungs-v-model geeignet', () => {
    const note = customText({ id: 'f-note', label: 'Notiz', sort_order: 70 })
    const cloned = orderFormFieldsForRound([note]).find((f) => f.id === 'f-note')
    expect(cloned).not.toBe(note)

    cloned!.label = 'Neue Notiz'
    expect(note.label).toBe('Notiz')
  })

  it('applyFormBuilderFieldOrder behält die gegebene Eingabereihenfolge', () => {
    const a = customText({ id: 'a', sort_order: 50 })
    const b = customText({ id: 'b', sort_order: 20 })
    const ordered = applyFormBuilderFieldOrder([a, b])

    expect(ordered[0]).toBe(a)
    expect(ordered[1]).toBe(b)
    expect(a.sort_order).toBe(10)
    expect(b.sort_order).toBe(20)
  })

  it('bietet Zeitraum nicht an, wenn Wann-Phasen schon existieren', () => {
    const when = createFormBuilderField({ kind: 'custom', custom_type: 'select' }, 10)
    when.options = { multiple: true, choices: ['Vor dem Anlass', 'Am Anlass', 'Nach dem Anlass'] }
    const options = availableFormBuilderAddOptions([when])
    expect(options.some((opt) => opt.kind === 'custom' && opt.custom_type === 'date_range')).toBe(false)
  })

  it('bietet Zeitraum an, wenn keine Wann-Phasen existieren', () => {
    const color = createFormBuilderField({ kind: 'custom', custom_type: 'select' }, 10)
    color.options = { choices: ['Rot', 'Blau'] }
    const options = availableFormBuilderAddOptions([color])
    expect(options.some((opt) => opt.kind === 'custom' && opt.custom_type === 'date_range')).toBe(true)
  })
})
