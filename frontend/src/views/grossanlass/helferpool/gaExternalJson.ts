/** Hilfen für den JSON-Baum der API-Daten: Pfade im selben Punktformat wie das Backend (`a.b.0.c`). */

export type GaJsonLeaf = { path: string; sample: string }

/** Listen von Objekten im JSON: mögliche Datensatzlisten (Pfad `''` = Wurzel). */
export function findRecordLists(json: unknown, maxDepth = 4): { path: string; count: number }[] {
  const out: { path: string; count: number }[] = []
  const walk = (node: unknown, path: string, depth: number) => {
    if (Array.isArray(node)) {
      if (node.length > 0 && node.every((n) => n !== null && typeof n === 'object' && !Array.isArray(n))) out.push({ path, count: node.length })
      return
    }
    if (node !== null && typeof node === 'object' && depth < maxDepth) {
      for (const [key, value] of Object.entries(node as Record<string, unknown>)) walk(value, path ? `${path}.${key}` : key, depth + 1)
    }
  }
  walk(json, '', 0)
  return out
}

export function valueAt(node: unknown, path: string): unknown {
  if (path === '') return node
  let current: unknown = node
  for (const segment of path.split('.')) {
    if (current === null || typeof current !== 'object' || !(segment in (current as object))) return undefined
    current = (current as Record<string, unknown>)[segment]
  }
  return current
}

/** Alle Felder (Blätter) der ersten Datensätze, mit einem Beispielwert; Listen in Datensätzen erscheinen mit Index. */
export function leafPaths(records: unknown[], sampleSize = 20, maxLeaves = 300): GaJsonLeaf[] {
  const seen = new Map<string, string>()
  const walk = (node: unknown, path: string, depth: number) => {
    if (seen.size >= maxLeaves) return
    if (node === null || typeof node !== 'object') {
      if (!seen.has(path)) seen.set(path, node === null ? 'null' : String(node))
      return
    }
    if (depth > 6) return
    for (const [key, value] of Object.entries(node as Record<string, unknown>)) walk(value, path ? `${path}.${key}` : key, depth + 1)
  }
  for (const record of records.slice(0, sampleSize)) walk(record, '', 0)
  return [...seen].map(([path, sample]) => ({ path, sample: sample.length > 80 ? `${sample.slice(0, 77)}…` : sample }))
}

export function parseJson(body: string): { ok: true; value: unknown } | { ok: false } {
  try {
    return { ok: true, value: JSON.parse(body) }
  } catch {
    return { ok: false }
  }
}
