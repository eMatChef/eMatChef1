import type { LayoutContent, LayoutDocument, LayoutItem, LayoutPage, LayoutVariant } from '@/api/displayLayout'

/** Elemente, die ein Variantenvorschaubild zeigt: die erste Seite bzw. Zone, die vom Wurzelinhalt aus erreichbar ist. */
export function firstVisibleItems(doc: LayoutDocument, variant: LayoutVariant | undefined): { rows: number; columns: number; items: LayoutItem[] } {
  const pages = new Map<string, LayoutPage>(doc.pages.map((p) => [p.id, p]))
  let content: LayoutContent | undefined = variant?.root
  for (let depth = 0; content && depth < 8; depth++) {
    if (content.type === 'rotation') {
      content = content.steps[0]?.content
    } else if (content.type === 'page') {
      const page = pages.get(content.page_id)
      return page ? { rows: page.rows, columns: page.columns ?? 12, items: page.items } : { rows: 12, columns: 12, items: [] }
    } else if (content.type === 'group') {
      return { rows: content.rows, columns: content.columns ?? 12, items: content.items }
    } else {
      return { rows: 12, columns: 12, items: [{ id: 'w', x: 0, y: 0, w: 12, h: 12, content }] }
    }
  }
  return { rows: 12, columns: 12, items: [] }
}

export function newId(prefix: string, taken: Iterable<string>): string {
  const used = new Set(taken)
  for (let i = 1; i < 10_000; i++) {
    const id = `${prefix}-${i}`
    if (!used.has(id)) return id
  }
  return `${prefix}-${Date.now()}`
}

/** Alle Element-IDs einer Item-Liste (für neue IDs innerhalb derselben Zone). */
export function itemIds(items: LayoutItem[]): string[] {
  return items.map((i) => i.id)
}

export function cloneLayout<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

/** Kurzbeschreibung des Inhalts für Beschriftungen (Typ des Widgets bzw. Art der Zone). */
export function contentKind(content: LayoutContent): string {
  return content.type === 'widget' ? content.widget : content.type
}
