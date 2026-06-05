export interface TextSegment {
  text: string
  highlight: boolean
}

/** Suchbegriffe aus Freitext (Leerzeichen), z. B. «pfadi zürich opium». */
export function parseSearchTokens(query: string): string[] {
  return query
    .trim()
    .toLowerCase()
    .split(/\s+/)
    .filter((token) => token.length > 0)
}

function mergeRanges(ranges: Array<[number, number]>): Array<[number, number]> {
  if (ranges.length === 0) return []
  const sorted = [...ranges].sort((a, b) => a[0] - b[0])
  const merged: Array<[number, number]> = [sorted[0]]
  for (let i = 1; i < sorted.length; i++) {
    const last = merged[merged.length - 1]
    const current = sorted[i]
    if (current[0] <= last[1]) {
      last[1] = Math.max(last[1], current[1])
    } else {
      merged.push(current)
    }
  }
  return merged
}

function findTokenRanges(lower: string, tokens: string[]): Array<[number, number]> {
  const ranges: Array<[number, number]> = []
  for (const token of tokens) {
    let pos = 0
    while (pos < lower.length) {
      const idx = lower.indexOf(token, pos)
      if (idx === -1) break
      ranges.push([idx, idx + token.length])
      pos = idx + token.length
    }
  }
  return mergeRanges(ranges)
}

/** Zerlegt Text in Segmente; alle Such-Tokens werden hervorgehoben. */
export function textSegments(text: string | null | undefined, query: string): TextSegment[] {
  const value = text ?? ''
  const tokens = parseSearchTokens(query)
  if (!value) return []
  if (tokens.length === 0) {
    return [{ text: value, highlight: false }]
  }

  const lower = value.toLowerCase()
  const ranges = findTokenRanges(lower, tokens)
  if (ranges.length === 0) {
    return [{ text: value, highlight: false }]
  }

  const segments: TextSegment[] = []
  let pos = 0
  for (const [start, end] of ranges) {
    if (start > pos) {
      segments.push({ text: value.slice(pos, start), highlight: false })
    }
    segments.push({ text: value.slice(start, end), highlight: true })
    pos = end
  }
  if (pos < value.length) {
    segments.push({ text: value.slice(pos), highlight: false })
  }
  return segments
}

/** Jedes Token muss irgendwo im Suchtext vorkommen (UND-Verknüpfung). */
export function textMatchesAllTokens(haystack: string, query: string): boolean {
  const tokens = parseSearchTokens(query)
  if (tokens.length === 0) return true
  const lower = haystack.toLowerCase()
  return tokens.every((token) => lower.includes(token))
}
