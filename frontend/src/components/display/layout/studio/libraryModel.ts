import type { CustomBlock, LayoutContent, LayoutItem, OfficialBlock, WidgetDefinition } from '@/api/displayLayout'

/** Kategorien der Bausteinbibliothek in Anzeigereihenfolge. */
export const LIBRARY_CATEGORIES = ['general', 'material', 'logistics', 'projects', 'map', 'helpers', 'activity', 'rotation', 'custom', 'templates'] as const
export type LibraryCategory = (typeof LIBRARY_CATEGORIES)[number]

export type LibrarySource = 'widget' | 'official' | 'custom' | 'builtin'
export type LibraryKind = 'widget' | 'group' | 'region' | 'rotation'

export interface LibraryEntry {
  /** Eindeutiger Schlüssel (`widget:clock`, `official:region_materiallager`, `custom:abc`). */
  key: string
  source: LibrarySource
  id: string
  kind: LibraryKind
  category: LibraryCategory
  /** Bezeichnung nur bei eigenen Bausteinen (sonst über i18n). */
  name?: string
  defaultSize: [number, number]
  /** Rechtecke für die kleine Vorschau (Anteile 0–1) bei Gruppen und Bereichen. */
  preview: { x: number; y: number; w: number; h: number }[]
}

const FAMILY_CATEGORY: Record<string, LibraryCategory> = {
  material: 'material',
  logistik: 'logistics',
  overall: 'projects',
  projekt: 'projects',
  tasks: 'projects',
  map: 'map',
  helpers: 'helpers',
  feed: 'activity',
  warnings: 'activity',
}

export function widgetCategory(def: WidgetDefinition): LibraryCategory {
  if (def.category === 'general') return 'general'
  return FAMILY_CATEGORY[def.family ?? ''] ?? 'projects'
}

function previewOf(content: LayoutContent): LibraryEntry['preview'] {
  if (content.type !== 'group') return []
  const cols = content.columns ?? 12
  return content.items.map((i: LayoutItem) => ({ x: i.x / cols, y: i.y / content.rows, w: i.w / cols, h: i.h / content.rows }))
}

export function buildLibrary(widgets: WidgetDefinition[], official: OfficialBlock[], custom: CustomBlock[]): LibraryEntry[] {
  const entries: LibraryEntry[] = []
  // Neue, leere Rotation (eigener Layout-Container mit frei gestaltbaren Seiten).
  entries.push({ key: 'builtin:rotation', source: 'builtin', id: 'rotation', kind: 'rotation', category: 'rotation', defaultSize: [6, 6], preview: [] })
  for (const def of widgets) {
    entries.push({ key: `widget:${def.type}`, source: 'widget', id: def.type, kind: 'widget', category: widgetCategory(def), defaultSize: def.default_size, preview: [] })
  }
  for (const block of official) {
    entries.push({
      key: `official:${block.id}`,
      source: 'official',
      id: block.id,
      kind: block.kind,
      category: block.kind === 'region' ? 'templates' : (block.category as LibraryCategory),
      defaultSize: block.default_size,
      preview: previewOf(block.content),
    })
  }
  for (const block of custom) {
    entries.push({
      key: `custom:${block.id}`,
      source: 'custom',
      id: block.id,
      kind: block.kind,
      category: block.kind === 'rotation' ? 'rotation' : 'custom',
      name: block.name,
      defaultSize: block.default_size,
      preview: previewOf(block.content),
    })
  }
  return entries
}

/** Gruppiert gefilterte Einträge nach Kategorie (leere Kategorien entfallen, ausser «Eigene Bausteine»). */
export function groupLibrary(entries: LibraryEntry[], matches: (entry: LibraryEntry) => boolean, keepEmpty: LibraryCategory[] = []): { category: LibraryCategory; entries: LibraryEntry[] }[] {
  return LIBRARY_CATEGORIES.map((category) => ({ category, entries: entries.filter((e) => e.category === category && matches(e)) })).filter(
    (g) => g.entries.length > 0 || keepEmpty.includes(g.category),
  )
}

/** Inhalt eines Eintrags als unabhängige Kopie (nie eine Referenz auf Katalog oder Vorlage). */
export function contentOf(entry: LibraryEntry, widgets: Map<string, WidgetDefinition>, official: OfficialBlock[], custom: CustomBlock[], widgetContent: (def: WidgetDefinition) => LayoutContent, makeRotation?: () => LayoutContent): LayoutContent | null {
  if (entry.source === 'builtin') return makeRotation ? makeRotation() : null
  if (entry.source === 'widget') {
    const def = widgets.get(entry.id)
    return def ? widgetContent(def) : null
  }
  const source = entry.source === 'official' ? official.find((b) => b.id === entry.id)?.content : custom.find((b) => b.id === entry.id)?.content
  return source ? (JSON.parse(JSON.stringify(source)) as LayoutContent) : null
}
