export type PublicPageLocale = 'de' | 'en' | 'fr'

const PUBLIC_PAGE_LOCALES: PublicPageLocale[] = ['de', 'en', 'fr']

export function preferredPublicLocale(localeValue: string): PublicPageLocale {
  const lc = String(localeValue || 'de').toLowerCase()
  if (lc.startsWith('en')) return 'en'
  if (lc.startsWith('fr')) return 'fr'
  return 'de'
}

/** CMS-Eintrag mit optionalem `locales`-Block (FAQ, TOS, Impressum, Blog). */
export function localizedPublicContent(
  raw: Record<string, unknown>,
  localeValue: string,
): Record<string, unknown> {
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return raw
  const locales = localesRaw as Record<string, unknown>
  const order: PublicPageLocale[] = [
    preferredPublicLocale(localeValue),
    ...PUBLIC_PAGE_LOCALES,
  ]
  const seen = new Set<PublicPageLocale>()
  for (const loc of order) {
    if (seen.has(loc)) continue
    seen.add(loc)
    const entry = locales[loc]
    if (entry && typeof entry === 'object') {
      return entry as Record<string, unknown>
    }
  }
  return raw
}
