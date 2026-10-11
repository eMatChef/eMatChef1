/**
 * Gemeinsame Formularlogik für Auftrag und Bauauftrag («Mein Ressort» und «Planung»).
 * Beide Ansichten nutzen denselben Dialog; Formularzustand, Validierung und Speicher-Payload liegen nur hier.
 */
import type { GrossanlassGroup, GrossanlassGroupKind } from '@/api/grossanlassGroups'
import type { GaOrderType } from '@/views/grossanlass/auftraege/gaAuftraegeModel'

export type GaOrderForm = {
  type: GaOrderType
  name: string
  parent_id: string
  window_start: string
  window_end: string
  build_status: string
  description: string
  /** Nur für normale Aufträge: Standort ja/nein. Bauaufträge haben immer einen Standort. */
  include_on_map: boolean
}

export type GaOrderFormError = 'name' | 'parent'

export function orderKindOf(type: GaOrderType): GrossanlassGroupKind {
  return type === 'build' ? 'teilbereich' : 'auftrag'
}

export function orderTypeOfGroup(group: Pick<GrossanlassGroup, 'kind' | 'node_type'>): GaOrderType {
  return group.kind === 'teilbereich' || group.node_type === 'bauprojekt' ? 'build' : 'order'
}

export function emptyOrderForm(type: GaOrderType = 'order', parentId = ''): GaOrderForm {
  return {
    type,
    name: '',
    parent_id: parentId,
    window_start: '',
    window_end: '',
    build_status: '',
    description: '',
    include_on_map: false,
  }
}

export function orderFormFromGroup(group: GrossanlassGroup): GaOrderForm {
  return {
    type: orderTypeOfGroup(group),
    name: group.name,
    parent_id: group.parent_id || '',
    window_start: group.window_start || '',
    window_end: group.window_end || '',
    build_status: group.build_status || '',
    description: group.description || '',
    include_on_map: group.include_on_map === true || !!group.place,
  }
}

export function validateOrderForm(form: GaOrderForm): GaOrderFormError[] {
  const errors: GaOrderFormError[] = []
  if (!form.name.trim()) errors.push('name')
  if (!form.parent_id) errors.push('parent')
  return errors
}

/** Bauaufträge haben immer einen Standort, normale Aufträge nur auf Wunsch. */
export function orderHasPlace(form: Pick<GaOrderForm, 'type' | 'include_on_map'>): boolean {
  return form.type === 'build' || form.include_on_map
}

export function orderSavePayload(form: GaOrderForm, opts: { canSetStatus: boolean; editing: boolean }) {
  return {
    name: form.name.trim(),
    parent_id: form.parent_id,
    kind: orderKindOf(form.type),
    window_start: form.window_start || null,
    window_end: form.window_end || null,
    // Ohne Recht wird der Status nie überschrieben (Bearbeiten) bzw. nicht gesetzt (Anlegen).
    build_status: opts.canSetStatus ? form.build_status || null : opts.editing ? undefined : null,
    description: form.description.trim() || null,
    include_on_map: form.type === 'order' ? form.include_on_map : undefined,
  }
}

/**
 * Standortname fürs Speichern. Erwartet die Gruppe einen bestehenden Standort, der noch nicht geladen ist,
 * ist der angezeigte Name nur der vorläufige Gruppenname und darf nie als Standortname gespeichert werden.
 */
export function placeNameForSave(
  typedName: string,
  current: { name: string } | null | undefined,
  expectsExistingPlace: boolean,
): string | undefined {
  const name = typedName.trim()
  if (!name) return undefined
  if (expectsExistingPlace && !current) return undefined
  return name !== current?.name ? name : undefined
}
