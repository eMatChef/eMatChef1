import type { PrintLayout, PrintLayoutField, PrintSheetCell, PrintSheetSpec } from '@/api/printLayouts'
import { defaultPrintFace, type PrintFace } from '@/print/printFace'
import { drawPrintFace } from '@/print/renderPrintFace'
import { sheetCellForItem, sheetPageCount } from '@/print/sheetPlacement'

export type LayoutSample = {
  label: string
  name?: string
  public_url: string
  public_code: string
  event?: string
  place?: string
  drive?: string
}

export function sampleFromUnknown(item: {
  label?: string
  name?: string
  public_url?: string
  public_code?: string | null
  event?: string
  place?: string
  drive?: string
}): LayoutSample {
  return {
    label: item.label || '',
    name: item.name || item.label || '',
    public_url: item.public_url || '',
    public_code: item.public_code || '',
    event: item.event || '',
    place: item.place || '',
    drive: item.drive || '',
  }
}

export function mmToPx(mm: number, pxPerMm: number): number {
  return mm * pxPerMm
}

export function fieldRect(field: PrintLayoutField, cell: PrintSheetCell): {
  x: number
  y: number
  w: number
  h: number
} {
  return {
    x: cell.x + (field.x / 100) * cell.w,
    y: cell.y + (field.y / 100) * cell.h,
    w: (field.w / 100) * cell.w,
    h: (field.h / 100) * cell.h,
  }
}

export async function renderPdfPageToCanvas(
  bytes: ArrayBuffer,
  canvas: HTMLCanvasElement,
  widthPx: number,
): Promise<void> {
  const pdfjs = await import('pdfjs-dist')
  const worker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url')
  pdfjs.GlobalWorkerOptions.workerSrc = worker.default
  const doc = await pdfjs.getDocument({ data: new Uint8Array(bytes) }).promise
  const page = await doc.getPage(1)
  const unscaled = page.getViewport({ scale: 1 })
  const scale = widthPx / unscaled.width
  const viewport = page.getViewport({ scale })
  canvas.width = Math.ceil(viewport.width)
  canvas.height = Math.ceil(viewport.height)
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  await page.render({ canvasContext: ctx, viewport, canvas }).promise
}

export async function renderLayoutCellToCanvas(
  layout: PrintLayout,
  item: LayoutSample,
  cellIndex = 0,
  dpi = 300,
  face: PrintFace = defaultPrintFace('label'),
): Promise<HTMLCanvasElement> {
  const cell = layout.cells[cellIndex] || layout.cells[0]
  if (!cell) throw new Error('Layout hat keine Zellen')
  const canvas = document.createElement('canvas')
  const pxPerMm = dpi / 25.4
  canvas.width = Math.max(32, Math.round(cell.w * pxPerMm))
  canvas.height = Math.max(32, Math.round(cell.h * pxPerMm))
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas ohne Kontext')
  await drawPrintFace(ctx, canvas.width, canvas.height, { layout, item, face })
  return canvas
}

function canvasPngBytes(canvas: HTMLCanvasElement): Uint8Array {
  const dataUrl = canvas.toDataURL('image/png')
  const raw = atob(dataUrl.split(',')[1] || '')
  const bytes = new Uint8Array(raw.length)
  for (let i = 0; i < raw.length; i += 1) bytes[i] = raw.charCodeAt(i)
  return bytes
}

export async function buildLayoutPdf(options: {
  layout: PrintLayout
  items: LayoutSample[]
  startIndex: number
  templateBytes?: ArrayBuffer | null
  face?: PrintFace
}): Promise<Uint8Array> {
  const { PDFDocument } = await import('pdf-lib')
  const spec = options.layout.sheet
  const cells = options.layout.cells
  const face = options.face || defaultPrintFace('label')
  const pagesNeeded = sheetPageCount(options.items.length, cells.length, options.startIndex)
  const mm = (v: number) => (v * 72) / 25.4

  let pdf: Awaited<ReturnType<typeof PDFDocument.create>>
  const includeTemplate = options.layout.include_template_on_print && options.templateBytes
  if (includeTemplate && options.templateBytes) {
    pdf = await PDFDocument.load(options.templateBytes)
    const first = pdf.getPage(0)
    while (pdf.getPageCount() < pagesNeeded) {
      const [copied] = await pdf.copyPages(pdf, [0])
      pdf.addPage(copied)
    }
    void first
  } else {
    pdf = await PDFDocument.create()
    for (let i = 0; i < pagesNeeded; i += 1) {
      pdf.addPage([mm(spec.sheet_width_mm), mm(spec.sheet_height_mm)])
    }
  }

  const pageHeight = mm(spec.sheet_height_mm)

  for (let i = 0; i < options.items.length; i += 1) {
    const item = options.items[i]
    const place = sheetCellForItem(i, cells.length, options.startIndex)
    const cell = cells[place.cellIndex]
    if (!cell) continue
    const page = pdf.getPage(place.pageIndex)
    const pageH = page.getHeight() || pageHeight
    const canvas = await renderLayoutCellToCanvas(options.layout, item, place.cellIndex, 220, face)
    const png = await pdf.embedPng(canvasPngBytes(canvas))
    page.drawImage(png, {
      x: mm(cell.x),
      y: pageH - mm(cell.y + cell.h),
      width: mm(cell.w),
      height: mm(cell.h),
    })
  }

  return pdf.save()
}

function pdfBytesToBlob(bytes: Uint8Array): Blob {
  const copy = new Uint8Array(bytes.byteLength)
  copy.set(bytes)
  return new Blob([copy as BlobPart], { type: 'application/pdf' })
}

export function downloadPdfBytes(bytes: Uint8Array, filename: string): void {
  const url = URL.createObjectURL(pdfBytesToBlob(bytes))
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

export function printPdfBytes(bytes: Uint8Array): void {
  const url = URL.createObjectURL(pdfBytesToBlob(bytes))
  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  iframe.style.cssText =
    'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none'
  document.body.appendChild(iframe)
  const cleanup = () => {
    try {
      if (iframe.parentNode) iframe.parentNode.removeChild(iframe)
    } catch {
      /* ignore */
    }
    URL.revokeObjectURL(url)
  }
  iframe.onload = () => {
    try {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    } catch {
      cleanup()
      return
    }
    window.setTimeout(cleanup, 120000)
  }
  iframe.src = url
}

export function specAspect(spec: PrintSheetSpec): number {
  return spec.sheet_width_mm / Math.max(1, spec.sheet_height_mm)
}
