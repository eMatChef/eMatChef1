const DEFAULT_LOCALE = 'de-CH'

/** Vergleicht Strings mit numerischen Teilen (z. B. C1 < C2 < C10). */
export function compareNatural(a: string, b: string, locale = DEFAULT_LOCALE): number {
  return String(a ?? '').localeCompare(String(b ?? ''), locale, {
    numeric: true,
    sensitivity: 'base',
  })
}

export function sortByNaturalName<T>(
  items: readonly T[],
  getName: (item: T) => string,
  locale = DEFAULT_LOCALE,
): T[] {
  return [...items].sort((a, b) => compareNatural(getName(a), getName(b), locale))
}
