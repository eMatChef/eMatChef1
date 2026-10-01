export const GA_BESTAND_SUB_TABS = ['alles', 'eigen', 'leihweise', 'gaeste', 'js'] as const
export type GaBestandSubTab = (typeof GA_BESTAND_SUB_TABS)[number]

export function gaBestandListPath(departmentId: string, from = 'alles'): string {
  const id = departmentId
  if (from === 'fahrzeuge') return `/${id}/fahrzeuge`
  if (from === 'wareneingang') return `/${id}/material-uebersicht/wareneingang`
  if (from === 'eigen' || from === 'leihweise' || from === 'gaeste' || from === 'js') {
    return `/${id}/material-uebersicht/bestand/${from}`
  }
  return `/${id}/material-uebersicht/bestand`
}

export function gaBestandArtikelPath(departmentId: string, itemId: string, from?: string) {
  return {
    path: `/${departmentId}/material-uebersicht/artikel/${itemId}`,
    query: from ? { from } : {},
  }
}
