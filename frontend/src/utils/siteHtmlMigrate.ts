/** Hilfen für Migration alter Klartext-Felder → HTML (TipTap). */

export function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

export function plainToP(s: string): string {
  const t = String(s || '').trim()
  if (!t) return '<p></p>'
  return `<p>${escapeHtml(t)}</p>`
}
