/** Packed Auto-Save value for start/end (ISO Y-m-d). */
export function packBauprojektWindow(
  start?: string | null,
  end?: string | null,
): string {
  return `${(start || '').slice(0, 10)}|${(end || '').slice(0, 10)}`
}

export function unpackBauprojektWindow(value: unknown): { start: string; end: string } {
  const raw = String(value ?? '')
  const idx = raw.indexOf('|')
  if (idx < 0) {
    return { start: raw.slice(0, 10), end: '' }
  }
  return {
    start: raw.slice(0, idx).slice(0, 10),
    end: raw.slice(idx + 1).slice(0, 10),
  }
}

/** Grobes Projektfenster als Anzeige (ISO-Datum Y-m-d). */
export function formatBauprojektWindow(
  start: string | null | undefined,
  end: string | null | undefined,
): string {
  const from = (start || '').slice(0, 10)
  const to = (end || '').slice(0, 10)
  if (!from && !to) return ''
  if (from && to && from === to) return from
  if (from && to) return `${from} – ${to}`
  return from || to
}
