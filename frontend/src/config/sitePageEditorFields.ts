export const SITE_PAGE_SLUGS = ['landing', 'blog', 'faq', 'tos', 'impressum'] as const

export type SitePageSlug = (typeof SITE_PAGE_SLUGS)[number]

export const SITE_PAGE_LABELS: Record<SitePageSlug, string> = {
  landing: 'Startseite',
  blog: 'Blog',
  faq: 'FAQ',
  tos: 'Nutzung & Datenschutz',
  impressum: 'Impressum',
}

/** Tabs unter „Allgemein“ (FAQ, Rechtliches, Impressum). */
export const SITE_GENERAL_TABS = ['faq', 'tos', 'impressum'] as const

export type SiteGeneralTab = (typeof SITE_GENERAL_TABS)[number]

export const SITE_GENERAL_TAB_LABELS: Record<SiteGeneralTab, string> = {
  faq: 'FAQ',
  tos: 'Nutzung & Datenschutz',
  impressum: 'Impressum',
}

export function isSiteGeneralTab(s: string): s is SiteGeneralTab {
  return (SITE_GENERAL_TABS as readonly string[]).includes(s)
}
