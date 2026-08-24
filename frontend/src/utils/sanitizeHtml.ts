import DOMPurify from 'dompurify'
import type { Config } from 'dompurify'

/**
 * Öffentliches HTML für v-html (DOMPurify: gepflegte XSS-Abwehr).
 * Nur erlaubte Tags; Links nur http(s)/mailto/tel; externe Links mit rel/target.
 */

const ALLOWED_TAGS = [
  'p',
  'br',
  'strong',
  'em',
  'u',
  's',
  'h1',
  'h2',
  'h3',
  'h4',
  'ul',
  'ol',
  'li',
  'a',
  'blockquote',
  'code',
  'pre',
  'span',
]

const PURIFY_CONFIG: Config = {
  ALLOWED_TAGS,
  ALLOWED_ATTR: ['href', 'target', 'rel'],
  ALLOW_DATA_ATTR: false,
  ALLOW_ARIA_ATTR: false,
  ALLOW_UNKNOWN_PROTOCOLS: false,
  ALLOWED_URI_REGEXP: /^(?:(?:https?):\/\/|mailto:|tel:)/i,
}

const MAIL_PURIFY_CONFIG: Config = {
  ALLOWED_TAGS: [...ALLOWED_TAGS, 'img'],
  ALLOWED_ATTR: ['href', 'target', 'rel', 'src', 'alt'],
  ALLOW_DATA_ATTR: false,
  ALLOW_ARIA_ATTR: false,
  ALLOW_UNKNOWN_PROTOCOLS: false,
  ALLOWED_URI_REGEXP: /^(?:(?:https?):\/\/|mailto:|tel:|data:image\/)/i,
}

let hooksInstalled = false

function ensureLinkHooks(): void {
  if (typeof window === 'undefined' || hooksInstalled) {
    return
  }
  hooksInstalled = true
  DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.nodeName !== 'A' || node.nodeType !== 1) {
      return
    }
    const el = node as Element
    if (!el.hasAttribute('href')) {
      return
    }
    el.setAttribute('target', '_blank')
    el.setAttribute('rel', 'noopener noreferrer')
  })
}

function fallbackStripTags(html: string): string {
  return String(html || '')
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '')
    .replace(/<[^>]+>/g, '')
}

/** Sicheres HTML für v-html (öffentliche Seiten). */
export function sanitizePublicHtml(html: string): string {
  const s = String(html || '').trim()
  if (!s) {
    return ''
  }

  if (typeof window === 'undefined') {
    return fallbackStripTags(s)
  }

  ensureLinkHooks()
  return DOMPurify.sanitize(s, PURIFY_CONFIG)
}

/** HTML aus Mail-Vorlagen (TipTap) für die Vorschau. */
export function sanitizeMailHtml(html: string): string {
  const s = String(html || '').trim()
  if (!s) {
    return ''
  }

  if (typeof window === 'undefined') {
    return fallbackStripTags(s)
  }

  ensureLinkHooks()
  return DOMPurify.sanitize(s, MAIL_PURIFY_CONFIG)
}
