export type AutoSaveFieldValue = string | number | boolean | null | undefined

export type AutoSaveFieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox'

/** Normalisiert Werte für Dirty-Vergleich (DB-Stand vs. Eingabe). */
export function normalizeAutoSaveValue(value: AutoSaveFieldValue): string {
  if (value == null || value === false) return ''
  if (typeof value === 'boolean') return value ? '1' : ''
  if (typeof value === 'number') return Number.isFinite(value) ? String(value) : ''
  return String(value).trim()
}

export function parseAutoSaveInputValue(
  raw: string,
  type: AutoSaveFieldType,
  currentValue: AutoSaveFieldValue,
): AutoSaveFieldValue {
  if (type === 'checkbox') return !!raw
  if (type === 'number') {
    const trimmed = raw.trim()
    if (trimmed === '') return currentValue === null ? null : ''
    const num = Number(trimmed)
    return Number.isFinite(num) ? num : raw
  }
  const trimmed = raw.trim()
  if (trimmed === '') return null
  return raw
}
