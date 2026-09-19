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
