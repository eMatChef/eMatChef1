export type InquiryMailPositionCheck = {
  mentioned: string[]
  omitted: string[]
  unexpected: string[]
  unexpectedQuotes: { name: string; quote: string }[]
}

const MIN_NAME_LEN = 3

export function htmlToPlainForMatch(html: string): string {
  const withBreaks = html.replace(
    /<br\s*\/?>|<\/p>|<\/div>|<\/li>|<\/h2>|<\/h3>|<\/strong>/gi,
    '\n',
  )
  const stripped = withBreaks.replace(/<[^>]+>/g, ' ')
  const decoded = stripped
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/g, "'")
  return decoded.replace(/\s+/g, ' ').trim()
}

export function isMatchablePositionName(name: string): boolean {
  return name.trim().length >= MIN_NAME_LEN
}

export function findMentionedNames(plain: string, names: string[]): string[] {
  const unique: string[] = []
  const seen = new Set<string>()
  for (const raw of names) {
    const name = raw.trim()
    if (!isMatchablePositionName(name) || seen.has(name)) continue
    seen.add(name)
    unique.push(name)
  }
  unique.sort((a, b) => b.length - a.length)
  const found: string[] = []
  let haystack = plain
  for (const name of unique) {
    const pattern = mentionPattern(name)
    if (!pattern.test(haystack)) continue
    found.push(name)
    haystack = haystack.replace(pattern, ' ')
  }
  return found
}

export function quoteMention(plain: string, name: string, pad = 42): string {
  const pattern = new RegExp(`.{0,${pad}}${escapeRegExp(name)}.{0,${pad}}`, 'iu')
  const match = plain.match(pattern)
  return match ? match[0].trim() : name
}

export function matchInquiryMailPositions(
  body: string,
  allowed: string[],
  other: string[],
): InquiryMailPositionCheck {
  const plain = htmlToPlainForMatch(body)
  const mentioned = findMentionedNames(plain, allowed)
  const mentionedSet = new Set(mentioned)
  const omitted = allowed.filter((name) => {
    const trimmed = name.trim()
    return isMatchablePositionName(trimmed) && !mentionedSet.has(trimmed)
  })
  const unexpected = findMentionedNames(plain, other)
  return {
    mentioned,
    omitted,
    unexpected,
    unexpectedQuotes: unexpected.map((name) => ({
      name,
      quote: quoteMention(plain, name),
    })),
  }
}

/** PDF: Rest aus dem Mailtext, sonst alle erlaubten Positionen. */
export function defaultPdfItemLabels(allowed: string[], omitted: string[]): string[] {
  const cleanAllowed = allowed.map((name) => name.trim()).filter((name) => name !== '')
  if (omitted.length) {
    const allowedSet = new Set(cleanAllowed)
    return omitted.map((name) => name.trim()).filter((name) => allowedSet.has(name))
  }
  return cleanAllowed
}

function mentionPattern(name: string): RegExp {
  return new RegExp(
    `(?<![\\p{L}\\p{N}-])${escapeRegExp(name)}(?![\\p{L}\\p{N}-])`,
    'iu',
  )
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
