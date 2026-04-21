/**
 * Levenshtein-Distanz (klein = ähnlicher).
 */
export function levenshtein(a: string, b: string): number {
  const s = a.toLowerCase()
  const t = b.toLowerCase()
  const m = s.length
  const n = t.length
  if (m === 0) return n
  if (n === 0) return m
  const row = new Array<number>(n + 1)
  for (let j = 0; j <= n; j++) row[j] = j
  for (let i = 1; i <= m; i++) {
    let prev = row[0]
    row[0] = i
    for (let j = 1; j <= n; j++) {
      const tmp = row[j]
      const cost = s[i - 1] === t[j - 1] ? 0 : 1
      row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + cost)
      prev = tmp
    }
  }
  return row[n]
}

/**
 * Höhere Werte = besserer Treffer für Sortierung (Abteilungsnamen vs. Suchtext).
 */
export function departmentNameMatchScore(name: string, needle: string): number {
  const a = name.trim().toLowerCase()
  const n = needle.trim().toLowerCase()
  if (!n) return 0
  if (a === n) return 1_000_000
  if (a.startsWith(n)) return 500_000 + n.length * 100
  if (a.includes(n)) return 200_000 + n.length * 50
  const maxLen = Math.max(a.length, n.length, 1)
  const d = levenshtein(a, n)
  return Math.max(0, 100_000 - Math.round((d * 100_000) / maxLen))
}
