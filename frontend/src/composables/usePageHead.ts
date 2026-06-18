import type { RouteLocationNormalized } from 'vue-router'
import { i18n } from '@/i18n'
import { usePageHeadStore } from '@/stores/pageHead'
import { getMainSiteOrigin, isAppOrigin, isQrPublicHost } from '@/utils/appLoginUrl'
import { isDevicesHost } from '@/utils/devicesHost'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'

/** vue-i18n-Keys – Fallback wie `index.html`, wenn keine route.meta gesetzt ist */
export const PAGE_HEAD_KEYS = {
  defaultTitle: 'router.meta.fallbackDocumentTitle',
  defaultDescription: 'router.meta.fallbackDocumentDescription',
} as const

const SITE_NAME = 'eMatChef'
const OG_LOCALE = 'de_CH'

function t(key: string): string {
  return i18n.global.t(key)
}

function defaultDocumentTitle(): string {
  return t(PAGE_HEAD_KEYS.defaultTitle)
}

function defaultPageDescription(): string {
  return t(PAGE_HEAD_KEYS.defaultDescription)
}

const DYNAMIC_ATTR = 'data-emc-head'

function setMetaName(name: string, content: string): void {
  let el = document.querySelector(`meta[${DYNAMIC_ATTR}][name="${name}"]`) as HTMLMetaElement | null
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(DYNAMIC_ATTR, '1')
    el.setAttribute('name', name)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function setMetaProperty(property: string, content: string): void {
  let el = document.querySelector(`meta[${DYNAMIC_ATTR}][property="${property}"]`) as HTMLMetaElement | null
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(DYNAMIC_ATTR, '1')
    el.setAttribute('property', property)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function setLinkRel(rel: string, href: string): void {
  let el = document.querySelector(`link[${DYNAMIC_ATTR}][rel="${rel}"]`) as HTMLLinkElement | null
  if (!el) {
    el = document.createElement('link')
    el.setAttribute(DYNAMIC_ATTR, '1')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

function setJsonLd(id: string, data: Record<string, unknown> | null): void {
  const selector = `script[${DYNAMIC_ATTR}][data-jsonld-id="${id}"]`
  const existing = document.querySelector(selector)
  if (!data) {
    existing?.remove()
    return
  }
  let el = existing as HTMLScriptElement | null
  if (!el) {
    el = document.createElement('script')
    el.setAttribute(DYNAMIC_ATTR, '1')
    el.setAttribute('data-jsonld-id', id)
    el.type = 'application/ld+json'
    document.head.appendChild(el)
  }
  el.textContent = JSON.stringify(data)
}

function clearDynamicHead(): void {
  document.querySelectorAll(`meta[${DYNAMIC_ATTR}], link[${DYNAMIC_ATTR}], script[${DYNAMIC_ATTR}]`).forEach((el) => {
    el.remove()
  })
}

function resolveSiteOrigin(): string {
  const configured = getMainSiteOrigin()
  if (configured) return configured
  if (typeof window !== 'undefined') {
    return window.location.origin
  }
  return 'https://ematchef.ch'
}

function resolveCanonicalUrl(route: RouteLocationNormalized): string {
  const origin = resolveSiteOrigin()
  const path = route.path || '/'
  return `${origin}${path === '/' ? '/' : path}`
}

function resolveOgImage(): string {
  return `${resolveSiteOrigin()}/og-image.png`
}

function shouldNoindex(route: RouteLocationNormalized): boolean {
  if (isDevToolsEnvironment()) {
    return true
  }
  if (isAppOrigin() || isQrPublicHost() || isDevicesHost()) {
    return true
  }
  return !route.matched.some((m) => m.meta.publicMarketing === true)
}

function organizationJsonLd(origin: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: SITE_NAME,
    url: origin,
    logo: `${origin}/favicon.svg`,
    description: defaultPageDescription(),
  }
}

function websiteJsonLd(origin: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: SITE_NAME,
    url: origin,
    inLanguage: 'de-CH',
  }
}

export function applyPageHead(title: string, description: string, route?: RouteLocationNormalized): void {
  document.title = title
  setMetaName('description', description)
  setMetaProperty('og:title', title)
  setMetaProperty('og:description', description)
  setMetaProperty('og:type', 'website')
  setMetaProperty('og:site_name', SITE_NAME)
  setMetaProperty('og:locale', OG_LOCALE)
  setMetaProperty('og:image', resolveOgImage())
  setMetaName('twitter:card', 'summary_large_image')
  setMetaName('twitter:title', title)
  setMetaName('twitter:description', description)
  setMetaName('twitter:image', resolveOgImage())

  if (route) {
    const canonical = resolveCanonicalUrl(route)
    setLinkRel('canonical', canonical)
    setMetaProperty('og:url', canonical)
    const noindex = shouldNoindex(route)
    setMetaName('robots', noindex ? 'noindex, nofollow' : 'index, follow')

    const origin = resolveSiteOrigin()
    const isLanding = route.name === 'LandingHome'
    setJsonLd('organization', isLanding ? organizationJsonLd(origin) : null)
    setJsonLd('website', isLanding ? websiteJsonLd(origin) : null)
  }
}

/**
 * Titel und Meta aus Route (route.meta) und optional dynamischem Store.
 */
export function resolvePageHead(route: RouteLocationNormalized): { title: string; description: string } {
  const store = usePageHeadStore()
  if (store.dynamicTitle) {
    return {
      title: store.dynamicTitle,
      description: store.dynamicDescription || store.dynamicTitle,
    }
  }
  for (let i = route.matched.length - 1; i >= 0; i--) {
    const m = route.matched[i]
    const titleKey = m.meta.pageTitleKey
    if (typeof titleKey === 'string' && titleKey.length > 0) {
      const descKey = m.meta.pageDescriptionKey
      const title = t(titleKey)
      const description =
        typeof descKey === 'string' && descKey.length > 0
          ? t(descKey)
          : t('router.meta.routeDescriptionDefault')
      return { title, description }
    }
  }
  return { title: defaultDocumentTitle(), description: defaultPageDescription() }
}

export function syncDocumentHead(route: RouteLocationNormalized): void {
  const { title, description } = resolvePageHead(route)
  applyPageHead(title, description, route)
}

/** Nur dynamische og/meta-Tags entfernen und Default-Titel setzen (selten nötig) */
export function resetPageHeadToDefault(): void {
  clearDynamicHead()
  applyPageHead(defaultDocumentTitle(), defaultPageDescription())
}
