import type { LayoutContent, LayoutDocument } from '@/api/displayLayout'

/** Alle im Dokument verwendeten Widget-Typen (auch in Gruppen, Rotationen und Seiten). */
export function collectWidgets(doc: LayoutDocument): string[] {
  const found: string[] = []
  const visit = (content: LayoutContent): void => {
    if (content.type === 'widget') found.push(content.widget)
    else if (content.type === 'group') content.items.forEach((i) => visit(i.content))
    else if (content.type === 'rotation') content.steps.forEach((s) => visit(s.content))
  }
  doc.variants.forEach((v) => visit(v.root))
  doc.pages.forEach((p) => p.items.forEach((i) => visit(i.content)))
  return found
}
