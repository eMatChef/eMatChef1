import { describe, expect, it } from 'vitest'
import {
  emptyOrderForm,
  orderHasPlace,
  orderKindOf,
  orderSavePayload,
  orderTypeOfGroup,
  placeNameForSave,
  validateOrderForm,
} from './grossanlassOrderForm'

describe('grossanlassOrderForm', () => {
  it('verlangt Name und Übergeordnet', () => {
    expect(validateOrderForm(emptyOrderForm())).toEqual(['name', 'parent'])
    expect(validateOrderForm({ ...emptyOrderForm('order', 'p1'), name: ' ' })).toEqual(['name'])
    expect(validateOrderForm({ ...emptyOrderForm('build', 'p1'), name: 'Bar' })).toEqual([])
  })

  it('bildet Typ auf Gruppenart ab', () => {
    expect(orderKindOf('order')).toBe('auftrag')
    expect(orderKindOf('build')).toBe('teilbereich')
    expect(orderTypeOfGroup({ kind: 'teilbereich', node_type: 'bauprojekt' })).toBe('build')
    expect(orderTypeOfGroup({ kind: 'auftrag', node_type: 'auftrag' })).toBe('order')
  })

  it('Bauauftrag hat immer einen Standort, Auftrag nur auf Wunsch', () => {
    expect(orderHasPlace({ type: 'build', include_on_map: false })).toBe(true)
    expect(orderHasPlace({ type: 'order', include_on_map: false })).toBe(false)
    expect(orderHasPlace({ type: 'order', include_on_map: true })).toBe(true)
  })

  it('überschreibt den Status ohne Recht nicht', () => {
    const form = { ...emptyOrderForm('order', 'p1'), name: ' A ', build_status: 'build' }
    expect(orderSavePayload(form, { canSetStatus: false, editing: true }).build_status).toBeUndefined()
    expect(orderSavePayload(form, { canSetStatus: false, editing: false }).build_status).toBeNull()
    expect(orderSavePayload(form, { canSetStatus: true, editing: true })).toMatchObject({ name: 'A', build_status: 'build', kind: 'auftrag' })
  })

  describe('placeNameForSave', () => {
    it('speichert den vorläufigen Gruppennamen nicht, solange der bestehende Standort nicht geladen ist', () => {
      expect(placeNameForSave('FOH Hauptbühne', undefined, true)).toBeUndefined()
      expect(placeNameForSave('FOH Hauptbühne', null, true)).toBeUndefined()
    })

    it('sendet nur einen geänderten Namen', () => {
      expect(placeNameForSave('FOH-Erweiterung', { name: 'FOH-Erweiterung' }, true)).toBeUndefined()
      expect(placeNameForSave(' FOH-Erweiterung TEST ', { name: 'FOH-Erweiterung' }, true)).toBe('FOH-Erweiterung TEST')
    })

    it('übernimmt beim Neuanlegen ohne bestehenden Standort den Namen', () => {
      expect(placeNameForSave('Neuer Ort', undefined, false)).toBe('Neuer Ort')
      expect(placeNameForSave('   ', undefined, false)).toBeUndefined()
    })
  })
})
