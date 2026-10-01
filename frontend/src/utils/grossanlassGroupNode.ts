import type { GrossanlassNodeType } from '@/api/grossanlassGroups'

/** Einheitliche Icons/Farben für den GA-Ressort-Stamm (Einstellungen → Ressorts). */
export function grossanlassGroupNodeIcon(nodeType: string | undefined): string {
  if (nodeType === 'bauprojekt') return 'mdi-hammer-wrench'
  if (nodeType === 'unterressort') return 'mdi-source-branch'
  return 'mdi-sitemap'
}

export function grossanlassGroupNodeKindKey(nodeType: string | undefined): string {
  if (nodeType === 'bauprojekt') return 'grossanlass.planung.ressorts.kindBauprojekt'
  if (nodeType === 'unterressort') return 'grossanlass.planung.ressorts.kindUnterressort'
  return 'grossanlass.planung.ressorts.kindRessort'
}

export function resolveGrossanlassNodeType(
  nodeType: string | undefined,
): GrossanlassNodeType {
  if (nodeType === 'bauprojekt' || nodeType === 'unterressort' || nodeType === 'ressort') {
    return nodeType
  }
  return 'ressort'
}
