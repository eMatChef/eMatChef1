export type LandingLocale = 'de' | 'en' | 'fr'

export interface LandingFeatureItem {
  icon: string
  title: string
  text: string
}

export interface LandingLocaleContent {
  kicker: string
  heroTitle: string
  heroSubtitle: string
  primaryCta: string
  secondaryCta: string
  secondaryCtaPath: string
  introTitle: string
  introParagraph1: string
  introParagraph2: string
  featuresTitle: string
  features: LandingFeatureItem[]
  ctaTitleSrOnly: string
  ctaText: string
}

export const LANDING_LOCALES: LandingLocale[] = ['de', 'en', 'fr']

export const DEFAULT_FEATURE_ICONS = ['⊙', '⌗', '◎', '⇄', '◇', '○'] as const

/** Leerer Inhalt — öffentliche Seite zeigt nur DB-Werte (keine i18n-Fallbacks). */
export function emptyLandingLocale(): LandingLocaleContent {
  return {
    kicker: '',
    heroTitle: '',
    heroSubtitle: '',
    primaryCta: '',
    secondaryCta: '',
    secondaryCtaPath: '',
    introTitle: '',
    introParagraph1: '',
    introParagraph2: '',
    featuresTitle: '',
    features: [],
    ctaTitleSrOnly: '',
    ctaText: '',
  }
}

/** Vorlage für Editor und Server-Defaults (nicht als öffentlicher Fallback). */
export function defaultLandingDe(): LandingLocaleContent {
  return {
    kicker: 'Erste Version online',
    heroTitle: 'Material im Griff, Team im Blick',
    heroSubtitle:
      'Nach über fünf Jahren Entwicklung ist eMatChef live: Du als Materialwart behältst den Überblick — deine Mitleiter planen Lager und Samstage und buchen Material direkt.',
    primaryCta: 'Jetzt anmelden',
    secondaryCta: 'Häufige Fragen',
    secondaryCtaPath: '/faq',
    introTitle: 'Für Materialwart und Team',
    introParagraph1:
      'eMatChef ist die gemeinsame Stelle für Material und Anlässe in deiner Abteilung. Als Materialwart pflegst du Bestände, Lagerorte und Buchungen — und siehst jederzeit, was für welches Lager, welchen Samstag oder welches Event reserviert ist. Deine Mitleiter legen Aktivitäten an, tragen Material ein und reichen ein. Du prüfst, packst und behältst den Überblick bis zur Retour.',
    introParagraph2:
      'Alles läuft im Browser, ohne Installation. Rollen legen fest, wer was sehen und bearbeiten darf. QR-Codes am Material helfen beim Wiederfinden — wo eure Abteilung es einrichtet. Das ist Version 1: bewusst ein Start, der im Alltag funktionieren soll — mit eurem Feedback für die nächsten Schritte.',
    featuresTitle: 'So arbeitet ihr mit eMatChef',
    features: [
      {
        icon: '⊙',
        title: 'Materialwart im Überblick',
        text: 'Alle Aktivitäten und Buchungen der Abteilung auf einen Blick — packen, ausgeben, Retour: du weisst, was gerade wo ist.',
      },
      {
        icon: '◎',
        title: 'Mitleiter planen selbst',
        text: 'Lager, Samstage und Events anlegen, Material buchen und einreichen — ohne Listen-Chaos per Chat oder Zettel.',
      },
      {
        icon: '⇄',
        title: 'Aktivitäten & Buchungen',
        text: 'Vom Entwurf über Einreichen und Packen bis zur Retour: der Bestand bleibt mit dem echten Einsatz verbunden.',
      },
      {
        icon: '⌗',
        title: 'Alles an einem Ort',
        text: 'Material, Mengen, Lagerorte und Bewegungen — strukturiert statt in verstreuten Tabellen.',
      },
      {
        icon: '◇',
        title: 'QR am Material',
        text: 'Scan am Regal oder unterwegs: Infos, Seriennummer — und optional Kontakt zum Materialwart.',
      },
      {
        icon: '○',
        title: 'Im Browser',
        text: 'Keine App installieren: anmelden und mit deiner Abteilung in einer gemeinsamen Oberfläche arbeiten.',
      },
    ],
    ctaTitleSrOnly: 'Loslegen',
    ctaText:
      'Bereit zum Ausprobieren? Melde dich an — oder lies zuerst die häufigen Fragen. Wir freuen uns über dein Feedback zur ersten Version.',
  }
}

function normalizeFeatureRow(row: unknown, index: number): LandingFeatureItem {
  const fallbackIcon = DEFAULT_FEATURE_ICONS[index % DEFAULT_FEATURE_ICONS.length]
  if (typeof row !== 'object' || !row) {
    return { icon: fallbackIcon, title: '', text: '' }
  }
  const o = row as Record<string, unknown>
  const rawIcon = String(o.icon ?? fallbackIcon).trim()
  // Legacy: max. 8 Zeichen (Ein-Zeichen-Symbole). MDI-Namen (mdi-…) nicht kürzen.
  const icon = rawIcon.startsWith('mdi-')
    ? rawIcon.slice(0, 96)
    : rawIcon.slice(0, 8) || fallbackIcon
  return {
    icon,
    title: String(o.title ?? ''),
    text: String(o.text ?? ''),
  }
}

function normalizeFeatures(raw: unknown, fallbackFeatures: LandingFeatureItem[]): LandingFeatureItem[] {
  if (!Array.isArray(raw) || raw.length === 0) {
    return fallbackFeatures.map((f) => ({ ...f }))
  }
  return raw.map((row, i) => normalizeFeatureRow(row, i))
}

export function normalizeLandingLocale(
  raw: Record<string, unknown>,
  fallback: LandingLocaleContent = defaultLandingDe(),
): LandingLocaleContent {
  const intro =
    raw.intro && typeof raw.intro === 'object' ? (raw.intro as Record<string, unknown>) : {}
  const featuresBlock =
    raw.features && typeof raw.features === 'object' ? (raw.features as Record<string, unknown>) : {}
  const cta = raw.cta && typeof raw.cta === 'object' ? (raw.cta as Record<string, unknown>) : {}

  return {
    kicker: String(raw.kicker ?? fallback.kicker),
    heroTitle: String(raw.heroTitle ?? fallback.heroTitle),
    heroSubtitle: String(raw.heroSubtitle ?? fallback.heroSubtitle),
    primaryCta: String(raw.primaryCta ?? fallback.primaryCta),
    secondaryCta: String(raw.secondaryCta ?? fallback.secondaryCta),
    secondaryCtaPath: String(raw.secondaryCtaPath ?? fallback.secondaryCtaPath),
    introTitle: String(raw.introTitle ?? intro.title ?? fallback.introTitle),
    introParagraph1: String(raw.introParagraph1 ?? intro.paragraph1 ?? fallback.introParagraph1),
    introParagraph2: String(raw.introParagraph2 ?? intro.paragraph2 ?? fallback.introParagraph2),
    featuresTitle: String(raw.featuresTitle ?? featuresBlock.title ?? fallback.featuresTitle),
    features: normalizeFeatures(featuresBlock.items ?? raw.featureItems, fallback.features),
    ctaTitleSrOnly: String(raw.ctaTitleSrOnly ?? cta.titleSrOnly ?? fallback.ctaTitleSrOnly),
    ctaText: String(raw.ctaText ?? cta.text ?? fallback.ctaText),
  }
}

export function normalizeLandingContent(
  raw: Record<string, unknown>,
): Record<LandingLocale, LandingLocaleContent> {
  const deFallback = defaultLandingDe()
  const legacy = normalizeLandingLocale(raw, deFallback)
  const out: Record<LandingLocale, LandingLocaleContent> = {
    de: legacy,
    en: { ...deFallback, features: deFallback.features.map((f) => ({ ...f })) },
    fr: { ...deFallback, features: deFallback.features.map((f) => ({ ...f })) },
  }
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return out
  const localesObj = localesRaw as Record<string, unknown>
  for (const loc of LANDING_LOCALES) {
    const entry = localesObj[loc]
    if (entry && typeof entry === 'object') {
      out[loc] = normalizeLandingLocale(entry as Record<string, unknown>, loc === 'de' ? deFallback : out.de)
    }
  }
  return out
}

export function preferredLandingLocale(localeValue: string): LandingLocale {
  const lc = String(localeValue || 'de').toLowerCase()
  if (lc.startsWith('en')) return 'en'
  if (lc.startsWith('fr')) return 'fr'
  return 'de'
}

export function localizedLandingRaw(
  raw: Record<string, unknown>,
  localeValue: string,
): Record<string, unknown> {
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return raw
  const locales = localesRaw as Record<string, unknown>
  const order: LandingLocale[] = [preferredLandingLocale(localeValue), 'de', 'en', 'fr']
  for (const loc of order) {
    const entry = locales[loc]
    if (entry && typeof entry === 'object') {
      return { ...raw, ...(entry as Record<string, unknown>) }
    }
  }
  return raw
}

export function landingLocaleToPayload(loc: LandingLocaleContent): Record<string, unknown> {
  return {
    kicker: loc.kicker,
    heroTitle: loc.heroTitle,
    heroSubtitle: loc.heroSubtitle,
    primaryCta: loc.primaryCta,
    secondaryCta: loc.secondaryCta,
    secondaryCtaPath: loc.secondaryCtaPath,
    introTitle: loc.introTitle,
    introParagraph1: loc.introParagraph1,
    introParagraph2: loc.introParagraph2,
    featuresTitle: loc.featuresTitle,
    features: {
      title: loc.featuresTitle,
      items: loc.features.map((f) => ({ icon: f.icon, title: f.title, text: f.text })),
    },
    ctaTitleSrOnly: loc.ctaTitleSrOnly,
    ctaText: loc.ctaText,
    cta: { titleSrOnly: loc.ctaTitleSrOnly, text: loc.ctaText },
    intro: {
      title: loc.introTitle,
      paragraph1: loc.introParagraph1,
      paragraph2: loc.introParagraph2,
    },
  }
}

export function buildLandingSavePayload(
  locales: Record<LandingLocale, LandingLocaleContent>,
): Record<string, unknown> {
  const de = locales.de
  return {
    ...landingLocaleToPayload(de),
    locales: Object.fromEntries(LANDING_LOCALES.map((loc) => [loc, landingLocaleToPayload(locales[loc])])),
  }
}

export interface LandingDisplay {
  kicker: string
  heroTitle: string
  heroSubtitle: string
  primaryCta: string
  secondaryCta: string
  secondaryCtaPath: string
  introTitle: string
  introParagraph1: string
  introParagraph2: string
  featuresTitle: string
  features: LandingFeatureItem[]
  ctaTitleSrOnly: string
  ctaText: string
}

/** Öffentliche Startseite: nur Inhalt aus site_page (keine de.json-Fallbacks). */
/** Teilt CTA-Fliesstext, wenn eine FAQ-Phrase vorkommt (für RouterLink in der Landing). */
export function parseLandingCtaFaqLink(
  text: string,
): { before: string; link: string; after: string } | null {
  const trimmed = text.trim()
  if (!trimmed) return null

  const patterns = [
    /häufigen Fragen/i,
    /häufige Fragen/i,
    /frequently asked questions/i,
    /questions\s*(?:&|and)\s*answers/i,
    /\bFAQ\b/,
  ]

  for (const re of patterns) {
    const match = trimmed.match(re)
    if (match?.index !== undefined) {
      return {
        before: trimmed.slice(0, match.index),
        link: match[0],
        after: trimmed.slice(match.index + match[0].length),
      }
    }
  }

  return null
}

export function resolveLandingDisplay(
  siteRaw: Record<string, unknown>,
  localeValue: string,
): LandingDisplay {
  const merged = localizedLandingRaw(siteRaw, localeValue)
  const normalized = normalizeLandingLocale(merged, emptyLandingLocale())

  return {
    kicker: normalized.kicker.trim(),
    heroTitle: normalized.heroTitle.trim(),
    heroSubtitle: normalized.heroSubtitle.trim(),
    primaryCta: normalized.primaryCta.trim(),
    secondaryCta: normalized.secondaryCta.trim(),
    secondaryCtaPath: normalized.secondaryCtaPath.trim(),
    introTitle: normalized.introTitle.trim(),
    introParagraph1: normalized.introParagraph1.trim(),
    introParagraph2: normalized.introParagraph2.trim(),
    featuresTitle: normalized.featuresTitle.trim(),
    features: normalized.features.filter((f) => f.title.trim() || f.text.trim()),
    ctaTitleSrOnly: normalized.ctaTitleSrOnly.trim(),
    ctaText: normalized.ctaText.trim(),
  }
}
