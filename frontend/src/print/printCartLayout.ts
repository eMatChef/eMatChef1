import type { PrintCartItem } from '@/api/tasks'
import { fetchPrintLayoutTemplateBytes, type PrintLayout } from '@/api/printLayouts'
import { printCanvasToBrotherQl } from '@/print/brotherQlUsb'
import {
  buildLayoutPdf,
  downloadPdfBytes,
  printPdfBytes,
  renderLayoutCellToCanvas,
  sampleFromUnknown,
} from '@/print/renderPrintLayout'

export function isBrotherQlLayout(layout: PrintLayout): boolean {
  return layout.media.family === 'brother_ql'
}

export async function downloadCartLayoutPdf(
  departmentId: string,
  layout: PrintLayout,
  items: PrintCartItem[],
  startIndex: number,
  printAfter = false,
): Promise<void> {
  const templateBytes = layout.has_template
    ? await fetchPrintLayoutTemplateBytes(departmentId, layout.id).catch(() => null)
    : null
  const bytes = await buildLayoutPdf({
    layout,
    startIndex,
    templateBytes,
    items: items.map((item) => sampleFromUnknown(item)),
  })
  if (printAfter) printPdfBytes(bytes)
  else downloadPdfBytes(bytes, `${layout.name}.pdf`)
}

export async function printCartLayoutToQl(
  device: USBDevice,
  layout: PrintLayout,
  items: PrintCartItem[],
): Promise<void> {
  for (const item of items) {
    const canvas = await renderLayoutCellToCanvas(layout, sampleFromUnknown(item), 0)
    await printCanvasToBrotherQl(device, canvas)
  }
}
