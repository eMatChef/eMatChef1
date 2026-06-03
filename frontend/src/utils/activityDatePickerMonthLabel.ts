const monthLabelFormatter = new Intl.DateTimeFormat('de-CH', { month: 'long' })

/** Monatsname für Kalender-Kopfzeile (z. B. «Juni»). */
export function formatActivityPickerMonthLabel(month: number, year: number): string {
  const raw = monthLabelFormatter.format(new Date(year, month, 1))
  return raw.charAt(0).toUpperCase() + raw.slice(1)
}

export function formatActivityPickerYearLabel(leftYear: number, rightYear: number): string {
  if (leftYear === rightYear) return String(leftYear)
  return `${leftYear} – ${rightYear}`
}
