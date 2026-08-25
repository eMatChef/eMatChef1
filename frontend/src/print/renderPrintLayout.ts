import type { PrintLayout, PrintLayoutField, PrintSheetCell, PrintSheetSpec } from '@/api/printLayouts'

export type LayoutSample = {
  label: string
  public_url: string
  public_code: string
}

export function sampleFromUnknown(item: {
  label?: string
  public_url?: string
  public_code?: string | null
}): LayoutSample {
  return {
    label: item.label || '',
    public_url: item.public_url || '',
    public_code: item.public_code || '',
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

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error('Bild konnte nicht geladen werden'))
    img.src = src
  })
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
): Promise<HTMLCanvasElement> {
  const QRCode = (await import('qrcode')).default
  const cell = layout.cells[cellIndex] || layout.cells[0]
  if (!cell) throw new Error('Layout hat keine Zellen')
  const canvas = document.createElement('canvas')
  const pxPerMm = dpi / 25.4
  canvas.width = Math.max(32, Math.round(cell.w * pxPerMm))
  canvas.height = Math.max(32, Math.round(cell.h * pxPerMm))
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas ohne Kontext')
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  for (const field of layout.fields) {
    const x = (field.x / 100) * canvas.width
    const y = (field.y / 100) * canvas.height
    const w = (field.w / 100) * canvas.width
    const h = (field.h / 100) * canvas.height
    if (field.type === 'qr' && item.public_url) {
      const dataUrl = await QRCode.toDataURL(item.public_url, { margin: 0, width: 512 })
      const img = await loadImage(dataUrl)
      const size = Math.min(w, h)
      ctx.drawImage(img, x, y + (h - size) / 2, size, size)
    } else {
      const text =
        field.key === 'public_code' ? item.public_code : field.key === 'public_url' ? item.public_url : item.label
      ctx.fillStyle = '#111827'
      ctx.font = `${Math.max(10, Math.round(h * 0.32))}px sans-serif`
      ctx.textBaseline = 'top'
      ctx.fillText(text || ' ', x + 2, y + 2, Math.max(8, w - 4))
    }
  }
  return canvas
}

export async function buildLayoutPdf(options: {
  layout: PrintLayout
  items: LayoutSample[]
  startIndex: number
  templateBytes?: ArrayBuffer | null
}): Promise<Uint8Array> {
  const { PDFDocument, StandardFonts, rgb } = await import('pdf-lib')
  const QRCode = (await import('qrcode')).default
  const spec = options.layout.sheet
  const cells = options.layout.cells
  const start = Math.max(0, Math.min(options.startIndex, Math.max(0, cells.length - 1)))
  const usable = cells.slice(start)
  const perPage = usable.length
  const pagesNeeded = Math.max(1, Math.ceil(options.items.length / perPage))
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

  const font = await pdf.embedFont(StandardFonts.Helvetica)
  const pageHeight = mm(spec.sheet_height_mm)

  for (let i = 0; i < options.items.length; i += 1) {
    const item = options.items[i]
    const pageIndex = Math.floor(i / perPage)
    const cell = usable[i % perPage]
    const page = pdf.getPage(pageIndex)
    const pageH = page.getHeight() || pageHeight
    for (const field of options.layout.fields) {
      const rect = fieldRect(field, cell)
      const x = mm(rect.x)
      const y = pageH - mm(rect.y + rect.h)
      const w = mm(rect.w)
      const h = mm(rect.h)
      if (field.type === 'qr' && item.public_url) {
        const dataUrl = await QRCode.toDataURL(item.public_url, { margin: 0, width: 512 })
        const png = await pdf.embedPng(dataUrl)
        const size = Math.min(w, h)
        page.drawImage(png, { x, y: y + (h - size) / 2, width: size, height: size })
      } else {
        const text = field.key === 'public_code' ? item.public_code : field.key === 'public_url' ? item.public_url : item.label
        const size = Math.max(6, Math.min(11, h * 0.35))
        page.drawText(text || ' ', {
          x: x + 1,
          y: y + h - size - 1,
          size,
          font,
          color: rgb(0.1, 0.1, 0.12),
          maxWidth: Math.max(8, w - 2),
        })
      }
    }
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
