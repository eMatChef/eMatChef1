import type { GrossanlassNodeType } from '@/api/grossanlassGroups'

/** Einheitliche Icons/Farben für den GA-Ressort-Stamm (Einstellungen → Ressorts). */
export function grossanlassGroupNodeIcon(nodeType: string | undefined): string {
  if (nodeType === 'bauprojekt') return 'mdi-hammer-wrench'
  if (nodeType === 'auftrag') return 'mdi-clipboard-check-outline'
  if (nodeType === 'unterressort') return 'mdi-source-branch'
  return 'mdi-sitemap'
}

export function grossanlassGroupNodeKindKey(nodeType: string | undefined): string {
  if (nodeType === 'bauprojekt') return 'grossanlass.planung.ressorts.kindBauprojekt'
  if (nodeType === 'auftrag') return 'grossanlass.planung.ressorts.kindAuftrag'
  if (nodeType === 'unterressort') return 'grossanlass.planung.ressorts.kindUnterressort'
  return 'grossanlass.planung.ressorts.kindRessort'
}

export function resolveGrossanlassNodeType(
  nodeType: string | undefined,
): GrossanlassNodeType {
  if (nodeType === 'bauprojekt' || nodeType === 'auftrag' || nodeType === 'unterressort' || nodeType === 'ressort') {
    return nodeType
  }
  return 'ressort'
}

/** Bauprojekte und Aufträge sind Blätter der Struktur: sie liegen an Ressort/Bereich und tragen keine Kinder. */
export function isGrossanlassLeafNodeType(nodeType: string | null | undefined): boolean {
  return nodeType === 'bauprojekt' || nodeType === 'auftrag'
}
