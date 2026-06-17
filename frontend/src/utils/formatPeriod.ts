function isSameDay(date1: string, date2: string): boolean {
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

function pad2(n: number): string {
  return String(n).padStart(2, '0')
}

function formatDayMonthYear(d: Date): string {
  return `${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${String(d.getFullYear()).slice(-2)}`
}

/** z. B. 14.03.26 oder 14.–18.03.26 */
export function formatPeriodCompact(startStr?: string | null, endStr?: string | null): string {
  if (!startStr) return ''
  const start = new Date(startStr)
  if (Number.isNaN(start.getTime())) return ''
  if (!endStr || isSameDay(startStr, endStr)) return formatDayMonthYear(start)
  const end = new Date(endStr)
  if (Number.isNaN(end.getTime())) return formatDayMonthYear(start)
  const d1 = pad2(start.getDate())
  const m1 = pad2(start.getMonth() + 1)
  const y1 = String(start.getFullYear()).slice(-2)
  const d2 = pad2(end.getDate())
  const m2 = pad2(end.getMonth() + 1)
  const y2 = String(end.getFullYear()).slice(-2)
  const sameMonth = start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()
  if (sameMonth && y1 === y2) return `${d1}.–${d2}.${m1}.${y1}`
  if (y1 === y2) return `${d1}.${m1}.–${d2}.${m2}.${y2}`
  return `${d1}.${m1}.${y1}–${d2}.${m2}.${y2}`
}
