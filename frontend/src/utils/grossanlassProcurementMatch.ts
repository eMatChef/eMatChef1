export type ProcurementMatchKind = 'exact' | 'similar' | null

export function normalizeProcurementLabel(label: string): string {
  let value = label.trim().toLocaleLowerCase('de-CH')
  value = value.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
  value = value.replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim()
  if (!value) return ''
  const tokens = value.split(' ').map(stemProcurementToken).sort()
  return tokens.join(' ')
}

export function procurementLabelsAreSimilar(a: string, b: string): boolean {
  return keysAreSimilar(normalizeProcurementLabel(a), normalizeProcurementLabel(b))
}

export function procurementMatchKind(
  queryLabels: string[],
  candidateLabels: string[],
): ProcurementMatchKind {
  const candidates = candidateLabels
    .map((label) => normalizeProcurementLabel(label))
    .filter(Boolean)
  if (candidates.length === 0) return null
  let similar = false
  for (const raw of queryLabels) {
    const query = normalizeProcurementLabel(raw)
    if (!query) continue
    for (const candidate of candidates) {
      if (query === candidate) return 'exact'
      if (keysAreSimilar(query, candidate)) similar = true
    }
  }
  return similar ? 'similar' : null
}

function stemProcurementToken(token: string): string {
  if (token.length < 5) return token
  if (token.endsWith('en') && token.length > 5) return token.slice(0, -2)
  if (token.endsWith('er') && token.length > 5) return token.slice(0, -2)
  if (token.endsWith('e') && token.length > 4) return token.slice(0, -1)
  if (token.endsWith('n') && token.length > 4) return token.slice(0, -1)
  return token
}

function keysAreSimilar(a: string, b: string): boolean {
  if (!a || !b) return false
  if (a === b) return true
  const min = Math.min(a.length, b.length)
  const max = Math.max(a.length, b.length)
  if (min < 4) return false
  if (a.includes(b) || b.includes(a)) return Math.abs(a.length - b.length) <= 8
  const distance = levenshtein(a, b)
  return max >= 8 ? distance <= 2 : distance <= 1
}

function levenshtein(a: string, b: string): number {
  const m = a.length
  const n = b.length
  if (m === 0) return n
  if (n === 0) return m
  const row = Array.from({ length: n + 1 }, (_, index) => index)
  for (let i = 1; i <= m; i += 1) {
    let prev = i - 1
    row[0] = i
    for (let j = 1; j <= n; j += 1) {
      const cur = row[j]
      const cost = a[i - 1] === b[j - 1] ? 0 : 1
      row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + cost)
      prev = cur
    }
  }
  return row[n] ?? n
}
