import type { RouteLocationNormalized } from 'vue-router'
import { usePageHeadStore } from '@/stores/pageHead'

/** Entspricht `index.html` – Fallback wenn keine route.meta gesetzt ist */
export const DEFAULT_DOCUMENT_TITLE = 'eMatChef - Materialverwaltung'

export const DEFAULT_PAGE_DESCRIPTION =
  'Materialverwaltung für Vermietungen – eMatChef.'

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
    const pt = m.meta.pageTitle
    if (typeof pt === 'string' && pt.length > 0) {
      const pd = m.meta.pageDescription
      return {
        title: pt,
        description: typeof pd === 'string' && pd.length > 0 ? pd : pt,
      }
    }
  }
  return { title: DEFAULT_DOCUMENT_TITLE, description: DEFAULT_PAGE_DESCRIPTION }
}

export function syncDocumentHead(route: RouteLocationNormalized): void {
  const { title, description } = resolvePageHead(route)
  applyPageHead(title, description)
}

/** Nur dynamische og/meta-Tags entfernen und Default-Titel setzen (selten nötig) */
export function resetPageHeadToDefault(): void {
  document.title = DEFAULT_DOCUMENT_TITLE
  clearDynamicHead()
}
