/** Material dimensions are stored in kg (weight) and cm (length/width/height). */

export type MaterialMetricUnit = 'kg' | 'cm'

function trimNumericString(n: number): string {
  const rounded = Math.round(n * 10000) / 10000
  const s = String(rounded)
  if (!s.includes('.')) return s
  return s.replace(/\.?0+$/, '')
}

/** kg string (DB) → Gramm-Anzeige für Eingabe */
export function kgToGramsDisplay(kgRaw: string | null | undefined): string {
  const kg = parseFloat(String(kgRaw ?? '').replace(',', '.'))
  if (!Number.isFinite(kg) || kg <= 0) return ''
  return trimNumericString(kg * 1000)
}

/** Gramm-Eingabe → kg string (DB) */
export function gramsToKgString(gramsRaw: string | null | undefined): string | null {
  const raw = String(gramsRaw ?? '').trim()
  if (!raw) return null
  const g = parseFloat(raw.replace(',', '.'))
  if (!Number.isFinite(g) || g < 0) return null
  if (g === 0) return '0'
  return trimNumericString(g / 1000)
}

/**
 * Normalizes free-text input to a plain numeric string in the standard unit (kg or cm).
 * Accepts optional unit suffixes and converts mm→cm, m→cm, g→kg when clearly indicated.
 */
export function normalizeMaterialMetricInput(
  raw: string | null | undefined,
  targetUnit: MaterialMetricUnit,
): string | null {
  let s = String(raw ?? '').trim()
  if (!s) return null

  s = s.replace(/\s+/g, ' ').replace(',', '.')

  const mm = s.match(/^([\d.]+)\s*mm$/i)
  if (mm && targetUnit === 'cm') {
    const n = parseFloat(mm[1])
    return Number.isFinite(n) ? trimNumericString(n / 10) : s
  }

  const meters = s.match(/^([\d.]+)\s*m$/i)
  if (meters && targetUnit === 'cm') {
    const n = parseFloat(meters[1])
    return Number.isFinite(n) ? trimNumericString(n * 100) : s
  }

  const grams = s.match(/^([\d.]+)\s*g(?:ramm)?$/i)
  if (grams && targetUnit === 'kg') {
    const n = parseFloat(grams[1])
    return Number.isFinite(n) ? trimNumericString(n / 1000) : s
  }

  if (targetUnit === 'kg') {
    s = s.replace(/\s*kg$/i, '').trim()
  } else {
    s = s.replace(/\s*cm$/i, '').trim()
  }

  return s || null
}
