import type { RouteLocationNormalized } from 'vue-router'
import { i18n } from '@/i18n'
import { usePageHeadStore } from '@/stores/pageHead'

/** vue-i18n-Keys – Fallback wie `index.html`, wenn keine route.meta gesetzt ist */
export const PAGE_HEAD_KEYS = {
  defaultTitle: 'router.meta.fallbackDocumentTitle',
  defaultDescription: 'router.meta.fallbackDocumentDescription',
} as const

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

function clearDynamicHead(): void {
  document.querySelectorAll(`meta[${DYNAMIC_ATTR}]`).forEach((el) => el.remove())
}

export function applyPageHead(title: string, description: string): void {
  document.title = title
  setMetaName('description', description)
  setMetaProperty('og:title', title)
  setMetaProperty('og:description', description)
  setMetaProperty('og:type', 'website')
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
  applyPageHead(title, description)
}

/** Nur dynamische og/meta-Tags entfernen und Default-Titel setzen (selten nötig) */
export function resetPageHeadToDefault(): void {
  clearDynamicHead()
  applyPageHead(defaultDocumentTitle(), defaultPageDescription())
}
