export const GA_BESTAND_SUB_TABS = ['alles', 'eigen', 'leihweise', 'gaeste', 'js'] as const
export type GaBestandSubTab = (typeof GA_BESTAND_SUB_TABS)[number]

/** Unter-Reiter (interne Kennung) → URL-Segment unter `/ga/material/stock/`. */
const GA_BESTAND_SUB_PATHS: Record<string, string> = {
  eigen: 'own',
  leihweise: 'loaned',
  gaeste: 'guests',
  js: 'js',
}

export function gaBestandListPath(departmentId: string, from = 'alles'): string {
  const id = departmentId
  if (from === 'fahrzeuge') return `/${id}/ga/vehicles`
  if (from === 'wareneingang') return `/${id}/ga/material/goods-receipt`
  if (from in GA_BESTAND_SUB_PATHS) {
    return `/${id}/ga/material/stock/${GA_BESTAND_SUB_PATHS[from]}`
  }
  return `/${id}/ga/material/stock`
}

export function gaBestandArtikelPath(departmentId: string, itemId: string, from?: string) {
  return {
    path: `/${departmentId}/ga/material/items/${itemId}`,
    query: from ? { from } : {},
  }
}
