/**
 * Scan-String von Pistole/Handheld (URL oder Rohcode) — ohne Navigation zur URL.
 */

export type ScanParseResult =
  | { type: 'activity'; activityCode: string; raw: string }
  | { type: 'material_batch'; materialCode: string; batchCode: string; raw: string }
  | { type: 'unknown'; raw: string }

function extractPath(input: string): string {
  const trimmed = input.trim()
  if (!trimmed) return ''
  try {
    if (/^https?:\/\//i.test(trimmed)) {
      return new URL(trimmed).pathname
    }
  } catch {
    /* fall through */
  }
  if (trimmed.startsWith('/')) return trimmed.split(/[?#]/)[0] || ''
  return ''
}

export function parseScanInput(raw: string): ScanParseResult {
  const trimmed = raw.trim()
  if (!trimmed) {
    return { type: 'unknown', raw: '' }
  }

  const path = extractPath(trimmed) || trimmed

  const activityMatch = path.match(/\/i\/a\/([^/]+)\/?$/i) || path.match(/^i\/a\/([^/]+)\/?$/i)
  if (activityMatch?.[1]) {
    return {
      type: 'activity',
      activityCode: decodeURIComponent(activityMatch[1]),
      raw: trimmed,
    }
  }

  const batchMatch =
    path.match(/\/i\/m\/([^/]+)\/b\/([^/]+)\/?$/i) ||
    path.match(/^i\/m\/([^/]+)\/b\/([^/]+)\/?$/i)
  if (batchMatch?.[1] && batchMatch[2]) {
    return {
      type: 'material_batch',
      materialCode: decodeURIComponent(batchMatch[1]),
      batchCode: decodeURIComponent(batchMatch[2]),
      raw: trimmed,
    }
  }

  return { type: 'unknown', raw: trimmed }
}

export function formatScanParseResult(result: ScanParseResult): string {
  switch (result.type) {
    case 'activity':
      return `activity:${result.activityCode}`
    case 'material_batch':
      return `batch:${result.materialCode}/${result.batchCode}`
    default:
      return result.raw ? `unknown:${result.raw.slice(0, 80)}` : 'empty'
  }
}
