import { fetchPrintLayoutTemplateBytes, type PrintLayout } from '@/api/printLayouts'
import { printCanvasToBrotherQl } from '@/print/brotherQlUsb'
import { defaultPrintFace, type PrintFace } from '@/print/printFace'
import {
  buildLayoutPdf,
  downloadPdfBytes,
  printPdfBytes,
  renderLayoutCellToCanvas,
  sampleFromUnknown,
  type LayoutSample,
} from '@/print/renderPrintLayout'

type LayoutPrintRow = {
  label?: string
  name?: string
  public_url?: string
  public_code?: string | null
  event?: string
  place?: string
  drive?: string
}

export function isBrotherQlLayout(layout: PrintLayout): boolean {
  return layout.media.family === 'brother_ql'
}

export async function downloadCartLayoutPdf(
  departmentId: string,
  layout: PrintLayout,
  items: LayoutPrintRow[] | LayoutSample[],
  startIndex: number,
  printAfter = false,
  face: PrintFace = defaultPrintFace('label'),
): Promise<void> {
  const templateBytes = layout.has_template
    ? await fetchPrintLayoutTemplateBytes(departmentId, layout.id).catch(() => null)
    : null
  const bytes = await buildLayoutPdf({
    layout,
    startIndex,
    templateBytes,
    face,
    items: items.map((item) => sampleFromUnknown(item)),
  })
  if (printAfter) printPdfBytes(bytes)
  else downloadPdfBytes(bytes, `${layout.name}.pdf`)
}

export async function printCartLayoutToQl(
  device: USBDevice,
  layout: PrintLayout,
  items: LayoutPrintRow[] | LayoutSample[],
  face: PrintFace = defaultPrintFace('label'),
): Promise<void> {
  for (const item of items) {
    const canvas = await renderLayoutCellToCanvas(layout, sampleFromUnknown(item), 0, 300, face)
    await printCanvasToBrotherQl(device, canvas)
  }
}
