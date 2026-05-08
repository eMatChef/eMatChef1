export type BlogLocale = 'de' | 'en' | 'fr'

export interface PublicBlogPost {
  id: string
  title: string
  summary: string
  coverImage: string
  bodyHtml: string
  createdAt: string
  status: 'draft' | 'published'
  slug: string
  excerpt: string
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function newId(i: number): string {
  return `legacy-${i}`
}

function slugify(raw: string): string {
  return raw
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-')
}

function datePrefix(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function stripHtml(html: string): string {
  return html
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function makeExcerpt(bodyHtml: string, max = 170): string {
  const plain = stripHtml(bodyHtml)
  if (plain.length <= max) return plain
  return `${plain.slice(0, max).trimEnd()}…`
}

export function preferredBlogLocale(localeValue: string): BlogLocale {
  const lc = String(localeValue || 'de').toLowerCase()
  if (lc.startsWith('en')) return 'en'
  if (lc.startsWith('fr')) return 'fr'
  return 'de'
}

export function localizedBlogContent(raw: Record<string, unknown>, localeValue: string): Record<string, unknown> {
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return raw
  const locales = localesRaw as Record<string, unknown>
  const order: BlogLocale[] = [preferredBlogLocale(localeValue), 'de', 'en', 'fr']
  for (const loc of order) {
    const entry = locales[loc]
    if (entry && typeof entry === 'object') {
      return entry as Record<string, unknown>
    }
  }
  return raw
}

export function normalizePublicPosts(
  localized: Record<string, unknown>,
  fallback: Record<string, unknown>,
  untitledFallback: string,
): PublicBlogPost[] {
  const raw = localized.posts ?? fallback.posts
  if (!Array.isArray(raw)) return []

  const taken = new Set<string>()
  const posts = raw.map((p, i) => {
    if (typeof p !== 'object' || !p) {
      const id = newId(i)
      return { id, title: '', summary: '', coverImage: '', bodyHtml: '<p></p>', createdAt: '', status: 'published' as const }
    }
    const o = p as Record<string, unknown>
    const id = typeof o.id === 'string' && o.id ? o.id : newId(i)
    const title = String(o.title ?? '')
    let bodyHtml = typeof o.bodyHtml === 'string' ? o.bodyHtml : ''
    if (!bodyHtml && typeof o.excerpt === 'string' && o.excerpt.trim()) {
      bodyHtml = `<p>${escapeHtml(o.excerpt)}</p>`
    }
    if (!bodyHtml) bodyHtml = '<p></p>'
    const createdAt = typeof o.createdAt === 'string' ? o.createdAt : ''
    const status: 'draft' | 'published' = o.status === 'draft' ? 'draft' : 'published'
    const summary = typeof o.summary === 'string' ? o.summary : ''
    const coverImage = typeof o.coverImage === 'string' ? o.coverImage : ''
    return { id, title, summary, coverImage, bodyHtml, createdAt, status }
  })

  const visible = posts.filter((p) => p.status === 'published')
  const sorted = [...visible].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
  return sorted.map((p) => {
    const safeTitle = p.title.trim() || untitledFallback
    const base = [datePrefix(p.createdAt), slugify(safeTitle)].filter(Boolean).join('-') || p.id
    let slug = base
    let n = 2
    while (taken.has(slug)) {
      slug = `${base}-${n}`
      n++
    }
    taken.add(slug)
    return {
      ...p,
      slug,
      excerpt: p.summary.trim() ? p.summary.trim() : makeExcerpt(p.bodyHtml),
    }
  })
}

