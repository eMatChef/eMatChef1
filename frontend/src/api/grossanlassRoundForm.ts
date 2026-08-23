import apiClient from './apiClient'

export type GrossanlassFormFieldRole = 'input' | 'meta'

/** Aktive System-Eingabe (löst Systemlogik aus). */
export type GrossanlassFormInputSystemKey = 'bauprojekt' | 'ressort_wahl'

/** Metadaten — fest vorgegeben, nur Listenanzeige. */
export type GrossanlassFormMetaSystemKey = 'submitter' | 'created_at' | 'updated_at'

/** Legacy — nur in bestehenden Formularen. */
export type GrossanlassFormLegacySystemKey =
  | 'wish_kind'
  | 'label'
  | 'quantity'
  | 'location'
  | 'period'
  | 'notes'
  | 'ressort'

export type GrossanlassFormSystemKey =
  | GrossanlassFormInputSystemKey
  | GrossanlassFormMetaSystemKey
  | GrossanlassFormLegacySystemKey

export type GrossanlassFormCustomType = 'text' | 'number' | 'select' | 'date_range'

export interface GrossanlassRoundFormField {
  id: string
  role: GrossanlassFormFieldRole
  system_key: GrossanlassFormSystemKey | null
  custom_type: GrossanlassFormCustomType | null
  label: string
  help_text: string | null
  required: boolean
  enabled: boolean
  sort_order: number
  options: { choices?: string[]; multiple?: boolean } | null
  config: { allow_new_bauprojekt?: boolean; leader_scope?: boolean } | null
  /** Gesetzt vom Backend — Feld hat bereits Antworten und darf nicht entfernt werden. */
  has_response_values?: boolean
}

export interface GrossanlassRoundForm {
  id: string
  round_id: string
  intro_text: string | null
  fields: GrossanlassRoundFormField[]
  created_at: string
  updated_at: string
}

export async function getGrossanlassRoundForm(
  departmentId: string,
  roundId: string,
): Promise<GrossanlassRoundForm> {
  const response = await apiClient.get<GrossanlassRoundForm>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/form`,
  )
  return response.data
}

export async function updateGrossanlassRoundForm(
  departmentId: string,
  roundId: string,
  payload: {
    intro_text?: string | null
    fields: GrossanlassRoundFormField[]
  },
): Promise<GrossanlassRoundForm> {
  const response = await apiClient.put<GrossanlassRoundForm>(
    `/api/departments/${departmentId}/grossanlass/planung/rounds/${roundId}/form`,
    payload,
  )
  return response.data
}

/** Metadaten — immer im Formular, nicht löschbar. */
export const META_FIELD_DEFS: Array<{
  system_key: GrossanlassFormMetaSystemKey
  role: 'meta'
  defaultLabel: string
}> = [
  { system_key: 'submitter', role: 'meta', defaultLabel: 'Eingereicht von' },
  { system_key: 'created_at', role: 'meta', defaultLabel: 'Erstellt' },
  { system_key: 'updated_at', role: 'meta', defaultLabel: 'Zuletzt bearbeitet' },
]

/** Fest eingebaut — Ressort-Zuordnung, nicht über «+» wählbar. */
export const FIXED_INPUT_FIELD_DEFS: Array<{
  system_key: 'ressort_wahl'
  role: 'input'
  defaultLabel: string
  configurable?: boolean
}> = [{ system_key: 'ressort_wahl', role: 'input', defaultLabel: 'Ressort', configurable: true }]

/** Optional über «Feld hinzufügen» — ergänzt Ressort-Zuordnung (nicht Ersatz). */
export const ADDABLE_SYSTEM_FIELD_DEFS: Array<{
  system_key: 'bauprojekt'
  role: 'input'
  defaultLabel: string
  configurable?: boolean
}> = [{ system_key: 'bauprojekt', role: 'input', defaultLabel: 'Bauprojekt', configurable: true }]

export const SYSTEM_FIELD_DEFS: Array<{
  system_key: GrossanlassFormSystemKey
  role: GrossanlassFormFieldRole
  defaultLabel: string
  configurable?: boolean
}> = [...FIXED_INPUT_FIELD_DEFS, ...ADDABLE_SYSTEM_FIELD_DEFS, ...META_FIELD_DEFS]

export const CUSTOM_TYPE_DEFS: Array<{ type: GrossanlassFormCustomType; defaultLabel: string }> = [
  { type: 'text', defaultLabel: 'Textfrage' },
  { type: 'number', defaultLabel: 'Zahl' },
  { type: 'select', defaultLabel: 'Auswahl' },
  { type: 'date_range', defaultLabel: 'Zeitraum' },
]

const DEFAULT_SYSTEM_PROPS: Partial<
  Record<GrossanlassFormSystemKey, { required?: boolean; config?: GrossanlassRoundFormField['config'] }>
> = {
  bauprojekt: { required: true, config: { allow_new_bauprojekt: true, leader_scope: false } },
  ressort_wahl: { required: true, config: { leader_scope: false } },
}

export type FormBuilderAddKind =
  | { kind: 'system'; system_key: 'bauprojekt' }
  | { kind: 'custom'; custom_type: GrossanlassFormCustomType }

export function isMetaSystemField(field: GrossanlassRoundFormField): boolean {
  return field.role === 'meta'
}

export function isFixedSystemField(field: GrossanlassRoundFormField): boolean {
  return isMetaSystemField(field) || field.system_key === 'ressort_wahl'
}

export function canRemoveFormBuilderField(field: GrossanlassRoundFormField): boolean {
  return !isFixedSystemField(field) && !field.has_response_values
}

export function normalizeSystemFieldLabels(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  applySystemFieldDefaultLabels(fields)
  return fields
}

/** Setzt System-Beschriftungen in-place — Objektidentität bleibt für v-model erhalten. */
export function applySystemFieldDefaultLabels(fields: GrossanlassRoundFormField[]): void {
  for (const f of fields) {
    if (!f.system_key) continue
    const def = SYSTEM_FIELD_DEFS.find((d) => d.system_key === f.system_key)
    if (def) f.label = def.defaultLabel
  }
}

export function isEditableCustomField(field: GrossanlassRoundFormField): boolean {
  return field.role === 'input' && !field.system_key && !!field.custom_type
}

export function isLegacySystemInputField(field: GrossanlassRoundFormField): boolean {
  if (!field.system_key || field.role !== 'input') return false
  return field.system_key !== 'bauprojekt' && field.system_key !== 'ressort_wahl'
}

export function createFormBuilderField(
  kind:
    | FormBuilderAddKind
    | { kind: 'system'; system_key: GrossanlassFormMetaSystemKey | 'ressort_wahl' },
  sortOrder: number,
  tempId?: string,
): GrossanlassRoundFormField {
  const id = tempId || `new_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`

  if (kind.kind === 'system') {
    const def =
      FIXED_INPUT_FIELD_DEFS.find((d) => d.system_key === kind.system_key) ||
      ADDABLE_SYSTEM_FIELD_DEFS.find((d) => d.system_key === kind.system_key) ||
      META_FIELD_DEFS.find((d) => d.system_key === kind.system_key)
    const extras = DEFAULT_SYSTEM_PROPS[kind.system_key as GrossanlassFormSystemKey] || {}
    return {
      id,
      role: def?.role || 'input',
      system_key: kind.system_key,
      custom_type: null,
      label: def?.defaultLabel || kind.system_key,
      help_text: null,
      required: extras.required ?? false,
      enabled: true,
      sort_order: sortOrder,
      options: null,
      config: extras.config ?? null,
    }
  }

  const def = CUSTOM_TYPE_DEFS.find((d) => d.type === kind.custom_type)
  return {
    id,
    role: 'input',
    system_key: null,
    custom_type: kind.custom_type,
    label: def?.defaultLabel || 'Frage',
    help_text: null,
    required: false,
    enabled: true,
    sort_order: sortOrder,
    options: kind.custom_type === 'select' ? { choices: [], multiple: false } : null,
    config: null,
  }
}

export function dedupeSystemInputFields(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  const seen = new Set<string>()
  return fields.filter((f) => {
    if (f.system_key !== 'ressort_wahl' && f.system_key !== 'bauprojekt') return true
    if (seen.has(f.system_key)) return false
    seen.add(f.system_key)
    return true
  })
}

function ensureMetaFieldsOnly(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  const next = fields.filter((f) => !(f.role === 'meta' && f.system_key === 'ressort'))
  const usedMeta = new Set(
    next.filter((f) => f.role === 'meta').map((f) => f.system_key).filter(Boolean),
  )
  let sortOrder = next.length > 0 ? Math.max(...next.map((f) => f.sort_order)) + 10 : 100

  for (const def of META_FIELD_DEFS) {
    if (!usedMeta.has(def.system_key)) {
      next.push(createFormBuilderField({ kind: 'system', system_key: def.system_key }, sortOrder))
      sortOrder += 10
    }
  }

  return next
}

export function ensureFixedSystemFields(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  let next = dedupeSystemInputFields(ensureMetaFieldsOnly(fields))

  if (!next.some((f) => f.system_key === 'ressort_wahl')) {
    next = [
      createFormBuilderField({ kind: 'system', system_key: 'ressort_wahl' }, 10),
      ...next,
    ]
  }

  return next
}

/** Metadaten ans Ende; Eingabefelder in konfigurierter Reihenfolge. */
export function orderFormFieldsForRound(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  const next = ensureFixedSystemFields(fields)
  const sorted = sortFormFields(next)
  const inputs = sorted.filter((f) => f.role === 'input')
  const meta = sorted.filter((f) => f.role === 'meta')

  return [...inputs, ...meta].map((f, i) => ({
    ...f,
    sort_order: (i + 1) * 10,
  }))
}

/** @deprecated use ensureFixedSystemFields */
export function ensureMetaSystemFields(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  return ensureFixedSystemFields(fields)
}

export function sortFormFields(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  return [...fields].sort((a, b) => a.sort_order - b.sort_order || a.id.localeCompare(b.id))
}

/** Dieselben Objekt-Referenzen wie `fields` — für den Form-Builder (v-model). */
export function listFormBuilderInputFields(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  return sortFormFields(fields.filter((f) => f.role === 'input'))
}

export function listFormBuilderMetaFields(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  return sortFormFields(fields.filter((f) => f.role === 'meta'))
}

/** Sortiert Eingabe- vor Metafeldern und schreibt sort_order in-place. */
export function applyFormBuilderFieldOrder(fields: GrossanlassRoundFormField[]): GrossanlassRoundFormField[] {
  const ordered = [...listFormBuilderInputFields(fields), ...listFormBuilderMetaFields(fields)]
  ordered.forEach((f, i) => {
    f.sort_order = (i + 1) * 10
  })
  return ordered
}

export function nextFormFieldSortOrder(fields: GrossanlassRoundFormField[]): number {
  if (fields.length === 0) return 10
  return Math.max(...fields.map((f) => f.sort_order)) + 10
}

export function availableFormBuilderAddOptions(
  fields: GrossanlassRoundFormField[],
): FormBuilderAddKind[] {
  const usedSystem = new Set(fields.map((f) => f.system_key).filter(Boolean))
  const options: FormBuilderAddKind[] = []

  for (const def of ADDABLE_SYSTEM_FIELD_DEFS) {
    if (!usedSystem.has(def.system_key)) {
      options.push({ kind: 'system', system_key: def.system_key })
    }
  }
  for (const def of CUSTOM_TYPE_DEFS) {
    options.push({ kind: 'custom', custom_type: def.type })
  }

  return options
}

export function formBuilderFieldTypeLabel(
  field: GrossanlassRoundFormField,
  t: (key: string) => string,
): string {
  if (field.system_key) {
    return t(`grossanlass.formBuilder.systemKeys.${field.system_key}`)
  }
  if (field.custom_type) {
    return t(`grossanlass.formBuilder.customTypes.${field.custom_type}`)
  }
  return field.label
}
