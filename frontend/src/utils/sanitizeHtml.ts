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
  ALLOWED_ATTR: ['href', 'target', 'rel', 'src', 'alt', 'style'],
  ALLOW_DATA_ATTR: false,
  ALLOW_ARIA_ATTR: false,
  ALLOW_UNKNOWN_PROTOCOLS: false,
  ALLOWED_URI_REGEXP: /^(?:(?:https?):\/\/|mailto:|tel:|data:image\/)/i,
}

let hooksInstalled = false
let mailStyleHookInstalled = false

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

function ensureMailStyleHook(): void {
  if (typeof window === 'undefined' || mailStyleHookInstalled) {
    return
  }
  mailStyleHookInstalled = true
  DOMPurify.addHook('uponSanitizeAttribute', (node, data) => {
    if (data.attrName !== 'style') {
      return
    }
    const value = String(data.attrValue || '').trim()
    const ok = node.nodeName === 'SPAN' && /^font-size:\s*\d+(\.\d+)?(pt|px)\s*;?$/i.test(value)
    if (!ok) {
      data.keepAttr = false
    }
  })
}

const PLAIN_TEXT_STRIP_CONFIG: Config = {
  ALLOWED_TAGS: [],
  ALLOWED_ATTR: [],
  ALLOW_DATA_ATTR: false,
  ALLOW_ARIA_ATTR: false,
}

const MAIL_PLAIN_TEXT_STRUCTURE_CONFIG: Config = {
  ALLOWED_TAGS: ['p', 'br', 'div', 'blockquote', 'li', 'ul', 'ol', 'span', 'strong', 'em', 'b', 'i', 'u'],
  ALLOWED_ATTR: [],
  ALLOW_DATA_ATTR: false,
  ALLOW_ARIA_ATTR: false,
}

function appendPlainTextFromNode(node: Node, parts: string[], layout: 'compact' | 'mail'): void {
  if (node.nodeType === Node.TEXT_NODE) {
    parts.push(node.textContent ?? '')
    return
  }
  if (node.nodeType !== Node.ELEMENT_NODE) {
    return
  }
  const el = node as Element
  if (layout === 'mail' && el.tagName === 'BR') {
    parts.push('\n')
    return
  }
  for (const child of el.childNodes) {
    appendPlainTextFromNode(child, parts, layout)
  }
  if (layout === 'mail' && el.tagName === 'P') {
    parts.push('\n\n')
  }
}

function plainTextFromSanitizedHtml(sanitized: string, layout: 'compact' | 'mail'): string {
  if (!sanitized) {
    return ''
  }
  if (layout === 'compact') {
    const doc = new DOMParser().parseFromString(`<div>${sanitized}</div>`, 'text/html')
    const text = doc.body.textContent ?? ''
    return text.replace(/\s+/g, ' ').trim()
  }
  const doc = new DOMParser().parseFromString(`<div>${sanitized}</div>`, 'text/html')
  const parts: string[] = []
  const root = doc.body.firstElementChild ?? doc.body
  for (const child of root.childNodes) {
    appendPlainTextFromNode(child, parts, layout)
  }
  return parts
    .join('')
    .replace(/\u00a0/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/**
 * HTML zu Klartext (DOMPurify + DOM-Textextraktion, kein Regex-Tag-Stripping).
 * `compact`: einzeilig, Whitespace normalisiert (z. B. Geo-Suchlabels).
 * `mail`: Absätze/Zeilenumbrüche wie bei Mail-HTML-Vorschau.
 */
export function htmlToPlainText(html: string, layout: 'compact' | 'mail' = 'compact'): string {
  const s = String(html || '').trim()
  if (!s) {
    return ''
  }

  const config = layout === 'mail' ? MAIL_PLAIN_TEXT_STRUCTURE_CONFIG : PLAIN_TEXT_STRIP_CONFIG
  const sanitized = DOMPurify.sanitize(s, config)
  return plainTextFromSanitizedHtml(sanitized, layout)
}

/** Sicheres HTML für v-html (öffentliche Seiten). */
export function sanitizePublicHtml(html: string): string {
  const s = String(html || '').trim()
  if (!s) {
    return ''
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

  ensureLinkHooks()
  ensureMailStyleHook()
  return DOMPurify.sanitize(s, MAIL_PURIFY_CONFIG)
}
